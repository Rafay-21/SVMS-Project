<?php
/**
 * api/kiosk_search.php
 * POST — search visitors by phone number (or name) for the kiosk.
 * Returns JSON: {ok:true, visitors:[{id,full_name,phone,photo_path,vip}]}
 */
require_once __DIR__ . '/../kiosk/kiosk_boot.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kiosk_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

if (empty($_SESSION['kiosk_active'])) {
    kiosk_json(['ok' => false, 'error' => 'Session expired.'], 401);
}

if (!kiosk_rate_limit(30, 60)) {
    kiosk_json(['ok' => false, 'error' => 'Too many requests. Please wait a moment.'], 429);
}

kiosk_csrf_validate();

// Parse JSON or form body
$raw  = file_get_contents('php://input');
$body = $raw ? (json_decode($raw, true) ?: []) : [];
$body = array_merge($_POST, $body);

$q      = trim($body['phone'] ?? $body['q'] ?? '');
$action = ($body['action'] ?? 'checkin') === 'checkout' ? 'checkout' : 'checkin';

if (strlen($q) < 3) {
    kiosk_json(['ok' => false, 'error' => 'Please enter at least 3 characters.'], 422);
}

// Sanitise query
$q = preg_replace('/[^0-9a-zA-Z\s\-]/', '', $q);

// Check visitor by phone (exact prefix) or name LIKE
$rows = query_all(
    "SELECT v.id, v.full_name, v.phone, v.photo_path, v.vip,
            COUNT(vl.id) AS visit_count,
            MAX(vl.check_in_time) AS last_visit,
            b.id AS blacklisted
     FROM visitors v
     LEFT JOIN visit_log vl ON vl.visitor_id = v.id
     LEFT JOIN blacklist  b  ON (b.phone = v.phone OR b.cnic = v.cnic) AND b.is_active = 1
     WHERE (v.phone LIKE ? OR v.full_name LIKE ?)
     GROUP BY v.id
     ORDER BY last_visit DESC
     LIMIT 10",
    [$q . '%', '%' . $q . '%']
);

if (!$rows) {
    kiosk_json(['ok' => false, 'error' => 'No visitor found with that phone number.']);
}

$results = [];
foreach ($rows as $r) {
    // Skip blacklisted
    if (!empty($r['blacklisted'])) continue;

    // For checkout: only show visitors with an active check-in
    if ($action === 'checkout') {
        $active = query_one(
            "SELECT id FROM visit_log WHERE visitor_id = ? AND status = 'checked_in' LIMIT 1",
            [(int)$r['id']]
        );
        if (!$active) continue;
    }

    $results[] = [
        'id'          => (int)$r['id'],
        'full_name'   => $r['full_name'],
        'phone'       => $r['phone'],
        'photo_path'  => $r['photo_path'] ?: null,
        'vip'         => (bool)$r['vip'],
        'visit_count' => (int)$r['visit_count'],
    ];
}

if (!$results) {
    $msg = $action === 'checkout'
        ? 'No active check-in found for that phone number.'
        : 'No visitor found with that phone number.';
    kiosk_json(['ok' => false, 'error' => $msg]);
}

kiosk_json(['ok' => true, 'visitors' => $results]);
