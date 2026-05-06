<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = sanitize($_POST['token'] ?? '');
if (!$token) {
    echo json_encode(['success' => false, 'error' => 'No barcode/token provided.']);
    exit;
}

// Look up by badge number OR qr_token
$visitor = query_one(
    'SELECT id, name, badge_number, cnic, phone, photo_path FROM visitors WHERE badge_number = ? OR qr_token = ? LIMIT 1',
    'ss', [$token, $token]
);

if (!$visitor) {
    echo json_encode(['success' => false, 'error' => 'Visitor not found for token: ' . $token]);
    exit;
}

// Check if currently checked in
$active = query_one("SELECT id FROM visits WHERE visitor_id=? AND status='checked_in' LIMIT 1", 'i', [(int)$visitor['id']]);

echo json_encode([
    'success'       => true,
    'visitor_id'    => (int)$visitor['id'],
    'name'          => $visitor['name'],
    'badge_number'  => $visitor['badge_number'],
    'cnic'          => $visitor['cnic'],
    'phone'         => $visitor['phone'],
    'photo_url'     => $visitor['photo_path'] ? BASE_URL . 'assets/uploads/' . $visitor['photo_path'] : null,
    'is_checked_in' => (bool)$active,
    'visit_id'      => $active ? (int)$active['id'] : null,
]);
