<?php
/**
 * api/checkout.php — Visitor Check-Out endpoint
 * POST /api/checkout.php
 *
 * Body (JSON):
 *   csrf_token     string  required
 *   visit_log_id   int     required
 *   rating         int     optional 1-5 (writes feedback row)
 *   notes          string  optional
 *
 * Returns JSON {ok, duration_minutes, success_message}
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/email_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

// ── Parse JSON body ───────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── CSRF ──────────────────────────────────────────────────────
$token  = $body['csrf_token'] ?? '';
$stored = $_SESSION['csrf_token'] ?? '';
if (!hash_equals($stored, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch.']);
    exit;
}

// ── Input ─────────────────────────────────────────────────────
$visit_log_id = (int)($body['visit_log_id'] ?? 0);
$rating       = isset($body['rating']) ? (int)$body['rating'] : null;
$notes        = trim($body['notes'] ?? '');

if ($rating !== null && ($rating < 1 || $rating > 5)) $rating = null;
if (strlen($notes) > 1000) $notes = substr($notes, 0, 1000);

if ($visit_log_id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'visit_log_id is required.']);
    exit;
}

global $conn;

// ── Validate visit exists and is active ───────────────────────
$visit = query_one(
    "SELECT vl.id, vl.visitor_id, vl.check_in_time, vl.badge_number, v.full_name
     FROM visit_log vl
     JOIN visitors v ON v.id = vl.visitor_id
     WHERE vl.id=? AND vl.status='checked_in' LIMIT 1",
    'i', [$visit_log_id]
);
if (!$visit) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Visit not found or already checked out.']);
    exit;
}

// ── Transaction ───────────────────────────────────────────────
$conn->begin_transaction();
try {
    // UPDATE visit_log
    query_exec(
        "UPDATE visit_log SET status='checked_out', check_out_time=NOW() WHERE id=?",
        'i', [$visit_log_id]
    );

    // Optional feedback
    if ($rating !== null) {
        // Create feedback table if not exists (idempotent guard)
        $conn->query(
            "CREATE TABLE IF NOT EXISTS feedback (
                id INT PRIMARY KEY AUTO_INCREMENT,
                visit_log_id INT NOT NULL,
                rating TINYINT NOT NULL,
                notes TEXT,
                created_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (visit_log_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $stmt = $conn->prepare(
            "INSERT INTO feedback (visit_log_id, rating, notes, created_by, created_at)
             VALUES (?,?,?,?,NOW())"
        );
        $stmt->bind_param('iisi', $visit_log_id, $rating, $notes, $_SESSION['admin_id']);
        $stmt->execute();
        $stmt->close();
    }

    // Notification
    $notif_title = 'Visitor Checked Out';
    $notif_msg   = $visit['full_name'] . ' has checked out. Badge: ' . $visit['badge_number'];
    $notif_link  = BASE_URL . 'pages/visitor_detail.php?id=' . $visit_log_id;

    $stmt = $conn->prepare(
        "INSERT INTO notifications (type, title, message, link, recipient_id, is_read, created_at)
         VALUES ('visitor_out',?,?,?,NULL,0,NOW())"
    );
    $stmt->bind_param('sss', $notif_title, $notif_msg, $notif_link);
    $stmt->execute();
    $stmt->close();

    // Duration
    $duration_mins = (int)round((time() - strtotime($visit['check_in_time'])) / 60);
    $dur_str = $duration_mins >= 60
        ? floor($duration_mins / 60) . 'h ' . ($duration_mins % 60) . 'm'
        : $duration_mins . ' min';

    // Generate signed public feedback URL
    $fb_token    = hash_hmac('sha256', 'fb:' . $visit_log_id, APP_KEY);
    $feedback_url = BASE_URL . 'pages/feedback_public.php?vid=' . $visit_log_id . '&tok=' . $fb_token;

    log_action('check_out', $visit_log_id, json_encode([
        'visitor_id'     => (int)$visit['visitor_id'],
        'name'           => $visit['full_name'],
        'duration_mins'  => $duration_mins,
        'rating'         => $rating,
    ]));

    $conn->commit();

    // ── Visitor checkout email (non-fatal) ──────────────────
    try {
        $vemail_row = query_one('SELECT email FROM visitors WHERE id=? AND email != "" LIMIT 1', 'i', [(int)$visit['visitor_id']]);
        if ($vemail_row && filter_var($vemail_row['email'], FILTER_VALIDATE_EMAIL)) {
            $site_name = defined('SITE_NAME') ? SITE_NAME : 'SVMS';
            ['html' => $h, 'text' => $t] = render_email_template('visitor_checkout', [
                'visitor_name'  => $visit['full_name'],
                'badge_number'  => $visit['badge_number'],
                'check_in_time' => date('d M Y g:i A', strtotime($visit['check_in_time'])),
                'check_out_time'=> date('d M Y g:i A'),
                'duration'      => $dur_str,
                'feedback_url'  => $feedback_url,
                'site_name'     => $site_name,
                'year'          => date('Y'),
            ]);
            send_email($vemail_row['email'],
                'Thank you for visiting — ' . $site_name,
                $h, $t,
                ['related_type' => 'visit_log', 'related_id' => $visit_log_id]);
        }
    } catch (\Throwable $ignored) {}

    echo json_encode([
        'ok'              => true,
        'duration_minutes'=> $duration_mins,
        'success_message' => $visit['full_name'] . ' checked out after ' . $dur_str . '.',
        'visitor_name'    => $visit['full_name'],
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    error_log('checkout.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'System error. Please try again.']);
}
