<?php
/**
 * pages/login.php — Staff login page with real database authentication.
 */
require_once __DIR__ . '/../config.php';

// Already logged in — redirect to dashboard
if (isset($_SESSION['admin_id']) && !empty($_SESSION['2fa_verified'])) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit;
}

$error        = '';
$success_msg  = '';
$username_val = '';

// Show messages passed via query string
$msg = $_GET['msg'] ?? '';
if ($msg === 'logged_out')       $success_msg = 'You have been signed out successfully.';
elseif ($msg === 'unauthorized') $error = 'Please sign in to continue.';
elseif ($msg === 'session_expired') $error = 'Your session has expired. Please sign in again.';

// ── POST handler ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_validate();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']      ?? '';
    $username_val = $username;

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        // Rate limiting: 10 attempts per minute per IP
        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
        if (!rl_check('login:' . $ip, 10)) {
            $error = 'Too many login attempts. Please wait a minute and try again.';
        } else {
            $admin = query_one(
                'SELECT id, name, username, email, password, role_id, is_active, theme, language
                 FROM admins WHERE username = ? OR email = ? LIMIT 1',
                'ss', [$username, $username]
            );

            if ($admin && password_verify($password, $admin['password'])) {
                if (!(bool)$admin['is_active']) {
                    $error = 'Your account has been deactivated. Contact an administrator.';
                } else {
                    // Successful login — set up session
                    session_regenerate_id(true);

                    $_SESSION['admin_id']      = (int)$admin['id'];
                    $_SESSION['admin_name']    = $admin['name'];
                    $_SESSION['role_id']       = (int)$admin['role_id'];
                    $_SESSION['theme']         = $admin['theme'] ?: 'light';
                    $_SESSION['lang']          = $admin['language'] ?: 'en';
                    $_SESSION['last_activity'] = time();

                    cache_permissions();

                    // Record last login
                    query_exec(
                        'UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?',
                        'si', [$ip, $admin['id']]
                    );
                    log_action('login', (int)$admin['id'], json_encode(['method' => 'password']));

                    if (ENABLE_2FA) {
                        $_SESSION['pending_2fa'] = true;
                        ob_end_clean();
                        header('Location: ' . BASE_URL . 'pages/verify_otp.php');
                    } else {
                        $_SESSION['2fa_verified'] = true;
                        ob_end_clean();
                        header('Location: ' . BASE_URL . 'pages/dashboard.php');
                    }
                    exit;
                }
            } else {
                $error = 'Invalid username or password.';
            }
        }
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
  <title>Sign In — <?= e(SITE_NAME) ?></title>
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
    .auth-feature{display:flex;align-items:center;gap:12px;padding:10px 0;font-size:13px;color:rgba(255,255,255,.82);border-bottom:1px solid rgba(255,255,255,.08);}
    .auth-feature:last-child{border-bottom:none;}
    .auth-feature i{font-size:16px;opacity:.8;flex-shrink:0;}
    .auth-features{width:100%;max-width:280px;position:relative;}
    .auth-form-panel{flex:1;background:var(--card);display:flex;align-items:center;justify-content:center;padding:48px 40px;}
    .auth-card{width:100%;max-width:420px;animation:slideIn 350ms cubic-bezier(.16,1,.3,1) both;}
    @keyframes slideIn{from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);}}
    .auth-heading{font-size:28px;font-weight:800;color:var(--text);margin:0 0 4px;letter-spacing:-.5px;}
    .auth-subheading{font-size:14px;color:var(--text-muted);margin:0 0 28px;}
    .form-group{margin-bottom:18px;}
    .form-label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:6px;}
    .input-wrap{position:relative;display:flex;align-items:center;}
    .input-lead-icon{position:absolute;left:12px;color:var(--text-muted);font-size:15px;pointer-events:none;z-index:1;}
    .input-wrap input{flex:1;width:100%;padding:11px 14px 11px 36px;border:1.5px solid var(--border);border-radius:var(--radius-md);background:var(--surface);color:var(--text);font-size:14px;font-family:inherit;transition:border-color .15s,box-shadow .15s;outline:none;}
    .input-wrap input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,60,94,.12);}
    .eye-toggle{position:absolute;right:10px;background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:15px;padding:4px;border-radius:4px;line-height:1;display:flex;align-items:center;}
    .eye-toggle:hover{color:var(--text);}
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
    .forgot-link{display:block;text-align:right;font-size:12px;color:var(--text-muted);margin-top:4px;text-decoration:none;}
    .forgot-link:hover{color:var(--primary);}
    @media(max-width:768px){.auth-split{flex-direction:column;}.auth-brand{flex:none;height:160px;padding:20px 24px;flex-direction:row;justify-content:flex-start;gap:14px;text-align:left;}.auth-brand .tagline,.auth-features{display:none;}.auth-brand-logo{width:44px;height:44px;border-radius:12px;margin-bottom:0;flex-shrink:0;}.auth-brand-logo i{font-size:22px;}.auth-brand h2{font-size:16px;}.auth-form-panel{padding:28px 20px;}}
  </style>
</head>
<body>
<div class="auth-split">

  <!-- Brand panel -->
  <aside class="auth-brand" aria-hidden="true">
    <div class="auth-brand-logo"><i class="bi bi-shield-lock-fill"></i></div>
    <h2><?= e(SITE_NAME) ?></h2>
    <p class="tagline">Secure, smart visitor management for modern organizations.</p>
    <div class="auth-features">
      <div class="auth-feature"><i class="bi bi-person-check-fill"></i><span>Real-time visitor tracking</span></div>
      <div class="auth-feature"><i class="bi bi-qr-code-scan"></i><span>QR-based check-in / check-out</span></div>
      <div class="auth-feature"><i class="bi bi-bar-chart-fill"></i><span>Analytics &amp; reporting</span></div>
      <div class="auth-feature"><i class="bi bi-slash-circle-fill"></i><span>Blacklist &amp; watchlist alerts</span></div>
    </div>
  </aside>

  <!-- Form panel -->
  <main class="auth-form-panel">
    <div class="auth-card">

      <h1 class="auth-heading">Welcome back</h1>
      <p class="auth-subheading">Sign in to your staff account.</p>

      <?php if ($error): ?>
        <div class="auth-alert error" role="alert">
          <i class="bi bi-exclamation-circle-fill"></i>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <?php if ($success_msg): ?>
        <div class="auth-alert success" role="alert">
          <i class="bi bi-check-circle-fill"></i>
          <div><?= e($success_msg) ?></div>
        </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate id="loginForm">
        <?= csrf_field() ?>

        <div class="form-group">
          <label class="form-label" for="username">Username or Email</label>
          <div class="input-wrap">
            <i class="bi bi-person input-lead-icon"></i>
            <input type="text" id="username" name="username"
                   autocomplete="username"
                   value="<?= e($username_val) ?>"
                   placeholder="Enter your username or email"
                   required autofocus>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-wrap">
            <i class="bi bi-lock input-lead-icon"></i>
            <input type="password" id="password" name="password"
                   autocomplete="current-password"
                   placeholder="Enter your password"
                   required>
            <button type="button" class="eye-toggle" id="eyeToggleBtn" aria-label="Show password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-auth" id="submitBtn">
          <i class="bi bi-box-arrow-in-right"></i> Sign In
        </button>
      </form>

      <div class="auth-footer">
        Don't have an account? <a href="<?= BASE_URL ?>pages/register.php">Create one</a>
      </div>

      <!-- Demo credentials panel -->
      <div style="margin-top:20px;padding:14px 16px;background:var(--bg-secondary,#f1f5f9);border:1px solid var(--border,#e2e8f0);border-radius:10px;font-size:12px;">
        <div style="font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;">
          <i class="bi bi-key-fill" style="color:var(--secondary,#2e75b6);margin-right:5px;"></i>Demo Credentials
        </div>
        <?php
        $demo = [
          ['admin@demo.local',     'Admin@123',       'Super Admin'],
          ['reception@demo.local', 'Frontdesk2026!',  'Receptionist'],
          ['security@demo.local',  'Guard2026!',      'Administrator'],
          ['ops@demo.local',       'Ops2026!',        'Administrator'],
        ];
        foreach ($demo as [$u, $p, $role]):
        ?>
        <button type="button" class="demo-cred-btn"
                data-user="<?= htmlspecialchars($u, ENT_QUOTES) ?>"
                data-pass="<?= htmlspecialchars($p, ENT_QUOTES) ?>"
                style="display:flex;align-items:center;justify-content:space-between;width:100%;padding:7px 10px;margin-bottom:4px;background:var(--card,#fff);border-radius:6px;border:1px solid var(--border,#e2e8f0);cursor:pointer;text-align:left;font-family:inherit;transition:background .15s;"
                title="Click to fill credentials">
          <div>
            <span style="font-weight:600;color:var(--text);font-size:11px;display:block;"><?= htmlspecialchars($u) ?></span>
            <span style="color:var(--text-muted);font-size:10px;"><?= htmlspecialchars($p) ?> &bull; <?= htmlspecialchars($role) ?></span>
          </div>
          <i class="bi bi-arrow-right-circle" style="color:var(--secondary,#2e75b6);font-size:15px;flex-shrink:0;"></i>
        </button>
        <?php endforeach; ?>
        <div style="margin-top:8px;font-size:10px;color:var(--text-muted);text-align:center;">&#9654; Click any row to auto-fill &amp; sign in</div>
      </div>

    </div><!-- /.auth-card -->
  </main>
</div><!-- /.auth-split -->

<script>
var eyeBtn = document.getElementById('eyeToggleBtn');
if (eyeBtn) {
  eyeBtn.addEventListener('click', function () {
    var inp = document.getElementById('password');
    var ico = this.querySelector('i');
    if (inp.type === 'password') {
      inp.type = 'text';
      ico.className = 'bi bi-eye-slash';
    } else {
      inp.type = 'password';
      ico.className = 'bi bi-eye';
    }
  });
}

document.getElementById('loginForm').addEventListener('submit', function () {
  var btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span style="width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;display:inline-block;animation:spin .65s linear infinite;"></span> Signing in…';
});

// Demo credential buttons — fill and submit login form
document.querySelectorAll('.demo-cred-btn').forEach(function (btn) {
  btn.addEventListener('mouseenter', function () { this.style.background = 'var(--bg-secondary,#f1f5f9)'; });
  btn.addEventListener('mouseleave', function () { this.style.background = 'var(--card,#fff)'; });
  btn.addEventListener('click', function () {
    document.getElementById('username').value = this.dataset.user;
    document.getElementById('password').value = this.dataset.pass;
    document.getElementById('loginForm').requestSubmit();
  });
});
</script>
</body>
</html>
