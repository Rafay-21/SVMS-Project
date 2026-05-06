<?php
/**
 * pages/notifications.php — Full Notifications Centre (Phase 4.4)
 * Filter pills, day grouping, bulk actions, mark-all-read, pagination.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

$page_title = 'Notifications';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<style>
/* ── Filter pills ─────────────────────────────────────────────── */
.np-pill { display:inline-flex;align-items:center;gap:6px;padding:5px 14px;
           border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;
           border:1.5px solid var(--border);color:var(--text-muted);background:var(--card);
           transition:all .12s;user-select:none; }
.np-pill.active { background:var(--secondary);color:#fff;border-color:var(--secondary); }
.np-pill:hover:not(.active) { border-color:var(--secondary);color:var(--secondary); }
/* ── Notification rows ────────────────────────────────────────── */
.np-item { display:flex;align-items:flex-start;gap:12px;padding:12px 16px;
           min-height:56px;cursor:pointer;transition:background .12s;
           border-bottom:1px solid var(--border);border-left:4px solid transparent;
           text-decoration:none;color:inherit; }
.np-item:hover { background:var(--bg-secondary); }
.np-item.unread { border-left-color:var(--secondary);background:rgba(46,117,182,.04); }
.np-item.unread .np-title { font-weight:700; }
.np-dot   { width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:5px; }
.np-body  { flex:1;min-width:0; }
.np-title { font-size:13px;font-weight:500;color:var(--text);
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.np-msg   { font-size:12px;color:var(--text-muted);margin-top:2px;
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.np-time  { font-size:11px;color:var(--text-muted);flex-shrink:0;padding-top:2px; }
.np-check { flex-shrink:0;accent-color:var(--secondary); }
/* ── Day group headers ────────────────────────────────────────── */
.np-day-group { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;
                color:var(--text-muted);padding:8px 16px 4px;background:var(--bg-secondary);
                border-bottom:1px solid var(--border); }
/* ── Bulk bar ─────────────────────────────────────────────────── */
#np-bulk-bar { display:none;align-items:center;gap:12px;padding:10px 16px;
               background:var(--secondary);color:#fff;font-size:13px;font-weight:600;
               border-radius:8px;margin-bottom:12px; }
#np-bulk-bar.visible { display:flex; }
/* ── Empty state ──────────────────────────────────────────────── */
.np-empty { padding:64px 24px;text-align:center;color:var(--text-muted);font-size:15px; }
</style>

<main class="main" id="main-content">
<div class="container">

  <!-- ── Page header ── -->
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="bi bi-bell-fill" style="color:var(--secondary);"></i> Notifications</h1>
      <p class="page-subtitle">All alerts and activity in one place.</p>
    </div>
    <button class="btn btn-secondary" id="np-mark-all">
      <i class="bi bi-check2-all"></i> Mark all as read
    </button>
  </div>

  <!-- ── Toolbar ── -->
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
    <!-- Filter pills -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;" id="np-pills">
      <span class="np-pill active" data-filter="">All</span>
      <span class="np-pill" data-filter="unread">
        <i class="bi bi-circle-fill" style="font-size:8px;color:var(--secondary);"></i> Unread
      </span>
      <span class="np-pill" data-filter="blacklist_alert">
        <i class="bi bi-slash-circle-fill" style="color:var(--danger);"></i> Blacklist Hits
      </span>
      <span class="np-pill" data-filter="system">
        <i class="bi bi-gear-fill"></i> System
      </span>
    </div>

    <!-- Search -->
    <div style="margin-left:auto;position:relative;">
      <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
      <input type="search" id="np-search" class="form-control" placeholder="Search notifications…"
             style="padding-left:32px;width:220px;font-size:13px;" autocomplete="off">
    </div>
  </div>

  <!-- ── Bulk action bar ── -->
  <div id="np-bulk-bar">
    <span id="np-bulk-count">0 selected</span>
    <button class="btn btn-sm" id="np-bulk-read"
            style="background:rgba(255,255,255,.2);color:#fff;border:1.5px solid rgba(255,255,255,.5);">
      <i class="bi bi-check2-all"></i> Mark read
    </button>
    <button class="btn btn-sm" id="np-bulk-cancel"
            style="background:none;color:rgba(255,255,255,.8);border:none;margin-left:auto;">
      Cancel
    </button>
  </div>

  <!-- ── Notifications list ── -->
  <div class="card" style="overflow:hidden;">
    <div id="np-list">
      <div class="np-empty">
        <i class="bi bi-bell-slash" style="font-size:48px;display:block;margin-bottom:12px;opacity:.3;"></i>
        Loading…
      </div>
    </div>

    <!-- Load more -->
    <div id="np-load-more-wrap" style="display:none;padding:14px;text-align:center;
         border-top:1px solid var(--border);">
      <button class="btn btn-secondary" id="np-load-more" style="font-size:13px;">
        <i class="bi bi-arrow-down"></i> Load more
      </button>
    </div>
  </div>

</div><!-- /.container -->
</main>

<script>
(function () {
  'use strict';

  var CSRF  = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var BASE  = window.BASE_URL || '/svms/';

  var state = { filter: '', page: 1, loading: false, allLoaded: false };
  var allItems  = [];   // flat array of all fetched items
  var searchStr = '';

  // ── Fetch items ────────────────────────────────────────────────────────────
  function load(reset) {
    if (state.loading) return;
    if (reset) { state.page = 1; state.allLoaded = false; allItems = []; }
    state.loading = true;

    var url = BASE + 'api/notifications.php?limit=50&page=' + state.page
            + '&since=0'
            + (state.filter ? '&type=' + encodeURIComponent(state.filter) : '');

    fetch(url, { headers: { 'X-CSRF-Token': CSRF } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        state.loading = false;
        if (!d.ok) return;
        var items = d.items || [];
        allItems = allItems.concat(items);
        if (items.length < 50) state.allLoaded = true;
        render();
        document.getElementById('np-load-more-wrap').style.display =
          state.allLoaded ? 'none' : 'block';
      })
      .catch(function () { state.loading = false; });
  }

  // ── Day grouping ────────────────────────────────────────────────────────────
  function _dayLabel(created_at) {
    var d      = new Date(created_at.replace(' ','T'));
    var now    = new Date();
    var today  = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var dDay   = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    var diff   = today - dDay;
    if (diff === 0)            return 'Today';
    if (diff === 86400000)     return 'Yesterday';
    if (diff < 7 * 86400000)  return 'Earlier this week';
    return 'Older';
  }

  // ── Render list ────────────────────────────────────────────────────────────
  function render() {
    var filtered = allItems.filter(function (n) {
      if (!searchStr) return true;
      var q = searchStr.toLowerCase();
      return (n.title || '').toLowerCase().indexOf(q) > -1
          || (n.message || '').toLowerCase().indexOf(q) > -1;
    });

    var list = document.getElementById('np-list');
    if (!filtered.length) {
      list.innerHTML = '<div class="np-empty">You\'re all caught up. 🎉</div>';
      return;
    }

    var grouped = {};
    var order   = [];
    filtered.forEach(function (n) {
      var lbl = _dayLabel(n.created_at);
      if (!grouped[lbl]) { grouped[lbl] = []; order.push(lbl); }
      grouped[lbl].push(n);
    });

    var html = '';
    order.forEach(function (lbl) {
      html += '<div class="np-day-group">' + lbl + '</div>';
      grouped[lbl].forEach(function (n) {
        var msg  = (n.message || '').substring(0, 80) + (n.message && n.message.length > 80 ? '…' : '');
        var link = n.link ? (n.link.indexOf('://') > -1 ? n.link : BASE + n.link) : '#';
        html += '<div class="np-item' + (n.is_read ? '' : ' unread') + '"'
          + ' data-id="' + n.id + '" data-link="' + _esc(link) + '">'
          + '<input type="checkbox" class="np-check" data-id="' + n.id + '" tabindex="-1">'
          + '<div class="np-dot" style="background:' + _esc(n.dot_colour) + ';"></div>'
          + '<div class="np-body">'
          +   '<div class="np-title">' + _esc(n.title) + '</div>'
          +   (msg ? '<div class="np-msg">' + _esc(msg) + '</div>' : '')
          + '</div>'
          + '<div class="np-time">' + _esc(n.rel_time) + '</div>'
          + '</div>';
      });
    });

    list.innerHTML = html;
    _bindRows();
  }

  // ── Row interactions ────────────────────────────────────────────────────────
  function _bindRows() {
    document.querySelectorAll('#np-list .np-item').forEach(function (row) {
      // Checkbox change → bulk bar
      var cb = row.querySelector('.np-check');
      if (cb) {
        cb.addEventListener('change', function (e) {
          e.stopPropagation();
          _updateBulkBar();
        });
      }
      // Row click (not on checkbox)
      row.addEventListener('click', function (e) {
        if (e.target.closest('.np-check')) return;
        var id   = +this.dataset.id;
        var link = this.dataset.link;
        this.classList.remove('unread');
        _markRead([id]);
        _updateItemInState(id, true);
        if (link && link !== '#') window.location.href = link;
      });
    });
  }

  // ── Mark read helpers ───────────────────────────────────────────────────────
  function _markRead(ids) {
    if (!ids.length) return;
    fetch(BASE + 'api/notifications.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ action: 'mark_read', ids: ids, csrf_token: CSRF }),
    }).catch(function () {});
  }

  function _updateItemInState(id, is_read) {
    allItems.forEach(function (n) { if (+n.id === id) n.is_read = is_read; });
  }

  // ── Bulk bar ────────────────────────────────────────────────────────────────
  function _getChecked() {
    return Array.from(document.querySelectorAll('#np-list .np-check:checked'))
      .map(function (c) { return +c.dataset.id; });
  }

  function _updateBulkBar() {
    var ids = _getChecked();
    var bar = document.getElementById('np-bulk-bar');
    document.getElementById('np-bulk-count').textContent = ids.length + ' selected';
    bar.classList.toggle('visible', ids.length > 0);
  }

  // ── Utility ─────────────────────────────────────────────────────────────────
  function _esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
           .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── Event bindings ──────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    load(true);

    // Filter pills
    document.getElementById('np-pills').addEventListener('click', function (e) {
      var pill = e.target.closest('.np-pill');
      if (!pill) return;
      document.querySelectorAll('.np-pill').forEach(function (p) { p.classList.remove('active'); });
      pill.classList.add('active');
      state.filter = pill.dataset.filter || '';
      load(true);
    });

    // Search
    var searchTimer;
    document.getElementById('np-search').addEventListener('input', function () {
      clearTimeout(searchTimer);
      var v = this.value;
      searchTimer = setTimeout(function () { searchStr = v.trim(); render(); }, 250);
    });

    // Mark all
    document.getElementById('np-mark-all').addEventListener('click', function () {
      fetch(BASE + 'api/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ action: 'mark_all_read', csrf_token: CSRF }),
      }).then(function () {
        allItems.forEach(function (n) { n.is_read = true; });
        render();
        if (window.SVMS_Notifications) SVMS_Notifications.refresh();
      }).catch(function () {});
    });

    // Bulk mark read
    document.getElementById('np-bulk-read').addEventListener('click', function () {
      var ids = _getChecked();
      if (!ids.length) return;
      _markRead(ids);
      ids.forEach(function (id) { _updateItemInState(id, true); });
      render();
      document.getElementById('np-bulk-bar').classList.remove('visible');
    });

    // Bulk cancel
    document.getElementById('np-bulk-cancel').addEventListener('click', function () {
      document.querySelectorAll('#np-list .np-check:checked').forEach(function (c) {
        c.checked = false;
      });
      document.getElementById('np-bulk-bar').classList.remove('visible');
    });

    // Load more
    document.getElementById('np-load-more').addEventListener('click', function () {
      state.page++;
      load(false);
    });
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
