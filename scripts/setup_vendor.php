<?php
/**
 * scripts/setup_vendor.php
 * Run once from a browser: http://localhost/svms/scripts/setup_vendor.php
 * Downloads phpqrcode v1.0.4 into /vendor/phpqrcode/qrlib_real.php.
 */
require_once __DIR__ . '/../config.php';

// Must be run from CLI or by a logged-in super_admin
$is_cli = PHP_SAPI === 'cli';
if (!$is_cli) {
    require_once __DIR__ . '/../includes/auth_check.php';
    if (role_slug((int)($_SESSION['role_id'] ?? 0)) !== 'super_admin') {
        die('Access denied.');
    }
}

header('Content-Type: text/plain; charset=utf-8');

$dest = __DIR__ . '/../vendor/phpqrcode/qrlib_real.php';
if (file_exists($dest) && filesize($dest) > 50000) {
    echo "phpqrcode already downloaded (" . number_format(filesize($dest)) . " bytes).\n";
    exit;
}

$mirrors = [
    'https://raw.githubusercontent.com/t0k4rt/phpqrcode/master/qrlib.php',
];

foreach ($mirrors as $url) {
    echo "Trying: $url\n";
    $ctx = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]);
    $src = @file_get_contents($url, false, $ctx);
    if ($src && strlen($src) > 50000) {
        if (file_put_contents($dest, $src)) {
            echo "Success! Downloaded " . number_format(strlen($src)) . " bytes to vendor/phpqrcode/qrlib_real.php\n";
            echo "phpqrcode is ready.\n";
            exit;
        } else {
            echo "Failed to write file (check directory permissions).\n";
        }
    } else {
        echo "Download failed or file too small.\n";
    }
}

echo "\nManual installation:\n";
echo "1. Download https://raw.githubusercontent.com/t0k4rt/phpqrcode/master/qrlib.php\n";
echo "2. Save as: " . realpath(__DIR__ . '/../vendor/phpqrcode/') . DIRECTORY_SEPARATOR . "qrlib_real.php\n";
