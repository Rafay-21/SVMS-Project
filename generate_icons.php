<?php
/**
 * generate_icons.php — One-time PWA icon generator for SVMS
 *
 * Generates all PNG icons needed for manifest.json:
 *   icon-192.png, icon-512.png, icon-maskable-192.png, icon-maskable-512.png,
 *   apple-touch-icon.png (180), shortcut-checkin/register/notifications (96)
 *
 * Run:
 *   - Browser: http://localhost/svms/generate_icons.php
 *   - CLI:     php generate_icons.php
 *
 * Requires: GD extension (enabled by default in XAMPP).
 * Delete this file after running.
 */
if (!extension_loaded('gd')) {
    die("GD extension not loaded. Enable it in php.ini (extension=gd).\n");
}

$outDir = __DIR__ . '/assets/img/icons/';
if (!is_dir($outDir) && !mkdir($outDir, 0755, true)) {
    die("Cannot create directory: $outDir\n");
}

// ── Palette ────────────────────────────────────────────────────────────────
$C_BG     = [27,  58,  92];   // #1B3A5C  brand navy
$C_ACCENT = [0,   180, 216];  // #00b4d8  cyan
$C_WHITE  = [255, 255, 255];
$C_GREEN  = [22,  163,  74];  // shortcut: check-in
$C_GOLD   = [202, 138,   4];  // shortcut: register
$C_INDIGO = [79,   70, 229];  // shortcut: notifications

// ── Helpers ────────────────────────────────────────────────────────────────

/**
 * Create a canvas. For standard icons, draw a rounded-corner background.
 * For maskable icons, fill the entire canvas (safe area is 80% of canvas).
 */
function create_canvas(int $size, array $rgb, bool $maskable): GdImage
{
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    imagealphablending($img, true);

    $bg = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);

    if ($maskable) {
        imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $bg);
    } else {
        // Rounded rect — corners ~22% radius
        $r = max(8, (int)($size * 0.22));
        imagefilledrectangle($img, $r,          0,          $size - $r, $size - 1,   $bg);
        imagefilledrectangle($img, 0,           $r,         $size - 1,  $size - $r,  $bg);
        imagefilledellipse(  $img, $r,          $r,          $r * 2,     $r * 2,      $bg);
        imagefilledellipse(  $img, $size - $r,  $r,          $r * 2,     $r * 2,      $bg);
        imagefilledellipse(  $img, $r,          $size - $r,  $r * 2,     $r * 2,      $bg);
        imagefilledellipse(  $img, $size - $r,  $size - $r,  $r * 2,     $r * 2,      $bg);
    }

    return $img;
}

/**
 * Draw the SVMS logo onto an image.
 * SVG viewBox is 48×48; $s = pixels-per-SVG-unit; $ox/$oy = pixel offset.
 */
function draw_logo(GdImage $img, float $s, float $ox, float $oy): void
{
    global $C_ACCENT, $C_WHITE;

    $accent = imagecolorallocate($img, $C_ACCENT[0], $C_ACCENT[1], $C_ACCENT[2]);
    $white  = imagecolorallocate($img, $C_WHITE[0],  $C_WHITE[1],  $C_WHITE[2]);

    // Monitor frame (SVG: rect x=10 y=14 w=28 h=20)
    $mx1 = (int)($ox + 10 * $s); $my1 = (int)($oy + 14 * $s);
    $mx2 = (int)($ox + 38 * $s); $my2 = (int)($oy + 34 * $s);
    $lw  = max(2, (int)(2.5 * $s));
    for ($t = 0; $t < $lw; $t++) {
        imagerectangle($img, $mx1 - $t, $my1 - $t, $mx2 + $t, $my2 + $t, $accent);
    }

    // Center circle (SVG: cx=24 cy=24 r=5)
    $cx = (int)($ox + 24 * $s);
    $cy = (int)($oy + 24 * $s);
    $cr = max(3, (int)(5 * $s));
    imagefilledellipse($img, $cx, $cy, $cr * 2, $cr * 2, $accent);

    // Stand arch (M18 14 v-3 … to x=30)
    $ax1    = (int)($ox + 18 * $s); $ay_bot = (int)($oy + 14 * $s);
    $ax2    = (int)($ox + 30 * $s); $ay_top = (int)($oy + 10 * $s);
    $sw     = max(1, (int)(2 * $s));
    for ($t = 0; $t < $sw; $t++) {
        imageline($img, $ax1 - $t, $ay_bot, $ax1 - $t, $ay_top,  $white);
        imageline($img, $ax1, $ay_top - $t, $ax2, $ay_top - $t,  $white);
        imageline($img, $ax2 + $t, $ay_top, $ax2 + $t, $ay_bot,  $white);
    }

    // Analytics sparkline (M14 29 l4-4 l3 3 l6-6 l4 4)
    $pts = [[14,29],[18,25],[21,28],[27,22],[31,26]];
    for ($i = 0; $i < count($pts) - 1; $i++) {
        $x1 = (int)($ox + $pts[$i][0]     * $s);
        $y1 = (int)($oy + $pts[$i][1]     * $s);
        $x2 = (int)($ox + $pts[$i + 1][0] * $s);
        $y2 = (int)($oy + $pts[$i + 1][1] * $s);
        imageline($img, $x1, $y1, $x2, $y2, $white);
        if ($s >= 2.0) {
            imageline($img, $x1, $y1 + 1, $x2, $y2 + 1, $white);
        }
    }
}

/**
 * Draw a simple shortcut icon (geometric shapes only — no font loading).
 */
function draw_shortcut(GdImage $img, int $size, string $type): void
{
    $white = imagecolorallocate($img, 255, 255, 255);
    $cx    = (int)($size / 2);
    $cy    = (int)($size / 2);
    $lw    = max(2, (int)($size * 0.045));

    if ($type === 'checkin') {
        // Door rectangle
        $dw = (int)($size * 0.28); $dh = (int)($size * 0.42);
        $dx1 = $cx - $dw;  $dy1 = $cy - $dh;
        $dx2 = $cx + $dw;  $dy2 = $cy + $dh;
        for ($t = 0; $t < $lw; $t++) {
            imagerectangle($img, $dx1 - $t, $dy1 - $t, $dx2 + $t, $dy2 + $t, $white);
        }
        // Arrow →
        $al = (int)($size * 0.22); $ah = (int)($size * 0.1);
        imageline($img, $cx - $al, $cy, $cx + $al, $cy, $white);
        imageline($img, $cx + $al - $ah, $cy - $ah, $cx + $al, $cy, $white);
        imageline($img, $cx + $al - $ah, $cy + $ah, $cx + $al, $cy, $white);
        // Knob
        $kr = max(3, (int)($size * 0.06));
        imagefilledellipse($img, $cx + (int)($size * 0.18), $cy, $kr * 2, $kr * 2, $white);

    } elseif ($type === 'register') {
        // Person head
        $hr  = max(5, (int)($size * 0.13));
        $hcx = (int)($cx - $size * 0.08);
        $hcy = (int)($cy - $size * 0.12);
        imagefilledellipse($img, $hcx, $hcy, $hr * 2, $hr * 2, $white);
        // Person body (arc)
        $br = (int)($size * 0.22);
        imagearc($img, $hcx, $cy + (int)($size * 0.12), $br * 2, $br * 2, 195, 345, $white);
        // Plus sign
        $px = $cx + (int)($size * 0.26);
        $py = $cy - (int)($size * 0.2);
        $pl = (int)($size * 0.12);
        for ($t = -$lw; $t <= $lw; $t++) {
            imageline($img, $px - $pl, $py + $t, $px + $pl, $py + $t, $white);
            imageline($img, $px + $t, $py - $pl, $px + $t, $py + $pl, $white);
        }

    } elseif ($type === 'notifications') {
        // Bell body
        $bw  = (int)($size * 0.32);
        $bh  = (int)($size * 0.3);
        $btop = $cy - (int)($size * 0.18);
        $bbot = $cy + (int)($size * 0.16);
        // Arc top
        imagearc($img, $cx, $btop + (int)($bh * 0.35), $bw * 2, (int)($bh * 1.2), 180, 0, $white);
        // Sides
        for ($t = 0; $t < $lw; $t++) {
            imageline($img, $cx - $bw - $t, $btop + (int)($bh * 0.35), $cx - $bw - $t, $bbot, $white);
            imageline($img, $cx + $bw + $t, $btop + (int)($bh * 0.35), $cx + $bw + $t, $bbot, $white);
        }
        // Bottom rim
        imagearc($img, $cx, $bbot, (int)($bw * 2.2), (int)($size * 0.14), 0, 180, $white);
        // Clapper
        $cr = max(3, (int)($size * 0.07));
        imagefilledellipse($img, $cx, $bbot + (int)($size * 0.08), $cr * 2, $cr * 2, $white);
        // Stem
        imagefilledellipse($img, $cx, $btop - (int)($size * 0.03),
            max(4, (int)($size * 0.07)) * 2, max(4, (int)($size * 0.07)) * 2, $white);
    }
}

// ── Main icon generation ──────────────────────────────────────────────────
$header  = PHP_SAPI === 'cli' ? '' : '';
$newline = PHP_SAPI === 'cli' ? "\n" : "<br>\n";

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Generate Icons</title></head><body><pre>\n";
}

$standardSizes = [
    ['icon-192.png',          192, false],
    ['icon-512.png',          512, false],
    ['icon-maskable-192.png', 192, true],
    ['icon-maskable-512.png', 512, true],
    ['apple-touch-icon.png',  180, false],
];

foreach ($standardSizes as [$filename, $size, $maskable]) {
    $img = create_canvas($size, $C_BG, $maskable);

    // For maskable icons the icon occupies the safe area (center 60%)
    $pad   = $maskable ? ($size * 0.20) : ($size * 0.08);
    $inner = $size - $pad * 2.0;
    $scale = $inner / 48.0;
    draw_logo($img, $scale, (float)$pad, (float)$pad);

    imagepng($img, $outDir . $filename);
    imagedestroy($img);
    echo "✓ Generated: $filename$newline";
}

$shortcuts = [
    ['shortcut-checkin.png',       96, $C_GREEN,  'checkin'],
    ['shortcut-register.png',      96, $C_GOLD,   'register'],
    ['shortcut-notifications.png', 96, $C_INDIGO, 'notifications'],
];

foreach ($shortcuts as [$filename, $size, $color, $type]) {
    $img = create_canvas($size, $color, false);
    draw_shortcut($img, $size, $type);
    imagepng($img, $outDir . $filename);
    imagedestroy($img);
    echo "✓ Generated: $filename$newline";
}

echo "{$newline}All icons written to: $outDir{$newline}";
echo "You can now delete this file.{$newline}";

if (PHP_SAPI !== 'cli') {
    echo "</pre></body></html>\n";
}
