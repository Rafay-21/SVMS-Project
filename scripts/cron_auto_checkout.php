<?php
/**
 * scripts/cron_auto_checkout.php
 * Run via cron: php /path/to/svms/scripts/cron_auto_checkout.php
 * Recommended schedule: every 15 minutes
 * Or triggered manually via api/run_auto_checkout.php
 */
define('SVMS_CRON', true);
require_once __DIR__ . '/../config.php';

// Find all visits that have been checked in longer than MAX_VISIT_HOURS
$threshold = MAX_VISIT_HOURS * 3600;
$visits = query_all(
    "SELECT id, visitor_id, check_in_time FROM visit_log
     WHERE status = 'checked_in'
       AND TIMESTAMPDIFF(SECOND, check_in_time, NOW()) >= ?",
    'i', [$threshold]
);

$count = 0;
foreach ($visits as $v) {
    query_exec(
        "UPDATE visit_log SET check_out_time=NOW(), status='auto_checkout' WHERE id=?",
        'i', [(int)$v['id']]
    );

    // Insert a system notification
    query_exec(
        "INSERT INTO notifications (type, title, message, link, recipient_id, is_read, created_at)
         VALUES ('auto_checkout', 'Auto Check-Out', ?, ?, NULL, 0, NOW())",
        'ss',
        [
            'Visitor auto-checked-out after ' . MAX_VISIT_HOURS . ' hours (Visit #' . (int)$v['id'] . ')',
            BASE_URL . 'pages/visitor_detail.php?id=' . (int)$v['id'],
        ]
    );

    log_action('auto_checkout', (int)$v['id'], json_encode([
        'visitor_id'       => $v['visitor_id'],
        'check_in'         => $v['check_in_time'],
        'threshold_hours'  => MAX_VISIT_HOURS,
    ]));
    $count++;
}

$msg = '[' . date('Y-m-d H:i:s') . '] cron_auto_checkout: ' . $count . ' visit(s) auto-checked-out.';
echo $msg . PHP_EOL;
if (!is_dir(LOG_DIR)) mkdir(LOG_DIR, 0750, true);
error_log($msg . PHP_EOL, 3, LOG_DIR . '/cron.log');
