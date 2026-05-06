/* ============================================================
   theme.js — SVMS Theme System v3.0
   Supports: light | dark | system (OS-preference)
   Persists mode in localStorage; saves to DB via AJAX.
   Exposes window.themeAPI and window.SVMSTheme (compat).
   ============================================================ */
(function () {
  'use strict';

  var STORAGE_KEY  = 'svms_theme_mode';
  var VALID_MODES  = ['light', 'dark', 'system'];
  var _saveTimer   = null;

  /* ── Persistence helpers ──────────────────────────────────── */
  function getSavedMode() {
    try { return localStorage.getItem(STORAGE_KEY) || 'system'; } catch (e) { return 'system'; }
  }

  function saveMode(mode) {
    try { localStorage.setItem(STORAGE_KEY, mode); } catch (e) {}
  }

  /* ── Resolution helpers ───────────────────────────────────── */
  function getSystemTheme() {
    return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
      ? 'dark' : 'light';
  }

  function resolveTheme(mode) {
    if (mode === 'dark')  return 'dark';
    if (mode === 'light') return 'light';
    return getSystemTheme();   // 'system'
  }

  /* ── Core apply ───────────────────────────────────────────── */
  function apply(mode) {
    if (VALID_MODES.indexOf(mode) === -1) mode = 'system';
    saveMode(mode);

    var resolved = resolveTheme(mode);
    var root = document.documentElement;
    root.setAttribute('data-theme', resolved);
    root.setAttribute('data-theme-mode', mode);

    // Update quick-toggle icon (sun = currently dark → click to go light)
    var icon = document.querySelector('#theme-toggle i');
    if (icon) {
      icon.className = resolved === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
      icon.style.transition = 'transform 200ms ease, opacity 200ms ease';
      icon.style.transform  = 'rotate(30deg) scale(0.8)';
      icon.style.opacity    = '0.5';
      setTimeout(function () {
        icon.style.transform = 'rotate(0deg) scale(1)';
        icon.style.opacity   = '1';
      }, 10);
    }

    // Update submenu radio rows
    var rows = document.querySelectorAll('[data-theme-mode-btn]');
    for (var i = 0; i < rows.length; i++) {
      var btn   = rows[i];
      var isMe  = btn.getAttribute('data-theme-mode-btn') === mode;
      btn.classList.toggle('active', isMe);
      var chk = btn.querySelector('.tm-check');
      if (chk) chk.style.display = isMe ? 'inline' : 'none';
    }

    // Dispatch event so Chart.js and other modules can react
    document.dispatchEvent(new CustomEvent('themechange', {
      detail: { theme: resolved, mode: mode }
    }));

    // Debounce AJAX save (avoid spam on rapid toggle)
    clearTimeout(_saveTimer);
    _saveTimer = setTimeout(function () { _saveToServer(mode); }, 400);
  }

  /* ── AJAX persistence to DB ───────────────────────────────── */
  function _saveToServer(mode) {
    var url = (window.BASE_URL || '') + 'api/set_preference.php';
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    if (!csrf) return;  // not logged in (login page) — skip
    try {
      fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: csrf, key: 'theme', value: mode })
      }).catch(function () {});
    } catch (e) {}
  }

  /* ── Anti-FOUC: apply theme immediately (before paint) ───── */
  (function () {
    var mode     = getSavedMode();
    var resolved = resolveTheme(mode);
    document.documentElement.setAttribute('data-theme', resolved);
    document.documentElement.setAttribute('data-theme-mode', mode);
    // Suppress all CSS transitions during initial paint
    document.documentElement.classList.add('preload');
  })();

  /* ── Remove preload class after first paint ───────────────── */
  if (document.readyState === 'complete') {
    setTimeout(function () { document.documentElement.classList.remove('preload'); }, 50);
  } else {
    window.addEventListener('load', function () {
      setTimeout(function () { document.documentElement.classList.remove('preload'); }, 50);
    });
  }

  /* ── Respond to OS preference changes in system mode ─────── */
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
      if (getSavedMode() === 'system') {
        var resolved = e.matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', resolved);
        document.dispatchEvent(new CustomEvent('themechange', {
          detail: { theme: resolved, mode: 'system' }
        }));
      }
    });
  }

  /* ── Wire up DOM after ready ──────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    // Quick-toggle button (single click → light ↔ dark, skips system)
    var toggleBtn = document.getElementById('theme-toggle');
    if (toggleBtn) {
      toggleBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var cur = resolveTheme(getSavedMode());
        apply(cur === 'dark' ? 'light' : 'dark');
      });
    }

    // Submenu radio rows
    var rows = document.querySelectorAll('[data-theme-mode-btn]');
    for (var i = 0; i < rows.length; i++) {
      (function (btn) {
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          apply(btn.getAttribute('data-theme-mode-btn'));
        });
      })(rows[i]);
    }

    // Sync initial check marks
    var currentMode = getSavedMode();
    var allRows = document.querySelectorAll('[data-theme-mode-btn]');
    for (var j = 0; j < allRows.length; j++) {
      var isActive = allRows[j].getAttribute('data-theme-mode-btn') === currentMode;
      allRows[j].classList.toggle('active', isActive);
      var chk = allRows[j].querySelector('.tm-check');
      if (chk) chk.style.display = isActive ? 'inline' : 'none';
    }

    // Sync toggle icon
    var icon = document.querySelector('#theme-toggle i');
    if (icon) {
      icon.className = resolveTheme(currentMode) === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
    }
  });

  /* ── Public API ───────────────────────────────────────────── */
  window.themeAPI = {
    set:         apply,
    get:         getSavedMode,
    getResolved: function () { return document.documentElement.getAttribute('data-theme') || 'light'; },
    toggle:      function () {
      var cur = resolveTheme(getSavedMode());
      apply(cur === 'dark' ? 'light' : 'dark');
    }
  };

  /* ── Backward-compat shim ─────────────────────────────────── */
  window.SVMSTheme = {
    apply:   apply,
    toggle:  function () { window.themeAPI.toggle(); },
    current: function () { return window.themeAPI.getResolved(); }
  };

})();
