<?php
/**
 * includes/badge_helpers.php
 * GD-based visitor badge generation for SVMS v2.0.
 *
 * Canvas:  480 × 720 px  (80 mm × 120 mm at 6 px/mm)
 * Output:  PNG  at  assets/uploads/badges/badge_{visit_log_id}.png
 *
 * Layout (top → bottom):
 *   0–80    Primary header bar  (#1a3c5e) — logo text + VISITOR label
 *  80–300   Visitor photo (200×200, circular crop) centred at y=190
 * 300–360   Full name (large bold)
 * 360–395   Badge number
 * 395–430   Department + Host
 * 430–460   Date + Time
 * 460–696   QR code (240×240) centred, bottom-third
 * 696–720   Footer bar (#2e75b6) — site URL
 */

require_once __DIR__ . '/qr_helpers.php';

/* ── Font helpers ─────────────────────────────────────────────── */

/**
 * Resolve the best available TTF font path.
 * Returns null if FreeType / TTF not available.
 */
function badge_font(?bool $bold = false): ?string
{
    if (!function_exists('imagettftext')) return null;

    $candidates = $bold ? [
        'C:/Windows/Fonts/arialbd.ttf',
        'C:/Windows/Fonts/calibrib.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/ubuntu/Ubuntu-B.ttf',
        '/System/Library/Fonts/Helvetica.ttc',
    ] : [
        'C:/Windows/Fonts/arial.ttf',
        'C:/Windows/Fonts/calibri.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/ubuntu/Ubuntu-R.ttf',
        '/System/Library/Fonts/Helvetica.ttc',
    ];

    foreach ($candidates as $path) {
        if (file_exists($path)) return $path;
    }
    return null;
}

/**
 * Draw horizontally centred text on a GD image.
 * Uses imagettftext if a TTF font is available, else imagestring.
 *
 * @param resource|\GdImage $im
 * @param int    $size    Font size in pt (TTF) or GD font index (1-5)
 * @param int    $y       Top-left Y for GD; baseline Y for TTF
 * @param int    $colour  Colour resource
 * @param string $text
 * @param ?string $font    TTF path or null
 * @param int    $canvasW Total canvas width for centering
 */
function badge_text_centre($im, int $size, int $y, int $colour, string $text, ?string $font, int $canvasW = 480): void
{
    if ($font) {
        $box = imagettfbbox($size, 0, $font, $text);
        $tw  = abs($box[4] - $box[0]);
        $x   = (int)(($canvasW - $tw) / 2);
        imagettftext($im, $size, 0, $x, $y, $colour, $font, $text);
    } else {
        // Built-in fonts: width per char ≈ $size * 5 + 2
        $charW = ($size <= 2) ? 6 : (($size <= 3) ? 7 : (($size <= 4) ? 8 : 9));
        $tw    = strlen($text) * $charW;
        $x     = (int)(($canvasW - $tw) / 2);
        imagestring($im, $size, $x, $y, $text, $colour);
    }
}

/* ── Main badge function ──────────────────────────────────────── */

/**
 * Generate a visitor badge PNG for the given visit_log row.
 *
 * @param int $visit_log_id
 * @return string|null  Absolute path of generated PNG, or null on error
 */
function generate_badge(int $visit_log_id): ?string
{
    global $conn;

    if (!function_exists('imagecreatetruecolor')) return null; // GD not available

    /* ── Load data ───────────────────────────────────────────── */
    $visit = query_one(
        "SELECT vl.id, v.badge_number, vl.host_name AS person_to_meet, vl.check_in_time,
                v.name AS full_name, v.photo_path, v.qr_token,
                COALESCE(d.name,'—') AS dept_name
         FROM visit_log vl
         JOIN visitors v         ON v.id = vl.visitor_id
         LEFT JOIN departments d ON d.id = vl.department_id
         WHERE vl.id = ?
         LIMIT 1",
        'i', [$visit_log_id]
    );
    if (!$visit) return null;

    /* ── Output path ─────────────────────────────────────────── */
    $badgeDir = UPLOAD_DIR . 'badges/';
    if (!is_dir($badgeDir)) mkdir($badgeDir, 0755, true);
    $outPath = $badgeDir . 'badge_' . $visit_log_id . '.png';

    /* ── Canvas ──────────────────────────────────────────────── */
    $W  = 480;
    $H  = 720;
    $im = imagecreatetruecolor($W, $H);
    if (!$im) return null;

    /* ── Palette ─────────────────────────────────────────────── */
    $white   = imagecolorallocate($im, 255, 255, 255);
    $cPrimary = imagecolorallocate($im, 26,  60,  94);   // #1a3c5e
    $cSec    = imagecolorallocate($im, 46, 117, 182);   // #2e75b6
    $cText   = imagecolorallocate($im, 30,  30,  30);
    $cMuted  = imagecolorallocate($im, 100, 100, 100);
    $cBorder = imagecolorallocate($im, 220, 220, 220);
    $cAccent = imagecolorallocate($im,  0, 180, 216);   // #00b4d8
    $cWhite  = imagecolorallocate($im, 255, 255, 255);

    /* ── White background ────────────────────────────────────── */
    imagefilledrectangle($im, 0, 0, $W - 1, $H - 1, $white);

    /* ── Header bar (0–80) ───────────────────────────────────── */
    imagefilledrectangle($im, 0, 0, $W, 80, $cPrimary);
    // Accent stripe
    imagefilledrectangle($im, 0, 76, $W, 80, $cAccent);

    $fontBold   = badge_font(true);
    $fontNormal = badge_font(false);

    // Header: site name
    badge_text_centre($im, $fontBold ? 18 : 4, $fontBold ? 32 : 20, $cWhite, SITE_NAME, $fontBold, $W);
    // Header: VISITOR label
    badge_text_centre($im, $fontBold ? 13 : 3, $fontBold ? 58 : 48, $cAccent, 'V I S I T O R', $fontBold, $W);

    /* ── Photo area (centred, y=90..290) ─────────────────────── */
    $photoSize = 200;
    $photoX    = (int)(($W - $photoSize) / 2);   // 140
    $photoY    = 90;

    // Circular mask helpers
    $hasPhoto = false;
    if (!empty($visit['photo_path'])) {
        $pPath = UPLOAD_DIR . $visit['photo_path'];
        if (file_exists($pPath)) {
            $ext   = strtolower(pathinfo($pPath, PATHINFO_EXTENSION));
            $photo = null;
            if (in_array($ext, ['jpg','jpeg'])) $photo = @imagecreatefromjpeg($pPath);
            elseif ($ext === 'png')             $photo = @imagecreatefrompng($pPath);
            elseif ($ext === 'gif')             $photo = @imagecreatefromgif($pPath);

            if ($photo) {
                // Resize to 200×200
                $thumb  = imagecreatetruecolor($photoSize, $photoSize);
                $transp = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
                imagefill($thumb, 0, 0, $transp);
                imagesavealpha($thumb, true);
                imagecopyresampled($thumb, $photo, 0, 0, 0, 0, $photoSize, $photoSize,
                    imagesx($photo), imagesy($photo));
                imagedestroy($photo);

                // Circular mask
                $mask = imagecreatetruecolor($photoSize, $photoSize);
                $maskBg    = imagecolorallocate($mask, 0, 0, 0);
                $maskWhite = imagecolorallocate($mask, 255, 255, 255);
                imagefill($mask, 0, 0, $maskBg);
                imagefilledellipse($mask, $photoSize / 2, $photoSize / 2, $photoSize, $photoSize, $maskWhite);

                // Apply circular mask
                for ($px = 0; $px < $photoSize; $px++) {
                    for ($py = 0; $py < $photoSize; $py++) {
                        $mc = imagecolorat($mask, $px, $py);
                        if ($mc !== $maskWhite) {
                            imagesetpixel($thumb, $px, $py, imagecolorallocatealpha($thumb, 255, 255, 255, 127));
                        }
                    }
                }
                imagedestroy($mask);

                imagecopy($im, $thumb, $photoX, $photoY, 0, 0, $photoSize, $photoSize);
                imagedestroy($thumb);
                $hasPhoto = true;
            }
        }
    }

    if (!$hasPhoto) {
        // Initials avatar
        imagefilledellipse($im, $W / 2, $photoY + $photoSize / 2, $photoSize, $photoSize, $cSec);
        $parts = explode(' ', trim($visit['full_name']));
        $ini   = strtoupper(substr($parts[0], 0, 1)) . (isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) : '');
        badge_text_centre($im, $fontBold ? 64 : 5, $fontBold ? ($photoY + $photoSize / 2 + 24) : ($photoY + $photoSize / 2 - 4), $cWhite, $ini, $fontBold, $W);
    }

    // Photo ring
    imageellipse($im, $W / 2, $photoY + $photoSize / 2, $photoSize + 4, $photoSize + 4, $cSec);

    /* ── Text block ──────────────────────────────────────────── */
    // Name
    $nameY = $fontBold ? 316 : 300;
    $name  = mb_strlen($visit['full_name']) > 24
           ? mb_substr($visit['full_name'], 0, 22) . '…'
           : $visit['full_name'];
    badge_text_centre($im, $fontBold ? 20 : 4, $nameY, $cText, $name, $fontBold, $W);

    // Divider
    imageline($im, 80, 332, $W - 80, 332, $cBorder);

    // Badge number
    badge_text_centre($im, $fontNormal ? 12 : 3, $fontNormal ? 354 : 342, $cMuted,
        'Badge: ' . $visit['badge_number'], $fontNormal, $W);

    // Department
    $dept = mb_strlen($visit['dept_name']) > 30
          ? mb_substr($visit['dept_name'], 0, 28) . '…'
          : $visit['dept_name'];
    badge_text_centre($im, $fontNormal ? 11 : 2, $fontNormal ? 378 : 360, $cText,
        'Dept: ' . $dept, $fontNormal, $W);

    // Host
    $host = mb_strlen($visit['person_to_meet']) > 30
          ? mb_substr($visit['person_to_meet'], 0, 28) . '…'
          : $visit['person_to_meet'];
    badge_text_centre($im, $fontNormal ? 11 : 2, $fontNormal ? 400 : 376, $cText,
        'Host: ' . $host, $fontNormal, $W);

    // Date/time
    $dt = date('d M Y  g:i A', strtotime($visit['check_in_time']));
    badge_text_centre($im, $fontNormal ? 10 : 2, $fontNormal ? 422 : 392, $cMuted, $dt, $fontNormal, $W);

    /* ── QR code (240×240 at y=444) ──────────────────────────── */
    $qrSize = 240;
    $qrX    = (int)(($W - $qrSize) / 2);   // 120
    $qrY    = 444;

    $qrPath = null;
    if (!empty($visit['qr_token'])) {
        $qrPath = get_or_create_qr($visit['qr_token']);
    }

    if ($qrPath && file_exists($qrPath)) {
        $qrIm = @imagecreatefrompng($qrPath);
        if ($qrIm) {
            imagecopyresampled($im, $qrIm, $qrX, $qrY, 0, 0,
                $qrSize, $qrSize, imagesx($qrIm), imagesy($qrIm));
            imagedestroy($qrIm);
        }
    } else {
        // QR placeholder
        imagefilledrectangle($im, $qrX, $qrY, $qrX + $qrSize, $qrY + $qrSize, $cBorder);
        badge_text_centre($im, 2, $qrY + $qrSize / 2, $cMuted, 'QR Code', null, $W);
    }

    /* ── Footer bar (696–720) ────────────────────────────────── */
    imagefilledrectangle($im, 0, 696, $W, $H, $cSec);
    badge_text_centre($im, $fontNormal ? 10 : 2, $fontNormal ? 712 : 706, $cWhite,
        'svms.com  |  Visitor Management System', $fontNormal, $W);

    /* ── PNG phys chunk equivalent — documented note ─────────── */
    // PNG pHYs chunk: 6px/mm = 152 DPI. GD does not expose direct pHYs writing.
    // Print at 152 DPI to get 80×120mm physical size.

    /* ── Save ────────────────────────────────────────────────── */
    $ok = imagepng($im, $outPath, 3); // compression 3 = fast, good quality
    imagedestroy($im);

    return ($ok && file_exists($outPath)) ? $outPath : null;
}

/**
 * Return the web-accessible URL for a badge PNG.
 * Generates the file first if missing.
 *
 * @param int $visit_log_id
 * @return string|null  URL or null if generation failed
 */
function badge_url(int $visit_log_id): ?string
{
    $path = UPLOAD_DIR . 'badges/badge_' . $visit_log_id . '.png';
    if (!file_exists($path)) {
        $path = generate_badge($visit_log_id);
    }
    if (!$path) return null;
    return BASE_URL . 'assets/uploads/badges/badge_' . $visit_log_id . '.png';
}
