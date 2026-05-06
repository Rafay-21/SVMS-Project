<?php
/**
 * includes/flash.php
 * Session-based flash message system.
 * Types: success, error, warning, info
 */

/**
 * Push a flash message onto the session stack.
 */
function flash(string $type, string $message): void {
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Render all queued flash banners and clear them.
 * Call this just below the navbar in header.php.
 */
function render_flash(): void {
    if (empty($_SESSION['flash'])) return;

    $icons = [
        'success' => 'bi-check-circle-fill',
        'error'   => 'bi-x-circle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
    ];

    foreach ($_SESSION['flash'] as $flash) {
        $type    = htmlspecialchars($flash['type']    ?? 'info',    ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($flash['message'] ?? '',        ENT_QUOTES, 'UTF-8');
        $icon    = $icons[$flash['type']] ?? 'bi-info-circle-fill';

        echo '<div class="alert alert-' . $type . '" role="alert">';
        echo   '<i class="alert-icon bi ' . $icon . '"></i>';
        echo   '<div class="alert-body">' . $message . '</div>';
        echo   '<button class="alert-close" aria-label="Close">&times;</button>';
        echo '</div>';
    }

    unset($_SESSION['flash']);
}
