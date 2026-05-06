<?php
/**
 * api/restore_backup.php — Restore database from a backup file
 * POST multipart/form-data OR JSON
 *
 * Fields:
 *   csrf_token   string  required
 *   source       'existing'|'upload'
 *   filename     string  (when source=existing, basename only)
 *   sql_file     file    (when source=upload, .sql or .sql.gz, max 50MB)
 *   confirm_word string  must equal 'RESTORE'
 *   password     string  current admin password (re-authentication)
 *
 * Returns: { ok, message }
 *
 * Security:
 *   - Super Admin only
 *   - CSRF validated
 *   - Password re-auth
 *   - finfo MIME + first-KB inspection
 *   - Executes inside START TRANSACTION / ROLLBACK on failure
 *   - Kills all other sessions after restore
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('manage_settings');

// Super Admin check (only super_admin role may restore)
if (role_slug((int)$_SESSION['role_id']) !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Super Admin access required.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────
$csrf_posted = $_POST['csrf_token'] ?? '';
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf_posted)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch.']);
    exit;
}

// ── Confirmation phrase ───────────────────────────────────────
$confirm_word = $_POST['confirm_word'] ?? '';
if ($confirm_word !== 'RESTORE') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Type RESTORE in the confirmation field.']);
    exit;
}

// ── Password re-authentication ────────────────────────────────
$posted_pass = $_POST['password'] ?? '';
$admin_id    = (int)$_SESSION['admin_id'];
// Try both table names used in the codebase
$admin_row = query_one('SELECT id, password FROM admins WHERE id=? LIMIT 1', 'i', [$admin_id]);
if (!$admin_row) {
    $admin_row = query_one('SELECT id, password_hash AS password FROM admin_users WHERE id=? LIMIT 1', 'i', [$admin_id]);
}
if (!$admin_row || !password_verify($posted_pass, $admin_row['password'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Password verification failed.']);
    exit;
}

// ── Resolve SQL content ───────────────────────────────────────
$sql_content = '';
$source_name = '';

$source = $_POST['source'] ?? '';

if ($source === 'existing') {
    // Validate filename (basename only, no path traversal)
    $filename = basename($_POST['filename'] ?? '');
    if (!preg_match('/^svms_[\w\-]+\.(sql|sql\.gz|gz)$/', $filename)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid backup filename.']);
        exit;
    }
    $filepath = LOG_DIR . '/backups/' . $filename;
    if (!file_exists($filepath) || !is_file($filepath)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Backup file not found.']);
        exit;
    }
    $source_name = $filename;
    $raw = file_get_contents($filepath);
    if ($raw === false) {
        echo json_encode(['ok' => false, 'error' => 'Cannot read backup file.']);
        exit;
    }
    // Decompress if needed
    if (str_ends_with($filename, '.gz')) {
        $raw = @gzdecode($raw);
        if ($raw === false) {
            echo json_encode(['ok' => false, 'error' => 'Failed to decompress backup.']);
            exit;
        }
    }
    $sql_content = $raw;

} elseif ($source === 'upload') {
    if (empty($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'File upload error: ' . ($_FILES['sql_file']['error'] ?? 'no file')]);
        exit;
    }
    // Size limit 50 MB
    if ($_FILES['sql_file']['size'] > 50 * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(['ok' => false, 'error' => 'File too large. Maximum 50 MB.']);
        exit;
    }
    $tmp  = $_FILES['sql_file']['tmp_name'];
    $orig = $_FILES['sql_file']['name'];

    // Validate extension
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['sql', 'gz'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Only .sql or .sql.gz files allowed.']);
        exit;
    }

    // MIME check
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp);
    finfo_close($finfo);
    $allowed_mimes = ['text/plain', 'application/sql', 'application/x-sql', 'application/gzip', 'application/x-gzip', 'application/octet-stream'];
    if (!in_array($mime, $allowed_mimes, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid file MIME type: ' . $mime]);
        exit;
    }

    $raw = file_get_contents($tmp);
    if ($raw === false) {
        echo json_encode(['ok' => false, 'error' => 'Cannot read uploaded file.']);
        exit;
    }

    // Decompress .gz
    if ($ext === 'gz' || str_starts_with($raw, "\x1f\x8b")) {
        $raw = @gzdecode($raw);
        if ($raw === false) {
            echo json_encode(['ok' => false, 'error' => 'Failed to decompress file.']);
            exit;
        }
    }
    $sql_content = $raw;
    $source_name = basename($orig);

} else {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid source parameter.']);
    exit;
}

// ── First-1KB inspection — must look like SQL ─────────────────
$first_kb = substr($sql_content, 0, 1024);
if (
    !preg_match('/\b(CREATE|INSERT|DROP|SET|ALTER|LOCK|START|COMMIT)\b/i', $first_kb) ||
    preg_match('/<\?php/i', $first_kb)
) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'File does not appear to be a valid SQL dump.']);
    exit;
}

// ── Execute restore inside a transaction ─────────────────────
global $conn;

// Disable FK checks for the restore
$conn->query('SET FOREIGN_KEY_CHECKS=0');
$conn->query('START TRANSACTION');

// Split SQL into individual statements (naive but effective for dumps)
$sql_content = preg_replace('/--[^\n]*\n/', '', $sql_content);          // strip -- comments
$sql_content = preg_replace('#/\*.*?\*/#s', '', $sql_content);          // strip /* */ comments
$statements  = array_filter(
    array_map('trim', explode(';', $sql_content)),
    static fn($s) => $s !== ''
);

$error_msg = null;
foreach ($statements as $stmt_sql) {
    if (!$conn->query($stmt_sql)) {
        $error_msg = $conn->error . ' [SQL: ' . substr($stmt_sql, 0, 200) . ']';
        break;
    }
}

if ($error_msg) {
    $conn->query('ROLLBACK');
    $conn->query('SET FOREIGN_KEY_CHECKS=1');
    log_action('restore_failed', 0, json_encode(['file' => $source_name, 'error' => $error_msg]));
    echo json_encode(['ok' => false, 'error' => 'Restore failed: ' . $error_msg]);
    exit;
}

$conn->query('COMMIT');
$conn->query('SET FOREIGN_KEY_CHECKS=1');

// ── Kill all sessions to force re-login ───────────────────────
// Truncate sessions table if DB-backed; also destroy current PHP session.
@$conn->query("TRUNCATE TABLE sessions"); // ignore if table doesn't exist

log_action('restore', 0, json_encode([
    'file'        => $source_name,
    'restored_by' => $admin_id,
]));

// Invalidate current session (force re-login after restore)
session_unset();
session_destroy();

echo json_encode([
    'ok'      => true,
    'message' => 'Database restored successfully from "' . $source_name . '". All sessions have been terminated. Please log in again.',
    'logout'  => true,
]);
