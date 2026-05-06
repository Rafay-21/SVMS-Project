/**
 * analytics.js — Analytics Dashboard logic (Phase 5.1)
 *
 * Depends on: Chart.js 4.x, window.BASE_URL, window.AN_CONFIG
 * Namespace:  window.AN = { load, exportChart }
 */
;(function () {
    'use strict';

    /* ── State ──────────────────────────────────────────────── */
    var state = {
        from        : '',
        to          : '',
        granularity : 'day',
        loading     : false,
    };
    var charts = {};   // { dept, purpose, trend }
    var data   = {};   // last API response

    /* ── Purpose colour palette ─────────────────────────────── */
    var PURPOSE_COLOURS = [
        '#2e75b6','#7c3aed','#059669','#d97706',
        '#dc2626','#0891b2','#9333ea','#94a3b8'
    ];

    /* ── Day labels (MySQL DOW: 1=Sun…7=Sat; display Mon-Sun) ── */
    var DOW_DISPLAY = [2, 3, 4, 5, 6, 7, 1]; // display order
    var DOW_LABELS  = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /* ── Helpers ─────────────────────────────────────────────── */
    function css(prop) {
        return getComputedStyle(document.documentElement)
            .getPropertyValue(prop).trim();
    }

    function pad2(n) { return String(n).padStart(2, '0'); }

    function dateStr(d) {
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    function displayDate(str) {
        var d = new Date(str + 'T00:00:00');
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function fmtDuration(min) {
        if (min < 60) return min + ' min';
        return Math.floor(min / 60) + 'h ' + (min % 60) + 'm';
    }

    function movingAverage(arr, win) {
        return arr.map(function (v, i) {
            var start = Math.max(0, i - win + 1);
            var slice = arr.slice(start, i + 1);
            var sum = slice.reduce(function (a, b) { return a + b; }, 0);
            return Math.round((sum / slice.length) * 10) / 10;
        });
    }

    /* ── Date presets ────────────────────────────────────────── */
    function applyPreset(preset) {
        var today  = new Date();
        var from, to;
        to = dateStr(today);

        switch (preset) {
            case 'today':
                from = to;
                break;
            case 'yesterday': {
                var y = new Date(today); y.setDate(y.getDate() - 1);
                from = to = dateStr(y);
                break;
            }
            case '7d': {
                var d = new Date(today); d.setDate(d.getDate() - 6);
                from = dateStr(d);
                break;
            }
            case '30d': {
                var d = new Date(today); d.setDate(d.getDate() - 29);
                from = dateStr(d);
                break;
            }
            case '90d': {
                var d = new Date(today); d.setDate(d.getDate() - 89);
                from = dateStr(d);
                break;
            }
            case 'month': {
                from = dateStr(new Date(today.getFullYear(), today.getMonth(), 1));
                break;
            }
            case 'year': {
                from = dateStr(new Date(today.getFullYear(), 0, 1));
                break;
            }
            case 'custom':
                showCustomPicker();
                return;
        }

        state.from = from;
        state.to   = to;

        document.querySelectorAll('.an-preset-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.preset === preset);
        });

        var display = document.getElementById('an-date-display');
        if (display) display.textContent = displayDate(from) + ' – ' + displayDate(to);

        var wrap = document.getElementById('an-custom-wrap');
        if (wrap) wrap.classList.remove('visible');

        autoGranularity();
        load();
    }

    function autoGranularity() {
        var span = (new Date(state.to) - new Date(state.from)) / 86400000;
        if (span <= 31)       state.granularity = 'day';
        else if (span <= 180) state.granularity = 'week';
        else                  state.granularity = 'month';

        document.querySelectorAll('.an-gran-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.gran === state.granularity);
        });
    }

    function showCustomPicker() {
        document.querySelectorAll('.an-preset-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.preset === 'custom');
        });
        var wrap = document.getElementById('an-custom-wrap');
        if (!wrap) return;
        wrap.classList.add('visible');

        var fi = document.getElementById('an-from-input');
        var ti = document.getElementById('an-to-input');
        if (fi) fi.value = state.from;
        if (ti) ti.value = state.to;
    }

    /* ── API fetch ───────────────────────────────────────────── */
    function load(forceRefresh) {
        if (state.loading) return;
        state.loading = true;
        showSkeletons();

        var qs = 'from=' + encodeURIComponent(state.from)
               + '&to='  + encodeURIComponent(state.to)
               + '&granularity=' + encodeURIComponent(state.granularity);
        if (forceRefresh) qs += '&refresh=1';

        var url = (window.BASE_URL || '') + 'api/analytics_data.php?' + qs;

        (window.SVMS ? window.SVMS.fetch(url) : fetch(url).then(function(r){ return r.json(); }))
            .then(function (d) {
                state.loading = false;
                if (!d || !d.ok) {
                    hideSkeletons();
                    return;
                }
                data = d;
                render(d);
            })
            .catch(function () {
                state.loading = false;
                hideSkeletons();
            });
    }

    /* ── Drill-down fetch ─────────────────────────────────────── */
    function loadDrill(params) {
        var qs = 'from=' + encodeURIComponent(state.from)
               + '&to='  + encodeURIComponent(state.to)
               + '&' + params;
        var url = (window.BASE_URL || '') + 'api/analytics_data.php?' + qs;
        return (window.SVMS ? window.SVMS.fetch(url) : fetch(url).then(function(r){ return r.json(); }));
    }

    /* ── Render all ──────────────────────────────────────────── */
    function render(d) {
        // Expose to window for PDF export modal
        window._AN_DATA  = d;
        window._AN_STATE = state;
        renderKPI(d.kpi);
        renderHeatmap(d.heatmap);
        renderDeptChart(d.departments);
        renderPurposeChart(d.purposes);
        renderTrendChart(d.trend);
        renderTopVisitors(d.top_visitors);
        hideSkeletons();
    }

    /* ── KPI ─────────────────────────────────────────────────── */
    function renderKPI(kpi) {
        setText('kpi-total-val',    kpi.total_visits.toLocaleString());
        setText('kpi-unique-val',   kpi.unique_visitors.toLocaleString());
        setText('kpi-duration-val', fmtDuration(Math.round(kpi.avg_duration_min)));
        setText('kpi-peak-val', kpi.peak_hour !== null
            ? pad2(kpi.peak_hour) + ':00 – ' + pad2(kpi.peak_hour) + ':59'
            : '—');

        setDelta('kpi-total-delta',    kpi.delta_total);
        setDelta('kpi-unique-delta',   kpi.delta_unique);
        setDelta('kpi-duration-delta', kpi.delta_duration);

        ['kpi-total-prev','kpi-unique-prev','kpi-duration-prev'].forEach(function (id) {
            setText(id, 'vs. ' + (kpi.prev_label || ''));
        });
    }

    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function setDelta(id, delta) {
        var el = document.getElementById(id);
        if (!el || !delta || delta.pct === null) {
            if (el) { el.textContent = ''; el.className = 'an-kpi-delta neutral'; }
            return;
        }
        var arrow = delta.dir === 'up' ? '↑' : (delta.dir === 'down' ? '↓' : '—');
        var abs   = Math.abs(delta.pct);
        el.innerHTML = arrow + ' ' + abs + '%';
        el.className = 'an-kpi-delta ' + delta.dir;
    }

    /* ── Heatmap ─────────────────────────────────────────────── */
    function renderHeatmap(heatmap) {
        var container = document.getElementById('an-heatmap');
        if (!container) return;
        container.innerHTML = '';

        // Find max value
        var maxVal = 0;
        for (var d = 1; d <= 7; d++) {
            for (var h = 0; h < 24; h++) {
                var v = (heatmap[d] && heatmap[d][h]) ? parseInt(heatmap[d][h]) : 0;
                if (v > maxVal) maxVal = v;
            }
        }
        if (maxVal === 0) maxVal = 1;

        var primaryRGB = hexToRGB(css('--primary') || '#1a3c5e');

        // Hour header row (corner + 24 hour labels)
        var corner = makeEl('div', 'an-heat-corner');
        container.appendChild(corner);
        for (var h = 0; h < 24; h++) {
            var lbl = makeEl('div', 'an-heat-hour-label');
            lbl.textContent = h % 3 === 0 ? pad2(h) : '';
            container.appendChild(lbl);
        }

        // 7 day rows
        for (var di = 0; di < 7; di++) {
            var dow = DOW_DISPLAY[di];
            var dayLbl = makeEl('div', 'an-heat-day-label');
            dayLbl.textContent = DOW_LABELS[di];
            container.appendChild(dayLbl);

            for (var h = 0; h < 24; h++) {
                var cnt    = (heatmap[dow] && heatmap[dow][h]) ? parseInt(heatmap[dow][h]) : 0;
                var cell   = makeEl('div', 'an-heat-cell');
                if (cnt === 0) {
                    cell.dataset.empty = '1';
                } else {
                    var alpha = 0.08 + (cnt / maxVal) * 0.82;
                    cell.style.backgroundColor = 'rgba(' + primaryRGB + ',' + alpha.toFixed(3) + ')';
                }
                var tipDay  = DOW_LABELS[di];
                var tipHour = pad2(h) + ':00–' + pad2(h) + ':59';
                cell.dataset.tip = tipDay + ' ' + tipHour + ': ' + cnt + ' visit' + (cnt !== 1 ? 's' : '');
                container.appendChild(cell);
            }
        }

        // Legend
        var legend = document.getElementById('an-heatmap-legend');
        if (legend) {
            var swatches = '';
            var steps    = 5;
            for (var s = 0; s <= steps; s++) {
                var a = (0.08 + (s / steps) * 0.82).toFixed(2);
                swatches += '<span class="an-legend-swatch" style="background:rgba(' + primaryRGB + ',' + a + ')"></span>';
            }
            legend.innerHTML = 'Low ' + swatches + ' High';
        }
    }

    function hexToRGB(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex.split('').map(function(c){return c+c;}).join('');
        var r = parseInt(hex.substring(0, 2), 16);
        var g = parseInt(hex.substring(2, 4), 16);
        var b = parseInt(hex.substring(4, 6), 16);
        return r + ',' + g + ',' + b;
    }

    function makeEl(tag, cls) {
        var el = document.createElement(tag);
        if (cls) el.className = cls;
        return el;
    }

    /* ── Department horizontal bar ────────────────────────────── */
    function renderDeptChart(departments) {
        var canvas = document.getElementById('chart-dept');
        if (!canvas) return;
        var textColor  = css('--text') || '#1e293b';
        var mutedColor = css('--text-muted') || '#64748b';
        var gridColor  = css('--border') || '#e2e8f0';

        var labels  = departments.map(function(d){ return d.name; });
        var values  = departments.map(function(d){ return d.visits; });
        var bgColors = departments.map(function(d){ return d.colour + 'cc'; });
        var bdColors = departments.map(function(d){ return d.colour; });

        if (charts.dept) { charts.dept.destroy(); }

        charts.dept = new Chart(canvas, {
            type: 'bar',
            data: {
                labels  : labels,
                datasets: [{
                    label           : 'Visits',
                    data            : values,
                    backgroundColor : bgColors,
                    borderColor     : bdColors,
                    borderWidth     : 1,
                    borderRadius    : 4,
                }]
            },
            options: {
                indexAxis : 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation : { duration: 400 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) { return ' ' + ctx.parsed.x + ' visits'; }
                        }
                    }
                },
                scales: {
                    x: {
                        grid : { color: gridColor },
                        ticks: { color: mutedColor, font: { size: 11 } }
                    },
                    y: {
                        grid : { display: false },
                        ticks: { color: textColor, font: { size: 12 } }
                    }
                },
                onClick: function(evt, elements) {
                    if (elements.length > 0) {
                        var idx   = elements[0].index;
                        var dept  = departments[idx];
                        openDrilldown(dept.id, dept.name);
                    }
                }
            }
        });
    }

    /* ── Purposes doughnut ──────────────────────────────────── */
    function renderPurposeChart(purposes) {
        var canvas = document.getElementById('chart-purpose');
        if (!canvas) return;
        var total    = purposes.reduce(function(s, p){ return s + p.count; }, 0);
        var labels   = purposes.map(function(p){ return p.label; });
        var values   = purposes.map(function(p){ return p.count; });
        var colors   = purposes.map(function(_, i){ return PURPOSE_COLOURS[i % PURPOSE_COLOURS.length]; });
        var textColor = css('--text') || '#1e293b';

        if (charts.purpose) { charts.purpose.destroy(); }

        charts.purpose = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels  : labels,
                datasets: [{
                    data           : values,
                    backgroundColor: colors,
                    borderColor    : css('--card') || '#ffffff',
                    borderWidth    : 2,
                    hoverOffset    : 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                animation: { duration: 400 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var pct = total ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ' ' + ctx.parsed + ' visits (' + pct + '%)';
                            }
                        }
                    }
                },
                onClick: function(evt, elements) {
                    if (elements.length > 0) {
                        var idx = elements[0].index;
                        openPurposeDrilldown(labels[idx]);
                    }
                }
            },
            plugins: [{
                id: 'center-text',
                afterDraw: function(chart) {
                    var ctx  = chart.ctx;
                    var meta = chart.getDatasetMeta(0);
                    if (!meta || !meta.data || !meta.data.length) return;
                    var cx = chart.chartArea.left + (chart.chartArea.right  - chart.chartArea.left) / 2;
                    var cy = chart.chartArea.top  + (chart.chartArea.bottom - chart.chartArea.top)  / 2;
                    ctx.save();
                    ctx.font = 'bold 22px ' + (css('--font-base') || 'system-ui');
                    ctx.fillStyle = textColor;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(total.toLocaleString(), cx, cy - 8);
                    ctx.font = '11px ' + (css('--font-base') || 'system-ui');
                    ctx.fillStyle = css('--text-muted') || '#64748b';
                    ctx.fillText('visits', cx, cy + 12);
                    ctx.restore();
                }
            }]
        });

        // Custom legend
        var legendEl = document.getElementById('purpose-legend');
        if (legendEl) {
            legendEl.innerHTML = purposes.map(function(p, i) {
                var pct = total ? ((p.count / total) * 100).toFixed(0) : 0;
                return '<div class="an-legend-item" data-purpose="' + escHtml(p.label) + '">'
                     + '<span class="an-legend-dot" style="background:' + colors[i] + '"></span>'
                     + '<span>' + escHtml(p.label) + '</span>'
                     + '<span class="an-legend-pct">' + pct + '%</span>'
                     + '</div>';
            }).join('');

            legendEl.querySelectorAll('.an-legend-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    openPurposeDrilldown(item.dataset.purpose);
                });
            });
        }
    }

    /* ── Trend line ──────────────────────────────────────────── */
    function renderTrendChart(trend) {
        var canvas = document.getElementById('chart-trend');
        if (!canvas) return;
        var labels    = trend.map(function(t){ return t.date; });
        var visits    = trend.map(function(t){ return t.visits; });
        var ma7       = movingAverage(visits, 7);
        var textColor = css('--text')       || '#1e293b';
        var mutedClr  = css('--text-muted') || '#64748b';
        var gridColor = css('--border')     || '#e2e8f0';

        if (charts.trend) { charts.trend.destroy(); }

        var ctx  = canvas.getContext('2d');
        var grad = ctx.createLinearGradient(0, 0, 0, canvas.offsetHeight || 260);
        grad.addColorStop(0, 'rgba(46,117,182,0.30)');
        grad.addColorStop(1, 'rgba(46,117,182,0.00)');

        charts.trend = new Chart(canvas, {
            type: 'line',
            data: {
                labels  : labels,
                datasets: [
                    {
                        label          : 'Daily Visits',
                        data           : visits,
                        borderColor    : '#2e75b6',
                        backgroundColor: grad,
                        borderWidth    : 2,
                        pointRadius    : visits.length > 60 ? 0 : 3,
                        pointHoverRadius: 5,
                        fill           : true,
                        tension        : 0.35,
                        order          : 2,
                    },
                    {
                        label          : '7-day avg',
                        data           : ma7,
                        borderColor    : '#f59e0b',
                        backgroundColor: 'transparent',
                        borderWidth    : 2,
                        borderDash     : [6, 4],
                        pointRadius    : 0,
                        tension        : 0.35,
                        fill           : false,
                        order          : 1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 400 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        labels: { color: textColor, font: { size: 12 }, boxWidth: 16 }
                    },
                    tooltip: {
                        callbacks: {
                            title: function(ctx) {
                                return ctx[0] ? displayDate(ctx[0].label) : '';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid : { display: false },
                        ticks: {
                            color   : mutedClr,
                            font    : { size: 11 },
                            maxTicksLimit: 12,
                            callback: function(val, idx) {
                                var lbl = labels[idx];
                                if (!lbl) return '';
                                var d = new Date(lbl + 'T00:00:00');
                                return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                            }
                        }
                    },
                    y: {
                        grid    : { color: gridColor },
                        ticks   : { color: mutedClr, font: { size: 11 } },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    /* ── Top visitors table ──────────────────────────────────── */
    function renderTopVisitors(visitors) {
        var tbody = document.getElementById('top-visitors-body');
        if (!tbody) return;
        if (!visitors || !visitors.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">No data for this period.</td></tr>';
            return;
        }
        tbody.innerHTML = visitors.map(function(v, i) {
            var rankCls = i === 0 ? 'rank-1' : (i === 1 ? 'rank-2' : (i === 2 ? 'rank-3' : ''));
            var last    = v.last_visit ? displayDate(v.last_visit.substring(0, 10)) : '—';
            var dur     = v.total_min ? fmtDuration(v.total_min) : '—';
            return '<tr>'
                + '<td><span class="rank ' + rankCls + '">' + (i + 1) + '</span></td>'
                + '<td><a href="' + escHtml((window.BASE_URL||'') + 'pages/visitor_profile.php?id=' + v.visitor_id) + '" class="an-drill-link">' + escHtml(v.full_name) + '</a></td>'
                + '<td>' + v.visits + '</td>'
                + '<td>' + last + '</td>'
                + '<td>' + dur + '</td>'
                + '</tr>';
        }).join('');
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /* ── Drilldown drawer ─────────────────────────────────────── */
    function openDrilldown(deptId, deptName) {
        var drawerTitle = document.getElementById('drill-title');
        var drawerBody  = document.getElementById('drill-body');
        if (drawerTitle) drawerTitle.textContent = deptName || 'Department';
        if (drawerBody)  drawerBody.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:30px">Loading…</p>';
        showDrawer();

        loadDrill('drill_dept=' + encodeURIComponent(deptId))
            .then(function(d) {
                if (!d || !d.ok || !d.drilldown) {
                    if (drawerBody) drawerBody.innerHTML = '<p style="color:var(--danger);text-align:center;padding:30px">Failed to load.</p>';
                    return;
                }
                renderDrillRows(d.drilldown.visits, drawerBody);
            })
            .catch(function() {
                if (drawerBody) drawerBody.innerHTML = '<p style="color:var(--danger);text-align:center;padding:30px">Error loading drilldown.</p>';
            });
    }

    function openPurposeDrilldown(label) {
        var drawerTitle = document.getElementById('drill-title');
        var drawerBody  = document.getElementById('drill-body');
        if (drawerTitle) drawerTitle.textContent = label + ' Visits';
        if (drawerBody)  drawerBody.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:30px">Loading…</p>';
        showDrawer();

        loadDrill('drill_purpose=' + encodeURIComponent(label))
            .then(function(d) {
                if (!d || !d.ok || !d.drilldown_purpose) {
                    if (drawerBody) drawerBody.innerHTML = '<p style="color:var(--danger);text-align:center;padding:30px">Failed to load.</p>';
                    return;
                }
                renderDrillRows(d.drilldown_purpose.visits, drawerBody);
            })
            .catch(function() {
                if (drawerBody) drawerBody.innerHTML = '<p style="color:var(--danger);text-align:center;padding:30px">Error loading drilldown.</p>';
            });
    }

    function renderDrillRows(visits, container) {
        if (!visits || !visits.length) {
            container.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:30px">No visits found.</p>';
            return;
        }
        container.innerHTML = visits.map(function(v) {
            var ciu  = v.check_in_time  ? new Date(v.check_in_time.replace(' ', 'T'))  : null;
            var cout = v.check_out_time ? new Date(v.check_out_time.replace(' ', 'T')) : null;
            var ciStr  = ciu  ? ciu.toLocaleString(undefined, {dateStyle:'short',timeStyle:'short'}) : '—';
            var coStr  = cout ? cout.toLocaleString(undefined, {timeStyle:'short'}) : '—';
            var statusCls = v.status === 'checked_in'  ? 'success'
                          : v.status === 'checked_out' ? 'secondary'
                          : 'warning';
            return '<div class="an-drill-row">'
                 + '<div style="flex:1;min-width:0">'
                 + '<div><a href="' + escHtml((window.BASE_URL||'') + 'pages/visitor_profile.php?id=' + v.id) + '" class="an-drill-link">' + escHtml(v.full_name || '—') + '</a></div>'
                 + '<div style="font-size:11px;color:var(--text-muted)">' + escHtml(v.purpose || 'No purpose') + '</div>'
                 + '</div>'
                 + '<div style="text-align:right;flex-shrink:0;font-size:11px;color:var(--text-muted)">'
                 + ciStr + (cout ? '<br>out: ' + coStr : '')
                 + '</div>'
                 + '<span class="badge bg-' + statusCls + '" style="font-size:10px">' + escHtml(v.status || '') + '</span>'
                 + '</div>';
        }).join('');
    }

    function showDrawer() {
        var bd = document.getElementById('drill-backdrop');
        var dr = document.getElementById('drill-drawer');
        if (!bd || !dr) return;
        bd.style.display = 'block';
        dr.style.display = 'flex';
        setTimeout(function() { bd.classList.add('open'); dr.classList.add('open'); }, 10);
    }

    function closeDrawer() {
        var bd = document.getElementById('drill-backdrop');
        var dr = document.getElementById('drill-drawer');
        if (!bd || !dr) return;
        bd.classList.remove('open');
        dr.classList.remove('open');
        setTimeout(function() { bd.style.display = 'none'; dr.style.display = 'none'; }, 250);
    }

    /* ── Export ───────────────────────────────────────────────── */
    function exportChart(key, filename) {
        var c = charts[key];
        if (!c) return;
        var link = document.createElement('a');
        link.href     = c.toBase64Image('image/png', 1);
        link.download = (filename || key) + '.png';
        link.click();
    }

    function exportHeatmap() {
        var el = document.getElementById('an-heatmap');
        if (!el) return;
        if (window.html2canvas) {
            html2canvas(el, { backgroundColor: css('--card') || '#fff' }).then(function(canvas) {
                var link = document.createElement('a');
                link.href     = canvas.toDataURL('image/png');
                link.download = 'peak-hours-heatmap.png';
                link.click();
            });
        } else {
            alert('html2canvas not loaded.');
        }
    }

    /* ── Skeletons ───────────────────────────────────────────── */
    function showSkeletons() {
        document.querySelectorAll('.an-skeleton-target').forEach(function(el) {
            el.classList.add('an-skeleton');
        });
        var tb = document.getElementById('top-visitors-body');
        if (tb) {
            tb.innerHTML = Array(5).fill(
                '<tr>' + Array(5).fill('<td><div class="an-skeleton" style="height:14px;border-radius:4px"></div></td>').join('') + '</tr>'
            ).join('');
        }
    }

    function hideSkeletons() {
        document.querySelectorAll('.an-skeleton-target').forEach(function(el) {
            el.classList.remove('an-skeleton');
        });
    }

    /* ── Theme change ─────────────────────────────────────────── */
    document.addEventListener('themechange', function() {
        // Re-render all charts with new colour tokens
        if (data.departments) renderDeptChart(data.departments);
        if (data.purposes)    renderPurposeChart(data.purposes);
        if (data.trend)       renderTrendChart(data.trend);
        if (data.heatmap)     renderHeatmap(data.heatmap);
    });

    /* ── Init ─────────────────────────────────────────────────── */
    function init() {
        var cfg = window.AN_CONFIG || {};
        state.from        = cfg.from        || '';
        state.to          = cfg.to          || '';
        state.granularity = cfg.granularity || 'day';

        // Preset buttons
        document.querySelectorAll('.an-preset-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                applyPreset(btn.dataset.preset);
            });
        });

        // Granularity buttons
        document.querySelectorAll('.an-gran-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                state.granularity = btn.dataset.gran;
                document.querySelectorAll('.an-gran-btn').forEach(function(b) {
                    b.classList.toggle('active', b.dataset.gran === state.granularity);
                });
                load();
            });
        });

        // Custom picker apply
        var customApply = document.getElementById('an-custom-apply');
        if (customApply) {
            customApply.addEventListener('click', function() {
                var fi = document.getElementById('an-from-input');
                var ti = document.getElementById('an-to-input');
                if (!fi || !ti || !fi.value || !ti.value) return;
                state.from = fi.value;
                state.to   = ti.value;
                if (state.from > state.to) { var tmp = state.from; state.from = state.to; state.to = tmp; }
                var display = document.getElementById('an-date-display');
                if (display) display.textContent = displayDate(state.from) + ' – ' + displayDate(state.to);
                var wrap = document.getElementById('an-custom-wrap');
                if (wrap) wrap.classList.remove('visible');
                autoGranularity();
                load();
            });
        }

        // Refresh button
        var refreshBtn = document.getElementById('an-refresh-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                var icon = refreshBtn.querySelector('i');
                if (icon) icon.style.transition = 'transform 0.5s';
                if (icon) icon.style.transform  = 'rotate(360deg)';
                setTimeout(function() { if (icon) { icon.style.transition = ''; icon.style.transform = ''; } }, 500);
                load(true);
            });
        }

        // Drawer close
        var closeBtn = document.getElementById('drill-close');
        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
        var bdEl = document.getElementById('drill-backdrop');
        if (bdEl) bdEl.addEventListener('click', closeDrawer);

        // Export buttons
        document.querySelectorAll('[data-export]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var key = btn.dataset.export;
                if (key === 'heatmap') { exportHeatmap(); }
                else { exportChart(key, key + '-chart'); }
            });
        });

        // Mark preset button
        document.querySelectorAll('.an-preset-btn').forEach(function(btn) {
            if (btn.dataset.preset === cfg.preset) btn.classList.add('active');
        });
        var display = document.getElementById('an-date-display');
        if (display && state.from && state.to) {
            display.textContent = displayDate(state.from) + ' – ' + displayDate(state.to);
        }

        // Set granularity active
        document.querySelectorAll('.an-gran-btn').forEach(function(btn) {
            btn.classList.toggle('active', btn.dataset.gran === state.granularity);
        });

        // Initial load
        load();
    }

    // Wait for DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* ── Public API ──────────────────────────────────────────── */
    window.AN = {
        load       : load,
        exportChart: exportChart,
    };

}());
