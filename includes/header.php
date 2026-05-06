<?php
/**
 * includes/header.php
 * Outputs the complete HTML <head> and top navbar.
 * Variables that calling pages may set before including:
 *   $page_title       — overrides <title>
 *   $page_uses_charts — set to true to include Chart.js
 */

$_lang  = current_lang();
$_theme_mode = $_SESSION['theme'] ?? 'system';
$_dir   = is_rtl() ? 'rtl' : 'ltr';
$_title = isset($page_title) ? e($page_title) . ' — ' . SITE_NAME : SITE_NAME;
$_admin_name   = e($_SESSION['admin_name']   ?? 'Admin');
$_admin_avatar = e($_SESSION['admin_avatar'] ?? BASE_URL . 'assets/img/default-avatar.svg');
?>
<!DOCTYPE html>
<html lang="<?= e($_lang) ?>" dir="<?= e($_dir) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#1a3c5e">
  <meta name="csrf-token" content="<?= csrf_token_for_js() ?>">
  <title><?= $_title ?></title>

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/img/logo.svg">
  <link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/img/icons/apple-touch-icon.png">

  <!-- PWA Manifest + Apple meta -->
  <link rel="manifest" href="<?= BASE_URL ?>manifest.json">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="SVMS">
  <meta name="msapplication-TileColor" content="#1B3A5C">

  <!-- Inter from Google CDN (swap Noto Nastaliq for local font when files are placed) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Design System CSS (order matters) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tokens.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/base.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/layout.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/forms.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/fonts.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/themes.css">
  <?php if (is_rtl()): ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/rtl-overrides.css">
  <?php endif; ?>

  <!-- Chart.js (only when page needs charts) -->
  <?php if (!empty($page_uses_charts)): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" defer></script>
  <?php endif; ?>

  <!-- Anti-FOUC: apply theme from localStorage BEFORE first paint -->
  <script>
  (function(){
    try{
      var m=localStorage.getItem('svms_theme_mode')||'system';
      var t=m==='dark'?'dark':m==='light'?'light':
        (window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
      var r=document.documentElement;
      r.setAttribute('data-theme',t);
      r.setAttribute('data-theme-mode',m);
      r.classList.add('preload');
    }catch(e){}
  })();
  </script>

  <!-- i18n + globals for JS modules -->
  <script>
    window.SVMS_LANG_STRINGS = <?= json_encode($LANG ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.__i18n = null; // populated by i18n.js after DOM ready
    window.BASE_URL = <?= json_encode(BASE_URL) ?>;
    window.SVMS_LANG = <?= json_encode($_lang) ?>;
    window.SVMS_RTL  = <?= json_encode(is_rtl()) ?>;
    window.AN_CONFIG = window.AN_CONFIG || {};
  </script>
</head>
<body>

<!-- Skip to main content -->
<a class="skip-link" href="#main-content">Skip to main content</a>

<!-- Toast container -->
<div id="toast-container" aria-live="polite" aria-atomic="true"></div>

<?php
$_em_mode = get_setting('emergency_mode', 'normal');
if ($_em_mode !== 'normal'):
  $_em_active_row = query_one("SELECT COUNT(*) cnt FROM visit_log WHERE status='checked_in'");
  $_em_active_cnt = (int)($_em_active_row['cnt'] ?? 0);
  $_em_is_evac    = $_em_mode === 'evacuation';
  $_em_color      = $_em_is_evac ? 'var(--warning)' : 'var(--danger)';
  $_em_bg         = $_em_is_evac ? '#fffbeb' : '#fef2f2';
  $_em_icon       = $_em_is_evac ? 'bi-exclamation-triangle-fill' : 'bi-lock-fill';
  $_em_label      = strtoupper($_em_mode);
?>
<div id="emergency-banner" role="alert"
     style="background:<?= $_em_bg ?>;border-bottom:2px solid <?= $_em_color ?>;color:<?= $_em_color ?>;
            padding:10px 20px;display:flex;align-items:center;gap:12px;font-size:13px;font-weight:600;
            position:sticky;top:0;z-index:9999;">
  <i class="bi <?= $_em_icon ?>" style="font-size:18px;animation:pulse 1s infinite alternate;"></i>
  <span style="flex:1;">&#9888; <?= $_em_label ?> MODE ACTIVE — <?= $_em_active_cnt ?> visitor(s) currently on premises.</span>
  <?php if (can('emergency_control')): ?>
  <a href="<?= BASE_URL ?>pages/emergency.php"
     style="background:<?= $_em_color ?>;color:#fff;padding:4px 12px;border-radius:6px;text-decoration:none;font-size:12px;">
    Control Panel
  </a>
  <?php endif; ?>
  <button onclick="document.getElementById('emergency-banner').style.display='none'"
          aria-label="Dismiss emergency banner"
          style="background:none;border:none;cursor:pointer;color:<?= $_em_color ?>;font-size:18px;line-height:1;padding:0 0 0 8px;">&#215;</button>
</div>
<style>@keyframes pulse{from{opacity:.6}to{opacity:1}}</style>
<?php endif; ?>

<!-- ── Top Navbar ──────────────────────────────────────────── -->
<nav class="navbar" role="navigation" aria-label="Main navigation">

  <!-- Hamburger (mobile) -->
  <button class="navbar-hamburger" id="sidebar-toggle" aria-label="Toggle sidebar" aria-expanded="false" aria-controls="sidebar">
    <i class="bi bi-list"></i>
  </button>

  <!-- Brand -->
  <a href="<?= BASE_URL ?>" class="navbar-brand">
    <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="SVMS Logo" width="36" height="36">
    <span class="navbar-brand-name"><?= SITE_NAME ?></span>
  </a>

  <!-- Smart Search -->
  <div class="navbar-search" role="search">
    <i class="bi bi-search search-icon" aria-hidden="true"></i>
    <input
      type="search"
      id="smart-search"
      placeholder="Search visitors, badges…"
      autocomplete="off"
      aria-label="Smart search"
      aria-owns="search-results"
      aria-autocomplete="list">
    <div id="search-results" class="navbar-search-results" role="listbox"></div>
  </div>

  <!-- Actions -->
  <div class="navbar-actions">

    <!-- Notifications -->
    <?php
    // Server-side unread count to avoid FOUC on badge
    $notif_unread = 0;
    try {
        $notif_unread = (int)(query_one(
            "SELECT COUNT(*) AS c FROM notifications
             WHERE (recipient_id=? OR recipient_id IS NULL)
               AND (visible_to_role_id IS NULL OR visible_to_role_id=?)
               AND (type != 'blacklist_alert' OR visible_to_role_id=?)
               AND is_read=0",
            'iii', [$_SESSION['admin_id'], $_SESSION['role_id'] ?? 0, $_SESSION['role_id'] ?? 0]
        )['c'] ?? 0);
    } catch (\Throwable $ignored) {}
    $notif_sound = get_setting('notif_sound', '0');
    ?>
    <div style="position:relative;" id="notif-wrapper">
      <button class="navbar-icon-btn" id="notification-bell"
              aria-label="Notifications" aria-haspopup="true" aria-expanded="false"
              data-notif-sound="<?= htmlspecialchars($notif_sound, ENT_QUOTES) ?>"
              style="position:relative;">
        <i class="bi bi-bell-fill"></i>
        <span id="notif-badge"
              style="position:absolute;top:-4px;right:-5px;min-width:18px;height:18px;padding:0 4px;
                     background:var(--danger);color:#fff;font-size:10px;font-weight:700;border-radius:9px;
                     display:<?= $notif_unread > 0 ? 'flex' : 'none' ?>;align-items:center;
                     justify-content:center;line-height:1;"><?= $notif_unread > 0 ? min(99, $notif_unread) : '' ?></span>
      </button>

      <!-- Notification Panel (360px) -->
      <div id="notification-panel" role="dialog" aria-label="Notifications"
           style="display:none;position:absolute;top:calc(100% + 14px);right:0;width:360px;
                  background:var(--card);border:1px solid var(--border);
                  border-radius:16px;
                  box-shadow:0 12px 32px rgba(0,0,0,.08);z-index:1200;overflow:hidden;">
        <!-- Caret arrow pointing at bell -->
        <style>
          #notification-panel::before {
            content:''; position:absolute; top:-8px; right:14px; width:0; height:0;
            border-left:8px solid transparent; border-right:8px solid transparent;
            border-bottom:8px solid var(--border);
          }
          #notification-panel::after {
            content:''; position:absolute; top:-7px; right:14px; width:0; height:0;
            border-left:8px solid transparent; border-right:8px solid transparent;
            border-bottom:8px solid var(--card);
          }
          /* Notification item styles */
          @keyframes notif-slide-in { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }
          @keyframes notif-pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.2)} }
          .notif-pulse { animation: notif-pulse 200ms ease-out; }
          .notif-item { display:flex;align-items:flex-start;gap:10px;padding:10px 16px;
                        min-height:56px;cursor:pointer;transition:background .12s;
                        text-decoration:none;color:inherit;border-left:4px solid transparent; }
          .notif-item:hover { background:var(--bg-secondary); }
          .notif-item.unread { border-left-color:var(--secondary); background:rgba(46,117,182,.04); }
          .notif-item.unread .notif-title { font-weight:700; }
          .notif-dot { width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:4px; }
          .notif-content { flex:1;min-width:0; }
          .notif-title { font-size:13px;font-weight:500;color:var(--text);
                         white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
          .notif-message { font-size:12px;color:var(--text-muted);
                           white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
          .notif-time { font-size:11px;color:var(--text-muted);flex-shrink:0;margin-left:8px;padding-top:2px; }
          .notif-empty { padding:24px;text-align:center;color:var(--text-muted);font-size:13px; }
          .notif-new { animation: notif-slide-in 200ms ease-out; }
        </style>
        <!-- Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;
                    border-bottom:1px solid var(--border);">
          <span style="font-size:14px;font-weight:700;color:var(--text);"><?= e(t('nav.notifications')) ?></span>
          <div style="display:flex;align-items:center;gap:12px;">
            <a href="#" id="notif-mark-all"
               style="font-size:12px;color:var(--secondary);text-decoration:none;"><?= e(t('common.mark_all_read', [])) ?></a>
            <a href="<?= BASE_URL ?>pages/settings.php#notifications"
               style="font-size:14px;color:var(--text-muted);line-height:1;" title="Notification settings">
              <i class="bi bi-gear-fill"></i>
            </a>
          </div>
        </div>
        <!-- Body -->
        <div id="notif-list-body"
             style="max-height:440px;overflow-y:auto;scrollbar-width:thin;">
          <!-- Populated by notifications.js -->
        </div>
        <!-- Footer -->
        <div style="padding:10px 16px;border-top:1px solid var(--border);text-align:center;">
          <a href="<?= BASE_URL ?>pages/notifications.php"
             style="font-size:12px;color:var(--secondary);text-decoration:none;font-weight:600;">
            <?= e(t('common.see_all')) ?>
          </a>
        </div>
      </div>
    </div>

    <?php
    // Email queue indicator (Super Admin only)
    if (can('manage_settings')):
        $eq_pending = 0; $eq_failed = 0;
        try {
            $r = query_one("SELECT
                SUM(status='pending') AS p,
                SUM(status='failed')  AS f
                FROM email_queue", '', []);
            $eq_pending = (int)($r['p'] ?? 0);
            $eq_failed  = (int)($r['f'] ?? 0);
        } catch (\Throwable $ignored) {}
        $eq_total = $eq_pending + $eq_failed;
    ?>
    <?php if ($eq_total > 0): ?>
    <a href="<?= BASE_URL ?>pages/email_queue.php"
       class="navbar-icon-btn" title="Email queue: <?= $eq_pending ?> pending, <?= $eq_failed ?> failed"
       style="position:relative;">
      <i class="bi bi-envelope-fill" style="<?= $eq_failed > 0 ? 'color:var(--danger)' : 'color:var(--warning)' ?>"></i>
      <span style="position:absolute;top:-4px;right:-5px;min-width:18px;height:18px;padding:0 4px;
                   background:<?= $eq_failed > 0 ? 'var(--danger)' : 'var(--warning)' ?>;color:#fff;
                   font-size:10px;font-weight:700;border-radius:9px;display:flex;
                   align-items:center;justify-content:center;line-height:1;"><?= $eq_total ?></span>
    </a>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Language Switcher -->
    <div class="navbar-dropdown">
      <button class="navbar-icon-btn" id="lang-toggle" aria-label="<?= e(t('settings.language')) ?>"
              aria-haspopup="true" aria-expanded="false">
        <i class="bi bi-globe2"></i>
      </button>
      <div id="lang-menu" class="navbar-dropdown-menu" role="menu"
           aria-label="<?= e(t('settings.language')) ?>">
        <button class="navbar-dropdown-item <?= $_lang === 'en' ? 'active' : '' ?>"
                data-lang-btn="en" role="menuitem">
          <span>🇬🇧</span> <?= e(t('settings.language_en')) ?>
          <?php if ($_lang === 'en'): ?><i class="bi bi-check" style="margin-inline-start:auto;"></i><?php endif; ?>
        </button>
        <button class="navbar-dropdown-item <?= $_lang === 'ur' ? 'active' : '' ?>"
                data-lang-btn="ur" role="menuitem">
          <span>🇵🇰</span> <?= e(t('settings.language_ur')) ?>
          <?php if ($_lang === 'ur'): ?><i class="bi bi-check" style="margin-inline-start:auto;"></i><?php endif; ?>
        </button>
      </div>
    </div>

    <!-- Theme: quick-toggle (icon morphs) + 3-way submenu -->
    <div class="navbar-dropdown" style="position:relative;">
      <!-- Quick toggle icon -->
      <button class="navbar-icon-btn" id="theme-toggle" aria-label="<?= e(t('settings.theme')) ?>"
              aria-haspopup="true" aria-expanded="false">
        <i class="bi <?= $_theme_mode === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill' ?>"></i>
      </button>
      <!-- Three-way submenu (opened by long-press / separate trigger) -->
      <div id="theme-menu" class="navbar-dropdown-menu" role="menu"
           aria-label="<?= e(t('settings.theme')) ?>"
           style="min-width:170px;">
        <div style="padding:6px 14px 4px;font-size:11px;font-weight:700;
                    color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;">
          <?= e(t('settings.theme')) ?>
        </div>
        <button class="navbar-dropdown-item" data-theme-mode-btn="light" role="menuitem"
                style="display:flex;align-items:center;gap:10px;">
          <i class="bi bi-sun" style="font-size:14px;color:var(--warning);"></i>
          <?= e(t('settings.theme_light')) ?>
          <i class="bi bi-check tm-check" style="margin-inline-start:auto;display:none;color:var(--primary);"></i>
        </button>
        <button class="navbar-dropdown-item" data-theme-mode-btn="dark" role="menuitem"
                style="display:flex;align-items:center;gap:10px;">
          <i class="bi bi-moon-stars" style="font-size:14px;color:var(--accent);"></i>
          <?= e(t('settings.theme_dark')) ?>
          <i class="bi bi-check tm-check" style="margin-inline-start:auto;display:none;color:var(--primary);"></i>
        </button>
        <button class="navbar-dropdown-item" data-theme-mode-btn="system" role="menuitem"
                style="display:flex;align-items:center;gap:10px;">
          <i class="bi bi-laptop" style="font-size:14px;color:var(--text-muted);"></i>
          <?= e(t('settings.theme_system')) ?>
          <i class="bi bi-check tm-check" style="margin-inline-start:auto;display:none;color:var(--primary);"></i>
        </button>
      </div>
    </div>

    <!-- Admin Avatar & Name -->
    <div class="navbar-dropdown">
      <button class="navbar-avatar" id="user-menu-btn" aria-label="User menu" aria-haspopup="true" aria-expanded="false">
        <img src="<?= $_admin_avatar ?>" alt="<?= $_admin_name ?>" class="avatar avatar-sm">
        <span class="navbar-avatar-name"><?= $_admin_name ?></span>
        <i class="bi bi-chevron-down" style="font-size:11px;"></i>
      </button>
      <div id="user-menu" class="navbar-dropdown-menu" role="menu" aria-label="User options">
        <a href="<?= BASE_URL ?>pages/profile.php" class="dropdown-item" role="menuitem">
          <i class="bi bi-person-circle"></i> <?= e(t('nav.profile')) ?>
        </a>
        <a href="<?= BASE_URL ?>pages/settings.php" class="dropdown-item" role="menuitem">
          <i class="bi bi-gear"></i> <?= e(t('nav.settings')) ?>
        </a>
        <div class="dropdown-divider"></div>
        <button type="button" id="install-app-btn" class="dropdown-item" role="menuitem"
                style="width:100%;text-align:start;background:none;border:none;cursor:pointer;font-family:inherit;">
          <i class="bi bi-phone-fill"></i> <?= e(t('nav.install_app')) ?>
        </button>
        <div class="dropdown-divider"></div>
        <a href="<?= BASE_URL ?>logout.php" class="dropdown-item danger" role="menuitem">
          <i class="bi bi-box-arrow-right"></i> <?= e(t('nav.logout')) ?>
        </a>
      </div>
    </div>

  </div>
</nav>

<!-- Flash messages -->
<div style="padding:0 24px;" aria-live="polite" aria-atomic="true">
<?php render_flash(); ?>
</div>

<!-- Sidebar backdrop (mobile) -->
<div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<!-- Main content wrapper -->
<main class="main" id="main-content">
