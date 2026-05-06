<?php
/**
 * SVMS Configuration File — Version 2.0
 * ======================================
 * Constants defined in this file:
 *
 * DATABASE:
 *   DB_HOST        — MySQL hostname
 *   DB_USER        — MySQL username
 *   DB_PASS        — MySQL password
 *   DB_NAME        — Database name
 *   DB_CHARSET     — Connection charset
 *
 * APPLICATION:
 *   SITE_NAME      — Display name of the application
 *   BASE_URL       — Absolute base URL (trailing slash)
 *   UPLOAD_DIR     — Absolute filesystem path for uploads
 *   LOG_DIR        — Absolute filesystem path for logs
 *
 * SMTP / EMAIL:
 *   SMTP_HOST          — SMTP server hostname
 *   SMTP_USER          — SMTP username
 *   SMTP_PASS          — SMTP password
 *   SMTP_PORT          — SMTP port (default 587 / STARTTLS)
 *   SMTP_FROM_EMAIL    — From address for outbound mail
 *   SMTP_FROM_NAME     — From display name
 *
 * BUSINESS LOGIC:
 *   MAX_VISIT_HOURS          — Auto-checkout threshold (hours)
 *   BADGE_PREFIX             — Prefix for generated badge numbers
 *   SESSION_LIFETIME_HOURS   — Idle session expiry
 *   OTP_EXPIRY_MINUTES       — Two-factor OTP validity window
 *   ENABLE_2FA               — Toggle two-factor authentication
 *   DEFAULT_LANG             — Fallback UI language code
 *   DEFAULT_THEME            — Fallback colour theme (light|dark)
 */

// ── Database ─────────────────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'svms_db');
define('DB_CHARSET', 'utf8mb4');

// ── Application ───────────────────────────────────────────────────────────────
define('SITE_NAME',   'Smart Visitor Management System');
define('BASE_URL',    'http://localhost/svms/');
define('UPLOAD_DIR',  __DIR__ . '/assets/uploads/');
define('LOG_DIR',     __DIR__ . '/logs');

// ── SMTP (placeholders — fill before production) ─────────────────────────────
define('SMTP_HOST',       '');
define('SMTP_USER',       '');
define('SMTP_PASS',       '');
define('SMTP_PORT',       587);
define('SMTP_FROM_EMAIL', 'no-reply@svms.com');
define('SMTP_FROM_NAME',  'SVMS Notifications');

// ── Business Logic ────────────────────────────────────────────────────────────
define('MAX_VISIT_HOURS',        8);
define('BADGE_PREFIX',           'VIS');
define('SESSION_LIFETIME_HOURS', 2);
define('OTP_EXPIRY_MINUTES',     10);
define('ENABLE_2FA',             false); // Set to true in production
define('DEFAULT_LANG',           'en');
define('DEFAULT_THEME',          'light');

// ── Environment flag ──────────────────────────────────────────────────────────
// Set IS_DEV = false in production to suppress error display and enable HSTS.
define('IS_DEV', (DB_HOST === 'localhost' && DB_USER === 'root'));

// ── Encryption Key bootstrap ──────────────────────────────────────────────────
// /config/keys.php is gitignored and auto-generated on first run.
$_svms_keys_file = __DIR__ . '/config/keys.php';
if (!file_exists($_svms_keys_file)) {
    if (!is_dir(dirname($_svms_keys_file))) {
        mkdir(dirname($_svms_keys_file), 0700, true);
    }
    $_svms_enc_key = bin2hex(random_bytes(32)); // 256-bit AES key
    file_put_contents(
        $_svms_keys_file,
        "<?php\n// Auto-generated — DO NOT commit this file.\n" .
        "define('ENCRYPTION_KEY', '{$_svms_enc_key}');\n"
    );
    @chmod($_svms_keys_file, 0600);
    unset($_svms_enc_key);
}
require_once $_svms_keys_file;
unset($_svms_keys_file);

// ── Application Secret Key (used for HMAC tokens — change in production) ─────
// Generate with: php -r "echo bin2hex(random_bytes(32));"
define('APP_KEY', 'svms-change-in-production-' . substr(sha1(DB_NAME . DB_USER . __FILE__), 0, 20));

// ── Timezone ──────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Karachi');

// ── Error Reporting ───────────────────────────────────────────────────────────
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0750, true);
}
error_reporting(E_ALL);
if (IS_DEV) {
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
}
ini_set('log_errors', 1);
ini_set('error_log',  LOG_DIR . '/php_errors.log');

// ── Session Configuration ────────────────────────────────────────────────────
$_svms_is_https = (!IS_DEV && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/svms/',
    'domain'   => '',
    'secure'   => $_svms_is_https,
    'httponly' => true,
    'samesite' => 'Strict',
]);
unset($_svms_is_https);
ini_set('session.use_strict_mode', 1);
session_name('SVMS_SESSID');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token bootstrap
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Database Connection ───────────────────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_errno) {
    $log_msg = date('[Y-m-d H:i:s] ') . 'DB connection failed: ' . $conn->connect_error . PHP_EOL;
    error_log($log_msg, 3, LOG_DIR . '/db_errors.log');
    die('A database connection error occurred. Please try again later.');
}

$conn->set_charset(DB_CHARSET);

// ── Core Includes ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/includes/db_functions.php';
require_once __DIR__ . '/includes/rate_limiter.php';
require_once __DIR__ . '/includes/crypto.php';
require_once __DIR__ . '/includes/error_handler.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/rbac.php';
require_once __DIR__ . '/includes/flash.php';
