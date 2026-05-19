<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('view_feedback');
$page_title = 'Visitor Feedback';

$per_page = 20;
$page_num = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

$total_row = query_one('SELECT COUNT(*) cnt FROM feedback');
$total     = (int)($total_row['cnt'] ?? 0);
$pages     = max(1, (int)ceil($total / $per_page));

$feedbacks = query_all(
    'SELECT f.id, f.visit_id, f.rating, f.comment AS notes, f.source, f.created_at,
            v.name AS visitor_name,
            v.badge_number
     FROM feedback f
     JOIN visit_log vl ON vl.id = f.visit_id
     JOIN visitors v   ON v.id  = vl.visitor_id
     ORDER BY f.created_at DESC LIMIT ' . $per_page . ' OFFSET ' . $offset
);

// Average rating
$avg_row = query_one('SELECT AVG(rating) avg_rating, COUNT(*) total FROM feedback WHERE rating IS NOT NULL');
$avg_rating = round((float)($avg_row['avg_rating'] ?? 0), 1);
$total_fb   = (int)($avg_row['total'] ?? 0);

// Rating distribution (1–5)
$dist_rows = query_all('SELECT rating, COUNT(*) AS cnt FROM feedback WHERE rating IS NOT NULL GROUP BY rating ORDER BY rating');
$dist = array_fill(1, 5, 0);
foreach ($dist_rows as $d) { $dist[(int)$d['rating']] = (int)$d['cnt']; }
$dist_max = max($dist) ?: 1;

include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="bi bi-chat-quote-fill" style="color:var(--secondary);"></i> Visitor Feedback</h1>
      <p class="page-subtitle">Review ratings and comments submitted by visitors.</p>
    </div>
  </div>

  <!-- Summary -->
  <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:var(--space-5);">
    <div class="stat-card">
      <div class="stat-info">
        <div class="stat-number"><?= $avg_rating > 0 ? $avg_rating : '—' ?></div>
        <div class="stat-label">Average Rating</div>
        <?php if ($avg_rating > 0): ?>
        <div style="color:var(--warning);margin-top:4px;">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="bi bi-star<?= $avg_rating >= $i ? '-fill' : ($avg_rating >= $i - 0.5 ? '-half' : '') ?>"></i>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="stat-card-icon accent"><i class="bi bi-star-fill"></i></div>
    </div>
    <div class="stat-card">
      <div class="stat-info">
        <div class="stat-number"><?= $total_fb ?></div>
        <div class="stat-label">Total Responses</div>
      </div>
      <div class="stat-card-icon primary"><i class="bi bi-chat-fill"></i></div>
    </div>
  </div>

  <!-- Rating Distribution -->
  <?php if ($total_fb > 0): ?>
  <div class="card" style="margin-bottom:var(--space-5);">
    <div class="card-header"><h3 class="card-title"><i class="bi bi-bar-chart-fill" style="color:var(--secondary);margin-right:6px;"></i>Rating Distribution</h3></div>
    <div class="card-body" style="padding:20px;">
      <?php for ($star = 5; $star >= 1; $star--): $cnt = $dist[$star]; $pct = $dist_max ? round($cnt / $dist_max * 100) : 0; ?>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;font-size:13px;">
        <span style="width:28px;text-align:right;font-weight:600;"><?= $star ?>★</span>
        <div style="flex:1;background:var(--bg);border-radius:4px;height:14px;overflow:hidden;">
          <div style="height:100%;width:<?= $pct ?>%;background:var(--warning);border-radius:4px;transition:width .4s;"></div>
        </div>
        <span style="width:36px;color:var(--text-muted);"><?= $cnt ?></span>
      </div>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="card">
    <?php if (empty($feedbacks)): ?>
      <div class="empty-state">
        <img src="<?= BASE_URL ?>assets/img/empty-state.svg" width="120" alt="">
        <h3>No Feedback Yet</h3>
        <p>Feedback is collected from the kiosk check-out step.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr><th>Visitor</th><th>Rating</th><th>Comment</th><th>Source</th><th>Date</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($feedbacks as $fb): ?>
            <tr>
              <td>
                <div style="font-weight:500;"><?= e($fb['visitor_name'] ?? 'Anonymous') ?></div>
                <?php if ($fb['badge_number']): ?><div style="font-size:11px;color:var(--text-muted);"><?= e($fb['badge_number']) ?></div><?php endif; ?>
              </td>
              <td>
                <?php if ($fb['rating']): ?>
                  <div style="color:var(--warning);white-space:nowrap;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="bi bi-star<?= $fb['rating'] >= $i ? '-fill' : '' ?>" style="font-size:14px;"></i>
                    <?php endfor; ?>
                    <span style="font-size:12px;color:var(--text-muted);margin-left:4px;"><?= (int)$fb['rating'] ?>/5</span>
                  </div>
                <?php else: ?>
                  <span style="color:var(--text-muted);">—</span>
                <?php endif; ?>
              </td>
              <td style="max-width:320px;font-size:var(--text-sm);"><?= e($fb['notes'] ?? '') ?: '<span style="color:var(--text-muted);">—</span>' ?></td>
              <td>
                <?php if (($fb['source'] ?? 'staff') === 'visitor'): ?>
                  <span class="badge badge-success" style="font-size:10px;">Visitor</span>
                <?php else: ?>
                  <span class="badge badge-primary" style="font-size:10px;">Staff</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;"><?= format_datetime($fb['created_at'] ?? '', 'M d, Y') ?></td>
              <td>
                <a href="<?= BASE_URL ?>pages/visitor_detail.php?id=<?= (int)$fb['visit_id'] ?>"
                   class="btn btn-sm btn-secondary" title="View visit">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1): ?>
      <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:var(--text-sm);color:var(--text-muted);">Page <?= $page_num ?> of <?= $pages ?></span>
        <nav class="pagination" aria-label="Pagination">
          <?php if ($page_num > 1): ?>
            <a class="page-link" href="?page=<?= $page_num - 1 ?>"><i class="bi bi-chevron-left"></i></a>
          <?php endif; ?>
          <?php for ($i = max(1,$page_num-2); $i <= min($pages,$page_num+2); $i++): ?>
            <a class="page-link <?= $i === $page_num ? 'active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($page_num < $pages): ?>
            <a class="page-link" href="?page=<?= $page_num + 1 ?>"><i class="bi bi-chevron-right"></i></a>
          <?php endif; ?>
        </nav>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
