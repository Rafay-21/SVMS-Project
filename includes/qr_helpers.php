<?php
/**
 * includes/qr_helpers.php
 * QR code generation helpers for SVMS v2.0.
 *
 * Wraps phpqrcode (vendor/phpqrcode/qrlib.php).
 * Call require_qrcode() before using generate_qr_png().
 */

/**
 * Load the phpqrcode library (lazy — only once per request).
 */
function require_qrcode(): void
{
    static $loaded = false;
    if ($loaded) return;
    require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';
    $loaded = true;
}

/**
 * Generate a QR code PNG and save to $outPath.
 *
 * @param string $payload  The text / URL to encode
 * @param string $outPath  Absolute filesystem path to write the PNG
 * @param int    $size     Module size in pixels (default 8 → ~240 px for v4 QR)
 * @param int    $margin   Quiet zone in modules (default 2)
 * @return bool  true on success
 */
function generate_qr_png(string $payload, string $outPath, int $size = 8, int $margin = 2): bool
{
    require_qrcode();

    $dir = dirname($outPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    try {
        QRcode::png($payload, $outPath, QR_ECLEVEL_M, $size, $margin);
        return file_exists($outPath) && filesize($outPath) > 0;
    } catch (\Throwable $e) {
        error_log('generate_qr_png: ' . $e->getMessage());
        return false;
    }
}

/**
 * Build the URL payload for a visitor badge QR code.
 *
 * Encodes: BASE_URL/pages/visitor_detail.php?token={qr_token}
 * Scanning with any phone QR scanner opens the visitor detail page.
 *
 * @param string $qrToken  The visitors.qr_token value (32-hex chars)
 * @return string
 */
function badge_qr_payload(string $qrToken): string
{
    return BASE_URL . 'pages/visitor_detail.php?token=' . rawurlencode($qrToken);
}

/**
 * Get (or create) the QR PNG path for a visitor's badge.
 * Caches at assets/uploads/qr/qr_{token}.png.
 *
 * @param string $qrToken
 * @return string|null  Absolute path on success, null on failure
 */
function get_or_create_qr(string $qrToken): ?string
{
    $dir  = UPLOAD_DIR . 'qr/';
    $path = $dir . 'qr_' . preg_replace('/[^a-f0-9]/i', '', $qrToken) . '.png';

    if (file_exists($path) && filesize($path) > 0) {
        return $path;
    }

    $payload = badge_qr_payload($qrToken);
    return generate_qr_png($payload, $path, 8, 2) ? $path : null;
}
