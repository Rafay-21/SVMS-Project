<?php
/**
 * scripts/cron_appointment_reminders.php
 * Run every hour via cron:
 *   0 * * * * php /var/www/html/svms/scripts/cron_appointment_reminders.php
 *
 * Sends appointment_reminder emails to visitors whose appointment is
 * 1–2 hours from now and haven't already been reminded.
 */
define('SVMS_CRON', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_helpers.php';

// Fetch appointments starting in the next 1–2 hours that need a reminder
$appointments = query_all(
    "SELECT a.id, a.visitor_name, a.phone, a.email AS appt_email,
            COALESCE(a.person_to_meet, a.host_name) AS host_name,
            a.purpose, a.scheduled_at, a.department_id,
            d.name AS dept_name,
            v.email AS visitor_email
     FROM appointments a
     LEFT JOIN visitors v ON v.phone = a.phone
     LEFT JOIN departments d ON d.id = a.department_id
     WHERE a.scheduled_at BETWEEN DATE_ADD(NOW(), INTERVAL 1 HOUR)
                               AND DATE_ADD(NOW(), INTERVAL 2 HOUR)
       AND a.status IN ('scheduled','confirmed')
       AND (a.reminder_sent IS NULL OR a.reminder_sent = 0)
     ORDER BY a.scheduled_at ASC
     LIMIT 200",
    '', []
);

$sent = 0;
foreach ($appointments as $a) {
    // Prefer the email stored on the appointment row; fall back to visitors table
    $email = $a['appt_email'] ?: $a['visitor_email'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

    $dept = $a['dept_name'] ?? '';
    ['html' => $html, 'text' => $text] = render_email_template('appointment_reminder', [
        'visitor_name'   => $a['visitor_name'],
        'host_name'      => $a['host_name'],
        'purpose'        => $a['purpose'],
        'scheduled_time' => date('g:i A', strtotime($a['scheduled_at'])),
        'department'     => $dept,
        'site_name'      => get_setting('site_name', SITE_NAME),
        'year'           => date('Y'),
    ]);

    $result = send_email(
        $email,
        'Reminder: Your appointment is in 1 hour — ' . get_setting('site_name', SITE_NAME),
        $html, $text,
        ['related_type' => 'appointment', 'related_id' => (int)$a['id']]
    );

    if ($result['ok'] || $result['queued']) {
        query_exec("UPDATE appointments SET reminder_sent=1 WHERE id=?", 'i', [(int)$a['id']]);
        $sent++;
    }
}

$msg = '[' . date('Y-m-d H:i:s') . "] cron_appointment_reminders: sent={$sent}/" . count($appointments);
echo $msg . "\n";
error_log('[SVMS cron] ' . $msg);
