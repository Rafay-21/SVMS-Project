<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('manage_settings');
$page_title = 'Custom Fields';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $label       = sanitize($_POST['label']        ?? '');
        $field_name  = sanitize($_POST['field_name']   ?? '');
        $field_type  = sanitize($_POST['field_type']   ?? 'text');
        $required    = isset($_POST['required']) ? 1 : 0;
        $options     = sanitize($_POST['options']      ?? '');
        if ($label && $field_name) {
            query_exec(
                'INSERT INTO custom_fields (label, field_name, field_type, is_required, options, is_active, sort_order, created_at)
                 VALUES (?,?,?,?,?,1,(SELECT COALESCE(MAX(sort_order),0)+1 FROM custom_fields cf2),NOW())',
                'sssisi', [$label, $field_name, $field_type, $required, $options]
            );
            flash('success', 'Custom field created.');
        }
    } elseif ($action === 'toggle') {
        $fid = (int)($_POST['id'] ?? 0);
        if ($fid) { query_exec('UPDATE custom_fields SET is_active = NOT is_active WHERE id=?', 'i', [$fid]); }
    } elseif ($action === 'delete') {
        $fid = (int)($_POST['id'] ?? 0);
        if ($fid) { query_exec('DELETE FROM custom_fields WHERE id=?', 'i', [$fid]); flash('info', 'Field deleted.'); }
    }
    header('Location: ' . BASE_URL . 'pages/custom_fields.php');
    exit;
}

$fields = query_all('SELECT * FROM custom_fields ORDER BY sort_order ASC, created_at ASC');
include __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:900px;">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="bi bi-input-cursor-text" style="color:var(--secondary);"></i> Custom Fields</h1>
      <p class="page-subtitle">Add extra fields to the visitor registration form.</p>
    </div>
    <button class="btn btn-primary" onclick="SVMS.modal.open('modal-create-field')">
      <i class="bi bi-plus-lg"></i> Add Field
    </button>
  </div>

  <div class="card">
    <?php if (empty($fields)): ?>
      <div class="empty-state">
        <img src="<?= BASE_URL ?>assets/img/empty-state.svg" width="120" alt="">
        <h3>No Custom Fields</h3>
        <p>Add custom fields to collect additional visitor data.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr><th>Label</th><th>Field Name</th><th>Type</th><th>Required</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($fields as $f): ?>
            <tr>
              <td style="font-weight:500;"><?= e($f['label']) ?></td>
              <td><code style="font-size:12px;background:var(--bg-alt);padding:2px 6px;border-radius:4px;"><?= e($f['field_name']) ?></code></td>
              <td><span class="badge badge-secondary"><?= e($f['field_type']) ?></span></td>
              <td><?= $f['is_required'] ? '<span class="badge badge-warning">Required</span>' : '<span style="color:var(--text-muted);font-size:13px;">Optional</span>' ?></td>
              <td><?= $f['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Hidden</span>' ?></td>
              <td style="display:flex;gap:6px;">
                <form method="POST" action="" style="display:inline;">
                  <?php csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id"     value="<?= (int)$f['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline" data-tooltip="<?= $f['is_active'] ? 'Hide' : 'Show' ?>">
                    <i class="bi bi-<?= $f['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                  </button>
                </form>
                <form method="POST" action="" style="display:inline;">
                  <?php csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id"     value="<?= (int)$f['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this field?')">
                    <i class="bi bi-trash-fill"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Create Field Modal -->
<div id="modal-create-field" class="modal" role="dialog" aria-modal="true" aria-labelledby="cf-modal-title">
  <div class="modal-header">
    <h2 class="modal-title" id="cf-modal-title"><i class="bi bi-plus-circle-fill"></i> New Custom Field</h2>
    <button class="btn btn-ghost btn-icon" onclick="SVMS.modal.close('modal-create-field')" aria-label="Close">&times;</button>
  </div>
  <form method="POST" action="" data-validate="true" novalidate>
    <div class="modal-body">
      <?php csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label>Label <span class="required">*</span></label>
        <input type="text" name="label" id="cf-label" class="form-control" data-rules="required" required placeholder="e.g. Company Name">
      </div>
      <div class="form-group">
        <label>Field Name (slug) <span class="required">*</span></label>
        <input type="text" name="field_name" id="cf-field-name" class="form-control" data-rules="required" required placeholder="e.g. company_name" pattern="[a-z0-9_]+">
        <span class="form-help">Lowercase, underscores only. Auto-generated from label.</span>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Field Type</label>
          <select name="field_type" id="cf-type" class="form-control" onchange="document.getElementById('cf-options-group').style.display=this.value==='select'?'block':'none'">
            <option value="text">Text</option>
            <option value="number">Number</option>
            <option value="email">Email</option>
            <option value="tel">Phone</option>
            <option value="textarea">Textarea</option>
            <option value="select">Dropdown (select)</option>
            <option value="checkbox">Checkbox</option>
            <option value="date">Date</option>
          </select>
        </div>
        <div class="form-group">
          <label>&nbsp;</label>
          <label class="form-check" style="margin-top:10px;">
            <input type="checkbox" name="required" class="form-check-input">
            Make this field required
          </label>
        </div>
      </div>
      <div class="form-group" id="cf-options-group" style="display:none;">
        <label>Options (comma-separated)</label>
        <input type="text" name="options" class="form-control" placeholder="Option A, Option B, Option C">
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="SVMS.modal.close('modal-create-field')">Cancel</button>
      <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create Field</button>
    </div>
  </form>
</div>

<script>
// Auto-slug from label
document.getElementById('cf-label').addEventListener('input', function() {
  document.getElementById('cf-field-name').value = this.value.toLowerCase().trim().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
