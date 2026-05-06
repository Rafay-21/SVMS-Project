/* ============================================================
   dashboard.js — SVMS Dashboard Module v2.3
   Manages Charts, live stat refresh, elapsed timers,
   and notification polling for the admin dashboard.
   ============================================================ */
(function (SVMS) {
  'use strict';

  /* ── CSS variable reader ──────────────────────────────────── */
  function cssVar(name) {
    return getComputedStyle(document.documentElement)
             .getPropertyValue(name).trim() || '#2e75b6';
  }

  /* ── Lerp counter animation ───────────────────────────────── */
  // Animates a stat-number element from its current displayed value
  // to `target` over 600ms using cubic-bezier easing.
  function animateCount(el, target) {
    if (!el) return;
    var start    = parseInt(el.textContent, 10) || 0;
    var delta    = target - start;
    if (delta === 0) return;
    var startTs  = null;
    var duration = 600;

    function step(ts) {
      if (!startTs) startTs = ts;
      var progress = Math.min((ts - startTs) / duration, 1);
      // cubic-bezier(0.4, 0, 0.2, 1) approximation
      var ease = progress < 0.5
        ? 2 * progress * progress
        : -1 + (4 - 2 * progress) * progress;
      el.textContent = Math.round(start + delta * ease);
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  /* ── Skeleton shimmer helpers ─────────────────────────────── */
  function showSkeletons() {
    ['stat-today','stat-checked-in','stat-checked-out','stat-month'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) {
        el.dataset.prevText = el.textContent;
        el.innerHTML = '<span class="skeleton skeleton-text" style="display:inline-block;width:48px;height:28px;border-radius:4px;vertical-align:middle;"></span>';
      }
    });
  }

  function hideSkeletons(data) {
    var map = {
      'stat-today':       data.today_total,
      'stat-checked-in':  data.checked_in,
      'stat-checked-out': data.checked_out_today,
      'stat-month':       data.month_total
    };
    Object.keys(map).forEach(function(id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.textContent = el.dataset.prevText || '0';
      animateCount(el, map[id]);
    });
  }

  /* ── Chart instances (kept for re-init on theme change) ───── */
  var doughnutChart = null;
  var lineChart     = null;

  /* ── Doughnut: Visit Status — Today ──────────────────────── */
  function initDoughnut(data) {
    var ctx = document.getElementById('status-doughnut');
    if (!ctx || !window.Chart) return;

    if (doughnutChart) { doughnutChart.destroy(); doughnutChart = null; }

    var bd   = data || (window.SVMS_DASHBOARD_DATA && window.SVMS_DASHBOARD_DATA.doughnut) || {};
    var ci   = bd.checked_in  || 0;
    var co   = bd.checked_out || 0;
    var ns   = bd.no_show     || 0;
    var hasData = ci + co + ns > 0;

    doughnutChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Checked In', 'Checked Out', 'No Show'],
        datasets: [{
          data: hasData ? [ci, co, ns] : [1],
          backgroundColor: hasData
            ? [cssVar('--accent'), cssVar('--secondary'), cssVar('--danger')]
            : ['rgba(0,0,0,.06)'],
          borderWidth: 3,
          borderColor: cssVar('--card') || '#fff',
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: { display: false },
          tooltip: {
            enabled: hasData,
            callbacks: {
              label: function(ctx) {
                return ' ' + ctx.label + ': ' + ctx.raw;
              }
            }
          }
        }
      }
    });

    // Update legend numbers
    var ciEl = document.getElementById('doughnut-label-ci');
    var coEl = document.getElementById('doughnut-label-co');
    var nsEl = document.getElementById('doughnut-label-ns');
    if (ciEl) ciEl.textContent = ci;
    if (coEl) coEl.textContent = co;
    if (nsEl) nsEl.textContent = ns;

    var upEl = document.getElementById('doughnut-updated');
    if (upEl) {
      var now = new Date();
      upEl.textContent = now.toTimeString().slice(0, 8);
    }
  }

  /* ── Line: 7-Day Visitor Trend ────────────────────────────── */
  function initLineChart(data) {
    var ctx = document.getElementById('trend-line-chart');
    if (!ctx || !window.Chart) return;

    if (lineChart) { lineChart.destroy(); lineChart = null; }

    var cd      = data || (window.SVMS_DASHBOARD_DATA && window.SVMS_DASHBOARD_DATA.chart7day) || {};
    var labels  = cd.labels || [];
    var values  = cd.data   || [];
    var color   = cssVar('--secondary');
    var textMuted = cssVar('--text-muted') || 'rgba(0,0,0,.5)';
    var borderColor = cssVar('--border')   || 'rgba(0,0,0,.08)';

    // Build gradient fill
    var gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0,   hexToRgba(color, 0.22));
    gradient.addColorStop(1,   hexToRgba(color, 0));

    lineChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          data:            values,
          borderColor:     color,
          backgroundColor: gradient,
          tension:         0.4,
          fill:            true,
          pointBackgroundColor: color,
          pointRadius:     4,
          pointHoverRadius: 6,
          borderWidth:     2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: cssVar('--card')  || '#fff',
            titleColor:      cssVar('--text')   || '#111',
            bodyColor:       cssVar('--text')   || '#111',
            borderColor:     borderColor,
            borderWidth:     1,
            padding:         10,
            callbacks: {
              title: function(items) {
                // e.g. "Mon 14 Apr"
                var idx   = items[0].dataIndex;
                var day   = labels[idx] || '';
                var date  = new Date();
                date.setDate(date.getDate() - (6 - idx));
                return day + ' ' + date.getDate() + ' ' + date.toLocaleString('default', { month: 'short' });
              },
              label: function(item) {
                return ' ' + item.raw + ' visitor' + (item.raw !== 1 ? 's' : '');
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { display: false },
            border: { display: false },
            ticks: {
              maxTicksLimit: 4,
              precision: 0,
              color: textMuted,
              font: { size: 11 }
            }
          },
          x: {
            grid: { display: false },
            border: { display: false },
            ticks: { color: textMuted, font: { size: 11 } }
          }
        }
      }
    });
  }

  /* ── Hex → rgba helper ────────────────────────────────────── */
  function hexToRgba(hex, alpha) {
    hex = hex.replace('#', '');
    if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
    var r = parseInt(hex.slice(0, 2), 16);
    var g = parseInt(hex.slice(2, 4), 16);
    var b = parseInt(hex.slice(4, 6), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  /* ── Elapsed time formatter ───────────────────────────────── */
  function formatElapsed(isoStr) {
    var diff = Math.floor((Date.now() - new Date(isoStr).getTime()) / 1000);
    if (diff < 60)   return diff + 's';
    var h = Math.floor(diff / 3600);
    var m = Math.floor((diff % 3600) / 60);
    return h > 0 ? h + 'h ' + m + 'm' : m + 'm';
  }

  function elapsedColor(isoStr) {
    var h = (Date.now() - new Date(isoStr).getTime()) / 3600000;
    if (h <= 2) return 'color:#065f46;background:#d1fae5;';
    if (h <= 4) return 'color:#92400e;background:#fef3c7;';
    return 'color:#991b1b;background:#fee2e2;';
  }

  /* ── Refresh elapsed chips every 30s ─────────────────────── */
  function refreshElapsed() {
    document.querySelectorAll('.elapsed-chip[data-checkin-time]').forEach(function(chip) {
      var t = chip.dataset.checkinTime;
      if (!t) return;
      chip.textContent  = formatElapsed(t);
      chip.style.cssText = 'font-size:12px;font-weight:600;padding:3px 8px;border-radius:20px;' + elapsedColor(t);
    });
  }

  /* ── Stats refresh every 60s ──────────────────────────────── */
  function refreshStats() {
    showSkeletons();
    SVMS.fetch(BASE_URL + 'api/get_stats.php')
      .then(function(data) {
        if (!data || data.error) return;
        hideSkeletons(data);

        // Update trend chip
        var trendEl = document.getElementById('stat-trend');
        if (trendEl && data.trend_yesterday_pct !== undefined) {
          var pct = data.trend_yesterday_pct;
          var up  = pct >= 0;
          trendEl.className = 'stat-trend ' + (up ? 'trend-up' : 'trend-down');
          trendEl.innerHTML = '<i class="bi bi-arrow-' + (up ? 'up' : 'down') + '-short"></i> '
            + Math.abs(pct) + '% vs yesterday';
        }

        // Update active-count badge
        var badge = document.getElementById('active-count-badge');
        if (badge && data.checked_in !== undefined) badge.textContent = data.checked_in;

        // Re-render doughnut with fresh breakdown
        if (data.status_breakdown) {
          initDoughnut(data.status_breakdown);
        }
      })
      .catch(function() {});
  }

  /* ── Notifications (poll every 5s) ───────────────────────── */
  var _lastNotifTime = null;

  SVMS.notifications = SVMS.notifications || {};
  SVMS.notifications.poll = function() {
    var url = BASE_URL + 'api/notifications.php';
    if (_lastNotifTime) url += '?since=' + encodeURIComponent(_lastNotifTime);

    SVMS.fetch(url)
      .then(function(resp) {
        if (!resp || resp.error) return;

        var count = resp.unread_count || 0;
        updateBell(count);

        if (_lastNotifTime && resp.notifications && resp.notifications.length) {
          prependNewNotifs(resp.notifications);
        }

        if (resp.server_time) _lastNotifTime = resp.server_time;
      })
      .catch(function() {});
  };

  function updateBell(count) {
    var dot   = document.getElementById('notif-badge');
    var bell  = document.getElementById('notification-bell');
    if (dot) {
      dot.textContent = count > 0 ? (count > 99 ? '99+' : count) : '';
      dot.style.display = count > 0 ? 'flex' : 'none';
    }
    if (bell) bell.setAttribute('aria-label', 'Notifications' + (count > 0 ? ' (' + count + ' unread)' : ''));
  }

  function prependNewNotifs(items) {
    var list = document.getElementById('notif-list-body');
    if (!list) return;
    items.forEach(function(n) {
      var el = buildNotifItem(n);
      el.style.opacity = '0';
      el.style.transform = 'translateY(-8px)';
      list.prepend(el);
      // Slide-in
      requestAnimationFrame(function() {
        el.style.transition = 'opacity 250ms, transform 250ms';
        el.style.opacity  = '1';
        el.style.transform = 'translateY(0)';
      });
    });
    // Play tick sound if enabled
    if (window.SVMS_SOUND_ENABLED) playTick();
  }

  function buildNotifItem(n) {
    var el = document.createElement('a');
    el.href = n.url || '#';
    el.className = 'notif-item' + (n.is_read ? '' : ' notif-unread');
    el.style.cssText = 'display:flex;gap:12px;align-items:flex-start;padding:12px 16px;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text);transition:background .15s;'
      + (n.is_read ? '' : 'border-left:3px solid var(--secondary);background:var(--soft,rgba(46,117,182,.04));');
    el.innerHTML =
      '<i class="bi bi-' + (n.icon || 'bell-fill') + '" style="font-size:18px;color:var(--secondary);flex-shrink:0;margin-top:2px;"></i>' +
      '<div style="flex:1;min-width:0;">' +
        '<div style="font-size:13px;font-weight:' + (n.is_read ? '400' : '600') + ';color:var(--text);line-height:1.35;">' +
          escHtml(n.title || n.body || '') + '</div>' +
        (n.body && n.title ? '<div style="font-size:12px;color:var(--text-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escHtml(n.body) + '</div>' : '') +
        '<div style="font-size:11px;color:var(--text-muted);margin-top:4px;">' + escHtml(n.rel_time || '') + '</div>' +
      '</div>';
    el.addEventListener('click', function(e) {
      if (!n.url || n.url === '#') e.preventDefault();
      markNotifRead(n.id);
    });
    return el;
  }

  function escHtml(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function markNotifRead(id) {
    var fd = new FormData();
    fd.append('action', 'mark_read');
    fd.append('id', id);
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
    fetch(BASE_URL + 'api/notifications.php', { method:'POST', body: fd, credentials:'same-origin' })
      .then(function() { SVMS.notifications.poll(); })
      .catch(function() {});
  }

  function markAllRead() {
    var fd = new FormData();
    fd.append('action', 'mark_all_read');
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
    fetch(BASE_URL + 'api/notifications.php', { method:'POST', body: fd, credentials:'same-origin' })
      .then(function() { SVMS.notifications.poll(); renderNotifList(); })
      .catch(function() {});
  }

  function renderNotifList() {
    var body = document.getElementById('notif-list-body');
    if (!body) return;
    body.innerHTML = '<div style="padding:16px;text-align:center;"><span class="skeleton skeleton-text" style="display:inline-block;width:80%;height:14px;border-radius:3px;"></span></div>';

    SVMS.fetch(BASE_URL + 'api/notifications.php')
      .then(function(resp) {
        body.innerHTML = '';
        if (!resp || !resp.notifications || !resp.notifications.length) {
          body.innerHTML = '<div style="padding:32px 16px;text-align:center;">' +
            '<i class="bi bi-bell-slash" style="font-size:36px;color:var(--text-muted);display:block;margin-bottom:8px;opacity:.4;"></i>' +
            '<p style="font-size:13px;color:var(--text-muted);margin:0;">You\'re all caught up</p></div>';
          return;
        }
        resp.notifications.forEach(function(n) {
          body.appendChild(buildNotifItem(n));
        });
        if (resp.server_time) _lastNotifTime = resp.server_time;
        updateBell(resp.unread_count || 0);
      })
      .catch(function() {
        body.innerHTML = '<div style="padding:16px;color:var(--danger);font-size:13px;">Failed to load.</div>';
      });
  }

  /* ── Soft tick sound ──────────────────────────────────────── */
  function playTick() {
    try {
      var ctx = new (window.AudioContext || window.webkitAudioContext)();
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.connect(gain); gain.connect(ctx.destination);
      osc.frequency.value = 880; osc.type = 'sine';
      gain.gain.setValueAtTime(0.08, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.15);
      osc.start(); osc.stop(ctx.currentTime + 0.15);
    } catch(e) {}
  }

  /* ── Bell dropdown wiring ─────────────────────────────────── */
  function initBellDropdown() {
    var bell  = document.getElementById('notification-bell');
    var panel = document.getElementById('notification-panel');
    if (!bell || !panel) return;

    bell.addEventListener('click', function(e) {
      e.stopPropagation();
      var open = panel.classList.toggle('open');
      if (open) renderNotifList();
    });

    document.addEventListener('click', function(e) {
      if (!panel.contains(e.target) && e.target !== bell) {
        panel.classList.remove('open');
      }
    });

    var markAll = document.getElementById('notif-mark-all');
    if (markAll) markAll.addEventListener('click', function(e) { e.preventDefault(); markAllRead(); });
  }

  /* ── Expose elapsed helpers globally (reused by checkin_checkout.php) ── */
  SVMS.elapsed = {
    format: formatElapsed,
    color:  elapsedColor,
    refresh: refreshElapsed
  };

  /* ── Re-init charts on theme change ──────────────────────── */
  document.addEventListener('svms:themechange', function() {
    if (window.SVMS_DASHBOARD_DATA) {
      initDoughnut(window.SVMS_DASHBOARD_DATA.doughnut);
      initLineChart(window.SVMS_DASHBOARD_DATA.chart7day);
    }
  });

  /* ── Boot ─────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function() {
    var d = window.SVMS_DASHBOARD_DATA || {};

    // Charts
    initDoughnut(d.doughnut || null);
    initLineChart(d.chart7day || null);

    // Notifications: initial poll to get server_time baseline
    SVMS.notifications.poll();

    // Bell dropdown
    initBellDropdown();

    // Stat refresh every 60s
    setInterval(refreshStats, 60000);

    // Elapsed time refresh every 30s
    refreshElapsed();
    setInterval(refreshElapsed, 30000);

    // Notification poll every 5s
    setInterval(SVMS.notifications.poll, 5000);
  });

}(window.SVMS = window.SVMS || {}));

