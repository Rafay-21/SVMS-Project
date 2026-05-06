<?php
/**
 * logout.php — Terminates the current admin session.
 *
 * ?switch=1  — Called from verify_otp.php "Use a different account" link;
 *               clears the pending-2FA state and redirects to login without
 *               a 'logged_out' banner.
 */
require_once __DIR__ . '/config.php';

// Audit log before we destroy session data
if (isset($_SESSION['admin_id'])) {
    $action = isset($_GET['switch']) ? 'switch_account' : 'logout';
    log_action($action, (int)$_SESSION['admin_id']);
}

// Clear all session data
$_SESSION = [];

// Expire the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Clear the "remember me" cookie (if it was set)
if (isset($_COOKIE['svms_remember'])) {
    setcookie('svms_remember', '', time() - 42000, '/', '', false, true);
}

session_destroy();

// Redirect
if (isset($_GET['switch'])) {
    header('Location: ' . BASE_URL . 'pages/login.php');
} else {
    header('Location: ' . BASE_URL . 'pages/login.php?msg=logged_out');
}
exit;
