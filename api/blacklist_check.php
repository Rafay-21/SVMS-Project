<?php
/**
 * api/blacklist_check.php — Check whether a visitor is blacklisted.
 * Called from kiosk/registration without requiring an admin session.
 * Returns: {match:bool, blacklisted:bool (compat alias), severity, reason, blacklist_id, name}
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Rate limit: 60 requests/min per IP
$_bl_ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
if (!rl_check('bl:' . $_bl_ip, 60)) {
    rl_abort();
}

$cnic  = trim($_GET['cnic']  ?? '');
$phone = trim($_GET['phone'] ?? '');

if (!$cnic && !$phone) {
    echo json_encode(['match' => false, 'blacklisted' => false, 'error' => 'No cnic or phone provided.']);
    exit;
}

$entry = null;
if ($cnic !== '') {
    $entry = query_one(
        'SELECT id, name, reason, severity FROM blacklist WHERE cnic=? AND is_active=1 LIMIT 1',
        's', [$cnic]
    );
}
if (!$entry && $phone !== '') {
    $entry = query_one(
        'SELECT id, name, reason, severity FROM blacklist WHERE phone=? AND is_active=1 LIMIT 1',
        's', [$phone]
    );
}

if ($entry) {
    // Increment block_count atomically
    query_exec(
        'UPDATE blacklist SET block_count = block_count + 1 WHERE id = ?',
        'i', [(int)$entry['id']]
    );

    echo json_encode([
        'match'        => true,
        'blacklisted'  => true,          // backward-compat alias
        'blacklist_id' => (int)$entry['id'],
        'name'         => $entry['name'],
        'reason'       => $entry['reason'],
        'severity'     => $entry['severity'],
    ]);
} else {
    echo json_encode([
        'match'       => false,
        'blacklisted' => false,
        'blacklist_id'=> null,
        'name'        => null,
        'reason'      => null,
        'severity'    => null,
    ]);
}
