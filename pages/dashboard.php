<?php
/**
 * pages/dashboard.php — Polished Admin Dashboard v2.3
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('view_dashboard');

// ── Time-of-day greeting ──────────────────────────────────────────────────────
$_hour          = (int)date('G');
$_greeting      = $_hour < 12 ? 'Good morning' : ($_hour < 17 ? 'Good afternoon' : 'Good evening');
$_first_name    = explode(' ', $_SESSION['admin_name'] ?? 'Admin')[0];
$_role_label    = role_label((int)($_SESSION['role_id'] ?? 0));
$_today_display = date('l, F j, Y');

// ── Stat queries ──────────────────────────────────────────────────────────────
$_stat_today      = (int)(query_one("SELECT COUNT(*) cnt FROM visits WHERE DATE(check_in_time)=CURDATE()")['cnt'] ?? 0);
$_stat_checked_in = (int)(query_one("SELECT COUNT(*) cnt FROM visits WHERE status='checked_in'")['cnt'] ?? 0);
$_stat_checked_out= (int)(query_one("SELECT COUNT(*) cnt FROM visits WHERE status='checked_out' AND DATE(check_out_time)=CURDATE()")['cnt'] ?? 0);
$_stat_month      = (int)(query_one("SELECT COUNT(*) cnt FROM visits WHERE MONTH(check_in_time)=MONTH(CURDATE()) AND YEAR(check_in_time)=YEAR(CURDATE())")['cnt'] ?? 0);
$_stat_yesterday  = (int)(query_one("SELECT COUNT(*) cnt FROM visits WHERE DATE(check_in_time)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)")['cnt'] ?? 0);
$_trend_pct = $_stat_yesterday > 0
    ? (int)round((($_stat_today - $_stat_yesterday) / $_stat_yesterday) * 100)
    : ($_stat_today > 0 ? 100 : 0);
$_trend_up = $_trend_pct >= 0;

// ── 7-day line chart data ─────────────────────────────────────────────────────
$_chart_rows = query_all(
    "SELECT DATE(check_in_time) AS day, COUNT(*) AS cnt FROM visits
     WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(check_in_time)"
);
$_day_map = [];
foreach ($_chart_rows as $_r) { $_day_map[$_r['day']] = (int)$_r['cnt']; }
$_chart_labels = [];
$_chart_values = [];
for ($_i = 6; $_i >= 0; $_i--) {
    $_d = date('Y-m-d', strtotime("-{$_i} days"));
    $_chart_labels[] = date('D', strtotime($_d));
    $_chart_values[] = $_day_map[$_d] ?? 0;
}
$_chart_sum = array_sum($_chart_values);

// ── Doughnut: visit status breakdown (today) ──────────────────────────────────
$_doughnut = ['checked_in' => 0, 'checked_out' => 0, 'no_show' => 0];
foreach (query_all("SELECT status, COUNT(*) cnt FROM visits WHERE DATE(check_in_time)=CURDATE() GROUP BY status") as $_r) {
    if (isset($_doughnut[$_r['status']])) $_doughnut[$_r['status']] = (int)$_r['cnt'];
}
$_last_updated = date('H:i:s');

// ── Recent visitors (last 8) ──────────────────────────────────────────────────
$_recent = query_all(
    "SELECT id, full_name, phone, purpose, host_name, host_department,
            check_in_time, check_out_time, status, photo_path
     FROM visits ORDER BY check_in_time DESC LIMIT 8"
);

// ── Active visitors (up to 10) ────────────────────────────────────────────────
$_active = query_all(
    "SELECT id, full_name, phone, purpose, host_name, host_department,
            check_in_time, photo_path
     FROM visits WHERE status='checked_in' ORDER BY check_in_time ASC LIMIT 10"
);

// ── Relative time helper ──────────────────────────────────────────────────────
function dash_rel_time(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return (int)($diff / 60) . ' min ago';
    if ($diff < 86400) return (int)($diff / 3600) . 'h ago';
    return date('M j', strtotime($dt));
}

$page_title       = 'Dashboard';
$page_uses_charts = true;
$page_extra_js    = ['dashboard.js'];
include __DIR__ . '/../includes/header.php';
?>

<div class="container">

  <!-- ── Page Header ── -->
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:var(--space-6);">
    <div>
      <h1 style="font-size:1.5rem;font-weight:700;color:var(--text);margin:0 0 4px;"><?= e($_greeting) ?>, <?= e($_first_name) ?> 👋</h1>
      <p style="font-size:13px;color:var(--text-muted);margin:0;">
        <span style="display:inline-flex;align-items:center;gap:6px;">
          <i class="bi bi-shield-check" style="color:var(--secondary);"></i>
          <?= e($_role_label) ?>
        </span>
      </p>
    </div>
    <div style="text-align:right;font-size:13px;color:var(--text-muted);padding-top:4px;">
      <i class="bi bi-calendar3" style="margin-right:4px;"></i><?= e($_today_display) ?>
    </div>
  </div>

  <!-- ── Quick Actions ── -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:var(--space-4);margin-bottom:var(--space-6);" class="quick-actions-grid">
    <a href="<?= BASE_URL ?>pages/register_visitor.php"
       style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:20px 12px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);text-decoration:none;color:var(--text);box-shadow:var(--shadow-sm);transition:transform 200ms,box-shadow 200ms;cursor:pointer;"
       class="qa-card" data-qa="register">
      <i class="bi bi-person-plus-fill" style="font-size:28px;color:var(--secondary);"></i>
      <span style="font-size:13px;font-weight:600;text-align:center;">+ Register Visitor</span>
    </a>
    <a href="<?= BASE_URL ?>pages/checkin_checkout.php"
       style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:20px 12px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);text-decoration:none;color:var(--text);box-shadow:var(--shadow-sm);transition:transform 200ms,box-shadow 200ms;"
       class="qa-card">
      <i class="bi bi-door-open-fill" style="font-size:28px;color:var(--accent);"></i>
      <span style="font-size:13px;font-weight:600;text-align:center;">Check-In / Out</span>
    </a>
    <a href="<?= BASE_URL ?>pages/visitor_history.php"
       style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:20px 12px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);text-decoration:none;color:var(--text);box-shadow:var(--shadow-sm);transition:transform 200ms,box-shadow 200ms;"
       class="qa-card">
      <i class="bi bi-clock-history" style="font-size:28px;color:#8E44AD;"></i>
      <span style="font-size:13px;font-weight:600;text-align:center;">View History</span>
    </a>
    <button type="button" onclick="document.getElementById('smart-search')?.focus()"
       style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:20px 12px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);color:var(--text);box-shadow:var(--shadow-sm);transition:transform 200ms,box-shadow 200ms;cursor:pointer;font-family:inherit;"
       class="qa-card">
      <i class="bi bi-search" style="font-size:28px;color:var(--warning);"></i>
      <span style="font-size:13px;font-weight:600;text-align:center;">Smart Search</span>
    </button>
  </div>

  <!-- ── Stat Cards ── -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:var(--space-4);margin-bottom:var(--space-6);" class="stats-grid-4">

    <!-- Card 1: Visitors Today -->
    <div class="stat-card-v2" style="--sc-accent:var(--secondary);">
      <div style="display:flex;flex-direction:column;gap:6px;flex:1;">
        <span style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);">Visitors Today</span>
        <span class="stat-num" id="stat-today" data-value="<?= $_stat_today ?>"
              style="font-size:36px;font-weight:700;color:var(--text);line-height:1;transition:color .2s;"><?= $_stat_today ?></span>
        <span class="stat-trend <?= $_trend_up ? 'trend-up' : 'trend-down' ?>" id="stat-trend">
          <i class="bi bi-<?= $_trend_up ? 'arrow-up' : 'arrow-down' ?>-short"></i>
          <?= abs($_trend_pct) ?>% vs yesterday
        </span>
      </div>
      <div style="width:48px;height:48px;border-radius:12px;background:rgba(46,117,182,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi bi-people-fill" style="font-size:22px;color:var(--secondary);"></i>
      </div>
    </div>

    <!-- Card 2: Currently Checked In -->
    <div class="stat-card-v2" style="--sc-accent:var(--accent);">
      <div style="display:flex;flex-direction:column;gap:6px;flex:1;">
        <span style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);">Currently Checked In</span>
        <span class="stat-num" id="stat-checked-in" data-value="<?= $_stat_checked_in ?>"
              style="font-size:36px;font-weight:700;color:var(--text);line-height:1;"><?= $_stat_checked_in ?></span>
        <span style="font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:5px;">
          <span class="live-dot-pulse"></span> Live
        </span>
      </div>
      <div style="width:48px;height:48px;border-radius:12px;background:rgba(0,180,216,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi bi-door-open-fill" style="font-size:22px;color:var(--accent);"></i>
      </div>
    </div>

    <!-- Card 3: Checked Out Today -->
    <div class="stat-card-v2" style="--sc-accent:#8E44AD;">
      <div style="display:flex;flex-direction:column;gap:6px;flex:1;">
        <span style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);">Checked Out Today</span>
        <span class="stat-num" id="stat-checked-out" data-value="<?= $_stat_checked_out ?>"
              style="font-size:36px;font-weight:700;color:var(--text);line-height:1;"><?= $_stat_checked_out ?></span>
        <span style="font-size:12px;color:var(--text-muted);">Today's exits</span>
      </div>
      <div style="width:48px;height:48px;border-radius:12px;background:rgba(142,68,173,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi bi-check-circle-fill" style="font-size:22px;color:#8E44AD;"></i>
      </div>
    </div>

    <!-- Card 4: This Month -->
    <div class="stat-card-v2" style="--sc-accent:var(--warning);">
      <div style="display:flex;flex-direction:column;gap:6px;flex:1;">
        <span style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);">This Month</span>
        <span class="stat-num" id="stat-month" data-value="<?= $_stat_month ?>"
              style="font-size:36px;font-weight:700;color:var(--text);line-height:1;"><?= $_stat_month ?></span>
        <span style="font-size:12px;color:var(--text-muted);"><?= date('F Y') ?></span>
      </div>
      <div style="width:48px;height:48px;border-radius:12px;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi bi-calendar3" style="font-size:22px;color:var(--warning);"></i>
      </div>
    </div>

  </div>

  <!-- ── Doughnut + Recent Visitors (38/62) ── -->
  <div style="display:grid;grid-template-columns:38% 1fr;gap:var(--space-4);margin-bottom:var(--space-6);align-items:start;" class="mid-grid">

    <!-- Doughnut -->
    <div class="card" style="padding:24px;">
      <h3 style="font-size:15px;font-weight:600;margin:0 0 20px;color:var(--text);">
        <i class="bi bi-pie-chart-fill" style="color:var(--secondary);margin-right:6px;"></i>Visit Status — Today
      </h3>
      <div style="position:relative;height:220px;display:flex;align-items:center;justify-content:center;">
        <canvas id="status-doughnut" aria-label="Visit status doughnut chart"></canvas>
      </div>
      <!-- Legend -->
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;">
          <span style="display:flex;align-items:center;gap:8px;">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--accent);flex-shrink:0;"></span>Checked In
          </span>
          <strong id="doughnut-label-ci"><?= $_doughnut['checked_in'] ?></strong>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;">
          <span style="display:flex;align-items:center;gap:8px;">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--secondary);flex-shrink:0;"></span>Checked Out
          </span>
          <strong id="doughnut-label-co"><?= $_doughnut['checked_out'] ?></strong>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;">
          <span style="display:flex;align-items:center;gap:8px;">
            <span style="width:10px;height:10px;border-radius:50%;background:var(--danger);flex-shrink:0;"></span>No Show
          </span>
          <strong id="doughnut-label-ns"><?= $_doughnut['no_show'] ?></strong>
        </div>
      </div>
      <p style="font-size:11px;color:var(--text-muted);margin:12px 0 0;text-align:right;">
        Last updated <span id="doughnut-updated"><?= $_last_updated ?></span>
      </p>
    </div>

    <!-- Recent Visitors -->
    <div class="card" style="padding:0;overflow:hidden;">
      <div style="padding:20px 24px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
        <h3 style="font-size:15px;font-weight:600;margin:0;color:var(--text);">
          <i class="bi bi-person-lines-fill" style="color:var(--secondary);margin-right:6px;"></i>Recent Visitors
        </h3>
        <a href="<?= BASE_URL ?>pages/visitor_history.php" style="font-size:12px;color:var(--secondary);text-decoration:none;">View All →</a>
      </div>
      <?php if (empty($_recent)): ?>
        <div style="padding:48px 24px;text-align:center;">
          <i class="bi bi-people" style="font-size:64px;color:var(--text-muted);display:block;margin-bottom:12px;opacity:.4;"></i>
          <p style="color:var(--text-muted);font-size:14px;margin:0 0 12px;">No visitor records yet</p>
          <a href="<?= BASE_URL ?>pages/register_visitor.php" class="btn btn-primary btn-sm">Register your first visitor →</a>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
              <tr style="background:var(--bg);">
                <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;">Visitor</th>
                <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Purpose</th>
                <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Host</th>
                <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;">Time</th>
                <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($_recent as $_v):
                $badge_color = match($_v['status']) {
                  'checked_in'  => 'color:#065f46;background:#d1fae5;',
                  'checked_out' => 'color:#1e40af;background:#dbeafe;',
                  'no_show'     => 'color:#991b1b;background:#fee2e2;',
                  default       => 'color:var(--text-muted);background:var(--bg);'
                };
                $status_label = match($_v['status']) {
                  'checked_in'  => 'Checked In',
                  'checked_out' => 'Checked Out',
                  'no_show'     => 'No Show',
                  default       => ucfirst($_v['status'])
                };
                $initials = strtoupper(substr($_v['full_name'] ?? 'V', 0, 1));
              ?>
              <tr style="border-top:1px solid var(--border);transition:background .15s;" onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background=''">
                <td style="padding:10px 16px;">
                  <div style="display:flex;align-items:center;gap:10px;">
                    <?php if (!empty($_v['photo_path']) && file_exists(UPLOAD_DIR . basename($_v['photo_path']))): ?>
                      <img src="<?= BASE_URL ?>assets/uploads/<?= e(basename($_v['photo_path'])) ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                    <?php else: ?>
                      <div style="width:32px;height:32px;border-radius:50%;background:var(--secondary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;flex-shrink:0;"><?= $initials ?></div>
                    <?php endif; ?>
                    <div>
                      <div style="font-weight:600;color:var(--text);"><?= e($_v['full_name']) ?></div>
                      <div style="font-size:11px;color:var(--text-muted);"><?= e($_v['phone'] ?? '') ?></div>
                    </div>
                  </div>
                </td>
                <td style="padding:10px 16px;color:var(--text);"><?= e($_v['purpose'] ?? '—') ?></td>
                <td style="padding:10px 16px;color:var(--text);"><?= e($_v['host_name'] ?? '—') ?></td>
                <td style="padding:10px 16px;color:var(--text-muted);white-space:nowrap;"><?= dash_rel_time($_v['check_in_time']) ?></td>
                <td style="padding:10px 16px;">
                  <span style="font-size:11px;font-weight:600;padding:3px 8px;border-radius:20px;<?= $badge_color ?>"><?= $status_label ?></span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- ── 7-Day Visitor Trend (full width) ── -->
  <div class="card" style="padding:24px;margin-bottom:var(--space-6);">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:20px;">
      <div>
        <h3 style="font-size:15px;font-weight:600;margin:0 0 4px;color:var(--text);">
          <i class="bi bi-graph-up-arrow" style="color:var(--secondary);margin-right:6px;"></i>Visitor Traffic — Last 7 Days
        </h3>
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Total <strong><?= $_chart_sum ?></strong> visits in the past week</p>
      </div>
    </div>
    <div style="height:260px;position:relative;">
      <canvas id="trend-line-chart" aria-label="7-day visitor trend line chart"></canvas>
    </div>
  </div>

  <!-- ── Active Visitors Table (full width) ── -->
  <div class="card" style="margin-bottom:var(--space-6);overflow:hidden;">
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <h3 style="font-size:15px;font-weight:600;margin:0;color:var(--text);display:flex;align-items:center;gap:8px;">
        <span class="live-dot-pulse"></span>Currently Checked In
        <span style="background:var(--accent);color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;min-width:24px;text-align:center;" id="active-count-badge"><?= $_stat_checked_in ?></span>
      </h3>
      <?php if ($_stat_checked_in > 10): ?>
      <a href="<?= BASE_URL ?>pages/visitor_history.php?status=checked_in" style="font-size:12px;color:var(--secondary);text-decoration:none;">
        View all <?= $_stat_checked_in ?> active visitors →
      </a>
      <?php endif; ?>
    </div>

    <?php if (empty($_active)): ?>
      <div style="padding:48px 24px;text-align:center;">
        <i class="bi bi-door-closed" style="font-size:48px;color:var(--text-muted);display:block;margin-bottom:12px;opacity:.35;"></i>
        <p style="color:var(--text-muted);font-size:14px;margin:0 0 12px;">No visitors are currently checked in.</p>
        <a href="<?= BASE_URL ?>pages/register_visitor.php" class="btn btn-secondary btn-sm">Register a visitor</a>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;" id="active-visitors-table">
          <thead>
            <tr style="background:var(--bg);">
              <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">#</th>
              <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Visitor</th>
              <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Purpose</th>
              <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Host / Dept</th>
              <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;">Check-In</th>
              <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;">Elapsed</th>
              <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($_active as $_i => $_av):
              $initials_a = strtoupper(substr($_av['full_name'] ?? 'V', 0, 1));
              $checkin_ts = strtotime($_av['check_in_time']);
              $elapsed_s  = time() - $checkin_ts;
              $elapsed_h  = $elapsed_s / 3600;
            ?>
            <tr style="border-top:1px solid var(--border);transition:background .15s;" onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background=''">
              <td style="padding:10px 16px;color:var(--text-muted);font-size:12px;"><?= $_i + 1 ?></td>
              <td style="padding:10px 16px;">
                <div style="display:flex;align-items:center;gap:10px;">
                  <?php if (!empty($_av['photo_path']) && file_exists(UPLOAD_DIR . basename($_av['photo_path']))): ?>
                    <img src="<?= BASE_URL ?>assets/uploads/<?= e(basename($_av['photo_path'])) ?>" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                  <?php else: ?>
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--secondary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;"><?= $initials_a ?></div>
                  <?php endif; ?>
                  <div>
                    <div style="font-weight:600;color:var(--text);"><?= e($_av['full_name']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= e($_av['phone'] ?? '') ?></div>
                  </div>
                </div>
              </td>
              <td style="padding:10px 16px;color:var(--text);"><?= e($_av['purpose'] ?? '—') ?></td>
              <td style="padding:10px 16px;">
                <div style="color:var(--text);"><?= e($_av['host_name'] ?? '—') ?></div>
                <?php if (!empty($_av['host_department'])): ?>
                <div style="font-size:11px;color:var(--text-muted);"><?= e($_av['host_department']) ?></div>
                <?php endif; ?>
              </td>
              <td style="padding:10px 16px;color:var(--text-muted);white-space:nowrap;font-size:12px;">
                <?= e(date('g:i A', $checkin_ts)) ?>
              </td>
              <td style="padding:10px 16px;">
                <span class="elapsed-chip"
                      data-checkin-time="<?= e($_av['check_in_time']) ?>"
                      style="font-size:12px;font-weight:600;padding:3px 8px;border-radius:20px;
                             <?= $elapsed_h <= 2 ? 'color:#065f46;background:#d1fae5;' : ($elapsed_h <= 4 ? 'color:#92400e;background:#fef3c7;' : 'color:#991b1b;background:#fee2e2;') ?>">
                  <?= time_elapsed($_av['check_in_time']) ?>
                </span>
              </td>
              <td style="padding:10px 16px;">
                <div style="display:flex;gap:6px;align-items:center;">
                  <form method="POST" action="<?= BASE_URL ?>api/checkout.php" style="display:inline;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="visit_id" value="<?= (int)$_av['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline"
                            style="font-size:12px;padding:5px 10px;"
                            onclick="return confirm('Check out <?= e(addslashes($_av['full_name'])) ?>?')">
                      <i class="bi bi-box-arrow-right"></i> Check Out
                    </button>
                  </form>
                  <a href="<?= BASE_URL ?>pages/visitor_detail.php?id=<?= (int)$_av['id'] ?>"
                     class="btn btn-sm btn-ghost" style="font-size:12px;padding:5px 10px;">
                    <i class="bi bi-eye"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- Embed chart data for dashboard.js -->
<script>
window.SVMS_DASHBOARD_DATA = {
  chart7day: {
    labels: <?= json_encode($_chart_labels, JSON_UNESCAPED_UNICODE) ?>,
    data:   <?= json_encode($_chart_values) ?>
  },
  doughnut: {
    checked_in:  <?= (int)$_doughnut['checked_in'] ?>,
    checked_out: <?= (int)$_doughnut['checked_out'] ?>,
    no_show:     <?= (int)$_doughnut['no_show'] ?>
  },
  stats: {
    today:       <?= $_stat_today ?>,
    checked_in:  <?= $_stat_checked_in ?>,
    checked_out: <?= $_stat_checked_out ?>,
    month:       <?= $_stat_month ?>
  }
};
</script>

<!-- Quick-action hover styles & live-dot -->
<style>
.qa-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md) !important; }
.stat-card-v2 {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 24px;
  background: var(--card);
  border: 1px solid var(--border);
  border-top: 4px solid var(--sc-accent, var(--secondary));
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  transition: transform 200ms ease, box-shadow 200ms ease;
}
.stat-card-v2:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg, 0 10px 30px rgba(0,0,0,.15)); }
.stat-trend { font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 3px; }
.trend-up   { color: var(--success); }
.trend-down { color: var(--danger); }
.live-dot-pulse {
  display: inline-block; width: 8px; height: 8px; border-radius: 50%;
  background: var(--success); flex-shrink: 0;
  animation: pulse-ring 1.6s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
}
@keyframes pulse-ring {
  0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
  70%  { box-shadow: 0 0 0 7px rgba(34,197,94,0); }
  100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
}
@media (max-width: 1024px) {
  .stats-grid-4  { grid-template-columns: repeat(2,1fr) !important; }
  .quick-actions-grid { grid-template-columns: repeat(2,1fr) !important; }
  .mid-grid      { grid-template-columns: 1fr !important; }
}
@media (max-width: 640px) {
  .stats-grid-4  { grid-template-columns: 1fr !important; }
  .quick-actions-grid { grid-template-columns: 1fr !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
