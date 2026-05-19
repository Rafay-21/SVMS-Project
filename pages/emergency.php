<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('emergency_control');
$page_title = 'Emergency Control';

$current_mode = get_setting('emergency_mode', 'normal');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = sanitize($_POST['action'] ?? '');

    /* ── Mode change ─────────────────────────────────────── */
    if ($action === 'set_mode') {
        $mode = sanitize($_POST['mode'] ?? '');
        if (in_array($mode, ['normal', 'evacuation', 'lockdown'], true)) {
            update_setting('emergency_mode', $mode);
            if ($mode !== 'normal') {
                $_SESSION['emergency_snapshot_at'] = time();
            } else {
                unset($_SESSION['emergency_snapshot_at']);
            }
            log_action('emergency_mode_change', 0, json_encode(['mode' => $mode]));
            flash($mode === 'normal' ? 'success' : 'error', 'Emergency mode set to: ' . strtoupper($mode));
        }
        header('Location: ' . BASE_URL . 'pages/emergency.php');
        exit;
    }

    /* ── Mass checkout ───────────────────────────────────── */
    if ($action === 'mass_checkout') {
        $confirm = sanitize($_POST['confirm_phrase'] ?? '');
        if ($confirm !== 'CONFIRM CHECKOUT') {
            flash('error', 'Confirmation phrase did not match. No action taken.');
            header('Location: ' . BASE_URL . 'pages/emergency.php');
            exit;
        }
        $affected = query_exec(
            "UPDATE visit_log SET status='checked_out', check_out_time=NOW()
             WHERE status='checked_in'"
        );
        log_action('emergency_mass_checkout', 0, json_encode(['count' => $affected, 'mode' => $current_mode]));
        query_exec(
            "INSERT INTO notifications (type, title, message, link, recipient_id, is_read, created_at)
             VALUES ('emergency_mass_checkout', 'Emergency Mass Check-Out', ?, ?, NULL, 0, NOW())",
            'ss',
            [
                $affected . ' visitor(s) marked safe during emergency evacuation.',
                BASE_URL . 'pages/emergency.php',
            ]
        );
        flash('success', $affected . ' visitor(s) checked out. All marked safe during evacuation.');
        header('Location: ' . BASE_URL . 'pages/emergency.php');
        exit;
    }
}

/* ── Active visitor count ────────────────────────────────────── */
$active_row   = query_one("SELECT COUNT(*) cnt FROM visit_log WHERE status='checked_in'");
$active_count = (int)($active_row['cnt'] ?? 0);

/* ── Active visitors for snapshot ───────────────────────────── */
$active_visitors = query_all(
    "SELECT vl.id, v.badge_number, vl.check_in_time, vl.host_name AS person_to_meet,
            vl.vehicle_number, '' AS visitor_type,
            v.name AS full_name, v.photo_path, v.phone, 0 AS vip,
            COALESCE(d.name,'—') AS dept_name
     FROM visit_log vl
     JOIN visitors v         ON v.id = vl.visitor_id
     LEFT JOIN departments d ON d.id = vl.department_id
     WHERE vl.status = 'checked_in'
     ORDER BY d.name ASC, vl.check_in_time ASC"
);

include __DIR__ . '/../includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="bi bi-exclamation-octagon-fill" style="color:var(--danger);"></i> Emergency Control</h1>
      <p class="page-subtitle">Manage emergency protocols and occupant counts.</p>
    </div>
  </div>

  <?php if ($current_mode !== 'normal'): ?>
  <div class="alert alert-error" style="font-size:var(--text-lg);font-weight:600;text-align:center;padding:20px;">
    <i class="alert-icon bi bi-exclamation-octagon-fill"></i>
    <div class="alert-body">
      EMERGENCY MODE ACTIVE: <?= strtoupper(e($current_mode)) ?>
      &mdash; <?= $active_count ?> visitor(s) on premises
    </div>
  </div>
  <?php endif; ?>

  <div class="grid-2">
    <!-- Current status -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Current Status</h3></div>
      <div class="card-body" style="text-align:center;padding:40px;">
        <?php
          $mode_color = match($current_mode) { 'evacuation' => 'var(--warning)', 'lockdown' => 'var(--danger)', default => 'var(--success)' };
          $mode_icon  = match($current_mode) { 'evacuation' => 'bi-door-open-fill', 'lockdown' => 'bi-lock-fill', default => 'bi-shield-check-fill' };
        ?>
        <i class="bi <?= $mode_icon ?>" style="font-size:80px;color:<?= $mode_color ?>;"></i>
        <h2 style="margin-top:16px;color:<?= $mode_color ?>;"><?= strtoupper(e($current_mode)) ?></h2>
        <p style="color:var(--text-muted);font-size:var(--text-lg);">
          <strong><?= $active_count ?></strong> visitor(s) currently on premises
        </p>
        <a href="<?= BASE_URL ?>pages/checkin_checkout.php" class="btn btn-outline btn-lg" style="margin-top:16px;">
          <i class="bi bi-people-fill"></i> View Active Visitors
        </a>
      </div>
    </div>

    <!-- Controls -->
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="bi bi-sliders" style="color:var(--secondary);"></i> Emergency Controls</h3></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:16px;padding:24px;">
        <form method="POST" action="">
          <?php csrf_field() ?>
          <input type="hidden" name="action" value="set_mode">
          <input type="hidden" name="mode" value="evacuation">
          <button type="submit"
                  class="btn btn-warning btn-block btn-lg"
                  onclick="return confirm('Activate EVACUATION mode? This will alert all security staff.')"
                  <?= $current_mode === 'evacuation' ? 'disabled' : '' ?>>
            <i class="bi bi-door-open-fill"></i> Activate Evacuation
          </button>
        </form>
        <form method="POST" action="">
          <?php csrf_field() ?>
          <input type="hidden" name="action" value="set_mode">
          <input type="hidden" name="mode" value="lockdown">
          <button type="submit"
                  class="btn btn-danger btn-block btn-lg"
                  onclick="return confirm('Activate LOCKDOWN? No visitors will be permitted entry.')"
                  <?= $current_mode === 'lockdown' ? 'disabled' : '' ?>>
            <i class="bi bi-lock-fill"></i> Activate Lockdown
          </button>
        </form>
        <hr style="border-color:var(--divider);">
        <form method="POST" action="">
          <?php csrf_field() ?>
          <input type="hidden" name="action" value="set_mode">
          <input type="hidden" name="mode" value="normal">
          <button type="submit"
                  class="btn btn-success btn-block"
                  onclick="return confirm('Clear emergency mode and return to normal operations?')"
                  <?= $current_mode === 'normal' ? 'disabled' : '' ?>>
            <i class="bi bi-shield-check-fill"></i> Clear Emergency — Return to Normal
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Instructions -->
  <div class="card" style="margin-top:var(--space-5);">
    <div class="card-header"><h3 class="card-title"><i class="bi bi-info-circle-fill" style="color:var(--info);"></i> Emergency Protocols</h3></div>
    <div class="card-body">
      <dl style="display:grid;grid-template-columns:140px 1fr;gap:12px 16px;font-size:var(--text-sm);">
        <dt style="font-weight:600;color:var(--warning);">EVACUATION</dt>
        <dd>All visitors must exit immediately. Kiosk will display evacuation message. Security to escort all personnel to muster points.</dd>
        <dt style="font-weight:600;color:var(--danger);">LOCKDOWN</dt>
        <dd>No new visitors permitted. Existing visitors must shelter in place. Contact law enforcement if necessary.</dd>
        <dt style="font-weight:600;color:var(--success);">NORMAL</dt>
        <dd>Standard operations. All visitor check-in/out functions available.</dd>
      </dl>
    </div>
  </div>

  <!-- ── Evacuation Snapshot ─────────────────────────────────── -->
  <div class="card" style="margin-top:var(--space-5);" id="snapshot-card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <h3 class="card-title"><i class="bi bi-clipboard2-data-fill" style="color:var(--warning);"></i> Evacuation Snapshot</h3>
      <button type="button" id="print-snapshot-btn" class="btn btn-warning btn-sm"
              onclick="window.print()">
        <i class="bi bi-printer-fill"></i> Print Snapshot
      </button>
    </div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 16px;">
        All <strong style="color:var(--text);"><?= $active_count ?></strong> visitor(s) currently on premises.
        Generated: <strong><?= date('d M Y g:i A') ?></strong>
      </p>
      <?php if (empty($active_visitors)): ?>
      <div style="text-align:center;padding:32px;color:var(--text-muted);">
        <i class="bi bi-person-check-fill" style="font-size:40px;opacity:.3;"></i>
        <p style="margin:10px 0 0;">No visitors currently on premises.</p>
      </div>
      <?php else: ?>
      <?php
        // Group by department
        $by_dept = [];
        foreach ($active_visitors as $av) {
          $by_dept[$av['dept_name']][] = $av;
        }
        ksort($by_dept);
      ?>
      <?php foreach ($by_dept as $dept_name => $dept_visitors): ?>
      <div style="margin-bottom:20px;">
        <h4 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--secondary);border-bottom:2px solid var(--border);padding-bottom:6px;margin:0 0 10px;">
          <i class="bi bi-building"></i> <?= e($dept_name) ?> (<?= count($dept_visitors) ?>)
        </h4>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
              <tr style="background:var(--bg);">
                <th style="padding:6px 10px;text-align:left;border-bottom:1px solid var(--border);font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;">Photo</th>
                <th style="padding:6px 10px;text-align:left;border-bottom:1px solid var(--border);font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;">Name</th>
                <th style="padding:6px 10px;text-align:left;border-bottom:1px solid var(--border);font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;">Badge</th>
                <th style="padding:6px 10px;text-align:left;border-bottom:1px solid var(--border);font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;">Host</th>
                <th style="padding:6px 10px;text-align:left;border-bottom:1px solid var(--border);font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;">Check-In</th>
                <th style="padding:6px 10px;text-align:left;border-bottom:1px solid var(--border);font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;">Vehicle</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dept_visitors as $av):
                $initials = strtoupper(substr($av['full_name'], 0, 1));
                $pts = explode(' ', $av['full_name']);
                if (isset($pts[1])) $initials .= strtoupper(substr($pts[1], 0, 1));
              ?>
              <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:6px 10px;">
                  <?php if ($av['photo_path']): ?>
                  <img src="<?= BASE_URL ?>assets/uploads/<?= e($av['photo_path']) ?>"
                       style="width:36px;height:36px;border-radius:50%;object-fit:cover;" alt="">
                  <?php else: ?>
                  <span style="display:inline-flex;width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--secondary),var(--accent));align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;"><?= e($initials) ?></span>
                  <?php endif; ?>
                </td>
                <td style="padding:6px 10px;font-weight:600;">
                  <?= e($av['full_name']) ?>
                  <?php if ($av['vip']): ?><span style="font-size:10px;background:#fef3c7;color:#92400e;padding:1px 5px;border-radius:10px;margin-left:4px;">⭐ VIP</span><?php endif; ?>
                  <div style="font-size:11px;color:var(--text-muted);"><?= e($av['phone']) ?></div>
                </td>
                <td style="padding:6px 10px;font-size:12px;font-family:monospace;"><?= e($av['badge_number']) ?></td>
                <td style="padding:6px 10px;"><?= e($av['person_to_meet']) ?></td>
                <td style="padding:6px 10px;white-space:nowrap;font-size:12px;"><?= format_datetime($av['check_in_time'], 'g:i A') ?></td>
                <td style="padding:6px 10px;font-size:12px;"><?= e($av['vehicle_number']) ?: '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Mass Check-Out ─────────────────────────────────────── -->
  <?php if ($active_count > 0): ?>
  <div class="card" style="margin-top:var(--space-5);border-color:var(--danger);" id="mass-checkout-card">
    <div class="card-header" style="background:rgba(239,68,68,.06);">
      <h3 class="card-title" style="color:var(--danger);">
        <i class="bi bi-person-x-fill"></i> Mass Check-Out — All Visitors
      </h3>
    </div>
    <div class="card-body">
      <div class="alert alert-error" style="margin-bottom:16px;">
        <i class="alert-icon bi bi-exclamation-octagon-fill"></i>
        <div class="alert-body">
          This action will immediately check out <strong>all <?= $active_count ?> active visitor(s)</strong>.
          This cannot be undone. Use only during a confirmed emergency evacuation.
        </div>
      </div>
      <form method="POST" action="" id="mass-checkout-form">
        <?php csrf_field() ?>
        <input type="hidden" name="action" value="mass_checkout">
        <div class="form-group" style="max-width:380px;">
          <label>Type <strong>CONFIRM CHECKOUT</strong> to proceed</label>
          <input type="text" name="confirm_phrase" class="form-control"
                 placeholder="CONFIRM CHECKOUT" autocomplete="off"
                 oninput="document.getElementById('mass-co-btn').disabled=this.value!=='CONFIRM CHECKOUT'">
        </div>
        <button type="submit" id="mass-co-btn" class="btn btn-danger" disabled
                onclick="return confirm('Are you absolutely sure? All <?= $active_count ?> visitor(s) will be checked out immediately.')">
          <i class="bi bi-people-fill"></i> Mark All Visitors Safe — Check Out All
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div>

<style>
@media print {
  #snapshot-card .card-header button,
  #mass-checkout-card,
  .navbar, .sidebar, nav, .page-header > *:last-child,
  .card:not(#snapshot-card) { display: none !important; }
  #snapshot-card { border: none !important; box-shadow: none !important; }
  body { background: #fff !important; color: #000 !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
