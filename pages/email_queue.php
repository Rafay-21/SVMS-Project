<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('manage_settings');

$page_title = 'Email Queue';

// Handle manual retry / cancel actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = sanitize($_POST['action'] ?? '');
    $id     = (int)($_POST['id']     ?? 0);

    if ($id > 0) {
        if ($action === 'retry') {
            query_exec(
                "UPDATE email_queue SET status='pending', attempts=0, scheduled_at=NOW(), last_error=NULL WHERE id=?",
                'i', [$id]
            );
            log_action('email_queue_retry', $id, json_encode(['by' => $_SESSION['admin_id']]));
            flash('success', 'Email re-queued for immediate delivery.');
        } elseif ($action === 'cancel') {
            query_exec("UPDATE email_queue SET status='failed', last_error='Cancelled by admin' WHERE id=?", 'i', [$id]);
            log_action('email_queue_cancel', $id, json_encode(['by' => $_SESSION['admin_id']]));
            flash('info', 'Email cancelled.');
        } elseif ($action === 'retry_all_failed') {
            query_exec(
                "UPDATE email_queue SET status='pending', attempts=0, scheduled_at=NOW(), last_error=NULL WHERE status='failed'",
                '', []
            );
            update_setting('email_queue_notice', '0');
            log_action('email_queue_retry_all', 0, json_encode(['by' => $_SESSION['admin_id']]));
            flash('success', 'All failed emails re-queued.');
        }
    }
    header('Location: ' . BASE_URL . 'pages/email_queue.php');
    exit;
}

$filter = sanitize($_GET['status'] ?? 'all');
$where  = $filter !== 'all' ? 'WHERE status=?' : 'WHERE 1=1';
$types  = $filter !== 'all' ? 's' : '';
$params = $filter !== 'all' ? [$filter] : [];

$rows = query_all(
    "SELECT id, to_email, subject, status, attempts, last_error, scheduled_at, created_at, sent_at, related_type, related_id
     FROM email_queue $where
     ORDER BY created_at DESC LIMIT 200",
    $types, $params
);

// Counts
$counts = [];
foreach (['pending', 'sending', 'sent', 'failed'] as $s) {
    $r = query_one("SELECT COUNT(*) AS c FROM email_queue WHERE status=?", 's', [$s]);
    $counts[$s] = (int)($r['c'] ?? 0);
}
$counts['all'] = array_sum($counts);

include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="bi bi-send-fill" style="color:var(--secondary);"></i> Email Queue</h1>
      <p class="page-subtitle">Monitor outbound email status and retry failed messages.</p>
    </div>
    <?php if ($counts['failed'] > 0): ?>
    <form method="POST" action="">
      <?php csrf_field() ?>
      <input type="hidden" name="action" value="retry_all_failed">
      <input type="hidden" name="id" value="0">
      <button type="submit" class="btn btn-warning">
        <i class="bi bi-arrow-repeat"></i> Retry All Failed (<?= $counts['failed'] ?>)
      </button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Status tabs -->
  <div class="tabs" style="margin-bottom:var(--space-5);">
    <?php foreach (['all' => 'All', 'pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed', 'sending' => 'Sending'] as $key => $label): ?>
      <a class="tab <?= $filter === $key ? 'active' : '' ?>" href="?status=<?= $key ?>">
        <?= $label ?>
        <?php if (isset($counts[$key]) && $counts[$key] > 0): ?>
          <span class="badge badge-<?= $key === 'failed' ? 'danger' : ($key === 'sent' ? 'success' : 'warning') ?>"
                style="margin-left:6px;font-size:11px;"><?= $counts[$key] ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <?php if (empty($rows)): ?>
    <div class="empty-state">
      <i class="bi bi-inbox" style="font-size:40px;opacity:.3;"></i>
      <h3>No emails found</h3>
      <p>The queue is empty for this filter.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>To</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Attempts</th>
            <th>Scheduled / Sent</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row):
            $badge_cls = match($row['status']) {
              'sent'    => 'success',
              'failed'  => 'danger',
              'sending' => 'info',
              default   => 'warning',
            };
          ?>
          <tr>
            <td style="font-size:11px;color:var(--text-muted);"><?= (int)$row['id'] ?></td>
            <td>
              <div style="font-weight:500;font-size:13px;"><?= e($row['to_email']) ?></div>
              <?php if ($row['related_type']): ?>
              <div style="font-size:11px;color:var(--text-muted);">
                <?= e($row['related_type']) ?><?= $row['related_id'] ? ' #' . (int)$row['related_id'] : '' ?>
              </div>
              <?php endif; ?>
            </td>
            <td style="max-width:280px;">
              <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;"
                   title="<?= e($row['subject']) ?>">
                <?= e($row['subject']) ?>
              </div>
            </td>
            <td><span class="badge badge-<?= $badge_cls ?>"><?= ucfirst(e($row['status'])) ?></span></td>
            <td style="text-align:center;"><?= (int)$row['attempts'] ?></td>
            <td style="font-size:12px;color:var(--text-muted);">
              <?php if ($row['sent_at']): ?>
                ✓ <?= format_datetime($row['sent_at'], 'M d g:i A') ?>
              <?php else: ?>
                <?= format_datetime($row['scheduled_at'], 'M d g:i A') ?>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:6px;">
                <?php if (in_array($row['status'], ['failed', 'pending'], true)): ?>
                <form method="POST" action="" style="display:inline;">
                  <?php csrf_field() ?>
                  <input type="hidden" name="action" value="retry">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" title="Retry">
                    <i class="bi bi-arrow-repeat"></i>
                  </button>
                </form>
                <?php endif; ?>
                <?php if ($row['status'] !== 'sent'): ?>
                <form method="POST" action="" style="display:inline;"
                      data-confirm="Cancel this email?">
                  <?php csrf_field() ?>
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger" title="Cancel">
                    <i class="bi bi-x-lg"></i>
                  </button>
                </form>
                <?php endif; ?>
                <?php if ($row['last_error']): ?>
                <button class="btn btn-sm btn-ghost" title="<?= e($row['last_error']) ?>"
                        onclick="alert(<?= json_encode($row['last_error']) ?>)">
                  <i class="bi bi-exclamation-circle text-danger"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
