/* ============================================================
   notifications.js — Real-Time Notification Bell (Phase 4.4)
   5-second ID-based polling, browser notifications, Web Audio.
   ============================================================ */
(function () {
  'use strict';

  var CSRF       = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var BASE       = window.BASE_URL || '/svms/';

  var lastId     = 0;
  var prevCount  = 0;
  var panelLoaded = false;
  var panelTs    = 0;      // timestamp when panel was last populated
  var pollTimer  = null;
  var audioCtx   = null;

  // ── Helpers ─────────────────────────────────────────────────────────────────
  function _esc(s) {
    return String(s || '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function _badge(el, count) {
    el.textContent   = count > 0 ? (count > 99 ? '99+' : count) : '';
    el.style.display = count > 0 ? 'flex' : 'none';
  }

  function _pulseBadge(badge) {
    badge.classList.remove('notif-pulse');
    void badge.offsetWidth;                        // force reflow
    badge.classList.add('notif-pulse');
    setTimeout(function () { badge.classList.remove('notif-pulse'); }, 600);
  }

  function _playSound() {
    try {
      if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      var osc = audioCtx.createOscillator();
      var gain = audioCtx.createGain();
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      osc.type = 'sine';
      osc.frequency.value = 800;
      gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.08);
      osc.start(audioCtx.currentTime);
      osc.stop(audioCtx.currentTime + 0.08);
    } catch (e) { /* ignore */ }
  }

  // ── Render a single notification item ───────────────────────────────────────
  function _renderItem(n) {
    var msg = (n.message || '').substring(0, 80) + (n.message && n.message.length > 80 ? '…' : '');
    var link = n.link ? (n.link.indexOf('://') > -1 ? n.link : BASE + n.link) : '#';
    return '<a class="notif-item' + (n.is_read ? '' : ' unread') + ' notif-new"'
      + ' data-id="' + n.id + '" data-link="' + _esc(link) + '" href="#"'
      + ' role="menuitem" tabindex="0">'
      + '<div class="notif-dot" style="background:' + _esc(n.dot_colour) + ';"></div>'
      + '<div class="notif-content">'
      +   '<div class="notif-title">' + _esc(n.title) + '</div>'
      +   (msg ? '<div class="notif-message">' + _esc(msg) + '</div>' : '')
      + '</div>'
      + '<div class="notif-time">' + _esc(n.rel_time) + '</div>'
      + '</a>';
  }

  // ── Browser notifications ────────────────────────────────────────────────────
  function _checkBrowserNotif(items) {
    if (!('Notification' in window)) return;
    if (Notification.permission === 'denied') return;
    if (!sessionStorage.getItem('notif_permission_asked')) {
      sessionStorage.setItem('notif_permission_asked', '1');
      if (Notification.permission === 'default') Notification.requestPermission();
    }
    if (Notification.permission !== 'granted') return;
    items.forEach(function (n) {
      var fire = false;
      if (n.type === 'blacklist_alert') fire = true;
      if (n.type === 'visitor_checkin') fire = true;    // extend as needed
      if (fire) {
        try {
          new Notification(n.title || 'SVMS Alert', {
            body: n.message || '',
            icon: BASE + 'assets/img/logo.png',
            tag:  'svms-' + n.id,
          });
        } catch (e) { /* ignore */ }
      }
    });
  }

  // ── Mark as read (array of ids) ──────────────────────────────────────────────
  function _markRead(ids) {
    if (!ids || !ids.length) return;
    fetch(BASE + 'api/notifications.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ action: 'mark_read', ids: ids, csrf_token: CSRF }),
    }).catch(function () {});
  }

  function _markAllRead() {
    fetch(BASE + 'api/notifications.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ action: 'mark_all_read', csrf_token: CSRF }),
    }).catch(function () {});
    // Immediately clear UI
    var badge = document.getElementById('notif-badge');
    if (badge) { badge.textContent = ''; badge.style.display = 'none'; }
    prevCount = 0;
    document.querySelectorAll('#notif-list-body .notif-item.unread').forEach(function (el) {
      el.classList.remove('unread');
      el.style.borderLeftColor = 'transparent';
    });
  }

  // ── Poll for new notifications ───────────────────────────────────────────────
  function _poll() {
    if (document.hidden) return;
    fetch(BASE + 'api/notifications.php?since=' + lastId + '&limit=8', {
      headers: { 'X-CSRF-Token': CSRF },
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) return;

        var newCount = d.unread_count || 0;
        var badge    = document.getElementById('notif-badge');
        if (badge) {
          _badge(badge, newCount);
          if (newCount > prevCount) _pulseBadge(badge);
        }

        // Sound
        var bellBtn = document.getElementById('notification-bell');
        if (bellBtn && bellBtn.dataset.notifSound === '1' && newCount > prevCount) {
          _playSound();
        }

        prevCount = newCount;

        // Prepend new items to panel
        var items  = d.items || [];
        var newItems = items.filter(function (n) { return n.id > lastId; });
        if (newItems.length) {
          var body = document.getElementById('notif-list-body');
          if (body) {
            // Remove empty state if present
            var emptyEl = body.querySelector('.notif-empty');
            if (emptyEl) emptyEl.remove();

            var frag = document.createDocumentFragment();
            newItems.forEach(function (n) {
              var tmp = document.createElement('div');
              tmp.innerHTML = _renderItem(n);
              frag.appendChild(tmp.firstElementChild);
            });
            body.insertBefore(frag, body.firstChild);
          }
          _checkBrowserNotif(newItems);
        }

        if (d.last_id > lastId) lastId = d.last_id;
      })
      .catch(function () {});
  }

  // ── Populate panel (on first open or stale > 30s) ───────────────────────────
  function _loadPanel() {
    var body = document.getElementById('notif-list-body');
    if (!body) return;
    fetch(BASE + 'api/notifications.php?limit=8&since=0', {
      headers: { 'X-CSRF-Token': CSRF },
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) return;
        var items = d.items || [];
        if (!items.length) {
          body.innerHTML = '<div class="notif-empty">No new notifications 🎉</div>';
        } else {
          body.innerHTML = items.map(_renderItem).join('');
          if (d.last_id > lastId) lastId = d.last_id;
        }
        panelLoaded = true;
        panelTs     = Date.now();
      })
      .catch(function () {});
  }

  // ── Start / stop polling ─────────────────────────────────────────────────────
  function _startPolling() {
    if (pollTimer) return;
    pollTimer = setInterval(_poll, 5000);
  }

  function _stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  // ── Init ─────────────────────────────────────────────────────────────────────
  function init() {
    var bell    = document.getElementById('notification-bell');
    var panel   = document.getElementById('notification-panel');
    var markAll = document.getElementById('notif-mark-all');
    var body    = document.getElementById('notif-list-body');

    if (!bell || !panel) return;

    // Bell click → toggle panel + load if needed
    bell.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = panel.style.display === 'block';
      panel.style.display = isOpen ? 'none' : 'block';
      bell.setAttribute('aria-expanded', String(!isOpen));

      if (!isOpen) {
        // Load or refresh if stale > 30s
        if (!panelLoaded || (Date.now() - panelTs > 30000)) {
          _loadPanel();
        }
      }
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
      var wrapper = document.getElementById('notif-wrapper');
      if (wrapper && !wrapper.contains(e.target)) {
        panel.style.display = 'none';
        bell.setAttribute('aria-expanded', 'false');
      }
    });

    // Mark all read
    if (markAll) {
      markAll.addEventListener('click', function (e) {
        e.preventDefault();
        _markAllRead();
      });
    }

    // Click delegation on notification items
    if (body) {
      body.addEventListener('click', function (e) {
        var item = e.target.closest('.notif-item[data-id]');
        if (!item) return;
        e.preventDefault();
        var id   = +item.dataset.id;
        var link = item.dataset.link;
        item.classList.remove('unread');
        _markRead([id]);
        // Recount
        var unread = body.querySelectorAll('.notif-item.unread').length;
        var badge  = document.getElementById('notif-badge');
        if (badge) _badge(badge, unread > 0 ? unread : 0);
        panel.style.display = 'none';
        if (link && link !== '#') window.location.href = link;
      });
    }

    // Visibility-based polling pause/resume
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) _stopPolling();
      else { _poll(); _startPolling(); }
    });

    // Initial poll + start interval
    _poll();
    _startPolling();
  }

  document.addEventListener('DOMContentLoaded', init);

  window.addEventListener('beforeunload', _stopPolling);

  // ── Public API ────────────────────────────────────────────────────────────────
  window.SVMS_Notifications = {
    refresh:    _poll,
    markRead:   _markRead,
    markAllRead:_markAllRead,
  };

})();

