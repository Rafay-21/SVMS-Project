<?php
/**
 * api/kiosk_checkin.php
 * POST — finalise kiosk check-in:
 *   - optionally saves photo
 *   - creates (or re-uses) visit_log row
 *   - generates badge PNG
 * Returns JSON: {ok:true, visit_log_id:N, badge_url:string|null}
 */
require_once __DIR__ . '/../kiosk/kiosk_boot.php';
require_once __DIR__ . '/../includes/badge_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kiosk_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

if (empty($_SESSION['kiosk_active'])) {
    kiosk_json(['ok' => false, 'error' => 'Session expired.'], 401);
}

if (!kiosk_rate_limit(20, 60)) {
    kiosk_json(['ok' => false, 'error' => 'Too many requests.'], 429);
}

kiosk_csrf_validate();

$raw  = file_get_contents('php://input');
$body = $raw ? (json_decode($raw, true) ?: []) : [];
$body = array_merge($_POST, $body);

$visitor_id   = (int)($body['visitor_id']   ?? 0);
$visit_log_id = (int)($body['visit_log_id'] ?? 0); // may be pre-created by kiosk_register
$photo_data   = $body['photo_data'] ?? null;

if ($visitor_id < 1) {
    kiosk_json(['ok' => false, 'error' => 'Invalid visitor ID.'], 422);
}

/* ── Load visitor ─────────────────────────────────────────── */
$visitor = query_one(
    "SELECT id, full_name, phone, cnic, photo_path FROM visitors WHERE id = ?",
    [$visitor_id]
);
if (!$visitor) {
    kiosk_json(['ok' => false, 'error' => 'Visitor not found.'], 404);
}

/* ── Blacklist check ──────────────────────────────────────── */
$bl = query_one(
    "SELECT id FROM blacklist WHERE (phone = ? OR (cnic IS NOT NULL AND cnic != '' AND cnic = ?)) AND is_active = 1 LIMIT 1",
    [$visitor['phone'], $visitor['cnic'] ?: '~~~NONE~~~']
);
if ($bl) {
    kiosk_json(['ok' => false, 'error' => 'Access denied. Please see reception.'], 403);
}

/* ── Save photo if provided ───────────────────────────────── */
if ($photo_data && str_starts_with($photo_data, 'data:image')) {
    $imgBin = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $photo_data));
    if ($imgBin && strlen($imgBin) > 1000) {
        // MIME validation via finfo
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $imgMime  = finfo_buffer($finfo, $imgBin);
        finfo_close($finfo);

        if (in_array($imgMime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            // Re-encode through GD to strip EXIF and validate pixel data
            $gdImg = @imagecreatefromstring($imgBin);
            if ($gdImg !== false) {
                $dir = UPLOAD_DIR . 'visitor_photos/';
                if (!is_dir($dir)) mkdir($dir, 0750, true);
                $fname = bin2hex(random_bytes(16)) . '.jpg';
                if (imagejpeg($gdImg, $dir . $fname, 85)) {
                    query_exec(
                        "UPDATE visitors SET photo_path = ? WHERE id = ?",
                        ['visitor_photos/' . $fname, $visitor_id]
                    );
                }
                imagedestroy($gdImg);
            }
        }
    }
}

/* ── Create visit_log if not pre-created ─────────────────── */
if (!$visit_log_id) {
    // Check if already checked in (double-submit guard)
    $existing = query_one(
        "SELECT id FROM visit_log WHERE visitor_id = ? AND status = 'checked_in' LIMIT 1",
        [$visitor_id]
    );
    if ($existing) {
        $visit_log_id = (int)$existing['id'];
    } else {
        $badge_number = generate_badge_number();
        query_exec(
            "INSERT INTO visit_log
               (visitor_id, badge_number, check_in_time, status, registered_by, visitor_type)
             VALUES (?, ?, NOW(), 'checked_in', NULL, 'walk_in')",
            [$visitor_id, $badge_number]
        );
        $visit_log_id = (int)last_insert_id();
        if (!$visit_log_id) {
            kiosk_json(['ok' => false, 'error' => 'Check-in failed. Please see reception.'], 500);
        }
    }
} else {
    // Update status if visit_log was pre-created (from kiosk_register with status pending/initial)
    query_exec(
        "UPDATE visit_log SET status = 'checked_in', check_in_time = COALESCE(check_in_time, NOW()) WHERE id = ?",
        [$visit_log_id]
    );
}

/* ── Generate badge asynchronously (best-effort) ─────────── */
$badge_url = null;
try {
    $badge_url = badge_url($visit_log_id);
} catch (Throwable $e) {
    // Badge generation failure must not block check-in
    error_log('kiosk badge gen failed: ' . $e->getMessage());
}

log_action('kiosk_checkin', $visit_log_id, json_encode([
    'visitor_id' => $visitor_id,
    'photo'      => !empty($photo_data),
]));

kiosk_json(['ok' => true, 'visit_log_id' => $visit_log_id, 'badge_url' => $badge_url]);
