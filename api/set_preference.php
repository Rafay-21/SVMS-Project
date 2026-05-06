<?php
/**
 * api/set_preference.php — Save admin UI preferences (theme / language)
 *
 * POST  application/json  { csrf_token, key: 'theme'|'language', value: string }
 * Auth: requires active session (admin_id).
 * Returns: { ok: true } on success, { ok: false, error: '...' } on failure.
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Auth ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}

// ── Parse input ───────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    // Fallback: try POST form data
    $data = $_POST;
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$csrfToken = $data['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF mismatch']);
    exit;
}

// ── Validate key & value ─────────────────────────────────────────────────────
$key   = $data['key']   ?? '';
$value = $data['value'] ?? '';

$allowed = [
    'theme'    => ['light', 'dark', 'system'],
    'language' => ['en', 'ur'],
];

if (!isset($allowed[$key])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid key']);
    exit;
}

if (!in_array($value, $allowed[$key], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid value']);
    exit;
}

// ── Persist to DB ─────────────────────────────────────────────────────────────
$adminId = (int)$_SESSION['admin_id'];
$column  = $key === 'theme' ? 'theme' : 'language';

query_exec("UPDATE admins SET `{$column}` = ? WHERE id = ?", 'si', [$value, $adminId]);

// ── Update session ────────────────────────────────────────────────────────────
if ($key === 'theme') {
    $_SESSION['theme'] = $value;
} elseif ($key === 'language') {
    $_SESSION['lang'] = $value;
    setcookie('svms_lang', $value, time() + 365 * 86400, '/', '', false, true);
}

echo json_encode(['ok' => true]);
