<?php
/**
 * scripts/cron_daily_backup.php
 * Automated daily database backup.
 *
 * Run via cron (recommended: 3 AM daily):
 *   0 3 * * * php /path/to/svms/scripts/cron_daily_backup.php >> /dev/null 2>&1
 *
 * Or with explicit log:
 *   0 3 * * * php /path/to/svms/scripts/cron_daily_backup.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

define('SVMS_CRON', true);
require_once __DIR__ . '/../config.php';

$backup_dir = LOG_DIR . '/backups/';
if (!is_dir($backup_dir)) mkdir($backup_dir, 0750, true);

$timestamp = date('Ymd_His');

/* ── Attempt mysqldump ────────────────────────────────────── */
function _cron_try_mysqldump(string $backup_dir, string $timestamp): array|false
{
    $filename = 'svms_' . $timestamp . '_auto.sql';
    $filepath = $backup_dir . $filename;

    $host     = escapeshellarg(DB_HOST);
    $user     = escapeshellarg(DB_USER);
    $pass_arg = DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) . ' ' : '';
    $db       = escapeshellarg(DB_NAME);

    $cmd = 'mysqldump'
         . ' --host=' . $host
         . ' --user=' . $user
         . ' '        . $pass_arg
         . ' --single-transaction --quick --routines --triggers --set-gtid-purged=OFF'
         . ' '        . $db
         . ' > '      . escapeshellarg($filepath)
         . ' 2>&1';

    $out = []; $code = 0;
    exec($cmd, $out, $code);

    if ($code !== 0 || !file_exists($filepath) || filesize($filepath) < 100) {
        @unlink($filepath);
        return false;
    }
    return ['filename' => $filename, 'filepath' => $filepath];
}

/* ── PHP fallback dump ───────────────────────────────────── */
function _cron_php_dump(string $backup_dir, string $timestamp): array|false
{
    global $conn;

    $filename = 'svms_' . $timestamp . '_auto_php.sql';
    $filepath = $backup_dir . $filename;

    $fp = fopen($filepath, 'w');
    if (!$fp) return false;

    fwrite($fp, "-- SVMS automated PHP backup\n");
    fwrite($fp, "-- Created: " . date('Y-m-d H:i:s') . "\n\n");
    fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

    $tables_res = $conn->query('SHOW TABLES');
    if (!$tables_res) { fclose($fp); return false; }

    while ($trow = $tables_res->fetch_row()) {
        $table      = $trow[0];
        $safe_table = '`' . str_replace('`', '``', $table) . '`';

        $create_res = $conn->query("SHOW CREATE TABLE $safe_table");
        if ($create_res) {
            $cr = $create_res->fetch_row();
            fwrite($fp, "DROP TABLE IF EXISTS $safe_table;\n" . $cr[1] . ";\n\n");
        }

        $data_res = $conn->query("SELECT * FROM $safe_table");
        if ($data_res && $data_res->num_rows > 0) {
            fwrite($fp, "INSERT INTO $safe_table VALUES\n");
            $i = 0;
            while ($row = $data_res->fetch_row()) {
                $vals = array_map(static fn($v) =>
                    $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'",
                    $row
                );
                fwrite($fp, ($i > 0 ? ",\n" : '') . '(' . implode(',', $vals) . ')');
                $i++;
            }
            fwrite($fp, ";\n\n");
        }
    }

    fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fp);

    // gzip
    $gz_filename = $filename . '.gz';
    $gz_filepath = $backup_dir . $gz_filename;
    $gz = gzopen($gz_filepath, 'wb9');
    if ($gz) {
        gzwrite($gz, file_get_contents($filepath));
        gzclose($gz);
        @unlink($filepath);
        return ['filename' => $gz_filename, 'filepath' => $gz_filepath];
    }
    return ['filename' => $filename, 'filepath' => $filepath];
}

/* ── Prune old backups ───────────────────────────────────── */
function _cron_prune(string $backup_dir, int $keep = 20, int $max_age_days = 30): void
{
    global $conn;
    $files = glob($backup_dir . 'svms_*.{sql,gz}', GLOB_BRACE);
    if (!$files) return;
    usort($files, static fn($a, $b) => filemtime($b) - filemtime($a));
    $cutoff = time() - ($max_age_days * 86400);
    foreach ($files as $i => $f) {
        if (filemtime($f) < $cutoff && $i >= $keep) {
            $bn = basename($f);
            @unlink($f);
            $conn->query(
                "UPDATE backups SET status='deleted' WHERE filename='" .
                $conn->real_escape_string($bn) . "'"
            );
        }
    }
}

/* ── Run ─────────────────────────────────────────────────── */
$result = _cron_try_mysqldump($backup_dir, $timestamp);
$method = 'mysqldump';
if (!$result) {
    $result = _cron_php_dump($backup_dir, $timestamp);
    $method = 'php';
}

$ts = '[' . date('Y-m-d H:i:s') . '] cron_daily_backup: ';

if (!$result) {
    $msg = $ts . 'FAILED — both mysqldump and PHP fallback failed.';
    // Record failure in backups table
    $conn->query(
        "INSERT INTO backups (filename, size_bytes, created_by, type, status, error, created_at)
         VALUES ('', 0, NULL, 'automated', 'error', 'Both strategies failed.', NOW())"
    );
} else {
    $size = filesize($result['filepath']);
    // Record success
    $stmt = $conn->prepare(
        'INSERT INTO backups (filename, size_bytes, created_by, type, status, created_at) VALUES (?,?,NULL,?,?,NOW())'
    );
    if ($stmt) {
        $t = 'automated'; $s = 'ok';
        $stmt->bind_param('sis s', $result['filename'], $size, $t, $s);
        $stmt->close();
    }
    // Simpler direct insert for CLI context
    $fn  = $conn->real_escape_string($result['filename']);
    $conn->query(
        "INSERT INTO backups (filename, size_bytes, created_by, type, status, created_at)
         VALUES ('$fn', $size, NULL, 'automated', 'ok', NOW())"
    );
    _cron_prune($backup_dir);
    $msg = $ts . 'OK — ' . $result['filename'] . ' (' . round($size / 1024, 1) . ' KB) via ' . $method;
}

echo $msg . PHP_EOL;
error_log($msg . PHP_EOL, 3, LOG_DIR . '/cron.log');
