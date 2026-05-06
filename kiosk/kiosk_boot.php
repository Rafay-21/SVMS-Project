<?php
/**
 * kiosk/kiosk_boot.php
 * Bootstrap for kiosk pages.
 *
 * Must be the FIRST thing included in every kiosk/*.php file.
 * Sets up the SVMS_KIOSK session (separate from the admin SVMS_SESSID),
 * then loads config.php (which detects session already started and skips
 * its own session_start()).
 */

// Kiosk session BEFORE config.php calls session_name('SVMS_SESSID')
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_name('SVMS_KIOSK');
    session_start();
}

// Mark kiosk session as active
$_SESSION['kiosk_active'] = true;

// Rotate CSRF token once per session if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../config.php';

// Security headers for all kiosk pages
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

/* ── Helpers shared by all kiosk pages ──────────────────────── */

/**
 * Simple IP-based rate limiter.
 * Uses PHP temp dir; max $max requests per $window seconds.
 *
 * @return bool  true if under limit, false if rate-limited
 */
function kiosk_rate_limit(int $max = 30, int $window = 60): bool
{
    $ip   = filter_var($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
    $file = sys_get_temp_dir() . '/svms_krl_' . md5($ip) . '.json';
    $now  = time();

    $data = [];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        $data = $raw ? (json_decode($raw, true) ?: []) : [];
    }

    // Purge old timestamps
    $data = array_values(array_filter($data, fn($t) => ($now - $t) < $window));
    $data[] = $now;

    file_put_contents($file, json_encode($data), LOCK_EX);

    return count($data) <= $max;
}

/**
 * Send a JSON response and exit. For kiosk API endpoints.
 */
function kiosk_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

/**
 * Validate CSRF token for a kiosk AJAX/POST request.
 * On failure, sends JSON error and exits.
 */
function kiosk_csrf_validate(): void
{
    $token  = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!hash_equals($stored, $token)) {
        kiosk_json(['ok' => false, 'error' => 'Security token mismatch.'], 403);
    }
}

/**
 * Emit common kiosk page <head> and opening <body> tags.
 *
 * @param string $title     <title> content
 * @param bool   $backBtn   Show back button in navbar
 * @param string $backUrl   URL for back button
 */
function kiosk_head(string $title, bool $backBtn = false, string $backUrl = ''): void
{
    $lang  = preg_replace('/[^a-z]/', '', $_COOKIE['svms_lang'] ?? DEFAULT_LANG);
    $lang  = in_array($lang, ['en','ur']) ? $lang : DEFAULT_LANG;
    $theme = preg_replace('/[^a-z]/', '', $_COOKIE['svms_theme'] ?? DEFAULT_THEME);
    $dir   = ($lang === 'ur') ? 'rtl' : 'ltr';
    ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <meta name="robots" content="noindex,nofollow">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — Kiosk — <?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/img/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tokens.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/kiosk.css">
  <script>
    window.KIOSK_BASE = <?= json_encode(BASE_URL) ?>;
    window.KIOSK_CSRF = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    (function(){var c=document.cookie.match(/(?:^|; )svms_theme=([^;]*)/);if(c)document.documentElement.setAttribute('data-theme',decodeURIComponent(c[1]));})();
  </script>
</head>
<body class="kiosk-body" id="kiosk-body">

<!-- Navbar -->
<nav class="kiosk-navbar">
  <div style="display:flex;align-items:center;gap:12px;">
    <?php if ($backBtn && $backUrl): ?>
    <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>"
       style="color:#fff;font-size:24px;text-decoration:none;padding:8px;margin:-8px;border-radius:8px;display:flex;align-items:center;"
       aria-label="Back">&#8592;</a>
    <?php endif; ?>
    <img src="<?= BASE_URL ?>assets/img/logo.svg" width="32" height="32" alt="" style="flex-shrink:0;">
    <span style="font-weight:700;font-size:17px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;"><?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
  <div class="kiosk-clock" id="kiosk-clock" aria-live="polite"></div>
</nav>

<!-- Idle reset overlay -->
<div class="kiosk-idle-overlay" id="kiosk-idle-overlay" role="alertdialog" aria-label="Session timeout">
  <i class="bi bi-clock-history" style="font-size:80px;opacity:.5;margin-bottom:20px;"></i>
  <h2 style="font-size:28px;font-weight:800;margin-bottom:8px;">Still there?</h2>
  <p style="font-size:20px;opacity:.8;margin-bottom:4px;">Returning to home in</p>
  <div class="kiosk-idle-countdown" id="kiosk-idle-count">5</div>
  <p style="font-size:16px;opacity:.6;">Tap anywhere to continue</p>
</div>

<!-- Staff PIN trigger -->
<button class="kiosk-staff-btn" id="kiosk-staff-btn" aria-label="Staff access" title="Staff">
  <i class="bi bi-person-badge"></i>
</button>

<!-- PIN modal -->
<div class="kiosk-pin-modal" id="kiosk-pin-modal" role="dialog" aria-modal="true" aria-label="Staff PIN">
  <div class="kiosk-pin-box">
    <h3 style="font-size:22px;font-weight:800;color:#1a3c5e;margin-bottom:6px;">Staff Access</h3>
    <p style="font-size:14px;color:#64748b;margin-bottom:16px;">Enter your 4-digit PIN to exit kiosk mode</p>
    <div class="kiosk-pin-dots" id="kiosk-pin-dots">
      <div class="kiosk-pin-dot"></div>
      <div class="kiosk-pin-dot"></div>
      <div class="kiosk-pin-dot"></div>
      <div class="kiosk-pin-dot"></div>
    </div>
    <p class="pin-error" style="color:#ef4444;font-size:13px;min-height:18px;margin-bottom:12px;"></p>
    <div class="kiosk-numpad" style="max-width:280px;margin:0 auto;">
      <?php for ($r=0;$r<3;$r++): for ($c=1;$c<=3;$c++): $n=$r*3+$c; ?>
      <button class="kiosk-key" data-digit="<?= $n ?>" type="button"><?= $n ?></button>
      <?php endfor; endfor; ?>
      <button class="kiosk-key kiosk-key-del" data-action="del" type="button">⌫</button>
      <button class="kiosk-key" data-digit="0" type="button">0</button>
      <button class="kiosk-key kiosk-key-mod" data-action="pin-cancel" type="button" style="font-size:13px;">Cancel</button>
    </div>
  </div>
</div>

<div class="kiosk-page" id="kiosk-main">
<?php
}

/**
 * Emit kiosk page closing tags + scripts.
 *
 * @param array $extraJs  Array of inline JS strings to include.
 */
function kiosk_foot(array $extraJs = []): void
{
    ?>
</div><!-- /#kiosk-main -->

<script src="<?= BASE_URL ?>assets/js/kiosk.js"></script>
<?php foreach ($extraJs as $js): ?>
<script><?= $js ?></script>
<?php endforeach; ?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (window.KIOSK) {
      KIOSK.initPinModal();
    }
  });
</script>
</body>
</html>
<?php
}
