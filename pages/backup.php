<?php
/**
 * pages/backup.php — Backup & Restore
 * Super Admin only (manage_settings + super_admin role).
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('manage_settings');

// Only super_admin may access this page
$_bk_role = role_slug((int)($_SESSION['role_id'] ?? 0));
if ($_bk_role !== 'super_admin') {
    require_permission('__never__'); // triggers the 403 page
}

$page_title = 'Backup & Restore';

// ── Handle POST actions (download / delete) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_backup') {
        $filename = basename(sanitize($_POST['filename'] ?? ''));
        if ($filename) {
            $filepath = LOG_DIR . '/backups/' . $filename;
            if (file_exists($filepath)) @unlink($filepath);
            $stmt = $GLOBALS['conn']->prepare('DELETE FROM backups WHERE filename = ?');
            if ($stmt) { $stmt->bind_param('s', $filename); $stmt->execute(); $stmt->close(); }
            log_action('backup_delete', 0, json_encode(['file' => $filename]));
            flash('info', 'Backup deleted: ' . $filename);
        }
    }

    if ($action === 'download') {
        $filename = basename(sanitize($_POST['filename'] ?? ''));
        $filepath = LOG_DIR . '/backups/' . $filename;
        if ($filename && file_exists($filepath) && is_file($filepath)) {
            log_action('backup_download', 0, json_encode(['file' => $filename]));
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: no-store');
            readfile($filepath);
            exit;
        }
        flash('error', 'Backup file not found.');
    }

    header('Location: ' . BASE_URL . 'pages/backup.php');
    exit;
}

// ── Load backup history from DB ───────────────────────────────
$backups = query_all(
    "SELECT b.*, a.name AS admin_name
     FROM backups b
     LEFT JOIN admins a ON a.id = b.created_by
     WHERE b.status != 'deleted'
     ORDER BY b.created_at DESC
     LIMIT 100"
);

$backup_dir = LOG_DIR . '/backups/';
if (!is_dir($backup_dir)) mkdir($backup_dir, 0750, true);

include __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:1000px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <i class="bi bi-database-fill-down" style="color:var(--secondary);"></i>
        Backup &amp; Restore
      </h1>
      <p class="page-subtitle">Create and manage database backups. Super Admin only.</p>
    </div>
  </div>

  <!-- Create Backup Card -->
  <div class="card" style="margin-bottom:var(--space-5);">
    <div class="card-header">
      <h3 class="card-title">
        <i class="bi bi-cloud-upload-fill" style="color:var(--secondary);"></i>
        Create Backup
      </h3>
    </div>
    <div class="card-body">
      <p style="color:var(--text-muted);font-size:var(--text-sm);margin-bottom:16px;">
        Generates a full SQL dump (<code>mysqldump</code> when available, PHP fallback otherwise).
        Backups are stored in <code>logs/backups/</code> and auto-pruned after
        <strong>30 days</strong> or when more than <strong>20</strong> copies exist.
      </p>
      <div id="bk-create-result" style="display:none;margin-bottom:16px;"></div>
      <button type="button" id="btn-create-backup" class="btn btn-primary btn-lg">
        <i class="bi bi-database-fill-down"></i> Create Backup Now
      </button>
    </div>
  </div>

  <!-- Backup History -->
  <div class="card" style="margin-bottom:var(--space-5);">
    <div class="card-header">
      <h3 class="card-title">
        <i class="bi bi-archive-fill" style="color:var(--secondary);"></i>
        Backup History
        <span class="badge badge-secondary" style="margin-left:8px;"><?= count($backups) ?></span>
      </h3>
    </div>

    <?php if (empty($backups)): ?>
      <div class="empty-state">
        <i class="bi bi-archive" style="font-size:64px;color:var(--border);display:block;margin-bottom:12px;"></i>
        <h3>No Backups Yet</h3>
        <p>Click "Create Backup Now" to generate your first backup.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Filename</th>
              <th>Size</th>
              <th>Type</th>
              <th>Created By</th>
              <th>Created At</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($backups as $bk):
              $filepath = $backup_dir . $bk['filename'];
              $exists   = !empty($bk['filename']) && file_exists($filepath);
              $sz       = (int)$bk['size_bytes'];
              $size_str = $sz >= 1048576
                ? round($sz / 1048576, 1) . ' MB'
                : ($sz >= 1024 ? round($sz / 1024, 0) . ' KB' : $sz . ' B');
            ?>
            <tr>
              <td>
                <i class="bi bi-file-earmark-zip" style="color:var(--secondary);margin-right:6px;"></i>
                <span style="font-size:var(--text-sm);font-family:monospace;"><?= e($bk['filename']) ?></span>
              </td>
              <td style="font-size:var(--text-sm);color:var(--text-muted);"><?= $size_str ?></td>
              <td>
                <?php if ($bk['type'] === 'automated'): ?>
                  <span class="badge badge-info">Auto</span>
                <?php else: ?>
                  <span class="badge badge-secondary">Manual</span>
                <?php endif; ?>
              </td>
              <td style="font-size:var(--text-sm);">
                <?= $bk['admin_name'] ? e($bk['admin_name']) : '<em style="color:var(--text-muted);">Cron</em>' ?>
              </td>
              <td style="font-size:var(--text-sm);color:var(--text-muted);">
                <?= format_datetime($bk['created_at']) ?>
              </td>
              <td>
                <?php if ($bk['status'] === 'ok'): ?>
                  <span class="badge badge-success">OK</span>
                <?php elseif ($bk['status'] === 'error'): ?>
                  <span class="badge badge-danger" data-tooltip="<?= e($bk['error'] ?? '') ?>">Error</span>
                <?php else: ?>
                  <span class="badge badge-secondary"><?= e($bk['status']) ?></span>
                <?php endif; ?>
              </td>
              <td style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if ($exists): ?>
                  <form method="POST" action="">
                    <?php csrf_field() ?>
                    <input type="hidden" name="action"   value="download">
                    <input type="hidden" name="filename" value="<?= e($bk['filename']) ?>">
                    <button type="submit" class="btn btn-sm btn-success" data-tooltip="Download">
                      <i class="bi bi-download"></i>
                    </button>
                  </form>
                  <button type="button" class="btn btn-sm btn-warning btn-restore-trigger"
                          data-filename="<?= e($bk['filename']) ?>"
                          aria-label="Restore backup <?= e($bk['filename']) ?>"
                          data-tooltip="Restore this backup">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                <?php else: ?>
                  <span style="font-size:var(--text-xs);color:var(--danger);">File missing</span>
                <?php endif; ?>
                <form method="POST" action="" onsubmit="return confirm('Permanently delete this backup?');">
                  <?php csrf_field() ?>
                  <input type="hidden" name="action"   value="delete_backup">
                  <input type="hidden" name="filename" value="<?= e($bk['filename']) ?>">
                  <button type="submit" class="btn btn-sm btn-danger" data-tooltip="Delete"
                          aria-label="Delete backup <?= e($bk['filename']) ?>">
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

  <!-- Info Panel -->
  <div class="alert alert-info" style="margin-bottom:var(--space-5);">
    <i class="alert-icon bi bi-info-circle-fill"></i>
    <div class="alert-body">
      <strong>Automated Backups:</strong> Schedule <code>scripts/cron_daily_backup.php</code> via cron
      (<code>0 3 * * * php /path/to/svms/scripts/cron_daily_backup.php</code>).
      Store backup files off-server for full disaster recovery.
    </div>
  </div>
</div>

<!-- ── Restore Modal ─────────────────────────────────────────── -->
<div id="restore-modal" class="modal-overlay" role="dialog" aria-modal="true"
     aria-labelledby="restore-modal-title" style="display:none;">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header" style="background:var(--danger);color:#fff;border-radius:12px 12px 0 0;">
      <h3 class="modal-title" id="restore-modal-title" style="color:#fff;">
        <i class="bi bi-exclamation-triangle-fill"></i> Restore Database
      </h3>
      <button type="button" id="restore-modal-close" class="modal-close"
              aria-label="Close restore dialog"
              style="background:transparent;border:none;color:#fff;font-size:1.4rem;cursor:pointer;line-height:1;">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="modal-body">
      <div class="alert alert-danger" style="margin-bottom:16px;">
        <i class="alert-icon bi bi-exclamation-triangle-fill"></i>
        <div class="alert-body">
          <strong>Warning:</strong> This will <strong>overwrite ALL current data</strong> with the backup contents.
          This action cannot be undone. All users will be logged out.
        </div>
      </div>

      <form id="restore-form" novalidate>
        <?php csrf_field() ?>
        <input type="hidden" name="source"   id="restore-source"   value="existing">
        <input type="hidden" name="filename" id="restore-filename" value="">

        <!-- Source selector -->
        <fieldset style="border:none;padding:0;margin:0 0 16px;">
          <legend class="form-label" style="font-weight:600;float:none;width:100%;padding:0;margin-bottom:8px;">Restore Source</legend>
          <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="radio" name="restore_src_radio" value="existing" checked> Use selected backup
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="radio" name="restore_src_radio" value="upload"> Upload .sql / .sql.gz
            </label>
          </div>
        </fieldset>

        <div id="restore-existing-info" style="margin-bottom:16px;">
          <p style="font-size:var(--text-sm);color:var(--text-muted);">
            Selected backup: <strong id="restore-selected-name">—</strong>
          </p>
        </div>

        <div id="restore-upload-area" style="display:none;margin-bottom:16px;">
          <label class="form-label" for="restore-file-input">
            Upload SQL File <span aria-hidden="true" style="color:var(--danger);">*</span>
          </label>
          <input type="file" id="restore-file-input" name="sql_file" accept=".sql,.gz"
                 class="form-control" aria-required="true">
          <p style="font-size:var(--text-xs);color:var(--text-muted);margin-top:4px;">Max 50 MB. .sql or .sql.gz only.</p>
        </div>

        <div class="form-group" style="margin-bottom:16px;">
          <label class="form-label" for="restore-confirm-word">
            Type <strong>RESTORE</strong> to confirm <span aria-hidden="true" style="color:var(--danger);">*</span>
          </label>
          <input type="text" id="restore-confirm-word" name="confirm_word"
                 class="form-control" placeholder="RESTORE" autocomplete="off" aria-required="true">
        </div>

        <div class="form-group" style="margin-bottom:20px;">
          <label class="form-label" for="restore-password">
            Your Password <span aria-hidden="true" style="color:var(--danger);">*</span>
          </label>
          <input type="password" id="restore-password" name="password"
                 class="form-control" autocomplete="current-password" aria-required="true"
                 placeholder="Enter your current password">
        </div>

        <div id="restore-error" class="alert alert-danger" role="alert" style="display:none;margin-bottom:16px;"></div>

        <div style="display:flex;gap:12px;justify-content:flex-end;">
          <button type="button" class="btn btn-outline-secondary" id="restore-cancel-btn">Cancel</button>
          <button type="submit" class="btn btn-danger" id="restore-submit-btn">
            <i class="bi bi-arrow-counterclockwise"></i> Restore Now
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

  /* ── Create Backup ─────────────────────────────────── */
  const btnCreate    = document.getElementById('btn-create-backup');
  const createResult = document.getElementById('bk-create-result');

  btnCreate.addEventListener('click', async () => {
    btnCreate.disabled = true;
    btnCreate.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating…';
    createResult.style.display = 'none';

    try {
      const res  = await fetch('<?= BASE_URL ?>api/create_backup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ csrf_token: csrfToken }),
      });
      const data = await res.json();
      createResult.style.display = 'block';
      if (data.ok) {
        createResult.className = 'alert alert-success';
        createResult.innerHTML =
          '<i class="bi bi-check-circle-fill alert-icon"></i>' +
          '<div class="alert-body"><strong>Backup created!</strong> ' +
          escHtml(data.filename) + ' (' + formatBytes(data.size_bytes) + ')</div>';
        setTimeout(() => location.reload(), 1500);
      } else {
        createResult.className = 'alert alert-danger';
        createResult.innerHTML =
          '<i class="bi bi-exclamation-triangle-fill alert-icon"></i>' +
          '<div class="alert-body"><strong>Error:</strong> ' + escHtml(data.error || 'Unknown error') + '</div>';
        btnCreate.disabled = false;
        btnCreate.innerHTML = '<i class="bi bi-database-fill-down"></i> Create Backup Now';
      }
    } catch (e) {
      createResult.style.display = 'block';
      createResult.className = 'alert alert-danger';
      createResult.innerHTML =
        '<i class="bi bi-exclamation-triangle-fill alert-icon"></i>' +
        '<div class="alert-body">Network error. Please try again.</div>';
      btnCreate.disabled = false;
      btnCreate.innerHTML = '<i class="bi bi-database-fill-down"></i> Create Backup Now';
    }
  });

  /* ── Restore Modal ─────────────────────────────────── */
  const modal          = document.getElementById('restore-modal');
  const closeBtn       = document.getElementById('restore-modal-close');
  const cancelBtn      = document.getElementById('restore-cancel-btn');
  const restoreForm    = document.getElementById('restore-form');
  const restoreError   = document.getElementById('restore-error');
  const submitBtn      = document.getElementById('restore-submit-btn');
  const srcRadios      = document.querySelectorAll('input[name="restore_src_radio"]');
  const existingInfo   = document.getElementById('restore-existing-info');
  const uploadArea     = document.getElementById('restore-upload-area');
  const filenameHidden = document.getElementById('restore-filename');
  const sourceHidden   = document.getElementById('restore-source');
  const selectedName   = document.getElementById('restore-selected-name');

  document.querySelectorAll('.btn-restore-trigger').forEach(btn => {
    btn.addEventListener('click', () => {
      const fn = btn.getAttribute('data-filename');
      filenameHidden.value = fn;
      sourceHidden.value   = 'existing';
      selectedName.textContent = fn;
      document.querySelector('input[name="restore_src_radio"][value="existing"]').checked = true;
      existingInfo.style.display = '';
      uploadArea.style.display   = 'none';
      restoreError.style.display = 'none';
      document.getElementById('restore-confirm-word').value = '';
      document.getElementById('restore-password').value     = '';
      modal.style.display = 'flex';
      document.getElementById('restore-confirm-word').focus();
    });
  });

  srcRadios.forEach(r => {
    r.addEventListener('change', () => {
      if (r.value === 'upload' && r.checked) {
        existingInfo.style.display = 'none';
        uploadArea.style.display   = '';
        sourceHidden.value   = 'upload';
        filenameHidden.value = '';
      } else if (r.value === 'existing' && r.checked) {
        existingInfo.style.display = '';
        uploadArea.style.display   = 'none';
        sourceHidden.value = 'existing';
      }
    });
  });

  function closeModal() { modal.style.display = 'none'; }
  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
  });

  restoreForm.addEventListener('submit', async e => {
    e.preventDefault();
    restoreError.style.display = 'none';

    const confirmWord = document.getElementById('restore-confirm-word').value.trim();
    if (confirmWord !== 'RESTORE') {
      restoreError.style.display = 'block';
      restoreError.textContent   = 'You must type RESTORE (all caps) to confirm.';
      return;
    }
    const password = document.getElementById('restore-password').value;
    if (!password) {
      restoreError.style.display = 'block';
      restoreError.textContent   = 'Please enter your password.';
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Restoring…';

    const formData = new FormData();
    formData.append('csrf_token',   document.querySelector('#restore-form input[name="csrf_token"]').value);
    formData.append('source',       sourceHidden.value);
    formData.append('filename',     filenameHidden.value);
    formData.append('confirm_word', confirmWord);
    formData.append('password',     password);

    const fileInput = document.getElementById('restore-file-input');
    if (sourceHidden.value === 'upload' && fileInput.files[0]) {
      formData.append('sql_file', fileInput.files[0]);
    }

    try {
      const res  = await fetch('<?= BASE_URL ?>api/restore_backup.php', {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();
      if (data.ok) {
        closeModal();
        document.body.insertAdjacentHTML('beforeend',
          '<div style="position:fixed;inset:0;background:rgba(0,0,0,.7);display:flex;align-items:center;' +
          'justify-content:center;z-index:99999;" role="alertdialog" aria-live="assertive">' +
          '<div style="background:#fff;padding:40px;border-radius:16px;max-width:420px;text-align:center;">' +
          '<i class="bi bi-check-circle-fill" aria-hidden="true" style="font-size:56px;color:#22c55e;"></i>' +
          '<h2 style="margin:16px 0 8px;">Restore Complete</h2>' +
          '<p>' + escHtml(data.message) + '</p>' +
          '<p style="color:#64748b;font-size:.875rem;">Redirecting to login…</p>' +
          '</div></div>'
        );
        setTimeout(() => { window.location.href = '<?= BASE_URL ?>pages/login.php'; }, 3000);
      } else {
        restoreError.style.display = 'block';
        restoreError.textContent   = data.error || 'Restore failed.';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Restore Now';
      }
    } catch (err) {
      restoreError.style.display = 'block';
      restoreError.textContent   = 'Network error. Please try again.';
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Restore Now';
    }
  });

  /* ── Helpers ───────────────────────────────────────── */
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function formatBytes(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024)    return Math.round(bytes / 1024)     + ' KB';
    return bytes + ' B';
  }
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
