<?php
/**
 * scripts/cron_daily_digest.php
 * Run daily at 18:00 via cron:
 *   0 18 * * * php /var/www/html/svms/scripts/cron_daily_digest.php
 *
 * Sends daily_digest emails to all super_admin accounts.
 */
define('SVMS_CRON', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_helpers.php';

$today = date('Y-m-d');

// ── Gather stats ────────────────────────────────────────────────────────────
$total_row = query_one(
    "SELECT COUNT(*) AS cnt FROM visit_log WHERE DATE(check_in_time)=?", 's', [$today]
);
$total_visits = (int)($total_row['cnt'] ?? 0);

$ci_row = query_one(
    "SELECT COUNT(*) AS cnt FROM visit_log WHERE DATE(check_in_time)=? AND status='checked_in'",
    's', [$today]
);
$checked_in = (int)($ci_row['cnt'] ?? 0);

$appt_row = query_one(
    "SELECT COUNT(*) AS cnt FROM appointments WHERE DATE(scheduled_at)=?", 's', [$today]
);
$appointments = (int)($appt_row['cnt'] ?? 0);

$bl_row = query_one(
    "SELECT COUNT(*) AS cnt FROM audit_logs WHERE action='checkin_blocked' AND DATE(created_at)=?",
    's', [$today]
);
$blocked_attempts = (int)($bl_row['cnt'] ?? 0);

// Peak hour
$peak_row = query_one(
    "SELECT HOUR(check_in_time) AS hr, COUNT(*) AS cnt
     FROM visit_log WHERE DATE(check_in_time)=?
     GROUP BY hr ORDER BY cnt DESC LIMIT 1",
    's', [$today]
);
$peak_hour  = '';
$peak_count = 0;
if ($peak_row) {
    $h = (int)$peak_row['hr'];
    $peak_hour  = date('g A', mktime($h, 0, 0)) . '–' . date('g A', mktime($h + 1, 0, 0));
    $peak_count = (int)$peak_row['cnt'];
}

// ── Super admin recipients ────────────────────────────────────────────────────
$super_admins = query_all(
    "SELECT a.email, a.full_name
     FROM admins a
     JOIN roles r ON r.id = a.role_id
     WHERE r.slug='super_admin' AND a.is_active=1",
    '', []
);

if (empty($super_admins)) {
    echo "[" . date('Y-m-d H:i:s') . "] cron_daily_digest: No super_admins found.\n";
    exit(0);
}

$site_name    = get_setting('site_name', SITE_NAME);
$dashboard_url = BASE_URL . 'pages/dashboard.php';
$sent = 0;

foreach ($super_admins as $admin) {
    ['html' => $html, 'text' => $text] = render_email_template('daily_digest', [
        'site_name'       => $site_name,
        'report_date'     => date('D, d M Y'),
        'total_visits'    => $total_visits,
        'checked_in'      => $checked_in,
        'appointments'    => $appointments,
        'blocked_attempts'=> $blocked_attempts,
        'peak_hour'       => $peak_hour,
        'peak_count'      => $peak_count,
        'dashboard_url'   => $dashboard_url,
        'year'            => date('Y'),
    ]);

    $result = send_email(
        $admin['email'],
        $site_name . ' — Daily Digest ' . date('d M Y'),
        $html, $text,
        ['related_type' => 'daily_digest', 'related_id' => 0]
    );

    if ($result['ok']) $sent++;
}

$msg = '[' . date('Y-m-d H:i:s') . "] cron_daily_digest: sent={$sent}/" . count($super_admins);
echo $msg . "\n";
error_log('[SVMS cron] ' . $msg);
