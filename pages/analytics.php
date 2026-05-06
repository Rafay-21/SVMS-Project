<?php
/**
 * pages/analytics.php — Analytics Dashboard (Phase 5.1)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('view_analytics');

$page_title       = 'Analytics';
$page_uses_charts = true;
$page_extra_js    = ['analytics.js'];

// Default date range: last 30 days
$_today    = date('Y-m-d');
$_from     = date('Y-m-d', strtotime('-29 days'));
$_to       = $_today;
$_gran     = 'day';
$_preset   = '30d';

include __DIR__ . '/../includes/header.php';
?>
<!-- Analytics-specific CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/analytics.css">
<!-- html2canvas for heatmap PNG export -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>

<!-- Config for analytics.js -->
<script>
window.AN_CONFIG = {
  from       : <?= json_encode($_from) ?>,
  to         : <?= json_encode($_to) ?>,
  granularity: 'day',
  preset     : '30d'
};
</script>

<div class="container" id="main-content">

  <!-- ── Toolbar ───────────────────────────────────────────── -->
  <div class="an-toolbar">
    <div style="flex:1;min-width:0;">
      <h1 class="page-title" style="margin:0 0 2px;">
        <i class="bi bi-bar-chart-fill" style="color:var(--secondary);margin-right:8px;"></i>Analytics
      </h1>
      <p class="page-subtitle" style="margin:0;">Insights into visitor patterns and trends.</p>
    </div>

    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <!-- Preset pills -->
      <div class="an-preset-group" role="group" aria-label="Date presets">
        <button class="an-preset-btn" data-preset="today">Today</button>
        <button class="an-preset-btn" data-preset="yesterday">Yesterday</button>
        <button class="an-preset-btn" data-preset="7d">7d</button>
        <button class="an-preset-btn active" data-preset="30d">30d</button>
        <button class="an-preset-btn" data-preset="90d">90d</button>
        <button class="an-preset-btn" data-preset="month">Month</button>
        <button class="an-preset-btn" data-preset="year">Year</button>
        <button class="an-preset-btn" data-preset="custom">Custom…</button>
      </div>

      <!-- Custom range -->
      <div class="an-custom-wrap" id="an-custom-wrap">
        <input type="date" id="an-from-input" class="an-date-input" aria-label="From date">
        <span style="color:var(--text-muted)">–</span>
        <input type="date" id="an-to-input" class="an-date-input" aria-label="To date">
        <button id="an-custom-apply" class="btn btn-primary" style="padding:5px 14px;font-size:12px;">Apply</button>
      </div>

      <!-- Granularity -->
      <div class="an-preset-group" role="group" aria-label="Granularity">
        <button class="an-gran-btn active" data-gran="day">Day</button>
        <button class="an-gran-btn" data-gran="week">Week</button>
        <button class="an-gran-btn" data-gran="month">Month</button>
      </div>

      <!-- Date range display -->
      <span id="an-date-display" style="font-size:12px;font-weight:600;color:var(--text-muted);white-space:nowrap;"></span>

      <!-- Refresh -->
      <button id="an-refresh-btn" class="btn btn-secondary" style="padding:6px 10px;" title="Refresh data">
        <i class="bi bi-arrow-clockwise"></i>
      </button>

      <!-- Export PDF — opens modal -->
      <button id="an-export-pdf-btn" class="btn btn-primary" style="padding:6px 12px;font-size:12px;"
              onclick="document.getElementById('pdf-modal-bd').style.display='flex'">
        <i class="bi bi-file-earmark-pdf"></i> Export PDF
      </button>
    </div>
  </div>

  <!-- ── KPI grid ───────────────────────────────────────────── -->
  <div class="an-kpi-grid" id="kpi-grid">

    <div class="an-kpi-card">
      <div class="an-kpi-label"><i class="bi bi-people"></i> Total Visits</div>
      <div class="an-kpi-value an-skeleton-target" id="kpi-total-val">—</div>
      <span class="an-kpi-delta neutral" id="kpi-total-delta"></span>
      <div class="an-kpi-prev" id="kpi-total-prev"></div>
      <span class="an-kpi-icon bi bi-people-fill"></span>
    </div>

    <div class="an-kpi-card">
      <div class="an-kpi-label"><i class="bi bi-person-badge"></i> Unique Visitors</div>
      <div class="an-kpi-value an-skeleton-target" id="kpi-unique-val">—</div>
      <span class="an-kpi-delta neutral" id="kpi-unique-delta"></span>
      <div class="an-kpi-prev" id="kpi-unique-prev"></div>
      <span class="an-kpi-icon bi bi-person-badge-fill"></span>
    </div>

    <div class="an-kpi-card">
      <div class="an-kpi-label"><i class="bi bi-clock"></i> Avg. Visit Duration</div>
      <div class="an-kpi-value an-skeleton-target" id="kpi-duration-val">—</div>
      <span class="an-kpi-delta neutral" id="kpi-duration-delta"></span>
      <div class="an-kpi-prev" id="kpi-duration-prev"></div>
      <span class="an-kpi-icon bi bi-clock-fill"></span>
    </div>

    <div class="an-kpi-card">
      <div class="an-kpi-label"><i class="bi bi-lightning"></i> Peak Hour</div>
      <div class="an-kpi-value an-skeleton-target" id="kpi-peak-val">—</div>
      <span class="an-kpi-delta" id="kpi-peak-delta" style="display:none"></span>
      <div class="an-kpi-prev" style="font-size:11px;color:var(--text-muted)">busiest time window</div>
      <span class="an-kpi-icon bi bi-lightning-fill"></span>
    </div>

  </div>

  <!-- ── Chart grid ─────────────────────────────────────────── -->
  <div class="an-chart-grid">

    <!-- Peak Hours Heatmap -->
    <div class="an-card">
      <div class="an-card-header">
        <h3 class="an-card-title"><i class="bi bi-grid-3x3-gap-fill" style="color:var(--secondary)"></i> Peak Hours</h3>
        <button class="an-export-btn" data-export="heatmap" title="Export PNG">
          <i class="bi bi-download"></i> PNG
        </button>
      </div>
      <div class="an-card-body">
        <div class="an-heatmap-wrap">
          <div class="an-heatmap" id="an-heatmap" role="img" aria-label="Peak hours heatmap"></div>
        </div>
        <div class="an-heatmap-legend" id="an-heatmap-legend">Loading…</div>
      </div>
    </div>

    <!-- Top Departments -->
    <div class="an-card">
      <div class="an-card-header">
        <h3 class="an-card-title"><i class="bi bi-building" style="color:var(--secondary)"></i> Top Departments</h3>
        <button class="an-export-btn" data-export="dept" title="Export PNG">
          <i class="bi bi-download"></i> PNG
        </button>
      </div>
      <div class="an-card-body">
        <div style="position:relative;height:280px;">
          <canvas id="chart-dept" aria-label="Departments bar chart"></canvas>
        </div>
        <p style="font-size:11px;color:var(--text-muted);margin-top:6px;text-align:center">
          Click a bar to drill down into visits
        </p>
      </div>
    </div>

    <!-- Visit Purposes -->
    <div class="an-card">
      <div class="an-card-header">
        <h3 class="an-card-title"><i class="bi bi-pie-chart-fill" style="color:var(--secondary)"></i> Visit Purposes</h3>
        <button class="an-export-btn" data-export="purpose" title="Export PNG">
          <i class="bi bi-download"></i> PNG
        </button>
      </div>
      <div class="an-card-body">
        <div class="an-pie-wrap">
          <div class="an-pie-canvas-wrap">
            <canvas id="chart-purpose" aria-label="Purposes doughnut chart" style="max-width:200px;max-height:200px;"></canvas>
          </div>
          <div class="an-legend" id="purpose-legend"></div>
        </div>
      </div>
    </div>

    <!-- Daily Trend -->
    <div class="an-card">
      <div class="an-card-header">
        <h3 class="an-card-title"><i class="bi bi-graph-up" style="color:var(--secondary)"></i> Daily Trend</h3>
        <button class="an-export-btn" data-export="trend" title="Export PNG">
          <i class="bi bi-download"></i> PNG
        </button>
      </div>
      <div class="an-card-body">
        <div style="position:relative;height:280px;">
          <canvas id="chart-trend" aria-label="Daily visits trend chart"></canvas>
        </div>
      </div>
    </div>

  </div><!-- /an-chart-grid -->

  <!-- ── Top Visitors table ─────────────────────────────────── -->
  <div class="an-card" style="margin-bottom:24px;">
    <div class="an-card-header">
      <h3 class="an-card-title"><i class="bi bi-trophy-fill" style="color:#f59e0b"></i> Top 10 Visitors</h3>
    </div>
    <div style="overflow-x:auto;">
      <table class="an-table" id="top-visitors-table" aria-label="Top 10 visitors">
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th>Name</th>
            <th>Visits</th>
            <th>Last Visit</th>
            <th>Total Time</th>
          </tr>
        </thead>
        <tbody id="top-visitors-body">
          <?php for ($i = 0; $i < 5; $i++): ?>
          <tr>
            <?php for ($j = 0; $j < 5; $j++): ?>
            <td><div class="an-skeleton" style="height:14px;border-radius:4px;"></div></td>
            <?php endfor; ?>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /container -->

<!-- ── Drilldown drawer ────────────────────────────────────── -->
<div id="drill-backdrop" class="an-drawer-bd" aria-hidden="true"></div>
<aside id="drill-drawer" class="an-drawer" role="dialog" aria-modal="true" aria-labelledby="drill-title">
  <div class="an-drawer-header">
    <h2 class="an-drawer-title" id="drill-title">Drilldown</h2>
    <button class="an-drawer-close" id="drill-close" aria-label="Close">&times;</button>
  </div>
  <div class="an-drawer-body" id="drill-body">
    <p style="color:var(--text-muted);text-align:center;padding:30px;">Select a department or purpose to drill down.</p>
  </div>
</aside>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- ══════════════════════════════════════════════════════════
     PDF EXPORT MODAL — analytics.php
     ══════════════════════════════════════════════════════════ -->

<!-- Backdrop -->
<div id="pdf-modal-bd" role="dialog" aria-modal="true" aria-labelledby="pdf-modal-title"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1300;
            align-items:center;justify-content:center;padding:16px;">

  <!-- Box -->
  <div style="background:var(--card);border-radius:var(--radius-md);width:100%;max-width:440px;
              box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;">

    <!-- Header -->
    <div style="background:var(--primary);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
      <h3 id="pdf-modal-title" style="color:#fff;font-size:15px;font-weight:700;margin:0;">
        <i class="bi bi-file-earmark-pdf" style="margin-right:8px;"></i>Export PDF Report
      </h3>
      <button onclick="document.getElementById('pdf-modal-bd').style.display='none'"
              style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1;padding:0 2px;"
              aria-label="Close">&times;</button>
    </div>

    <!-- Body -->
    <form id="pdf-export-form" style="padding:20px 24px;">

      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px;">Report Title</label>
        <input type="text" id="pdf-title" value="Visitor Activity Report" maxlength="120"
               style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);
                      background:var(--card);color:var(--text);font-size:13px;box-sizing:border-box;">
      </div>

      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px;">Notes (optional)</label>
        <textarea id="pdf-notes" rows="2" maxlength="400" placeholder="Additional context or disclaimer…"
                  style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);
                         background:var(--card);color:var(--text);font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
      </div>

      <div style="display:flex;gap:20px;margin-bottom:14px;">
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
          <input type="checkbox" id="pdf-include-charts" checked style="width:16px;height:16px;">
          <span>Include charts</span>
        </label>
        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;">
          <input type="checkbox" id="pdf-include-raw" checked style="width:16px;height:16px;">
          <span>Include raw data (last 50 rows)</span>
        </label>
      </div>

      <!-- Progress area -->
      <div id="pdf-progress" style="display:none;background:var(--bg-alt);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:14px;">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;" id="pdf-progress-label">Capturing charts…</div>
        <div style="height:4px;background:var(--border);border-radius:2px;overflow:hidden;">
          <div id="pdf-progress-bar" style="height:100%;background:var(--secondary);width:0%;transition:width .3s;border-radius:2px;"></div>
        </div>
      </div>

      <!-- Error area -->
      <div id="pdf-error" style="display:none;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius-sm);padding:10px 12px;margin-bottom:14px;font-size:12px;color:#dc2626;"></div>

      <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;">
        <button type="button" onclick="document.getElementById('pdf-modal-bd').style.display='none'"
                class="btn btn-secondary" style="font-size:13px;">Cancel</button>
        <button type="button" id="pdf-generate-btn" class="btn btn-primary" style="font-size:13px;">
          <i class="bi bi-download"></i> Generate &amp; Download
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';

  var btn  = document.getElementById('pdf-generate-btn');
  var bd   = document.getElementById('pdf-modal-bd');
  var err  = document.getElementById('pdf-error');
  var prog = document.getElementById('pdf-progress');
  var pBar = document.getElementById('pdf-progress-bar');
  var pLbl = document.getElementById('pdf-progress-label');

  function setProgress(pct, label) {
    if (prog) {
      prog.style.display = 'block';
      if (pBar) pBar.style.width = pct + '%';
      if (pLbl) pLbl.textContent = label || '';
    }
  }

  function showError(msg) {
    if (err) { err.textContent = msg; err.style.display = 'block'; }
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-download"></i> Generate &amp; Download'; }
    if (prog) prog.style.display = 'none';
  }

  function getCanvasB64(canvasId) {
    var c = document.getElementById(canvasId);
    if (!c) return '';
    try { return c.toDataURL('image/png', 1.0).replace(/^data:image\/png;base64,/, ''); }
    catch(e) { return ''; }
  }

  btn && btn.addEventListener('click', function () {
    if (err)  err.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating…';

    var includeCharts = document.getElementById('pdf-include-charts').checked;
    var includeRaw    = document.getElementById('pdf-include-raw').checked;
    var title         = document.getElementById('pdf-title').value.trim() || 'Visitor Activity Report';
    var notes         = document.getElementById('pdf-notes').value.trim();

    // Get current date range from AN state
    var an = window.AN_CONFIG || {};
    var currentFrom = an.from || '';
    var currentTo   = an.to   || '';
    // AN object may have updated state
    if (window._AN_STATE) { currentFrom = window._AN_STATE.from || currentFrom; currentTo = window._AN_STATE.to || currentTo; }

    var payload = {
      csrf_token     : (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
      from           : currentFrom,
      to             : currentTo,
      title          : title,
      notes          : notes,
      include_charts : includeCharts ? '1' : '0',
      include_raw    : includeRaw    ? '1' : '0',
      report_type    : 'analytics',
      kpi            : (window._AN_DATA && window._AN_DATA.kpi)          ? window._AN_DATA.kpi          : null,
      top_visitors   : (window._AN_DATA && window._AN_DATA.top_visitors) ? window._AN_DATA.top_visitors : [],
      departments    : (window._AN_DATA && window._AN_DATA.departments)  ? window._AN_DATA.departments  : [],
      purposes       : (window._AN_DATA && window._AN_DATA.purposes)     ? window._AN_DATA.purposes     : [],
      trend          : (window._AN_DATA && window._AN_DATA.trend)        ? window._AN_DATA.trend        : [],
      charts         : {}
    };

    function doFetch() {
      setProgress(80, 'Sending to server…');
      var url = (window.BASE_URL || '') + 'api/generate_report.php';
      fetch(url, {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify(payload)
      })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok) { showError(d.error || 'Server error generating PDF.'); return; }
        setProgress(100, 'Done! Starting download…');
        // Auto-download
        var a = document.createElement('a');
        a.href     = d.url;
        a.download = d.filename || 'report.pdf';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function() {
          bd.style.display = 'none';
          if (prog) prog.style.display = 'none';
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-download"></i> Generate &amp; Download';
          if (window.SVMS) SVMS.toast('PDF report downloaded successfully!', 'success');
        }, 800);
      })
      .catch(function(e) {
        showError('Network error: ' + e.message);
      });
    }

    if (!includeCharts) {
      doFetch();
      return;
    }

    // Capture charts
    setProgress(10, 'Capturing charts…');

    var chartPromises = [];
    ['chart-dept', 'chart-purpose', 'chart-trend'].forEach(function(id) {
      var key = id.replace('chart-', '');
      payload.charts[key] = getCanvasB64(id);
    });

    setProgress(40, 'Capturing heatmap…');
    var heatEl = document.getElementById('an-heatmap');
    if (heatEl && window.html2canvas) {
      html2canvas(heatEl, {
        backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--card').trim() || '#fff',
        scale: 2
      }).then(function(canvas) {
        payload.charts['heatmap'] = canvas.toDataURL('image/png', 1.0).replace(/^data:image\/png;base64,/, '');
        setProgress(60, 'Preparing report data…');
        doFetch();
      }).catch(function() {
        setProgress(60, 'Preparing report data…');
        doFetch();
      });
    } else {
      setProgress(60, 'Preparing report data…');
      doFetch();
    }
  });

  // Close on backdrop click
  bd && bd.addEventListener('click', function(e) {
    if (e.target === bd) bd.style.display = 'none';
  });
}());
</script>

