<?php
/**
 * includes/rbac.php
 * Role-Based Access Control — Phase 2 (DB-backed).
 *
 * Roles and their permissions are stored in the `roles` table:
 *   roles(id, slug, label, permissions JSON)
 *
 * Permissions JSON example:
 *   {"view_dashboard":true,"manage_users":true,"register_visitor":true,...}
 *
 * Session keys consumed:
 *   $_SESSION['role_id']      — integer FK to roles.id
 *   $_SESSION['permissions']  — decoded JSON array cached on login
 */

/**
 * Cache permissions from DB into session for the current role.
 * Call once after successful login (after role_id is set).
 */
function cache_permissions(): void {
    $role_id = (int)($_SESSION['role_id'] ?? 0);
    if (!$role_id) {
        $_SESSION['permissions'] = [];
        return;
    }
    $row = query_one('SELECT permissions FROM roles WHERE id = ? LIMIT 1', 'i', [$role_id]);
    if ($row && !empty($row['permissions'])) {
        $_SESSION['permissions'] = json_decode($row['permissions'], true) ?: [];
    } else {
        $_SESSION['permissions'] = [];
    }
}

/**
 * Re-read permissions from DB and refresh the session cache.
 * Call after a role is updated.
 */
function refresh_permissions(): void {
    cache_permissions();
}

/**
 * Check whether the current user has a given permission.
 *
 * @param  string $permission_key  e.g. 'manage_users'
 * @return bool
 */
function can(string $permission_key): bool {
    // Permissions must be loaded; fall back to false if not.
    $perms = $_SESSION['permissions'] ?? null;
    if (!is_array($perms)) return false;
    return ($perms[$permission_key] ?? false) === true;
}

/**
 * Enforce a permission gate.
 * Renders a styled 403 page and exits if permission is denied.
 *
 * @param string $permission_key
 */
function require_permission(string $permission_key): void {
    if (can($permission_key)) return;

    http_response_code(403);
    // Determine if header has already been included by checking for a constant
    $already_in_layout = (defined('SVMS_HEADER_INCLUDED') && SVMS_HEADER_INCLUDED);
    if (!$already_in_layout):
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_SESSION['theme'] ?? 'light', ENT_QUOTES) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>403 Forbidden — <?= defined('SITE_NAME') ? SITE_NAME : 'SVMS' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <?php if (defined('BASE_URL')): ?>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tokens.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/base.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/themes.css">
  <?php endif; ?>
  <style>
    body { min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg,#f8fafc);font-family:'Inter',sans-serif;margin:0; }
    .forbidden-card { text-align:center;padding:48px 40px;background:var(--card,#fff);border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.1);max-width:480px;width:100%; }
    .forbidden-icon { font-size:72px;color:var(--danger,#ef4444);margin-bottom:20px;line-height:1; }
    .forbidden-code { font-size:80px;font-weight:800;color:var(--border,#e2e8f0);line-height:1;margin-bottom:8px; }
    .forbidden-title { font-size:24px;font-weight:700;color:var(--text,#1e293b);margin:0 0 10px; }
    .forbidden-msg   { font-size:15px;color:var(--text-muted,#64748b);margin:0 0 28px;line-height:1.6; }
    .btn-back { display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:#1a3c5e;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;text-decoration:none;cursor:pointer; }
    .btn-back:hover { background:#152e4d; }
  </style>
</head>
<body>
<?php endif; ?>
  <div class="forbidden-card" role="main">
    <div class="forbidden-code">403</div>
    <div class="forbidden-icon"><i class="bi bi-shield-x-fill"></i></div>
    <h1 class="forbidden-title">Access Forbidden</h1>
    <p class="forbidden-msg">You don't have permission to view this page.<br>Contact your administrator if you believe this is a mistake.</p>
    <?php if (defined('BASE_URL')): ?>
    <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn-back">
      <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
    <?php endif; ?>
  </div>
<?php if (!$already_in_layout): ?>
</body>
</html>
<?php
    endif;
    exit;
}

/**
 * Return the display label for a role by its integer ID (from session/DB).
 * Falls back to DB lookup if the label is not cached.
 *
 * @param  int $role_id
 * @return string
 */
function role_label(int $role_id): string {
    // Common labels — avoids a DB round-trip for standard roles
    static $cache = [];
    if (isset($cache[$role_id])) return $cache[$role_id];

    $row = query_one('SELECT label FROM roles WHERE id = ? LIMIT 1', 'i', [$role_id]);
    $label = $row['label'] ?? 'Role #' . $role_id;
    $cache[$role_id] = $label;
    return $label;
}

/**
 * Return the role slug (e.g. 'super_admin') for a role_id.
 */
function role_slug(int $role_id): string {
    static $slugCache = [];
    if (isset($slugCache[$role_id])) return $slugCache[$role_id];

    $row = query_one('SELECT slug FROM roles WHERE id = ? LIMIT 1', 'i', [$role_id]);
    $slug = $row['slug'] ?? '';
    $slugCache[$role_id] = $slug;
    return $slug;
}
