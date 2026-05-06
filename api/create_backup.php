<?php
/**
 * api/create_backup.php — Create a database backup
 * POST /api/create_backup.php
 *
 * Body (JSON): { csrf_token }
 * Returns: { ok, filename, size_bytes, message }
 *
 * Strategy 1 (preferred): mysqldump --single-transaction --quick --routines
 * Strategy 2 (PHP fallback): dump via INFORMATION_SCHEMA + SELECT * INSERTs
 *
 * Requires: Super Admin (manage_settings + role_slug='super_admin')
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('manage_settings');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────
$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$token = $body['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch.']);
    exit;
}

// ── Ensure backup directory ───────────────────────────────────
$backup_dir = LOG_DIR . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0750, true);
}

$timestamp = date('Ymd_His');
$admin_id  = (int)$_SESSION['admin_id'];

// ── Try mysqldump first ───────────────────────────────────────
function _svms_try_mysqldump(string $backup_dir, string $timestamp): array|false
{
    $filename = 'svms_' . $timestamp . '.sql';
    $filepath = $backup_dir . $filename;

    $host = escapeshellarg(DB_HOST);
    $user = escapeshellarg(DB_USER);
    $db   = escapeshellarg(DB_NAME);

    // Build password argument safely
    $pass_args = DB_PASS !== ''
        ? '--password=' . escapeshellarg(DB_PASS) . ' '
        : '';

    $cmd = 'mysqldump'
         . ' --host='              . $host
         . ' --user='              . $user
         . ' '                     . $pass_args
         . ' --single-transaction'
         . ' --quick'
         . ' --routines'
         . ' --triggers'
         . ' --set-gtid-purged=OFF'
         . ' '                     . $db
         . ' > '                   . escapeshellarg($filepath)
         . ' 2>&1';

    $output = []; $code = 0;
    exec($cmd, $output, $code);

    if ($code !== 0 || !file_exists($filepath) || filesize($filepath) < 100) {
        @unlink($filepath);
        return false;
    }

    return ['filename' => $filename, 'filepath' => $filepath];
}

// ── PHP-based fallback dump ───────────────────────────────────
function _svms_php_dump(string $backup_dir, string $timestamp): array|false
{
    global $conn;

    $filename = 'svms_' . $timestamp . '_php.sql';
    $filepath = $backup_dir . $filename;

    $fp = fopen($filepath, 'w');
    if (!$fp) return false;

    fwrite($fp, "-- SVMS PHP-generated backup\n");
    fwrite($fp, "-- Created: " . date('Y-m-d H:i:s') . "\n");
    fwrite($fp, "-- Database: " . DB_NAME . "\n\n");
    fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
    fwrite($fp, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

    // Get all tables
    $tables_res = $conn->query("SHOW TABLES");
    if (!$tables_res) { fclose($fp); return false; }

    while ($trow = $tables_res->fetch_row()) {
        $table = $trow[0];
        $safe_table = '`' . str_replace('`', '``', $table) . '`';

        // CREATE TABLE
        $create_res = $conn->query("SHOW CREATE TABLE $safe_table");
        if ($create_res) {
            $create_row = $create_res->fetch_row();
            fwrite($fp, "DROP TABLE IF EXISTS $safe_table;\n");
            fwrite($fp, $create_row[1] . ";\n\n");
        }

        // Data
        $data_res = $conn->query("SELECT * FROM $safe_table");
        if ($data_res && $data_res->num_rows > 0) {
            fwrite($fp, "INSERT INTO $safe_table VALUES\n");
            $rows_written = 0;
            while ($row = $data_res->fetch_row()) {
                $values = array_map(static function ($v) use ($conn) {
                    if ($v === null) return 'NULL';
                    return "'" . $conn->real_escape_string($v) . "'";
                }, $row);
                $sep = $rows_written > 0 ? ",\n" : '';
                fwrite($fp, $sep . '(' . implode(',', $values) . ')');
                $rows_written++;
            }
            fwrite($fp, ";\n\n");
        }
    }

    fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fp);

    // gzip the file
    $gz_filename = $filename . '.gz';
    $gz_filepath = $backup_dir . $gz_filename;
    $gz = gzopen($gz_filepath, 'wb9');
    if ($gz) {
        $plain = file_get_contents($filepath);
        gzwrite($gz, $plain);
        gzclose($gz);
        @unlink($filepath); // remove uncompressed copy
        return ['filename' => $gz_filename, 'filepath' => $gz_filepath];
    }

    return ['filename' => $filename, 'filepath' => $filepath];
}

// ── Auto-prune old backups (keep last 20 OR <30 days, whichever is more) ────
function _svms_prune_backups(string $backup_dir, int $keep = 20, int $max_age_days = 30): void
{
    global $conn;

    $files = glob($backup_dir . 'svms_*.{sql,gz}', GLOB_BRACE);
    if (!$files) return;

    usort($files, static fn($a, $b) => filemtime($b) - filemtime($a));

    $cutoff = time() - ($max_age_days * 86400);

    foreach ($files as $i => $f) {
        $is_old   = filemtime($f) < $cutoff;
        $is_extra = $i >= $keep;

        if ($is_old && $is_extra) {
            $bn = basename($f);
            @unlink($f);
            // Mark deleted in backups table if present
            $conn->query(
                "UPDATE backups SET status='deleted' WHERE filename='" .
                $conn->real_escape_string($bn) . "'"
            );
        }
    }
}

// ── Execute backup ────────────────────────────────────────────
$result = _svms_try_mysqldump($backup_dir, $timestamp);
$type   = 'mysqldump';
if (!$result) {
    $result = _svms_php_dump($backup_dir, $timestamp);
    $type   = 'php';
}

if (!$result) {
    // Record failure
    $conn->query(
        "INSERT INTO backups (filename, size_bytes, created_by, type, status, error, created_at)
         VALUES ('', 0, $admin_id, 'manual', 'error', 'Both mysqldump and PHP dump failed.', NOW())"
    );
    echo json_encode(['ok' => false, 'error' => 'Backup failed. Check server permissions and mysqldump availability.']);
    exit;
}

$size = filesize($result['filepath']);

// ── Record in backups table ───────────────────────────────────
$stmt = $conn->prepare(
    'INSERT INTO backups (filename, size_bytes, created_by, type, status, created_at) VALUES (?,?,?,?,?,NOW())'
);
if ($stmt) {
    $t = 'manual';
    $s = 'ok';
    $stmt->bind_param('siiss', $result['filename'], $size, $admin_id, $t, $s);
    $stmt->execute();
    $stmt->close();
}

log_action('backup_create', 0, json_encode([
    'file'    => $result['filename'],
    'size'    => $size,
    'method'  => $type,
]));

// ── Prune old backups ─────────────────────────────────────────
_svms_prune_backups($backup_dir);

echo json_encode([
    'ok'         => true,
    'filename'   => $result['filename'],
    'size_bytes' => $size,
    'message'    => 'Backup created successfully (' . $type . ').',
]);
