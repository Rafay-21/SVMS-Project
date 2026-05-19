<?php
/**
 * pages/visitor_detail.php
 * Deep-dive view of a single visit + visitor lifetime history.
 * ?id={visit_log_id}  ?token={qr_token}  &new=1  &print=1
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/badge_helpers.php';
require_permission('view_history');

/* ── Token-based lookup (QR badge scan) ─────────────────────── */
if (isset($_GET['token']) && !isset($_GET['id'])) {
    $tok = preg_replace('/[^a-f0-9]/i', '', substr($_GET['token'], 0, 64));
    if ($tok) {
        $tv = query_one(
            "SELECT vl.id FROM visit_log vl JOIN visitors v ON v.id=vl.visitor_id WHERE v.qr_token=? ORDER BY vl.check_in_time DESC LIMIT 1",
            's', [$tok]
        );
        if ($tv) {
            header('Location: ' . BASE_URL . 'pages/visitor_detail.php?id=' . (int)$tv['id']);
            exit;
        }
    }
    // Token not found — show generic 404 below via visit_log_id = 0
}

/* ── Validate ID ─────────────────────────────────────────────── */
$visit_log_id = (int)($_GET['id'] ?? 0);
if (!$visit_log_id) {
    header('Location: ' . BASE_URL . 'pages/visitor_history.php');
    exit;
}

/* ── Load visit_log + visitor ────────────────────────────────── */
$visit = query_one(
    "SELECT
        vl.id              AS visit_log_id,
        vl.visitor_id,
        vl.department_id,
        vl.host_name       AS person_to_meet,
        vl.purpose,
        vl.vehicle_number,
        v.badge_number,
        ''                 AS visitor_type,
        vl.check_in_time,
        vl.check_out_time,
        vl.status,
        vl.registered_by,
        v.name             AS full_name,
        v.cnic,
        v.phone,
        v.email,
        v.photo_path,
        COALESCE(v.vip, 0) AS vip,
        v.qr_token,
        v.notes            AS custom_data,
        v.created_at       AS visitor_since,
        COALESCE(d.name,'—')        AS dept_name,
        CASE WHEN vl.registered_by IS NULL OR vl.registered_by = 0 THEN 'Self-Service Kiosk'
             WHEN a.full_name IS NOT NULL THEN a.full_name ELSE '—' END AS registered_by_name,
        CASE WHEN vl.registered_by IS NULL OR vl.registered_by = 0 THEN 'kiosk'
             WHEN r.label IS NOT NULL THEN r.label ELSE '—' END AS registered_by_role,
        TIMESTAMPDIFF(MINUTE, vl.check_in_time,
            COALESCE(vl.check_out_time, NOW()))  AS duration_min
     FROM visit_log vl
     JOIN visitors v         ON v.id  = vl.visitor_id
     LEFT JOIN departments d ON d.id  = vl.department_id
     LEFT JOIN admins a      ON a.id  = vl.registered_by
     LEFT JOIN roles r       ON r.id  = a.role_id
     WHERE vl.id = ?
     LIMIT 1",
    'i', [$visit_log_id]
);

if (!$visit) {
    http_response_code(404);
    ?><!DOCTYPE html>
    <html><head><title>404 Not Found</title></head>
    <body style="font-family:sans-serif;text-align:center;padding:80px;">
      <h1 style="font-size:72px;color:#e2e8f0;margin:0;">404</h1>
      <h2>Visit not found</h2>
      <p>This visit record may have been deleted or the link is incorrect.</p>
      <a href="<?= BASE_URL ?>pages/visitor_history.php">← Back to History</a>
    </body></html>
    <?php
    exit;
}

$visitor_id = (int)$visit['visitor_id'];
$is_active  = $visit['status'] === 'checked_in';

/* ── Current user role ───────────────────────────────────────── */
$my_role_slug  = role_slug((int)($_SESSION['role_id'] ?? 0));
$is_super_admin = $my_role_slug === 'super_admin';

/* ── Handle EDIT save (POST) ─────────────────────────────────── */
$edit_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_visit') {
    if (!$is_super_admin) {
        http_response_code(403); exit;
    }
    csrf_validate();

    $upd_dept    = (int)($_POST['department_id']  ?? 0);
    $upd_person  = trim($_POST['person_to_meet']  ?? '');
    $upd_purpose = trim($_POST['purpose']         ?? '');
    $upd_vehicle = strtoupper(trim($_POST['vehicle_number'] ?? ''));
    $upd_type    = trim($_POST['visitor_type']    ?? 'walk_in');
    $upd_status  = trim($_POST['status']          ?? $visit['status']);

    // Validate
    if (strlen($upd_person)  < 2)  $edit_errors[] = 'Person to meet is required.';
    if (strlen($upd_purpose) < 2)  $edit_errors[] = 'Purpose is required.';
    $allowed_statuses = ['checked_in','checked_out','no_show'];
    if (!in_array($upd_status, $allowed_statuses)) $edit_errors[] = 'Invalid status.';
    $allowed_types = ['walk_in','appointment','delivery','vendor','contractor','vip'];
    if (!in_array($upd_type, $allowed_types)) $edit_errors[] = 'Invalid visitor type.';

    if (empty($edit_errors)) {
        // Build diff JSON for audit
        $diff = [];
        $old_vals = ['department_id' => $visit['department_id'], 'person_to_meet' => $visit['person_to_meet'],
                     'purpose' => $visit['purpose'], 'vehicle_number' => $visit['vehicle_number'],
                     'visitor_type' => $visit['visitor_type'], 'status' => $visit['status']];
        $new_vals = ['department_id' => $upd_dept, 'person_to_meet' => $upd_person,
                     'purpose' => $upd_purpose, 'vehicle_number' => $upd_vehicle,
                     'visitor_type' => $upd_type, 'status' => $upd_status];
        foreach ($new_vals as $k => $v) {
            if ((string)$old_vals[$k] !== (string)$v) $diff[$k] = ['from' => $old_vals[$k], 'to' => $v];
        }

        query_exec(
            "UPDATE visit_log SET department_id=?, host_name=?, purpose=?, vehicle_number=?, status=? WHERE id=?",
            'isssi',
            [$upd_dept ?: null, $upd_person, $upd_purpose, $upd_vehicle ?: null, $upd_status, $visit_log_id]
        );

        if ($diff) {
            log_action('edit_visit', $visit_log_id, json_encode($diff));
        }

        flash('success', 'Visit record updated successfully.');
        header('Location: ' . BASE_URL . 'pages/visitor_detail.php?id=' . $visit_log_id);
        exit;
    }
}

/* ── Handle VIP toggle (POST, Super Admin only) ─────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_vip') {
    if (!$is_super_admin) { http_response_code(403); exit; }
    csrf_validate();
    $new_vip = $visit['vip'] ? 0 : 1;
    query_exec("UPDATE visitors SET vip=? WHERE id=?", 'ii', [$new_vip, $visitor_id]);
    log_action('toggle_vip', $visitor_id, json_encode(['vip' => $new_vip, 'visitor_id' => $visitor_id]));
    flash($new_vip ? 'success' : 'info', $visit['full_name'] . ($new_vip ? ' marked as VIP.' : ' VIP status removed.'));
    header('Location: ' . BASE_URL . 'pages/visitor_detail.php?id=' . $visit_log_id);
    exit;
}

/* ── Total visit count for visitor ──────────────────────────── */
$total_visits_row = query_one("SELECT COUNT(*) AS cnt FROM visit_log WHERE visitor_id=?", 'i', [$visitor_id]);
$total_visits = (int)($total_visits_row['cnt'] ?? 0);

/* ── Full visit timeline for this visitor ────────────────────── */
$all_visits = query_all(
    "SELECT vl.id, vl.department_id, vl.host_name AS person_to_meet, vl.purpose,
            vl.check_in_time, vl.check_out_time, vl.status, v.badge_number,
            COALESCE(d.name,'—') AS dept_name,
            TIMESTAMPDIFF(MINUTE, vl.check_in_time,
                COALESCE(vl.check_out_time, NOW())) AS duration_min
     FROM visit_log vl
     JOIN visitors v ON v.id = vl.visitor_id
     LEFT JOIN departments d ON d.id = vl.department_id
     WHERE vl.visitor_id = ?
     ORDER BY vl.check_in_time DESC",
    'i', [$visitor_id]
);

/* ── Feedback for this specific visit ────────────────────────── */
$feedback = query_one(
    "SELECT f.rating, f.comment AS notes, f.created_at, f.source AS by_name
     FROM feedback f
     WHERE f.visit_id = ?
     LIMIT 1",
    'i', [$visit_log_id]
);

/* ── Audit log entries for this visit ────────────────────────── */
$audit_entries = query_all(
    "SELECT al.action, al.details, al.ip_address, al.created_at,
            COALESCE(a.full_name,'System') AS admin_name,
            COALESCE(r.label,'—') AS role_label
     FROM audit_logs al
     LEFT JOIN admins a ON a.id = al.admin_id
     LEFT JOIN roles r  ON r.id = a.role_id
     WHERE al.target_id = ? AND al.action REGEXP 'check|register|edit|visit'
     ORDER BY al.created_at DESC
     LIMIT 50",
    'i', [$visit_log_id]
);

/* ── Custom fields ───────────────────────────────────────────── */
$custom_fields = query_all(
    "SELECT field_name, label, field_type FROM custom_fields WHERE is_active=1 ORDER BY sort_order ASC, id ASC",
    '', []
);
$custom_data = [];
if ($visit['custom_data']) {
    $custom_data = json_decode($visit['custom_data'], true) ?: [];
}

/* ── Departments (for edit form) ─────────────────────────────── */
$departments = query_all("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name");

/* ── Duration string ─────────────────────────────────────────── */
$dur_min = (int)$visit['duration_min'];
$h = (int)floor($dur_min / 60); $m = $dur_min % 60;
$dur_str = $h > 0 ? "{$h}h {$m}m" : "{$m}m";

$page_title = 'Visit — ' . $visit['full_name'];
include __DIR__ . '/../includes/header.php';

// Inline check-out reuses the modal from checkin_checkout
$csrf_js = csrf_token_for_js();
?>

<?php if (!empty($edit_errors)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  <?php foreach ($edit_errors as $err): ?>
  if (window.SVMS && SVMS.toast) SVMS.toast(<?= json_encode($err) ?>, 'error');
  <?php endforeach; ?>
  document.getElementById('edit-toggle-btn')?.click();
});
</script>
<?php endif; ?>

<!-- ── Inline Check-Out Modal (same UX as checkin_checkout) ─── -->
<div class="modal-backdrop" id="co-modal" role="dialog" aria-modal="true"
     aria-labelledby="co-modal-title" aria-hidden="true" style="display:none;">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h5 class="modal-title" id="co-modal-title">
        <i class="bi bi-box-arrow-right" style="color:var(--secondary);margin-right:6px;"></i>Confirm Check-Out
      </h5>
      <button class="modal-close" id="co-modal-close" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <div style="display:flex;align-items:center;gap:14px;padding:12px 16px;
           background:var(--bg);border-radius:8px;border:1px solid var(--border);margin-bottom:16px;">
        <div style="width:48px;height:48px;border-radius:50%;overflow:hidden;flex-shrink:0;
             background:linear-gradient(135deg,var(--secondary),var(--accent));
             display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#fff;">
          <?php
          $ini = strtoupper(substr($visit['full_name'], 0, 1));
          $pts = explode(' ', $visit['full_name']);
          if (isset($pts[1])) $ini .= strtoupper(substr($pts[1], 0, 1));
          ?>
          <?php if ($visit['photo_path']): ?>
          <img src="<?= BASE_URL ?>assets/uploads/<?= e($visit['photo_path']) ?>"
               style="width:48px;height:48px;object-fit:cover;" alt="">
          <?php else: ?>
          <?= e($ini) ?>
          <?php endif; ?>
        </div>
        <div>
          <div style="font-weight:700;font-size:15px;"><?= e($visit['full_name']) ?></div>
          <div style="font-size:12px;color:var(--text-muted);"><?= e($visit['badge_number']) ?></div>
        </div>
      </div>
      <div style="margin-bottom:16px;">
        <label style="font-size:13px;font-weight:600;display:block;margin-bottom:8px;">
          Experience Rating <span style="font-weight:400;color:var(--text-muted);">(optional)</span>
        </label>
        <div id="co-stars" style="display:flex;gap:6px;">
          <?php for ($s=1;$s<=5;$s++): ?>
          <button type="button" class="co-star-btn" data-star="<?= $s ?>"
                  style="font-size:28px;color:var(--border);background:none;border:none;cursor:pointer;padding:2px;transition:color .1s,transform .1s;line-height:1;">★</button>
          <?php endfor; ?>
        </div>
      </div>
      <div class="form-group">
        <label for="co-notes" style="font-size:13px;font-weight:600;">Notes <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
        <textarea id="co-notes" class="form-control" rows="2" maxlength="500" placeholder="Any remarks…"></textarea>
      </div>
    </div>
    <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end;">
      <button class="btn btn-secondary" id="co-modal-cancel">Cancel</button>
      <button class="btn btn-primary" id="co-modal-confirm" style="min-width:140px;">
        <span id="co-modal-label"><i class="bi bi-box-arrow-right"></i> Confirm Check-Out</span>
        <span id="co-modal-spinner" style="display:none;">
          <span class="spinner-border spinner-border-sm"></span> Processing…
        </span>
      </button>
    </div>
  </div>
</div>

<style>
/* ── Two-column layout ──────────────────────────────────────── */
.vd-grid {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 20px;
  align-items: start;
}
@media (max-width: 900px) {
  .vd-grid { grid-template-columns: 1fr; }
}

/* ── Timeline ───────────────────────────────────────────────── */
.tl-rail { position:relative; padding-left:32px; }
.tl-rail::before {
  content:''; position:absolute; left:10px; top:0; bottom:0;
  width:2px; background:var(--border);
}
.tl-item { position:relative; margin-bottom:20px; }
.tl-dot {
  position:absolute; left:-27px; top:4px;
  width:14px; height:14px; border-radius:50%;
  border:2px solid var(--secondary); background:var(--card);
}
.tl-dot.active   { background:var(--success); border-color:var(--success); }
.tl-dot.checkout { background:var(--card);     border-color:var(--secondary); }
.tl-dot.noshow   { background:var(--card);     border-color:var(--border); }
.tl-item:last-child { margin-bottom:0; }

/* ── Star rating (display) ──────────────────────────────────── */
.star-display { font-size:20px; color:#fbbf24; letter-spacing:1px; }
.star-display .empty { color:var(--border); }

/* ── Edit field toggle ──────────────────────────────────────── */
.vd-view  { display:block; }
.vd-edit  { display:none; }
body.vd-editing .vd-view { display:none; }
body.vd-editing .vd-edit { display:block; }
body.vd-editing #btn-edit { display:none; }
body.vd-editing #btn-save-row { display:flex; }
#btn-save-row { display:none; gap:8px; }

/* ── Print ──────────────────────────────────────────────────── */
@media print {
  .no-print, .modal-backdrop, #btn-save-row, #edit-toggle-btn { display:none !important; }
  .vd-grid { grid-template-columns:1fr 2fr; gap:12px; }
  .card { box-shadow:none !important; border:1px solid #e2e8f0 !important; }
  .tl-rail { font-size:11px; }
}

.co-star-btn.lit { color:#f59e0b !important; }
.co-star-btn:hover { transform:scale(1.15); }
</style>

<div class="container" style="padding-bottom:56px;">

  <!-- ── HEADER STRIP ────────────────────────────────────────── -->
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
    <a href="<?= BASE_URL ?>pages/visitor_history.php" class="btn btn-secondary btn-sm no-print">
      <i class="bi bi-arrow-left"></i> Back
    </a>
    <div style="flex:1;min-width:0;">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <h1 style="font-size:1.3rem;font-weight:700;margin:0;color:var(--text);">Visit Details</h1>
        <span style="font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;background:var(--bg);border:1px solid var(--border);color:var(--text-muted);">
          #<?= $visit_log_id ?>
        </span>
        <?php if ($is_active): ?>
        <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;">
          <span style="display:inline-block;width:7px;height:7px;background:#22c55e;border-radius:50%;margin-right:4px;animation:pulse-dot 1.4s infinite;"></span>Active
        </span>
        <?php elseif ($visit['status'] === 'checked_out'): ?>
        <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:#dcfce7;color:#166534;">
          <i class="bi bi-check-circle-fill" style="margin-right:3px;"></i>Checked Out
        </span>
        <?php else: ?>
        <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:#f1f5f9;color:#475569;">No Show</span>
        <?php endif; ?>
      </div>
    </div>
    <!-- Action buttons -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;" class="no-print">
      <?php if ($is_active): ?>
      <button type="button" id="co-open-btn" class="btn btn-primary btn-sm">
        <i class="bi bi-box-arrow-right"></i> Check Out
      </button>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>pages/print_badge.php?id=<?= $visit_log_id ?>" target="_blank" class="btn btn-secondary btn-sm">
        <i class="bi bi-printer"></i> Print Badge
      </a>
      <a href="<?= BASE_URL ?>pages/print_badge.php?id=<?= $visit_log_id ?>&regen=1" class="btn btn-secondary btn-sm" title="Regenerate badge PNG">
        <i class="bi bi-arrow-clockwise"></i> Reprint
      </a>
      <?php if ($is_super_admin): ?>
      <button type="button" id="edit-toggle-btn" class="btn btn-secondary btn-sm" id="btn-edit">
        <i class="bi bi-pencil"></i> Edit
      </button>
      <?php endif; ?>
    </div>
    <?php if ($is_super_admin): ?>
    <!-- Save/Cancel row (hidden unless editing) -->
    <div id="btn-save-row" class="no-print">
      <button type="submit" form="edit-form" class="btn btn-primary btn-sm">
        <i class="bi bi-check-lg"></i> Save Changes
      </button>
      <button type="button" id="edit-cancel-btn" class="btn btn-secondary btn-sm">
        Cancel
      </button>
    </div>
    <?php endif; ?>
  </div>

  <?= render_flash() ?>

  <!-- ── MAIN GRID ─────────────────────────────────────────── -->
  <div class="vd-grid">

    <!-- LEFT: Visitor card -->
    <div>
      <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);">
        <!-- Photo -->
        <div style="padding:28px 20px 16px;text-align:center;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);">
          <div style="width:90px;height:90px;border-radius:50%;overflow:hidden;margin:0 auto 12px;
               border:3px solid rgba(255,255,255,.4);background:rgba(255,255,255,.2);
               display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:#fff;">
            <?php if ($visit['photo_path']): ?>
            <img src="<?= BASE_URL ?>assets/uploads/<?= e($visit['photo_path']) ?>"
                 style="width:90px;height:90px;object-fit:cover;" alt="">
            <?php else: ?>
            <?= e($ini) ?>
            <?php endif; ?>
          </div>
          <div style="font-weight:700;font-size:16px;color:#fff;line-height:1.2;">
            <?= e($visit['full_name']) ?>
            <?php if ($visit['vip']): ?>
            <span style="font-size:16px;margin-left:4px;">⭐</span>
            <?php endif; ?>
          </div>
          <div style="font-size:11px;color:rgba(255,255,255,.7);margin-top:4px;letter-spacing:1px;">
            <?= e($visit['badge_number']) ?>
          </div>
          <?php if ($is_super_admin): ?>
          <form method="post" action="" style="margin-top:10px;">
            <?php csrf_field() ?>
            <input type="hidden" name="action" value="toggle_vip">
            <button type="submit" class="btn btn-sm"
                    style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;"
                    onclick="return confirm('<?= $visit['vip'] ? 'Remove VIP status from' : 'Mark' ?> <?= e(addslashes($visit['full_name'])) ?><?= $visit['vip'] ? '' : ' as VIP' ?>?')">
              <?= $visit['vip'] ? '✕ Remove VIP' : '⭐ Mark as VIP' ?>
            </button>
          </form>
          <?php endif; ?>
        </div>
        <!-- Details -->
        <div style="padding:16px 20px;">
          <dl style="display:grid;grid-template-columns:auto 1fr;gap:8px 12px;font-size:13px;margin:0;">
            <dt style="color:var(--text-muted);font-weight:500;white-space:nowrap;">Phone</dt>
            <dd>
              <?php if ($visit['phone']): ?>
              <a href="tel:<?= e($visit['phone']) ?>" style="color:var(--secondary);font-weight:600;"><?= e($visit['phone']) ?></a>
              <?php else: ?>—<?php endif; ?>
            </dd>
            <dt style="color:var(--text-muted);font-weight:500;">Email</dt>
            <dd>
              <?php if ($visit['email']): ?>
              <a href="mailto:<?= e($visit['email']) ?>" style="color:var(--secondary);"><?= e($visit['email']) ?></a>
              <?php else: ?>—<?php endif; ?>
            </dd>
            <dt style="color:var(--text-muted);font-weight:500;">CNIC</dt>
            <dd><?= e($visit['cnic']) ?: '—' ?></dd>
            <dt style="color:var(--text-muted);font-weight:500;">Member since</dt>
            <dd><?= format_datetime($visit['visitor_since'], 'M d, Y') ?></dd>
            <dt style="color:var(--text-muted);font-weight:500;">Total visits</dt>
            <dd><strong style="color:var(--secondary);"><?= $total_visits ?></strong></dd>
          </dl>
        </div>
      </div>

      <!-- Feedback card -->
      <?php if ($feedback): ?>
      <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px 20px;margin-top:16px;box-shadow:0 2px 8px rgba(0,0,0,.04);">
        <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">
          <i class="bi bi-star-fill" style="color:#fbbf24;margin-right:4px;"></i>Visit Feedback
        </div>
        <div style="font-size:22px;letter-spacing:2px;margin-bottom:6px;">
          <?php for ($s=1; $s<=5; $s++): ?>
          <span style="color:<?= $s <= (int)$feedback['rating'] ? '#f59e0b' : 'var(--border)'; ?>;">★</span>
          <?php endfor; ?>
          <span style="font-size:13px;font-weight:700;color:var(--text);margin-left:4px;"><?= (int)$feedback['rating'] ?>/5</span>
        </div>
        <?php if ($feedback['notes']): ?>
        <blockquote style="margin:8px 0 0;padding:8px 12px;border-left:3px solid var(--secondary);background:var(--bg);border-radius:0 6px 6px 0;font-size:13px;color:var(--text);font-style:italic;">
          "<?= e($feedback['notes']) ?>"
        </blockquote>
        <?php endif; ?>
        <div style="font-size:11px;color:var(--text-muted);margin-top:8px;">by <?= e($feedback['by_name']) ?> · <?= format_datetime($feedback['created_at'], 'M d, Y g:i A') ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- RIGHT: Visit details + timeline -->
    <div>
      <!-- Visit details card -->
      <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.04);margin-bottom:20px;">
        <div style="padding:18px 20px 12px;border-bottom:1px solid var(--border);">
          <div style="font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;">
            <i class="bi bi-info-circle-fill" style="color:var(--secondary);"></i>Visit Details
          </div>
        </div>

        <!-- View mode -->
        <div class="vd-view" style="padding:16px 20px;">
          <dl style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:0;" class="vd-dl">
            <div>
              <dt style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Department</dt>
              <dd style="font-size:14px;font-weight:500;color:var(--text);"><?= e($visit['dept_name']) ?></dd>
            </div>
            <div>
              <dt style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Person to Meet</dt>
              <dd style="font-size:14px;font-weight:500;color:var(--text);"><?= e($visit['person_to_meet']) ?></dd>
            </div>
            <div style="grid-column:span 2;">
              <dt style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Purpose</dt>
              <dd style="font-size:14px;font-weight:500;color:var(--text);"><?= e($visit['purpose']) ?></dd>
            </div>
            <div>
              <dt style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Vehicle</dt>
              <dd style="font-size:14px;font-weight:500;color:var(--text);"><?= e($visit['vehicle_number']) ?: '—' ?></dd>
            </div>
            <div>
              <dt style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Visitor Type</dt>
              <dd style="font-size:14px;font-weight:500;color:var(--text);"><?= ucfirst(str_replace('_',' ',$visit['visitor_type'])) ?></dd>
            </div>
            <div>
              <dt style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Check-In</dt>
              <dd style="font-size:14px;font-weight:500;color:var(--text);"><?= format_datetime($visit['check_in_time'], 'M d, Y g:i A') ?></dd>
            </div>
            <div>
              <dt style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Check-Out / Duration</dt>
              <dd style="font-size:14px;font-weight:500;color:var(--text);">
                <?php if ($is_active): ?>
                <span id="live-elapsed" style="color:var(--secondary);font-weight:700;"
                      data-checkin="<?= e($visit['check_in_time']) ?>">
                  <?= time_elapsed($visit['check_in_time']) ?> (live)
                </span>
                <?php else: ?>
                <?= format_datetime($visit['check_out_time'], 'g:i A') ?> <span style="color:var(--text-muted);">(<?= $dur_str ?>)</span>
                <?php endif; ?>
              </dd>
            </div>
            <div style="grid-column:span 2;">
              <dt style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Registered By</dt>
              <dd style="font-size:14px;font-weight:500;color:var(--text);">
                <?= e($visit['registered_by_name']) ?>
                <span style="font-size:11px;background:<?= $visit['registered_by_role']==='kiosk' ? '#dbeafe' : 'var(--bg)' ?>;border:1px solid <?= $visit['registered_by_role']==='kiosk' ? '#93c5fd' : 'var(--border)' ?>;border-radius:20px;padding:1px 8px;margin-left:6px;color:<?= $visit['registered_by_role']==='kiosk' ? '#1e40af' : 'var(--text-muted)' ?>;">
                  <?php if ($visit['registered_by_role'] === 'kiosk'): ?><i class="bi bi-display" style="margin-right:3px;"></i>Self-service<?php else: ?><?= e($visit['registered_by_role']) ?><?php endif; ?>
                </span>
              </dd>
            </div>
            <?php if ($custom_fields && $custom_data): ?>
            <?php foreach ($custom_fields as $cf):
              $val = $custom_data[$cf['field_name']] ?? null;
              if ($val === null || $val === '') continue;
              $display_label = !empty($cf['label']) ? $cf['label'] : ucwords(str_replace('_', ' ', $cf['field_name']));
            ?>
            <div>
              <dt style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;"><?= e($display_label) ?></dt>
              <dd style="font-size:14px;font-weight:500;color:var(--text);"><?= e(is_array($val) ? implode(', ', $val) : $val) ?></dd>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </dl>
        </div>

        <!-- Edit mode (Super Admin only) -->
        <?php if ($is_super_admin): ?>
        <form id="edit-form" method="post" action="" class="vd-edit" style="padding:16px 20px;">
          <input type="hidden" name="action" value="edit_visit">
          <?= csrf_field() ?>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group" style="margin:0;">
              <label style="font-size:12px;font-weight:600;">Department</label>
              <select name="department_id" class="form-control">
                <option value="">— None —</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= (int)$visit['department_id'] === (int)$d['id'] ? 'selected' : '' ?>>
                  <?= e($d['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin:0;">
              <label style="font-size:12px;font-weight:600;">Person to Meet <span style="color:var(--danger);">*</span></label>
              <input type="text" name="person_to_meet" class="form-control"
                     value="<?= e($visit['person_to_meet']) ?>" maxlength="100" required>
            </div>
            <div class="form-group" style="margin:0;grid-column:span 2;">
              <label style="font-size:12px;font-weight:600;">Purpose <span style="color:var(--danger);">*</span></label>
              <input type="text" name="purpose" class="form-control"
                     value="<?= e($visit['purpose']) ?>" maxlength="500" required>
            </div>
            <div class="form-group" style="margin:0;">
              <label style="font-size:12px;font-weight:600;">Vehicle Number</label>
              <input type="text" name="vehicle_number" class="form-control"
                     value="<?= e($visit['vehicle_number']) ?>" maxlength="20" style="text-transform:uppercase;">
            </div>
            <div class="form-group" style="margin:0;">
              <label style="font-size:12px;font-weight:600;">Visitor Type</label>
              <select name="visitor_type" class="form-control">
                <?php foreach (['walk_in','appointment','delivery','vendor','contractor','vip'] as $vt): ?>
                <option value="<?= $vt ?>" <?= $visit['visitor_type'] === $vt ? 'selected' : '' ?>>
                  <?= ucfirst(str_replace('_',' ',$vt)) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin:0;">
              <label style="font-size:12px;font-weight:600;">Status</label>
              <select name="status" class="form-control">
                <option value="checked_in"  <?= $visit['status'] === 'checked_in'  ? 'selected' : '' ?>>Active (Checked In)</option>
                <option value="checked_out" <?= $visit['status'] === 'checked_out' ? 'selected' : '' ?>>Checked Out</option>
                <option value="no_show"     <?= $visit['status'] === 'no_show'     ? 'selected' : '' ?>>No Show</option>
              </select>
            </div>
          </div>
          <p style="font-size:11px;color:var(--text-muted);margin-top:10px;margin-bottom:0;">
            <i class="bi bi-shield-check" style="color:var(--secondary);"></i>
            All edits are logged to the audit trail.
          </p>
        </form>
        <?php endif; ?>
      </div>

      <!-- ── TIMELINE ──────────────────────────────────────── -->
      <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.04);margin-bottom:20px;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;">
          <i class="bi bi-activity" style="color:var(--secondary);"></i>
          <span style="font-size:13px;font-weight:700;color:var(--text);">All Visits for this Visitor</span>
          <span style="font-size:11px;background:var(--secondary);color:#fff;padding:1px 8px;border-radius:20px;font-weight:700;"><?= count($all_visits) ?></span>
        </div>
        <div style="padding:20px 20px 12px;max-height:360px;overflow-y:auto;">
          <?php if (empty($all_visits)): ?>
          <p style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px 0;">No visits on record.</p>
          <?php else: ?>
          <div class="tl-rail">
            <?php foreach ($all_visits as $tl):
              $tl_h = (int)floor((int)$tl['duration_min'] / 60);
              $tl_m = (int)$tl['duration_min'] % 60;
              $tl_dur = $tl_h > 0 ? "{$tl_h}h {$tl_m}m" : "{$tl_m}m";
              $tl_isCurrent = (int)$tl['id'] === $visit_log_id;
            ?>
            <div class="tl-item">
              <div class="tl-dot <?= $tl['status'] === 'checked_in' ? 'active' : ($tl['status'] === 'no_show' ? 'noshow' : 'checkout') ?>"
                   <?= $tl_isCurrent ? 'style="background:var(--secondary);border-color:var(--secondary);"' : '' ?>></div>
              <a href="<?= BASE_URL ?>pages/visitor_detail.php?id=<?= (int)$tl['id'] ?>"
                 style="text-decoration:none;display:block;padding:10px 14px;border-radius:8px;border:1px solid <?= $tl_isCurrent ? 'var(--secondary)' : 'var(--border)' ?>;background:<?= $tl_isCurrent ? 'rgba(46,117,182,.06)' : 'var(--bg)' ?>;transition:background .1s;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                  <div style="font-size:13px;font-weight:<?= $tl_isCurrent ? 700 : 500 ?>;color:var(--text);">
                    <?= e($tl['dept_name']) ?> — <?= e($tl['person_to_meet']) ?>
                    <?php if ($tl_isCurrent): ?>
                    <span style="font-size:10px;background:var(--secondary);color:#fff;padding:1px 6px;border-radius:10px;margin-left:4px;">current</span>
                    <?php endif; ?>
                  </div>
                  <div style="font-size:11px;color:var(--text-muted);white-space:nowrap;">
                    <?php if ($tl['status'] === 'checked_in'): ?>
                    <span style="color:var(--success);font-weight:600;">Active</span>
                    <?php else: ?>
                    <?= $tl_dur ?>
                    <?php endif; ?>
                  </div>
                </div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">
                  <?= format_datetime($tl['check_in_time'], 'M d, Y g:i A') ?>
                  <?php if ($tl['status'] === 'checked_out' && $tl['check_out_time']): ?>
                  → <?= format_datetime($tl['check_out_time'], 'g:i A') ?>
                  <?php endif; ?>
                </div>
              </a>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── AUDIT TRAIL (collapsible) ─────────────────────── -->
      <?php if (!empty($audit_entries)): ?>
      <details style="background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.04);overflow:hidden;" class="no-print">
        <summary style="padding:16px 20px;cursor:pointer;font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;list-style:none;user-select:none;">
          <i class="bi bi-shield-lock" style="color:var(--secondary);"></i>
          Audit Trail <span style="font-size:11px;background:var(--bg);border:1px solid var(--border);padding:1px 8px;border-radius:20px;color:var(--text-muted);"><?= count($audit_entries) ?> entries</span>
          <i class="bi bi-chevron-down" style="margin-left:auto;font-size:11px;color:var(--text-muted);"></i>
        </summary>
        <div style="border-top:1px solid var(--border);overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:var(--bg);">
                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--text-muted);white-space:nowrap;">Timestamp</th>
                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--text-muted);">Admin</th>
                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--text-muted);">Action</th>
                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--text-muted);">IP</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($audit_entries as $ae): ?>
              <tr style="border-top:1px solid var(--border);">
                <td style="padding:8px 12px;color:var(--text-muted);white-space:nowrap;"><?= format_datetime($ae['created_at'], 'M d, Y g:i A') ?></td>
                <td style="padding:8px 12px;">
                  <span style="font-weight:600;color:var(--text);"><?= e($ae['admin_name']) ?></span>
                  <span style="font-size:10px;color:var(--text-muted);display:block;"><?= e($ae['role_label']) ?></span>
                </td>
                <td style="padding:8px 12px;">
                  <code style="font-size:11px;background:var(--bg);padding:2px 6px;border-radius:4px;border:1px solid var(--border);"><?= e($ae['action']) ?></code>
                </td>
                <td style="padding:8px 12px;color:var(--text-muted);font-family:monospace;"><?= e($ae['ip_address']) ?></td>
              </tr>
              <?php if ($ae['details']): ?>
              <tr style="background:var(--bg);">
                <td colspan="4" style="padding:6px 12px 10px 32px;">
                  <pre style="margin:0;font-size:11px;color:var(--text-muted);overflow-x:auto;white-space:pre-wrap;word-break:break-all;"><?= e(json_encode(json_decode($ae['details']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </td>
              </tr>
              <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>
      <?php endif; ?>

    </div><!-- /right col -->
  </div><!-- /grid -->
</div><!-- /container -->

<script>
(function () {
  var visitLogId = <?= $visit_log_id ?>;
  var csrfToken  = <?= json_encode($csrf_js) ?>;
  var baseUrl    = <?= json_encode(BASE_URL) ?>;

  /* ── Edit mode toggle ─────────────────────────────────────── */
  var editBtn    = document.getElementById('edit-toggle-btn');
  var cancelBtn  = document.getElementById('edit-cancel-btn');
  if (editBtn) {
    editBtn.addEventListener('click', function () {
      document.body.classList.add('vd-editing');
    });
  }
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
      document.body.classList.remove('vd-editing');
    });
  }

  /* ── Live elapsed ticker (if active) ─────────────────────── */
  var liveEl = document.getElementById('live-elapsed');
  if (liveEl) {
    var checkin = liveEl.dataset.checkin;
    function tick() {
      var diff = Math.floor((Date.now() - new Date(checkin).getTime()) / 1000);
      var h = Math.floor(diff / 3600);
      var m = Math.floor((diff % 3600) / 60);
      liveEl.textContent = (h > 0 ? h + 'h ' + m + 'm' : m + 'm') + ' (live)';
    }
    tick();
    setInterval(tick, 30000);
  }

  /* ── Star rating widget in modal ─────────────────────────── */
  var coRating = null;
  document.querySelectorAll('.co-star-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      coRating = parseInt(this.dataset.star);
      highlightStars(coRating);
    });
    btn.addEventListener('mouseenter', function () { highlightStars(parseInt(this.dataset.star)); });
    btn.addEventListener('mouseleave', function () { highlightStars(coRating || 0); });
  });
  function highlightStars(n) {
    document.querySelectorAll('.co-star-btn').forEach(function (b) {
      b.classList.toggle('lit', parseInt(b.dataset.star) <= n);
    });
  }

  /* ── Check-out modal ─────────────────────────────────────── */
  var coModal   = document.getElementById('co-modal');
  var coOpenBtn = document.getElementById('co-open-btn');
  function openCoModal() {
    if (!coModal) return;
    coRating = null; highlightStars(0);
    document.getElementById('co-notes').value = '';
    document.getElementById('co-modal-label').style.display  = 'inline';
    document.getElementById('co-modal-spinner').style.display = 'none';
    document.getElementById('co-modal-confirm').disabled = false;
    coModal.style.display = 'flex';
    coModal.setAttribute('aria-hidden', 'false');
    document.getElementById('co-modal-confirm').focus();
  }
  function closeCoModal() {
    if (!coModal) return;
    coModal.style.display = 'none';
    coModal.setAttribute('aria-hidden', 'true');
  }
  if (coOpenBtn) coOpenBtn.addEventListener('click', openCoModal);
  document.getElementById('co-modal-close')?.addEventListener('click', closeCoModal);
  document.getElementById('co-modal-cancel')?.addEventListener('click', closeCoModal);
  coModal?.addEventListener('click', function (e) { if (e.target === coModal) closeCoModal(); });

  document.getElementById('co-modal-confirm')?.addEventListener('click', function () {
    var payload = {
      csrf_token:   csrfToken,
      visit_log_id: visitLogId,
      rating:       coRating,
      notes:        (document.getElementById('co-notes')?.value || '').trim(),
    };
    document.getElementById('co-modal-label').style.display  = 'none';
    document.getElementById('co-modal-spinner').style.display = 'inline';
    document.getElementById('co-modal-confirm').disabled = true;

    fetch(baseUrl + 'api/checkout.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.ok) {
        window.location.reload();
      } else {
        document.getElementById('co-modal-label').style.display  = 'inline';
        document.getElementById('co-modal-spinner').style.display = 'none';
        document.getElementById('co-modal-confirm').disabled = false;
        if (window.SVMS && SVMS.toast) SVMS.toast(data.error || 'Check-out failed.', 'error');
      }
    })
    .catch(function () {
      document.getElementById('co-modal-label').style.display  = 'inline';
      document.getElementById('co-modal-spinner').style.display = 'none';
      document.getElementById('co-modal-confirm').disabled = false;
      if (window.SVMS && SVMS.toast) SVMS.toast('Network error. Please try again.', 'error');
    });
  });

  /* ── Esc closes modal ────────────────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && coModal && coModal.style.display !== 'none') closeCoModal();
  });

  /* ── Auto-print ──────────────────────────────────────────── */
  if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', function () { window.print(); });
  }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
