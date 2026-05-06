<?php
/**
 * api/blacklist.php — Blacklist CRUD API
 * All mutation actions accept JSON body + CSRF token.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!can('manage_blacklist')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ── Auto-expire entries past their expiry_date ────────────────────────────────
// (lightweight — runs at most once per request, fast index hit)
$GLOBALS['conn']->query(
    "UPDATE blacklist SET is_active=0
     WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() AND is_active=1"
);

// ── GET actions ───────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    // GET action=get&id=X — single entry
    if ($action === 'get') {
        $id  = (int)($_GET['id'] ?? 0);
        $row = query_one(
            "SELECT b.*, CONCAT_WS(' ', a.full_name) AS added_by_name,
                    rb.full_name AS removed_by_name
             FROM blacklist b
             LEFT JOIN admins a  ON a.id = b.added_by
             LEFT JOIN admins rb ON rb.id = b.removed_by
             WHERE b.id = ?",
            'i', [$id]
        );
        echo json_encode(['ok' => (bool)$row, 'entry' => $row]);
        exit;
    }

    // GET action=history&id=X — audit trail
    if ($action === 'history') {
        $id  = (int)($_GET['id'] ?? 0);
        $row = query_one("SELECT phone, cnic FROM blacklist WHERE id=?", 'i', [$id]);
        if (!$row) { echo json_encode(['ok' => false, 'error' => 'Not found']); exit; }

        // Audit log entries for this blacklist row
        $logs = query_all(
            "SELECT al.action, al.details, al.ip_address, al.created_at,
                    a.full_name AS admin_name
             FROM audit_logs al
             LEFT JOIN admins a ON a.id = al.admin_id
             WHERE al.target_id = ? AND al.action LIKE 'blacklist_%'
             ORDER BY al.created_at DESC LIMIT 100",
            'i', [$id]
        );

        // Block attempts matching this identifier
        $phone = $row['phone'];
        $cnic  = $row['cnic'];
        $blocks = [];
        if ($phone || $cnic) {
            $likePhone = '%"phone":"' . $GLOBALS['conn']->real_escape_string($phone) . '"%';
            $likeCnic  = '%"cnic":"'  . $GLOBALS['conn']->real_escape_string($cnic)  . '"%';
            $blocks = query_all(
                "SELECT action, details, ip_address, created_at,
                        (SELECT full_name FROM admins WHERE id=al.admin_id) AS admin_name
                 FROM audit_logs al
                 WHERE action IN ('registration_blocked','checkin_blocked','blacklist_blocked')
                   AND (details LIKE ? OR details LIKE ?)
                 ORDER BY created_at DESC LIMIT 50",
                'ss', [$likePhone, $likeCnic]
            );
        }

        echo json_encode(['ok' => true, 'logs' => $logs, 'blocks' => $blocks]);
        exit;
    }

    // GET action=list (default) — paginated list with filters
    $show_inactive = ($_GET['show_inactive'] ?? '0') === '1';
    $q             = trim($_GET['q']        ?? '');
    $severity      = trim($_GET['severity'] ?? '');
    $page          = max(1, (int)($_GET['page'] ?? 1));
    $per_page      = 25;
    $offset        = ($page - 1) * $per_page;

    $conditions = [];
    $params     = [];
    $types      = '';

    if (!$show_inactive) {
        $conditions[] = 'b.is_active = 1';
    }
    if ($q !== '') {
        $like         = '%' . $q . '%';
        $conditions[] = '(b.name LIKE ? OR b.phone LIKE ? OR b.cnic LIKE ?)';
        $params       = array_merge($params, [$like, $like, $like]);
        $types       .= 'sss';
    }
    if ($severity && in_array($severity, ['low','medium','high','critical'], true)) {
        $conditions[] = 'b.severity = ?';
        $params[]     = $severity;
        $types       .= 's';
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $total = (int)(query_one(
        "SELECT COUNT(*) AS c FROM blacklist b $where", $types, $params
    )['c'] ?? 0);

    $params[] = $per_page;
    $params[] = $offset;
    $types   .= 'ii';

    $entries = query_all(
        "SELECT b.id, b.name, b.phone, b.cnic, b.severity, b.reason, b.notes,
                b.source, b.expiry_date, b.is_active, b.block_count,
                b.added_at, b.removed_at, b.removed_reason,
                a.full_name AS added_by_name,
                rb.full_name AS removed_by_name
         FROM blacklist b
         LEFT JOIN admins a  ON a.id = b.added_by
         LEFT JOIN admins rb ON rb.id = b.removed_by
         $where
         ORDER BY b.is_active DESC, b.added_at DESC
         LIMIT ? OFFSET ?",
        $types, $params
    );

    echo json_encode([
        'ok'      => true,
        'entries' => $entries,
        'total'   => $total,
        'page'    => $page,
        'pages'   => max(1, (int)ceil($total / $per_page)),
    ]);
    exit;
}

// ── POST actions ──────────────────────────────────────────────────────────────
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Read JSON body
$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf_body  = $body['csrf_token'] ?? '';
$csrf_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_body ?: $csrf_header)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF validation failed']);
    exit;
}

$action   = $body['action'] ?? '';
$admin_id = (int)$_SESSION['admin_id'];

// ── ADD ───────────────────────────────────────────────────────────────────────
if ($action === 'add') {
    $name     = substr(trim($body['name']   ?? ''), 0, 120);
    $phone    = substr(trim($body['phone']  ?? ''), 0, 30);
    $cnic     = substr(trim($body['cnic']   ?? ''), 0, 20);
    $severity = in_array($body['severity'] ?? '', ['low','medium','high','critical'])
                ? $body['severity'] : 'medium';
    $reason   = trim($body['reason'] ?? '');
    $notes    = trim($body['notes']  ?? '');
    $source   = in_array($body['source'] ?? '', ['internal','lea_notice','court_order','self_blocked','other'])
                ? $body['source'] : 'internal';
    $expiry   = $body['expiry_date'] ?? '';
    $expiry   = ($expiry && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) ? $expiry : null;

    // Validation
    if (!$phone && !$cnic) {
        echo json_encode(['ok' => false, 'error' => 'At least one of phone or CNIC is required.']);
        exit;
    }
    if (strlen($reason) < 20) {
        echo json_encode(['ok' => false, 'error' => 'Reason must be at least 20 characters.']);
        exit;
    }
    if (!in_array($severity, ['low','medium','high','critical'])) {
        echo json_encode(['ok' => false, 'error' => 'Invalid severity.']);
        exit;
    }

    $stmt = $GLOBALS['conn']->prepare(
        "INSERT INTO blacklist (name, phone, cnic, severity, reason, notes, source,
                               expiry_date, added_by, added_at, is_active)
         VALUES (?,?,?,?,?,?,?,?,?,NOW(),1)"
    );
    $stmt->bind_param('ssssssssi',
        $name, $phone, $cnic, $severity, $reason, $notes, $source, $expiry, $admin_id
    );
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();

    log_action('blacklist_add', $new_id, json_encode([
        'name' => $name, 'phone' => $phone, 'cnic' => $cnic, 'severity' => $severity
    ]));

    // High/critical severity → create notification for security + super_admin
    if (in_array($severity, ['high','critical'])) {
        _blacklist_notify($new_id, $name ?: $phone ?: $cnic, $severity, $reason, $admin_id);
    }

    echo json_encode(['ok' => true, 'id' => $new_id]);
    exit;
}

// ── EDIT ──────────────────────────────────────────────────────────────────────
if ($action === 'edit') {
    $id       = (int)($body['id'] ?? 0);
    $name     = substr(trim($body['name']   ?? ''), 0, 120);
    $phone    = substr(trim($body['phone']  ?? ''), 0, 30);
    $cnic     = substr(trim($body['cnic']   ?? ''), 0, 20);
    $severity = in_array($body['severity'] ?? '', ['low','medium','high','critical'])
                ? $body['severity'] : 'medium';
    $reason   = trim($body['reason'] ?? '');
    $notes    = trim($body['notes']  ?? '');
    $source   = in_array($body['source'] ?? '', ['internal','lea_notice','court_order','self_blocked','other'])
                ? $body['source'] : 'internal';
    $expiry   = $body['expiry_date'] ?? '';
    $expiry   = ($expiry && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) ? $expiry : null;

    if (!$id) { echo json_encode(['ok' => false, 'error' => 'Invalid ID']); exit; }
    if (!$phone && !$cnic) {
        echo json_encode(['ok' => false, 'error' => 'At least one of phone or CNIC is required.']);
        exit;
    }
    if (strlen($reason) < 20) {
        echo json_encode(['ok' => false, 'error' => 'Reason must be at least 20 characters.']);
        exit;
    }

    $stmt = $GLOBALS['conn']->prepare(
        "UPDATE blacklist SET name=?, phone=?, cnic=?, severity=?, reason=?,
                              notes=?, source=?, expiry_date=?
         WHERE id=?"
    );
    $stmt->bind_param('ssssssssi',
        $name, $phone, $cnic, $severity, $reason, $notes, $source, $expiry, $id
    );
    $stmt->execute();
    $stmt->close();

    log_action('blacklist_edit', $id, json_encode([
        'name' => $name, 'phone' => $phone, 'cnic' => $cnic, 'severity' => $severity
    ]));

    echo json_encode(['ok' => true]);
    exit;
}

// ── TOGGLE ACTIVE ─────────────────────────────────────────────────────────────
if ($action === 'toggle') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'Invalid ID']); exit; }

    $row = query_one("SELECT is_active FROM blacklist WHERE id=?", 'i', [$id]);
    if (!$row) { echo json_encode(['ok' => false, 'error' => 'Not found']); exit; }

    $new_val = $row['is_active'] ? 0 : 1;
    query_exec("UPDATE blacklist SET is_active=? WHERE id=?", 'ii', [$new_val, $id]);
    log_action($new_val ? 'blacklist_reactivate' : 'blacklist_deactivate', $id);

    echo json_encode(['ok' => true, 'is_active' => (bool)$new_val]);
    exit;
}

// ── REMOVE (soft delete) ──────────────────────────────────────────────────────
if ($action === 'remove') {
    $id     = (int)($body['id'] ?? 0);
    $reason = trim($body['removed_reason'] ?? '');
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'Invalid ID']); exit; }

    $stmt = $GLOBALS['conn']->prepare(
        "UPDATE blacklist SET is_active=0, removed_by=?, removed_at=NOW(), removed_reason=?
         WHERE id=?"
    );
    $stmt->bind_param('isi', $admin_id, $reason, $id);
    $stmt->execute();
    $stmt->close();

    log_action('blacklist_remove', $id, json_encode(['removed_reason' => $reason]));
    echo json_encode(['ok' => true]);
    exit;
}

// ── INCREMENT block_count ─────────────────────────────────────────────────────
// Called internally by registration/checkin when a block is triggered
if ($action === 'block_hit') {
    $id = (int)($body['id'] ?? 0);
    if ($id) query_exec("UPDATE blacklist SET block_count=block_count+1 WHERE id=?", 'i', [$id]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);

// ── Helper: create high-severity notification ─────────────────────────────────
function _blacklist_notify(int $bl_id, string $identifier, string $severity, string $reason, int $added_by): void
{
    // Find role IDs for security and super_admin
    $roles = query_all(
        "SELECT id FROM roles WHERE slug IN ('super_admin','security')", '', []
    );
    if (empty($roles)) return;

    $title   = strtoupper($severity) . ' blacklist alert: ' . $identifier;
    $message = substr($reason, 0, 200);
    $link    = 'pages/blacklist.php';

    foreach ($roles as $r) {
        $rid = (int)$r['id'];
        $stmt = $GLOBALS['conn']->prepare(
            "INSERT INTO notifications (type, title, message, link, recipient_id,
                                       visible_to_role_id, is_read, created_at)
             VALUES ('blacklist_alert',?,?,?,NULL,?,0,NOW())"
        );
        $stmt->bind_param('sssi', $title, $message, $link, $rid);
        $stmt->execute();
        $stmt->close();
    }
}
