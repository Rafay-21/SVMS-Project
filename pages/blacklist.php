<?php
/**
 * pages/blacklist.php — Blacklist & Watchlist (Phase 4.4)
 * Severity-levels, full CRUD via AJAX, audit history drawer, soft-delete.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('manage_blacklist');

$page_title = 'Blacklist & Watchlist';

$page_title = 'Blacklist & Watchlist';
$_my_role   = role_slug((int)($_SESSION['role_id'] ?? 0));

include __DIR__ . '/../includes/header.php';
?>
<style>
/* ── Severity pills ─── */
.sev-low      { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
.sev-medium   { background:#fef9c3; color:#854d0e; border:1px solid #fde68a; }
.sev-high     { background:#ffedd5; color:#9a3412; border:1px solid #fed7aa; }
.sev-critical { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
.sev-pill     { display:inline-flex; align-items:center; gap:4px; padding:3px 10px;
                border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase;
                letter-spacing:.4px; white-space:nowrap; }
/* ── Toolbar ─── */
.bl-toolbar   { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.bl-inactive-toggle { display:inline-flex; align-items:center; gap:6px; font-size:13px;
                       color:var(--text-muted); cursor:pointer; user-select:none; }
/* ── History drawer ─── */
.bl-drawer-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:1090;
                       opacity:0; transition:opacity 240ms; display:none; }
.bl-drawer-backdrop.open { opacity:1; }
.bl-drawer  { position:fixed; top:0; right:0; height:100vh; width:480px; max-width:100vw;
               background:var(--card); box-shadow:-4px 0 28px rgba(0,0,0,.18); z-index:1100;
               transform:translateX(100%); transition:transform 240ms ease-out;
               display:none; flex-direction:column; overflow:hidden; }
.bl-drawer.open { transform:translateX(0); }
.bl-drawer-header { display:flex; align-items:center; justify-content:space-between;
                    padding:16px 20px; border-bottom:1px solid var(--border);
                    background:var(--bg-secondary); flex-shrink:0; }
.bl-drawer-close  { background:none; border:none; font-size:22px; color:var(--text-muted);
                    cursor:pointer; line-height:1; padding:0 4px; }
.bl-drawer-body   { flex:1; overflow-y:auto; padding:20px; scrollbar-width:thin; }
/* ── Toggle switch ─── */
.toggle-switch { position:relative; display:inline-block; width:36px; height:20px; }
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; cursor:pointer; inset:0; background:#ccc;
                 border-radius:20px; transition:.2s; }
.toggle-slider::before { content:''; position:absolute; height:14px; width:14px;
                          left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
.toggle-switch input:checked + .toggle-slider { background:var(--success); }
.toggle-switch input:checked + .toggle-slider::before { transform:translateX(16px); }
/* ── Table tooltip ─── */
.bl-row:hover td { background:var(--bg-secondary); }
/* ── History log line ─── */
.hist-line { display:flex; gap:10px; font-size:12px; border-bottom:1px solid var(--border);
             padding:8px 0; align-items:flex-start; }
.hist-icon { width:24px; height:24px; border-radius:50%; display:flex; align-items:center;
             justify-content:center; flex-shrink:0; font-size:12px; }
.hist-body { flex:1; }
.hist-time { color:var(--text-muted); font-size:11px; }
</style>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<main class="main" id="main-content">
<div class="container">

  <!-- ── Page header ── -->
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <i class="bi bi-slash-circle-fill" style="color:var(--danger);"></i> Blacklist &amp; Watchlist
      </h1>
      <p class="page-subtitle">Manage individuals who are restricted from entry.</p>
    </div>
    <?php if (can('manage_blacklist')): ?>
    <button class="btn btn-danger" id="btn-add-blacklist">
      <i class="bi bi-person-fill-slash"></i> + Add to Blacklist
    </button>
    <?php endif; ?>
  </div>

  <!-- ── Toolbar ── -->
  <div class="bl-toolbar">
    <div style="position:relative;">
      <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
      <input type="search" id="bl-search" class="form-control" placeholder="Name, phone, CNIC…"
             style="padding-left:32px;width:220px;font-size:13px;" autocomplete="off">
    </div>

    <select id="bl-severity-filter" class="form-control" style="width:auto;">
      <option value="">All Severities</option>
      <option value="low">Low</option>
      <option value="medium">Medium</option>
      <option value="high">High</option>
      <option value="critical">Critical</option>
    </select>

    <label class="bl-inactive-toggle" id="show-inactive-label">
      <input type="checkbox" id="show-inactive" style="accent-color:var(--secondary);">
      Show inactive
    </label>

    <span id="bl-count" style="font-size:12px;color:var(--text-muted);margin-left:auto;"></span>
  </div>

  <!-- ── Table ── -->
  <div class="card" style="overflow:hidden;">
    <div class="table-responsive">
      <table class="table" id="bl-table">
        <thead>
          <tr>
            <th style="width:36px;"></th>
            <th>Identifier</th>
            <th>Severity</th>
            <th>Reason</th>
            <th>Added By</th>
            <th>Added At</th>
            <th>Blocks</th>
            <th>Active</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="bl-tbody">
          <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--text-muted);">
            <div class="shimmer" style="height:20px;width:60%;margin:auto;border-radius:4px;"></div>
          </td></tr>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <div id="bl-pagination" style="display:flex;justify-content:flex-end;align-items:center;
         gap:8px;padding:12px 16px;border-top:1px solid var(--border);"></div>
  </div>

</div><!-- /.container -->
</main>

<!-- ── Add / Edit Modal ──────────────────────────────────────────────────── -->
<div class="modal-backdrop" id="bl-modal-backdrop" style="display:none;"
     role="dialog" aria-modal="true" aria-labelledby="bl-modal-title">
  <div class="modal" style="width:560px;max-width:100%;" id="bl-modal">
    <div class="modal-header">
      <h3 class="modal-title" id="bl-modal-title">Add to Blacklist</h3>
      <button class="modal-close" id="bl-modal-close" aria-label="Close">&times;</button>
    </div>
    <form id="bl-form" novalidate>
      <input type="hidden" id="bl-action" value="add">
      <input type="hidden" id="bl-id" value="">
      <div class="modal-body" style="max-height:72vh;overflow-y:auto;padding:20px;">

        <!-- Name (optional) -->
        <div class="form-group">
          <label for="bl-name">Full Name <small style="color:var(--text-muted);">(optional)</small></label>
          <input type="text" id="bl-name" class="form-control" placeholder="As appears on ID document">
        </div>

        <!-- Phone + CNIC (at least one required) -->
        <div class="form-row">
          <div class="form-group">
            <label for="bl-phone">Phone <span style="color:var(--danger);">*</span> <small style="color:var(--text-muted);">(or CNIC)</small></label>
            <input type="tel" id="bl-phone" class="form-control" placeholder="+92 300 0000000">
          </div>
          <div class="form-group">
            <label for="bl-cnic">CNIC <span style="color:var(--danger);">*</span> <small style="color:var(--text-muted);">(or phone)</small></label>
            <input type="text" id="bl-cnic" class="form-control" placeholder="12345-1234567-1">
          </div>
        </div>

        <!-- Severity -->
        <div class="form-group">
          <label for="bl-severity">Severity <span style="color:var(--danger);">*</span></label>
          <select id="bl-severity" class="form-control">
            <option value="low">Low — Minor concern, monitor only</option>
            <option value="medium" selected>Medium — Deny entry, log attempt</option>
            <option value="high">High — Alert security on match</option>
            <option value="critical">Critical — Immediate security response</option>
          </select>
        </div>

        <!-- Reason -->
        <div class="form-group">
          <label for="bl-reason">Reason <span style="color:var(--danger);">*</span> <small style="color:var(--text-muted);">(min 20 chars)</small></label>
          <textarea id="bl-reason" class="form-control" rows="3" placeholder="Describe the security concern or incident…"></textarea>
          <div id="bl-reason-count" style="font-size:11px;color:var(--text-muted);text-align:right;margin-top:2px;">0 / 20 minimum</div>
        </div>

        <!-- Source -->
        <div class="form-row">
          <div class="form-group">
            <label for="bl-source">Source</label>
            <select id="bl-source" class="form-control">
              <option value="internal">Internal decision</option>
              <option value="lea_notice">LEA notice</option>
              <option value="court_order">Court order</option>
              <option value="self_blocked">Self-blocked by visitor</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label for="bl-expiry">Expiry Date <small style="color:var(--text-muted);">(auto-deactivate)</small></label>
            <input type="date" id="bl-expiry" class="form-control">
          </div>
        </div>

        <!-- Notes -->
        <div class="form-group" style="margin-bottom:0;">
          <label for="bl-notes">Internal Notes <small style="color:var(--text-muted);">(not shown publicly)</small></label>
          <textarea id="bl-notes" class="form-control" rows="2" placeholder="Additional context…"></textarea>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="bl-modal-cancel">Cancel</button>
        <button type="submit" class="btn btn-danger" id="bl-form-save">
          <i class="bi bi-slash-circle-fill"></i> Save Entry
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Remove Reason Modal ───────────────────────────────────────────────── -->
<div class="modal-backdrop" id="bl-remove-backdrop" style="display:none;"
     role="dialog" aria-modal="true" aria-labelledby="bl-remove-title">
  <div class="modal" style="width:420px;max-width:100%;">
    <div class="modal-header">
      <h3 class="modal-title" id="bl-remove-title">Remove from Blacklist</h3>
      <button class="modal-close" id="bl-remove-close" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body" style="padding:20px;">
      <p style="font-size:14px;margin-bottom:12px;">
        This entry will be <strong>soft-deleted</strong> (deactivated) and remain in the audit trail.
      </p>
      <div class="form-group" style="margin-bottom:0;">
        <label for="bl-removed-reason">Removal Reason <small style="color:var(--text-muted);">(optional)</small></label>
        <textarea id="bl-removed-reason" class="form-control" rows="2" placeholder="Why is this entry being removed?"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" id="bl-remove-cancel">Cancel</button>
      <button type="button" class="btn btn-danger" id="bl-remove-confirm">
        <i class="bi bi-person-check-fill"></i> Remove Entry
      </button>
    </div>
  </div>
</div>

<!-- ── History Drawer ────────────────────────────────────────────────────── -->
<div id="bl-drawer-backdrop" class="bl-drawer-backdrop" aria-hidden="true"></div>
<aside id="bl-drawer" class="bl-drawer" role="dialog" aria-modal="true" aria-labelledby="bl-drawer-title">
  <div class="bl-drawer-header">
    <h2 style="font-size:15px;font-weight:700;color:var(--text);margin:0;" id="bl-drawer-title">Audit History</h2>
    <button class="bl-drawer-close" id="bl-drawer-close" aria-label="Close">&times;</button>
  </div>
  <div class="bl-drawer-body" id="bl-drawer-body">
    <p style="color:var(--text-muted);text-align:center;padding:32px;">Loading…</p>
  </div>
</aside>

<script>
(function () {
  'use strict';

  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var BASE = window.BASE_URL || '/svms/';

  // ── State ──────────────────────────────────────────────────────────────────
  var state = { q: '', severity: '', showInactive: false, page: 1, total: 0, pages: 1 };
  var pendingRemoveId = 0;

  // ── Severity config ────────────────────────────────────────────────────────
  var SEV = {
    low:      { cls: 'sev-low',      label: 'Low',      icon: 'bi-shield-check' },
    medium:   { cls: 'sev-medium',   label: 'Medium',   icon: 'bi-shield-exclamation' },
    high:     { cls: 'sev-high',     label: 'High',     icon: 'bi-shield-fill-exclamation' },
    critical: { cls: 'sev-critical', label: 'Critical', icon: 'bi-shield-fill-x' },
  };

  // ── Fetch & Render ─────────────────────────────────────────────────────────
  function loadEntries() {
    var params = new URLSearchParams({
      action:        'list',
      q:             state.q,
      severity:      state.severity,
      show_inactive: state.showInactive ? '1' : '0',
      page:          String(state.page),
    });
    fetch(BASE + 'api/blacklist.php?' + params, { headers: { 'X-CSRF-Token': CSRF } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) return SVMS.toast(d.error || 'Load failed', 'error');
        state.total = d.total; state.pages = d.pages;
        renderTable(d.entries);
        renderPagination();
        document.getElementById('bl-count').textContent =
          d.total + ' entr' + (d.total === 1 ? 'y' : 'ies');
      })
      .catch(function () { SVMS.toast('Network error', 'error'); });
  }

  function renderTable(entries) {
    var tbody = document.getElementById('bl-tbody');
    if (!entries.length) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:48px 16px;color:var(--text-muted);">'
        + '<i class="bi bi-shield-check" style="font-size:36px;display:block;margin-bottom:8px;opacity:.3;"></i>'
        + 'No blacklist entries found.</td></tr>';
      return;
    }
    tbody.innerHTML = entries.map(function (e) {
      var sev   = SEV[e.severity] || SEV.medium;
      var isAct = parseInt(e.is_active, 10) === 1;
      var ident = [
        e.name  ? '<strong>' + _esc(e.name)  + '</strong>' : '',
        e.phone ? '<div style="font-size:11px;color:var(--text-muted);">' + _esc(e.phone) + '</div>' : '',
        e.cnic  ? '<div style="font-size:11px;color:var(--text-muted);">' + _esc(e.cnic)  + '</div>' : '',
      ].filter(Boolean).join('') || '<span style="color:var(--text-muted);">—</span>';
      var tooltip = 'Block count: ' + (e.block_count || 0) + ' time' + (e.block_count === '1' ? '' : 's');
      var rowCls  = isAct ? 'bl-row' : 'bl-row' + ' style="opacity:.55;"';

      return '<tr class="bl-row" title="' + tooltip + '"' + (!isAct ? ' style="opacity:.55;"' : '') + '>'
        + '<td style="padding:8px 4px;"><input type="checkbox" class="bl-row-check" data-id="' + e.id + '"></td>'
        + '<td>' + ident + '</td>'
        + '<td><span class="sev-pill ' + sev.cls + '"><i class="bi ' + sev.icon + '"></i> ' + sev.label + '</span></td>'
        + '<td style="max-width:200px;font-size:12px;">' + _esc(e.reason) + '</td>'
        + '<td style="font-size:12px;">' + _esc(e.added_by_name || '—') + '</td>'
        + '<td style="font-size:12px;white-space:nowrap;">' + _fmtDate(e.added_at) + '</td>'
        + '<td style="text-align:center;font-size:13px;font-weight:600;color:var(--text-muted);">'
        +   (parseInt(e.block_count, 10) || 0) + '</td>'
        + '<td style="text-align:center;">'
        +   '<label class="toggle-switch" title="Toggle active">'
        +   '<input type="checkbox" class="bl-toggle" data-id="' + e.id + '" ' + (isAct ? 'checked' : '') + '>'
        +   '<span class="toggle-slider"></span></label></td>'
        + '<td style="white-space:nowrap;">'
        +   '<button class="btn btn-sm btn-secondary btn-edit" data-id="' + e.id + '" title="Edit" style="padding:4px 8px;">'
        +     '<i class="bi bi-pencil-fill"></i></button> '
        +   '<button class="btn btn-sm btn-info btn-history" data-id="' + e.id + '" title="View History" style="padding:4px 8px;">'
        +     '<i class="bi bi-clock-history"></i></button> '
        +   '<button class="btn btn-sm btn-danger btn-remove" data-id="' + e.id + '" title="Remove" style="padding:4px 8px;">'
        +     '<i class="bi bi-person-check-fill"></i></button>'
        + '</td></tr>';
    }).join('');

    // Bind toggle switches
    tbody.querySelectorAll('.bl-toggle').forEach(function (el) {
      el.addEventListener('change', function () { doToggle(+this.dataset.id, this); });
    });
  }

  function renderPagination() {
    var pg = document.getElementById('bl-pagination');
    if (state.pages <= 1) { pg.innerHTML = ''; return; }
    var html = '';
    for (var i = 1; i <= state.pages; i++) {
      html += '<button class="btn btn-sm ' + (i === state.page ? 'btn-primary' : 'btn-secondary') + '"'
        + ' data-pg="' + i + '" style="padding:4px 10px;">' + i + '</button>';
    }
    pg.innerHTML = html;
    pg.querySelectorAll('button[data-pg]').forEach(function (b) {
      b.addEventListener('click', function () { state.page = +this.dataset.pg; loadEntries(); });
    });
  }

  // ── CRUD actions ───────────────────────────────────────────────────────────
  function doToggle(id, checkbox) {
    var payload = { action: 'toggle', id: id, csrf_token: CSRF };
    fetchPost(payload, function (d) {
      if (!d.ok) { checkbox.checked = !checkbox.checked; SVMS.toast(d.error || 'Failed', 'error'); }
      else SVMS.toast(d.is_active ? 'Entry reactivated.' : 'Entry deactivated.', 'info');
    });
  }

  function doRemove() {
    var reason = (document.getElementById('bl-removed-reason') || {}).value || '';
    fetchPost({ action: 'remove', id: pendingRemoveId, removed_reason: reason, csrf_token: CSRF }, function (d) {
      closeRemoveModal();
      if (d.ok) { SVMS.toast('Entry removed.', 'success'); loadEntries(); }
      else SVMS.toast(d.error || 'Failed', 'error');
    });
  }

  function doSave(e) {
    e.preventDefault();
    var action   = document.getElementById('bl-action').value;
    var id       = +document.getElementById('bl-id').value || null;
    var reason   = (document.getElementById('bl-reason').value || '').trim();

    if (!document.getElementById('bl-phone').value && !document.getElementById('bl-cnic').value) {
      return SVMS.toast('At least one of phone or CNIC is required.', 'error');
    }
    if (reason.length < 20) {
      return SVMS.toast('Reason must be at least 20 characters.', 'error');
    }

    var payload = {
      action:       action,
      csrf_token:   CSRF,
      name:         document.getElementById('bl-name').value,
      phone:        document.getElementById('bl-phone').value,
      cnic:         document.getElementById('bl-cnic').value,
      severity:     document.getElementById('bl-severity').value,
      reason:       reason,
      notes:        document.getElementById('bl-notes').value,
      source:       document.getElementById('bl-source').value,
      expiry_date:  document.getElementById('bl-expiry').value || null,
    };
    if (id) payload.id = id;

    var btn = document.getElementById('bl-form-save');
    btn.disabled = true; btn.textContent = 'Saving…';
    fetchPost(payload, function (d) {
      btn.disabled = false; btn.textContent = 'Save Entry';
      if (d.ok) {
        closeModal();
        SVMS.toast(action === 'add' ? 'Entry added to blacklist.' : 'Entry updated.', 'success');
        loadEntries();
      } else SVMS.toast(d.error || 'Failed', 'error');
    });
  }

  // ── History drawer ─────────────────────────────────────────────────────────
  function openHistory(id) {
    var body = document.getElementById('bl-drawer-body');
    body.innerHTML = '<p style="text-align:center;padding:32px;color:var(--text-muted);">Loading…</p>';
    openDrawer();
    fetch(BASE + 'api/blacklist.php?action=history&id=' + id, { headers: { 'X-CSRF-Token': CSRF } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { body.innerHTML = '<p style="color:var(--danger);">Failed to load history.</p>'; return; }
        var html = '<h4 style="font-size:13px;font-weight:700;color:var(--text);margin:0 0 12px;">Audit Log</h4>';
        if (d.logs && d.logs.length) {
          html += d.logs.map(function (l) {
            var ic = { blacklist_add:'person-fill-slash', blacklist_edit:'pencil-fill',
                       blacklist_remove:'person-check-fill', blacklist_reactivate:'arrow-clockwise',
                       blacklist_deactivate:'toggle-off' };
            return '<div class="hist-line">'
              + '<div class="hist-icon" style="background:var(--bg-secondary);">'
              +   '<i class="bi bi-' + (ic[l.action] || 'clock') + '"></i></div>'
              + '<div class="hist-body">'
              +   '<div><strong>' + _esc(l.action.replace(/_/g, ' ')) + '</strong>'
              +   (l.admin_name ? ' by ' + _esc(l.admin_name) : '')
              +   ' <span class="hist-time">· ' + _esc(l.created_at) + '</span></div>'
              +   (l.ip_address ? '<div style="color:var(--text-muted);font-size:11px;">IP: ' + _esc(l.ip_address) + '</div>' : '')
              + '</div></div>';
          }).join('');
        } else { html += '<p style="color:var(--text-muted);">No admin actions recorded.</p>'; }

        html += '<h4 style="font-size:13px;font-weight:700;color:var(--text);margin:16px 0 12px;">Block Attempts</h4>';
        if (d.blocks && d.blocks.length) {
          html += d.blocks.map(function (b) {
            return '<div class="hist-line">'
              + '<div class="hist-icon" style="background:#fee2e2;">'
              +   '<i class="bi bi-x-circle-fill" style="color:var(--danger);"></i></div>'
              + '<div class="hist-body">'
              +   '<div><strong>Blocked</strong> at ' + _esc(b.action.replace(/_/g, ' '))
              +   ' <span class="hist-time">· ' + _esc(b.created_at) + '</span></div>'
              +   '<div style="color:var(--text-muted);font-size:11px;">IP: ' + _esc(b.ip_address) + '</div>'
              + '</div></div>';
          }).join('');
        } else { html += '<p style="color:var(--text-muted);">No block attempts on record.</p>'; }

        body.innerHTML = html;
      })
      .catch(function () { body.innerHTML = '<p style="color:var(--danger);">Network error.</p>'; });
  }

  // ── Modal helpers ──────────────────────────────────────────────────────────
  function openModal(mode, id) {
    document.getElementById('bl-modal-title').textContent = mode === 'add' ? 'Add to Blacklist' : 'Edit Entry';
    document.getElementById('bl-action').value = mode;
    document.getElementById('bl-id').value     = id || '';
    document.getElementById('bl-form').reset();
    document.getElementById('bl-reason-count').textContent = '0 / 20 minimum';

    if (mode === 'edit' && id) {
      fetch(BASE + 'api/blacklist.php?action=get&id=' + id, { headers: { 'X-CSRF-Token': CSRF } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok || !d.entry) return;
          var e = d.entry;
          document.getElementById('bl-name').value     = e.name     || '';
          document.getElementById('bl-phone').value    = e.phone    || '';
          document.getElementById('bl-cnic').value     = e.cnic     || '';
          document.getElementById('bl-severity').value = e.severity || 'medium';
          document.getElementById('bl-reason').value   = e.reason   || '';
          document.getElementById('bl-notes').value    = e.notes    || '';
          document.getElementById('bl-source').value   = e.source   || 'internal';
          document.getElementById('bl-expiry').value   = e.expiry_date || '';
          updateReasonCount();
        });
    }

    var bd = document.getElementById('bl-modal-backdrop');
    bd.style.display = 'flex';
    document.getElementById('bl-modal').querySelector('input,select,textarea').focus();
  }

  function closeModal() {
    document.getElementById('bl-modal-backdrop').style.display = 'none';
  }

  function openRemoveModal(id) {
    pendingRemoveId = id;
    document.getElementById('bl-removed-reason').value = '';
    document.getElementById('bl-remove-backdrop').style.display = 'flex';
  }

  function closeRemoveModal() {
    document.getElementById('bl-remove-backdrop').style.display = 'none';
    pendingRemoveId = 0;
  }

  function openDrawer() {
    var bd = document.getElementById('bl-drawer-backdrop');
    var dr = document.getElementById('bl-drawer');
    bd.style.display = 'block'; requestAnimationFrame(function () { bd.classList.add('open'); });
    dr.style.display = 'flex';  requestAnimationFrame(function () { dr.classList.add('open'); });
  }

  function closeDrawer() {
    var bd = document.getElementById('bl-drawer-backdrop');
    var dr = document.getElementById('bl-drawer');
    bd.classList.remove('open'); setTimeout(function () { bd.style.display = 'none'; }, 250);
    dr.classList.remove('open'); setTimeout(function () { dr.style.display = 'none'; }, 250);
  }

  // ── Utility ────────────────────────────────────────────────────────────────
  function fetchPost(payload, cb) {
    fetch(BASE + 'api/blacklist.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(payload),
    }).then(function (r) { return r.json(); }).then(cb)
      .catch(function () { SVMS.toast('Network error', 'error'); });
  }

  function updateReasonCount() {
    var v = (document.getElementById('bl-reason').value || '').trim().length;
    var el = document.getElementById('bl-reason-count');
    el.textContent = v + ' / 20 minimum';
    el.style.color = v >= 20 ? 'var(--success)' : 'var(--danger)';
  }

  function _esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function _fmtDate(d) {
    if (!d) return '—';
    var dt = new Date(d.replace(' ','T'));
    return isNaN(dt) ? d : dt.toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
  }

  // ── Event binding ──────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    loadEntries();

    // Add button
    document.getElementById('btn-add-blacklist').addEventListener('click', function () { openModal('add'); });

    // Modal close
    document.getElementById('bl-modal-close').addEventListener('click', closeModal);
    document.getElementById('bl-modal-cancel').addEventListener('click', closeModal);
    document.getElementById('bl-modal-backdrop').addEventListener('click', function (e) {
      if (e.target === this) closeModal();
    });

    // Remove modal
    document.getElementById('bl-remove-close').addEventListener('click', closeRemoveModal);
    document.getElementById('bl-remove-cancel').addEventListener('click', closeRemoveModal);
    document.getElementById('bl-remove-confirm').addEventListener('click', doRemove);
    document.getElementById('bl-remove-backdrop').addEventListener('click', function (e) {
      if (e.target === this) closeRemoveModal();
    });

    // Drawer
    document.getElementById('bl-drawer-close').addEventListener('click', closeDrawer);
    document.getElementById('bl-drawer-backdrop').addEventListener('click', closeDrawer);

    // Form submit
    document.getElementById('bl-form').addEventListener('submit', doSave);

    // Reason counter
    document.getElementById('bl-reason').addEventListener('input', updateReasonCount);

    // Search + filters
    var searchTimer;
    document.getElementById('bl-search').addEventListener('input', function () {
      clearTimeout(searchTimer);
      var v = this.value;
      searchTimer = setTimeout(function () { state.q = v; state.page = 1; loadEntries(); }, 350);
    });
    document.getElementById('bl-severity-filter').addEventListener('change', function () {
      state.severity = this.value; state.page = 1; loadEntries();
    });
    document.getElementById('show-inactive').addEventListener('change', function () {
      state.showInactive = this.checked; state.page = 1; loadEntries();
    });

    // Table event delegation (edit / history / remove)
    document.getElementById('bl-tbody').addEventListener('click', function (e) {
      var btn = e.target.closest('button');
      if (!btn) return;
      var id = +btn.dataset.id;
      if (btn.classList.contains('btn-edit'))    openModal('edit', id);
      if (btn.classList.contains('btn-history')) openHistory(id);
      if (btn.classList.contains('btn-remove'))  openRemoveModal(id);
    });

    // Escape key
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { closeModal(); closeRemoveModal(); closeDrawer(); }
    });
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
