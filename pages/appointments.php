<?php
/**
 * pages/appointments.php — Appointments Calendar (Phase 4.3)
 * Day / Week / Month views with drag-to-reschedule, side drawer,
 * conflict detection, and automatic e-pass emails.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('manage_appointments');

$page_title    = 'Appointments';
$page_extra_js = ['calendar.js'];

$departments = query_all("SELECT id, name, COALESCE(colour,'#2e75b6') AS colour FROM departments WHERE is_active=1 ORDER BY name");
$is_super    = role_slug() === 'super_admin';

include __DIR__ . '/../includes/header.php';
?>
<style>
/* ── Calendar layout ──────────────────────────────────────── */
.cal-toolbar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
.cal-filter-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.cal-view-pill { display:inline-flex; border:1px solid var(--border); border-radius:6px; overflow:hidden; }
.cal-view-pill button { padding:6px 16px; font-size:13px; background:var(--card); border:none; cursor:pointer; color:var(--text-muted); transition:background .15s,color .15s; }
.cal-view-pill button.active { background:var(--secondary); color:#fff; }
.cal-nav { display:inline-flex; gap:4px; align-items:center; }
.cal-nav button { padding:5px 10px; border:1px solid var(--border); background:var(--card); border-radius:6px; cursor:pointer; color:var(--text); font-size:13px; transition:background .15s; }
.cal-nav button:hover { background:var(--bg-secondary); }
#cal-date-label { font-size:14px; font-weight:600; color:var(--text); min-width:180px; }

/* ── Week/Day grid ────────────────────────────────────────── */
.cal-week-wrapper { border:1px solid var(--border); border-radius:8px; overflow:hidden; background:var(--card); }
.cal-week-header { display:grid; grid-template-columns:60px repeat(7,1fr); border-bottom:2px solid var(--border); background:var(--bg-secondary); }
.cal-week-header.day-view { grid-template-columns:60px 1fr; }
.cal-header-cell { min-height:48px; }
.cal-day-header { padding:8px 4px; text-align:center; border-left:1px solid var(--border); }
.cal-day-name { display:block; font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; }
.cal-day-num { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; font-size:15px; font-weight:600; color:var(--text); }
.cal-today-num { background:var(--secondary); color:#fff !important; }
.cal-today-header { background:rgba(46,117,182,.06); }
.cal-week-body { display:grid; grid-template-columns:60px repeat(7,1fr); height:600px; overflow-y:auto; scrollbar-width:thin; }
.cal-week-body.day-view { grid-template-columns:60px 1fr; }
.cal-time-gutter { border-right:1px solid var(--border); }
.cal-hour-label { height:60px; display:flex; align-items:flex-start; justify-content:flex-end; padding:4px 6px 0; font-size:11px; color:var(--text-muted); line-height:1; }
.cal-day-col { position:relative; border-left:1px solid var(--border); min-height:1440px; }
.cal-day-col--single { min-width:0; }
.cal-today-col { background:rgba(0,180,216,.03); }
.cal-hour-row { height:60px; border-bottom:1px solid var(--border); }
.cal-odd-row { background:rgba(0,0,0,.015); }
[data-theme="dark"] .cal-odd-row { background:rgba(255,255,255,.02); }

/* ── Appointment cards ────────────────────────────────────── */
.cal-appt-card { position:absolute; left:3px; right:3px; border-left:4px solid; border-radius:5px; background:var(--card); box-shadow:0 1px 4px rgba(0,0,0,.12); padding:3px 6px; overflow:hidden; cursor:pointer; transition:box-shadow .15s,opacity .15s; z-index:1; user-select:none; }
.cal-appt-card:hover,.cal-appt-card:focus { box-shadow:0 4px 14px rgba(0,0,0,.2); outline:none; z-index:2; }
.cal-appt-card[draggable="true"] { cursor:grab; }
.cal-appt-card[draggable="true"]:active { cursor:grabbing; }
.cal-card-time { font-size:10px; color:var(--text-muted); white-space:nowrap; overflow:hidden; }
.cal-card-name { font-size:12px; font-weight:600; color:var(--text); overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
.cal-dept-badge { font-size:10px; padding:1px 5px; border-radius:3px; display:inline-block; margin-top:2px; white-space:nowrap; }
.cal-now-line { position:absolute; left:0; right:0; height:2px; background:var(--danger); z-index:3; pointer-events:none; }
.cal-now-dot { position:absolute; left:-5px; top:-4px; width:10px; height:10px; background:var(--danger); border-radius:50%; }

/* ── Month view ───────────────────────────────────────────── */
.cal-month-wrapper { border:1px solid var(--border); border-radius:8px; overflow:hidden; background:var(--card); }
.cal-month-header { display:grid; grid-template-columns:repeat(7,1fr); background:var(--bg-secondary); border-bottom:2px solid var(--border); }
.cal-month-day-name { padding:8px 4px; text-align:center; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; }
.cal-month-row { display:grid; grid-template-columns:repeat(7,1fr); border-top:1px solid var(--border); }
.cal-month-cell { min-height:110px; padding:4px; border-right:1px solid var(--border); overflow:hidden; position:relative; }
.cal-month-cell:last-child { border-right:none; }
.cal-month-other { opacity:.45; }
.cal-month-today { background:rgba(0,180,216,.07); }
.cal-month-weekend { background:rgba(0,0,0,.018); }
[data-theme="dark"] .cal-month-weekend { background:rgba(255,255,255,.018); }
.cal-month-cell-date { font-size:13px; font-weight:600; color:var(--text); width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:50%; margin-bottom:3px; }
.cal-month-card { font-size:11px; padding:2px 6px; margin-bottom:2px; border-radius:4px; border-left:3px solid; background:var(--bg-secondary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.cal-mc-time { color:var(--text-muted); }
.cal-mc-name { color:var(--text); font-weight:500; }
.cal-month-more { font-size:11px; color:var(--secondary); cursor:pointer; padding:1px 4px; }
.cal-month-more:hover { text-decoration:underline; }

/* ── Skeleton shimmer ─────────────────────────────────────── */
.shimmer { background:linear-gradient(90deg,var(--border) 25%,var(--bg-secondary) 50%,var(--border) 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:6px; }
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

/* ── Side drawer ──────────────────────────────────────────── */
.drawer-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:1090; opacity:0; transition:opacity 240ms ease-out; display:none; }
.drawer-backdrop.open { opacity:1; }
.appt-drawer { position:fixed; top:0; right:0; height:100vh; width:420px; max-width:100vw; background:var(--card); box-shadow:-4px 0 28px rgba(0,0,0,.18); z-index:1100; transform:translateX(100%); transition:transform 240ms ease-out; display:none; flex-direction:column; overflow:hidden; }
.appt-drawer.open { transform:translateX(0); }
.drawer-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); background:var(--bg-secondary); flex-shrink:0; }
.drawer-title { font-size:16px; font-weight:700; color:var(--text); margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:340px; }
.drawer-close { background:none; border:none; font-size:22px; color:var(--text-muted); cursor:pointer; padding:0 4px; line-height:1; }
.drawer-close:hover { color:var(--text); }
.drawer-body { flex:1; overflow-y:auto; padding:20px; scrollbar-width:thin; }
.drawer-footer { padding:14px 20px; border-top:1px solid var(--border); background:var(--bg-secondary); flex-shrink:0; }
.drawer-section { margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid var(--border); }
.drawer-section:last-child { border-bottom:none; margin-bottom:0; }
.drawer-field { display:flex; align-items:flex-start; gap:8px; font-size:13px; color:var(--text); margin-bottom:6px; line-height:1.4; }
.drawer-field i { color:var(--text-muted); flex-shrink:0; margin-top:2px; }
.drawer-notes { white-space:pre-wrap; }
.drawer-status-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.drawer-actions { display:flex; flex-wrap:wrap; gap:8px; }

/* ── Appointment modal ────────────────────────────────────── */
#appt-modal { width:560px; max-width:100%; }
.appt-conflict-warn { display:none; background:#fef3c7; border:1px solid #f59e0b; color:#92400e; padding:10px 14px; border-radius:6px; font-size:13px; margin-top:8px; }
.visitor-search-results { position:absolute; top:100%; left:0; right:0; background:var(--card); border:1px solid var(--border); border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,.15); z-index:200; max-height:220px; overflow-y:auto; }
.visitor-search-item { padding:8px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid var(--border); }
.visitor-search-item:last-child { border-bottom:none; }
.visitor-search-item:hover { background:var(--bg-secondary); }
.visitor-search-wrap { position:relative; }
@media(max-width:640px) { .appt-drawer{width:100vw;} #cal-date-label{display:none;} }
</style>

<div class="layout-body">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <main class="main" id="main-content">
    <div class="container" style="max-width:none;padding:24px 24px 0;">

      <!-- ── Page toolbar ────────────────────────────────────────── -->
      <div class="cal-toolbar">
        <div>
          <h1 class="page-title" style="margin:0;">
            <i class="bi bi-calendar-week-fill" style="color:var(--secondary);"></i> Appointments
          </h1>
        </div>

        <!-- View toggle -->
        <div class="cal-view-pill" role="group" aria-label="Calendar view">
          <button data-cal-view="day"   aria-pressed="false">Day</button>
          <button data-cal-view="week"  aria-pressed="true" class="active">Week</button>
          <button data-cal-view="month" aria-pressed="false">Month</button>
        </div>

        <!-- Date navigator -->
        <div class="cal-nav" role="navigation" aria-label="Date navigation">
          <button id="cal-prev"  title="Previous" aria-label="Previous period"><i class="bi bi-chevron-left"></i></button>
          <button id="cal-today" title="Today">Today</button>
          <button id="cal-next"  title="Next"     aria-label="Next period"><i class="bi bi-chevron-right"></i></button>
        </div>
        <span id="cal-date-label" aria-live="polite"></span>

        <div style="margin-left:auto;">
          <?php if (can('manage_appointments')): ?>
          <button class="btn btn-primary" id="cal-new-appt">
            <i class="bi bi-calendar-plus-fill"></i> New Appointment
          </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Filter row ──────────────────────────────────────────── -->
      <div class="cal-filter-row">
        <select id="cal-filter-dept" class="form-control" style="width:auto;min-width:150px;" aria-label="Filter by department">
          <option value="">All Departments</option>
          <?php foreach ($departments as $d): ?>
          <option value="<?= e($d['id']) ?>"><?= e($d['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <select id="cal-filter-status" class="form-control" style="width:auto;" aria-label="Filter by status">
          <option value="">All Statuses</option>
          <option value="scheduled">Scheduled</option>
          <option value="confirmed">Confirmed</option>
          <option value="arrived">Arrived</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
          <option value="no_show">No-show</option>
        </select>

        <div style="position:relative;">
          <input type="search" id="cal-filter-search" class="form-control" placeholder="Search visitor, host…" style="width:220px;" aria-label="Search appointments">
        </div>
      </div>

      <!-- ── Calendar grid ───────────────────────────────────────── -->
      <div id="cal-grid" aria-live="polite" aria-label="Appointments calendar">
        <!-- Populated by calendar.js -->
      </div>

    </div><!-- /.container -->
  </main>
</div>

<!-- ── Side drawer ─────────────────────────────────────────────────────────── -->
<div id="appt-drawer-backdrop" class="drawer-backdrop" aria-hidden="true"></div>
<aside id="appt-drawer" class="appt-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
  <div class="drawer-header">
    <h2 class="drawer-title" id="drawer-title">Appointment</h2>
    <button class="drawer-close" id="drawer-close" aria-label="Close drawer">&times;</button>
  </div>
  <div class="drawer-body" id="drawer-body"></div>
  <div class="drawer-footer" id="drawer-footer"></div>
</aside>

<!-- ── Appointment create/edit modal ───────────────────────────────────────── -->
<div class="modal-backdrop" id="appt-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="appt-modal-title" aria-hidden="true">
  <div class="modal" id="appt-modal">
    <div class="modal-header">
      <h3 class="modal-title" id="appt-modal-title">New Appointment</h3>
      <button class="modal-close" id="appt-modal-close" aria-label="Close">&times;</button>
    </div>
    <form id="appt-form" novalidate>
      <input type="hidden" id="appt-form-action" value="create">
      <input type="hidden" id="appt-id" name="id">
      <input type="hidden" id="appt-visitor-id" name="visitor_id">
      <input type="hidden" id="appt-visitor-name" name="visitor_name">

      <div class="modal-body" style="max-height:70vh;overflow-y:auto;padding:20px;">
        <!-- Visitor search -->
        <div class="form-group">
          <label>Visitor <span class="text-danger">*</span></label>
          <div class="visitor-search-wrap">
            <input type="text" id="appt-visitor-search" class="form-control" placeholder="Search existing visitor by name or phone…" autocomplete="off">
            <div id="appt-visitor-results" class="visitor-search-results" style="display:none;"></div>
          </div>
          <small style="color:var(--text-muted);">Or leave blank and enter guest details below.</small>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="appt-phone">Phone</label>
            <input type="tel" id="appt-phone" name="phone" class="form-control" placeholder="+92 300 0000000">
          </div>
          <div class="form-group">
            <label for="appt-email">Email</label>
            <input type="email" id="appt-email" name="email" class="form-control" placeholder="visitor@example.com">
          </div>
        </div>

        <!-- Host & department -->
        <div class="form-row">
          <div class="form-group">
            <label for="appt-host">Person to Meet <span class="text-danger">*</span></label>
            <input type="text" id="appt-host" name="person_to_meet" class="form-control" required placeholder="Full name of host">
          </div>
          <div class="form-group">
            <label for="appt-dept">Department</label>
            <select id="appt-dept" name="department_id" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($departments as $d): ?>
              <option value="<?= e($d['id']) ?>"><?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Date, time, duration -->
        <div class="form-row">
          <div class="form-group">
            <label for="appt-datetime">Date &amp; Time <span class="text-danger">*</span></label>
            <input type="datetime-local" id="appt-datetime" name="scheduled_at" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="appt-duration">Duration</label>
            <select id="appt-duration" name="duration_minutes" class="form-control">
              <option value="15">15 min</option>
              <option value="30" selected>30 min</option>
              <option value="45">45 min</option>
              <option value="60">1 hour</option>
              <option value="90">1.5 hours</option>
              <option value="120">2 hours</option>
            </select>
          </div>
        </div>

        <!-- Conflict warning -->
        <div id="appt-conflict-warning" class="appt-conflict-warn"></div>

        <!-- Purpose -->
        <div class="form-group">
          <label for="appt-purpose">Purpose <span class="text-danger">*</span></label>
          <textarea id="appt-purpose" name="purpose" class="form-control" rows="2" required placeholder="Reason for visit…"></textarea>
        </div>

        <!-- Notes -->
        <div class="form-group">
          <label for="appt-notes">Notes <small style="color:var(--text-muted);">(optional)</small></label>
          <textarea id="appt-notes" name="notes" class="form-control" rows="2" placeholder="Internal notes…"></textarea>
        </div>

        <!-- Email toggle -->
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-check">
            <input type="checkbox" id="appt-send-email" checked>
            <span>Send confirmation email with e-Pass QR (requires visitor email)</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="SVMS_Calendar.closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="appt-form-save">Save Appointment</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  window.BASE_URL = <?= json_encode(BASE_URL) ?>;

  SVMS_Calendar.init({
    isSuperAdmin: <?= $is_super ? 'true' : 'false' ?>,
    defaultView:  'week',
    departments:  <?= json_encode(array_values($departments), JSON_UNESCAPED_UNICODE) ?>,
  });

  // Show/hide visitor-search results dropdown
  var vsRes = document.getElementById('appt-visitor-results');
  var vsIn  = document.getElementById('appt-visitor-search');

  document.addEventListener('click', function (e) {
    if (vsRes && !e.target.closest('.visitor-search-wrap')) vsRes.style.display = 'none';
  });

  if (vsIn && vsRes) {
    vsIn.addEventListener('focus', function () {
      if (vsRes.innerHTML) vsRes.style.display = 'block';
    });
    vsIn.addEventListener('input', function () {
      document.getElementById('appt-visitor-id').value   = '';
      document.getElementById('appt-visitor-name').value = vsIn.value;
    });

    // Auto-show results when calendar.js populates them
    new MutationObserver(function () {
      vsRes.style.display = vsRes.innerHTML ? 'block' : 'none';
    }).observe(vsRes, { childList: true });
  }
});
</script>

<?php
// Remove old POST handler — all mutations now go through api/appointment.php
$action = null; // unused placeholder suppresses IDE warnings
if (false) { $action = $_POST['action'] ?? ''; } // dead code guard
?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
