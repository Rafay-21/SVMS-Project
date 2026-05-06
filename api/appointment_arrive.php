<?php
/**
 * api/appointment_arrive.php — Mark appointment arrived + create visit_log
 *
 * Two paths:
 *   GET  ?token=<qr_token>   — kiosk QR scan (enforces ±2h window)
 *   POST {appointment_id}    — admin side drawer (bypasses time window)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/email_helpers.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// ─────────────────────────────────────────────────────────────────────────────
// GET: kiosk QR scan path
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = sanitize($_GET['token'] ?? '');
    if (!$token) {
        header('Location: ' . BASE_URL . 'kiosk/index.php');
        exit;
    }

    $appt = query_one(
        "SELECT a.*, d.name AS dept_name
         FROM appointments a
         LEFT JOIN departments d ON d.id = a.department_id
         WHERE a.qr_token = ? LIMIT 1",
        's', [$token]
    );

    if (!$appt) {
        // Bad token — redirect to kiosk home
        header('Location: ' . BASE_URL . 'kiosk/index.php?error=invalid_token');
        exit;
    }

    if (!in_array($appt['status'], ['scheduled', 'confirmed'], true)) {
        // Already arrived / cancelled / etc.
        header('Location: ' . BASE_URL . 'kiosk/index.php?error=appt_status&status=' . urlencode($appt['status']));
        exit;
    }

    // Enforce ±2 hour window for kiosk path
    $sched_ts = strtotime($appt['scheduled_at']);
    $diff_min = abs(time() - $sched_ts) / 60;
    if ($diff_min > 120) {
        header('Location: ' . BASE_URL . 'kiosk/index.php?error=appt_time_window&name=' . urlencode($appt['visitor_name']));
        exit;
    }

    // If admin session exists, process arrival atomically here;
    // otherwise redirect kiosk to pre-filled identify step
    if (!empty($_SESSION['admin_id'])) {
        _do_arrive((int)$appt['id'], $appt, true);
        exit;
    }

    // Redirect kiosk flow, pre-filling visitor data
    $params = http_build_query([
        'appt_id'    => $appt['id'],
        'appt_token' => $token,
        'name'       => $appt['visitor_name'],
        'phone'      => $appt['phone'],
        'host'       => $appt['person_to_meet'] ?: $appt['host_name'],
        'purpose'    => $appt['purpose'],
        'dept_id'    => $appt['department_id'] ?? '',
    ]);
    header('Location: ' . BASE_URL . 'kiosk/step_identify.php?' . $params);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST: admin side drawer / kiosk arrive confirmation
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // CSRF check — accept JSON body or FormData
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $body['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch.']);
        exit;
    }

    if (empty($_SESSION['admin_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
        exit;
    }

    $appt_id = (int)($body['appointment_id'] ?? ($_POST['appointment_id'] ?? 0));
    if (!$appt_id) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'appointment_id is required.']);
        exit;
    }

    $appt = query_one(
        "SELECT a.*, d.name AS dept_name
         FROM appointments a
         LEFT JOIN departments d ON d.id = a.department_id
         WHERE a.id = ? LIMIT 1",
        'i', [$appt_id]
    );
    if (!$appt) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Appointment not found.']);
        exit;
    }
    if (!in_array($appt['status'], ['scheduled', 'confirmed'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Appointment status is ' . $appt['status'] . ' — cannot check in.']);
        exit;
    }

    _do_arrive($appt_id, $appt, false);
}

// ─────────────────────────────────────────────────────────────────────────────
// Internal: perform the atomic arrive + visit_log creation
// ─────────────────────────────────────────────────────────────────────────────
function _do_arrive(int $appt_id, array $appt, bool $redirect_on_success): void
{
    global $conn;
    $is_json = !$redirect_on_success;

    // Find or create visitor record by phone
    $visitor_id = (int)($appt['visitor_id'] ?? 0);
    if (!$visitor_id && $appt['phone']) {
        $vrow = query_one('SELECT id FROM visitors WHERE phone=? LIMIT 1', 's', [$appt['phone']]);
        $visitor_id = $vrow ? (int)$vrow['id'] : 0;
    }

    // If still no visitor, create a minimal one so visit_log can reference it
    if (!$visitor_id && $appt['visitor_name']) {
        try {
            $qr = bin2hex(random_bytes(16));
            query_exec(
                "INSERT INTO visitors (full_name, phone, email, qr_token, created_at)
                 VALUES (?,?,?,?,NOW())",
                'ssss',
                [$appt['visitor_name'], $appt['phone'], $appt['email'] ?? '', $qr]
            );
            $visitor_id = last_insert_id();
        } catch (\Throwable $e) {
            // Visitor table might have different required columns; log and continue
            error_log('appointment_arrive: could not auto-create visitor: ' . $e->getMessage());
        }
    }

    // Badge number
    $seq_row  = query_one("SELECT COUNT(*)+1 AS seq FROM visit_log WHERE DATE(check_in_time)=CURDATE()");
    $seq      = str_pad((int)($seq_row['seq'] ?? 1), 4, '0', STR_PAD_LEFT);
    $badge    = BADGE_PREFIX . '-' . date('ymd') . '-' . $seq;
    $dept_id  = $appt['department_id'] ? (int)$appt['department_id'] : null;
    $host     = $appt['person_to_meet'] ?: $appt['host_name'];
    $admin_id = (int)($_SESSION['admin_id'] ?? 0);

    // Atomic transaction: update appointment + insert visit_log
    $conn->begin_transaction();
    try {
        // Mark appointment arrived
        $stmt = $conn->prepare(
            "UPDATE appointments SET status='arrived' WHERE id=? AND status IN ('scheduled','confirmed')"
        );
        $stmt->bind_param('i', $appt_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $conn->rollback();
            _arrive_respond($is_json, false, 'Race condition — appointment already updated.', null, null);
            return;
        }

        // Insert visit_log
        $stmt2 = $conn->prepare(
            "INSERT INTO visit_log
               (visitor_id, department_id, person_to_meet, purpose,
                badge_number, visitor_type, check_in_time, status, registered_by)
             VALUES (?,?,?,?,?,'appointment',NOW(),'checked_in',?)"
        );
        $stmt2->bind_param('iisssi',
            $visitor_id, $dept_id, $host, $appt['purpose'], $badge, $admin_id
        );
        $stmt2->execute();
        $visit_log_id = (int)$conn->insert_id;
        $stmt2->close();

        // Notification
        $notif_title = 'Appointment Arrived';
        $notif_msg   = ($appt['visitor_name']) . ' (appointment) checked in. Badge: ' . $badge;
        $notif_link  = BASE_URL . 'pages/visitor_detail.php?id=' . $visit_log_id;
        $conn->query(
            "INSERT INTO notifications (type,title,message,link,recipient_id,is_read,created_at)
             VALUES ('visitor_in'," . $conn->real_escape_string($conn->quote_string ?? '') . "'" .
             $conn->real_escape_string($notif_title) . "','" .
             $conn->real_escape_string($notif_msg) . "','" .
             $conn->real_escape_string($notif_link) . "',NULL,0,NOW())"
        );
        // Use proper prepared statement for notification
        $stmt3 = $conn->prepare(
            "INSERT INTO notifications (type,title,message,link,recipient_id,is_read,created_at)
             VALUES ('visitor_in',?,?,?,NULL,0,NOW())"
        );
        if ($stmt3) {
            $stmt3->bind_param('sss', $notif_title, $notif_msg, $notif_link);
            $stmt3->execute();
            $stmt3->close();
        }

        log_action('appointment_arrived', $appt_id, json_encode([
            'visit_log_id' => $visit_log_id,
            'badge'        => $badge,
            'visitor'      => $appt['visitor_name'],
        ]));

        $conn->commit();

        _arrive_respond($is_json, true, null, $visit_log_id, $badge);
    } catch (\Throwable $e) {
        $conn->rollback();
        error_log('appointment_arrive error: ' . $e->getMessage());
        _arrive_respond($is_json, false, 'System error — please try again.', null, null);
    }
}

function _arrive_respond(bool $is_json, bool $ok, ?string $error, ?int $visit_log_id, ?string $badge): void
{
    if ($is_json) {
        if ($ok) {
            echo json_encode([
                'ok'           => true,
                'visit_log_id' => $visit_log_id,
                'badge_number' => $badge,
                'badge_url'    => BASE_URL . 'pages/visitor_detail.php?id=' . $visit_log_id . '&print=1',
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $error]);
        }
    } else {
        if ($ok) {
            header('Location: ' . BASE_URL . 'kiosk/step_done.php?visit_id=' . $visit_log_id . '&badge=' . urlencode($badge));
        } else {
            header('Location: ' . BASE_URL . 'kiosk/index.php?error=arrive_failed');
        }
    }
    exit;
}
