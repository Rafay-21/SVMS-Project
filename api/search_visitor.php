<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$q = sanitize($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$like    = '%' . $q . '%';
$results = query_all(
    'SELECT id, name, cnic, badge_number, phone, photo_path FROM visitors
     WHERE name LIKE ? OR cnic LIKE ? OR badge_number LIKE ?
     ORDER BY name ASC LIMIT 10',
    'sss', [$like, $like, $like]
);

$out = [];
foreach ($results as $r) {
    $out[] = [
        'id'           => (int)$r['id'],
        'name'         => $r['name'],
        'cnic'         => $r['cnic'],
        'badge_number' => $r['badge_number'],
        'phone'        => $r['phone'],
        'photo_url'    => $r['photo_path'] ? BASE_URL . 'assets/uploads/' . $r['photo_path'] : null,
    ];
}
echo json_encode($out);
