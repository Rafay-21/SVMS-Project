<?php
/**
 * includes/email_helpers.php  — SVMS v2.0 / Phase 4.2
 * =====================================================
 * PHPMailer-backed SMTP email with queue + retry support.
 *
 * Public API
 * ----------
 * send_email(string $to, string $subject, string $htmlBody,
 *            ?string $plainBody = null, array $opts = []): array
 *     $opts keys:
 *       'queue'        (bool)   — force queue instead of sync send
 *       'attachments'  (array)  — [['path'=>…,'name'=>…], …]
 *       'related_type' (string) — for email_queue.related_type
 *       'related_id'   (int)    — for email_queue.related_id
 *       'scheduled_at' (string) — ISO datetime for deferred delivery
 *     Returns: ['ok'=>bool, 'message_id'=>?string, 'queued'=>bool, 'error'=>?string]
 *
 * queue_email(...same args...)                    — always queues
 * render_email_template(string $name, array $vars): array{html:string,text:string}
 *
 * Internal
 * --------
 * _svms_smtp_send()   — raw PHPMailer call, throws on failure
 * _svms_enqueue()     — INSERT into email_queue
 * _svms_smtp_config() — read settings table, fall back to constants
 */

// ── PHPMailer bootstrap ────────────────────────────────────────────────────────
// Composer autoload (preferred)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Manual fallback: vendor/phpmailer/phpmailer/src/
    $pm_src = __DIR__ . '/../vendor/phpmailer/phpmailer/src/';
    foreach (['Exception.php', 'OAuth.php', 'OAuthTokenProvider.php', 'SMTP.php', 'PHPMailer.php'] as $f) {
        if (file_exists($pm_src . $f)) require_once $pm_src . $f;
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

// ── SMTP config reader ────────────────────────────────────────────────────────
function _svms_smtp_config(): array
{
    return [
        'host'       => get_setting('smtp_host',       defined('SMTP_HOST')       ? SMTP_HOST       : ''),
        'port'       => (int)(get_setting('smtp_port', defined('SMTP_PORT')       ? (string)SMTP_PORT : '587')),
        'user'       => get_setting('smtp_user',       defined('SMTP_USER')       ? SMTP_USER       : ''),
        'pass'       => decrypt_setting(get_setting('smtp_pass', defined('SMTP_PASS') ? SMTP_PASS : '')),
        'from_email' => get_setting('smtp_from_email', defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL  : ''),
        'from_name'  => get_setting('smtp_from_name',  defined('SMTP_FROM_NAME')  ? SMTP_FROM_NAME   : SITE_NAME),
        'security'   => get_setting('smtp_security',   'tls'), // tls | ssl | none
    ];
}

// ── PHPMailer raw send (throws on failure) ────────────────────────────────────
function _svms_smtp_send(
    string  $to,
    string  $subject,
    string  $htmlBody,
    ?string $plainBody,
    array   $attachments,
    ?array  $cfg = null
): string {
    if ($cfg === null) $cfg = _svms_smtp_config();

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $cfg['host'];
    $mail->Port       = $cfg['port'];
    $mail->Username   = $cfg['user'];
    $mail->Password   = $cfg['pass'];
    $mail->SMTPAuth   = ($cfg['user'] !== '');

    $security = strtolower($cfg['security']);
    if ($security === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($security === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = '';
        $mail->SMTPAuth   = false;
    }

    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ],
    ];

    $mail->setFrom($cfg['from_email'], $cfg['from_name']);
    $mail->addAddress($to);
    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'quoted-printable';
    $mail->Subject  = $subject;
    $mail->isHTML(true);
    $mail->Body     = $htmlBody;
    $mail->AltBody  = $plainBody ?? strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));

    foreach ($attachments as $att) {
        if (!empty($att['path']) && file_exists($att['path'])) {
            $mail->addAttachment($att['path'], $att['name'] ?? basename($att['path']));
        }
    }

    $mail->send();
    return $mail->getLastMessageID();
}

// ── Queue insertion ────────────────────────────────────────────────────────────
function _svms_enqueue(
    string  $to,
    string  $subject,
    string  $htmlBody,
    ?string $plainBody,
    string  $relatedType = '',
    int     $relatedId   = 0,
    string  $scheduledAt = ''
): int {
    global $conn;
    $plain   = $plainBody ?? strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));
    $sched   = $scheduledAt ?: date('Y-m-d H:i:s');
    $relType = $relatedType ?: null;
    $relId   = $relatedId   ?: null;

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

    $stmt = $conn->prepare(
        "INSERT INTO email_queue
           (to_email, subject, body_html, body_plain, status, scheduled_at, related_type, related_id)
         VALUES (?, ?, ?, ?, 'pending', ?, ?, ?)"
    );
    $stmt->bind_param('ssssssi', $to, $subject, $htmlBody, $plain, $sched, $relType, $relId);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

// ── Admin queue-paused notice ─────────────────────────────────────────────────
function _svms_set_queue_notice(): void
{
    try { update_setting('email_queue_notice', '1'); } catch (\Throwable $ignored) {}
}

// ── Main public send function ─────────────────────────────────────────────────
/**
 * Send an email synchronously, or enqueue it.
 *
 * @param string      $to        Recipient address
 * @param string      $subject   Subject line
 * @param string      $htmlBody  HTML body
 * @param string|null $plainBody Plain-text fallback; auto-generated if null
 * @param array       $opts      queue, attachments, related_type, related_id, scheduled_at
 * @return array                 {ok, message_id, queued, error}
 */
function send_email(
    string  $to,
    string  $subject,
    string  $htmlBody,
    ?string $plainBody = null,
    array   $opts      = []
): array {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $err = 'Invalid recipient address: ' . $to;
        error_log('[SVMS email] ' . $err);
        return ['ok' => false, 'message_id' => null, 'queued' => false, 'error' => $err];
    }

    $forceQueue  = !empty($opts['queue']);
    $attachments = $opts['attachments'] ?? [];
    $relType     = $opts['related_type'] ?? '';
    $relId       = (int)($opts['related_id'] ?? 0);
    $schedAt     = $opts['scheduled_at']  ?? '';

    if ($forceQueue || $schedAt) {
        $qid = _svms_enqueue($to, $subject, $htmlBody, $plainBody, $relType, $relId, $schedAt);
        log_action('email_queued', $qid, json_encode(['to' => $to, 'subject' => $subject]));
        return ['ok' => true, 'message_id' => null, 'queued' => true, 'error' => null];
    }

    $cfg = _svms_smtp_config();
    if (empty($cfg['host']) || empty($cfg['from_email'])) {
        $qid = _svms_enqueue($to, $subject, $htmlBody, $plainBody, $relType, $relId);
        _svms_set_queue_notice();
        log_action('email_queued', $qid, json_encode(['to' => $to, 'subject' => $subject, 'reason' => 'smtp_not_configured']));
        return ['ok' => true, 'message_id' => null, 'queued' => true, 'error' => 'smtp_not_configured'];
    }

    try {
        $msgId = _svms_smtp_send($to, $subject, $htmlBody, $plainBody, $attachments, $cfg);
        log_action('email_sent', 0, json_encode(['to' => $to, 'subject' => $subject, 'message_id' => $msgId]));
        return ['ok' => true, 'message_id' => $msgId, 'queued' => false, 'error' => null];

    } catch (\Throwable $e) {
        $errMsg = $e->getMessage();
        error_log('[SVMS email] SMTP failed to ' . $to . ': ' . $errMsg);
        $qid = _svms_enqueue($to, $subject, $htmlBody, $plainBody, $relType, $relId);
        _svms_set_queue_notice();
        log_action('email_failed', $qid, json_encode(['to' => $to, 'subject' => $subject, 'error' => $errMsg]));
        return ['ok' => false, 'message_id' => null, 'queued' => true, 'error' => $errMsg];
    }
}

/**
 * Always-queue variant.
 */
function queue_email(
    string  $to,
    string  $subject,
    string  $htmlBody,
    ?string $plainBody = null,
    array   $opts      = []
): array {
    return send_email($to, $subject, $htmlBody, $plainBody, array_merge($opts, ['queue' => true]));
}

// ── Template engine ───────────────────────────────────────────────────────────
/**
 * Render an email template by name.
 *
 * Templates: includes/email_templates/{name}/template.html and template.txt
 * Supports {{varname}} and {{#if varname}}…{{/if}} blocks.
 *
 * @param  string $name  Template folder name (e.g. 'otp_code')
 * @param  array  $vars  Associative array of token => value
 * @return array{html:string, text:string}
 */
function render_email_template(string $name, array $vars): array
{
    $base = __DIR__ . '/email_templates/' . basename($name);
    $html = '';
    $text = '';

    if (file_exists($base . '/template.html')) {
        $html = (string)file_get_contents($base . '/template.html');
    } elseif (file_exists($base . '.html')) {
        $html = (string)file_get_contents($base . '.html');
    }

    if (file_exists($base . '/template.txt')) {
        $text = (string)file_get_contents($base . '/template.txt');
    }

    $process = function (string $tpl, bool $escape) use ($vars): string {
        // {{#if var}}…{{/if}} blocks
        $tpl = preg_replace_callback(
            '/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s',
            function (array $m) use ($vars): string {
                $v = $vars[$m[1]] ?? '';
                return ($v !== '' && $v !== false && $v !== null && $v !== '0') ? $m[2] : '';
            },
            $tpl
        );
        // Token replacement
        foreach ($vars as $k => $v) {
            $rep = $escape ? htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') : (string)$v;
            $tpl = str_replace('{{' . $k . '}}', $rep, $tpl);
        }
        return preg_replace('/\{\{[^}]+\}\}/', '', $tpl);
    };

    $html = $process($html, true);
    $text = $process($text, false);

    if ($text === '') {
        $text = html_entity_decode(
            strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)),
            ENT_QUOTES, 'UTF-8'
        );
    }

    return ['html' => $html, 'text' => $text];
}

/**
 * Legacy shim — load_email_template() returns just HTML.
 */
function load_email_template(string $name, array $vars): string
{
    return render_email_template($name, $vars)['html'];
}
