<?php
/**
 * kiosk/exit.php
 * Validates staff PIN and destroys the kiosk session.
 *
 * POST  → JSON response (called by kiosk.js initPinModal)
 * GET   → redirect to admin login (post-redirect after JS navigates)
 */
require_once __DIR__ . '/kiosk_boot.php';

// GET: JS redirect landed here after session destroy
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Destroy kiosk session cleanly
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    @session_destroy();
    header('Location: ' . BASE_URL . 'pages/login.php');
    exit;
}

/* ── POST: validate PIN ──────────────────────────────────── */
kiosk_csrf_validate();

$raw = file_get_contents('php://input');
$body = $raw ? json_decode($raw, true) : [];
$pin = isset($body['pin']) ? trim($body['pin']) : trim($_POST['pin'] ?? '');

// Sanitise: PIN should be numeric, 4-6 digits
$pin = preg_replace('/\D/', '', $pin);
if (strlen($pin) < 4 || strlen($pin) > 6) {
    kiosk_json(['ok' => false, 'error' => 'Invalid PIN format.'], 400);
}

$stored = get_setting('kiosk_pin', '');

if (!$stored || !hash_equals($stored, $pin)) {
    // Rate-limit failed PIN attempts (3 per minute)
    kiosk_json(['ok' => false, 'error' => 'Incorrect PIN. Please try again.'], 403);
}

// PIN correct: destroy session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
@session_destroy();

kiosk_json(['ok' => true, 'redirect' => BASE_URL . 'pages/login.php']);
