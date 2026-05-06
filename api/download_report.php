<?php
/**
 * api/download_report.php — Secure PDF Report Download Proxy
 *
 * GET ?file=report_Ymd_His_N.pdf
 *
 * - Requires authentication
 * - Super admins can download any report; others only their own
 * - Validates filename strictly to prevent path traversal
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$filename = basename($_GET['file'] ?? '');

// Strict filename validation: report_YYYYMMDD_HHMMSS_N.pdf
if (!preg_match('/^report_\d{8}_\d{6}_\d+\.pdf$/', $filename)) {
    http_response_code(400);
    exit('Invalid filename.');
}

$filePath = LOG_DIR . '/reports/' . $filename;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('Report not found or has been deleted.');
}

// Non-super-admins may only download their own reports
$adminId  = (int)$_SESSION['admin_id'];
$roleSlug = role_slug((int)($_SESSION['role_id'] ?? 0));
if ($roleSlug !== 'super_admin') {
    // Filename format: report_YYYYMMDD_HHMMSS_{adminId}.pdf
    preg_match('/^report_\d{8}_\d{6}_(\d+)\.pdf$/', $filename, $m);
    $fileOwnerId = isset($m[1]) ? (int)$m[1] : -1;
    if ($fileOwnerId !== $adminId) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// Serve the file
$size = filesize($filePath);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . $size);
header('Cache-Control: private, no-store');
header('Pragma: no-cache');

readfile($filePath);
exit;
