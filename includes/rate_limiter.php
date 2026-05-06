<?php
/**
 * includes/rate_limiter.php
 * File-based sliding-window rate limiter.
 *
 * Usage:
 *   if (!rl_check('login:' . $ip, 10)) {
 *       http_response_code(429);
 *       header('Retry-After: 60');
 *       echo json_encode(['ok' => false, 'error' => 'rate_limit']);
 *       exit;
 *   }
 */

/**
 * Check whether a given key is within its allowed request budget.
 *
 * @param string $key          Namespaced bucket key, e.g. 'login:1.2.3.4'
 * @param int    $max_per_min  Maximum allowed hits within a 60-second window.
 * @return bool  TRUE = request is allowed; FALSE = rate limit exceeded.
 */
function rl_check(string $key, int $max_per_min): bool
{
    $dir = LOG_DIR . '/rate_limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    // Filename: deterministic hash so no user-controlled path components reach disk.
    $file = $dir . '/rl_' . hash('sha256', $key) . '.json';
    $now  = time();
    $win  = 60; // 1-minute sliding window

    // Read existing timestamps
    $data = [];
    if (file_exists($file)) {
        $raw  = @file_get_contents($file);
        $data = ($raw !== false) ? (json_decode($raw, true) ?: []) : [];
    }

    // Evict timestamps outside the window
    $data = array_values(array_filter($data, static fn(int $t) => ($now - $t) < $win));

    if (count($data) >= $max_per_min) {
        return false; // over limit
    }

    $data[] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    return true;
}

/**
 * Convenience wrapper: send a 429 JSON response with Retry-After and exit.
 * Call after rl_check() returns false.
 */
function rl_abort(): never
{
    http_response_code(429);
    header('Retry-After: 60');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => false, 'error' => 'rate_limit']);
    exit;
}
