<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('manage_settings');
$page_title = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $tab = sanitize($_POST['tab'] ?? 'general');

    $keys = [];
    if ($tab === 'general') {
        $keys = ['site_name', 'site_phone', 'site_address', 'default_lang', 'default_theme', 'badge_prefix', 'max_visit_hours'];
    } elseif ($tab === 'email') {
        $keys = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_from_name', 'smtp_from_email', 'smtp_security'];
        $smtp_pass = $_POST['smtp_pass'] ?? '';
        if ($smtp_pass !== '') update_setting('smtp_pass', encrypt_setting($smtp_pass));
    } elseif ($tab === 'security') {
        $keys = ['session_lifetime_hours', 'otp_expiry_minutes', 'enable_2fa', 'max_login_attempts', 'lockout_minutes'];
    }

    foreach ($keys as $key) {
        $val = sanitize($_POST[$key] ?? '');
        if ($val !== '') update_setting($key, $val);
    }

    log_action('settings_update', 0, json_encode(['tab' => $tab]));
    flash('success', 'Settings saved successfully.');
    header('Location: ' . BASE_URL . 'pages/settings.php?tab=' . $tab);
    exit;
}

$active_tab = sanitize($_GET['tab'] ?? 'general');

// Load settings
$s = [
    'site_name'             => get_setting('site_name',             SITE_NAME),
    'site_phone'            => get_setting('site_phone',            ''),
    'site_address'          => get_setting('site_address',          ''),
    'default_lang'          => get_setting('default_lang',          DEFAULT_LANG),
    'default_theme'         => get_setting('default_theme',         DEFAULT_THEME),
    'badge_prefix'          => get_setting('badge_prefix',          BADGE_PREFIX),
    'max_visit_hours'       => get_setting('max_visit_hours',       (string)MAX_VISIT_HOURS),
    'smtp_host'             => get_setting('smtp_host',             SMTP_HOST),
    'smtp_port'             => get_setting('smtp_port',             (string)SMTP_PORT),
    'smtp_user'             => get_setting('smtp_user',             SMTP_USER),
    'smtp_from_name'        => get_setting('smtp_from_name',        SMTP_FROM_NAME),
    'smtp_from_email'       => get_setting('smtp_from_email',       SMTP_FROM_EMAIL),    'smtp_security'         => get_setting('smtp_security',         'tls'),    'session_lifetime_hours'=> get_setting('session_lifetime_hours',(string)SESSION_LIFETIME_HOURS),
    'otp_expiry_minutes'    => get_setting('otp_expiry_minutes',    (string)OTP_EXPIRY_MINUTES),
    'enable_2fa'            => get_setting('enable_2fa',            ENABLE_2FA ? 'true' : 'false'),
    'max_login_attempts'    => get_setting('max_login_attempts',    '5'),
    'lockout_minutes'       => get_setting('lockout_minutes',       '15'),
];

include __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:900px;">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="bi bi-gear-fill" style="color:var(--secondary);"></i> <?= __t('settings') ?></h1>
      <p class="page-subtitle">Configure system-wide settings.</p>
    </div>
  </div>

  <div class="tabs" style="margin-bottom:var(--space-5);">
    <a class="tab <?= $active_tab==='general'  ? 'active' : '' ?>" href="?tab=general"><i class="bi bi-sliders"></i> General</a>
    <a class="tab <?= $active_tab==='email'    ? 'active' : '' ?>" href="?tab=email"><i class="bi bi-envelope-fill"></i> Email / SMTP</a>
    <a class="tab <?= $active_tab==='security' ? 'active' : '' ?>" href="?tab=security"><i class="bi bi-shield-lock-fill"></i> Security</a>
  </div>

  <div class="card">
    <form method="POST" action="" data-validate="true" novalidate>
      <?php csrf_field() ?>
      <input type="hidden" name="tab" value="<?= e($active_tab) ?>">
      <div class="card-body">

        <?php if ($active_tab === 'general'): ?>
          <div class="form-section-title"><i class="bi bi-building"></i> Organisation</div>
          <div class="form-group">
            <label>Site / Organisation Name</label>
            <input type="text" name="site_name" class="form-control" value="<?= e($s['site_name']) ?>">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Contact Phone</label>
              <input type="tel" name="site_phone" class="form-control" value="<?= e($s['site_phone']) ?>" data-format="phone">
            </div>
            <div class="form-group">
              <label>Badge Prefix</label>
              <input type="text" name="badge_prefix" class="form-control" value="<?= e($s['badge_prefix']) ?>" placeholder="SVMS">
            </div>
          </div>
          <div class="form-group">
            <label>Address</label>
            <textarea name="site_address" class="form-control" rows="2"><?= e($s['site_address']) ?></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Default Language</label>
              <select name="default_lang" class="form-control">
                <option value="en" <?= $s['default_lang']==='en' ? 'selected' : '' ?>>English</option>
                <option value="ur" <?= $s['default_lang']==='ur' ? 'selected' : '' ?>>اردو</option>
              </select>
            </div>
            <div class="form-group">
              <label>Default Theme</label>
              <select name="default_theme" class="form-control">
                <option value="light" <?= $s['default_theme']==='light' ? 'selected' : '' ?>>Light</option>
                <option value="dark"  <?= $s['default_theme']==='dark'  ? 'selected' : '' ?>>Dark</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Max Visit Duration (hours)</label>
            <input type="number" name="max_visit_hours" class="form-control" value="<?= e($s['max_visit_hours']) ?>" min="1" max="24">
            <span class="form-help">Visitors checked in longer than this will be auto-checked out by cron.</span>
          </div>

          <!-- Auto Checkout Trigger -->
          <div class="form-group" style="border-top:1px solid var(--border);padding-top:16px;margin-top:8px;">
            <label>Manual Auto-Checkout</label>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
              <button type="button" id="run-auto-checkout-btn" class="btn btn-warning btn-sm"
                      style="display:flex;align-items:center;gap:6px;">
                <i class="bi bi-clock-history"></i> Run Auto-Checkout Now
              </button>
              <span id="auto-checkout-result" style="font-size:13px;color:var(--text-muted);"></span>
            </div>
            <span class="form-help">Immediately checks out all visitors who have exceeded the max duration.</span>
          </div>

        <?php elseif ($active_tab === 'email'): ?>
          <div class="form-section-title"><i class="bi bi-envelope-fill"></i> SMTP Configuration</div>
          <div class="alert alert-info" style="margin-bottom:16px;">
            <i class="alert-icon bi bi-info-circle-fill"></i>
            <div class="alert-body">SMTP password is write-only. Leave blank to keep existing password.</div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>SMTP Host</label>
              <input type="text" name="smtp_host" class="form-control" value="<?= e($s['smtp_host']) ?>" placeholder="smtp.gmail.com">
            </div>
            <div class="form-group">
              <label>SMTP Port</label>
              <input type="number" name="smtp_port" class="form-control" value="<?= e($s['smtp_port']) ?>" min="1" max="65535">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>SMTP Username</label>
              <input type="text" name="smtp_user" class="form-control" value="<?= e($s['smtp_user']) ?>" autocomplete="username">
            </div>
            <div class="form-group">
              <label>SMTP Password</label>
              <div class="input-group">
                <input type="password" name="smtp_pass" class="form-control" placeholder="Leave blank to keep current" autocomplete="new-password" id="smtp-pass-input">
                <button type="button" class="input-group-btn" onclick="var i=document.getElementById('smtp-pass-input');i.type=i.type==='password'?'text':'password';"><i class="bi bi-eye"></i></button>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Encryption</label>
              <select name="smtp_security" class="form-control">
                <option value="tls"  <?= $s['smtp_security']==='tls'  ? 'selected' : '' ?>>STARTTLS (port 587 — recommended)</option>
                <option value="ssl"  <?= $s['smtp_security']==='ssl'  ? 'selected' : '' ?>>SSL/TLS (port 465)</option>
                <option value="none" <?= $s['smtp_security']==='none' ? 'selected' : '' ?>>None (not recommended)</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>From Name</label>
              <input type="text" name="smtp_from_name" class="form-control" value="<?= e($s['smtp_from_name']) ?>">
            </div>
            <div class="form-group">
              <label>From Email</label>
              <input type="email" name="smtp_from_email" class="form-control" value="<?= e($s['smtp_from_email']) ?>">
            </div>
          </div>

          <!-- Test Email button -->
          <div style="margin-top:8px;padding-top:16px;border-top:1px solid var(--border);">
            <button type="button" class="btn btn-secondary" id="test-email-btn">
              <i class="bi bi-send"></i> Send Test Email
            </button>
            <span class="form-help" style="margin-left:10px;">Tests the settings above (without saving first).</span>
          </div>

          <!-- Test Email Modal -->
          <div id="test-email-modal" class="modal-backdrop" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="test-email-title">
            <div class="modal" style="max-width:420px;">
              <div class="modal-header">
                <h3 class="modal-title" id="test-email-title"><i class="bi bi-send"></i> Send Test Email</h3>
                <button type="button" class="modal-close" id="test-email-close" aria-label="Close">&times;</button>
              </div>
              <div class="modal-body">
                <div class="form-group">
                  <label for="test-email-to">Recipient Address</label>
                  <input type="email" id="test-email-to" class="form-control"
                         value="<?= e($_SESSION['admin_email'] ?? '') ?>" placeholder="you@example.com">
                </div>
                <div id="test-email-result" style="display:none;margin-top:12px;"></div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="test-email-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="test-email-send">
                  <i class="bi bi-send"></i> Send Test
                </button>
              </div>
            </div>
          </div>

          <script>
          (function() {
            var modal  = document.getElementById('test-email-modal');
            var result = document.getElementById('test-email-result');

            document.getElementById('test-email-btn').addEventListener('click', function() {
              result.style.display = 'none';
              modal.style.display  = 'flex';
              document.getElementById('test-email-to').focus();
            });

            function closeModal() { modal.style.display = 'none'; }
            document.getElementById('test-email-close').addEventListener('click', closeModal);
            document.getElementById('test-email-cancel').addEventListener('click', closeModal);
            modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

            document.getElementById('test-email-send').addEventListener('click', function() {
              var btn  = this;
              var to   = document.getElementById('test-email-to').value.trim();
              if (!to) { alert('Please enter a recipient email.'); return; }

              // Collect current form values (unsaved) for the test
              var form = btn.closest('form') || document.querySelector('form[data-validate]');
              var fd   = new FormData(form);
              fd.append('test_to', to);
              fd.append('action', 'test_email_inline');

              btn.disabled = true;
              btn.textContent = 'Sending…';
              result.style.display = 'none';

              fetch('<?= BASE_URL ?>api/test_email.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
                body: fd,
              })
              .then(r => r.json())
              .then(function(data) {
                result.style.display = 'block';
                if (data.ok) {
                  result.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle-fill alert-icon"></i><div class="alert-body">Test email sent successfully to <strong>' + to + '</strong>!</div></div>';
                } else {
                  result.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle-fill alert-icon"></i><div class="alert-body"><strong>Failed:</strong> ' + (data.error || 'Unknown error') + '</div></div>';
                }
              })
              .catch(function(e) {
                result.style.display = 'block';
                result.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle-fill alert-icon"></i><div class="alert-body">Request failed: ' + e.message + '</div></div>';
              })
              .finally(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send"></i> Send Test';
              });
            });
          })();
          </script>

        <?php elseif ($active_tab === 'security'): ?>
          <div class="form-section-title"><i class="bi bi-shield-lock-fill"></i> Authentication</div>
          <div class="form-row">
            <div class="form-group">
              <label>Session Lifetime (hours)</label>
              <input type="number" name="session_lifetime_hours" class="form-control" value="<?= e($s['session_lifetime_hours']) ?>" min="1" max="24">
            </div>
            <div class="form-group">
              <label>OTP Expiry (minutes)</label>
              <input type="number" name="otp_expiry_minutes" class="form-control" value="<?= e($s['otp_expiry_minutes']) ?>" min="1" max="60">
            </div>
          </div>
          <div class="form-group">
            <label class="form-check">
              <input type="checkbox" name="enable_2fa" value="true" <?= $s['enable_2fa']==='true' ? 'checked' : '' ?> class="form-check-input">
              Enable Two-Factor Authentication (OTP via email)
            </label>
          </div>
          <div class="form-section-title" style="margin-top:20px;"><i class="bi bi-lock-fill"></i> Account Lockout</div>
          <div class="form-row">
            <div class="form-group">
              <label>Max Failed Login Attempts</label>
              <input type="number" name="max_login_attempts" class="form-control" value="<?= e($s['max_login_attempts']) ?>" min="1" max="20">
            </div>
            <div class="form-group">
              <label>Lockout Duration (minutes)</label>
              <input type="number" name="lockout_minutes" class="form-control" value="<?= e($s['lockout_minutes']) ?>" min="1" max="1440">
            </div>
          </div>
        <?php endif; ?>

      </div>
      <div class="card-footer" style="display:flex;justify-content:flex-end;gap:10px;">
        <a href="?" class="btn btn-secondary"><?= __t('cancel') ?></a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Settings</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var btn = document.getElementById('run-auto-checkout-btn');
  var res = document.getElementById('auto-checkout-result');
  if (!btn) return;
  btn.addEventListener('click', function () {
    if (!confirm('Run auto-checkout now? This will check out all visitors exceeding the max duration.')) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Running…';
    if (res) res.textContent = '';
    fetch(<?= json_encode(BASE_URL . 'api/run_auto_checkout.php') ?>, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ csrf_token: <?= json_encode(csrf_token_for_js()) ?> })
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (res) {
        res.textContent = d.message || (d.ok ? 'Done.' : 'Error: ' + (d.error || 'Unknown'));
        res.style.color = d.ok ? 'var(--success)' : 'var(--danger)';
      }
    })
    .catch(function () { if (res) { res.textContent = 'Request failed.'; res.style.color = 'var(--danger)'; } })
    .finally(function () {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-clock-history"></i> Run Auto-Checkout Now';
    });
  });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
