<?php
/**
 * api/get_stats.php — Dashboard statistics endpoint
 * Returns JSON for AJAX stat-card refresh.
 * GET /api/get_stats.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Core counts ───────────────────────────────────────────────────────────────
$today_total    = (int)(query_one("SELECT COUNT(*) cnt FROM visits WHERE DATE(check_in_time)=CURDATE()")['cnt'] ?? 0);
$checked_in     = (int)(query_one("SELECT COUNT(*) cnt FROM visits WHERE status='checked_in'")['cnt'] ?? 0);
$checked_out    = (int)(query_one("SELECT COUNT(*) cnt FROM visits WHERE status='checked_out' AND DATE(check_out_time)=CURDATE()")['cnt'] ?? 0);
$month_total    = (int)(query_one("SELECT COUNT(*) cnt FROM visits WHERE MONTH(check_in_time)=MONTH(CURDATE()) AND YEAR(check_in_time)=YEAR(CURDATE())")['cnt'] ?? 0);
$yesterday      = (int)(query_one("SELECT COUNT(*) cnt FROM visits WHERE DATE(check_in_time)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)")['cnt'] ?? 0);

// ── Trend vs yesterday ────────────────────────────────────────────────────────
$trend_pct = $yesterday > 0
    ? (int)round((($today_total - $yesterday) / $yesterday) * 100)
    : ($today_total > 0 ? 100 : 0);

// ── Status breakdown (today) ──────────────────────────────────────────────────
$status_breakdown = ['checked_in' => 0, 'checked_out' => 0, 'no_show' => 0];
foreach (query_all("SELECT status, COUNT(*) cnt FROM visits WHERE DATE(check_in_time)=CURDATE() GROUP BY status") as $r) {
    if (isset($status_breakdown[$r['status']])) {
        $status_breakdown[$r['status']] = (int)$r['cnt'];
    }
}

// ── 7-day trend ───────────────────────────────────────────────────────────────
$chart_rows = query_all(
    "SELECT DATE(check_in_time) AS day, COUNT(*) AS cnt FROM visits
     WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(check_in_time)"
);
$day_map = [];
foreach ($chart_rows as $r) { $day_map[$r['day']] = (int)$r['cnt']; }

$chart_labels = [];
$chart_data   = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chart_labels[] = date('D', strtotime($d));
    $chart_data[]   = $day_map[$d] ?? 0;
}

echo json_encode([
    'today_total'         => $today_total,
    'checked_in'          => $checked_in,
    'checked_out_today'   => $checked_out,
    'month_total'         => $month_total,
    'trend_yesterday_pct' => $trend_pct,
    'status_breakdown'    => $status_breakdown,
    'chart_7day'          => ['labels' => $chart_labels, 'data' => $chart_data],
    'generated_at'        => date('c'),
]);
