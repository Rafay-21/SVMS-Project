<?php
/**
 * includes/error_handler.php
 * Production-safe error and exception handling.
 *
 * Loaded by config.php for every request.
 * In IS_DEV mode: errors propagate normally (display_errors=1).
 * In production: errors are logged to /logs/php_errors.log and the user
 * sees a clean 500 page — no stack traces, file paths, or DB details leak.
 */

if (!IS_DEV) {

    /**
     * Custom error handler — converts E_* errors to log entries.
     * Suppresses output of any error detail to the browser.
     */
    set_error_handler(static function (
        int    $errno,
        string $errstr,
        string $errfile = '',
        int    $errline = 0
    ): bool {
        if (!(error_reporting() & $errno)) {
            return false; // honour @ operator
        }

        $level = match ($errno) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR   => 'FATAL',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING,
                E_USER_WARNING                                      => 'WARNING',
            E_NOTICE, E_USER_NOTICE                                 => 'NOTICE',
            E_DEPRECATED, E_USER_DEPRECATED                        => 'DEPRECATED',
            default                                                 => 'ERROR',
        };

        $msg = sprintf(
            '[%s] %s: %s in %s:%d',
            date('Y-m-d H:i:s'),
            $level,
            $errstr,
            $errfile,
            $errline
        );
        error_log($msg . PHP_EOL, 3, LOG_DIR . '/php_errors.log');

        // For fatal-class errors, show the 500 page.
        if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            _svms_show_500();
        }

        return true; // don't execute PHP internal error handler
    });

    /**
     * Custom exception handler — logs full stack trace, shows clean 500 page.
     */
    set_exception_handler(static function (Throwable $e): void {
        $msg = sprintf(
            '[%s] UNCAUGHT %s: %s in %s:%d%sStack trace:%s%s',
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            PHP_EOL,
            PHP_EOL,
            $e->getTraceAsString()
        );
        error_log($msg . PHP_EOL, 3, LOG_DIR . '/php_errors.log');
        _svms_show_500();
    });

    /**
     * Shutdown handler — catches fatal errors that bypass set_error_handler.
     */
    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            $msg = sprintf(
                '[%s] SHUTDOWN FATAL %s in %s:%d',
                date('Y-m-d H:i:s'),
                $err['message'],
                $err['file'],
                $err['line']
            );
            error_log($msg . PHP_EOL, 3, LOG_DIR . '/php_errors.log');
            _svms_show_500();
        }
    });
}

/**
 * Render the 500 error page and terminate.
 * Called by handlers above; also callable directly.
 */
function _svms_show_500(): never
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    // Avoid recursive includes if 500.php itself errors.
    $f = defined('__DIR__') ? __DIR__ . '/../500.php' : dirname(__FILE__) . '/../500.php';
    if (file_exists($f)) {
        include $f;
    } else {
        echo '<!DOCTYPE html><html><head><title>Server Error</title></head><body>'
           . '<h1>500 — Internal Server Error</h1>'
           . '<p>An unexpected error occurred. Please try again later.</p>'
           . '</body></html>';
    }
    exit(1);
}
