<?php
/**
 * api/notifications.php — Notifications API (Phase 4.4)
 * GET  ?since=lastId&type=filter&limit=N&page=N  — fetch items (ID-based polling)
 * POST {action:'mark_read',ids:[],csrf_token}    — mark subset read
 * POST {action:'mark_all_read',csrf_token}       — mark all read for this admin
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$role_id  = (int)($_SESSION['role_id'] ?? 0);

// ── Icon & colour maps ────────────────────────────────────────────────────────
function _notif_icon(string $type): string
{
    $map = [
        'visitor_checkin'      => 'person-check-fill',
        'visitor_checkout'     => 'door-closed-fill',
        'blacklist_alert'      => 'slash-circle-fill',
        'emergency'            => 'exclamation-octagon-fill',
        'no_show'              => 'calendar-x-fill',
        'appointment_created'  => 'calendar-plus-fill',
        'system'               => 'gear-fill',
    ];
    return $map[$type] ?? 'bell-fill';
}

function _notif_colour(string $type): string
{
    if (in_array($type, ['visitor_checkin','visitor_checkout'])) return 'var(--success)';
    if (in_array($type, ['blacklist_alert','emergency']))        return 'var(--danger)';
    if ($type === 'system')                                       return 'var(--text-muted)';
    return 'var(--secondary)';
}

function _rel_time(string $created_at): string
{
    $ts   = strtotime($created_at);
    $diff = time() - $ts;
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60)   . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600)  . 'h ago';
    if ($diff < 604800)  return floor($diff / 86400) . 'd ago';
    return date('d M Y', $ts);
}

// ── GET ───────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $since  = max(0, (int)($_GET['since']  ?? 0));
    $type_f = trim($_GET['type']  ?? '');
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $page   = max(1,  (int)($_GET['page']  ?? 1));
    $offset = ($page - 1) * $limit;

    $conditions = [
        "(recipient_id = ? OR recipient_id IS NULL)",
        "(visible_to_role_id IS NULL OR visible_to_role_id = ?)",
        "(type != 'blacklist_alert' OR visible_to_role_id = ?)",
    ];
    $params = [$admin_id, $role_id, $role_id];
    $types  = 'iii';

    if ($since > 0) {
        $conditions[] = 'id > ?';
        $params[]     = $since;
        $types       .= 'i';
    }

    if ($type_f === 'unread') {
        $conditions[] = 'is_read = 0';
    } elseif ($type_f === 'blacklist_alert') {
        $conditions[] = "type = 'blacklist_alert'";
    } elseif ($type_f === 'system') {
        $conditions[] = "type = 'system'";
    } elseif ($type_f && preg_match('/^[a-z_]+$/', $type_f)) {
        $conditions[] = 'type = ?';
        $params[]     = $type_f;
        $types       .= 's';
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);

    // Unread count (independent of since/type)
    $unread_count = (int)(query_one(
        "SELECT COUNT(*) AS c FROM notifications
         WHERE (recipient_id = ? OR recipient_id IS NULL)
           AND (visible_to_role_id IS NULL OR visible_to_role_id = ?)
           AND (type != 'blacklist_alert' OR visible_to_role_id = ?)
           AND is_read = 0",
        'iii', [$admin_id, $role_id, $role_id]
    )['c'] ?? 0);

    $extra_params = array_merge($params, [$limit, $offset]);
    $extra_types  = $types . 'ii';

    $rows = query_all(
        "SELECT id, type, title, message, link, is_read, created_at
         FROM notifications
         $where
         ORDER BY id DESC
         LIMIT ? OFFSET ?",
        $extra_types, $extra_params
    );

    $items   = [];
    $last_id = 0;
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        if ($id > $last_id) $last_id = $id;
        $items[] = [
            'id'         => $id,
            'type'       => $row['type'],
            'title'      => $row['title'],
            'message'    => $row['message'],
            'link'       => $row['link'],
            'is_read'    => (bool)(int)$row['is_read'],
            'created_at' => $row['created_at'],
            'rel_time'   => _rel_time($row['created_at']),
            'icon'       => _notif_icon($row['type']),
            'dot_colour' => _notif_colour($row['type']),
        ];
    }

    echo json_encode([
        'ok'           => true,
        'items'        => $items,
        'unread_count' => $unread_count,
        'last_id'      => $last_id,
    ]);
    exit;
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf_body = $body['csrf_token']           ?? '';
$csrf_hdr  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_body ?: $csrf_hdr)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF validation failed']);
    exit;
}

$action = $body['action'] ?? '';

if ($action === 'mark_read') {
    $ids = array_filter(array_map('intval', (array)($body['ids'] ?? [])));
    if (!$ids) { echo json_encode(['ok' => true]); exit; }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types  = str_repeat('i', count($ids)) . 'ii';
    $params = array_merge(array_values($ids), [$admin_id, $role_id]);

    $stmt = $GLOBALS['conn']->prepare(
        "UPDATE notifications SET is_read=1
         WHERE id IN ($placeholders)
           AND (recipient_id=? OR recipient_id IS NULL)
           AND (visible_to_role_id IS NULL OR visible_to_role_id=?)"
    );
    $bind_args = [$types];
    foreach ($params as &$p) { $bind_args[] = &$p; }
    call_user_func_array([$stmt, 'bind_param'], $bind_args);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'mark_all_read') {
    $stmt = $GLOBALS['conn']->prepare(
        "UPDATE notifications SET is_read=1
         WHERE (recipient_id=? OR recipient_id IS NULL)
           AND (visible_to_role_id IS NULL OR visible_to_role_id=?)
           AND is_read = 0"
    );
    $stmt->bind_param('ii', $admin_id, $role_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);


// ── POST actions ──────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'mark_read') {
        $nid = (int)($_POST['id'] ?? 0);
        if ($nid > 0) {
            query_exec(
                'UPDATE notifications SET is_read=1 WHERE id=? AND (recipient_id=? OR recipient_id IS NULL)',
                'ii', [$nid, $admin_id]
            );
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'mark_all_read') {
        query_exec(
            'UPDATE notifications SET is_read=1 WHERE (recipient_id=? OR recipient_id IS NULL) AND is_read=0',
            'i', [$admin_id]
        );
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// ── GET: poll since=timestamp ─────────────────────────────────────────────────
$since = $_GET['since'] ?? '';
$valid_since = false;
if ($since !== '') {
    // Accept ISO 8601 or MySQL datetime; validate loosely
    $ts = strtotime($since);
    if ($ts !== false && $ts > 0) {
        $valid_since = date('Y-m-d H:i:s', $ts);
    }
}

if ($valid_since) {
    $rows = query_all(
        'SELECT id, title, body, icon, url, is_read, type, created_at
         FROM notifications
         WHERE (recipient_id=? OR recipient_id IS NULL) AND created_at > ?
         ORDER BY created_at DESC LIMIT 20',
        'is', [$admin_id, $valid_since]
    );
} else {
    // Full list (initial load)
    $rows = query_all(
        'SELECT id, title, body, icon, url, is_read, type, created_at
         FROM notifications
         WHERE recipient_id=? OR recipient_id IS NULL
         ORDER BY is_read ASC, created_at DESC LIMIT 20',
        'i', [$admin_id]
    );
}

// Add relative time and sanitise output
$unread_count = 0;
foreach ($rows as &$n) {
    $diff = time() - strtotime($n['created_at'] ?? '');
    if ($diff < 60)       $n['rel_time'] = 'Just now';
    elseif ($diff < 3600) $n['rel_time'] = (int)($diff / 60) . 'm ago';
    elseif ($diff < 86400)$n['rel_time'] = (int)($diff / 3600) . 'h ago';
    else                   $n['rel_time'] = date('M j', strtotime($n['created_at']));

    $n['is_read'] = (bool)$n['is_read'];
    if (!$n['is_read']) $unread_count++;

    // Map type → Bootstrap Icon name
    $n['icon'] = match($n['type'] ?? '') {
        'visitor_checkin'  => 'person-check-fill',
        'visitor_checkout' => 'door-closed-fill',
        'blacklist_alert'  => 'slash-circle-fill',
        'emergency'        => 'exclamation-octagon-fill',
        'system'           => 'gear-fill',
        default            => 'bell-fill',
    };
}
unset($n);

echo json_encode([
    'notifications' => $rows,
    'unread_count'  => $unread_count,
    'server_time'   => date('c'),
]);
