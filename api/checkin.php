<?php
/**
 * api/checkin.php — Quick Check-In endpoint
 * POST /api/checkin.php
 *
 * Body (JSON):
 *   csrf_token      string  required
 *   visitor_id      int     required
 *   department_id   int     optional
 *   person_to_meet  string  required
 *   purpose         string  required
 *   vehicle_number  string  optional
 *   visitor_type    string  optional (default walk_in)
 *
 * Returns JSON {ok, visit_log_id, badge_number, badge_url}
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/email_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// ── Only POST ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── Auth ──────────────────────────────────────────────────────
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

// ── Input extraction & sanitisation ──────────────────────────
$visitor_id    = (int)($body['visitor_id']    ?? 0);
$department_id = (int)($body['department_id'] ?? 0) ?: null;
$person_meet   = trim($body['person_to_meet'] ?? '');
$purpose       = trim($body['purpose']        ?? '');
$vehicle       = strtoupper(trim($body['vehicle_number'] ?? ''));
$visitor_type  = trim($body['visitor_type']   ?? 'walk_in');

$allowed_types = ['walk_in','appointment','delivery','vendor','contractor','vip'];
if (!in_array($visitor_type, $allowed_types, true)) $visitor_type = 'walk_in';

// ── Server-side validation ────────────────────────────────────
$errors = [];
if ($visitor_id <= 0)    $errors[] = 'visitor_id is required.';
if ($person_meet === '')  $errors[] = 'person_to_meet is required.';
if ($purpose === '')      $errors[] = 'purpose is required.';
if (strlen($person_meet) > 100) $errors[] = 'person_to_meet exceeds 100 characters.';
if (strlen($purpose) > 500)     $errors[] = 'purpose exceeds 500 characters.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
    exit;
}

global $conn;

// ── Visitor must exist ────────────────────────────────────────
$visitor = query_one("SELECT id, full_name, phone, cnic, vip FROM visitors WHERE id=? LIMIT 1", 'i', [$visitor_id]);
if (!$visitor) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Visitor not found.']);
    exit;
}

// ── No active check-in already ────────────────────────────────
$active = query_one(
    "SELECT id FROM visit_log WHERE visitor_id=? AND status='checked_in' LIMIT 1",
    'i', [$visitor_id]
);
if ($active) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'This visitor already has an active check-in (ID ' . (int)$active['id'] . ').']);
    exit;
}

// ── Blacklist gate (server-side, un-bypassable) ───────────────
$bl = query_one(
    "SELECT severity, reason FROM blacklist
     WHERE (phone=? OR (? != '' AND cnic=?)) AND is_active=1 LIMIT 1",
    'sss', [$visitor['phone'], $visitor['cnic'], $visitor['cnic']]
);
if ($bl && in_array(strtolower($bl['severity'] ?? 'high'), ['high', 'critical'], true)) {
    log_action('checkin_blocked', $visitor_id, json_encode(['reason' => $bl['reason'], 'severity' => $bl['severity']]));
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Visitor is on the watchlist and cannot be checked in. Reason: ' . $bl['reason']]);
    exit;
}

// ── Badge number ──────────────────────────────────────────────
$seq_row   = query_one("SELECT COUNT(*)+1 AS seq FROM visit_log WHERE DATE(check_in_time)=CURDATE()");
$seq       = str_pad((int)($seq_row['seq'] ?? 1), 4, '0', STR_PAD_LEFT);
$badge_num = BADGE_PREFIX . '-' . date('ymd') . '-' . $seq;

// ── Transaction ───────────────────────────────────────────────
$conn->begin_transaction();
try {
    // INSERT visit_log
    $stmt = $conn->prepare(
        "INSERT INTO visit_log
           (visitor_id, department_id, person_to_meet, purpose, vehicle_number,
            badge_number, visitor_type, check_in_time, status, registered_by)
         VALUES (?,?,?,?,?,?,?,NOW(),'checked_in',?)"
    );
    $stmt->bind_param('iisssssi',
        $visitor_id, $department_id, $person_meet, $purpose,
        $vehicle, $badge_num, $visitor_type, $_SESSION['admin_id']
    );
    $stmt->execute();
    $visit_log_id = (int)$conn->insert_id;
    $stmt->close();

    // Notification
    $is_vip      = (bool)$visitor['vip'] || $visitor_type === 'vip';
    $notif_type  = $is_vip ? 'vip_arrival' : 'visitor_in';
    $notif_title = $is_vip ? '⭐ VIP Visitor Checked In' : 'New Visitor Checked In';
    $notif_msg   = $visitor['full_name'] . ' is here to meet ' . $person_meet . ($is_vip ? ' (VIP)' : '');
    $notif_link  = BASE_URL . 'pages/visitor_detail.php?id=' . $visit_log_id;

    $stmt = $conn->prepare(
        "INSERT INTO notifications (type, title, message, link, recipient_id, is_read, created_at)
         VALUES (?,?,?,?,NULL,0,NOW())"
    );
    $stmt->bind_param('ssss', $notif_type, $notif_title, $notif_msg, $notif_link);
    $stmt->execute();
    $stmt->close();

    log_action('check_in', $visit_log_id, json_encode([
        'visitor_id' => $visitor_id,
        'name'       => $visitor['full_name'],
        'badge'      => $badge_num,
    ]));

    $conn->commit();

    // ── Email triggers (non-fatal) ──────────────────────────
    try {
        $site_name   = defined('SITE_NAME') ? SITE_NAME : 'SVMS';
        $visit_url   = BASE_URL . 'pages/visitor_detail.php?id=' . $visit_log_id;
        $checkin_fmt = date('d M Y g:i A');

        // Fetch department name
        $dept_name = '';
        if ($department_id > 0) {
            $drow = query_one('SELECT name FROM departments WHERE id=? LIMIT 1', 'i', [$department_id]);
            $dept_name = $drow['name'] ?? '';
        }

        // Host notification — look up host email from admins table
        $host_row = query_one(
            "SELECT full_name, email FROM admins
              WHERE is_active=1
                AND (full_name LIKE ? OR email LIKE ?)
              LIMIT 1",
            'ss',
            ['%' . $person_meet . '%', '%' . $person_meet . '%']
        );
        if ($host_row && filter_var($host_row['email'], FILTER_VALIDATE_EMAIL)) {
            ['html' => $h, 'text' => $t] = render_email_template('host_notification', [
                'site_name'         => $site_name,
                'host_name'         => $host_row['full_name'],
                'visitor_name'      => $visitor['full_name'],
                'visitor_phone'     => $visitor['phone'],
                'visitor_photo_url' => '',
                'purpose'           => $purpose,
                'check_in_time'     => $checkin_fmt,
                'badge_number'      => $badge_num,
                'department'        => $dept_name,
                'visit_url'         => $visit_url,
                'year'              => date('Y'),
            ]);
            send_email($host_row['email'],
                $visitor['full_name'] . ' is here to see you — ' . $site_name,
                $h, $t,
                ['related_type' => 'visit_log', 'related_id' => $visit_log_id]);
        }

        // Visitor check-in receipt — if visitor has email on record
        $vemail_row = query_one('SELECT email FROM visitors WHERE id=? AND email != "" LIMIT 1', 'i', [$visitor_id]);
        if ($vemail_row && filter_var($vemail_row['email'], FILTER_VALIDATE_EMAIL)) {
            ['html' => $h2, 'text' => $t2] = render_email_template('visitor_checkin', [
                'visitor_name'   => $visitor['full_name'],
                'badge_number'   => $badge_num,
                'person_to_meet' => $person_meet,
                'purpose'        => $purpose,
                'check_in_time'  => $checkin_fmt,
                'department'     => $dept_name,
                'site_name'      => $site_name,
                'year'           => date('Y'),
            ]);
            send_email($vemail_row['email'],
                'Check-in confirmed — ' . $site_name,
                $h2, $t2,
                ['related_type' => 'visit_log', 'related_id' => $visit_log_id]);
        }
    } catch (\Throwable $ignored) {}

    echo json_encode([
        'ok'           => true,
        'visit_log_id' => $visit_log_id,
        'badge_number' => $badge_num,
        'badge_url'    => BASE_URL . 'pages/visitor_detail.php?id=' . $visit_log_id . '&print=1',
        'visitor_name' => $visitor['full_name'],
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    error_log('checkin.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'System error. Please try again.']);
}
