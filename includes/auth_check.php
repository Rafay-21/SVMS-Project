<?php
/**
 * includes/auth_check.php
 * Enforce authentication on every protected page.
 * Include at the very top of each protected page, after config.php.
 */

// 1. Not logged in at all
if (!isset($_SESSION['admin_id'])) {
    ob_end_clean();
    header('Location: ' . BASE_URL . 'pages/login.php?msg=unauthorized');
    exit;
}

// 2. Two-factor auth pending
if (ENABLE_2FA && isset($_SESSION['pending_2fa']) && !isset($_SESSION['2fa_verified'])) {
    ob_end_clean();
    header('Location: ' . BASE_URL . 'pages/verify_otp.php');
    exit;
}

// 3. Idle timeout via last_activity
$idle_limit = SESSION_LIFETIME_HOURS * 3600;
if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $idle_limit) {
    session_unset();
    session_destroy();
    ob_end_clean();
    header('Location: ' . BASE_URL . 'pages/login.php?msg=session_expired');
    exit;
}

// 4. Session binding: IP /24 subnet + User-Agent fingerprint.
//    Protects against session token theft across different clients.
$_sa_ip_raw = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
$_sa_ip24   = implode('.', array_slice(explode('.', $_sa_ip_raw), 0, 3));
$_sa_ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);
$_sa_sig    = hash_hmac('sha256', $_sa_ip24 . '|' . $_sa_ua, APP_KEY);

if (!isset($_SESSION['session_sig'])) {
    $_SESSION['session_sig'] = $_sa_sig;
} elseif (!hash_equals((string)$_SESSION['session_sig'], $_sa_sig)) {
    session_unset();
    session_destroy();
    ob_end_clean();
    header('Location: ' . BASE_URL . 'pages/login.php?msg=session_expired');
    exit;
}

// 5. Ensure permissions are cached in session (lazy-load if missing)
if (!isset($_SESSION['permissions'])) {
    cache_permissions();
}

// 6. Refresh last activity timestamp
$_SESSION['last_activity'] = time();
