<?php
/**
 * api/test_email.php — Send a test email using the provided (unsaved) SMTP settings.
 * POST — Super Admin only.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/email_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

// Validate CSRF (header or form field)
$csrf_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$stored      = $_SESSION['csrf_token'] ?? '';
if (!hash_equals($stored, $csrf_header)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch.']);
    exit;
}

$to = filter_var(trim($_POST['test_to'] ?? ''), FILTER_VALIDATE_EMAIL);
if (!$to) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid recipient address.']);
    exit;
}

// Build ephemeral config from POST values (not yet saved)
$cfg = [
    'host'       => sanitize($_POST['smtp_host']       ?? ''),
    'port'       => max(1, min(65535, (int)($_POST['smtp_port'] ?? 587))),
    'user'       => sanitize($_POST['smtp_user']       ?? ''),
    'pass'       => $_POST['smtp_pass']               ?? '',
    'from_email' => sanitize($_POST['smtp_from_email'] ?? ''),
    'from_name'  => sanitize($_POST['smtp_from_name']  ?? SITE_NAME),
    'security'   => sanitize($_POST['smtp_security']   ?? 'tls'),
];

// Fall back to stored password if none provided
if ($cfg['pass'] === '') {
    $cfg['pass'] = get_setting('smtp_pass', '');
}

if (empty($cfg['host']) || empty($cfg['from_email'])) {
    echo json_encode(['ok' => false, 'error' => 'SMTP Host and From Email are required.']);
    exit;
}

// Render a simple branded test email body
['html' => $html, 'text' => $text] = render_email_template('visitor_checkin', [
    'visitor_name'   => $_SESSION['admin_name'] ?? 'Admin',
    'badge_number'   => 'TEST-0000',
    'person_to_meet' => 'SMTP Test',
    'purpose'        => 'Email configuration test',
    'check_in_time'  => date('d M Y g:i A'),
    'department'     => '',
    'site_name'      => $cfg['from_name'],
    'year'           => date('Y'),
]);

// Replace template subject for test
$subject = '✅ SVMS Test Email — ' . date('d M Y g:i A');
// Prepend test banner
$html = '<div style="background:#fef3c7;padding:12px 20px;font-family:sans-serif;font-size:13px;border-bottom:3px solid #f59e0b;">
  <strong>⚗ Test Email</strong> — This is a configuration test sent from SVMS. Your SMTP is working correctly!
</div>' . $html;

try {
    _svms_smtp_send($to, $subject, $html, $text, [], $cfg);
    log_action('email_test_sent', 0, json_encode([
        'to'   => $to,
        'host' => $cfg['host'],
        'port' => $cfg['port'],
        'by'   => (int)$_SESSION['admin_id'],
    ]));
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    $msg = $e->getMessage();
    log_action('email_test_failed', 0, json_encode([
        'to'    => $to,
        'host'  => $cfg['host'],
        'error' => $msg,
        'by'    => (int)$_SESSION['admin_id'],
    ]));
    echo json_encode(['ok' => false, 'error' => $msg]);
}
