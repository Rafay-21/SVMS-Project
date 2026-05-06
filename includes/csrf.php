<?php
/**
 * includes/csrf.php
 * CSRF token generation and validation.
 *
 * Strategy:
 *   - Per-session double-submit token (stored in $_SESSION['csrf_token']).
 *   - HTML forms include a hidden <input name="csrf_token">.
 *   - AJAX requests send the token as the X-CSRF-Token HTTP header.
 *   - High-value actions (mass-checkout, delete, settings) use a
 *     once-per-request sub-token via csrf_one_time_token() /
 *     csrf_validate_one_time() to prevent replay within the same session.
 */

/**
 * Output a hidden CSRF input field.
 */
function csrf_field(): void {
    $token = $_SESSION['csrf_token'] ?? '';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate the CSRF token from POST body or X-CSRF-Token header.
 * On failure, redirects back with an error flash (for form POSTs)
 * or sends 403 JSON (for AJAX requests).
 */
function csrf_validate(): void {
    $stored = (string)($_SESSION['csrf_token'] ?? '');

    // 1. Check POST field
    $token = (string)($_POST['csrf_token'] ?? '');

    // 2. Fallback: X-CSRF-Token header (for AJAX/fetch calls)
    if ($token === '') {
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        foreach ($headers as $k => $v) {
            if (strtolower((string)$k) === 'x-csrf-token') {
                $token = (string)$v;
                break;
            }
        }
    }

    if ($stored === '' || !hash_equals($stored, $token)) {
        $is_ajax = (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || (
            isset($_SERVER['CONTENT_TYPE']) &&
            str_contains(strtolower($_SERVER['CONTENT_TYPE']), 'application/json')
        );

        if ($is_ajax) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'csrf_mismatch']);
            exit;
        }

        flash('error', 'Security token mismatch. Please try again.');
        $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
        $parsed  = parse_url($referer);
        $base    = parse_url(BASE_URL);
        if (($parsed['host'] ?? '') === ($base['host'] ?? '')) {
            header('Location: ' . $referer);
        } else {
            header('Location: ' . BASE_URL);
        }
        exit;
    }
}

/**
 * Return the current CSRF token for use in JavaScript (AJAX).
 */
function csrf_token_for_js(): string {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Generate (or return existing) a one-time sub-token for a specific
 * high-value action (e.g. 'mass_checkout', 'delete_visitor').
 * The sub-token is stored in $_SESSION and consumed on first use.
 */
function csrf_one_time_token(string $action): string {
    $key = 'csrf_ot_' . $action;
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(16));
    }
    return $_SESSION[$key];
}

/**
 * Validate and consume a one-time sub-token for the given action.
 * Returns true on success; false if missing or already consumed.
 */
function csrf_validate_one_time(string $action, string $submitted): bool {
    $key   = 'csrf_ot_' . $action;
    $stored = (string)($_SESSION[$key] ?? '');
    if ($stored === '' || !hash_equals($stored, $submitted)) {
        return false;
    }
    unset($_SESSION[$key]); // consume — single use
    return true;
}
