<?php
/**
 * pages/register.php — Staff self-registration page.
 *
 * New accounts are created with the Receptionist role (role_id = 3).
 * A Super Admin can promote roles via the Users management page.
 */
require_once __DIR__ . '/../config.php';

// Already logged in — redirect to dashboard
if (isset($_SESSION['admin_id']) && !empty($_SESSION['2fa_verified'])) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit;
}

$errors  = [];
$success = false;
$fields  = ['name' => '', 'username' => '', 'email' => ''];

// ── POST handler ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_validate();

    $name     = trim($_POST['name']     ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    // Persist field values for re-fill on error
    $fields = compact('name', 'username', 'email');

    // ── Validation ─────────────────────────────────────────────────────────
    if ($name === '' || mb_strlen($name) < 2) {
        $errors['name'] = 'Full name must be at least 2 characters.';
    } elseif (mb_strlen($name) > 150) {
        $errors['name'] = 'Full name must be 150 characters or fewer.';
    }

    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors['username'] = 'Username must be 3–50 characters: letters, digits, underscores only.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 320) {
        $errors['email'] = 'Email address is too long.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one special character.';
    }

    if ($confirm !== $password) {
        $errors['confirm'] = 'Passwords do not match.';
    }

    // ── Uniqueness checks (only if basic validation passed) ─────────────────
    if (empty($errors['username'])) {
        $existing = query_one('SELECT id FROM admins WHERE username = ? LIMIT 1', 's', [$username]);
        if ($existing) {
            $errors['username'] = 'This username is already taken.';
        }
    }

    if (empty($errors['email'])) {
        $existing = query_one('SELECT id FROM admins WHERE email = ? LIMIT 1', 's', [$email]);
        if ($existing) {
            $errors['email'] = 'An account with this email already exists.';
        }
    }

    // ── Insert ──────────────────────────────────────────────────────────────
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        query_exec(
            'INSERT INTO admins (name, username, email, password, role_id, is_active)
             VALUES (?, ?, ?, ?, 3, 1)',
            'ssss',
            [$name, $username, $email, $hash]
        );
        $success = true;
        $fields  = ['name' => '', 'username' => '', 'email' => ''];
    }
}

$_lang = current_lang();
$_dir  = is_rtl() ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?= e($_lang) ?>" dir="<?= e($_dir) ?>" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>Create Account — <?= e(SITE_NAME) ?></title>
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
  <script>(function(){var m=localStorage.getItem('svms_theme_mode')||'system';var t=m==='dark'?'dark':m==='light'?'light':(window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');document.documentElement.setAttribute('data-theme',t);})();</script>
  <style>
    html,body{height:100%;margin:0;}
    body{min-height:100vh;display:flex;align-items:stretch;background:#f0f4f8;}
    .auth-split{display:flex;width:100%;min-height:100vh;}
    .auth-brand{flex:0 0 42%;background:linear-gradient(135deg,var(--primary) 0%,#0D2137 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 48px;color:#fff;text-align:center;position:relative;overflow:hidden;}
    .auth-brand::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(46,117,182,.35) 0%,transparent 70%);pointer-events:none;}
    .auth-brand-logo{width:80px;height:80px;background:rgba(255,255,255,.12);border-radius:20px;display:flex;align-items:center;justify-content:center;margin-bottom:24px;border:1px solid rgba(255,255,255,.18);position:relative;}
    .auth-brand-logo i{font-size:38px;color:#fff;}
    .auth-brand h2{font-size:26px;font-weight:800;letter-spacing:-.5px;color:#fff;margin:0 0 10px;position:relative;}
    .auth-brand .tagline{font-size:14px;color:rgba(255,255,255,.65);margin:0 0 36px;max-width:260px;line-height:1.6;position:relative;}
    .auth-steps{list-style:none;padding:0;margin:0;text-align:left;width:100%;max-width:280px;position:relative;}
    .auth-steps li{display:flex;align-items:flex-start;gap:12px;padding:10px 0;font-size:13px;color:rgba(255,255,255,.82);border-bottom:1px solid rgba(255,255,255,.08);}
    .auth-steps li:last-child{border-bottom:none;}
    .step-num{width:24px;height:24px;background:rgba(255,255,255,.18);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
    .auth-form-panel{flex:1;background:var(--card);display:flex;align-items:center;justify-content:center;padding:48px 40px;}
    .auth-card{width:100%;max-width:460px;animation:slideIn 350ms cubic-bezier(.16,1,.3,1) both;}
    @keyframes slideIn{from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);}}
    .auth-heading{font-size:24px;font-weight:800;color:var(--text);margin:0 0 6px;letter-spacing:-.4px;}
    .auth-subheading{font-size:14px;color:var(--text-muted);margin:0 0 28px;}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .form-group{margin-bottom:18px;}
    .form-label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px;}
    .form-label .req{color:var(--danger);margin-left:2px;}
    .input-wrap{position:relative;display:flex;align-items:center;}
    .input-lead-icon{position:absolute;left:12px;color:var(--text-muted);font-size:15px;pointer-events:none;z-index:1;}
    .input-wrap input,.input-wrap select{flex:1;width:100%;padding:10px 14px 10px 36px;border:1.5px solid var(--border);border-radius:var(--radius-md);background:var(--surface);color:var(--text);font-size:14px;font-family:inherit;transition:border-color .15s,box-shadow .15s;outline:none;}
    .input-wrap input:focus,.input-wrap select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,60,94,.12);}
    .input-wrap input.is-invalid{border-color:var(--danger);}
    .field-error{font-size:12px;color:var(--danger);margin-top:4px;display:flex;align-items:center;gap:4px;}
    .eye-toggle{position:absolute;right:10px;background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:15px;padding:4px;border-radius:4px;line-height:1;display:flex;align-items:center;}
    .eye-toggle:hover{color:var(--text);}
    .password-strength{margin-top:6px;}
    .strength-bar{height:3px;border-radius:2px;background:var(--border);overflow:hidden;margin-bottom:4px;}
    .strength-fill{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s;}
    .strength-label{font-size:11px;color:var(--text-muted);}
    .btn-auth{width:100%;padding:12px;background:var(--primary);color:#fff;border:none;border-radius:var(--radius-md);font-size:15px;font-weight:700;font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s,box-shadow .15s;margin-top:8px;}
    .btn-auth:hover:not(:disabled){background:#152e4d;box-shadow:0 4px 14px rgba(26,60,94,.3);}
    .btn-auth:disabled{opacity:.7;cursor:not-allowed;}
    .auth-alert{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:var(--radius-md);font-size:14px;margin-bottom:20px;line-height:1.5;}
    .auth-alert.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;}
    .auth-alert.success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;}
    [data-theme="dark"] .auth-alert.error{background:rgba(220,38,38,.12);color:#fca5a5;border-color:rgba(220,38,38,.3);}
    [data-theme="dark"] .auth-alert.success{background:rgba(34,197,94,.12);color:#86efac;border-color:rgba(34,197,94,.3);}
    .auth-footer{margin-top:28px;padding-top:16px;border-top:1px solid var(--border);font-size:13px;color:var(--text-muted);text-align:center;}
    .auth-footer a{color:var(--primary);text-decoration:none;font-weight:600;}
    .auth-footer a:hover{text-decoration:underline;}
    .role-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(26,60,94,.08);color:var(--primary);padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:20px;}
    @media(max-width:768px){.auth-split{flex-direction:column;}.auth-brand{flex:none;height:160px;padding:20px 24px;flex-direction:row;justify-content:flex-start;gap:14px;text-align:left;}.auth-brand .tagline,.auth-steps{display:none;}.auth-brand-logo{width:44px;height:44px;border-radius:12px;margin-bottom:0;flex-shrink:0;}.auth-brand-logo i{font-size:22px;}.auth-brand h2{font-size:16px;}.auth-form-panel{padding:28px 20px;}.form-row{grid-template-columns:1fr;}}
  </style>
</head>
<body>
<div class="auth-split">

  <!-- Brand panel -->
  <aside class="auth-brand" aria-hidden="true">
    <div class="auth-brand-logo"><i class="bi bi-person-badge-fill"></i></div>
    <h2><?= e(SITE_NAME) ?></h2>
    <p class="tagline">Create a staff account to start managing visitors.</p>
    <ul class="auth-steps">
      <li><span class="step-num">1</span><span>Fill in your details below</span></li>
      <li><span class="step-num">2</span><span>Your account is created instantly</span></li>
      <li><span class="step-num">3</span><span>Sign in and start managing visitors</span></li>
    </ul>
  </aside>

  <!-- Form panel -->
  <main class="auth-form-panel">
    <div class="auth-card">

      <h1 class="auth-heading">Create your account</h1>
      <p class="auth-subheading">Staff registration — new accounts start with the Receptionist role.</p>

      <?php if ($success): ?>
        <div class="auth-alert success" role="alert">
          <i class="bi bi-check-circle-fill"></i>
          <div>
            <strong>Account created!</strong><br>
            You can now <a href="<?= BASE_URL ?>pages/login.php" style="color:inherit;font-weight:700;text-decoration:underline;">sign in</a> with your credentials.
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors) && !$success): ?>
        <div class="auth-alert error" role="alert">
          <i class="bi bi-exclamation-circle-fill"></i>
          <div>Please correct the errors below.</div>
        </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate id="regForm">
        <?= csrf_field() ?>

        <div class="form-row">
          <!-- Full Name -->
          <div class="form-group">
            <label class="form-label" for="name">Full Name <span class="req">*</span></label>
            <div class="input-wrap">
              <i class="bi bi-person input-lead-icon"></i>
              <input type="text" id="name" name="name" autocomplete="name"
                     value="<?= e($fields['name']) ?>"
                     class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                     placeholder="Your full name" required>
            </div>
            <?php if (isset($errors['name'])): ?>
              <div class="field-error"><i class="bi bi-exclamation-circle"></i><?= e($errors['name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Username -->
          <div class="form-group">
            <label class="form-label" for="username">Username <span class="req">*</span></label>
            <div class="input-wrap">
              <i class="bi bi-at input-lead-icon"></i>
              <input type="text" id="username" name="username" autocomplete="username"
                     value="<?= e($fields['username']) ?>"
                     class="<?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                     placeholder="e.g. john_doe" required>
            </div>
            <?php if (isset($errors['username'])): ?>
              <div class="field-error"><i class="bi bi-exclamation-circle"></i><?= e($errors['username']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label class="form-label" for="email">Email Address <span class="req">*</span></label>
          <div class="input-wrap">
            <i class="bi bi-envelope input-lead-icon"></i>
            <input type="email" id="email" name="email" autocomplete="email"
                   value="<?= e($fields['email']) ?>"
                   class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                   placeholder="you@example.com" required>
          </div>
          <?php if (isset($errors['email'])): ?>
            <div class="field-error"><i class="bi bi-exclamation-circle"></i><?= e($errors['email']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label class="form-label" for="password">Password <span class="req">*</span></label>
          <div class="input-wrap">
            <i class="bi bi-lock input-lead-icon"></i>
            <input type="password" id="password" name="password" autocomplete="new-password"
                   class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                   placeholder="Min 8 chars, 1 upper, 1 number, 1 special" required>
            <button type="button" class="eye-toggle" onclick="toggleEye(this,'password')" aria-label="Show password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="password-strength">
            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
            <span class="strength-label" id="strengthLabel"></span>
          </div>
          <?php if (isset($errors['password'])): ?>
            <div class="field-error"><i class="bi bi-exclamation-circle"></i><?= e($errors['password']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
          <label class="form-label" for="confirm">Confirm Password <span class="req">*</span></label>
          <div class="input-wrap">
            <i class="bi bi-lock-fill input-lead-icon"></i>
            <input type="password" id="confirm" name="confirm" autocomplete="new-password"
                   class="<?= isset($errors['confirm']) ? 'is-invalid' : '' ?>"
                   placeholder="Repeat your password" required>
            <button type="button" class="eye-toggle" onclick="toggleEye(this,'confirm')" aria-label="Show confirm password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <?php if (isset($errors['confirm'])): ?>
            <div class="field-error"><i class="bi bi-exclamation-circle"></i><?= e($errors['confirm']) ?></div>
          <?php endif; ?>
        </div>

        <div class="role-badge">
          <i class="bi bi-shield-check"></i> New accounts: Receptionist role
        </div>

        <button type="submit" class="btn-auth" id="submitBtn">
          <i class="bi bi-person-plus-fill"></i> Create Account
        </button>
      </form>

      <div class="auth-footer">
        Already have an account? <a href="<?= BASE_URL ?>pages/login.php">Sign in</a>
      </div>

    </div><!-- /.auth-card -->
  </main>
</div><!-- /.auth-split -->

<script>
// Toggle password visibility
function toggleEye(btn, fieldId) {
  var inp = document.getElementById(fieldId);
  var ico = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'bi bi-eye';
  }
}

// Password strength meter
document.getElementById('password').addEventListener('input', function () {
  var val = this.value;
  var score = 0;
  if (val.length >= 8)  score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^a-zA-Z0-9]/.test(val)) score++;
  if (val.length >= 12) score++;

  var fill  = document.getElementById('strengthFill');
  var label = document.getElementById('strengthLabel');
  var levels = [
    {w:'0%',   bg:'',           txt:''},
    {w:'25%',  bg:'#ef4444',    txt:'Weak'},
    {w:'50%',  bg:'#f97316',    txt:'Fair'},
    {w:'75%',  bg:'#eab308',    txt:'Good'},
    {w:'100%', bg:'#22c55e',    txt:'Strong'},
    {w:'100%', bg:'#16a34a',    txt:'Very strong'},
  ];
  var l = levels[Math.min(score, levels.length - 1)];
  fill.style.width = l.w;
  fill.style.background = l.bg;
  label.textContent = l.txt;
});

// Disable submit after click to prevent double-submit
document.getElementById('regForm').addEventListener('submit', function () {
  var btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;display:inline-block;animation:spin .65s linear infinite;"></span> Creating…';
});
</script>
</body>
</html>
