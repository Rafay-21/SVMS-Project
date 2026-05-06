<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

// Public endpoint — no auth required (kiosk submits feedback)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$visitor_id = (int)($_POST['visitor_id'] ?? 0);
$visit_id   = (int)($_POST['visit_id']   ?? 0);
$rating     = (int)($_POST['rating']     ?? 0);
$comment    = sanitize($_POST['comment'] ?? '');

if ($rating < 1 || $rating > 5) { $rating = null; }

if (!$visitor_id && !$visit_id) {
    echo json_encode(['success' => false, 'error' => 'visitor_id or visit_id required.']);
    exit;
}

query_exec(
    'INSERT INTO feedback (visitor_id, visit_id, rating, comment, submitted_at) VALUES (?,?,?,?,NOW())',
    'iiis', [$visitor_id ?: null, $visit_id ?: null, $rating, $comment]
);

echo json_encode(['success' => true, 'id' => last_insert_id()]);
