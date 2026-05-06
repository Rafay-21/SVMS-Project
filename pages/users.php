<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('manage_users');
$page_title = 'User Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = sanitize($_POST['name']  ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $role  = sanitize($_POST['role']  ?? 'receptionist');
        $pass  = $_POST['password'] ?? '';
        $pw_err = validate_password_strength($pass);
        if (!$name || !$email || !$pass) {
            flash('error', 'Name, email and password are required.');
        } elseif ($pw_err !== '') {
            flash('error', $pw_err);
        } elseif (query_one('SELECT id FROM admin_users WHERE email=? LIMIT 1', 's', [$email])) {
            flash('error', 'An account with that email already exists.');
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            query_exec(
                'INSERT INTO admin_users (name, email, password_hash, role, is_active, created_at) VALUES (?,?,?,?,1,NOW())',
                'ssss', [$name, $email, $hash, $role]
            );
            $new_uid = last_insert_id();
            record_password_history($new_uid, $hash);
            log_action('user_create', $new_uid, json_encode(['email' => $email, 'role' => $role]));
            flash('success', 'User created successfully.');
        }
    } elseif ($action === 'toggle_active') {
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid && $uid !== (int)$_SESSION['admin_id']) {
            query_exec('UPDATE admin_users SET is_active = NOT is_active WHERE id=?', 'i', [$uid]);
            log_action('user_toggle', $uid);
            flash('info', 'User status updated.');
        }
    } elseif ($action === 'delete') {
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid && $uid !== (int)$_SESSION['admin_id']) {
            query_exec('DELETE FROM admin_users WHERE id=?', 'i', [$uid]);
            log_action('user_delete', $uid);
            flash('info', 'User deleted.');
        }
    }
    header('Location: ' . BASE_URL . 'pages/users.php');
    exit;
}

$q     = sanitize($_GET['q'] ?? '');
$where = '1=1';
$params = []; $types = '';
if ($q) {
    $like = '%' . $q . '%';
    $where .= ' AND (name LIKE ? OR email LIKE ?)';
    $params = [$like, $like]; $types = 'ss';
}
$users = query_all("SELECT id,name,email,role,is_active,created_at,last_login FROM admin_users WHERE $where ORDER BY created_at DESC", $types, $params);
include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="bi bi-people-fill" style="color:var(--secondary);"></i> User Management</h1>
      <p class="page-subtitle">Manage administrator accounts and roles.</p>
    </div>
    <button class="btn btn-primary" onclick="SVMS.modal.open('modal-create-user')">
      <i class="bi bi-person-plus-fill"></i> Add User
    </button>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Admin Users (<?= count($users) ?>)</h3>
      <form method="GET" action="" style="display:flex;gap:8px;">
        <div class="search-input">
          <i class="bi bi-search"></i>
          <input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Name or email…" style="padding-left:34px;font-size:13px;padding-top:7px;padding-bottom:7px;">
        </div>
        <button type="submit" class="btn btn-secondary btn-sm"><i class="bi bi-funnel"></i></button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr><th>User</th><th>Role</th><th>Status</th><th>Last Login</th><th>Created</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div style="font-weight:600;"><?= e($u['name']) ?></div>
              <div style="font-size:11px;color:var(--text-muted);"><?= e($u['email']) ?></div>
            </td>
            <td><span class="badge badge-secondary"><?= e(role_label($u['role'])) ?></span></td>
            <td>
              <?php if ($u['is_active']): ?>
                <span class="badge badge-success">Active</span>
              <?php else: ?>
                <span class="badge badge-danger">Disabled</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--text-muted);">
              <?= $u['last_login'] ? format_datetime($u['last_login'], 'M d, Y') : 'Never' ?>
            </td>
            <td style="font-size:12px;color:var(--text-muted);"><?= format_datetime($u['created_at'], 'M d, Y') ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <?php if ((int)$u['id'] !== (int)$_SESSION['admin_id']): ?>
                  <form method="POST" action="" style="display:inline;">
                    <?php csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id"     value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline" data-tooltip="<?= $u['is_active'] ? 'Disable' : 'Enable' ?>">
                      <i class="bi bi-<?= $u['is_active'] ? 'person-dash-fill' : 'person-check-fill' ?>"></i>
                    </button>
                  </form>
                  <form method="POST" action="" style="display:inline;">
                    <?php csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"     value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user permanently?')" data-tooltip="Delete">
                      <i class="bi bi-trash-fill"></i>
                    </button>
                  </form>
                <?php else: ?>
                  <span style="font-size:12px;color:var(--text-muted);">You</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create User Modal -->
<div id="modal-create-user" class="modal" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
  <div class="modal-header">
    <h2 class="modal-title" id="user-modal-title"><i class="bi bi-person-plus-fill"></i> New Admin User</h2>
    <button class="btn btn-ghost btn-icon" onclick="SVMS.modal.close('modal-create-user')" aria-label="Close">&times;</button>
  </div>
  <form method="POST" action="" data-validate="true" novalidate>
    <div class="modal-body">
      <?php csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label>Full Name <span class="required">*</span></label>
        <input type="text" name="name" class="form-control" data-rules="required" required autocomplete="off">
      </div>
      <div class="form-group">
        <label>Email <span class="required">*</span></label>
        <input type="email" name="email" class="form-control" data-rules="required,email" required autocomplete="off">
      </div>
      <div class="form-group">
        <label>Role</label>
        <select name="role" class="form-control">
          <option value="receptionist">Receptionist</option>
          <option value="security">Security Officer</option>
          <option value="manager">Manager</option>
          <option value="admin">Administrator</option>
          <option value="super_admin">Super Admin</option>
        </select>
      </div>
      <div class="form-group">
        <label>Password <span class="required">*</span></label>
        <div class="input-group">
          <input type="password" name="password" id="new-user-pass" class="form-control" data-rules="required" data-rules-minlen="8" required autocomplete="new-password" minlength="8">
          <button type="button" class="input-group-btn" onclick="var i=document.getElementById('new-user-pass');i.type=i.type==='password'?'text':'password';"><i class="bi bi-eye"></i></button>
        </div>
        <span class="form-help">Minimum 8 characters.</span>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="SVMS.modal.close('modal-create-user')">Cancel</button>
      <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus-fill"></i> Create User</button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
