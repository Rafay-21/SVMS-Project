<?php
/**
 * includes/sidebar.php
 * Fixed left-side navigation with permission-gated items.
 * Sections auto-hide when ALL their children are hidden.
 */

$_current_page = basename($_SERVER['PHP_SELF']);

/**
 * Render a single sidebar nav-link, or nothing if the user lacks $permission.
 */
function nav_link(string $href, string $icon, string $label, string $permission = '', ?string $badge = null): void {
    global $_current_page;
    if ($permission && !can($permission)) return;
    $file   = basename($href);
    $active = ($file === $_current_page) ? ' active' : '';
    echo '<a href="' . BASE_URL . 'pages/' . htmlspecialchars($href, ENT_QUOTES) . '" class="sidebar-link' . $active . '">';
    echo   '<i class="bi bi-' . $icon . '" aria-hidden="true"></i>';
    echo   '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    if ($badge !== null) echo '<span class="sidebar-link-badge">' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</a>';
}

/**
 * Return true if the current user has ANY of the given permissions.
 * Used to decide whether a section header should render at all.
 */
function section_visible(array $permissions): bool {
    foreach ($permissions as $p) {
        if ($p === '' || can($p)) return true;
    }
    return false;
}
?>
<aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">

  <nav class="sidebar-body" aria-label="Primary navigation">

    <!-- MAIN — always visible (dashboard has no permission gate) -->
    <div class="sidebar-section">
      <div class="sidebar-section-label"><?= e(t('nav.section_main')) ?></div>
      <?php nav_link('dashboard.php', 'speedometer2', t('nav.dashboard')); ?>
    </div>

    <!-- VISITORS -->
    <?php if (section_visible(['register_visitor', 'checkin_visitor', 'view_history', 'manage_appointments'])): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-label"><?= e(t('nav.section_visitors')) ?></div>
      <?php nav_link('register_visitor.php', 'person-plus-fill',  t('nav.register_visitor'),   'register_visitor'); ?>
      <?php nav_link('checkin_checkout.php', 'door-open-fill',    t('nav.checkin'),             'checkin_visitor'); ?>
      <?php nav_link('visitor_history.php',  'clock-history',     t('nav.history'),             'view_history'); ?>
      <?php nav_link('appointments.php',     'calendar-check',    t('nav.appointments'),        'manage_appointments'); ?>
    </div>
    <?php endif; ?>

    <!-- SECURITY -->
    <?php if (section_visible(['manage_blacklist', 'view_audit_log', 'manage_emergency'])): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-label"><?= e(t('nav.section_security')) ?></div>
      <?php nav_link('blacklist.php',  'slash-circle-fill',        t('nav.blacklist'),  'manage_blacklist'); ?>
      <?php nav_link('audit_log.php',  'journal-text',             t('nav.audit_log'),  'view_audit_log'); ?>
      <?php nav_link('emergency.php',  'exclamation-octagon-fill', t('nav.emergency'),  'manage_emergency'); ?>
    </div>
    <?php endif; ?>

    <!-- INSIGHTS -->
    <?php if (section_visible(['view_analytics', 'view_feedback'])): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-label"><?= e(t('nav.section_insights')) ?></div>
      <?php nav_link('analytics.php',     'bar-chart-line-fill',  t('nav.analytics'), 'view_analytics'); ?>
      <?php nav_link('feedback_view.php', 'chat-left-text-fill',  t('nav.feedback'),  'view_feedback'); ?>
    </div>
    <?php endif; ?>

    <!-- ADMIN -->
    <?php if (section_visible(['manage_users', 'manage_custom_fields', 'manage_settings', 'run_backup'])): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-label"><?= e(t('nav.section_admin')) ?></div>
      <?php nav_link('users.php',         'people-fill',           t('nav.users'),         'manage_users'); ?>
      <?php nav_link('custom_fields.php', 'sliders',               t('nav.custom_fields'), 'manage_custom_fields'); ?>
      <?php nav_link('settings.php',      'gear-fill',             t('nav.settings'),      'manage_settings'); ?>
      <?php nav_link('email_queue.php',   'envelope-fill',         t('nav.email_queue'),   'manage_settings'); ?>
      <?php nav_link('backup.php',        'cloud-arrow-down-fill', t('nav.backup'),        'run_backup'); ?>
    </div>
    <?php endif; ?>

    <!-- PROFILE — always visible -->
    <div class="sidebar-section">
      <div class="sidebar-section-label"><?= e(t('nav.section_account')) ?></div>
      <?php nav_link('profile.php', 'person-circle', t('nav.profile')); ?>
    </div>

  </nav>

  <!-- Kiosk Mode shortcut -->
  <div style="padding:12px 16px;border-top:1px solid rgba(255,255,255,.08);flex-shrink:0;">
    <a href="<?= BASE_URL ?>kiosk/" class="sidebar-link" style="border-radius:var(--radius-sm);">
      <i class="bi bi-tablet-landscape-fill" aria-hidden="true"></i>
      <span>Kiosk Mode</span>
    </a>
  </div>

</aside>
