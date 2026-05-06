<?php
/**
 * scripts/regenerate_badges.php
 * Idempotent badge regeneration script.
 * Loops all visit_log rows and (re)generates badge PNGs for those that are missing.
 *
 * Usage:
 *   Browser: http://localhost/svms/scripts/regenerate_badges.php  (requires super_admin login)
 *   CLI:     php scripts/regenerate_badges.php [--force]
 *            Add --force to regenerate ALL badges (even existing ones).
 */

define('SCRIPT_CLI', PHP_SAPI === 'cli');

if (!SCRIPT_CLI) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/auth_check.php';
    if (role_slug((int)$_SESSION['role_id']) !== 'super_admin') {
        http_response_code(403);
        die('Forbidden: Super Admin only.');
    }
} else {
    require_once __DIR__ . '/../config.php';
}

require_once __DIR__ . '/../includes/badge_helpers.php';

$force = SCRIPT_CLI
    ? in_array('--force', $argv ?? [], true)
    : !empty($_GET['force']);

if (!SCRIPT_CLI) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
          <title>Regenerate Badges — ' . SITE_NAME . '</title>
          <style>body{font-family:monospace;padding:24px;max-width:900px;margin:auto;background:#f8fafc;}
          pre{background:#1e293b;color:#e2e8f0;padding:16px;border-radius:8px;font-size:13px;line-height:1.6;}
          h1{color:#1a3c5e;} .ok{color:#22c55e;} .err{color:#ef4444;} .skip{color:#94a3b8;}</style></head>
          <body><h1>&#128738; Regenerate Visitor Badges</h1><pre>';
    ob_start();
    register_shutdown_function(function() {
        $out = ob_get_clean();
        echo htmlspecialchars($out, ENT_QUOTES, 'UTF-8');
        echo '</pre></body></html>';
    });
}

$out = function(string $line) {
    if (SCRIPT_CLI) {
        echo $line . "\n";
    } else {
        echo $line . "\n";
        @ob_flush(); @flush();
    }
};

$out('Starting badge regeneration [' . date('Y-m-d H:i:s') . ']');
$out('Force mode: ' . ($force ? 'YES' : 'NO (skip existing)'));
$out('');

$visits = query_all(
    "SELECT vl.id FROM visit_log vl ORDER BY vl.id ASC"
);
$total   = count($visits);
$out("Total visit_log rows: {$total}");
$out('');

$generated = 0;
$skipped   = 0;
$failed    = 0;

foreach ($visits as $row) {
    $id = (int)$row['id'];
    $badgePath = UPLOAD_DIR . 'badges/badge_' . $id . '.png';

    if (!$force && file_exists($badgePath)) {
        $skipped++;
        if ($total <= 200) $out("  [skip] visit_log #{$id} — badge exists");
        continue;
    }

    try {
        $result = generate_badge($id);
        if ($result && file_exists($result)) {
            $generated++;
            $out("  [ ok ] visit_log #{$id} — {$result}");
        } else {
            $failed++;
            $out("  [FAIL] visit_log #{$id} — generate_badge() returned null");
        }
    } catch (Throwable $e) {
        $failed++;
        $out("  [ERR ] visit_log #{$id} — " . $e->getMessage());
    }
}

$out('');
$out("Done. Generated: {$generated}, Skipped: {$skipped}, Failed: {$failed}");
$out('[' . date('Y-m-d H:i:s') . ']');
