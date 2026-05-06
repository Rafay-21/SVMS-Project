<?php
/**
 * pages/login.php — Step 1 of two-step authentication.
 *
 * Flow: Email + Password → OTP email → verify_otp.php → dashboard.php
 * Depends on: config.php (bootstraps session, CSRF, db_functions, flash, i18n)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_helpers.php';

// ── Already fully authenticated ───────────────────────────────────────────────
if (isset($_SESSION['admin_id']) && !empty($_SESSION['2fa_verified'])) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit;
}

// ── Initialise state ─────────────────────────────────────────────────────────
$error          = '';
$lock_remaining = 0;   // seconds remaining on lockout (0 = not locked)

// ── URL message banners ───────────────────────────────────────────────────────
$msg_map = [
    'logged_out'      => ['success', 'You have been signed out successfully.'],
    'session_expired' => ['error',   'Your session has expired. Please sign in again.'],
    'unauthorized'    => ['error',   'Please sign in to continue.'],
    'otp_failed'      => ['error',   'OTP verification failed. Please sign in again.'],
];
$url_msg = $_GET['msg'] ?? '';
$banner  = $msg_map[$url_msg] ?? null;   // ['type', 'text'] or null

// ── POST: Login form submission ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF
    csrf_validate();

    // 2. IP-based rate limit: 10 attempts/minute hard ceiling (before session lock)
    $rl_ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
    if (!rl_check('login:' . $rl_ip, 10)) {
        http_response_code(429);
        $error = 'Too many requests. Please wait a moment before trying again.';
        goto render;
    }

    // 3. Brute-force check (session-based, no DB)
    if (!isset($_SESSION['login_attempts']) || !is_array($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = ['count' => 0, 'first_attempt_at' => 0, 'lock_until' => 0];
    }
    $la  = &$_SESSION['login_attempts'];
    $now = time();

    if (isset($la['lock_until']) && $la['lock_until'] > $now) {
        // Still locked — reject, show countdown only
        $lock_remaining = (int)($la['lock_until'] - $now);
        $mins  = (int)floor($lock_remaining / 60);
        $secs  = $lock_remaining % 60;
        $error = sprintf('Too many failed attempts. Try again in %d:%02d.', $mins, $secs);
        goto render;
    }

    // Reset window if last attempt was > 10 minutes ago
    if (($now - ($la['first_attempt_at'] ?? 0)) > 600) {
        $la = ['count' => 0, 'first_attempt_at' => $now, 'lock_until' => 0];
    }

    // 3. Input validation
    $raw_email    = trim($_POST['email'] ?? '');
    $raw_password = $_POST['password'] ?? '';

    if (!filter_var($raw_email, FILTER_VALIDATE_EMAIL) || $raw_password === '') {
        $la['count']++;
        $la['first_attempt_at'] = $la['first_attempt_at'] ?: $now;
        if ($la['count'] >= 5) {
            $la['lock_until'] = $now + 900;
            $lock_remaining   = 900;
        }
        $error = 'Invalid email or password.';
        goto render;
    }

    // 4. Lookup admin by email (prepared statement)
    $admin = query_one(
        'SELECT id, name, email, password, role_id, is_active, otp_enabled
         FROM admins WHERE email = ? LIMIT 1',
        's',
        [$raw_email]
    );

    // 5. Validate: existence, active, password
    if (!$admin || !(bool)$admin['is_active'] || !password_verify($raw_password, $admin['password'])) {
        // Timing-safe delay 200–500 ms (mitigates timing oracle)
        usleep(random_int(200000, 500000));

        $la['first_attempt_at'] = $la['first_attempt_at'] ?: $now;
        $la['count']++;
        if ($la['count'] >= 5) {
            $la['lock_until'] = $now + 900;
            $lock_remaining   = 900;
            $error = sprintf('Too many failed attempts. Try again in %d:%02d.', (int)floor(900/60), 900 % 60);
        } else {
            $error = 'Invalid email or password.';
        }
        goto render;
    }

    // 6. Success — clear brute-force counter
    $la = ['count' => 0, 'first_attempt_at' => 0, 'lock_until' => 0];
    unset($la);

    // Prevent session fixation
    session_regenerate_id(true);

    // 7. Persist minimal admin info into session
    $_SESSION['admin_id']    = (int)$admin['id'];
    $_SESSION['admin_name']  = $admin['name'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['role_id']     = (int)$admin['role_id'];
    $_SESSION['login_time']  = time();
    $_SESSION['2fa_verified']  = false;
    $_SESSION['pending_2fa']   = true;
    // Pre-cache permissions so RBAC works immediately after OTP verification
    cache_permissions();

    // If 2FA is disabled (dev/testing), skip OTP and go straight to dashboard
    if (!ENABLE_2FA) {
        $_SESSION['2fa_verified'] = true;
        unset($_SESSION['pending_2fa']);
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit;
    }

    // 8. Generate 6-digit OTP (SHA-256 hash stored in DB)
    $otp_plain = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_hash  = hash('sha256', $otp_plain);
    $admin_id  = (int)$admin['id'];

    // Invalidate any previous unused OTPs for this admin
    query_exec(
        'UPDATE admin_otps SET used = 1 WHERE admin_id = ? AND used = 0',
        'i', [$admin_id]
    );

    // Insert new OTP record
    query_exec(
        'INSERT INTO admin_otps (admin_id, otp_hash, expires_at, used, attempts, created_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), 0, 0, NOW())',
        'isi', [$admin_id, $otp_hash, OTP_EXPIRY_MINUTES]
    );

    // Store resend cooldown (60 s) in session
    $_SESSION['otp_resend_after'] = time() + 60;

    // 9. Build and send OTP email
    ['html' => $html_body, 'text' => $_otp_txt] = render_email_template('otp_code', [
        'admin_name'     => $admin['name'],
        'otp_code'       => $otp_plain,
        'expiry_minutes' => OTP_EXPIRY_MINUTES,
        'site_name'      => SITE_NAME,
        'admin_email'    => $admin['email'],
        'sent_at'        => date('d M Y, g:i A'),
        'year'           => date('Y'),
    ]);

    $email_result = send_email(
        $admin['email'],
        'Your SVMS verification code',
        $html_body, $_otp_txt
    );

    if (!($email_result['ok'] ?? false) && !($email_result['queued'] ?? false)) {
        flash('info', 'OTP could not be emailed — check your spam folder or contact your administrator.');
    }

    // 10. Update last login meta & audit log
    $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
    query_exec(
        'UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?',
        'si', [$ip, $admin_id]
    );
    log_action('login_password_ok', $admin_id);

    header('Location: ' . BASE_URL . 'pages/verify_otp.php');
    exit;
}

render:
// i18n for login page (lang already set in config.php via i18n.php)
$_login_lang = current_lang();
$_login_dir  = is_rtl() ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?= e($_login_lang) ?>" dir="<?= e($_login_dir) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#1a3c5e">
  <title><?= e(t('login.sign_in')) ?> — <?= e(SITE_NAME) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/img/logo.svg">
  <link rel="manifest" href="<?= BASE_URL ?>manifest.json">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/fonts.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tokens.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/base.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/forms.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/themes.css">
  <?php if (is_rtl()): ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/rtl-overrides.css">
  <?php endif; ?>
  <!-- Anti-FOUC: apply theme from localStorage -->
  <script>
  (function(){
    try{
      var m=localStorage.getItem('svms_theme_mode')||'system';
      var t=m==='dark'?'dark':m==='light'?'light':
        (window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
      var r=document.documentElement;
      r.setAttribute('data-theme',t);
      r.setAttribute('data-theme-mode',m);
      r.classList.add('preload');
    }catch(e){}
  })();
  </script>
  <script>
    window.SVMS_LANG_STRINGS = <?= json_encode($LANG ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.BASE_URL = <?= json_encode(BASE_URL) ?>;
    window.SVMS_LANG = <?= json_encode($_login_lang) ?>;
    window.SVMS_RTL  = <?= json_encode(is_rtl()) ?>;
  </script>
  <style>
    html, body { height: 100%; }
    body {
      min-height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      align-items: stretch;
      background: #f0f4f8;
    }
    /* ── Split wrapper */
    .auth-split { display: flex; width: 100%; min-height: 100vh; }
    /* ── Brand panel */
    .auth-brand {
      flex: 0 0 50%;
      background: linear-gradient(135deg, var(--primary) 0%, #0D2137 100%);
      display: flex; flex-direction: column; align-items: center;
      justify-content: center; padding: 60px 48px; color: #fff;
      text-align: center; position: relative; overflow: hidden;
    }
    .auth-brand::before {
      content: ''; position: absolute; inset: 0;
      background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(46,117,182,.35) 0%, transparent 70%);
      pointer-events: none;
    }
    .auth-brand-logo {
      width: 88px; height: 88px; background: rgba(255,255,255,.12);
      border-radius: 24px; display: flex; align-items: center;
      justify-content: center; margin-bottom: 28px;
      border: 1px solid rgba(255,255,255,.18); position: relative;
    }
    .auth-brand-logo img { width: 52px; height: 52px; }
    .auth-brand h2 { font-size: 28px; font-weight: 800; letter-spacing: -.5px; color: #fff; margin: 0 0 10px; position: relative; }
    .auth-brand .tagline { font-size: 15px; color: rgba(255,255,255,.65); margin: 0 0 40px; max-width: 280px; line-height: 1.6; position: relative; }
    .auth-features { list-style: none; padding: 0; margin: 0; text-align: left; width: 100%; max-width: 300px; position: relative; }
    .auth-features li { display: flex; align-items: center; gap: 12px; padding: 10px 0; font-size: 14px; color: rgba(255,255,255,.82); border-bottom: 1px solid rgba(255,255,255,.08); }
    .auth-features li:last-child { border-bottom: none; }
    .feat-icon { width: 32px; height: 32px; background: rgba(255,255,255,.12); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
    /* ── Form panel */
    .auth-form-panel { flex: 0 0 50%; background: var(--card); display: flex; align-items: center; justify-content: center; padding: 48px 40px; }
    .auth-card { width: 100%; max-width: 420px; animation: cardSlideIn 350ms cubic-bezier(.16,1,.3,1) both; }
    @keyframes cardSlideIn { from { opacity: 0; transform: translateY(28px); } to { opacity: 1; transform: translateY(0); } }
    .auth-heading    { font-size: 26px; font-weight: 800; color: var(--text); margin: 0 0 6px; letter-spacing: -.4px; }
    .auth-subheading { font-size: 14px; color: var(--text-muted); margin: 0 0 28px; }
    /* ── Form groups */
    .form-group   { margin-bottom: 20px; }
    .form-label   { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
    .form-label .req { color: var(--danger); margin-left: 2px; }
    .input-wrap   { position: relative; display: flex; align-items: center; }
    .input-lead-icon { position: absolute; left: 14px; color: var(--text-muted); font-size: 16px; pointer-events: none; z-index: 1; }
    .input-wrap input {
      flex: 1; width: 100%; padding: 11px 44px 11px 40px;
      border: 1.5px solid var(--border); border-radius: var(--radius-md);
      background: var(--surface); color: var(--text); font-size: 15px;
      font-family: inherit; transition: border-color .15s, box-shadow .15s; outline: none;
    }
    .input-wrap input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,60,94,.12); }
    .input-wrap input:focus-visible { outline: 2px solid var(--secondary); outline-offset: 2px; }
    .eye-toggle {
      position: absolute; right: 12px; background: none; border: none;
      cursor: pointer; color: var(--text-muted); font-size: 16px; padding: 4px;
      border-radius: 4px; line-height: 1; display: flex; align-items: center;
    }
    .eye-toggle:focus-visible { outline: 2px solid var(--secondary); outline-offset: 2px; }
    .eye-toggle:hover { color: var(--text); }
    /* ── Remember / forgot row */
    .auth-row-meta { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; font-size: 13px; }
    .check-label { display: flex; align-items: center; gap: 7px; cursor: pointer; color: var(--text); user-select: none; }
    .check-label input[type="checkbox"] { width: 15px; height: 15px; accent-color: var(--primary); cursor: pointer; }
    .link-muted { color: var(--text-muted); text-decoration: none; }
    .link-muted:hover { color: var(--primary); text-decoration: underline; }
    /* ── Submit button */
    .btn-auth {
      width: 100%; padding: 13px; background: var(--primary); color: #fff;
      border: none; border-radius: var(--radius-md); font-size: 15px;
      font-weight: 700; font-family: inherit; cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      transition: background .15s, transform .1s, box-shadow .15s; letter-spacing: .2px;
    }
    .btn-auth:hover:not(:disabled) { background: #152e4d; box-shadow: 0 4px 14px rgba(26,60,94,.35); }
    .btn-auth:active:not(:disabled) { transform: scale(.98); }
    .btn-auth:disabled { opacity: .7; cursor: not-allowed; }
    .btn-auth:focus-visible { outline: 2px solid var(--secondary); outline-offset: 2px; }
    @keyframes btnPulse { 0%,100%{box-shadow:0 0 0 0 rgba(26,60,94,.4);}50%{box-shadow:0 0 0 8px rgba(26,60,94,0);} }
    .btn-auth.submitting { animation: btnPulse 1s ease infinite; }
    .spinner { width:18px;height:18px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:spin .65s linear infinite;flex-shrink:0; }
    @keyframes spin { to { transform: rotate(360deg); } }
    /* ── Alert banners */
    .auth-alert { display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:var(--radius-md);font-size:14px;margin-bottom:20px;line-height:1.5; }
    .auth-alert i { margin-top:1px;flex-shrink:0; }
    .auth-alert.error   { background:#fef2f2;color:#991b1b;border:1px solid #fecaca; }
    .auth-alert.success { background:#f0fdf4;color:#166534;border:1px solid #bbf7d0; }
    .auth-alert.info    { background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe; }
    [data-theme="dark"] .auth-alert.error   { background:rgba(220,38,38,.12);color:#fca5a5;border-color:rgba(220,38,38,.3); }
    [data-theme="dark"] .auth-alert.success { background:rgba(34,197,94,.12);color:#86efac;border-color:rgba(34,197,94,.3); }
    [data-theme="dark"] .auth-alert.info    { background:rgba(59,130,246,.12);color:#93c5fd;border-color:rgba(59,130,246,.3); }
    #lockout-countdown { font-variant-numeric:tabular-nums;font-weight:700; }
    /* ── Footer */
    .auth-footer { margin-top:32px;padding-top:20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;font-size:12px;color:var(--text-muted); }
    .btn-theme-toggle { background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:18px;padding:4px;border-radius:4px;display:flex;align-items:center; }
    .btn-theme-toggle:focus-visible { outline:2px solid var(--secondary);outline-offset:2px; }
    /* ── Modal */
    .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:900;align-items:center;justify-content:center;padding:16px; }
    .modal-overlay.open { display:flex; }
    .modal-box { background:var(--card);border-radius:var(--radius-xl);padding:32px;max-width:400px;width:100%;box-shadow:var(--shadow-xl);animation:cardSlideIn 250ms ease both; }
    .modal-box h3 { font-size:18px;font-weight:700;margin:0 0 10px;color:var(--text); }
    .modal-box p  { font-size:14px;color:var(--text-muted);line-height:1.6;margin:0 0 20px; }
    .modal-close-btn { width:100%;padding:10px;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-md);cursor:pointer;font-size:14px;font-weight:600;color:var(--text);font-family:inherit; }
    .modal-close-btn:hover { background:var(--border); }
    /* ── Mobile */
    @media (max-width:768px) {
      .auth-split { flex-direction: column; }
      .auth-brand { flex:none;height:200px;padding:20px 24px;flex-direction:row;justify-content:flex-start;gap:16px;text-align:left; }
      .auth-brand::before { display:none; }
      .auth-brand-logo { width:52px;height:52px;border-radius:14px;margin-bottom:0;flex-shrink:0; }
      .auth-brand-logo img { width:30px;height:30px; }
      .auth-brand h2 { font-size:18px;margin-bottom:4px; }
      .auth-brand .tagline { font-size:12px;margin-bottom:0; }
      .auth-features { display:none; }
      .auth-brand-text { display:flex;flex-direction:column; }
      .auth-form-panel { flex:1;padding:32px 24px;align-items:flex-start;padding-top:36px; }
    }
    @media (max-width:400px) { .auth-form-panel { padding:24px 16px; } }
  </style>
</head>
<body>

<!-- Forgot password modal -->
<div id="forgot-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="forgot-title">
  <div class="modal-box">
    <h3 id="forgot-title"><i class="bi bi-info-circle" style="margin-right:6px;color:var(--primary);"></i>Forgot Password?</h3>
    <p>Password resets are managed by your system administrator.<br>
       Please contact <strong>admin@svms.com</strong> or your IT department to have your password reset.</p>
    <button class="modal-close-btn" id="forgot-close">Got it</button>
  </div>
</div>

<div class="auth-split">

  <!-- Left: Brand panel -->
  <div class="auth-brand" aria-hidden="true">
    <div class="auth-brand-logo">
      <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="">
    </div>
    <div class="auth-brand-text">
      <h2><?= e(SITE_NAME) ?></h2>
      <p class="tagline"><?= e(t('login.brand_tagline')) ?></p>
    </div>
    <ul class="auth-features">
      <li><span class="feat-icon"><i class="bi bi-shield-check-fill"></i></span><?= e(t('login.feature_rbac')) ?></li>
      <li><span class="feat-icon"><i class="bi bi-camera-video-fill"></i></span><?= e(t('login.feature_photo')) ?></li>
      <li><span class="feat-icon"><i class="bi bi-graph-up-arrow"></i></span><?= e(t('login.feature_analytics')) ?></li>
    </ul>
  </div>

  <!-- Right: Form panel -->
  <div class="auth-form-panel">
    <div class="auth-card" role="main">

      <h1 class="auth-heading"><?= e(t('login.welcome')) ?></h1>
      <p class="auth-subheading"><?= e(t('login.subtitle')) ?></p>

      <?php if ($banner): ?>
        <div class="auth-alert <?= e($banner[0]) ?>" role="alert" aria-live="polite">
          <i class="bi <?= $banner[0] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
          <?= e($banner[1]) ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="auth-alert error" role="alert" aria-live="polite">
          <i class="bi bi-x-circle-fill"></i>
          <span>
            <?= e($error) ?>
            <?php if ($lock_remaining > 0): ?>
              <br><span id="lockout-countdown" aria-live="polite"><?= (int)floor($lock_remaining/60) ?>:<?= str_pad((string)($lock_remaining%60),2,'0',STR_PAD_LEFT) ?></span>
            <?php endif; ?>
          </span>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="login-form" novalidate>
        <?php csrf_field() ?>

        <div class="form-group">
          <label class="form-label" for="email"><?= e(t('login.email')) ?> <span class="req" aria-hidden="true">*</span></label>
          <div class="input-wrap">
            <i class="bi bi-envelope-fill input-lead-icon" aria-hidden="true"></i>
            <input type="email" id="email" name="email"
              value="<?= e($_POST['email'] ?? '') ?>"
              placeholder="admin@svms.com"
              autocomplete="username" required autofocus aria-required="true">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password"><?= e(t('login.password')) ?> <span class="req" aria-hidden="true">*</span></label>
          <div class="input-wrap">
            <i class="bi bi-lock-fill input-lead-icon" aria-hidden="true"></i>
            <input type="password" id="password" name="password"
              placeholder="Enter your password"
              autocomplete="current-password" required aria-required="true">
            <button type="button" class="eye-toggle" id="eye-toggle" aria-label="Show password">
              <i class="bi bi-eye-fill" id="eye-icon"></i>
            </button>
          </div>
        </div>

        <div class="auth-row-meta">
          <label class="check-label">
            <input type="checkbox" name="remember_me" value="1"> <?= e(t('login.remember_me')) ?>
          </label>
          <a href="#" class="link-muted" id="forgot-link" aria-haspopup="dialog"><?= e(t('login.forgot_password')) ?></a>
        </div>

        <button type="submit" class="btn-auth" id="submit-btn">
          <i class="bi bi-box-arrow-in-right" id="btn-icon"></i>
          <span id="btn-label"><?= e(t('login.sign_in')) ?></span>
        </button>
      </form>

      <div class="auth-footer">
        <span>SVMS v2.0</span>
        <div style="display:flex;align-items:center;gap:8px;">
          <!-- Language toggle -->
          <div style="display:flex;gap:4px;">
            <a href="?lang=en" style="font-size:12px;padding:3px 8px;border-radius:4px;border:1px solid var(--border);text-decoration:none;color:var(--text-muted);<?= $_login_lang==='en' ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : '' ?>" aria-label="Switch to English">EN</a>
            <a href="?lang=ur" style="font-size:12px;padding:3px 8px;border-radius:4px;border:1px solid var(--border);text-decoration:none;color:var(--text-muted);font-family:var(--font-urdu);<?= $_login_lang==='ur' ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : '' ?>" aria-label="اردو زبان">اردو</a>
          </div>
          <!-- Theme toggle -->
          <button class="btn-theme-toggle" id="theme-toggle" aria-label="Toggle dark mode" title="Toggle theme">
            <i class="bi bi-moon-fill" id="theme-icon"></i>
          </button>
        </div>
      </div>

    </div>
  </div>

</div>

<script src="<?= BASE_URL ?>assets/js/theme.js"></script>
<script>
(function(){
  'use strict';

  /* Eye toggle */
  var eyeBtn   = document.getElementById('eye-toggle');
  var pwdInput = document.getElementById('password');
  var eyeIcon  = document.getElementById('eye-icon');
  eyeBtn.addEventListener('click', function(){
    var hidden = pwdInput.type === 'password';
    pwdInput.type     = hidden ? 'text' : 'password';
    eyeIcon.className = hidden ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill';
    this.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
  });

  /* Forgot-password modal */
  var forgotLink  = document.getElementById('forgot-link');
  var forgotModal = document.getElementById('forgot-modal');
  var forgotClose = document.getElementById('forgot-close');
  forgotLink.addEventListener('click', function(e){ e.preventDefault(); forgotModal.classList.add('open'); forgotClose.focus(); });
  forgotClose.addEventListener('click', function(){ forgotModal.classList.remove('open'); forgotLink.focus(); });
  forgotModal.addEventListener('click', function(e){ if(e.target===forgotModal){ forgotModal.classList.remove('open'); forgotLink.focus(); } });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape' && forgotModal.classList.contains('open')){ forgotModal.classList.remove('open'); forgotLink.focus(); } });

  /* Submit spinner */
  var form      = document.getElementById('login-form');
  var submitBtn = document.getElementById('submit-btn');
  var btnLabel  = document.getElementById('btn-label');
  form.addEventListener('submit', function(){
    if(!form.checkValidity()) return;
    submitBtn.disabled = true;
    submitBtn.classList.add('submitting');
    document.getElementById('btn-icon').outerHTML = '<span class="spinner" id="btn-icon"></span>';
    btnLabel.textContent = 'Signing in\u2026';
    form.querySelectorAll('input,button').forEach(function(el){ el.disabled = true; });
  });

  /* Lockout countdown */
  var cdEl = document.getElementById('lockout-countdown');
  if(cdEl){
    var remaining = <?= (int)$lock_remaining ?>;
    var timer = setInterval(function(){
      remaining--;
      if(remaining <= 0){ clearInterval(timer); window.location.reload(); return; }
      var m = Math.floor(remaining/60), s = remaining%60;
      cdEl.textContent = m + ':' + (s<10?'0':'') + s;
    }, 1000);
  }

  /* Theme toggle */
  var themeBtn  = document.getElementById('theme-toggle');
  var themeIcon = document.getElementById('theme-icon');
  function syncIcon(){ themeIcon.className = document.documentElement.getAttribute('data-theme')==='dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill'; }
  syncIcon();
  themeBtn.addEventListener('click', function(){
    var next = document.documentElement.getAttribute('data-theme')==='dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    document.cookie = 'svms_theme='+next+';path=/;max-age=31536000;SameSite=Strict';
    syncIcon();
  });
})();
</script>
</body>
</html>
