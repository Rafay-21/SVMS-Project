<?php
/**
 * api/kiosk_checkout.php
 * POST — kiosk check-out + optional emoji rating.
 * Can be called with rating_only=true from step_done to save feedback after redirect.
 * Returns JSON: {ok:true}
 */
require_once __DIR__ . '/../kiosk/kiosk_boot.php';

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

$visit_log_id = (int)($body['visit_log_id'] ?? 0);
$rating       = isset($body['rating']) ? (int)$body['rating'] : null;
$notes        = trim($body['notes'] ?? '');
$ratingOnly   = !empty($body['rating_only']);

if ($visit_log_id < 1) {
    kiosk_json(['ok' => false, 'error' => 'Invalid visit ID.'], 422);
}

/* ── Load visit_log ───────────────────────────────────────── */
$visit = query_one(
    "SELECT vl.id, vl.visitor_id, vl.status
     FROM visit_log vl
     WHERE vl.id = ?",
    [$visit_log_id]
);
if (!$visit) {
    kiosk_json(['ok' => false, 'error' => 'Visit record not found.'], 404);
}

/* ── Check-out (skip if rating_only) ─────────────────────── */
if (!$ratingOnly) {
    if ($visit['status'] !== 'checked_in') {
        kiosk_json(['ok' => false, 'error' => 'This visit is not currently checked in.'], 409);
    }

    query_exec(
        "UPDATE visit_log SET status = 'checked_out', check_out_time = NOW() WHERE id = ? AND status = 'checked_in'",
        [$visit_log_id]
    );

    log_action('kiosk_checkout', $visit_log_id, json_encode([
        'visitor_id' => (int)$visit['visitor_id'],
        'rating'     => $rating,
    ]));
}

/* ── Save feedback/rating if provided ────────────────────── */
if ($rating !== null && $rating >= 1 && $rating <= 5) {
    // Upsert: one feedback per visit
    $existing = query_one(
        "SELECT id FROM feedback WHERE visit_log_id = ? LIMIT 1",
        [$visit_log_id]
    );
    if ($existing) {
        query_exec(
            "UPDATE feedback SET rating = ?, notes = ?, submitted_at = NOW() WHERE id = ?",
            [$rating, $notes ?: null, (int)$existing['id']]
        );
    } else {
        query_exec(
            "INSERT INTO feedback (visit_log_id, visitor_id, rating, notes, submitted_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$visit_log_id, (int)$visit['visitor_id'], $rating, $notes ?: null]
        );
    }
}

kiosk_json(['ok' => true]);
