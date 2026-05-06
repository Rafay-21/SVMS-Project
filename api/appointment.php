<?php
/**
 * api/appointment.php — Appointments CRUD + calendar data
 *
 * GET  ?action=calendar&start=YYYY-MM-DD&end=YYYY-MM-DD[&dept_id=][&status=][&q=]
 * GET  ?action=get&id=
 * GET  ?action=conflict&host=&scheduled_at=&duration_minutes=&exclude_id=
 * POST action=create
 * POST action=update
 * POST action=reschedule   (drag-drop)
 * POST action=cancel
 * POST action=delete        (super_admin only)
 * POST action=resend_epass
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/email_helpers.php';
require_once __DIR__ . '/../includes/qr_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ── CSRF for all POST requests ────────────────────────────────────────────────
if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch.']);
        exit;
    }
} else {
    $body = [];
}

$action = sanitize($_GET['action'] ?? ($body['action'] ?? 'list'));

// ─────────────────────────────────────────────────────────────────────────────
// GET: calendar range
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'calendar') {
    $start   = sanitize($_GET['start']   ?? date('Y-m-d'));
    $end     = sanitize($_GET['end']     ?? date('Y-m-d', strtotime('+6 days')));
    $dept_id = (int)($_GET['dept_id']   ?? 0);
    $status  = sanitize($_GET['status'] ?? '');
    $q       = sanitize($_GET['q']      ?? '');

    $where  = ['a.scheduled_at >= ?', 'a.scheduled_at < DATE_ADD(?, INTERVAL 1 DAY)'];
    $types  = 'ss';
    $params = [$start, $end];

    if ($dept_id > 0)   { $where[] = 'a.department_id = ?'; $types .= 'i'; $params[] = $dept_id; }
    if ($status !== '')  { $where[] = 'a.status = ?';        $types .= 's'; $params[] = $status;  }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(a.visitor_name LIKE ? OR a.person_to_meet LIKE ? OR a.host_name LIKE ?)';
        $types .= 'sss';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $rows = query_all(
        "SELECT a.id, a.visitor_id, a.visitor_name, a.phone, a.email,
                a.department_id, d.name AS dept_name, COALESCE(d.colour,'#2e75b6') AS dept_colour,
                COALESCE(a.person_to_meet, a.host_name) AS person_to_meet,
                a.host_name, a.purpose, a.notes,
                a.scheduled_at, a.duration_minutes, a.status, a.qr_token,
                a.reminder_sent, a.created_at
         FROM appointments a
         LEFT JOIN departments d ON d.id = a.department_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY a.scheduled_at ASC
         LIMIT 500",
        $types, $params
    );
    echo json_encode(['ok' => true, 'appointments' => $rows]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// GET: single appointment
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'Missing id.']); exit; }

    $row = query_one(
        "SELECT a.*, d.name AS dept_name, COALESCE(d.colour,'#2e75b6') AS dept_colour
         FROM appointments a
         LEFT JOIN departments d ON d.id = a.department_id
         WHERE a.id = ? LIMIT 1",
        'i', [$id]
    );
    if (!$row) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Not found.']); exit; }
    echo json_encode(['ok' => true, 'appointment' => $row]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// GET: conflict check
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'conflict') {
    $host       = sanitize($_GET['host']           ?? '');
    $sched      = sanitize($_GET['scheduled_at']   ?? '');
    $dur        = max(1, (int)($_GET['duration_minutes'] ?? 30));
    $exclude_id = (int)($_GET['exclude_id']         ?? 0);

    if (!$host || !$sched) { echo json_encode(['ok' => true, 'conflicts' => []]); exit; }

    $rows = query_all(
        "SELECT id, visitor_name, scheduled_at, duration_minutes
         FROM appointments
         WHERE (person_to_meet = ? OR host_name = ?)
           AND status NOT IN ('cancelled','no_show','completed')
           AND id != ?
           AND scheduled_at < DATE_ADD(?, INTERVAL ? MINUTE)
           AND DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?
         LIMIT 5",
        'ssisss',
        [$host, $host, $exclude_id, $sched, $dur, $sched]
    );
    echo json_encode(['ok' => true, 'conflicts' => $rows]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST: create
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'create') {
    require_permission('manage_appointments');
    global $conn;

    $visitor_id  = ($body['visitor_id'] ?? 0) ? (int)$body['visitor_id'] : null;
    $v_name      = sanitize($body['visitor_name']   ?? '');
    $phone       = sanitize($body['phone']          ?? '');
    $email       = filter_var(trim($body['email']   ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
    $dept_id     = (int)($body['department_id']     ?? 0) ?: null;
    $person_meet = sanitize($body['person_to_meet'] ?? '');
    $purpose     = sanitize($body['purpose']        ?? '');
    $sched       = sanitize($body['scheduled_at']   ?? '');
    $dur         = max(15, min(480, (int)($body['duration_minutes'] ?? 30)));
    $notes       = sanitize($body['notes']          ?? '');
    $send_eml    = !isset($body['send_email']) || !empty($body['send_email']);

    if (!$v_name || !$person_meet || !$sched) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'visitor_name, person_to_meet and scheduled_at are required.']);
        exit;
    }

    $qr_token = bin2hex(random_bytes(16));
    $admin_id = (int)$_SESSION['admin_id'];

    $stmt = $conn->prepare(
        "INSERT INTO appointments
           (visitor_id, visitor_name, phone, email, department_id, person_to_meet,
            host_name, purpose, notes, scheduled_at, duration_minutes,
            status, qr_token, created_by, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,'scheduled',?,?,NOW())"
    );
    $stmt->bind_param('ississssssisi',
        $visitor_id, $v_name, $phone, $email, $dept_id, $person_meet,
        $person_meet, $purpose, $notes, $sched, $dur, $qr_token, $admin_id
    );
    $stmt->execute();
    $appt_id = (int)$conn->insert_id;
    $stmt->close();

    log_action('appointment_created', $appt_id, json_encode([
        'visitor' => $v_name, 'host' => $person_meet, 'sched' => $sched,
    ]));

    // Dept name for email
    $dept_name = '';
    if ($dept_id) {
        $dr = query_one('SELECT name FROM departments WHERE id=? LIMIT 1', 'i', [$dept_id]);
        $dept_name = $dr['name'] ?? '';
    }

    // Generate QR PNG for e-pass
    $qr_path = null;
    try {
        $qr_url  = BASE_URL . 'api/appointment_arrive.php?token=' . $qr_token;
        $qr_dir  = rtrim(UPLOAD_DIR, '/') . '/qr/';
        if (!is_dir($qr_dir)) mkdir($qr_dir, 0755, true);
        $qr_file = $qr_dir . 'appt_qr_' . $qr_token . '.png';
        if (generate_qr_png($qr_url, $qr_file)) $qr_path = $qr_file;
    } catch (\Throwable $ignored) {}

    // Send confirmation email
    $email_result = ['ok' => false, 'queued' => false];
    if ($send_eml && $email) {
        try {
            ['html' => $eh, 'text' => $et] = render_email_template('appointment_confirmation', [
                'visitor_name' => $v_name,
                'scheduled_at' => date('d M Y g:i A', strtotime($sched)),
                'host_name'    => $person_meet,
                'purpose'      => $purpose,
                'department'   => $dept_name,
                'site_name'    => SITE_NAME,
                'year'         => date('Y'),
            ]);
            $att = $qr_path ? [['path' => $qr_path, 'name' => 'appointment-qr.png']] : [];
            $email_result = send_email(
                $email, 'Appointment Confirmed — ' . SITE_NAME, $eh, $et,
                ['attachments' => $att, 'related_type' => 'appointment', 'related_id' => $appt_id]
            );
        } catch (\Throwable $ignored) {}
    }

    echo json_encode([
        'ok'           => true,
        'id'           => $appt_id,
        'qr_token'     => $qr_token,
        'email_sent'   => (bool)($email_result['ok']     ?? false),
        'email_queued' => (bool)($email_result['queued'] ?? false),
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST: update
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'update') {
    require_permission('manage_appointments');

    $id          = (int)($body['id']               ?? 0);
    $v_name      = sanitize($body['visitor_name']   ?? '');
    $phone       = sanitize($body['phone']          ?? '');
    $email       = filter_var(trim($body['email']   ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
    $dept_id     = (int)($body['department_id']     ?? 0) ?: null;
    $person_meet = sanitize($body['person_to_meet'] ?? '');
    $purpose     = sanitize($body['purpose']        ?? '');
    $sched       = sanitize($body['scheduled_at']   ?? '');
    $dur         = max(15, min(480, (int)($body['duration_minutes'] ?? 30)));
    $notes       = sanitize($body['notes']          ?? '');

    if (!$id || !$v_name || !$person_meet || !$sched) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing required fields.']); exit;
    }

    query_exec(
        "UPDATE appointments
            SET visitor_name=?, phone=?, email=?, department_id=?,
                person_to_meet=?, host_name=?, purpose=?, notes=?,
                scheduled_at=?, duration_minutes=?
          WHERE id=?",
        'sssississii',
        [$v_name, $phone, $email, $dept_id,
         $person_meet, $person_meet, $purpose, $notes, $sched, $dur, $id]
    );
    log_action('appointment_updated', $id, json_encode(['sched' => $sched]));
    echo json_encode(['ok' => true]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST: reschedule (drag-drop)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'reschedule') {
    require_permission('manage_appointments');

    $id    = (int)($body['id']          ?? 0);
    $sched = sanitize($body['scheduled_at'] ?? '');
    $dur   = isset($body['duration_minutes']) ? max(15, min(480, (int)$body['duration_minutes'])) : null;

    if (!$id || !$sched) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'id and scheduled_at required.']); exit;
    }

    if ($dur !== null) {
        query_exec('UPDATE appointments SET scheduled_at=?, duration_minutes=? WHERE id=?', 'sii', [$sched, $dur, $id]);
    } else {
        query_exec('UPDATE appointments SET scheduled_at=? WHERE id=?', 'si', [$sched, $id]);
    }
    log_action('appointment_rescheduled', $id, json_encode(['new_sched' => $sched]));
    echo json_encode(['ok' => true]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST: cancel
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'cancel') {
    require_permission('manage_appointments');
    $id = (int)($body['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'Missing id.']); exit; }
    query_exec("UPDATE appointments SET status='cancelled' WHERE id=?", 'i', [$id]);
    log_action('appointment_cancelled', $id);
    echo json_encode(['ok' => true]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST: delete (super_admin only)
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'delete') {
    if (role_slug() !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Permission denied.']); exit;
    }
    $id = (int)($body['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'Missing id.']); exit; }
    query_exec('DELETE FROM appointments WHERE id=?', 'i', [$id]);
    log_action('appointment_deleted', $id);
    echo json_encode(['ok' => true]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// POST: resend e-pass
// ─────────────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'resend_epass') {
    require_permission('manage_appointments');
    $id = (int)($body['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'Missing id.']); exit; }

    $appt = query_one(
        "SELECT a.*, d.name AS dept_name
         FROM appointments a LEFT JOIN departments d ON d.id=a.department_id
         WHERE a.id=? LIMIT 1",
        'i', [$id]
    );
    if (!$appt) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Not found.']); exit; }

    $email = $appt['email'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'No valid email on record for this appointment.']); exit;
    }

    $qr_token = $appt['qr_token'] ?: bin2hex(random_bytes(16));
    if (!$appt['qr_token']) query_exec('UPDATE appointments SET qr_token=? WHERE id=?', 'si', [$qr_token, $id]);

    $qr_path = null;
    try {
        $qr_url  = BASE_URL . 'api/appointment_arrive.php?token=' . $qr_token;
        $qr_dir  = rtrim(UPLOAD_DIR, '/') . '/qr/';
        if (!is_dir($qr_dir)) mkdir($qr_dir, 0755, true);
        $qr_file = $qr_dir . 'appt_qr_' . $qr_token . '.png';
        if (generate_qr_png($qr_url, $qr_file)) $qr_path = $qr_file;
    } catch (\Throwable $ignored) {}

    ['html' => $eh, 'text' => $et] = render_email_template('appointment_confirmation', [
        'visitor_name' => $appt['visitor_name'],
        'scheduled_at' => date('d M Y g:i A', strtotime($appt['scheduled_at'])),
        'host_name'    => $appt['person_to_meet'] ?: $appt['host_name'],
        'purpose'      => $appt['purpose'],
        'department'   => $appt['dept_name'] ?? '',
        'site_name'    => SITE_NAME,
        'year'         => date('Y'),
    ]);
    $att    = $qr_path ? [['path' => $qr_path, 'name' => 'appointment-qr.png']] : [];
    $result = send_email(
        $email, 'Your Appointment e-Pass — ' . SITE_NAME, $eh, $et,
        ['attachments' => $att, 'related_type' => 'appointment', 'related_id' => $id]
    );
    log_action('appointment_epass_resent', $id, json_encode(['to' => $email]));
    echo json_encode(['ok' => $result['ok'] || $result['queued'], 'queued' => $result['queued']]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
