<?php
/**
 * pages/dashboard.php — Role-specific dashboard.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Dashboard';
$my_role = role_slug((int)($_SESSION['role_id'] ?? 0));

// ── Stats ─────────────────────────────────────────────────────────────────────
$today = date('Y-m-d');

$stat_today = (int)(query_one(
    "SELECT COUNT(*) AS c FROM visit_log WHERE DATE(check_in_time) = ?",
    's', [$today]
)['c'] ?? 0);

$stat_active = (int)(query_one(
    "SELECT COUNT(*) AS c FROM visit_log WHERE status = 'checked_in'",
    '', []
)['c'] ?? 0);

$stat_visitors_total = (int)(query_one(
    "SELECT COUNT(*) AS c FROM visitors",
    '', []
)['c'] ?? 0);

$stat_appt_today = (int)(query_one(
    "SELECT COUNT(*) AS c FROM appointments WHERE DATE(scheduled_at) = ? AND status NOT IN ('cancelled','no_show')",
    's', [$today]
)['c'] ?? 0);

$stat_yesterday = (int)(query_one(
    "SELECT COUNT(*) AS c FROM visit_log WHERE DATE(check_in_time) = DATE_SUB(?, INTERVAL 1 DAY)",
    's', [$today]
)['c'] ?? 0);

// ── Recent activity ───────────────────────────────────────────────────────────
$recent_visits = query_all(
    "SELECT vl.id, v.name AS visitor_name, v.badge_number,
            vl.host_name, vl.check_in_time, vl.check_out_time, vl.status,
            d.name AS dept_name
     FROM visit_log vl
     JOIN visitors v ON v.id = vl.visitor_id
     LEFT JOIN departments d ON d.id = vl.department_id
     ORDER BY vl.check_in_time DESC
     LIMIT 10",
    '', []
);

// ── Today's appointments ──────────────────────────────────────────────────────
$todays_appts = query_all(
    "SELECT a.id, a.visitor_name, a.host_name, a.scheduled_at, a.status, a.duration_minutes,
            d.name AS dept_name
     FROM appointments a
     LEFT JOIN departments d ON d.id = a.department_id
     WHERE DATE(a.scheduled_at) = ?
       AND a.status NOT IN ('cancelled','no_show')
     ORDER BY a.scheduled_at ASC
     LIMIT 8",
    's', [$today]
);

// ── Trend badge helper ────────────────────────────────────────────────────────
$today_trend = $stat_today > $stat_yesterday ? 'up' : ($stat_today < $stat_yesterday ? 'down' : 'same');
$today_delta = abs($stat_today - $stat_yesterday);

include __DIR__ . '/../includes/header.php';
?>

<div class="container" id="main-content-inner">

  <!-- ── Welcome banner ──────────────────────────────────────── -->
  <div style="background:linear-gradient(135deg,var(--primary,#1a3c5e) 0%,#0d2a47 55%,#0a1f36 100%);
              border-radius:var(--radius-lg,14px);
              padding:28px 32px;
              margin-bottom:24px;
              display:flex;
              align-items:center;
              justify-content:space-between;
              gap:20px;
              position:relative;
              overflow:hidden;">
    <!-- Decorative glow -->
    <div style="position:absolute;top:-40px;right:160px;width:200px;height:200px;
                background:radial-gradient(circle,rgba(0,180,216,.18) 0%,transparent 70%);
                pointer-events:none;"></div>

    <div style="flex:1;min-width:0;position:relative;z-index:1;">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
        <div style="width:36px;height:36px;background:rgba(255,255,255,.12);border-radius:10px;
                    display:flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.15);flex-shrink:0;">
          <i class="bi bi-speedometer2" style="font-size:18px;color:#fff;"></i>
        </div>
        <h2 style="font-size:20px;font-weight:800;color:#fff;margin:0;letter-spacing:-.3px;">
          Welcome back, <?= e(explode(' ', $_SESSION['admin_name'] ?? 'Admin')[0]) ?>!
        </h2>
      </div>
      <p style="font-size:13.5px;color:rgba(255,255,255,.65);margin:0 0 18px;line-height:1.5;">
        <i class="bi bi-calendar3" style="margin-right:5px;opacity:.7;"></i><?= date('l, F j, Y') ?>
        &nbsp;&bull;&nbsp;
        <i class="bi bi-person-fill" style="margin-right:5px;opacity:.7;"></i><?= e(role_label((int)($_SESSION['role_id'] ?? 0))) ?>
        &nbsp;&bull;&nbsp;
        <span style="color:rgba(0,180,216,.85);font-weight:600;"><?= $stat_active ?> on premises</span>
      </p>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php if (can('register_visitor')): ?>
        <a href="<?= BASE_URL ?>pages/register_visitor.php"
           style="background:rgba(255,255,255,.15);color:#fff;padding:7px 14px;border-radius:8px;
                  text-decoration:none;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:7px;
                  border:1px solid rgba(255,255,255,.2);transition:background .15s;"
           onmouseover="this.style.background='rgba(255,255,255,.24)'"
           onmouseout="this.style.background='rgba(255,255,255,.15)'">
          <i class="bi bi-person-plus-fill"></i> Register Visitor
        </a>
        <?php endif; ?>
        <?php if (can('checkin_visitor')): ?>
        <a href="<?= BASE_URL ?>pages/checkin_checkout.php"
           style="background:rgba(255,255,255,.15);color:#fff;padding:7px 14px;border-radius:8px;
                  text-decoration:none;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:7px;
                  border:1px solid rgba(255,255,255,.2);transition:background .15s;"
           onmouseover="this.style.background='rgba(255,255,255,.24)'"
           onmouseout="this.style.background='rgba(255,255,255,.15)'">
          <i class="bi bi-door-open-fill"></i> Check In / Out
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>kiosk/"
           style="background:rgba(0,180,216,.22);color:#00b4d8;padding:7px 14px;border-radius:8px;
                  text-decoration:none;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:7px;
                  border:1px solid rgba(0,180,216,.32);transition:background .15s;"
           onmouseover="this.style.background='rgba(0,180,216,.34)'"
           onmouseout="this.style.background='rgba(0,180,216,.22)'">
          <i class="bi bi-tablet-landscape-fill"></i> Kiosk Mode
        </a>
      </div>
    </div>

    <!-- Dashboard hero illustration -->
    <div style="flex-shrink:0;width:260px;height:130px;display:flex;align-items:center;justify-content:flex-end;position:relative;z-index:1;">
      <img src="<?= BASE_URL ?>assets/img/dashboard-hero.svg" alt=""
           style="width:260px;height:auto;opacity:.82;display:block;"
           loading="eager" aria-hidden="true">
    </div>
  </div>

  <!-- ── Page header ───────────────────────────────────────────── -->
  <div class="page-header" style="padding-top:0;margin-top:0;">
    <div>
      <h1 class="page-title">
        <i class="bi bi-speedometer2" style="color:var(--secondary);margin-right:8px;"></i>
        <?= e(t('nav.dashboard')) ?>
      </h1>
      <p class="page-subtitle">
        <?= date_fmt() ?> &mdash; <?= e($_SESSION['admin_name'] ?? 'Admin') ?>,
        <?= e(role_label((int)($_SESSION['role_id'] ?? 0))) ?>
      </p>
    </div>
    <?php if (can('register_visitor')): ?>
    <a href="<?= BASE_URL ?>pages/register_visitor.php" class="btn btn-primary">
      <i class="bi bi-person-plus-fill"></i>
      Register Visitor
    </a>
    <?php endif; ?>
  </div>

  <!-- ── Stat cards ────────────────────────────────────────────── -->
  <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px;">

    <!-- Visitors today -->
    <a href="<?= BASE_URL ?>pages/visitor_history.php" class="stat-card" style="display:block;text-decoration:none;background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px 22px;position:relative;overflow:hidden;cursor:pointer;transition:box-shadow .15s;" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow=''">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;">
        <div>
          <p style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;margin:0 0 8px;">Visitors Today</p>
          <p style="font-size:36px;font-weight:800;color:var(--text);margin:0;line-height:1;"><?= $stat_today ?></p>
          <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;">
            <?php if ($today_trend === 'up'): ?>
              <span style="color:var(--success);"><i class="bi bi-arrow-up-short"></i>+<?= $today_delta ?> vs yesterday</span>
            <?php elseif ($today_trend === 'down'): ?>
              <span style="color:var(--danger);"><i class="bi bi-arrow-down-short"></i><?= $today_delta ?> vs yesterday</span>
            <?php else: ?>
              <span>Same as yesterday</span>
            <?php endif; ?>
          </p>
        </div>
        <div style="width:44px;height:44px;background:rgba(26,60,94,.1);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="bi bi-people-fill" style="font-size:20px;color:var(--primary);"></i>
        </div>
      </div>
    </a>

    <!-- Currently on premises -->
    <a href="<?= BASE_URL ?>pages/checkin_checkout.php" class="stat-card" style="display:block;text-decoration:none;background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px 22px;position:relative;overflow:hidden;cursor:pointer;transition:box-shadow .15s;" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow=''">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;">
        <div>
          <p style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;margin:0 0 8px;">On Premises Now</p>
          <p style="font-size:36px;font-weight:800;color:var(--text);margin:0;line-height:1;"><?= $stat_active ?></p>
          <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;">Checked in, not yet out</p>
        </div>
        <div style="width:44px;height:44px;background:rgba(5,150,105,.1);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="bi bi-door-open-fill" style="font-size:20px;color:#059669;"></i>
        </div>
      </div>
    </a>

    <!-- Total visitors in DB -->
    <a href="<?= BASE_URL ?>pages/visitor_history.php" class="stat-card" style="display:block;text-decoration:none;background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px 22px;position:relative;overflow:hidden;cursor:pointer;transition:box-shadow .15s;" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow=''">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;">
        <div>
          <p style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;margin:0 0 8px;">Total Registered</p>
          <p style="font-size:36px;font-weight:800;color:var(--text);margin:0;line-height:1;"><?= number_format($stat_visitors_total) ?></p>
          <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;">Unique visitor profiles</p>
        </div>
        <div style="width:44px;height:44px;background:rgba(139,92,246,.1);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="bi bi-person-badge-fill" style="font-size:20px;color:#7c3aed;"></i>
        </div>
      </div>
    </a>

    <!-- Appointments today -->
    <a href="<?= BASE_URL ?>pages/appointments.php" class="stat-card" style="display:block;text-decoration:none;background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px 22px;position:relative;overflow:hidden;cursor:pointer;transition:box-shadow .15s;" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow=''">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;">
        <div>
          <p style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;margin:0 0 8px;">Appointments Today</p>
          <p style="font-size:36px;font-weight:800;color:var(--text);margin:0;line-height:1;"><?= $stat_appt_today ?></p>
          <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;">Scheduled &amp; confirmed</p>
        </div>
        <div style="width:44px;height:44px;background:rgba(2,132,199,.1);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="bi bi-calendar-check" style="font-size:20px;color:#0284c7;"></i>
        </div>
      </div>
    </a>

  </div>

  <!-- ── Quick Actions ──────────────────────────────────────────── -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:28px;">

    <!-- Recent Visit Log -->
    <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;flex:2;min-width:0;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid var(--border);">
        <h2 style="font-size:15px;font-weight:700;color:var(--text);margin:0;">
          <i class="bi bi-clock-history" style="color:var(--secondary);margin-right:8px;"></i>Recent Activity
        </h2>
        <?php if (can('view_history')): ?>
        <a href="<?= BASE_URL ?>pages/visitor_history.php" class="btn btn-sm btn-secondary" style="font-size:12px;padding:4px 10px;">View all</a>
        <?php endif; ?>
      </div>

      <?php if (empty($recent_visits)): ?>
      <div style="padding:32px;text-align:center;color:var(--text-muted);">
        <i class="bi bi-inbox" style="font-size:32px;opacity:.4;display:block;margin-bottom:8px;"></i>
        No visits recorded yet.
      </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <thead>
            <tr style="background:var(--bg-secondary);">
              <th style="padding:8px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Visitor</th>
              <th style="padding:8px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Host</th>
              <th style="padding:8px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Check-in</th>
              <th style="padding:8px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_visits as $visit): ?>
            <tr style="border-top:1px solid var(--border);cursor:pointer;" class="table-row-hover"
                onclick="window.location='<?= BASE_URL ?>pages/visitor_detail.php?id=<?= (int)$visit['id'] ?>'">
              <td style="padding:10px 16px;">
                <div style="font-weight:600;color:var(--text);"><?= e($visit['visitor_name']) ?></div>
                <?php if ($visit['badge_number']): ?>
                <div style="font-size:11px;color:var(--text-muted);"><?= e($visit['badge_number']) ?></div>
                <?php endif; ?>
              </td>
              <td style="padding:10px 16px;color:var(--text-muted);">
                <?= e($visit['host_name'] ?: '—') ?>
                <?php if ($visit['dept_name']): ?>
                <div style="font-size:11px;"><?= e($visit['dept_name']) ?></div>
                <?php endif; ?>
              </td>
              <td style="padding:10px 16px;color:var(--text-muted);white-space:nowrap;">
                <?= format_datetime($visit['check_in_time'], 'M d, g:i A') ?>
              </td>
              <td style="padding:10px 16px;">
                <?php
                $st = $visit['status'];
                $badge_class = match($st) {
                    'checked_in'   => 'background:#dcfce7;color:#166534;',
                    'checked_out'  => 'background:#f1f5f9;color:#475569;',
                    'auto_checkout'=> 'background:#fef9c3;color:#854d0e;',
                    default        => 'background:#fee2e2;color:#991b1b;',
                };
                $badge_label = match($st) {
                    'checked_in'   => 'Checked In',
                    'checked_out'  => 'Checked Out',
                    'auto_checkout'=> 'Auto-Out',
                    'no_show'      => 'No Show',
                    default        => ucfirst(str_replace('_', ' ', $st)),
                };
                ?>
                <span style="<?= $badge_class ?>padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;">
                  <?= $badge_label ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Today's Appointments -->
    <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;">
      <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid var(--border);">
        <h2 style="font-size:15px;font-weight:700;color:var(--text);margin:0;">
          <i class="bi bi-calendar-event" style="color:#0284c7;margin-right:8px;"></i>Today's Appointments
        </h2>
        <?php if (can('manage_appointments')): ?>
        <a href="<?= BASE_URL ?>pages/appointments.php" class="btn btn-sm btn-secondary" style="font-size:12px;padding:4px 10px;">Manage</a>
        <?php endif; ?>
      </div>

      <?php if (empty($todays_appts)): ?>
      <div style="padding:32px;text-align:center;color:var(--text-muted);">
        <i class="bi bi-calendar-x" style="font-size:32px;opacity:.4;display:block;margin-bottom:8px;"></i>
        No appointments scheduled today.
      </div>
      <?php else: ?>
      <ul style="list-style:none;margin:0;padding:0;">
        <?php foreach ($todays_appts as $appt): ?>
        <li style="display:flex;align-items:center;gap:12px;padding:12px 18px;border-top:1px solid var(--border);">
          <div style="width:40px;height:40px;background:rgba(2,132,199,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-person-fill" style="color:#0284c7;font-size:16px;"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:13px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($appt['visitor_name']) ?></div>
            <div style="font-size:11px;color:var(--text-muted);">
              <?= e($appt['host_name']) ?> &bull; <?= date('g:i A', strtotime($appt['scheduled_at'])) ?>
            </div>
          </div>
          <?php
          $ast = $appt['status'];
          $ast_style = match($ast) {
              'confirmed' => 'background:#dcfce7;color:#166534;',
              'arrived'   => 'background:#dbeafe;color:#1e40af;',
              'scheduled' => 'background:#f1f5f9;color:#475569;',
              default     => 'background:#fef9c3;color:#854d0e;',
          };
          ?>
          <span style="<?= $ast_style ?>padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap;">
            <?= ucfirst($ast) ?>
          </span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

  </div>

  <!-- ── Role-Specific Panel ──────────────────────────────────── -->
  <?php if ($my_role === 'security_guard'): ?>
  <?php
    $blacklisted_count = (int)(query_one("SELECT COUNT(*) AS c FROM blacklist")['c'] ?? 0);
    $em_mode = get_setting('emergency_mode', 'normal');
    $recent_checkins = query_all(
      "SELECT v.name, vl.check_in_time, vl.host_name
       FROM visit_log vl JOIN visitors v ON v.id=vl.visitor_id
       WHERE vl.status='checked_in' ORDER BY vl.check_in_time DESC LIMIT 5"
    );
  ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:28px;">
    <!-- Emergency Status -->
    <div class="card" style="border-color:<?= $em_mode !== 'normal' ? 'var(--danger)' : 'var(--border)' ?>;">
      <div class="card-header" style="background:<?= $em_mode !== 'normal' ? 'rgba(239,68,68,.08)' : '' ?>">
        <h3 class="card-title"><i class="bi bi-exclamation-octagon-fill" style="color:<?= $em_mode !== 'normal' ? 'var(--danger)' : 'var(--success)' ?>;"></i> Facility Status</h3>
      </div>
      <div class="card-body" style="text-align:center;padding:24px;">
        <div style="font-size:28px;font-weight:800;color:<?= $em_mode !== 'normal' ? 'var(--danger)' : 'var(--success)' ?>;"><?= strtoupper($em_mode) ?></div>
        <p style="color:var(--text-muted);font-size:13px;margin:8px 0 16px;"><?= $stat_active ?> visitor(s) currently on premises</p>
        <?php if (can('emergency_control')): ?>
        <a href="<?= BASE_URL ?>pages/emergency.php" class="btn btn-<?= $em_mode !== 'normal' ? 'danger' : 'outline' ?> btn-sm">
          <i class="bi bi-shield-exclamation"></i> Emergency Controls
        </a>
        <?php endif; ?>
      </div>
    </div>
    <!-- Blacklist Summary -->
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="bi bi-slash-circle-fill" style="color:var(--danger);"></i> Blacklist Monitor</h3></div>
      <div class="card-body" style="text-align:center;padding:24px;">
        <div style="font-size:36px;font-weight:800;color:var(--danger);"><?= $blacklisted_count ?></div>
        <p style="color:var(--text-muted);font-size:13px;margin:8px 0 16px;">Total blacklisted visitors</p>
        <a href="<?= BASE_URL ?>pages/blacklist.php" class="btn btn-outline btn-sm">
          <i class="bi bi-eye"></i> View Blacklist
        </a>
      </div>
    </div>
    <!-- Active Check-ins -->
    <div class="card">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <h3 class="card-title"><i class="bi bi-door-open-fill" style="color:var(--success);"></i> Active Visitors</h3>
        <a href="<?= BASE_URL ?>pages/checkin_checkout.php" class="btn btn-sm btn-secondary">Manage</a>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if (empty($recent_checkins)): ?>
        <p style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">No visitors on premises</p>
        <?php else: ?>
        <?php foreach ($recent_checkins as $ci): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--border);font-size:13px;">
          <div>
            <div style="font-weight:600;"><?= e($ci['name']) ?></div>
            <div style="font-size:11px;color:var(--text-muted);">Visiting: <?= e($ci['host_name']) ?></div>
          </div>
          <span style="font-size:11px;color:var(--text-muted);"><?= format_datetime($ci['check_in_time'], 'g:i A') ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php elseif ($my_role === 'operations'): ?>
  <?php
    $stat_month = (int)(query_one(
      "SELECT COUNT(*) AS c FROM visit_log WHERE MONTH(check_in_time)=MONTH(NOW()) AND YEAR(check_in_time)=YEAR(NOW())"
    )['c'] ?? 0);
    $stat_avg_dur = query_one(
      "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE,check_in_time,check_out_time)),0) AS avg FROM visit_log WHERE status='checked_out' AND check_out_time IS NOT NULL"
    )['avg'] ?? 0;
    $top_depts = query_all(
      "SELECT COALESCE(d.name,'Unknown') AS dept, COUNT(*) AS cnt FROM visit_log vl LEFT JOIN departments d ON d.id=vl.department_id GROUP BY vl.department_id ORDER BY cnt DESC LIMIT 5"
    );
    $pending_appts = (int)(query_one("SELECT COUNT(*) AS c FROM appointments WHERE status='scheduled'")['c'] ?? 0);
  ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:28px;">
    <!-- Monthly Stats -->
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="bi bi-bar-chart-fill" style="color:var(--secondary);"></i> Monthly Overview</h3></div>
      <div class="card-body" style="padding:20px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;text-align:center;">
          <div>
            <div style="font-size:28px;font-weight:800;color:var(--text);"><?= $stat_month ?></div>
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-muted);">Visits This Month</div>
          </div>
          <div>
            <div style="font-size:28px;font-weight:800;color:var(--text);"><?= $stat_avg_dur ?>m</div>
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text-muted);">Avg Duration</div>
          </div>
        </div>
        <div style="margin-top:16px;text-align:center;">
          <a href="<?= BASE_URL ?>pages/analytics.php" class="btn btn-secondary btn-sm"><i class="bi bi-graph-up"></i> Full Analytics</a>
        </div>
      </div>
    </div>
    <!-- Top Departments -->
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="bi bi-building" style="color:var(--secondary);"></i> Top Departments</h3></div>
      <div class="card-body" style="padding:0;">
        <?php foreach ($top_depts as $td): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 16px;border-bottom:1px solid var(--border);font-size:13px;">
          <span><?= e($td['dept']) ?></span>
          <span style="font-weight:700;color:var(--secondary);"><?= $td['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <!-- Appointments & Reports -->
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="bi bi-calendar-check" style="color:#0284c7;"></i> Appointments & Reports</h3></div>
      <div class="card-body" style="padding:20px;display:flex;flex-direction:column;gap:10px;">
        <a href="<?= BASE_URL ?>pages/appointments.php" class="btn btn-secondary" style="justify-content:space-between;">
          <span><i class="bi bi-calendar-check"></i> Manage Appointments</span>
          <span style="background:var(--primary);color:#fff;border-radius:20px;padding:1px 8px;font-size:11px;font-weight:700;"><?= $pending_appts ?> pending</span>
        </a>
        <a href="<?= BASE_URL ?>pages/visitor_history.php" class="btn btn-secondary">
          <i class="bi bi-clock-history"></i> Visitor History
        </a>
        <a href="<?= BASE_URL ?>pages/analytics.php" class="btn btn-secondary">
          <i class="bi bi-bar-chart-line-fill"></i> Analytics & Reports
        </a>
        <a href="<?= BASE_URL ?>pages/backup.php" class="btn btn-secondary">
          <i class="bi bi-cloud-arrow-down-fill"></i> Backup & Export
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Quick Actions Bar ─────────────────────────────────────── -->
  <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px 22px;margin-bottom:28px;">
    <h2 style="font-size:14px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;margin:0 0 16px;">Quick Actions</h2>
    <div style="display:flex;flex-wrap:wrap;gap:10px;">
      <?php if (can('register_visitor')): ?>
      <a href="<?= BASE_URL ?>pages/register_visitor.php" class="btn btn-secondary" style="gap:6px;">
        <i class="bi bi-person-plus-fill"></i> Register Visitor
      </a>
      <?php endif; ?>
      <?php if (can('checkin_visitor')): ?>
      <a href="<?= BASE_URL ?>pages/checkin_checkout.php" class="btn btn-secondary" style="gap:6px;">
        <i class="bi bi-door-open-fill"></i> Check-In / Out
      </a>
      <?php endif; ?>
      <?php if (can('manage_appointments')): ?>
      <a href="<?= BASE_URL ?>pages/appointments.php" class="btn btn-secondary" style="gap:6px;">
        <i class="bi bi-calendar-plus"></i> New Appointment
      </a>
      <?php endif; ?>
      <?php if (can('view_history')): ?>
      <a href="<?= BASE_URL ?>pages/visitor_history.php" class="btn btn-secondary" style="gap:6px;">
        <i class="bi bi-clock-history"></i> Visit History
      </a>
      <?php endif; ?>
      <?php if (can('view_analytics')): ?>
      <a href="<?= BASE_URL ?>pages/analytics.php" class="btn btn-secondary" style="gap:6px;">
        <i class="bi bi-bar-chart-line-fill"></i> Analytics
      </a>
      <?php endif; ?>
      <?php if (can('manage_blacklist')): ?>
      <a href="<?= BASE_URL ?>pages/blacklist.php" class="btn btn-secondary" style="gap:6px;">
        <i class="bi bi-slash-circle-fill"></i> Blacklist
      </a>
      <?php endif; ?>
    </div>
  </div>

</div>

<style>
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:16px;flex-wrap:wrap;}
.page-title{font-size:24px;font-weight:800;color:var(--text);margin:0 0 4px;letter-spacing:-.4px;}
.page-subtitle{font-size:13px;color:var(--text-muted);margin:0;}
.table-row-hover:hover{background:var(--bg-secondary);}
#main-content-inner{padding:24px;}
@media(max-width:640px){#main-content-inner{padding:16px;}.stats-grid{grid-template-columns:1fr 1fr!important;}}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
