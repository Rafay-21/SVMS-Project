<?php
/**
 * includes/db_functions.php
 * Core database helper functions for SVMS v2.0
 */

/**
 * Escape output — htmlspecialchars wrapper.
 */
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize user input.
 */
function sanitize(string $s): string {
    return e(stripslashes(trim($s)));
}

/**
 * Format a datetime string.
 */
function format_datetime(?string $dt, string $fmt = 'M d, Y g:i A'): string {
    if (empty($dt) || $dt === '0000-00-00 00:00:00') return '—';
    return date($fmt, strtotime($dt));
}

/**
 * Human-readable elapsed time, e.g. "2h 15m".
 */
function time_elapsed(string $from, ?string $to = null): string {
    $start  = strtotime($from);
    $end    = $to ? strtotime($to) : time();
    $secs   = max(0, $end - $start);
    $hours  = (int)floor($secs / 3600);
    $mins   = (int)floor(($secs % 3600) / 60);
    if ($hours > 0) return $hours . 'h ' . $mins . 'm';
    return $mins . 'm';
}

/**
 * Generate a unique badge number, e.g. VIS-240501-A3F2C.
 */
function generate_badge_number(): string {
    return BADGE_PREFIX . '-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 5));
}

/**
 * Generate a 32-character hex QR token.
 */
function generate_qr_token(): string {
    return bin2hex(random_bytes(16));
}

/**
 * Log an admin action to the audit_logs table.
 */
function log_action(string $action, int $target_id = 0, string $details_json = ''): void {
    global $conn;
    $admin_id   = (int)($_SESSION['admin_id'] ?? 0);
    $ip         = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    $stmt = $conn->prepare(
        'INSERT INTO audit_logs (admin_id, action, target_id, details, ip_address, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    if ($stmt) {
        $stmt->bind_param('isisss', $admin_id, $action, $target_id, $details_json, $ip, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Retrieve a single setting value by key.
 */
function get_setting(string $key, string $default = ''): string {
    global $conn;
    $stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    if (!$stmt) return $default;
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();
    return $row['setting_value'] ?? $default;
}

/**
 * Update a setting value.
 */
function update_setting(string $key, string $value): bool {
    global $conn;
    $stmt = $conn->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    if (!$stmt) return false;
    $stmt->bind_param('ss', $key, $value);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Execute a prepared statement and return a single row.
 *
 * @param string $sql    SQL with ? placeholders
 * @param string $types  Bind types string (e.g. 'si')
 * @param array  $params Values to bind
 * @return array|null
 */
function query_one(string $sql, string $types = '', array $params = []): ?array {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Execute a prepared statement and return all rows.
 *
 * @param string $sql
 * @param string $types
 * @param array  $params
 * @return array
 */
function query_all(string $sql, string $types = '', array $params = []): array {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows   = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Execute a prepared INSERT/UPDATE/DELETE and return affected rows.
 */
function query_exec(string $sql, string $types = '', array $params = []): int {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

/**
 * Return last inserted ID from the connection.
 */
function last_insert_id(): int {
    global $conn;
    return (int)$conn->insert_id;
}

/**
 * Validate password complexity.
 * Rules: ≥10 chars, ≥1 letter, ≥1 digit, ≥1 symbol.
 *
 * @return string  Empty string on pass; human-readable error on fail.
 */
function validate_password_strength(string $pass): string
{
    if (strlen($pass) < 10) {
        return 'Password must be at least 10 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $pass)) {
        return 'Password must contain at least one letter.';
    }
    if (!preg_match('/[0-9]/', $pass)) {
        return 'Password must contain at least one digit.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $pass)) {
        return 'Password must contain at least one special character (e.g. @, #, !, $).';
    }
    return '';
}

/**
 * Check whether a plaintext password matches any of the last $limit
 * stored hashes in admin_password_history for the given admin.
 *
 * @return bool  TRUE if the password was used recently (reject it).
 */
function password_used_recently(string $plain, int $admin_id, int $limit = 3): bool
{
    $rows = query_all(
        'SELECT pass_hash FROM admin_password_history
         WHERE admin_id = ? ORDER BY changed_at DESC LIMIT ?',
        'ii', [$admin_id, $limit]
    );
    foreach ($rows as $row) {
        if (password_verify($plain, $row['pass_hash'])) {
            return true;
        }
    }
    return false;
}

/**
 * Record a new password hash in admin_password_history.
 * Keeps only the most recent 10 entries per admin.
 */
function record_password_history(int $admin_id, string $hash): void
{
    query_exec(
        'INSERT INTO admin_password_history (admin_id, pass_hash, changed_at) VALUES (?, ?, NOW())',
        'is', [$admin_id, $hash]
    );
    // Prune older than the 10 most recent
    query_exec(
        'DELETE FROM admin_password_history
         WHERE admin_id = ?
           AND id NOT IN (
               SELECT id FROM (
                   SELECT id FROM admin_password_history
                   WHERE admin_id = ?
                   ORDER BY changed_at DESC LIMIT 10
               ) t
           )',
        'ii', [$admin_id, $admin_id]
    );
}
