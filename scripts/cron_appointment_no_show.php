<?php
/**
 * scripts/cron_appointment_no_show.php
 * Run every 15 minutes via cron:
 *   [star]/15 * * * * php /var/www/html/svms/scripts/cron_appointment_no_show.php
 *
 * Marks scheduled/confirmed appointments as 'no_show' when they are
 * more than 30 minutes past their scheduled time and the visitor
 * never arrived.  Notifies the host admin by email (non-fatal).
 */
define('SVMS_CRON', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_helpers.php';

// ── 1. Find overdue appointments ─────────────────────────────────────────────
$overdue = query_all(
    "SELECT a.id,
            a.visitor_name,
            COALESCE(a.person_to_meet, a.host_name) AS host_name,
            a.purpose,
            a.scheduled_at,
            a.department_id,
            d.name  AS dept_name
     FROM appointments a
     LEFT JOIN departments d ON d.id = a.department_id
     WHERE a.scheduled_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
       AND a.status IN ('scheduled','confirmed')
     ORDER BY a.scheduled_at ASC
     LIMIT 50",
    '', []
);

if (empty($overdue)) {
    $msg = '[' . date('Y-m-d H:i:s') . '] cron_appointment_no_show: nothing to process';
    echo $msg . "\n";
    error_log('[SVMS cron] ' . $msg);
    exit(0);
}

// ── 2. Process each overdue appointment ──────────────────────────────────────
$marked = 0;
foreach ($overdue as $a) {
    $appt_id = (int)$a['id'];

    // Race-condition guard: only update if still in an open status
    $stmt = $GLOBALS['conn']->prepare(
        "UPDATE appointments SET status='no_show' WHERE id=? AND status IN ('scheduled','confirmed')"
    );
    $stmt->bind_param('i', $appt_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) continue;   // another process already handled it
    $marked++;

    // ── 2a. Insert admin notification ────────────────────────────────────────
    $title   = 'No-show: ' . $a['visitor_name'];
    $message = $a['visitor_name'] . ' did not arrive for their appointment with '
        . $a['host_name'] . ' scheduled at '
        . date('d M Y g:i A', strtotime($a['scheduled_at'])) . '.';
    $link    = 'pages/appointments.php';

    $ns = $GLOBALS['conn']->prepare(
        "INSERT INTO notifications (type,title,message,link,recipient_id,is_read,created_at)
         VALUES ('no_show',?,?,?,NULL,0,NOW())"
    );
    $ns->bind_param('sss', $title, $message, $link);
    $ns->execute();
    $ns->close();

    // ── 2b. Log the action ────────────────────────────────────────────────────
    log_action('appointment_no_show', $appt_id, json_encode([
        'visitor_name' => $a['visitor_name'],
        'host_name'    => $a['host_name'],
        'scheduled_at' => $a['scheduled_at'],
        'dept_name'    => $a['dept_name'],
    ]));

    // ── 2c. Notify host admin by email (non-fatal) ────────────────────────────
    $host_name = $a['host_name'];
    $host_row  = query_one(
        "SELECT email FROM admins WHERE full_name LIKE ? AND is_active=1 LIMIT 1",
        's', ['%' . $host_name . '%']
    );

    if ($host_row && filter_var($host_row['email'], FILTER_VALIDATE_EMAIL)) {
        $site_name   = get_setting('site_name', SITE_NAME);
        $subject     = 'No-show: ' . $a['visitor_name'] . ' — ' . $site_name;
        $dept_label  = $a['dept_name'] ? ' (' . $a['dept_name'] . ')' : '';
        $sched_fmt   = date('d M Y g:i A', strtotime($a['scheduled_at']));

        $html = '<p>Hi ' . htmlspecialchars($host_name, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p><strong>' . htmlspecialchars($a['visitor_name'], ENT_QUOTES, 'UTF-8') . '</strong>'
            . ' did not arrive for their appointment with you' . htmlspecialchars($dept_label, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<ul>'
            . '<li><strong>Scheduled:</strong> ' . htmlspecialchars($sched_fmt, ENT_QUOTES, 'UTF-8') . '</li>'
            . '<li><strong>Purpose:</strong> '   . htmlspecialchars($a['purpose'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</li>'
            . '</ul>'
            . '<p>The appointment has been automatically marked as <strong>No-show</strong>.</p>'
            . '<p style="color:#6b7280;font-size:12px;">' . htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8') . '</p>';

        $text = "Hi {$host_name},\n\n"
            . "{$a['visitor_name']} did not arrive for their appointment with you{$dept_label}.\n"
            . "Scheduled: {$sched_fmt}\n"
            . "Purpose: " . ($a['purpose'] ?? 'N/A') . "\n\n"
            . "The appointment has been automatically marked as No-show.\n\n"
            . "-- {$site_name}";

        send_email(
            $host_row['email'],
            $subject,
            $html, $text,
            ['related_type' => 'appointment', 'related_id' => $appt_id]
        );
    }
}

$msg = '[' . date('Y-m-d H:i:s') . "] cron_appointment_no_show: marked={$marked}/" . count($overdue);
echo $msg . "\n";
error_log('[SVMS cron] ' . $msg);
