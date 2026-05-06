/* ============================================================
   calendar.js — SVMS Appointments Calendar v1.0
   Implements Day / Week / Month views with drag-to-reschedule,
   side drawer, conflict detection, and "now" line.
   ============================================================ */
(function () {
  'use strict';

  // ── Constants ───────────────────────────────────────────────────────────────
  var PX_PER_HOUR = 60;   // 60px = 1 hour
  var PX_PER_MIN  = PX_PER_HOUR / 60;
  var SNAP_MIN    = 15;   // 15-minute drag snap

  // Status display config
  var STATUS_CFG = {
    scheduled: { label: 'Scheduled', cls: 'badge-info'    },
    confirmed:  { label: 'Confirmed', cls: 'badge-primary' },
    arrived:    { label: 'Arrived',   cls: 'badge-warning' },
    completed:  { label: 'Completed', cls: 'badge-success' },
    cancelled:  { label: 'Cancelled', cls: 'badge-secondary'},
    no_show:    { label: 'No-show',   cls: 'badge-danger'  },
  };

  // ── State ────────────────────────────────────────────────────────────────────
  var Cal = {
    view:        'week',
    currentDate: new Date(),
    appointments: [],
    departments:  {},
    filters:      { dept_id: 0, status: '', q: '' },
    loading:      false,
    isSuperAdmin: false,
    dragging:     null,   // drag state object
    nowTimer:     null,
    initialized:  false,
  };

  // ── Public init ──────────────────────────────────────────────────────────────
  Cal.init = function (opts) {
    opts = opts || {};
    Cal.isSuperAdmin = !!opts.isSuperAdmin;
    if (opts.departments) {
      opts.departments.forEach(function (d) {
        Cal.departments[d.id] = d;
      });
    }
    Cal.view = opts.defaultView || 'week';

    // Parse ?date= from URL
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('date')) {
      Cal.currentDate = new Date(urlParams.get('date') + 'T00:00:00');
      if (isNaN(Cal.currentDate)) Cal.currentDate = new Date();
    }

    Cal._bindToolbar();
    Cal._bindFilters();
    Cal._bindDrawer();
    Cal._bindModal();
    Cal.fetch();
    Cal.initialized = true;
  };

  // ── Date helpers ─────────────────────────────────────────────────────────────
  Cal._weekStart = function (d) {
    var dt = new Date(d);
    var day = dt.getDay(); // 0=Sun
    // Monday-start
    var diff = (day === 0) ? -6 : 1 - day;
    dt.setDate(dt.getDate() + diff);
    dt.setHours(0, 0, 0, 0);
    return dt;
  };

  Cal._addDays = function (d, n) {
    var dt = new Date(d);
    dt.setDate(dt.getDate() + n);
    return dt;
  };

  Cal._fmt = function (d, fmt) {
    var pad = function (n) { return String(n).padStart(2, '0'); };
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var days   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    return fmt
      .replace('YYYY', d.getFullYear())
      .replace('MM',   pad(d.getMonth() + 1))
      .replace('DD',   pad(d.getDate()))
      .replace('HH',   pad(d.getHours()))
      .replace('mm',   pad(d.getMinutes()))
      .replace('Mon',  days[d.getDay()])
      .replace('D',    d.getDate())
      .replace('Mth',  months[d.getMonth()]);
  };

  Cal._isoDate = function (d) {
    var pad = function (n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());
  };

  Cal._isoDateTime = function (d) {
    var pad = function (n) { return String(n).padStart(2, '0'); };
    return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate())
      + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':00';
  };

  Cal._parseDate = function (str) {
    // Handles 'YYYY-MM-DD HH:mm:ss' and 'YYYY-MM-DDTHH:mm:ss'
    return new Date(str.replace(' ', 'T'));
  };

  Cal._minutesFromMidnight = function (d) {
    return d.getHours() * 60 + d.getMinutes();
  };

  Cal._dateRangeLabel = function () {
    if (Cal.view === 'day') {
      var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
      var days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
      return days[Cal.currentDate.getDay()] + ', ' + Cal.currentDate.getDate() + ' ' + months[Cal.currentDate.getMonth()] + ' ' + Cal.currentDate.getFullYear();
    }
    if (Cal.view === 'week') {
      var ws = Cal._weekStart(Cal.currentDate);
      var we = Cal._addDays(ws, 6);
      if (ws.getMonth() === we.getMonth()) {
        return Cal._fmt(ws, 'D') + '–' + Cal._fmt(we, 'D') + ' ' + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][ws.getMonth()] + ' ' + ws.getFullYear();
      }
      return Cal._fmt(ws, 'D Mon') + ' – ' + Cal._fmt(we, 'D Mon') + ' ' + we.getFullYear();
    }
    // month
    var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    return months[Cal.currentDate.getMonth()] + ' ' + Cal.currentDate.getFullYear();
  };

  // ── Toolbar binding ──────────────────────────────────────────────────────────
  Cal._bindToolbar = function () {
    // View toggle
    document.querySelectorAll('[data-cal-view]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        Cal.view = this.dataset.calView;
        document.querySelectorAll('[data-cal-view]').forEach(function (b) {
          b.classList.toggle('active', b.dataset.calView === Cal.view);
        });
        Cal.render();
      });
    });

    // Navigation
    var prevBtn  = document.getElementById('cal-prev');
    var nextBtn  = document.getElementById('cal-next');
    var todayBtn = document.getElementById('cal-today');

    if (prevBtn) prevBtn.addEventListener('click', function () { Cal.navigate(-1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { Cal.navigate(1); });
    if (todayBtn) todayBtn.addEventListener('click', function () {
      Cal.currentDate = new Date();
      Cal.fetch();
    });

    // New appointment button
    var newBtn = document.getElementById('cal-new-appt');
    if (newBtn) newBtn.addEventListener('click', function () { Cal.openCreateModal({}); });
  };

  Cal.navigate = function (dir) {
    var d = Cal.currentDate;
    if (Cal.view === 'day')   d.setDate(d.getDate() + dir);
    if (Cal.view === 'week')  d.setDate(d.getDate() + dir * 7);
    if (Cal.view === 'month') d.setMonth(d.getMonth() + dir);
    Cal.fetch();
  };

  // ── Filter binding ───────────────────────────────────────────────────────────
  Cal._bindFilters = function () {
    var deptSel   = document.getElementById('cal-filter-dept');
    var statusSel = document.getElementById('cal-filter-status');
    var searchIn  = document.getElementById('cal-filter-search');

    if (deptSel)   deptSel.addEventListener('change',   function () { Cal.filters.dept_id = this.value; Cal.fetch(); });
    if (statusSel) statusSel.addEventListener('change', function () { Cal.filters.status  = this.value; Cal.fetch(); });
    if (searchIn)  searchIn.addEventListener('input',   SVMS.debounce(function () { Cal.filters.q = this.value; Cal.fetch(); }, 400));
  };

  // ── Data fetching ────────────────────────────────────────────────────────────
  Cal.fetch = function () {
    Cal.loading = true;
    Cal._showSkeleton();
    Cal._updateDateLabel();

    var start, end;
    if (Cal.view === 'day') {
      start = end = Cal._isoDate(Cal.currentDate);
    } else if (Cal.view === 'week') {
      var ws = Cal._weekStart(Cal.currentDate);
      start  = Cal._isoDate(ws);
      end    = Cal._isoDate(Cal._addDays(ws, 6));
    } else {
      // Month: fetch the whole month + padding
      var y = Cal.currentDate.getFullYear(), m = Cal.currentDate.getMonth();
      start = Cal._isoDate(new Date(y, m, 1));
      end   = Cal._isoDate(new Date(y, m + 1, 0));
    }

    var params = new URLSearchParams({
      action: 'calendar',
      start:  start,
      end:    end,
    });
    if (Cal.filters.dept_id) params.set('dept_id', Cal.filters.dept_id);
    if (Cal.filters.status)  params.set('status',  Cal.filters.status);
    if (Cal.filters.q)       params.set('q',        Cal.filters.q);

    fetch(window.BASE_URL + 'api/appointment.php?' + params.toString(), {
      headers: { 'X-CSRF-Token': Cal._csrf() }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      Cal.loading = false;
      if (data.ok) {
        Cal.appointments = data.appointments || [];
        Cal.render();
      } else {
        Cal._hideSkeleton();
        SVMS.toast('Failed to load appointments: ' + (data.error || 'Unknown error'), 'error');
      }
    })
    .catch(function (e) {
      Cal.loading = false;
      Cal._hideSkeleton();
      SVMS.toast('Network error: ' + e.message, 'error');
    });
  };

  Cal._csrf = function () {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  };

  Cal._updateDateLabel = function () {
    var el = document.getElementById('cal-date-label');
    if (el) el.textContent = Cal._dateRangeLabel();
  };

  // ── Skeleton loading ─────────────────────────────────────────────────────────
  Cal._showSkeleton = function () {
    var grid = document.getElementById('cal-grid');
    if (!grid) return;
    grid.innerHTML = '<div class="cal-skeleton">' +
      '<div class="shimmer" style="height:40px;margin-bottom:8px;border-radius:6px;"></div>' +
      '<div class="shimmer" style="height:400px;border-radius:6px;"></div>' +
      '</div>';
  };

  Cal._hideSkeleton = function () {
    var sk = document.querySelector('.cal-skeleton');
    if (sk && sk.parentNode) sk.parentNode.removeChild(sk);
  };

  // ── Main render dispatcher ───────────────────────────────────────────────────
  Cal.render = function () {
    Cal._hideSkeleton();
    if (Cal.view === 'week')  Cal._renderWeek();
    else if (Cal.view === 'day')   Cal._renderDay();
    else                           Cal._renderMonth();
    Cal._startNowLine();
  };

  // ── Week view ────────────────────────────────────────────────────────────────
  Cal._renderWeek = function () {
    var ws   = Cal._weekStart(Cal.currentDate);
    var days = [];
    for (var i = 0; i < 7; i++) days.push(Cal._addDays(ws, i));

    var today    = Cal._isoDate(new Date());
    var dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    var months   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    // Build header row
    var headerHTML = '<div class="cal-time-gutter cal-header-cell"></div>';
    days.forEach(function (d, i) {
      var iso      = Cal._isoDate(d);
      var isToday  = iso === today;
      headerHTML  += '<div class="cal-day-header' + (isToday ? ' cal-today-header' : '') + '">'
        + '<span class="cal-day-name">' + dayNames[i] + '</span>'
        + '<span class="cal-day-num' + (isToday ? ' cal-today-num' : '') + '">' + d.getDate() + '</span>'
        + '</div>';
    });

    // Build hour labels + day columns
    var gutterHTML = '';
    var colsHTML   = '';
    for (var h = 0; h < 24; h++) {
      var label = h === 0 ? '' : (h < 12 ? h + ' AM' : (h === 12 ? '12 PM' : (h - 12) + ' PM'));
      gutterHTML += '<div class="cal-hour-label">' + label + '</div>';
    }

    // Build day columns with appointment cards
    days.forEach(function (d) {
      var iso     = Cal._isoDate(d);
      var isToday = iso === today;
      var colAppts = Cal.appointments.filter(function (a) {
        return Cal._isoDate(Cal._parseDate(a.scheduled_at)) === iso;
      });

      var cards = '';
      colAppts.forEach(function (appt) {
        cards += Cal._buildCard(appt, 'week');
      });

      // Now line
      var nowLine = '';
      if (isToday) {
        var now = new Date();
        var pct = (now.getHours() * 60 + now.getMinutes()) * PX_PER_MIN;
        nowLine = '<div class="cal-now-line" id="cal-now-line" style="top:' + pct + 'px;">'
          + '<div class="cal-now-dot"></div></div>';
      }

      colsHTML += '<div class="cal-day-col' + (isToday ? ' cal-today-col' : '') + '" data-date="' + iso + '">'
        + Array.from({length: 24}, function (_, hh) {
            var stripe = hh % 2 === 0 ? '' : ' cal-odd-row';
            return '<div class="cal-hour-row' + stripe + '"></div>';
          }).join('')
        + cards
        + nowLine
        + '</div>';
    });

    var html = '<div class="cal-week-wrapper">'
      + '<div class="cal-week-header">' + headerHTML + '</div>'
      + '<div class="cal-week-body">'
      +   '<div class="cal-time-gutter">' + gutterHTML + '</div>'
      +   colsHTML
      + '</div>'
      + '</div>';

    var grid = document.getElementById('cal-grid');
    if (grid) {
      grid.innerHTML = html;
      Cal._initDragDrop();
      Cal._scrollToCurrentHour();
    }
  };

  // ── Day view ─────────────────────────────────────────────────────────────────
  Cal._renderDay = function () {
    var iso      = Cal._isoDate(Cal.currentDate);
    var today    = Cal._isoDate(new Date());
    var isToday  = iso === today;
    var dayAppts = Cal.appointments.filter(function (a) {
      return Cal._isoDate(Cal._parseDate(a.scheduled_at)) === iso;
    });

    var gutterHTML = '';
    for (var h = 0; h < 24; h++) {
      var label = h === 0 ? '' : (h < 12 ? h + ' AM' : (h === 12 ? '12 PM' : (h - 12) + ' PM'));
      gutterHTML += '<div class="cal-hour-label">' + label + '</div>';
    }

    var rows = Array.from({length: 24}, function (_, hh) {
      var stripe = hh % 2 === 0 ? '' : ' cal-odd-row';
      return '<div class="cal-hour-row' + stripe + '"></div>';
    }).join('');

    var cards = dayAppts.map(function (a) { return Cal._buildCard(a, 'day'); }).join('');

    var nowLine = '';
    if (isToday) {
      var now = new Date();
      var pct = (now.getHours() * 60 + now.getMinutes()) * PX_PER_MIN;
      nowLine = '<div class="cal-now-line" id="cal-now-line" style="top:' + pct + 'px;">'
        + '<div class="cal-now-dot"></div></div>';
    }

    var html = '<div class="cal-week-wrapper">'
      + '<div class="cal-week-header">'
      +   '<div class="cal-time-gutter cal-header-cell"></div>'
      +   '<div class="cal-day-header' + (isToday ? ' cal-today-header' : '') + '">'
      +     '<span class="cal-day-full">' + Cal._dateRangeLabel() + '</span></div>'
      + '</div>'
      + '<div class="cal-week-body">'
      +   '<div class="cal-time-gutter">' + gutterHTML + '</div>'
      +   '<div class="cal-day-col cal-day-col--single' + (isToday ? ' cal-today-col' : '') + '" data-date="' + iso + '">'
      +     rows + cards + nowLine
      +   '</div>'
      + '</div>'
      + '</div>';

    var grid = document.getElementById('cal-grid');
    if (grid) {
      grid.innerHTML = html;
      Cal._initDragDrop();
      Cal._scrollToCurrentHour();
    }
  };

  // ── Month view ───────────────────────────────────────────────────────────────
  Cal._renderMonth = function () {
    var y     = Cal.currentDate.getFullYear();
    var m     = Cal.currentDate.getMonth();
    var today = Cal._isoDate(new Date());

    // First day of month, padded to Monday
    var firstDay = new Date(y, m, 1);
    var startDay = Cal._weekStart(firstDay);

    var dayHeaders = '<div class="cal-month-header">';
    ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].forEach(function (d) {
      dayHeaders += '<div class="cal-month-day-name">' + d + '</div>';
    });
    dayHeaders += '</div>';

    var cells = '';
    for (var row = 0; row < 6; row++) {
      cells += '<div class="cal-month-row">';
      for (var col = 0; col < 7; col++) {
        var d    = Cal._addDays(startDay, row * 7 + col);
        var iso  = Cal._isoDate(d);
        var inM  = d.getMonth() === m;
        var isT  = iso === today;
        var isSat = d.getDay() === 6;
        var isSun = d.getDay() === 0;

        var dayAppts = Cal.appointments.filter(function (a) {
          return Cal._isoDate(Cal._parseDate(a.scheduled_at)) === iso;
        });

        var cardList = '';
        var MAX_SHOW = 3;
        dayAppts.slice(0, MAX_SHOW).forEach(function (a) {
          var colour = a.dept_colour || '#2e75b6';
          var time   = Cal._parseDate(a.scheduled_at);
          var hh     = time.getHours(), mm = time.getMinutes();
          var timeStr = (hh % 12 || 12) + ':' + String(mm).padStart(2,'0') + (hh < 12 ? 'am' : 'pm');
          var sc      = STATUS_CFG[a.status] || STATUS_CFG.scheduled;
          cardList += '<div class="cal-month-card" data-id="' + a.id + '" style="border-left-color:' + colour + ';cursor:pointer;"'
            + ' title="' + _esc(a.visitor_name) + ' — ' + _esc(a.purpose) + '">'
            + '<span class="cal-mc-time">' + timeStr + '</span> '
            + '<span class="cal-mc-name">' + _esc(a.visitor_name) + '</span>'
            + '</div>';
        });
        if (dayAppts.length > MAX_SHOW) {
          cardList += '<div class="cal-month-more" data-date="' + iso + '">'
            + (dayAppts.length - MAX_SHOW) + ' more</div>';
        }

        var cls = 'cal-month-cell'
          + (inM    ? '' : ' cal-month-other')
          + (isT    ? ' cal-month-today' : '')
          + ((isSat || isSun) ? ' cal-month-weekend' : '');

        cells += '<div class="' + cls + '">'
          + '<div class="cal-month-cell-date' + (isT ? ' cal-today-num' : '') + '">' + d.getDate() + '</div>'
          + cardList
          + '</div>';
      }
      cells += '</div>';
    }

    var grid = document.getElementById('cal-grid');
    if (grid) {
      grid.innerHTML = '<div class="cal-month-wrapper">' + dayHeaders + cells + '</div>';

      // Event delegation for month cards and "more" links
      grid.addEventListener('click', function (e) {
        var card = e.target.closest('.cal-month-card');
        if (card) { Cal.openDrawer(parseInt(card.dataset.id, 10)); return; }
        var more = e.target.closest('.cal-month-more');
        if (more) {
          Cal.view = 'day';
          Cal.currentDate = new Date(more.dataset.date + 'T00:00:00');
          Cal.fetch();
        }
      });
    }
  };

  // ── Build appointment card (week/day views) ──────────────────────────────────
  Cal._buildCard = function (appt, viewMode) {
    var start   = Cal._parseDate(appt.scheduled_at);
    var dur     = parseInt(appt.duration_minutes, 10) || 30;
    var top     = Cal._minutesFromMidnight(start) * PX_PER_MIN;
    var height  = Math.max(20, dur * PX_PER_MIN);
    var colour  = appt.dept_colour || '#2e75b6';
    var sc      = STATUS_CFG[appt.status] || STATUS_CFG.scheduled;
    var hh      = start.getHours(), mm = start.getMinutes();
    var endMin  = Cal._minutesFromMidnight(start) + dur;
    var eh      = Math.floor(endMin / 60), em = endMin % 60;
    var timeStr = (hh % 12 || 12) + ':' + String(mm).padStart(2,'0') + (hh < 12 ? 'am' : 'pm')
      + '–' + (eh % 12 || 12) + ':' + String(em).padStart(2,'0') + (eh < 12 ? 'am' : 'pm');

    var deptBadge = appt.dept_name
      ? '<span class="cal-dept-badge" style="background:' + colour + '22;color:' + colour + ';border:1px solid ' + colour + '44">' + _esc(appt.dept_name) + '</span>'
      : '';

    return '<div class="cal-appt-card" data-id="' + appt.id + '"'
      + ' style="top:' + top + 'px;height:' + height + 'px;border-left-color:' + colour + ';"'
      + ' draggable="true"'
      + ' role="button" tabindex="0"'
      + ' aria-label="' + _esc(appt.visitor_name) + ' at ' + timeStr + '">'
      + '<div class="cal-card-time">' + timeStr + '</div>'
      + '<div class="cal-card-name">' + _esc(appt.visitor_name) + '</div>'
      + (height > 45 ? deptBadge : '')
      + '</div>';
  };

  // ── Scroll to current hour ───────────────────────────────────────────────────
  Cal._scrollToCurrentHour = function () {
    var body = document.querySelector('.cal-week-body');
    if (!body) return;
    var now = new Date();
    var offset = Math.max(0, (now.getHours() - 1) * PX_PER_HOUR);
    body.scrollTop = offset;
  };

  // ── Now line updater ─────────────────────────────────────────────────────────
  Cal._startNowLine = function () {
    if (Cal.nowTimer) clearInterval(Cal.nowTimer);
    Cal._updateNowLine();
    Cal.nowTimer = setInterval(Cal._updateNowLine, 60000);
  };

  Cal._updateNowLine = function () {
    var line = document.getElementById('cal-now-line');
    if (!line) return;
    var now = new Date();
    line.style.top = (Cal._minutesFromMidnight(now) * PX_PER_MIN) + 'px';
  };

  // ── Drag-and-drop ────────────────────────────────────────────────────────────
  Cal._initDragDrop = function () {
    var grid = document.getElementById('cal-grid');
    if (!grid) return;

    // Attach drag events via delegation
    grid.addEventListener('dragstart', Cal._onDragStart);
    grid.addEventListener('dragover',  Cal._onDragOver);
    grid.addEventListener('drop',      Cal._onDrop);
    grid.addEventListener('dragend',   Cal._onDragEnd);

    // Click to open drawer (delegation)
    grid.addEventListener('click', function (e) {
      var card = e.target.closest('.cal-appt-card');
      if (card && !Cal.dragging) Cal.openDrawer(parseInt(card.dataset.id, 10));
    });
    grid.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        var card = e.target.closest('.cal-appt-card');
        if (card) Cal.openDrawer(parseInt(card.dataset.id, 10));
      }
    });
  };

  Cal._onDragStart = function (e) {
    var card = e.target.closest('.cal-appt-card');
    if (!card) return;
    Cal.dragging = { id: parseInt(card.dataset.id, 10), card: card };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', card.dataset.id);
    card.style.opacity    = '0.5';
    card.style.transform  = 'rotate(-1deg)';
  };

  Cal._onDragOver = function (e) {
    var col = e.target.closest('.cal-day-col');
    if (!col) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
  };

  Cal._onDrop = function (e) {
    var col = e.target.closest('.cal-day-col');
    if (!col || !Cal.dragging) return;
    e.preventDefault();

    var rect    = col.getBoundingClientRect();
    var body    = col.closest('.cal-week-body');
    var scrollY = body ? body.scrollTop : 0;
    var relY    = (e.clientY - rect.top) + scrollY;
    var rawMin  = relY / PX_PER_MIN;
    var snapped = Math.round(rawMin / SNAP_MIN) * SNAP_MIN;
    snapped     = Math.max(0, Math.min(23 * 60 + 45, snapped));

    var h       = Math.floor(snapped / 60);
    var m       = snapped % 60;
    var dateStr = col.dataset.date;
    var newDT   = dateStr + ' ' + String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':00';

    var apptId = Cal.dragging.id;
    var oldTop = Cal.dragging.card.style.top;

    // Optimistic UI: move card
    Cal.dragging.card.style.top    = snapped * PX_PER_MIN + 'px';
    Cal.dragging.card.style.opacity = '1';
    Cal.dragging.card.style.transform = '';

    // Move card to new column if needed
    var currentCol = Cal.dragging.card.closest('.cal-day-col');
    if (currentCol && currentCol !== col) {
      col.appendChild(Cal.dragging.card);
    }

    // AJAX reschedule
    fetch(window.BASE_URL + 'api/appointment.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': Cal._csrf() },
      body:    JSON.stringify({ action: 'reschedule', id: apptId, scheduled_at: newDT, csrf_token: Cal._csrf() }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.ok) {
        SVMS.toast('Appointment rescheduled.', 'success');
        // Update local state
        Cal.appointments.forEach(function (a) {
          if (a.id === apptId) a.scheduled_at = newDT;
        });
      } else {
        // Rollback
        Cal.dragging.card.style.top = oldTop;
        if (currentCol) currentCol.appendChild(Cal.dragging.card);
        SVMS.toast('Reschedule failed: ' + (data.error || 'Unknown error'), 'error');
      }
    })
    .catch(function () {
      Cal.dragging.card.style.top = oldTop;
      if (currentCol) currentCol.appendChild(Cal.dragging.card);
      SVMS.toast('Network error while rescheduling.', 'error');
    })
    .finally(function () { Cal.dragging = null; });
  };

  Cal._onDragEnd = function (e) {
    if (Cal.dragging && Cal.dragging.card) {
      Cal.dragging.card.style.opacity   = '1';
      Cal.dragging.card.style.transform = '';
    }
  };

  // ── Side drawer ──────────────────────────────────────────────────────────────
  Cal._bindDrawer = function () {
    var backdrop = document.getElementById('appt-drawer-backdrop');
    var closeBtn = document.getElementById('drawer-close');

    if (backdrop) backdrop.addEventListener('click', Cal.closeDrawer);
    if (closeBtn) closeBtn.addEventListener('click', Cal.closeDrawer);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') Cal.closeDrawer();
    });
  };

  Cal.openDrawer = function (idOrAppt) {
    var appt = (typeof idOrAppt === 'object') ? idOrAppt
      : Cal.appointments.find(function (a) { return a.id === idOrAppt; });
    if (!appt) {
      // Fetch from server
      fetch(window.BASE_URL + 'api/appointment.php?action=get&id=' + idOrAppt, {
        headers: { 'X-CSRF-Token': Cal._csrf() }
      })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok) Cal._showDrawer(d.appointment); });
      return;
    }
    Cal._showDrawer(appt);
  };

  Cal._showDrawer = function (appt) {
    var body   = document.getElementById('drawer-body');
    var footer = document.getElementById('drawer-footer');
    var title  = document.getElementById('drawer-title');
    if (!body) return;

    var sc      = STATUS_CFG[appt.status] || STATUS_CFG.scheduled;
    var start   = Cal._parseDate(appt.scheduled_at);
    var dur     = parseInt(appt.duration_minutes, 10) || 30;
    var endMin  = Cal._minutesFromMidnight(start) + dur;
    var eh      = Math.floor(endMin / 60), em = endMin % 60;
    var months  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var dateStr = Cal._fmt(start, 'D') + ' ' + months[start.getMonth()] + ' ' + start.getFullYear();
    var timeStr = (start.getHours() % 12 || 12) + ':' + String(start.getMinutes()).padStart(2,'0')
      + (start.getHours() < 12 ? ' AM' : ' PM')
      + ' – ' + (eh % 12 || 12) + ':' + String(em).padStart(2,'0') + (eh < 12 ? ' AM' : ' PM');
    var colour  = appt.dept_colour || '#2e75b6';

    if (title) title.textContent = appt.visitor_name;

    body.innerHTML =
      '<div class="drawer-section">'
      + '<div class="drawer-status-row">'
      + '<span class="badge ' + sc.cls + '">' + sc.label + '</span>'
      + (appt.dept_name ? '<span class="cal-dept-badge" style="background:' + colour + '22;color:' + colour + ';border:1px solid ' + colour + '44">' + _esc(appt.dept_name) + '</span>' : '')
      + '</div>'
      + '</div>'
      + '<div class="drawer-section">'
      + '<div class="drawer-field"><i class="bi bi-calendar3"></i> <strong>' + dateStr + '</strong></div>'
      + '<div class="drawer-field"><i class="bi bi-clock"></i> ' + timeStr + ' (' + dur + ' min)</div>'
      + '</div>'
      + '<div class="drawer-section">'
      + '<div class="drawer-field"><i class="bi bi-person-fill"></i> <strong>Visitor:</strong> ' + _esc(appt.visitor_name) + '</div>'
      + (appt.phone ? '<div class="drawer-field"><i class="bi bi-telephone-fill"></i> ' + _esc(appt.phone) + '</div>' : '')
      + (appt.email ? '<div class="drawer-field"><i class="bi bi-envelope-fill"></i> ' + _esc(appt.email) + '</div>' : '')
      + '</div>'
      + '<div class="drawer-section">'
      + '<div class="drawer-field"><i class="bi bi-person-badge-fill"></i> <strong>Host:</strong> ' + _esc(appt.person_to_meet || appt.host_name) + '</div>'
      + (appt.dept_name ? '<div class="drawer-field"><i class="bi bi-building"></i> ' + _esc(appt.dept_name) + '</div>' : '')
      + '</div>'
      + '<div class="drawer-section">'
      + '<div class="drawer-field"><i class="bi bi-chat-left-text-fill"></i> <strong>Purpose:</strong> ' + _esc(appt.purpose) + '</div>'
      + (appt.notes ? '<div class="drawer-field drawer-notes"><i class="bi bi-sticky-fill"></i> ' + _esc(appt.notes) + '</div>' : '')
      + '</div>';

    // Action buttons
    var canArrive   = ['scheduled','confirmed'].includes(appt.status);
    var canCancel   = ['scheduled','confirmed'].includes(appt.status);
    var canResend   = !!appt.email;

    footer.innerHTML =
      '<div class="drawer-actions">'
      + '<button class="btn btn-sm btn-secondary" onclick="SVMS_Calendar.openEditModal(' + appt.id + ')">'
      +   '<i class="bi bi-pencil-fill"></i> Edit</button> '
      + (canArrive
          ? '<button class="btn btn-sm btn-success" onclick="SVMS_Calendar.doArrive(' + appt.id + ')">'
          +   '<i class="bi bi-box-arrow-in-right"></i> Mark Arrived → Check In</button> '
          : '')
      + (canResend && appt.email
          ? '<button class="btn btn-sm btn-info" onclick="SVMS_Calendar.doResendEPass(' + appt.id + ')">'
          +   '<i class="bi bi-envelope-fill"></i> Resend e-Pass</button> '
          : '')
      + (canCancel
          ? '<button class="btn btn-sm btn-warning" onclick="SVMS_Calendar.doCancel(' + appt.id + ')">'
          +   '<i class="bi bi-x-circle-fill"></i> Cancel</button> '
          : '')
      + (Cal.isSuperAdmin
          ? '<button class="btn btn-sm btn-danger" onclick="SVMS_Calendar.doDelete(' + appt.id + ')">'
          +   '<i class="bi bi-trash-fill"></i> Delete</button>'
          : '')
      + '</div>';

    // Open drawer
    var backdrop = document.getElementById('appt-drawer-backdrop');
    var drawer   = document.getElementById('appt-drawer');
    if (backdrop) { backdrop.style.display = 'block'; requestAnimationFrame(function () { backdrop.classList.add('open'); }); }
    if (drawer)   { drawer.style.display   = 'flex';  requestAnimationFrame(function () { drawer.classList.add('open');   }); }

    // Store current appt for edit
    Cal._drawerAppt = appt;
  };

  Cal.closeDrawer = function () {
    var backdrop = document.getElementById('appt-drawer-backdrop');
    var drawer   = document.getElementById('appt-drawer');
    if (backdrop) { backdrop.classList.remove('open'); setTimeout(function () { backdrop.style.display = 'none'; }, 250); }
    if (drawer)   { drawer.classList.remove('open');   setTimeout(function () { drawer.style.display   = 'none'; }, 250); }
    Cal._drawerAppt = null;
  };

  // ── Drawer actions ───────────────────────────────────────────────────────────
  Cal.doArrive = function (id) {
    if (!confirm('Mark this appointment as Arrived and create a visit check-in?')) return;
    Cal._postAction('arrive', id, function (data) {
      if (data.ok) {
        SVMS.toast('Visitor checked in! Badge: ' + data.badge_number, 'success');
        Cal.closeDrawer();
        Cal.fetch();
      }
    });
  };

  Cal.doCancel = function (id) {
    if (!confirm('Cancel this appointment?')) return;
    Cal._postJSON({ action: 'cancel', id: id, csrf_token: Cal._csrf() }, 'api/appointment.php', function (data) {
      if (data.ok) {
        SVMS.toast('Appointment cancelled.', 'info');
        Cal.closeDrawer();
        Cal.fetch();
      }
    });
  };

  Cal.doDelete = function (id) {
    if (!confirm('Permanently delete this appointment? This cannot be undone.')) return;
    Cal._postJSON({ action: 'delete', id: id, csrf_token: Cal._csrf() }, 'api/appointment.php', function (data) {
      if (data.ok) {
        SVMS.toast('Appointment deleted.', 'success');
        Cal.closeDrawer();
        Cal.fetch();
      }
    });
  };

  Cal.doResendEPass = function (id) {
    Cal._postJSON({ action: 'resend_epass', id: id, csrf_token: Cal._csrf() }, 'api/appointment.php', function (data) {
      SVMS.toast(data.ok ? 'e-Pass resent!' : ('Failed: ' + (data.error || 'Unknown')), data.ok ? 'success' : 'error');
    });
  };

  Cal._postAction = function (action, id, cb) {
    if (action === 'arrive') {
      fetch(window.BASE_URL + 'api/appointment_arrive.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': Cal._csrf() },
        body:    JSON.stringify({ appointment_id: id, csrf_token: Cal._csrf() }),
      })
      .then(function (r) { return r.json(); })
      .then(cb)
      .catch(function (e) { SVMS.toast('Network error: ' + e.message, 'error'); });
    }
  };

  Cal._postJSON = function (payload, endpoint, cb) {
    fetch(window.BASE_URL + endpoint, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': Cal._csrf() },
      body:    JSON.stringify(payload),
    })
    .then(function (r) { return r.json(); })
    .then(cb)
    .catch(function (e) { SVMS.toast('Network error: ' + e.message, 'error'); });
  };

  // ── Create / Edit modal ──────────────────────────────────────────────────────
  Cal._bindModal = function () {
    var modal    = document.getElementById('appt-modal');
    var backdrop = document.getElementById('appt-modal-backdrop');
    var closeBtn = document.getElementById('appt-modal-close');
    var form     = document.getElementById('appt-form');

    if (closeBtn)  closeBtn.addEventListener('click',  Cal.closeModal);
    if (backdrop)  backdrop.addEventListener('click',  Cal.closeModal);

    // Conflict check on host / datetime change
    ['appt-host','appt-datetime','appt-duration'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', Cal._checkConflict);
    });

    // Visitor smart-search
    var vsInput  = document.getElementById('appt-visitor-search');
    var vsResult = document.getElementById('appt-visitor-results');
    if (vsInput) {
      vsInput.addEventListener('input', SVMS.debounce(function () {
        var q = vsInput.value.trim();
        if (q.length < 2) { if (vsResult) vsResult.innerHTML = ''; return; }
        fetch(window.BASE_URL + 'api/smart_search.php?q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!vsResult) return;
            var rows = data.results || data;
            vsResult.innerHTML = rows.slice(0, 6).map(function (v) {
              return '<div class="visitor-search-item" data-id="' + v.id + '" data-name="' + _esc(v.full_name) + '" data-phone="' + _esc(v.phone) + '" data-email="' + _esc(v.email || '') + '">'
                + '<strong>' + _esc(v.full_name) + '</strong> <small>' + _esc(v.phone) + '</small>'
                + '</div>';
            }).join('');
          });
      }, 300));

      if (vsResult) {
        vsResult.addEventListener('click', function (e) {
          var item = e.target.closest('.visitor-search-item');
          if (!item) return;
          document.getElementById('appt-visitor-id').value    = item.dataset.id;
          document.getElementById('appt-visitor-name').value  = item.dataset.name;
          document.getElementById('appt-phone').value         = item.dataset.phone;
          document.getElementById('appt-email').value         = item.dataset.email;
          vsInput.value    = item.dataset.name;
          vsResult.innerHTML = '';
        });
      }
    }

    // Form submit
    if (form) form.addEventListener('submit', Cal._handleFormSubmit);
  };

  Cal.openCreateModal = function (prefill) {
    prefill = prefill || {};
    Cal._resetForm();
    document.getElementById('appt-modal-title').textContent = 'New Appointment';
    document.getElementById('appt-form-action').value = 'create';
    document.getElementById('appt-id').value = '';

    if (prefill.date) {
      var dt = document.getElementById('appt-datetime');
      if (dt) dt.value = prefill.date + 'T09:00';
    }
    Cal._openModal();
  };

  Cal.openEditModal = function (id) {
    var appt = (typeof id === 'object') ? id : Cal.appointments.find(function (a) { return a.id === id; });
    if (!appt) {
      fetch(window.BASE_URL + 'api/appointment.php?action=get&id=' + id, { headers: { 'X-CSRF-Token': Cal._csrf() } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) Cal._fillEditForm(d.appointment); });
      return;
    }
    Cal._fillEditForm(appt);
  };

  Cal._fillEditForm = function (appt) {
    Cal._resetForm();
    document.getElementById('appt-modal-title').textContent = 'Edit Appointment';
    document.getElementById('appt-form-action').value = 'update';
    document.getElementById('appt-id').value = appt.id;

    var vsIn = document.getElementById('appt-visitor-search');
    if (vsIn) vsIn.value = appt.visitor_name;
    document.getElementById('appt-visitor-id').value   = appt.visitor_id || '';
    document.getElementById('appt-visitor-name').value = appt.visitor_name;
    document.getElementById('appt-phone').value        = appt.phone;
    document.getElementById('appt-email').value        = appt.email || '';
    document.getElementById('appt-host').value         = appt.person_to_meet || appt.host_name;
    document.getElementById('appt-purpose').value      = appt.purpose;
    document.getElementById('appt-notes').value        = appt.notes || '';

    var dept = document.getElementById('appt-dept');
    if (dept) dept.value = appt.department_id || '';

    var dur = document.getElementById('appt-duration');
    if (dur) dur.value = appt.duration_minutes || 30;

    // datetime-local format: YYYY-MM-DDTHH:mm
    var dt = Cal._parseDate(appt.scheduled_at);
    var pad = function (n) { return String(n).padStart(2,'0'); };
    var dtLocal = dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate())
      + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
    document.getElementById('appt-datetime').value = dtLocal;

    Cal._openModal();
  };

  Cal._openModal = function () {
    var backdrop = document.getElementById('appt-modal-backdrop');
    if (backdrop) backdrop.classList.add('open');
    var modal = document.getElementById('appt-modal');
    if (modal) {
      modal.style.display = 'block';
      var firstInput = modal.querySelector('input:not([type=hidden]),select,textarea');
      if (firstInput) firstInput.focus();
    }
  };

  Cal.closeModal = function () {
    var backdrop = document.getElementById('appt-modal-backdrop');
    if (backdrop) backdrop.classList.remove('open');
    var modal = document.getElementById('appt-modal');
    if (modal) modal.style.display = 'none';
  };

  Cal._resetForm = function () {
    var form = document.getElementById('appt-form');
    if (form) form.reset();
    var vsIn = document.getElementById('appt-visitor-search');
    if (vsIn) vsIn.value = '';
    var conflict = document.getElementById('appt-conflict-warning');
    if (conflict) { conflict.style.display = 'none'; conflict.innerHTML = ''; }
    var vsRes = document.getElementById('appt-visitor-results');
    if (vsRes) vsRes.innerHTML = '';
  };

  Cal._checkConflict = function () {
    var host  = (document.getElementById('appt-host')     || {}).value || '';
    var dt    = (document.getElementById('appt-datetime') || {}).value || '';
    var dur   = (document.getElementById('appt-duration') || {}).value || 30;
    var excId = (document.getElementById('appt-id')       || {}).value || 0;
    var warn  = document.getElementById('appt-conflict-warning');
    if (!warn || !host || !dt) return;

    var iso = dt.replace('T', ' ') + ':00';
    fetch(window.BASE_URL + 'api/appointment.php?action=conflict&host=' + encodeURIComponent(host)
      + '&scheduled_at=' + encodeURIComponent(iso)
      + '&duration_minutes=' + dur
      + '&exclude_id=' + excId,
      { headers: { 'X-CSRF-Token': Cal._csrf() } }
    )
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d.conflicts && d.conflicts.length > 0) {
        var c = d.conflicts[0];
        var cStart = Cal._parseDate(c.scheduled_at);
        var cEnd   = new Date(cStart.getTime() + c.duration_minutes * 60000);
        warn.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Host <strong>' + _esc(host) + '</strong> has another appointment from '
          + Cal._fmt(cStart,'HH') + ':' + String(cStart.getMinutes()).padStart(2,'0')
          + ' to ' + Cal._fmt(cEnd,'HH') + ':' + String(cEnd.getMinutes()).padStart(2,'0')
          + '. Continue anyway?';
        warn.style.display = 'block';
      } else {
        warn.style.display = 'none';
        warn.innerHTML = '';
      }
    })
    .catch(function () {});
  };

  Cal._handleFormSubmit = function (e) {
    e.preventDefault();
    var action = (document.getElementById('appt-form-action') || {}).value || 'create';
    var sendEmailCb = document.getElementById('appt-send-email');

    var payload = {
      action:          action,
      csrf_token:      Cal._csrf(),
      id:              parseInt((document.getElementById('appt-id') || {}).value || '0', 10) || undefined,
      visitor_id:      parseInt((document.getElementById('appt-visitor-id') || {}).value || '0', 10) || null,
      visitor_name:    (document.getElementById('appt-visitor-name') || {}).value || (document.getElementById('appt-visitor-search') || {}).value || '',
      phone:           (document.getElementById('appt-phone')    || {}).value || '',
      email:           (document.getElementById('appt-email')    || {}).value || '',
      department_id:   parseInt((document.getElementById('appt-dept') || {}).value || '0', 10) || null,
      person_to_meet:  (document.getElementById('appt-host')     || {}).value || '',
      purpose:         (document.getElementById('appt-purpose')  || {}).value || '',
      notes:           (document.getElementById('appt-notes')    || {}).value || '',
      duration_minutes:parseInt((document.getElementById('appt-duration') || {}).value || '30', 10),
      send_email:      sendEmailCb ? sendEmailCb.checked : true,
    };

    // Convert datetime-local to MySQL datetime
    var dtVal = (document.getElementById('appt-datetime') || {}).value || '';
    payload.scheduled_at = dtVal ? dtVal.replace('T', ' ') + ':00' : '';

    if (!payload.visitor_name || !payload.person_to_meet || !payload.scheduled_at) {
      SVMS.toast('Please fill in all required fields.', 'error');
      return;
    }

    var btn = document.getElementById('appt-form-save');
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

    Cal._postJSON(payload, 'api/appointment.php', function (data) {
      if (btn) { btn.disabled = false; btn.textContent = 'Save Appointment'; }
      if (data.ok) {
        SVMS.toast(action === 'create' ? 'Appointment created!' : 'Appointment updated!', 'success');
        Cal.closeModal();
        Cal.fetch();
      } else {
        SVMS.toast('Error: ' + (data.error || 'Unknown'), 'error');
      }
    });
  };

  // ── Helpers ──────────────────────────────────────────────────────────────────
  function _esc(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Expose ───────────────────────────────────────────────────────────────────
  window.SVMS_Calendar = Cal;

})();
