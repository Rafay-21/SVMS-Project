<?php
/**
 * api/smart_search.php — Visitor typeahead search endpoint
 * GET /api/smart_search.php?q={term}
 *
 * Returns JSON array of up to 8 visitor records with last-visit info.
 * Matches on: full_name, phone, cnic (LIKE %q%) or exact qr_token.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// ── Auth guard ────────────────────────────────────────────────
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// ── Rate limit: 60 searches/min per admin ─────────────────────
if (!rl_check('ss:' . (int)$_SESSION['admin_id'], 60)) {
    rl_abort();
}

// ── Input validation ──────────────────────────────────────────
$q = trim($_GET['q'] ?? '');
$q = preg_replace('/[\x00-\x1F\x7F]/u', '', $q);

if (strlen($q) < 2) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}
if (strlen($q) > 50) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Query too long.']);
    exit;
}

// ── Build query ───────────────────────────────────────────────
$like = '%' . $q . '%';

$sql = "
    SELECT
        v.id,
        v.full_name,
        v.phone,
        v.cnic,
        v.photo_path,
        v.vip,
        (v.qr_token = ?) AS qr_exact,
        (SELECT COUNT(*)        FROM visit_log vl2 WHERE vl2.visitor_id = v.id) AS total_visits,
        (SELECT vl3.check_in_time FROM visit_log vl3 WHERE vl3.visitor_id = v.id ORDER BY vl3.check_in_time DESC LIMIT 1) AS last_visit,
        (SELECT vl4.status        FROM visit_log vl4 WHERE vl4.visitor_id = v.id ORDER BY vl4.check_in_time DESC LIMIT 1) AS last_status,
        (SELECT vl5.department_id FROM visit_log vl5 WHERE vl5.visitor_id = v.id ORDER BY vl5.check_in_time DESC LIMIT 1) AS last_dept_id,
        (SELECT vl6.person_to_meet FROM visit_log vl6 WHERE vl6.visitor_id = v.id ORDER BY vl6.check_in_time DESC LIMIT 1) AS last_person_meet,
        (SELECT vl7.purpose        FROM visit_log vl7 WHERE vl7.visitor_id = v.id ORDER BY vl7.check_in_time DESC LIMIT 1) AS last_purpose,
        (SELECT vl8.vehicle_number FROM visit_log vl8 WHERE vl8.visitor_id = v.id ORDER BY vl8.check_in_time DESC LIMIT 1) AS last_vehicle,
        (SELECT bl.severity FROM blacklist bl
            WHERE (bl.phone = v.phone OR (v.cnic != '' AND bl.cnic = v.cnic)) AND bl.is_active = 1 LIMIT 1) AS bl_severity,
        (SELECT bl2.reason FROM blacklist bl2
            WHERE (bl2.phone = v.phone OR (v.cnic != '' AND bl2.cnic = v.cnic)) AND bl2.is_active = 1 LIMIT 1) AS bl_reason
    FROM visitors v
    WHERE v.full_name LIKE ? OR v.phone LIKE ? OR v.cnic LIKE ? OR v.qr_token = ?
    ORDER BY qr_exact DESC, last_visit DESC
    LIMIT 8
";

global $conn;
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Query preparation failed.']);
    exit;
}

$stmt->bind_param('sssss', $q, $like, $like, $like, $q);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = [
        'id'              => (int)$row['id'],
        'full_name'       => $row['full_name'],
        'phone'           => $row['phone'],
        'cnic'            => $row['cnic'],
        'photo_path'      => $row['photo_path'] ? BASE_URL . 'assets/uploads/' . $row['photo_path'] : '',
        'vip'             => (bool)$row['vip'],
        'total_visits'    => (int)$row['total_visits'],
        'last_visit'      => $row['last_visit'],
        'last_status'     => $row['last_status'],
        'last_dept_id'    => $row['last_dept_id'],
        'last_person_meet'=> $row['last_person_meet'],
        'last_purpose'    => $row['last_purpose'],
        'last_vehicle'    => $row['last_vehicle'],
        'blacklisted'     => !empty($row['bl_severity']),
        'bl_severity'     => $row['bl_severity'] ?? null,
        'bl_reason'       => $row['bl_reason']   ?? null,
    ];
}
$stmt->close();

echo json_encode(['ok' => true, 'results' => $rows], JSON_UNESCAPED_UNICODE);

