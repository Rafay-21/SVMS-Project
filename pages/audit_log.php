<?php
/**
 * pages/audit_log.php
 * Full audit trail — Super Admin only.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Restrict to super_admin only
$_my_role_slug = role_slug((int)($_SESSION['role_id'] ?? 0));
if ($_my_role_slug !== 'super_admin') {
    require_permission('__nonexistent__'); // triggers 403
}

/* ══════════════════════════════════════════════════════════════
   FILTER PARAMS
   ══════════════════════════════════════════════════════════════ */
$admin_f   = (int)($_GET['admin_id']    ?? 0);
$action_f  = trim($_GET['action_type']  ?? '');
$date_from = trim($_GET['date_from']    ?? date('Y-m-d', strtotime('-30 days')));
$date_to   = trim($_GET['date_to']      ?? date('Y-m-d'));
$entity_f  = trim($_GET['entity_id']    ?? '');
$search_f  = trim($_GET['q']            ?? '');
$sort_dir  = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$_al_ps_raw = (int)($_GET['per_page'] ?? 25);
$page_size  = in_array($_al_ps_raw, [25, 50, 100]) ? $_al_ps_raw : 25;
$page_num  = max(1, (int)($_GET['page'] ?? 1));

/* ── WHERE clause ─────────────────────────────────────────── */
$where  = [];
$types  = '';
$params = [];

$where[] = 'DATE(al.created_at) >= ?'; $types .= 's'; $params[] = $date_from;
$where[] = 'DATE(al.created_at) <= ?'; $types .= 's'; $params[] = $date_to;

if ($admin_f) {
    $where[] = 'al.admin_id = ?'; $types .= 'i'; $params[] = $admin_f;
}
if ($action_f !== '') {
    $where[] = 'al.action = ?'; $types .= 's'; $params[] = $action_f;
}
if ($entity_f !== '' && ctype_digit($entity_f)) {
    $where[] = 'al.target_id = ?'; $types .= 'i'; $params[] = (int)$entity_f;
}
if ($search_f !== '') {
    $like     = '%' . $search_f . '%';
    $where[]  = '(al.action LIKE ? OR al.details LIKE ? OR a.full_name LIKE ?)';
    $types   .= 'sss';
    $params   = array_merge($params, [$like, $like, $like]);
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ── Base SQL ─────────────────────────────────────────────── */
$base_sql = "
    SELECT
        al.id,
        al.action,
        al.target_id,
        al.details,
        al.ip_address,
        al.user_agent,
        al.created_at,
        al.admin_id,
        COALESCE(a.full_name,'System') AS admin_name,
        COALESCE(r.label,'—')          AS role_label
    FROM audit_logs al
    LEFT JOIN admins a ON a.id = al.admin_id
    LEFT JOIN roles r  ON r.id = a.role_id
    $where_sql
";

/* ── CSV Export ───────────────────────────────────────────── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_limit = 50000;
    $erows = query_all($base_sql . " ORDER BY al.created_at $sort_dir LIMIT " . ($export_limit + 1), $types, $params);
    $capped = count($erows) > $export_limit;
    if ($capped) array_pop($erows);

    log_action('export_csv', count($erows), json_encode(['table'=>'audit_logs','filters'=>array_intersect_key($_GET,array_flip(['date_from','date_to','admin_id','action_type','q']))]));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="svms_audit_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-store');
    $fh = fopen('php://output', 'w');
    fputs($fh, "\xEF\xBB\xBF");
    fputcsv($fh, ['ID','Timestamp','Admin','Role','Action','Entity ID','IP Address','User Agent','Details']);
    foreach ($erows as $r) {
        fputcsv($fh, [
            $r['id'], $r['created_at'], $r['admin_name'], $r['role_label'],
            $r['action'], $r['target_id'], $r['ip_address'],
            $r['user_agent'], $r['details'],
        ]);
    }
    fclose($fh);
    exit;
}

/* ── Count + Paginate ─────────────────────────────────────── */
$count_row   = query_one("SELECT COUNT(*) AS total FROM audit_logs al LEFT JOIN admins a ON a.id=al.admin_id $where_sql", $types, $params);
$total_count = (int)($count_row['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_count / $page_size));
$page_num    = min($page_num, $total_pages);
$offset      = ($page_num - 1) * $page_size;

$rows = query_all(
    $base_sql . " ORDER BY al.created_at $sort_dir LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$page_size, $offset])
);

/* ── Sidebar data ─────────────────────────────────────────── */
$admins_list = query_all("SELECT id, full_name FROM admins ORDER BY full_name");

// Distinct action types for the filter dropdown (from last 6 months)
$action_types_rows = query_all(
    "SELECT DISTINCT action FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) ORDER BY action",
    '', []
);
$action_types = array_column($action_types_rows, 'action');

/* ── URL helper ───────────────────────────────────────────── */
function al_url(array $override = []): string {
    $base = [
        'date_from'   => $_GET['date_from']   ?? '',
        'date_to'     => $_GET['date_to']     ?? '',
        'admin_id'    => $_GET['admin_id']    ?? '',
        'action_type' => $_GET['action_type'] ?? '',
        'entity_id'   => $_GET['entity_id']   ?? '',
        'q'           => $_GET['q']           ?? '',
        'dir'         => $_GET['dir']         ?? '',
        'per_page'    => $_GET['per_page']    ?? '',
        'page'        => $_GET['page']        ?? '',
    ];
    $merged = array_filter(array_merge($base, $override), fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($merged);
}

// Action colour map
function al_action_style(string $action): string {
    if (str_contains($action, 'login'))         return 'background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;';
    if (str_contains($action, 'check_in'))      return 'background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;';
    if (str_contains($action, 'check_out'))     return 'background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;';
    if (str_contains($action, 'register'))      return 'background:#faf5ff;color:#6b21a8;border:1px solid #ddd6fe;';
    if (str_contains($action, 'delete'))        return 'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;';
    if (str_contains($action, 'blacklist'))     return 'background:#fef2f2;color:#991b1b;border:1px solid #fecaca;';
    if (str_contains($action, 'edit'))          return 'background:#fffbeb;color:#92400e;border:1px solid #fde68a;';
    if (str_contains($action, 'export'))        return 'background:#f0f9ff;color:#0c4a6e;border:1px solid #bae6fd;';
    if (str_contains($action, 'settings'))      return 'background:#f8fafc;color:#475569;border:1px solid #e2e8f0;';
    return 'background:var(--bg);color:var(--text-muted);border:1px solid var(--border);';
}

// Navigable target link
function al_entity_link(string $action, int $target_id): string {
    if (!$target_id) return '—';
    if (str_contains($action, 'check_in') || str_contains($action, 'check_out') ||
        str_contains($action, 'register') || str_contains($action, 'edit_visit')) {
        return '<a href="' . BASE_URL . 'pages/visitor_detail.php?id=' . $target_id . '" style="color:var(--secondary);font-weight:600;">#' . $target_id . '</a>';
    }
    return '<span style="font-family:monospace;">#' . $target_id . '</span>';
}

$page_title = 'Audit Trail';
include __DIR__ . '/../includes/header.php';
?>

<style>
#al-filter-bar {
  position:sticky; top:0; z-index:50;
  background:var(--bg); padding:16px 0 12px; transition:box-shadow .2s;
}
#al-filter-bar.is-stuck { box-shadow:0 2px 8px rgba(0,0,0,.06); }

.al-table { width:100%; border-collapse:collapse; font-size:12px; }
.al-table thead th {
  background:var(--bg); color:var(--text-muted); font-weight:600;
  font-size:11px; text-transform:uppercase; letter-spacing:.05em;
  padding:9px 12px; border-bottom:2px solid var(--border);
  white-space:nowrap;
}
.al-table tbody tr {
  border-bottom:1px solid var(--border);
  transition:background .1s;
}
.al-table tbody tr:hover { background:var(--bg); }
.al-table tbody td { padding:9px 12px; vertical-align:top; }

.al-action-tag {
  font-size:11px; font-weight:700; font-family:monospace;
  padding:2px 8px; border-radius:4px; display:inline-block; white-space:nowrap;
}

.pager { display:flex; align-items:center; gap:4px; flex-wrap:wrap; }
.pager a, .pager span {
  display:inline-flex; align-items:center; justify-content:center;
  min-width:32px; height:32px; padding:0 8px;
  border:1px solid var(--border); border-radius:6px;
  font-size:13px; font-weight:600; text-decoration:none; color:var(--text-muted);
  transition:background .1s,color .1s;
}
.pager a:hover { background:var(--bg); color:var(--text); }
.pager span.current { background:var(--secondary); color:#fff; border-color:var(--secondary); }
.pager span.disabled { opacity:.4; pointer-events:none; }

@media print {
  #al-filter-bar, .pager, .no-print { display:none !important; }
  .al-table { font-size:10px; }
}
</style>

<div class="container" style="padding-bottom:48px;">

  <!-- Page header -->
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <div>
      <h1 style="font-size:1.4rem;font-weight:700;margin:0 0 4px;color:var(--text);">
        <i class="bi bi-shield-lock-fill" style="color:var(--secondary);margin-right:8px;"></i>Audit Trail
      </h1>
      <p style="font-size:13px;color:var(--text-muted);margin:0;">Every administrative action is recorded here.</p>
    </div>
    <a href="<?= e(al_url(['export' => 'csv', 'page' => ''])) ?>"
       class="btn btn-secondary btn-sm no-print">
      <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
    </a>
  </div>

  <!-- Readonly notice -->
  <div style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;background:#fffbeb;border:1.5px solid #fde68a;border-radius:8px;margin-bottom:20px;">
    <i class="bi bi-lock-fill" style="color:#92400e;font-size:16px;flex-shrink:0;margin-top:2px;"></i>
    <p style="font-size:13px;color:#78350f;margin:0;">
      <strong>This log is append-only.</strong> Records cannot be edited or deleted.
      This trail is admissible evidence and must be preserved.
    </p>
  </div>

  <?= render_flash() ?>

  <!-- ── FILTER BAR ─────────────────────────────────────────── -->
  <div id="al-filter-bar" class="no-print">
    <form method="get" action="">
      <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">

        <div class="form-group" style="margin:0;min-width:140px;">
          <label style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">From</label>
          <input type="date" name="date_from" class="form-control form-control-sm"
                 value="<?= e($date_from) ?>" style="font-size:13px;padding:7px 10px;">
        </div>

        <div class="form-group" style="margin:0;min-width:140px;">
          <label style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">To</label>
          <input type="date" name="date_to" class="form-control form-control-sm"
                 value="<?= e($date_to) ?>" style="font-size:13px;padding:7px 10px;">
        </div>

        <div class="form-group" style="margin:0;min-width:160px;">
          <label style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">Admin</label>
          <select name="admin_id" class="form-control form-control-sm" style="font-size:13px;padding:7px 10px;">
            <option value="">All Admins</option>
            <?php foreach ($admins_list as $al): ?>
            <option value="<?= (int)$al['id'] ?>" <?= $admin_f === (int)$al['id'] ? 'selected' : '' ?>>
              <?= e($al['full_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="margin:0;min-width:180px;">
          <label style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">Action Type</label>
          <select name="action_type" class="form-control form-control-sm" style="font-size:13px;padding:7px 10px;">
            <option value="">All Actions</option>
            <?php foreach ($action_types as $at): ?>
            <option value="<?= e($at) ?>" <?= $action_f === $at ? 'selected' : '' ?>><?= e($at) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="margin:0;min-width:100px;">
          <label style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">Entity ID</label>
          <input type="number" name="entity_id" class="form-control form-control-sm"
                 value="<?= e($entity_f) ?>" placeholder="e.g. 42" min="1"
                 style="font-size:13px;padding:7px 10px;">
        </div>

        <div class="form-group" style="margin:0;flex:1;min-width:160px;">
          <label style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">Search</label>
          <div style="position:relative;">
            <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;font-size:12px;"></i>
            <input type="search" name="q" value="<?= e($search_f) ?>"
                   placeholder="Action, admin, details…"
                   class="form-control form-control-sm"
                   style="padding-left:30px;font-size:13px;padding-top:7px;padding-bottom:7px;">
          </div>
        </div>

        <input type="hidden" name="dir"      value="<?= e($sort_dir) ?>">
        <input type="hidden" name="per_page" value="<?= $page_size ?>">

        <div style="display:flex;gap:8px;margin-top:auto;padding-bottom:0;">
          <button type="submit" class="btn btn-primary btn-sm" style="padding:7px 16px;">
            <i class="bi bi-funnel-fill"></i> Apply
          </button>
          <a href="?" class="btn btn-secondary btn-sm" style="padding:7px 16px;">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
          </a>
        </div>
      </div>
    </form>
  </div>

  <!-- Results count -->
  <div style="font-size:13px;color:var(--text-muted);margin:12px 0 16px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;" class="no-print">
    <span>Showing
      <strong style="color:var(--text);"><?= number_format(count($rows)) ?></strong>
      of
      <strong style="color:var(--text);"><?= number_format($total_count) ?></strong>
      entr<?= $total_count !== 1 ? 'ies' : 'y' ?>
    </span>
    <span style="margin-left:auto;display:flex;align-items:center;gap:6px;">
      <label style="font-size:12px;">Rows:</label>
      <select onchange="window.location=this.value"
              style="font-size:12px;padding:3px 6px;border:1px solid var(--border);border-radius:5px;background:var(--bg);color:var(--text);cursor:pointer;">
        <?php foreach ([25, 50, 100] as $ps): ?>
        <option value="<?= e(al_url(['per_page' => $ps, 'page' => 1])) ?>"
                <?= $ps === $page_size ? 'selected' : '' ?>><?= $ps ?> / page</option>
        <?php endforeach; ?>
      </select>
      <a href="<?= e(al_url(['dir' => $sort_dir === 'DESC' ? 'ASC' : 'DESC', 'page' => 1])) ?>"
         class="btn btn-secondary btn-sm" style="padding:4px 10px;font-size:12px;">
        <i class="bi bi-sort-<?= $sort_dir === 'DESC' ? 'down' : 'up' ?>"></i>
        <?= $sort_dir === 'DESC' ? 'Newest first' : 'Oldest first' ?>
      </a>
    </span>
  </div>

  <!-- ── TABLE ─────────────────────────────────────────────── -->
  <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);">
    <?php if (empty($rows)): ?>
    <div style="text-align:center;padding:72px 24px;">
      <div style="font-size:64px;opacity:.15;margin-bottom:12px;">🔍</div>
      <p style="font-size:16px;font-weight:700;color:var(--text);margin:0 0 6px;">No log entries match</p>
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 20px;">Widen the date range or clear filters.</p>
      <a href="?" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Reset filters</a>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="al-table" aria-label="Audit log">
        <thead>
          <tr>
            <th style="width:140px;">Timestamp</th>
            <th>Admin</th>
            <th>Action</th>
            <th style="width:80px;">Entity</th>
            <th style="width:120px;">IP Address</th>
            <th>User Agent</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
          <tr>
            <td style="color:var(--text-muted);white-space:nowrap;font-size:11px;">
              <?= format_datetime($row['created_at'], 'M d, Y') ?><br>
              <span style="font-size:10px;"><?= format_datetime($row['created_at'], 'g:i:s A') ?></span>
            </td>
            <td>
              <div style="font-weight:600;font-size:12px;color:var(--text);"><?= e($row['admin_name']) ?></div>
              <div style="font-size:10px;color:var(--text-muted);"><?= e($row['role_label']) ?></div>
            </td>
            <td>
              <span class="al-action-tag" style="<?= al_action_style($row['action']) ?>">
                <?= e($row['action']) ?>
              </span>
            </td>
            <td style="text-align:center;">
              <?= al_entity_link($row['action'], (int)$row['target_id']) ?>
            </td>
            <td style="font-family:monospace;font-size:11px;color:var(--text-muted);">
              <?= e($row['ip_address']) ?>
            </td>
            <td>
              <span style="font-size:11px;color:var(--text-muted);display:block;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?= e($row['user_agent']) ?>">
                <?= e(substr($row['user_agent'], 0, 80)) ?>
              </span>
              <?php if ($row['details'] && $row['details'] !== 'null'): ?>
              <details style="margin-top:4px;">
                <summary style="font-size:10px;cursor:pointer;color:var(--secondary);list-style:none;user-select:none;">
                  <i class="bi bi-code"></i> Details
                </summary>
                <pre style="margin:4px 0 0;font-size:10px;color:var(--text-muted);background:var(--bg);padding:6px 8px;border-radius:4px;overflow-x:auto;white-space:pre-wrap;word-break:break-all;max-width:320px;"><?= e(json_encode(json_decode($row['details']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
              </details>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-top:1px solid var(--border);flex-wrap:wrap;gap:12px;" class="no-print">
      <div class="pager">
        <?php if ($page_num > 1): ?>
        <a href="<?= e(al_url(['page' => $page_num - 1])) ?>" aria-label="Previous">&laquo;</a>
        <?php else: ?><span class="disabled">&laquo;</span><?php endif; ?>
        <?php
        $pp = array_unique(array_filter([1,$page_num-2,$page_num-1,$page_num,$page_num+1,$page_num+2,$total_pages], fn($p)=>$p>=1&&$p<=$total_pages));
        sort($pp); $prev=0;
        foreach ($pp as $p):
            if ($prev && $p-$prev>1) echo '<span class="disabled" style="border:none;min-width:auto;">…</span>';
            $prev=$p;
        ?>
        <?php if ($p===$page_num): ?><span class="current"><?= $p ?></span>
        <?php else: ?><a href="<?= e(al_url(['page'=>$p])) ?>"><?= $p ?></a><?php endif; ?>
        <?php endforeach; ?>
        <?php if ($page_num<$total_pages): ?>
        <a href="<?= e(al_url(['page'=>$page_num+1])) ?>" aria-label="Next">&raquo;</a>
        <?php else: ?><span class="disabled">&raquo;</span><?php endif; ?>
      </div>
      <form method="get" action="" style="display:flex;align-items:center;gap:6px;">
        <?php foreach ($_GET as $k => $v): if ($k !== 'page'): ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e(is_array($v) ? implode(',', $v) : $v) ?>">
        <?php endif; endforeach; ?>
        <label style="font-size:12px;color:var(--text-muted);">Go to</label>
        <input type="number" name="page" min="1" max="<?= $total_pages ?>" value="<?= $page_num ?>"
               style="width:60px;padding:4px 8px;font-size:12px;border:1px solid var(--border);border-radius:5px;background:var(--bg);color:var(--text);">
        <button type="submit" class="btn btn-secondary btn-sm" style="padding:4px 10px;font-size:12px;">Go</button>
      </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<script>
(function () {
  var bar = document.getElementById('al-filter-bar');
  if (!bar) return;
  var s = document.createElement('div'); s.style.height='1px';
  bar.parentNode.insertBefore(s, bar);
  new IntersectionObserver(function(e){ bar.classList.toggle('is-stuck',!e[0].isIntersecting);},{rootMargin:'-1px 0px 0px 0px',threshold:[1]}).observe(s);
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
