<?php
/**
 * pages/verify_otp.php — Step 2 of two-step authentication.
 *
 * Validates the 6-digit OTP emailed during login.
 * Guards: requires $_SESSION['pending_2fa'] and $_SESSION['admin_id'].
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_helpers.php';

// ── Guard ────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['pending_2fa'])) {
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit;
}
if (!empty($_SESSION['2fa_verified'])) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$error    = '';
$otp_row  = null;   // current live OTP record

// ── Helper: load latest unused OTP for this admin ─────────────────────────
function get_live_otp(int $admin_id): ?array {
    return query_one(
        'SELECT id, otp_hash, expires_at, attempts,
                TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS secs_remaining
         FROM admin_otps
         WHERE admin_id = ? AND used = 0
         ORDER BY id DESC LIMIT 1',
        'i', [$admin_id]
    );
}

// ── Resend GET handler ────────────────────────────────────────────────────────
if (isset($_GET['resend'])) {
    // Honour 60-second cooldown
    $resend_after = (int)($_SESSION['otp_resend_after'] ?? 0);
    if (time() < $resend_after) {
        flash('error', 'Please wait before requesting another code.');
        header('Location: ' . BASE_URL . 'pages/verify_otp.php');
        exit;
    }

    $admin = query_one('SELECT full_name, email FROM admins WHERE id = ? LIMIT 1', 'i', [$admin_id]);
    if (!$admin) {
        session_unset(); session_destroy();
        header('Location: ' . BASE_URL . 'pages/login.php');
        exit;
    }

    $otp_plain = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_hash  = hash('sha256', $otp_plain);

    query_exec('UPDATE admin_otps SET used = 1 WHERE admin_id = ? AND used = 0', 'i', [$admin_id]);
    query_exec(
        'INSERT INTO admin_otps (admin_id, otp_hash, expires_at, used, attempts, created_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), 0, 0, NOW())',
        'isi', [$admin_id, $otp_hash, OTP_EXPIRY_MINUTES]
    );

    $_SESSION['otp_resend_after'] = time() + 60;

    ['html' => $html_body, 'text' => $_otp_txt] = render_email_template('otp_code', [
        'admin_name'     => $admin['full_name'],
        'otp_code'       => $otp_plain,
        'expiry_minutes' => OTP_EXPIRY_MINUTES,
        'site_name'      => SITE_NAME,
        'admin_email'    => $admin['email'],
        'sent_at'        => date('d M Y, g:i A'),
        'year'           => date('Y'),
    ]);
    send_email($admin['email'], 'Your SVMS verification code', $html_body, $_otp_txt);
    log_action('otp_resent', $admin_id);

    flash('info', 'A new verification code has been sent to your email.');
    header('Location: ' . BASE_URL . 'pages/verify_otp.php');
    exit;
}

// ── POST: OTP submission ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    // Rate limit: 10 OTP attempts per minute per admin
    if (!rl_check('otp:' . $admin_id, 10)) {
        http_response_code(429);
        $error = 'Too many attempts. Please wait a moment.';
        goto render;
    }

    // Collect and concatenate the 6 digit fields
    $entered = '';
    for ($i = 1; $i <= 6; $i++) {
        $entered .= preg_replace('/[^0-9]/', '', substr(trim($_POST['d' . $i] ?? ''), 0, 1));
    }
    // Accept combined hidden field as fallback
    if (strlen($entered) < 6) {
        $entered = preg_replace('/[^0-9]/', '', trim($_POST['otp'] ?? ''));
    }

    if (!preg_match('/^[0-9]{6}$/', $entered)) {
        $error = 'Please enter all 6 digits.';
    } else {
        $otp_row = get_live_otp($admin_id);

        if (!$otp_row) {
            $error = 'No active code found — please request a new one.';
        } elseif ((int)$otp_row['secs_remaining'] <= 0) {
            query_exec('UPDATE admin_otps SET used = 1 WHERE id = ?', 'i', [(int)$otp_row['id']]);
            $error = 'Code expired — please request a new one.';
        } elseif ((int)$otp_row['attempts'] >= 5) {
            query_exec('UPDATE admin_otps SET used = 1 WHERE id = ?', 'i', [(int)$otp_row['id']]);
            $error = 'Too many wrong attempts — request a new code.';
        } elseif (hash_equals($otp_row['otp_hash'], hash('sha256', $entered))) {
            // ── Correct OTP ─────────────────────────────────────────────────
            query_exec('UPDATE admin_otps SET used = 1 WHERE id = ?', 'i', [(int)$otp_row['id']]);
            $_SESSION['2fa_verified'] = true;
            unset($_SESSION['pending_2fa'], $_SESSION['otp_resend_after']);
            log_action('login_2fa_ok', $admin_id);
            header('Location: ' . BASE_URL . 'pages/dashboard.php');
            exit;
        } else {
            // ── Wrong OTP ────────────────────────────────────────────────────
            $new_attempts = (int)$otp_row['attempts'] + 1;
            query_exec('UPDATE admin_otps SET attempts = ? WHERE id = ?', 'ii', [$new_attempts, (int)$otp_row['id']]);
            $remaining_tries = max(0, 5 - $new_attempts);
            $error = 'Incorrect code. ' . ($remaining_tries > 0
                ? $remaining_tries . ' attempt' . ($remaining_tries === 1 ? '' : 's') . ' remaining.'
                : 'Request a new code.');
        }
    }
}

// ── Load live OTP for countdown (if not already loaded by POST) ───────────
render:
if (!$otp_row) {
    $otp_row = get_live_otp($admin_id);
}

$otp_secs_remaining = max(0, (int)($otp_row['secs_remaining'] ?? 0));
$resend_cooldown    = max(0, (int)(($_SESSION['otp_resend_after'] ?? 0) - time()));
$admin_email        = e($_SESSION['admin_email'] ?? '');

// Mask email for display: show first 2 chars + *** + domain
$email_display = '';
if ($admin_email) {
    [$local, $domain] = explode('@', $admin_email, 2) + ['', ''];
    $email_display = substr($local, 0, 2) . '***@' . $domain;
}
?>
<!DOCTYPE html>
<html
  lang="<?= e($_SESSION['lang'] ?? DEFAULT_LANG) ?>"
  dir="<?= (($_SESSION['lang'] ?? DEFAULT_LANG) === 'ur') ? 'rtl' : 'ltr' ?>"
  data-theme="<?= e($_SESSION['theme'] ?? DEFAULT_THEME) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#1a3c5e">
  <title>Verify Identity — <?= e(SITE_NAME) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/img/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tokens.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/base.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/forms.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/themes.css">
  <script>(function(){var c=document.cookie.match(/(?:^|; )svms_theme=([^;]*)/);if(c)document.documentElement.setAttribute('data-theme',decodeURIComponent(c[1]));})();</script>
  <style>
    html, body { height: 100%; }
    body { min-height:100vh; margin:0; display:flex; align-items:stretch; background:#f0f4f8; }

    /* Split layout */
    .auth-split { display:flex; width:100%; min-height:100vh; }
    .auth-brand {
      flex:0 0 50%;
      background:linear-gradient(135deg,var(--primary) 0%,#0D2137 100%);
      display:flex;flex-direction:column;align-items:center;justify-content:center;
      padding:60px 48px;color:#fff;text-align:center;position:relative;overflow:hidden;
    }
    .auth-brand::before { content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(46,117,182,.35) 0%,transparent 70%);pointer-events:none; }
    .auth-brand-logo { width:88px;height:88px;background:rgba(255,255,255,.12);border-radius:24px;display:flex;align-items:center;justify-content:center;margin-bottom:28px;border:1px solid rgba(255,255,255,.18);position:relative; }
    .auth-brand-logo img { width:52px;height:52px; }
    .auth-brand h2 { font-size:28px;font-weight:800;letter-spacing:-.5px;color:#fff;margin:0 0 10px;position:relative; }
    .auth-brand .tagline { font-size:15px;color:rgba(255,255,255,.65);margin:0 0 40px;max-width:280px;line-height:1.6;position:relative; }
    .auth-features { list-style:none;padding:0;margin:0;text-align:left;width:100%;max-width:300px;position:relative; }
    .auth-features li { display:flex;align-items:center;gap:12px;padding:10px 0;font-size:14px;color:rgba(255,255,255,.82);border-bottom:1px solid rgba(255,255,255,.08); }
    .auth-features li:last-child { border-bottom:none; }
    .feat-icon { width:32px;height:32px;background:rgba(255,255,255,.12);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0; }

    /* Form panel */
    .auth-form-panel { flex:0 0 50%;background:var(--card);display:flex;align-items:center;justify-content:center;padding:48px 40px; }
    .auth-card { width:100%;max-width:420px;animation:cardSlideIn 350ms cubic-bezier(.16,1,.3,1) both; }
    @keyframes cardSlideIn { from{opacity:0;transform:translateY(28px);}to{opacity:1;transform:translateY(0);} }

    .otp-icon-wrap { width:72px;height:72px;background:rgba(46,117,182,.12);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:32px;color:var(--secondary); }
    .auth-heading    { font-size:24px;font-weight:800;color:var(--text);margin:0 0 6px;letter-spacing:-.3px;text-align:center; }
    .auth-subheading { font-size:14px;color:var(--text-muted);margin:0 0 28px;text-align:center;line-height:1.5; }

    /* 6-digit OTP boxes */
    .otp-boxes { display:flex;gap:10px;justify-content:center;margin-bottom:8px; }
    .otp-box {
      width:56px;height:56px;
      border:2px solid var(--border);
      border-radius:var(--radius-md);
      background:var(--surface);
      color:var(--text);
      font-size:28px;
      font-family:'Courier New',Courier,monospace;
      font-weight:700;
      text-align:center;
      outline:none;
      transition:border-color .15s,box-shadow .15s;
      caret-color:transparent;
    }
    .otp-box:focus { border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,60,94,.15); }
    .otp-box:focus-visible { outline:2px solid var(--secondary);outline-offset:2px; }
    .otp-box.filled { border-color:var(--primary);background:rgba(26,60,94,.06); }

    @keyframes otpShake {
      0%,100%{transform:translateX(0);}
      15%{transform:translateX(-8px);}
      30%{transform:translateX(8px);}
      45%{transform:translateX(-8px);}
      60%{transform:translateX(8px);}
      75%{transform:translateX(-4px);}
      90%{transform:translateX(4px);}
    }
    .otp-boxes.shake { animation:otpShake 480ms ease; }

    /* Countdown / status row */
    .otp-status { font-size:13px;color:var(--text-muted);text-align:center;margin-bottom:20px;min-height:20px; }
    .otp-status .cd  { font-variant-numeric:tabular-nums;font-weight:600;color:var(--primary); }
    .otp-status .expired { color:var(--danger);font-weight:600; }

    /* Submit button */
    .btn-auth {
      width:100%;padding:13px;background:var(--primary);color:#fff;border:none;
      border-radius:var(--radius-md);font-size:15px;font-weight:700;font-family:inherit;
      cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
      transition:background .15s,transform .1s;letter-spacing:.2px;
    }
    .btn-auth:hover:not(:disabled) { background:#152e4d;box-shadow:0 4px 14px rgba(26,60,94,.35); }
    .btn-auth:active:not(:disabled) { transform:scale(.98); }
    .btn-auth:disabled { opacity:.7;cursor:not-allowed; }
    .btn-auth:focus-visible { outline:2px solid var(--secondary);outline-offset:2px; }
    @keyframes btnPulse { 0%,100%{box-shadow:0 0 0 0 rgba(26,60,94,.4);}50%{box-shadow:0 0 0 8px rgba(26,60,94,0);} }
    .btn-auth.submitting { animation:btnPulse 1s ease infinite; }
    .spinner { width:18px;height:18px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:spin .65s linear infinite;flex-shrink:0; }
    @keyframes spin { to{transform:rotate(360deg);} }

    /* Alerts */
    .auth-alert { display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:var(--radius-md);font-size:14px;margin-bottom:16px;line-height:1.5; }
    .auth-alert i { margin-top:1px;flex-shrink:0; }
    .auth-alert.error { background:#fef2f2;color:#991b1b;border:1px solid #fecaca; }
    .auth-alert.info  { background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe; }
    [data-theme="dark"] .auth-alert.error { background:rgba(220,38,38,.12);color:#fca5a5;border-color:rgba(220,38,38,.3); }
    [data-theme="dark"] .auth-alert.info  { background:rgba(59,130,246,.12);color:#93c5fd;border-color:rgba(59,130,246,.3); }

    /* Resend + back links */
    .auth-links { margin-top:20px;text-align:center;font-size:13px;color:var(--text-muted); }
    .auth-links a { color:var(--primary);text-decoration:none; }
    .auth-links a:hover { text-decoration:underline; }
    .auth-links a.disabled { pointer-events:none;opacity:.5;color:var(--text-muted); }
    .resend-cd { font-variant-numeric:tabular-nums;font-weight:600; }

    /* Mobile */
    @media (max-width:768px) {
      .auth-split { flex-direction:column; }
      .auth-brand { flex:none;height:200px;padding:20px 24px;flex-direction:row;justify-content:flex-start;gap:16px;text-align:left; }
      .auth-brand::before { display:none; }
      .auth-brand-logo { width:52px;height:52px;border-radius:14px;margin-bottom:0;flex-shrink:0; }
      .auth-brand-logo img { width:30px;height:30px; }
      .auth-brand h2 { font-size:18px;margin-bottom:4px; }
      .auth-brand .tagline { font-size:12px;margin-bottom:0; }
      .auth-features { display:none; }
      .auth-brand-text { display:flex;flex-direction:column; }
      .auth-form-panel { flex:1;padding:32px 24px;align-items:flex-start;padding-top:36px; }
      .otp-box { width:46px;height:46px;font-size:22px; }
    }
    @media (max-width:400px) { .auth-form-panel{padding:24px 16px;} .otp-box{width:40px;height:40px;gap:6px;} .otp-boxes{gap:6px;} }
  </style>
</head>
<body>
<div class="auth-split">

  <!-- Left: Brand panel -->
  <div class="auth-brand" aria-hidden="true">
    <div class="auth-brand-logo">
      <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="">
    </div>
    <div class="auth-brand-text">
      <h2><?= e(SITE_NAME) ?></h2>
      <p class="tagline">Secure, intelligent visitor tracking for modern facilities.</p>
    </div>
    <ul class="auth-features">
      <li><span class="feat-icon"><i class="bi bi-shield-lock-fill"></i></span>Two-factor authentication</li>
      <li><span class="feat-icon"><i class="bi bi-envelope-check-fill"></i></span>Email-delivered OTP codes</li>
      <li><span class="feat-icon"><i class="bi bi-clock-history"></i></span>Codes expire in <?= OTP_EXPIRY_MINUTES ?> minutes</li>
    </ul>
  </div>

  <!-- Right: Form panel -->
  <div class="auth-form-panel">
    <div class="auth-card" role="main">

      <div class="otp-icon-wrap" aria-hidden="true">
        <i class="bi bi-shield-lock-fill"></i>
      </div>
      <h1 class="auth-heading">Verify your identity</h1>
      <p class="auth-subheading">
        We sent a 6-digit code to
        <?php if ($email_display): ?>
          <strong><?= e($email_display) ?></strong>
        <?php else: ?>
          your email
        <?php endif; ?>.
      </p>

      <?php foreach ($_SESSION['flash'] ?? [] as $f): ?>
        <div class="auth-alert <?= e($f['type'] ?? 'info') ?>" role="alert" aria-live="polite">
          <i class="bi bi-info-circle-fill"></i>
          <?= e($f['message'] ?? '') ?>
        </div>
      <?php endforeach; ?>
      <?php unset($_SESSION['flash']); ?>

      <?php if ($error): ?>
        <div class="auth-alert error" role="alert" aria-live="polite" id="otp-error-banner">
          <i class="bi bi-x-circle-fill"></i>
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="otp-form" novalidate>
        <?php csrf_field() ?>

        <!-- 6 individual digit boxes -->
        <div class="otp-boxes" id="otp-boxes" role="group" aria-label="One-time password, 6 digits">
          <?php for ($i = 1; $i <= 6; $i++): ?>
            <input
              type="text"
              inputmode="numeric"
              maxlength="1"
              pattern="[0-9]"
              class="otp-box"
              id="otp-d<?= $i ?>"
              name="d<?= $i ?>"
              aria-label="Digit <?= $i ?> of 6"
              autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>"
              value=""
            >
          <?php endfor; ?>
        </div>
        <!-- Hidden combined field (fallback) -->
        <input type="hidden" name="otp" id="otp-combined">

        <!-- Live countdown -->
        <div class="otp-status" id="otp-status" aria-live="polite">
          <?php if ($otp_secs_remaining > 0): ?>
            Code expires in <span class="cd" id="otp-cd"><?= (int)floor($otp_secs_remaining/60) ?>:<?= str_pad((string)($otp_secs_remaining%60),2,'0',STR_PAD_LEFT) ?></span>
          <?php else: ?>
            <span class="expired">Code expired — request a new one below.</span>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn-auth" id="submit-btn">
          <i class="bi bi-check-circle-fill" id="btn-icon"></i>
          <span id="btn-label">Verify code</span>
        </button>
      </form>

      <div class="auth-links">
        <p>
          Didn't receive it?
          <a href="<?= BASE_URL ?>pages/verify_otp.php?resend=1"
             id="resend-link"
             <?= $resend_cooldown > 0 ? 'class="disabled" aria-disabled="true"' : '' ?>>
            Resend code<?php if ($resend_cooldown > 0): ?> (<span class="resend-cd" id="resend-cd"><?= $resend_cooldown ?></span>s)<?php endif; ?>
          </a>
        </p>
        <p>
          <a href="<?= BASE_URL ?>pages/login.php?_clear=1" id="switch-account-link">
            ← Use a different account
          </a>
        </p>
      </div>

    </div>
  </div>

</div>

<script src="<?= BASE_URL ?>assets/js/theme.js"></script>
<script>
(function(){
  'use strict';

  var boxes       = Array.from(document.querySelectorAll('.otp-box'));
  var combined    = document.getElementById('otp-combined');
  var form        = document.getElementById('otp-form');
  var boxesWrap   = document.getElementById('otp-boxes');
  var submitBtn   = document.getElementById('submit-btn');
  var btnLabel    = document.getElementById('btn-label');
  var hasError    = <?= $error ? 'true' : 'false' ?>;

  /* ── Digit input: auto-advance, backspace, paste ─────────────────────── */
  boxes.forEach(function(box, idx){
    box.addEventListener('input', function(){
      // Keep only first digit
      this.value = this.value.replace(/[^0-9]/g, '').slice(-1);
      if(this.value) this.classList.add('filled');
      else           this.classList.remove('filled');

      if(this.value && idx < boxes.length - 1){
        boxes[idx+1].focus();
      }
      if(idx === boxes.length - 1 && this.value){
        // Auto-submit on last digit
        triggerSubmit();
      }
    });

    box.addEventListener('keydown', function(e){
      if(e.key === 'Backspace'){
        if(this.value === '' && idx > 0){
          boxes[idx-1].value = '';
          boxes[idx-1].classList.remove('filled');
          boxes[idx-1].focus();
        } else {
          this.value = '';
          this.classList.remove('filled');
        }
        e.preventDefault();
      }
      if(e.key === 'ArrowLeft' && idx > 0)    { boxes[idx-1].focus(); e.preventDefault(); }
      if(e.key === 'ArrowRight' && idx < boxes.length-1){ boxes[idx+1].focus(); e.preventDefault(); }
    });

    box.addEventListener('focus', function(){ this.select(); });
  });

  /* ── Paste handler: distribute 6 chars ──────────────────────────────── */
  boxes[0].addEventListener('paste', function(e){
    e.preventDefault();
    var text = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g,'').slice(0,6);
    text.split('').forEach(function(ch, i){
      if(boxes[i]){
        boxes[i].value = ch;
        boxes[i].classList.add('filled');
      }
    });
    var nextEmpty = boxes.findIndex(function(b){ return !b.value; });
    (nextEmpty >= 0 ? boxes[nextEmpty] : boxes[boxes.length-1]).focus();
    if(text.length === 6) triggerSubmit();
  });

  /* ── Combine digits → hidden field, then submit ──────────────────────── */
  function triggerSubmit(){
    var code = boxes.map(function(b){ return b.value; }).join('');
    if(code.length !== 6) return;
    combined.value = code;
    submitBtn.disabled = true;
    submitBtn.classList.add('submitting');
    document.getElementById('btn-icon').outerHTML = '<span class="spinner" id="btn-icon"></span>';
    btnLabel.textContent = 'Verifying\u2026';
    form.submit();
  }

  form.addEventListener('submit', function(){
    combined.value = boxes.map(function(b){ return b.value; }).join('');
  });

  /* ── Shake on error ──────────────────────────────────────────────────── */
  if(hasError){
    boxesWrap.classList.add('shake');
    // Clear filled boxes after shake so user can retry
    setTimeout(function(){
      boxesWrap.classList.remove('shake');
      boxes.forEach(function(b){ b.value=''; b.classList.remove('filled'); });
      boxes[0].focus();
    }, 500);
  } else {
    boxes[0].focus();
  }

  /* ── OTP countdown (from PHP) ────────────────────────────────────────── */
  var cdEl      = document.getElementById('otp-cd');
  var statusEl  = document.getElementById('otp-status');
  var secsLeft  = <?= (int)$otp_secs_remaining ?>;

  if(cdEl && secsLeft > 0){
    var cdTimer = setInterval(function(){
      secsLeft--;
      if(secsLeft <= 0){
        clearInterval(cdTimer);
        statusEl.innerHTML = '<span class="expired">Code expired \u2014 request a new one below.</span>';
        submitBtn.disabled = true;
        return;
      }
      var m = Math.floor(secsLeft/60), s = secsLeft%60;
      cdEl.textContent = m + ':' + (s<10?'0':'') + s;
    }, 1000);
  }

  /* ── Resend cooldown timer ───────────────────────────────────────────── */
  var resendLink = document.getElementById('resend-link');
  var resendCdEl = document.getElementById('resend-cd');
  var resendLeft = <?= (int)$resend_cooldown ?>;

  if(resendCdEl && resendLeft > 0){
    var rTimer = setInterval(function(){
      resendLeft--;
      if(resendLeft <= 0){
        clearInterval(rTimer);
        resendLink.classList.remove('disabled');
        resendLink.removeAttribute('aria-disabled');
        resendLink.textContent = 'Resend code';
        return;
      }
      resendCdEl.textContent = resendLeft;
    }, 1000);
  }

  /* ── 'Use a different account': clear pending session state ─────────── */
  document.getElementById('switch-account-link').addEventListener('click', function(e){
    e.preventDefault();
    // POST-free session clear via a dedicated redirect param
    window.location.href = '<?= BASE_URL ?>logout.php?switch=1';
  });

})();
</script>
</body>
</html>
