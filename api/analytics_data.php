<?php
/**
 * api/analytics_data.php — Analytics Data API (Phase 5.1)
 *
 * GET ?from=YYYY-MM-DD&to=YYYY-MM-DD&granularity=day|week|month
 *
 * Returns one JSON payload with:
 *   kpi        — { total_visits, unique_visitors, avg_duration_min, peak_hour,
 *                  delta_total, delta_unique, delta_duration, delta_peak_hour }
 *   heatmap    — 7×24 matrix [dow][hour] = count  (dow: 1=Sun…7=Sat MySQL)
 *   departments— [{ id, name, colour, visits }]  top 10
 *   purposes   — [{ label, count }]  top 8 clustered
 *   trend      — [{ date, visits }]
 *   top_visitors — [{ visitor_id, full_name, visits, last_visit, total_min }]
 *   drilldown  — when ?drill_dept=ID  →  { dept_id, visits: [{...}] }
 *   drilldown_purpose — when ?drill_purpose=label  →  { label, visits:[{...}] }
 *
 * Results are cached for 60 s in logs/analytics_cache/{hash}.json
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// ── Date / param validation ───────────────────────────────────────────────────
$today    = date('Y-m-d');
$defaults = [
    'from'        => date('Y-m-d', strtotime('-29 days')),
    'to'          => $today,
    'granularity' => 'day',
];
$from_raw = $_GET['from'] ?? $defaults['from'];
$to_raw   = $_GET['to']   ?? $defaults['to'];
$gran     = $_GET['granularity'] ?? $defaults['granularity'];

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_raw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_raw)) {
    $from_raw = $defaults['from'];
    $to_raw   = $defaults['to'];
}
$from = $from_raw;
$to   = $to_raw;
if ($from > $to) [$from, $to] = [$to, $from];

// Max 1-year span
$span_days = (int)round((strtotime($to) - strtotime($from)) / 86400);
if ($span_days > 366) {
    $from = date('Y-m-d', strtotime($to) - 365 * 86400);
}

if (!in_array($gran, ['day','week','month'])) $gran = 'day';

// Drilldown sub-queries (bypass cache for drilldowns)
$drill_dept    = (int)($_GET['drill_dept']    ?? 0);
$drill_purpose = trim($_GET['drill_purpose']  ?? '');

// ── Cache ──────────────────────────────────────────────────────────────────────
$cache_dir = LOG_DIR . '/analytics_cache';
if (!is_dir($cache_dir)) mkdir($cache_dir, 0750, true);

$cache_key  = md5($from . '|' . $to . '|' . $gran . '|' . $drill_dept . '|' . $drill_purpose);
$cache_file = $cache_dir . '/' . $cache_key . '.json';
$cache_ttl  = 60; // seconds

if (!isset($_GET['refresh']) && file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
    readfile($cache_file);
    exit;
}

// ── Previous-period window for deltas ─────────────────────────────────────────
$span      = max(1, $span_days + 1);  // inclusive
$prev_from = date('Y-m-d', strtotime($from) - $span * 86400);
$prev_to   = date('Y-m-d', strtotime($from) - 86400);

// ── KPI — current period ──────────────────────────────────────────────────────
$kpi_cur = query_one(
    "SELECT
        COUNT(*)                                   AS total_visits,
        COUNT(DISTINCT visitor_id)                 AS unique_visitors,
        AVG(TIMESTAMPDIFF(MINUTE, check_in_time,
            COALESCE(check_out_time, NOW())))       AS avg_duration_min
     FROM visit_log
     WHERE check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)",
    'ss', [$from, $to]
) ?? [];

$peak_row = query_one(
    "SELECT HOUR(check_in_time) AS hr, COUNT(*) AS cnt
     FROM visit_log
     WHERE check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY hr ORDER BY cnt DESC LIMIT 1",
    'ss', [$from, $to]
);

// ── KPI — previous period ─────────────────────────────────────────────────────
$kpi_prev = query_one(
    "SELECT
        COUNT(*)                                   AS total_visits,
        COUNT(DISTINCT visitor_id)                 AS unique_visitors,
        AVG(TIMESTAMPDIFF(MINUTE, check_in_time,
            COALESCE(check_out_time, NOW())))       AS avg_duration_min
     FROM visit_log
     WHERE check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)",
    'ss', [$prev_from, $prev_to]
) ?? [];

$peak_prev = query_one(
    "SELECT HOUR(check_in_time) AS hr
     FROM visit_log
     WHERE check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY hr ORDER BY COUNT(*) DESC LIMIT 1",
    'ss', [$prev_from, $prev_to]
);

function _delta(float $cur, float $prev): array {
    if ($prev == 0) return ['pct' => null, 'dir' => 'neutral'];
    $pct = round(($cur - $prev) / $prev * 100, 1);
    return ['pct' => $pct, 'dir' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'neutral')];
}

$kpi = [
    'total_visits'    => (int)($kpi_cur['total_visits']    ?? 0),
    'unique_visitors' => (int)($kpi_cur['unique_visitors'] ?? 0),
    'avg_duration_min'=> (float)round($kpi_cur['avg_duration_min'] ?? 0, 1),
    'peak_hour'       => isset($peak_row['hr']) ? (int)$peak_row['hr'] : null,
    'delta_total'     => _delta(
        (float)($kpi_cur['total_visits']    ?? 0),
        (float)($kpi_prev['total_visits']   ?? 0)
    ),
    'delta_unique'    => _delta(
        (float)($kpi_cur['unique_visitors'] ?? 0),
        (float)($kpi_prev['unique_visitors']?? 0)
    ),
    'delta_duration'  => _delta(
        (float)($kpi_cur['avg_duration_min']?? 0),
        (float)($kpi_prev['avg_duration_min']?? 0)
    ),
    'prev_label'      => $prev_from . ' – ' . $prev_to,
];

// ── Heatmap — 7×24 ────────────────────────────────────────────────────────────
// MySQL DAYOFWEEK: 1=Sun, 2=Mon … 7=Sat
$heat_rows = query_all(
    "SELECT DAYOFWEEK(check_in_time) AS dow,
            HOUR(check_in_time)     AS hr,
            COUNT(*)                AS cnt
     FROM visit_log
     WHERE check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY dow, hr",
    'ss', [$from, $to]
);
// Build 7×24 matrix indexed [dow 1..7][hour 0..23]
$heatmap = [];
for ($d = 1; $d <= 7; $d++) $heatmap[$d] = array_fill(0, 24, 0);
foreach ($heat_rows as $r) $heatmap[(int)$r['dow']][(int)$r['hr']] = (int)$r['cnt'];

// ── Departments — top 10 ──────────────────────────────────────────────────────
$dept_rows = query_all(
    "SELECT d.id, d.name, COALESCE(d.colour,'#2e75b6') AS colour, COUNT(vl.id) AS visits
     FROM visit_log vl
     LEFT JOIN departments d ON d.id = vl.department_id
     WHERE vl.check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY d.id, d.name, d.colour
     ORDER BY visits DESC LIMIT 10",
    'ss', [$from, $to]
);
// Handle NULL department_id (no department assigned)
$departments_data = array_map(function($r) {
    return [
        'id'     => $r['id'] ? (int)$r['id'] : 0,
        'name'   => $r['name'] ?? 'Unassigned',
        'colour' => $r['colour'] ?? '#94a3b8',
        'visits' => (int)$r['visits'],
    ];
}, $dept_rows);

// ── Purposes — clustered into ≤8 buckets ─────────────────────────────────────
$purpose_rows = query_all(
    "SELECT purpose, COUNT(*) AS cnt
     FROM visit_log
     WHERE check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
       AND purpose IS NOT NULL AND purpose <> ''
     GROUP BY purpose
     ORDER BY cnt DESC",
    'ss', [$from, $to]
);

$synonym_map = [
    'meeting'     => ['meeting','meet','conference','discussion','brief','session'],
    'interview'   => ['interview','hiring','recruitment','hr meeting','job'],
    'delivery'    => ['delivery','courier','parcel','package','drop'],
    'maintenance' => ['maintenance','repair','fix','service','technician','tech support','it support'],
    'audit'       => ['audit','inspection','review','compliance','survey'],
    'training'    => ['training','workshop','seminar','course','induction'],
    'event'       => ['event','ceremony','launch','presentation','exhibition'],
];

$clustered = [];
$other     = 0;
foreach ($purpose_rows as $pr) {
    $raw   = strtolower(trim($pr['purpose'] ?? ''));
    $cnt   = (int)$pr['cnt'];
    $found = false;
    foreach ($synonym_map as $bucket => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($raw, $kw) !== false) {
                $clustered[$bucket] = ($clustered[$bucket] ?? 0) + $cnt;
                $found = true;
                break 2;
            }
        }
    }
    if (!$found) $other += $cnt;
}
if ($other > 0) $clustered['other'] = $other;

arsort($clustered);
$purposes = [];
foreach (array_slice($clustered, 0, 8, true) as $lbl => $cnt) {
    $purposes[] = ['label' => ucfirst($lbl), 'count' => $cnt];
}

// ── Daily Trend ───────────────────────────────────────────────────────────────
if ($gran === 'week') {
    $group_expr  = "YEARWEEK(check_in_time, 1)";
    $label_expr  = "DATE(MIN(check_in_time))";
} elseif ($gran === 'month') {
    $group_expr  = "DATE_FORMAT(check_in_time,'%Y-%m')";
    $label_expr  = "DATE_FORMAT(MIN(check_in_time),'%Y-%m-01')";
} else {
    $group_expr  = "DATE(check_in_time)";
    $label_expr  = "DATE(check_in_time)";
}

$trend_rows = query_all(
    "SELECT $label_expr AS period, COUNT(*) AS visits
     FROM visit_log
     WHERE check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY $group_expr
     ORDER BY period ASC",
    'ss', [$from, $to]
);
$trend = array_map(function($r) {
    return ['date' => $r['period'], 'visits' => (int)$r['visits']];
}, $trend_rows);

// ── Top 10 Visitors ───────────────────────────────────────────────────────────
$top_visitors = query_all(
    "SELECT vl.visitor_id,
            vis.full_name,
            COUNT(vl.id)                                                          AS visits,
            MAX(vl.check_in_time)                                                 AS last_visit,
            SUM(TIMESTAMPDIFF(MINUTE, vl.check_in_time,
                COALESCE(vl.check_out_time, NOW())))                              AS total_min
     FROM visit_log vl
     JOIN visitors vis ON vis.id = vl.visitor_id
     WHERE vl.check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY vl.visitor_id, vis.full_name
     ORDER BY visits DESC, last_visit DESC
     LIMIT 10",
    'ss', [$from, $to]
);
$top_visitors = array_map(function($r) {
    return [
        'visitor_id' => (int)$r['visitor_id'],
        'full_name'  => $r['full_name'],
        'visits'     => (int)$r['visits'],
        'last_visit' => $r['last_visit'],
        'total_min'  => (int)round($r['total_min'] ?? 0),
    ];
}, $top_visitors);

// ── Drilldown: department ─────────────────────────────────────────────────────
$drilldown = null;
if ($drill_dept > 0) {
    $dept_info = query_one("SELECT id, name FROM departments WHERE id=?", 'i', [$drill_dept]);
    $drill_rows = query_all(
        "SELECT vl.id, vis.full_name, vl.check_in_time, vl.check_out_time,
                vl.purpose, vl.visitor_type, vl.status
         FROM visit_log vl
         JOIN visitors vis ON vis.id = vl.visitor_id
         WHERE vl.department_id=?
           AND vl.check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
         ORDER BY vl.check_in_time DESC LIMIT 100",
        'iss', [$drill_dept, $from, $to]
    );
    $drilldown = [
        'dept_id' => $drill_dept,
        'name'    => $dept_info['name'] ?? '—',
        'visits'  => $drill_rows,
    ];
}

// ── Drilldown: purpose bucket ─────────────────────────────────────────────────
$drilldown_purpose = null;
if ($drill_purpose !== '') {
    $bucket_lc  = strtolower($drill_purpose);
    $keywords   = $synonym_map[$bucket_lc] ?? [$bucket_lc];
    // Build LIKE conditions
    $like_parts = [];
    $like_params = [];
    foreach ($keywords as $kw) {
        $like_parts[]  = "LOWER(vl.purpose) LIKE ?";
        $like_params[] = '%' . $kw . '%';
    }
    $like_sql = implode(' OR ', $like_parts);
    $params   = array_merge($like_params, [$from, $to]);
    $types    = str_repeat('s', count($like_params)) . 'ss';

    $drilldown_purpose = [
        'label'  => ucfirst($drill_purpose),
        'visits' => query_all(
            "SELECT vl.id, vis.full_name, vl.check_in_time, vl.check_out_time,
                    vl.purpose, vl.visitor_type, vl.status
             FROM visit_log vl
             JOIN visitors vis ON vis.id = vl.visitor_id
             WHERE ($like_sql)
               AND vl.check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
             ORDER BY vl.check_in_time DESC LIMIT 100",
            $types, $params
        ),
    ];
}

// ── Assemble & cache ──────────────────────────────────────────────────────────
$payload = json_encode([
    'ok'               => true,
    'from'             => $from,
    'to'               => $to,
    'span_days'        => $span_days,
    'kpi'              => $kpi,
    'heatmap'          => $heatmap,
    'departments'      => $departments_data,
    'purposes'         => $purposes,
    'trend'            => $trend,
    'top_visitors'     => $top_visitors,
    'drilldown'        => $drilldown,
    'drilldown_purpose'=> $drilldown_purpose,
    'generated_at'     => date('c'),
], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

// Write cache (only for non-drilldown requests)
if (!$drill_dept && !$drill_purpose) {
    file_put_contents($cache_file, $payload, LOCK_EX);
}

echo $payload;


// Visitor trend (daily check-ins)
$trend_rows = query_all(
    "SELECT DATE(check_in_time) AS date, COUNT(*) AS cnt
     FROM visits
     WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY DATE(check_in_time) ORDER BY date ASC",
    'i', [$range]
);
$trend = ['labels' => [], 'data' => []];
foreach ($trend_rows as $r) { $trend['labels'][] = $r['date']; $trend['data'][] = (int)$r['cnt']; }

// Purpose breakdown
$purpose_rows = query_all(
    "SELECT purpose, COUNT(*) cnt FROM visits
     WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
     GROUP BY purpose ORDER BY cnt DESC LIMIT 8",
    'i', [$range]
);
$purpose = ['labels' => [], 'data' => []];
foreach ($purpose_rows as $r) { $purpose['labels'][] = $r['purpose']; $purpose['data'][] = (int)$r['cnt']; }

// Hourly (today)
$hourly_rows = query_all(
    "SELECT HOUR(check_in_time) AS hr, COUNT(*) cnt FROM visits
     WHERE DATE(check_in_time) = CURDATE()
     GROUP BY HOUR(check_in_time) ORDER BY hr ASC"
);
$hourly = array_fill(0, 24, 0);
foreach ($hourly_rows as $r) $hourly[(int)$r['hr']] = (int)$r['cnt'];

echo json_encode([
    'trend'   => $trend,
    'purpose' => $purpose,
    'hourly'  => ['labels' => array_map(fn($h) => $h . ':00', range(0, 23)), 'data' => array_values($hourly)],
]);
