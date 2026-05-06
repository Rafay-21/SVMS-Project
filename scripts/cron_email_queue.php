<?php
/**
 * scripts/cron_email_queue.php — Email Queue Processor (Phase 4.2)
 * =================================================================
 * Picks up pending emails, attempts PHPMailer SMTP delivery,
 * applies exponential back-off, and marks rows sent/failed.
 *
 * Usage (cron):
 *   php /path/to/svms/scripts/cron_email_queue.php
 *
 * Or via web (Super Admin only):
 *   GET /svms/scripts/cron_email_queue.php
 *
 * Cron example (every 5 minutes):
 *   * /5 * * * * php /var/www/html/svms/scripts/cron_email_queue.php >> /var/log/svms_email.log 2>&1
 *
 * Day-of-appointment reminder cron (09:00 daily):
 *   0 9 * * * php /var/www/html/svms/scripts/cron_appointment_reminders.php
 *
 * Daily digest cron (18:00 daily):
 *   0 18 * * * php /var/www/html/svms/scripts/cron_daily_digest.php
 */
define('SVMS_CRON', true);

$is_cli = (PHP_SAPI === 'cli');

require_once __DIR__ . '/../config.php';

if (!$is_cli) {
    require_once __DIR__ . '/../includes/auth_check.php';
    if (empty($_SESSION['admin_id']) || role_slug($_SESSION['role_id'] ?? 0) !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden.']);
        exit;
    }
}

require_once __DIR__ . '/../includes/email_helpers.php';

global $conn;

const BATCH_SIZE  = 50;
const MAX_ATTEMPTS = 5;

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS `email_queue` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `to_email`     VARCHAR(320) NOT NULL,
    `subject`      VARCHAR(998) NOT NULL,
    `body_html`    MEDIUMTEXT   NOT NULL,
    `body_plain`   MEDIUMTEXT,
    `status`       ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
    `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_error`   TEXT,
    `scheduled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at`      DATETIME,
    `related_type` VARCHAR(64),
    `related_id`   INT UNSIGNED,
    PRIMARY KEY (`id`),
    INDEX `idx_status_sched` (`status`, `scheduled_at`),
    INDEX `idx_related`      (`related_type`, `related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$rows = query_all(
    "SELECT id, to_email, subject, body_html, body_plain, attempts
     FROM email_queue
     WHERE status IN ('pending','failed')
       AND attempts < ?
       AND scheduled_at <= NOW()
     ORDER BY scheduled_at ASC
     LIMIT " . BATCH_SIZE,
    'i', [MAX_ATTEMPTS]
);

if (empty($rows)) {
    _cron_log('No emails to process.');
    exit(0);
}

_cron_log('Processing ' . count($rows) . ' email(s)…');

$cfg        = _svms_smtp_config();
$sent_count = 0;
$fail_count = 0;

foreach ($rows as $row) {
    $id       = (int)$row['id'];
    $attempts = (int)$row['attempts'] + 1;

    // Mark as 'sending' to prevent duplicate processing in concurrent runs
    $affected = query_exec(
        "UPDATE email_queue SET status='sending' WHERE id=? AND status IN ('pending','failed')",
        'i', [$id]
    );
    if (!$affected) continue; // Another process grabbed it

    try {
        $msgId = _svms_smtp_send(
            $row['to_email'],
            $row['subject'],
            $row['body_html'],
            $row['body_plain'] ?: null,
            [],
            $cfg
        );

        query_exec(
            "UPDATE email_queue SET status='sent', attempts=?, sent_at=NOW(), last_error=NULL WHERE id=?",
            'ii', [$attempts, $id]
        );

        log_action('email_sent', $id, json_encode([
            'to'         => $row['to_email'],
            'subject'    => $row['subject'],
            'message_id' => $msgId,
        ]));

        _cron_log("[OK]  #{$id} → {$row['to_email']}");
        $sent_count++;

    } catch (\Throwable $e) {
        $errMsg    = substr($e->getMessage(), 0, 1000);
        $newStatus = ($attempts >= MAX_ATTEMPTS) ? 'failed' : 'pending';

        // Exponential back-off: 2^attempts minutes (capped at 24h)
        $backoff   = (int)min(pow(2, $attempts), 1440);
        $nextSched = date('Y-m-d H:i:s', time() + $backoff * 60);

        query_exec(
            "UPDATE email_queue SET status=?, attempts=?, last_error=?, scheduled_at=? WHERE id=?",
            'sissi', [$newStatus, $attempts, $errMsg, $nextSched, $id]
        );

        log_action('email_failed', $id, json_encode([
            'to'      => $row['to_email'],
            'subject' => $row['subject'],
            'error'   => $errMsg,
            'attempt' => $attempts,
        ]));

        _cron_log("[FAIL] #{$id} → {$row['to_email']} (attempt {$attempts}/" . MAX_ATTEMPTS . "): {$errMsg}");
        $fail_count++;
    }
}

if ($fail_count === 0) {
    update_setting('email_queue_notice', '0');
}

_cron_log("Done. Sent: {$sent_count}, Failed: {$fail_count}.");

// ── PDF report housekeeping (prune files > 30 days) ───────────────────────────
_cron_log('Running PDF report housekeeping…');
_prune_old_reports(30);
_cron_log('Housekeeping complete.');

exit(0);

/**
 * Delete PDF report files in LOG_DIR/reports/ that are older than $days days.
 */
function _prune_old_reports(int $days = 30): void
{
    $dir = LOG_DIR . '/reports';
    if (!is_dir($dir)) return;

    $cutoff  = time() - ($days * 86400);
    $deleted = 0;
    $errors  = 0;

    foreach (new DirectoryIterator($dir) as $file) {
        if ($file->isDot() || !$file->isFile()) continue;
        if (!in_array(strtolower($file->getExtension()), ['pdf'])) continue;
        if ($file->getMTime() < $cutoff) {
            if (@unlink($file->getRealPath())) {
                $deleted++;
            } else {
                $errors++;
                _cron_log('[WARN] Could not delete: ' . $file->getFilename());
            }
        }
    }

    _cron_log("PDF housekeeping: deleted=$deleted, errors=$errors (threshold={$days}d).");
}

function _cron_log(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $line . "\n";
    error_log('[SVMS email-cron] ' . $msg);
}
