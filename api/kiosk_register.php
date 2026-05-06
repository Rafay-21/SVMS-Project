<?php
/**
 * api/kiosk_register.php
 * POST — register a new visitor from the kiosk (new visitor tab).
 * Creates visitors row + visit_log row with registered_by = NULL.
 * Returns JSON: {ok:true, visitor_id:N, visit_log_id:N}
 */
require_once __DIR__ . '/../kiosk/kiosk_boot.php';
require_once __DIR__ . '/../includes/qr_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kiosk_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

if (empty($_SESSION['kiosk_active'])) {
    kiosk_json(['ok' => false, 'error' => 'Session expired.'], 401);
}

if (!kiosk_rate_limit(10, 60)) {
    kiosk_json(['ok' => false, 'error' => 'Too many registrations. Please see reception.'], 429);
}

kiosk_csrf_validate();

$raw  = file_get_contents('php://input');
$body = $raw ? (json_decode($raw, true) ?: []) : [];
$body = array_merge($_POST, $body);

/* ── Validate fields ──────────────────────────────────────── */
$full_name      = trim($body['full_name']      ?? '');
$phone          = trim($body['phone']          ?? '');
$cnic           = trim($body['cnic']           ?? '');
$department_id  = (int)($body['department_id'] ?? 0);
$person_to_meet = trim($body['person_to_meet'] ?? '');
$purpose        = trim($body['purpose']        ?? 'Visit');

if (strlen($full_name) < 2) {
    kiosk_json(['ok' => false, 'error' => 'Full name is required.'], 422);
}
if (!preg_match('/^[0-9\s\-+]{7,15}$/', $phone)) {
    kiosk_json(['ok' => false, 'error' => 'Please enter a valid phone number.'], 422);
}
if (strlen($person_to_meet) < 2) {
    kiosk_json(['ok' => false, 'error' => 'Please enter who you are meeting.'], 422);
}

// Sanitise
$full_name      = htmlspecialchars(strip_tags($full_name), ENT_QUOTES, 'UTF-8');
$phone          = preg_replace('/[^0-9+\-\s]/', '', $phone);
$cnic           = preg_replace('/[^0-9\-]/', '', $cnic);
$person_to_meet = htmlspecialchars(strip_tags($person_to_meet), ENT_QUOTES, 'UTF-8');
$purpose        = in_array($purpose, ['Meeting','Delivery','Interview','Personal','Other']) ? $purpose : 'Visit';

/* ── Blacklist check ──────────────────────────────────────── */
$bl = query_one(
    "SELECT id FROM blacklist WHERE (phone = ? OR (cnic != '' AND cnic = ?)) AND is_active = 1 LIMIT 1",
    [$phone, $cnic ?: '~~~NONE~~~']
);
if ($bl) {
    kiosk_json(['ok' => false, 'error' => 'Access denied. Please see reception.'], 403);
}

/* ── Create or find visitor ───────────────────────────────── */
$visitor = query_one("SELECT id FROM visitors WHERE phone = ? LIMIT 1", [$phone]);

if ($visitor) {
    $visitor_id = (int)$visitor['id'];
} else {
    $qr_token     = generate_qr_token();
    $badge_number = generate_badge_number();

    query_exec(
        "INSERT INTO visitors (full_name, phone, cnic, badge_number, qr_token, visitor_type, created_at)
         VALUES (?, ?, ?, ?, ?, 'walk_in', NOW())",
        [$full_name, $phone, $cnic ?: null, $badge_number, $qr_token]
    );
    $visitor_id = (int)last_insert_id();
    if (!$visitor_id) {
        kiosk_json(['ok' => false, 'error' => 'Registration failed. Please see reception.'], 500);
    }
}

/* ── Create visit_log row ─────────────────────────────────── */
query_exec(
    "INSERT INTO visit_log
       (visitor_id, department_id, person_to_meet, purpose, check_in_time, status, registered_by, visitor_type)
     VALUES (?, ?, ?, ?, NOW(), 'checked_in', NULL, 'walk_in')",
    [
        $visitor_id,
        $department_id ?: null,
        $person_to_meet,
        $purpose,
    ]
);
$visit_log_id = (int)last_insert_id();
if (!$visit_log_id) {
    kiosk_json(['ok' => false, 'error' => 'Check-in failed. Please see reception.'], 500);
}

log_action('kiosk_register', $visit_log_id, json_encode([
    'visitor_id'     => $visitor_id,
    'full_name'      => $full_name,
    'phone'          => $phone,
    'person_to_meet' => $person_to_meet,
    'purpose'        => $purpose,
    'department_id'  => $department_id,
]));

kiosk_json(['ok' => true, 'visitor_id' => $visitor_id, 'visit_log_id' => $visit_log_id]);
