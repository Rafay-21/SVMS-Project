<?php
/**
 * pages/visitor_history.php
 * Searchable, filterable, sortable, exportable visit history.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('view_history');

/* ══════════════════════════════════════════════════════════════
   1. FILTER PARAMS (from GET / URL query string)
   ══════════════════════════════════════════════════════════════ */
$date_from  = trim($_GET['date_from']  ?? date('Y-m-d', strtotime('-30 days')));
$date_to    = trim($_GET['date_to']    ?? date('Y-m-d'));
$status_f   = trim($_GET['status']     ?? '');
$dept_f     = (int)($_GET['dept']      ?? 0);
$vip_f      = isset($_GET['vip']) ? 1 : 0;
$search_f   = trim($_GET['q']          ?? '');
$sort_col   = trim($_GET['sort']       ?? 'check_in_time');
$sort_dir   = strtoupper(trim($_GET['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$_ps_raw    = (int)($_GET['per_page'] ?? 25);
$page_size  = in_array($_ps_raw, [25, 50, 100]) ? $_ps_raw : 25;
$page_num   = max(1, (int)($_GET['page'] ?? 1));

// Whitelist sortable columns
$allowed_sorts = [
    'check_in_time'  => 'vl.check_in_time',
    'check_out_time' => 'vl.check_out_time',
    'full_name'      => 'v.full_name',
    'dept_name'      => 'd.name',
    'status'         => 'vl.status',
];
$sort_expr = $allowed_sorts[$sort_col] ?? 'vl.check_in_time';

/* ══════════════════════════════════════════════════════════════
   2. BUILD WHERE CLAUSE
   ══════════════════════════════════════════════════════════════ */
$where   = [];
$types   = '';
$params  = [];

$where[] = 'DATE(vl.check_in_time) >= ?';
$types  .= 's';
$params[] = $date_from;

$where[] = 'DATE(vl.check_in_time) <= ?';
$types  .= 's';
$params[] = $date_to;

if ($status_f === 'checked_in')   { $where[] = "vl.status = 'checked_in'";  }
if ($status_f === 'checked_out')  { $where[] = "vl.status = 'checked_out'"; }
if ($status_f === 'no_show')      { $where[] = "vl.status = 'no_show'";     }
if ($status_f === 'auto_checkout'){ $where[] = "vl.status = 'auto_checkout'"; }

if ($dept_f) {
    $where[] = 'vl.department_id = ?';
    $types  .= 'i';
    $params[] = $dept_f;
}

if ($vip_f) { $where[] = 'v.vip = 1'; }

if ($search_f !== '') {
    $where[] = '(v.full_name LIKE ? OR v.phone LIKE ? OR v.cnic LIKE ? OR vl.badge_number LIKE ?)';
    $like     = '%' . $search_f . '%';
    $types   .= 'ssss';
    $params   = array_merge($params, [$like, $like, $like, $like]);
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ══════════════════════════════════════════════════════════════
   3. BASE SQL
   ══════════════════════════════════════════════════════════════ */
$base_sql = "
    SELECT
        vl.id              AS visit_log_id,
        vl.visitor_id,
        vl.badge_number,
        vl.check_in_time,
        vl.check_out_time,
        vl.status,
        vl.department_id,
        vl.person_to_meet,
        vl.purpose,
        vl.vehicle_number,
        vl.visitor_type,
        vl.registered_by,
        v.full_name,
        v.phone,
        v.cnic,
        v.photo_path,
        v.vip,
        COALESCE(d.name,'—')      AS dept_name,
        COALESCE(a.full_name,'—') AS registered_by_name,
        TIMESTAMPDIFF(MINUTE, vl.check_in_time,
            COALESCE(vl.check_out_time, NOW())) AS duration_min
    FROM visit_log vl
    JOIN visitors v         ON v.id  = vl.visitor_id
    LEFT JOIN departments d ON d.id  = vl.department_id
    LEFT JOIN admins a      ON a.id  = vl.registered_by
    $where_sql
";

/* ══════════════════════════════════════════════════════════════
   4. CSV EXPORT
   ══════════════════════════════════════════════════════════════ */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_limit = 50000;
    $export_sql   = $base_sql . " ORDER BY $sort_expr $sort_dir LIMIT " . ($export_limit + 1);
    $export_rows  = query_all($export_sql, $types, $params);

    $capped = count($export_rows) > $export_limit;
    if ($capped) array_pop($export_rows);

    log_action('export_csv', count($export_rows), json_encode(['filters' => array_intersect_key($_GET, array_flip(['date_from','date_to','status','dept','vip','q']))]));

    $filename = 'svms_visits_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');

    $fh = fopen('php://output', 'w');
    fputs($fh, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

    fputcsv($fh, ['Visit ID','Visitor Name','Phone','CNIC','Department','Person Met',
                  'Purpose','Vehicle','Check-In','Check-Out','Duration (minutes)',
                  'Status','Registered By','VIP']);
    foreach ($export_rows as $r) {
        fputcsv($fh, [
            $r['visit_log_id'],
            $r['full_name']          ?? '',
            $r['phone']              ?? '',
            $r['cnic']               ?? '',
            $r['dept_name']          ?? '',
            $r['person_to_meet']     ?? '',
            $r['purpose']            ?? '',
            $r['vehicle_number']     ?? '',
            $r['check_in_time']      ?? '',
            $r['check_out_time']     ?? '',
            $r['duration_min']       ?? '',
            $r['status']             ?? '',
            $r['registered_by_name'] ?? '',
            $r['vip'] ? 'Yes' : 'No',
        ]);
    }
    fclose($fh);

    if ($capped) {
        flash('warning', 'Export capped at ' . number_format($export_limit) . ' rows. Narrow your filters to export all records.');
    }
    exit;
}

/* ══════════════════════════════════════════════════════════════
   5. COUNT + PAGINATE
   ══════════════════════════════════════════════════════════════ */
$count_sql   = "SELECT COUNT(*) AS total
                FROM visit_log vl
                JOIN visitors v         ON v.id = vl.visitor_id
                LEFT JOIN departments d ON d.id = vl.department_id
                $where_sql";
$count_row   = query_one($count_sql, $types, $params);
$total_count = (int)($count_row['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_count / $page_size));
$page_num    = min($page_num, $total_pages);
$offset      = ($page_num - 1) * $page_size;

$rows = query_all(
    $base_sql . " ORDER BY $sort_expr $sort_dir LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$page_size, $offset])
);

/* ══════════════════════════════════════════════════════════════
   6. SIDEBAR DATA + URL HELPERS
   ══════════════════════════════════════════════════════════════ */
$departments = query_all("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name");

function vh_url(array $override = []): string {
    $base = [
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to']   ?? '',
        'status'    => $_GET['status']    ?? '',
        'dept'      => $_GET['dept']      ?? '',
        'vip'       => $_GET['vip']       ?? '',
        'q'         => $_GET['q']         ?? '',
        'sort'      => $_GET['sort']      ?? '',
        'dir'       => $_GET['dir']       ?? '',
        'per_page'  => $_GET['per_page']  ?? '',
        'page'      => $_GET['page']      ?? '',
    ];
    $merged = array_merge($base, $override);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null && $v !== false);
    return '?' . http_build_query($merged);
}

function vh_sort_url(string $col): string {
    global $sort_col, $sort_dir;
    $dir = ($sort_col === $col && $sort_dir === 'DESC') ? 'ASC' : 'DESC';
    return vh_url(['sort' => $col, 'dir' => $dir, 'page' => 1]);
}

/* ══════════════════════════════════════════════════════════════
   7. RENDER
   ══════════════════════════════════════════════════════════════ */
$page_title = 'Visit History';
include __DIR__ . '/../includes/header.php';
?>

<style>
#filter-bar {
  position: sticky; top: 0; z-index: 50;
  background: var(--bg); padding: 16px 0 12px; transition: box-shadow .2s;
}
#filter-bar.is-stuck { box-shadow: 0 2px 8px rgba(0,0,0,.06); }

.vh-table { width:100%; border-collapse:collapse; font-size:13px; }
.vh-table thead th {
  background:var(--bg); color:var(--text-muted); font-weight:600;
  font-size:11px; text-transform:uppercase; letter-spacing:.05em;
  padding:10px 14px; border-bottom:2px solid var(--border);
  white-space:nowrap; position:sticky; top:0; z-index:10;
}
.vh-table tbody tr {
  border-bottom:1px solid var(--border); cursor:pointer;
  transition:background .1s,transform .1s;
}
.vh-table tbody tr:hover { background:var(--bg); transform:scale(1.001); }
.vh-table tbody td { padding:10px 14px; vertical-align:middle; }

.sort-link {
  display:inline-flex; align-items:center; gap:4px;
  color:inherit; text-decoration:none; font-weight:600;
  font-size:11px; text-transform:uppercase; letter-spacing:.05em;
}
.sort-icon { font-size:11px; color:var(--border); transition:color .15s; }
.sort-active .sort-icon { color:var(--primary); }

.badge-in     { background:#dbeafe;color:#1e40af;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap; }
.badge-out    { background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap; }
.badge-noshow { background:#f1f5f9;color:#475569;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap; }
.badge-auto-out { background:#f3f4f6;color:#374151;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap;border:1px solid #d1d5db;cursor:help; }

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

.visitor-photo {
  width:40px; height:40px; border-radius:50%; object-fit:cover; flex-shrink:0;
  background:linear-gradient(135deg,var(--secondary),var(--accent));
  display:inline-flex; align-items:center; justify-content:center;
  color:#fff; font-size:14px; font-weight:700; overflow:hidden;
}

@media print {
  #filter-bar, .pager, .vh-actions, #page-header-actions { display:none !important; }
  .vh-table { font-size:10px; }
  .vh-table thead th { position:static; }
}
</style>

<div class="container" style="padding-bottom:48px;">

  <!-- Page header -->
  <div id="page-header-actions" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <div>
      <h1 style="font-size:1.4rem;font-weight:700;margin:0 0 4px;color:var(--text);">
        <i class="bi bi-clock-history" style="color:var(--secondary);margin-right:8px;"></i>Visit History
      </h1>
      <p style="font-size:13px;color:var(--text-muted);margin:0;">Browse, filter, and export every visit on record.</p>
    </div>
    <a href="<?= BASE_URL ?>pages/register_visitor.php" class="btn btn-primary btn-sm">
      <i class="bi bi-person-plus-fill"></i> Register Visitor
    </a>
    <button id="vh-pdf-btn" class="btn btn-secondary btn-sm"
            onclick="document.getElementById('vh-pdf-modal-bd').style.display='flex'"
            style="display:inline-flex;align-items:center;gap:5px;">
      <i class="bi bi-file-earmark-pdf"></i> Export PDF
    </button>
  </div>

  <?= render_flash() ?>

  <!-- ── FILTER BAR ──────────────────────────────────────────── -->
  <div id="filter-bar">
    <form method="get" action="" id="filter-form">
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
        <div class="form-group" style="margin:0;min-width:130px;">
          <label style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">Status</label>
          <select name="status" class="form-control form-control-sm" style="font-size:13px;padding:7px 10px;">
            <option value="">All</option>
            <option value="checked_in"  <?= $status_f === 'checked_in'  ? 'selected' : '' ?>>Active</option>
            <option value="checked_out" <?= $status_f === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
            <option value="auto_checkout" <?= $status_f === 'auto_checkout' ? 'selected' : '' ?>>Auto Checkout</option>
            <option value="no_show"     <?= $status_f === 'no_show'     ? 'selected' : '' ?>>No Show</option>
          </select>
        </div>
        <div class="form-group" style="margin:0;min-width:150px;">
          <label style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">Department</label>
          <select name="dept" class="form-control form-control-sm" style="font-size:13px;padding:7px 10px;">
            <option value="">All Depts</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= $dept_f === (int)$d['id'] ? 'selected' : '' ?>>
              <?= e($d['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:180px;">
          <label style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:4px;">Search</label>
          <div style="position:relative;">
            <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;font-size:12px;"></i>
            <input type="search" name="q" value="<?= e($search_f) ?>"
                   placeholder="Name, phone, CNIC, badge…"
                   class="form-control form-control-sm"
                   style="padding-left:30px;font-size:13px;padding-top:7px;padding-bottom:7px;">
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;padding-bottom:4px;">
          <input type="checkbox" name="vip" id="filter-vip" value="1" <?= $vip_f ? 'checked' : '' ?>
                 style="width:15px;height:15px;cursor:pointer;">
          <label for="filter-vip" style="font-size:13px;font-weight:600;cursor:pointer;margin:0;">VIP only</label>
        </div>
        <input type="hidden" name="sort"     value="<?= e($sort_col) ?>">
        <input type="hidden" name="dir"      value="<?= e($sort_dir) ?>">
        <input type="hidden" name="per_page" value="<?= $page_size ?>">
        <div style="display:flex;gap:8px;padding-bottom:0;margin-top:auto;">
          <button type="submit" class="btn btn-primary btn-sm" style="padding:7px 16px;">
            <i class="bi bi-funnel-fill"></i> Apply
          </button>
          <a href="?" class="btn btn-secondary btn-sm" style="padding:7px 16px;">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
          </a>
        </div>
        <div style="margin-left:auto;">
          <a href="<?= e(vh_url(['export' => 'csv', 'page' => ''])) ?>"
             class="btn btn-secondary btn-sm" style="padding:7px 16px;">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
          </a>
        </div>
      </div>
    </form>
  </div>

  <!-- Results count -->
  <div style="font-size:13px;color:var(--text-muted);margin:12px 0 16px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
    <span>Showing
      <strong style="color:var(--text);"><?= number_format(count($rows)) ?></strong>
      of
      <strong style="color:var(--text);"><?= number_format($total_count) ?></strong>
      visit<?= $total_count !== 1 ? 's' : '' ?> matching your filters
    </span>
    <?php if ($total_pages > 1): ?>
    <span style="font-size:11px;">(page <?= $page_num ?> of <?= $total_pages ?>)</span>
    <?php endif; ?>
    <div style="margin-left:auto;display:flex;align-items:center;gap:6px;">
      <label style="font-size:12px;color:var(--text-muted);">Rows:</label>
      <select onchange="window.location=this.value"
              style="font-size:12px;padding:3px 6px;border:1px solid var(--border);border-radius:5px;background:var(--bg);color:var(--text);cursor:pointer;">
        <?php foreach ([25, 50, 100] as $ps): ?>
        <option value="<?= e(vh_url(['per_page' => $ps, 'page' => 1])) ?>"
                <?= $ps === $page_size ? 'selected' : '' ?>><?= $ps ?> / page</option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- ── TABLE ───────────────────────────────────────────────── -->
  <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);">
    <?php if (empty($rows)): ?>
    <div style="text-align:center;padding:72px 24px;">
      <div style="font-size:72px;opacity:.15;margin-bottom:12px;line-height:1;">📋</div>
      <p style="font-size:16px;font-weight:700;color:var(--text);margin:0 0 6px;">No visits match these filters</p>
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 20px;">Try widening your date range or clearing the search box.</p>
      <a href="?" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Reset filters</a>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="vh-table" aria-label="Visit history">
        <thead>
          <tr>
            <th style="width:52px;"></th>
            <th><a href="<?= e(vh_sort_url('full_name')) ?>" class="sort-link <?= $sort_col==='full_name'?'sort-active':'' ?>">Visitor <i class="bi sort-icon <?= $sort_col==='full_name'?($sort_dir==='ASC'?'bi-chevron-up':'bi-chevron-down'):'bi-chevron-expand' ?>"></i></a></th>
            <th><a href="<?= e(vh_sort_url('dept_name')) ?>" class="sort-link <?= $sort_col==='dept_name'?'sort-active':'' ?>">Department <i class="bi sort-icon <?= $sort_col==='dept_name'?($sort_dir==='ASC'?'bi-chevron-up':'bi-chevron-down'):'bi-chevron-expand' ?>"></i></a></th>
            <th>Person Met</th>
            <th><a href="<?= e(vh_sort_url('check_in_time')) ?>" class="sort-link <?= $sort_col==='check_in_time'?'sort-active':'' ?>">Check-In <i class="bi sort-icon <?= $sort_col==='check_in_time'?($sort_dir==='ASC'?'bi-chevron-up':'bi-chevron-down'):'bi-chevron-expand' ?>"></i></a></th>
            <th><a href="<?= e(vh_sort_url('check_out_time')) ?>" class="sort-link <?= $sort_col==='check_out_time'?'sort-active':'' ?>">Duration <i class="bi sort-icon <?= $sort_col==='check_out_time'?($sort_dir==='ASC'?'bi-chevron-up':'bi-chevron-down'):'bi-chevron-expand' ?>"></i></a></th>
            <th><a href="<?= e(vh_sort_url('status')) ?>" class="sort-link <?= $sort_col==='status'?'sort-active':'' ?>">Status <i class="bi sort-icon <?= $sort_col==='status'?($sort_dir==='ASC'?'bi-chevron-up':'bi-chevron-down'):'bi-chevron-expand' ?>"></i></a></th>
            <th class="vh-actions"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row):
            $initials = strtoupper(substr($row['full_name'], 0, 1));
            $parts    = explode(' ', $row['full_name']);
            if (isset($parts[1])) $initials .= strtoupper(substr($parts[1], 0, 1));
            $dur_min = $row['duration_min'];
            $dur_str = '—';
            if ($dur_min !== null) {
                $h = (int)floor((int)$dur_min / 60);
                $m = (int)$dur_min % 60;
                $dur_str = $h > 0 ? "{$h}h {$m}m" : "{$m}m";
            }
            $detail_url = BASE_URL . 'pages/visitor_detail.php?id=' . (int)$row['visit_log_id'];
          ?>
          <tr onclick="window.location='<?= $detail_url ?>'" title="View visit details">
            <td>
              <?php if ($row['photo_path']): ?>
              <img src="<?= BASE_URL ?>assets/uploads/<?= e($row['photo_path']) ?>"
                   class="visitor-photo" alt="<?= e($row['full_name']) ?>">
              <?php else: ?>
              <span class="visitor-photo"><?= e($initials) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:600;color:var(--text);line-height:1.3;">
                <?= e($row['full_name']) ?>
                <?php if ($row['vip']): ?>
                <span style="font-size:10px;background:#fef3c7;color:#92400e;padding:1px 5px;border-radius:10px;margin-left:4px;">⭐ VIP</span>
                <?php endif; ?>
              </div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= e($row['phone']) ?></div>
            </td>
            <td style="color:var(--text-muted);"><?= e($row['dept_name']) ?></td>
            <td><?= e($row['person_to_meet']) ?></td>
            <td style="color:var(--text-muted);white-space:nowrap;"><?= format_datetime($row['check_in_time'], 'M d, Y g:i A') ?></td>
            <td style="white-space:nowrap;">
              <?php if ($row['status'] === 'checked_in'): ?>
              <span style="color:var(--secondary);font-weight:600;font-size:12px;">
                <i class="bi bi-stopwatch"></i> <?= time_elapsed($row['check_in_time']) ?> (live)
              </span>
              <?php else: ?>
              <span style="color:var(--text-muted);"><?= $dur_str ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($row['status'] === 'checked_in'): ?>
              <span class="badge-in"><i class="bi bi-door-open" style="margin-right:3px;"></i>Active</span>
              <?php elseif ($row['status'] === 'checked_out'): ?>
              <span class="badge-out"><i class="bi bi-door-closed" style="margin-right:3px;"></i>Out</span>
              <?php elseif ($row['status'] === 'auto_checkout'): ?>
              <span class="badge-auto-out" title="Automatically checked out after <?= MAX_VISIT_HOURS ?> hours"><i class="bi bi-clock-history" style="margin-right:3px;"></i>Auto Out</span>
              <?php else: ?>
              <span class="badge-noshow">No Show</span>
              <?php endif; ?>
            </td>
            <td class="vh-actions" onclick="event.stopPropagation();">
              <a href="<?= $detail_url ?>" class="btn btn-secondary btn-sm"
                 title="View full details" style="padding:5px 10px;">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-top:1px solid var(--border);flex-wrap:wrap;gap:12px;">
      <div class="pager">
        <?php if ($page_num > 1): ?>
        <a href="<?= e(vh_url(['page' => $page_num - 1])) ?>" aria-label="Previous">&laquo;</a>
        <?php else: ?><span class="disabled">&laquo;</span><?php endif; ?>
        <?php
        $pager_pages = array_unique(array_filter([
            1, $page_num-2, $page_num-1, $page_num, $page_num+1, $page_num+2, $total_pages
        ], fn($p) => $p >= 1 && $p <= $total_pages));
        sort($pager_pages);
        $prev_p = 0;
        foreach ($pager_pages as $p):
            if ($prev_p && $p - $prev_p > 1) echo '<span class="disabled" style="border:none;min-width:auto;">…</span>';
            $prev_p = $p;
        ?>
        <?php if ($p === $page_num): ?>
        <span class="current"><?= $p ?></span>
        <?php else: ?>
        <a href="<?= e(vh_url(['page' => $p])) ?>"><?= $p ?></a>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($page_num < $total_pages): ?>
        <a href="<?= e(vh_url(['page' => $page_num + 1])) ?>" aria-label="Next">&raquo;</a>
        <?php else: ?><span class="disabled">&raquo;</span><?php endif; ?>
      </div>
      <form method="get" action="" style="display:flex;align-items:center;gap:6px;">
        <?php foreach ($_GET as $k => $v): if ($k !== 'page'): ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e(is_array($v) ? implode(',', $v) : $v) ?>">
        <?php endif; endforeach; ?>
        <label style="font-size:12px;color:var(--text-muted);">Go to page</label>
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
  var bar = document.getElementById('filter-bar');
  if (!bar) return;
  var sentinel = document.createElement('div');
  sentinel.style.height = '1px';
  bar.parentNode.insertBefore(sentinel, bar);
  new IntersectionObserver(function (entries) {
    bar.classList.toggle('is-stuck', !entries[0].isIntersecting);
  }, { rootMargin: '-1px 0px 0px 0px', threshold: [1] }).observe(sentinel);
})();
</script>

<?php
// Build filters summary for PDF
$_vh_filters = [];
if ($date_from) $_vh_filters[] = 'From: ' . $date_from;
if ($date_to)   $_vh_filters[] = 'To: '   . $date_to;
if ($status_f)  $_vh_filters[] = 'Status: ' . ucfirst(str_replace('_', ' ', $status_f));
if ($dept_f) {
    $df = query_one("SELECT name FROM departments WHERE id=?", 'i', [$dept_f]);
    if ($df) $_vh_filters[] = 'Department: ' . $df['name'];
}
if ($vip_f)     $_vh_filters[] = 'VIP only';
if ($search_f)  $_vh_filters[] = 'Search: "' . $search_f . '"';
$_vh_filters_label = $_vh_filters ? implode(' | ', $_vh_filters) : 'All records';
?>

<!-- ══════════════════════════════════════════════════════════
     PDF EXPORT MODAL — visitor_history.php
     ══════════════════════════════════════════════════════════ -->
<div id="vh-pdf-modal-bd" role="dialog" aria-modal="true" aria-labelledby="vh-pdf-modal-title"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1300;
            align-items:center;justify-content:center;padding:16px;">

  <div style="background:var(--card);border-radius:var(--radius-md);width:100%;max-width:420px;
              box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">

    <div style="background:var(--primary);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
      <h3 id="vh-pdf-modal-title" style="color:#fff;font-size:15px;font-weight:700;margin:0;">
        <i class="bi bi-file-earmark-pdf" style="margin-right:8px;"></i>Export Visit List PDF
      </h3>
      <button onclick="document.getElementById('vh-pdf-modal-bd').style.display='none'"
              style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1;"
              aria-label="Close">&times;</button>
    </div>

    <div style="padding:20px 24px;">
      <div style="background:var(--bg-alt);border-radius:var(--radius-sm);padding:10px 12px;
                  font-size:12px;color:var(--text-muted);margin-bottom:16px;">
        <strong style="color:var(--text);">Active filters:</strong>
        <?= e($_vh_filters_label) ?>
        <br><span style="font-size:11px;">(Up to 200 rows will be exported)</span>
      </div>

      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px;">Report Title</label>
        <input type="text" id="vh-pdf-title" value="Visitor Activity Report" maxlength="120"
               style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);
                      background:var(--card);color:var(--text);font-size:13px;box-sizing:border-box;">
      </div>

      <div id="vh-pdf-progress" style="display:none;background:var(--bg-alt);border-radius:var(--radius-sm);
                                        padding:12px 14px;margin-bottom:14px;">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;" id="vh-pdf-prog-lbl">Generating…</div>
        <div style="height:4px;background:var(--border);border-radius:2px;overflow:hidden;">
          <div id="vh-pdf-prog-bar" style="height:100%;background:var(--secondary);width:0%;transition:width .3s;border-radius:2px;"></div>
        </div>
      </div>

      <div id="vh-pdf-error" style="display:none;background:#fef2f2;border:1px solid #fecaca;
                                     border-radius:var(--radius-sm);padding:10px 12px;margin-bottom:14px;
                                     font-size:12px;color:#dc2626;"></div>

      <div style="display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" onclick="document.getElementById('vh-pdf-modal-bd').style.display='none'"
                class="btn btn-secondary" style="font-size:13px;">Cancel</button>
        <button type="button" id="vh-pdf-generate-btn" class="btn btn-primary" style="font-size:13px;">
          <i class="bi bi-download"></i> Generate &amp; Download
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var btn  = document.getElementById('vh-pdf-generate-btn');
  var bd   = document.getElementById('vh-pdf-modal-bd');
  var err  = document.getElementById('vh-pdf-error');
  var prog = document.getElementById('vh-pdf-progress');
  var pBar = document.getElementById('vh-pdf-prog-bar');
  var pLbl = document.getElementById('vh-pdf-prog-lbl');

  function setProgress(pct, label) {
    prog.style.display = 'block';
    pBar.style.width   = pct + '%';
    pLbl.textContent   = label;
  }

  function showError(msg) {
    err.textContent    = msg;
    err.style.display  = 'block';
    btn.disabled       = false;
    btn.innerHTML      = '<i class="bi bi-download"></i> Generate &amp; Download';
    prog.style.display = 'none';
  }

  btn && btn.addEventListener('click', function () {
    err.style.display = 'none';
    btn.disabled      = true;
    btn.innerHTML     = '<i class="bi bi-hourglass-split"></i> Generating…';
    setProgress(30, 'Sending request…');

    var payload = {
      csrf_token    : (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
      from          : <?= json_encode($date_from) ?>,
      to            : <?= json_encode($date_to) ?>,
      title         : document.getElementById('vh-pdf-title').value.trim() || 'Visitor Activity Report',
      include_charts: '0',
      include_raw   : '1',
      report_type   : 'visit_list',
      filters_label : <?= json_encode($_vh_filters_label) ?>,
      filters_dept  : <?= json_encode((string)$dept_f) ?>,
      filters_status: <?= json_encode($status_f) ?>,
    };

    fetch((window.BASE_URL || '') + 'api/generate_report.php', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body   : JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!d.ok) { showError(d.error || 'Server error generating PDF.'); return; }
      setProgress(100, 'Done! Starting download…');
      var a = document.createElement('a');
      a.href     = d.url;
      a.download = d.filename || 'report.pdf';
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
      setTimeout(function () {
        bd.style.display   = 'none';
        prog.style.display = 'none';
        btn.disabled       = false;
        btn.innerHTML      = '<i class="bi bi-download"></i> Generate &amp; Download';
        if (window.SVMS) SVMS.toast('Visit list PDF downloaded!', 'success');
      }, 700);
    })
    .catch(function(e) { showError('Network error: ' + e.message); });
  });

  bd && bd.addEventListener('click', function(e) {
    if (e.target === bd) bd.style.display = 'none';
  });
}());
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
