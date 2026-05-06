<?php
/**
 * api/run_auto_checkout.php — Admin-triggered auto-checkout
 * POST /api/run_auto_checkout.php
 *
 * Body (JSON): { csrf_token }
 * Returns: { ok, count, message }
 *
 * Requires: manage_settings permission (Operations tab trigger)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('manage_settings');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$token = $body['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch.']);
    exit;
}

$threshold = MAX_VISIT_HOURS * 3600;
$visits = query_all(
    "SELECT id, visitor_id, check_in_time FROM visit_log
     WHERE status = 'checked_in'
       AND TIMESTAMPDIFF(SECOND, check_in_time, NOW()) >= ?",
    'i', [$threshold]
);

$count = 0;
foreach ($visits as $v) {
    query_exec(
        "UPDATE visit_log SET check_out_time=NOW(), status='auto_checkout' WHERE id=?",
        'i', [(int)$v['id']]
    );
    query_exec(
        "INSERT INTO notifications (type, title, message, link, recipient_id, is_read, created_at)
         VALUES ('auto_checkout', 'Auto Check-Out', ?, ?, NULL, 0, NOW())",
        'ss',
        [
            'Visitor auto-checked-out after ' . MAX_VISIT_HOURS . 'h (Visit #' . (int)$v['id'] . ')',
            BASE_URL . 'pages/visitor_detail.php?id=' . (int)$v['id'],
        ]
    );
    log_action('auto_checkout', (int)$v['id'], json_encode([
        'visitor_id'      => $v['visitor_id'],
        'check_in'        => $v['check_in_time'],
        'threshold_hours' => MAX_VISIT_HOURS,
        'triggered_by'    => (int)$_SESSION['admin_id'],
    ]));
    $count++;
}

echo json_encode([
    'ok'      => true,
    'count'   => $count,
    'message' => $count > 0
        ? $count . ' visitor(s) auto-checked-out (threshold: ' . MAX_VISIT_HOURS . 'h).'
        : 'No overstaying visitors found.',
]);
