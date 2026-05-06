<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
$page_title = 'My Profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';
    $admin_id = (int)$_SESSION['admin_id'];

    if ($action === 'update_profile') {
        $name  = sanitize($_POST['name']  ?? '');
        $email = sanitize($_POST['email'] ?? '');
        if (!$name) { flash('error', 'Name is required.'); }
        else {
            query_exec('UPDATE admin_users SET name=?, email=? WHERE id=?', 'ssi', [$name, $email, $admin_id]);
            $_SESSION['admin_name'] = $name;
            log_action('profile_update', $admin_id);
            flash('success', 'Profile updated successfully.');
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Look up from the correct table (admins)
        $admin_row = query_one('SELECT id, password FROM admins WHERE id=? LIMIT 1', 'i', [$admin_id]);
        if (!$admin_row || !password_verify($current, $admin_row['password'])) {
            flash('error', 'Current password is incorrect.');
        } elseif ($new !== $confirm) {
            flash('error', 'New passwords do not match.');
        } elseif (($pw_err = validate_password_strength($new)) !== '') {
            flash('error', $pw_err);
        } elseif (password_used_recently($new, $admin_id, 3)) {
            flash('error', 'New password cannot match any of your last 3 passwords.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            query_exec('UPDATE admins SET password=? WHERE id=?', 'si', [$hash, $admin_id]);
            record_password_history($admin_id, $hash);
            // Regenerate session ID on privilege change
            session_regenerate_id(true);
            log_action('password_change', $admin_id);
            flash('success', 'Password changed successfully.');
        }
    }
    header('Location: ' . BASE_URL . 'pages/profile.php');
    exit;
}

$admin = query_one('SELECT id, name, email, role, created_at, last_login FROM admin_users WHERE id=? LIMIT 1', 'i', [(int)$_SESSION['admin_id']]);
include __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:900px;">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="bi bi-person-circle" style="color:var(--secondary);"></i> My Profile</h1>
      <p class="page-subtitle">Update your account details and password.</p>
    </div>
  </div>

  <div class="grid-2">
    <!-- Profile Info -->
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="bi bi-person-fill"></i> Account Information</h3></div>
      <form method="POST" action="" data-validate="true" novalidate>
        <div class="card-body">
          <?php csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">
          <div style="text-align:center;margin-bottom:24px;">
            <img src="<?= $_SESSION['admin_avatar'] ? BASE_URL . 'assets/uploads/' . e($_SESSION['admin_avatar']) : BASE_URL . 'assets/img/default-avatar.svg' ?>"
                 style="width:90px;height:90px;border-radius:50%;border:3px solid var(--secondary);object-fit:cover;" alt="">
            <div style="margin-top:8px;font-weight:600;"><?= e($admin['name'] ?? '') ?></div>
            <div style="font-size:var(--text-sm);color:var(--text-muted);"><?= e(role_label($admin['role'] ?? '')) ?></div>
          </div>
          <div class="form-group">
            <label>Full Name <span class="required">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= e($admin['name'] ?? '') ?>" data-rules="required" required>
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= e($admin['email'] ?? '') ?>">
          </div>
          <div style="color:var(--text-muted);font-size:var(--text-sm);">
            Member since <?= format_datetime($admin['created_at'] ?? '', 'M Y') ?>
            <?php if ($admin['last_login'] ?? null): ?>
              &nbsp;·&nbsp; Last login <?= format_datetime($admin['last_login'], 'M d, Y g:i A') ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-footer" style="display:flex;justify-content:flex-end;">
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Profile</button>
        </div>
      </form>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="bi bi-key-fill"></i> Change Password</h3></div>
      <form method="POST" action="" data-validate="true" novalidate>
        <div class="card-body">
          <?php csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="form-group">
            <label>Current Password <span class="required">*</span></label>
            <div class="input-group">
              <input type="password" name="current_password" id="cp-current" class="form-control" data-rules="required" required autocomplete="current-password">
              <button type="button" class="input-group-btn" onclick="var i=document.getElementById('cp-current');i.type=i.type==='password'?'text':'password';"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label>New Password <span class="required">*</span></label>
            <div class="input-group">
              <input type="password" name="new_password" id="cp-new" class="form-control" data-rules="required" data-rules-minlen="10" required autocomplete="new-password">
              <button type="button" class="input-group-btn" onclick="var i=document.getElementById('cp-new');i.type=i.type==='password'?'text':'password';"><i class="bi bi-eye"></i></button>
            </div>
            <span class="form-help">Min 10 chars. Must include a letter, a digit, and a special character (e.g. @, #, !).</span>
          </div>
          <div class="form-group">
            <label>Confirm New Password <span class="required">*</span></label>
            <div class="input-group">
              <input type="password" name="confirm_password" id="cp-confirm" class="form-control" data-rules="required" required autocomplete="new-password">
              <button type="button" class="input-group-btn" onclick="var i=document.getElementById('cp-confirm');i.type=i.type==='password'?'text':'password';"><i class="bi bi-eye"></i></button>
            </div>
          </div>
        </div>
        <div class="card-footer" style="display:flex;justify-content:flex-end;">
          <button type="submit" class="btn btn-primary"><i class="bi bi-key-fill"></i> Update Password</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
