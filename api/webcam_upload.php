<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// CSRF check (supports JSON body or form post)
$raw  = file_get_contents('php://input');
$body = $raw ? (json_decode($raw, true) ?: []) : [];
$csrf_token = $_POST['csrf_token'] ?? ($body['csrf_token'] ?? '');
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF token mismatch.']);
    exit;
}

$visitor_id = (int)($_POST['visitor_id'] ?? $body['visitor_id'] ?? 0);
$photo_data = $_POST['photo_data'] ?? ($body['photo_data'] ?? '');

if (!$visitor_id || !$photo_data || strpos($photo_data, 'data:image') !== 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid visitor_id or photo_data.']);
    exit;
}

// Decode base64 image bytes
$img_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $photo_data));
if (!$img_data || strlen($img_data) < 100) {
    echo json_encode(['success' => false, 'error' => 'Failed to decode image.']);
    exit;
}

// ── MIME validation via finfo ─────────────────────────────────────────────────
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mime     = finfo_buffer($finfo, $img_data);
finfo_close($finfo);

$allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowed_mimes, true)) {
    echo json_encode(['success' => false, 'error' => 'Unsupported image type.']);
    exit;
}

// ── Re-encode through GD (strips EXIF/comments, validates pixel data) ────────
$gdImg = @imagecreatefromstring($img_data);
if ($gdImg === false) {
    echo json_encode(['success' => false, 'error' => 'Invalid image data.']);
    exit;
}

// Enforce max dimensions (4096×4096)
$w = imagesx($gdImg);
$h = imagesy($gdImg);
if ($w > 4096 || $h > 4096 || $w < 10 || $h < 10) {
    imagedestroy($gdImg);
    echo json_encode(['success' => false, 'error' => 'Image dimensions out of range.']);
    exit;
}

$dir = UPLOAD_DIR . 'visitor_photos/';
if (!is_dir($dir)) mkdir($dir, 0750, true);

// Filename: never use user-supplied name
$filename   = bin2hex(random_bytes(16)) . '.jpg';
$filepath   = $dir . $filename;
$photo_path = 'visitor_photos/' . $filename;

// Save re-encoded JPEG (quality 85, strips metadata)
$saved = imagejpeg($gdImg, $filepath, 85);
imagedestroy($gdImg);

if (!$saved) {
    echo json_encode(['success' => false, 'error' => 'Could not save photo file.']);
    exit;
}

query_exec('UPDATE visitors SET photo_path=? WHERE id=?', 'si', [$photo_path, $visitor_id]);
log_action('photo_upload', $visitor_id);

echo json_encode([
    'success'   => true,
    'photo_url' => BASE_URL . 'assets/uploads/' . $photo_path,
]);
