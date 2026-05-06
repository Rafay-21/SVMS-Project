/* ============================================================
   main.js — SVMS Core JavaScript Module v2.0
   Self-invoking module. Exposes window.SVMS namespace.
   ============================================================ */
(function () {
  'use strict';

  window.SVMS = window.SVMS || {};

  // ── Debounce ──────────────────────────────────────────────
  SVMS.debounce = function (fn, ms) {
    let timer;
    return function () {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, arguments), ms);
    };
  };

  // ── Toast Notifications ───────────────────────────────────
  SVMS.toast = function (message, type) {
    type = type || 'info';
    const icons = {
      success: 'bi-check-circle-fill',
      error:   'bi-x-circle-fill',
      warning: 'bi-exclamation-triangle-fill',
      info:    'bi-info-circle-fill'
    };

    const container = document.getElementById('toast-container');
    if (!container) return;

    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'polite');
    el.innerHTML =
      '<i class="toast-icon bi ' + (icons[type] || icons.info) + '"></i>' +
      '<div class="toast-body">' + message + '</div>' +
      '<button class="toast-close" aria-label="Close">&times;</button>';

    el.querySelector('.toast-close').addEventListener('click', function () {
      SVMS._removeToast(el);
    });

    container.appendChild(el);

    setTimeout(function () {
      SVMS._removeToast(el);
    }, 5000);
  };

  SVMS._removeToast = function (el) {
    if (!el.parentNode) return;
    el.classList.add('fade-out');
    setTimeout(function () {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, 350);
  };

  // ── Modal Management ──────────────────────────────────────
  SVMS.modal = (function () {
    let previouslyFocused = null;

    function open(id) {
      const backdrop = document.getElementById(id);
      if (!backdrop) return;
      previouslyFocused = document.activeElement;
      backdrop.classList.add('open');
      backdrop.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      // Focus first focusable element
      const focusable = backdrop.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      if (focusable.length) focusable[0].focus();

      // Trap focus
      backdrop.addEventListener('keydown', _trapFocus);
    }

    function close(id) {
      const backdrop = document.getElementById(id);
      if (!backdrop) return;
      backdrop.classList.remove('open');
      backdrop.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      backdrop.removeEventListener('keydown', _trapFocus);
      if (previouslyFocused) previouslyFocused.focus();
    }

    function _trapFocus(e) {
      if (e.key !== 'Tab') return;
      const focusable = this.querySelectorAll(
        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
      );
      const first = focusable[0];
      const last  = focusable[focusable.length - 1];
      if (e.shiftKey) {
        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
      } else {
        if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
      }
    }

    return { open: open, close: close };
  })();

  // ── Fetch Wrapper with CSRF ───────────────────────────────
  SVMS.fetch = function (url, opts) {
    opts = opts || {};
    opts.headers = opts.headers || {};

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
      opts.headers['X-CSRF-Token'] = csrfMeta.getAttribute('content');
    }

    if (!opts.headers['Content-Type'] && !(opts.body instanceof FormData)) {
      opts.headers['Content-Type'] = 'application/json';
    }

    opts.headers['X-Requested-With'] = 'XMLHttpRequest';

    return fetch(url, opts).then(function (res) {
      if (!res.ok) {
        return res.text().then(function (text) {
          throw new Error(text || 'Request failed: ' + res.status);
        });
      }
      const ct = res.headers.get('Content-Type') || '';
      return ct.includes('application/json') ? res.json() : res.text();
    });
  };

  // ── Promise-based Confirm Modal ───────────────────────────
  SVMS.confirm = function (text, onYes) {
    const modal = document.getElementById('confirm-modal');
    if (!modal) {
      if (window.confirm(text)) onYes();
      return;
    }
    modal.querySelector('.confirm-message').textContent = text;
    SVMS.modal.open('confirm-modal');

    const yesBtn = modal.querySelector('#confirm-yes');
    const noBtn  = modal.querySelector('#confirm-no');

    const cleanup = function () {
      SVMS.modal.close('confirm-modal');
      yesBtn.removeEventListener('click', handleYes);
      noBtn.removeEventListener('click',  handleNo);
    };

    const handleYes = function () { cleanup(); if (onYes) onYes(); };
    const handleNo  = function () { cleanup(); };

    yesBtn.addEventListener('click', handleYes);
    noBtn.addEventListener('click',  handleNo);
  };

  // ── Sidebar Toggle ────────────────────────────────────────
  function initSidebar() {
    const hamburger = document.getElementById('sidebar-toggle');
    const backdrop  = document.querySelector('.sidebar-backdrop');

    if (hamburger) {
      hamburger.addEventListener('click', function () {
        document.body.classList.toggle('sidebar-open');
      });
    }

    if (backdrop) {
      backdrop.addEventListener('click', function () {
        document.body.classList.remove('sidebar-open');
      });
    }

    // ESC closes sidebar on mobile
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        document.body.classList.remove('sidebar-open');
      }
    });
  }

  // ── Theme Switcher ────────────────────────────────────────
  // NOTE: Core theme logic is in theme.js (loaded before main.js).
  // main.js only wires the dropdown open/close for the 3-way menu.
  function initTheme() {
    var btn  = document.getElementById('theme-toggle');
    var menu = document.getElementById('theme-menu');
    if (!btn || !menu) return;

    // Right-click OR long-press → open submenu; normal click → toggle
    btn.addEventListener('contextmenu', function (e) {
      e.preventDefault();
      menu.classList.toggle('open');
    });

    // Also allow clicking a small caret/expand area — for now,
    // hold Shift+click to open the menu without toggling
    btn.addEventListener('click', function (e) {
      if (e.shiftKey) {
        e.stopPropagation();
        menu.classList.toggle('open');
        return;
      }
      // Default: quick toggle (handled by theme.js)
      menu.classList.remove('open');
    });

    document.addEventListener('click', function (e) {
      if (menu.classList.contains('open') &&
          !menu.contains(e.target) && e.target !== btn) {
        menu.classList.remove('open');
      }
    });
  }

  // ── Language Switcher ─────────────────────────────────────
  function initLangSwitcher() {
    const btn  = document.getElementById('lang-toggle');
    const menu = document.getElementById('lang-menu');

    if (!btn || !menu) return;

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      menu.classList.toggle('open');
    });

    // data-lang-btn clicks are handled by i18n.js
    document.addEventListener('click', function (e) {
      if (!menu.contains(e.target) && e.target !== btn) {
        menu.classList.remove('open');
      }
    });
  }

  // ── Smart Search ──────────────────────────────────────────
  function initSmartSearch() {
    const input   = document.getElementById('smart-search');
    const results = document.getElementById('search-results');

    if (!input || !results) return;

    const doSearch = SVMS.debounce(function () {
      const q = input.value.trim();
      if (q.length < 2) { results.classList.remove('open'); return; }

      SVMS.fetch('/svms/api/smart_search.php?q=' + encodeURIComponent(q))
        .then(function (data) {
          results.innerHTML = '';
          if (!data || !data.length) {
            results.innerHTML = '<div style="padding:12px 16px;color:var(--text-muted);font-size:13px;">No results found</div>';
          } else {
            data.forEach(function (item) {
              const row = document.createElement('a');
              row.href = item.url || '#';
              row.className = 'dropdown-item';
              row.innerHTML = '<i class="bi bi-person"></i> ' + (item.label || item.name || '');
              results.appendChild(row);
            });
          }
          results.classList.add('open');
        })
        .catch(function () {
          results.classList.remove('open');
        });
    }, 300);

    input.addEventListener('input', doSearch);

    input.addEventListener('focus', function () {
      if (input.value.trim().length >= 2) results.classList.add('open');
    });

    document.addEventListener('click', function (e) {
      if (!input.contains(e.target) && !results.contains(e.target)) {
        results.classList.remove('open');
      }
    });

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') results.classList.remove('open');
    });
  }

  // ── Modal close on backdrop click ────────────────────────
  function initModalBackdrops() {
    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
      backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) {
          SVMS.modal.close(backdrop.id);
        }
      });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const id = this.closest('.modal-backdrop').id;
        SVMS.modal.close(id);
      });
    });

    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        SVMS.modal.open(this.dataset.modalOpen);
      });
    });

    // ESC key closes any open modal
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        const open = document.querySelector('.modal-backdrop.open');
        if (open) SVMS.modal.close(open.id);
      }
    });
  }

  // ── Alert close buttons ───────────────────────────────────
  function initAlerts() {
    document.addEventListener('click', function (e) {
      if (e.target.classList.contains('alert-close')) {
        const alert = e.target.closest('.alert');
        if (alert) alert.remove();
      }
    });
  }

  // ── Dropdown toggles ──────────────────────────────────────
  function initDropdowns() {
    document.addEventListener('click', function (e) {
      const trigger = e.target.closest('[data-dropdown]');
      if (trigger) {
        e.stopPropagation();
        const menuId = trigger.dataset.dropdown;
        const menu   = document.getElementById(menuId);
        if (menu) menu.classList.toggle('open');
      } else {
        document.querySelectorAll('.dropdown-menu.open, .navbar-dropdown-menu.open').forEach(function (m) {
          m.classList.remove('open');
        });
      }
    });
  }

  // ── Tab system ────────────────────────────────────────────
  function initTabs() {
    document.querySelectorAll('.tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        const target  = this.dataset.tab;
        const parent  = this.closest('[data-tabs]');
        if (!parent) return;

        parent.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        parent.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        this.classList.add('active');
        const content = parent.querySelector('#tab-' + target);
        if (content) content.classList.add('active');
      });
    });
  }

  // ── Cookie Helpers ────────────────────────────────────────
  SVMS.setCookie = function (name, value, days) {
    const expires = days
      ? '; expires=' + new Date(Date.now() + days * 864e5).toUTCString()
      : '';
    document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Strict';
  };

  SVMS.getCookie = function (name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  };

  // ── DOM Ready ─────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    initTheme();
    initLangSwitcher();
    initSmartSearch();
    initModalBackdrops();
    initAlerts();
    initDropdowns();
    initTabs();
    initPWA();

    // Mark active nav link
    const path = window.location.pathname;
    document.querySelectorAll('.sidebar-link').forEach(function (link) {
      if (link.getAttribute('href') && path.endsWith(link.getAttribute('href').split('/').pop())) {
        link.classList.add('active');
      }
    });
  });

  // ── PWA: Service Worker + Install Prompt + Update Toast ──────────────────
  // Kept in one closure so deferredPrompt is shared between event and buttons.
  var _pwa = (function () {
    var deferredPrompt   = null; // BeforeInstallPromptEvent
    var swRegistration   = null;
    var isIOS            = /iphone|ipad|ipod/i.test(navigator.userAgent) &&
                           !window.MSStream;
    var isInStandalone   = window.matchMedia('(display-mode: standalone)').matches ||
                           (navigator.standalone === true);

    // ── Inject PWA UI styles once ─────────────────────────────────────────
    function injectStyles() {
      if (document.getElementById('pwa-styles')) return;
      var style = document.createElement('style');
      style.id  = 'pwa-styles';
      style.textContent = [
        /* Install bottom-sheet */
        '#pwa-install-sheet{position:fixed;bottom:0;left:0;right:0;z-index:10000;',
        'transform:translateY(110%);transition:transform .35s cubic-bezier(.16,1,.3,1);',
        'background:var(--card,#fff);border-top:1px solid var(--border,#e2e8f0);',
        'border-radius:20px 20px 0 0;padding:20px 20px 28px;',
        'box-shadow:0 -8px 40px rgba(0,0,0,.14);font-family:var(--font-base,sans-serif);}',
        '#pwa-install-sheet.open{transform:translateY(0);}',
        '#pwa-install-sheet .pwa-sheet-inner{display:flex;align-items:center;gap:14px;max-width:500px;margin:0 auto;}',
        '#pwa-install-sheet img{width:48px;height:48px;border-radius:12px;flex-shrink:0;}',
        '#pwa-install-sheet .pwa-sheet-body{flex:1;min-width:0;}',
        '#pwa-install-sheet strong{display:block;font-size:15px;font-weight:700;color:var(--text,#1e293b);margin-bottom:2px;}',
        '#pwa-install-sheet p{font-size:13px;color:var(--text-muted,#64748b);margin:0;}',
        '#pwa-install-sheet .pwa-sheet-btns{display:flex;gap:8px;flex-shrink:0;}',
        '.pwa-btn-later{padding:9px 16px;border:1.5px solid var(--border,#e2e8f0);border-radius:9px;',
        'background:none;font-size:13px;font-weight:600;color:var(--text-muted,#64748b);cursor:pointer;',
        'font-family:inherit;}',
        '.pwa-btn-install{padding:9px 18px;border:none;border-radius:9px;',
        'background:var(--primary,#1B3A5C);color:#fff;font-size:13px;font-weight:700;',
        'cursor:pointer;font-family:inherit;}',
        '.pwa-btn-install:hover{opacity:.88;}',
        /* iOS tip tooltip */
        '#pwa-ios-tip{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);',
        'z-index:10000;background:var(--card,#fff);border:1px solid var(--border,#e2e8f0);',
        'border-radius:14px;padding:14px 18px;font-size:13px;color:var(--text,#1e293b);',
        'box-shadow:0 8px 30px rgba(0,0,0,.15);max-width:320px;text-align:center;',
        'opacity:0;transition:opacity .3s,transform .3s;pointer-events:none;}',
        '#pwa-ios-tip.open{opacity:1;transform:translateX(-50%) translateY(0);pointer-events:auto;}',
        '#pwa-ios-tip .pwa-ios-close{position:absolute;top:8px;right:10px;background:none;border:none;',
        'font-size:18px;cursor:pointer;color:var(--text-muted,#64748b);line-height:1;}',
        '#pwa-ios-tip .share-icon{display:inline-block;vertical-align:middle;margin:0 3px;}',
        /* Update toast */
        '#pwa-update-toast{position:fixed;bottom:24px;right:24px;z-index:10001;',
        'background:var(--card,#fff);border:1px solid var(--border,#e2e8f0);',
        'border-radius:12px;padding:14px 16px;',
        'box-shadow:0 8px 30px rgba(0,0,0,.15);display:flex;align-items:center;gap:12px;',
        'font-size:13px;color:var(--text,#1e293b);max-width:340px;',
        'transform:translateY(20px);opacity:0;',
        'transition:opacity .3s,transform .3s;pointer-events:none;}',
        '#pwa-update-toast.open{opacity:1;transform:translateY(0);pointer-events:auto;}',
        '#pwa-update-toast .pwa-update-icon{font-size:20px;flex-shrink:0;}',
        '#pwa-update-toast .pwa-update-body{flex:1;line-height:1.4;}',
        '#pwa-update-toast strong{display:block;margin-bottom:2px;}',
        '#pwa-refresh-btn{padding:7px 14px;border:none;border-radius:8px;',
        'background:var(--primary,#1B3A5C);color:#fff;font-size:12px;font-weight:700;',
        'cursor:pointer;font-family:inherit;white-space:nowrap;}',
        '#pwa-refresh-btn:hover{opacity:.88;}',
      ].join('');
      document.head.appendChild(style);
    }

    // ── Register service worker ───────────────────────────────────────────
    function registerSW() {
      if (!('serviceWorker' in navigator)) return;

      navigator.serviceWorker.register('/svms/sw.js', { scope: '/svms/' })
        .then(function (reg) {
          swRegistration = reg;

          // Update already waiting (e.g. user refreshed after background update)
          if (reg.waiting && navigator.serviceWorker.controller) {
            showUpdateToast(reg.waiting);
          }

          // Detect new SW installing
          reg.addEventListener('updatefound', function () {
            var newSW = reg.installing;
            if (!newSW) return;
            newSW.addEventListener('statechange', function () {
              if (newSW.state === 'installed' && navigator.serviceWorker.controller) {
                showUpdateToast(newSW);
              }
            });
          });
        })
        .catch(function (err) {
          console.warn('[PWA] SW registration failed:', err);
        });

      // When SKIP_WAITING succeeds, controller changes → reload
      navigator.serviceWorker.addEventListener('controllerchange', function () {
        window.location.reload();
      });
    }

    // ── Update toast ─────────────────────────────────────────────────────
    function showUpdateToast(waitingSW) {
      injectStyles();
      var existing = document.getElementById('pwa-update-toast');
      if (existing) return; // already shown

      var toast = document.createElement('div');
      toast.id = 'pwa-update-toast';
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      toast.innerHTML =
        '<span class="pwa-update-icon">&#8635;</span>' +
        '<div class="pwa-update-body">' +
        '<strong>Update available</strong>' +
        'A new version of SVMS is ready.' +
        '</div>' +
        '<button id="pwa-refresh-btn" type="button">Refresh&nbsp;now</button>';

      document.body.appendChild(toast);
      requestAnimationFrame(function () { toast.classList.add('open'); });

      document.getElementById('pwa-refresh-btn').addEventListener('click', function () {
        toast.classList.remove('open');
        waitingSW.postMessage({ type: 'SKIP_WAITING' });
      });
    }

    // ── Install bottom-sheet ─────────────────────────────────────────────
    function showInstallSheet() {
      injectStyles();
      if (document.getElementById('pwa-install-sheet')) return;

      var base = (window.BASE_URL || '/svms/');
      var sheet = document.createElement('div');
      sheet.id  = 'pwa-install-sheet';
      sheet.setAttribute('role', 'dialog');
      sheet.setAttribute('aria-label', 'Install SVMS');
      sheet.innerHTML =
        '<div class="pwa-sheet-inner">' +
        '<img src="' + base + 'assets/img/logo.svg" width="48" height="48" alt="SVMS logo">' +
        '<div class="pwa-sheet-body">' +
        '<strong>Install SVMS</strong>' +
        '<p>Add to home screen for faster access &amp; offline use</p>' +
        '</div>' +
        '<div class="pwa-sheet-btns">' +
        '<button class="pwa-btn-later"  id="pwa-later-btn"   type="button">Later</button>' +
        '<button class="pwa-btn-install" id="pwa-install-btn" type="button">Install</button>' +
        '</div></div>';

      document.body.appendChild(sheet);
      requestAnimationFrame(function () { sheet.classList.add('open'); });

      document.getElementById('pwa-install-btn').addEventListener('click', function () {
        hideInstallSheet();
        if (deferredPrompt) {
          deferredPrompt.prompt();
          deferredPrompt.userChoice.then(function (choice) {
            if (choice.outcome === 'accepted') {
              var btn = document.getElementById('install-app-btn');
              if (btn) btn.closest('li') && (btn.closest('li').style.display = 'none');
            }
            deferredPrompt = null;
          });
        }
      });

      document.getElementById('pwa-later-btn').addEventListener('click', function () {
        // Snooze for 14 days
        try {
          localStorage.setItem('svms_install_snoozed',
            String(Date.now() + 14 * 24 * 60 * 60 * 1000));
        } catch (_) {}
        hideInstallSheet();
      });
    }

    function hideInstallSheet() {
      var sheet = document.getElementById('pwa-install-sheet');
      if (!sheet) return;
      sheet.classList.remove('open');
      setTimeout(function () {
        if (sheet.parentNode) sheet.parentNode.removeChild(sheet);
      }, 400);
    }

    // ── iOS Safari install tip ────────────────────────────────────────────
    function showIOSTip() {
      injectStyles();
      if (document.getElementById('pwa-ios-tip')) return;
      try {
        if (localStorage.getItem('svms_ios_tip_shown')) return;
        localStorage.setItem('svms_ios_tip_shown', '1');
      } catch (_) {}

      var tip = document.createElement('div');
      tip.id  = 'pwa-ios-tip';
      tip.setAttribute('role', 'tooltip');
      tip.innerHTML =
        '<button class="pwa-ios-close" id="pwa-ios-close" aria-label="Dismiss">&times;</button>' +
        'Tap&nbsp;' +
        '<svg class="share-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" ' +
        'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>' +
        '<polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/>' +
        '</svg>' +
        '&nbsp;then <strong>&ldquo;Add to Home Screen&rdquo;</strong> to install SVMS.';

      document.body.appendChild(tip);
      requestAnimationFrame(function () { tip.classList.add('open'); });

      document.getElementById('pwa-ios-close').addEventListener('click', function () {
        tip.classList.remove('open');
        setTimeout(function () {
          if (tip.parentNode) tip.parentNode.removeChild(tip);
        }, 350);
      });

      // Auto-dismiss after 8 s
      setTimeout(function () {
        if (tip.parentNode) {
          tip.classList.remove('open');
          setTimeout(function () {
            if (tip.parentNode) tip.parentNode.removeChild(tip);
          }, 350);
        }
      }, 8000);
    }

    // ── Install instructions modal (non-Chrome fallback) ──────────────────
    function showInstallModal() {
      var existing = document.getElementById('pwa-instructions-modal');
      if (existing) { SVMS.modal.open('pwa-instructions-modal'); return; }

      var modal = document.createElement('div');
      modal.id        = 'pwa-instructions-modal';
      modal.className = 'modal-backdrop';
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');
      modal.setAttribute('aria-labelledby', 'pwa-modal-title');
      modal.innerHTML =
        '<div class="modal-box" style="max-width:420px;">' +
        '<div class="modal-header"><h3 id="pwa-modal-title" class="modal-title">Install SVMS</h3>' +
        '<button class="modal-close" data-modal-close aria-label="Close">&times;</button></div>' +
        '<div class="modal-body" style="padding:20px;">' +
        '<p style="font-size:14px;line-height:1.6;margin:0 0 16px;">' +
        'To install SVMS on your device:</p>' +
        '<ol style="font-size:14px;line-height:1.8;padding-left:20px;margin:0;">' +
        '<li>Open this page in <strong>Chrome</strong> or <strong>Edge</strong>.</li>' +
        '<li>Click the browser&rsquo;s menu (<strong>&#8942;</strong>) or address-bar install icon.</li>' +
        '<li>Select <strong>&ldquo;Install app&rdquo;</strong> or <strong>&ldquo;Add to Home screen&rdquo;</strong>.</li>' +
        '<li>On iOS Safari: tap <strong>Share &#8599;</strong> &rarr; <strong>Add to Home Screen</strong>.</li>' +
        '</ol></div>' +
        '<div class="modal-footer"><button type="button" data-modal-close class="btn btn-primary">Got it</button></div>' +
        '</div>';

      document.body.appendChild(modal);
      modal.addEventListener('click', function (e) {
        if (e.target === modal) SVMS.modal.close('pwa-instructions-modal');
      });
      modal.querySelectorAll('[data-modal-close]').forEach(function (b) {
        b.addEventListener('click', function () { SVMS.modal.close('pwa-instructions-modal'); });
      });
      SVMS.modal.open('pwa-instructions-modal');
    }

    // ── Public init ───────────────────────────────────────────────────────
    function init() {
      registerSW();

      // Capture beforeinstallprompt (Chrome/Edge/Android)
      window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;

        // Hide the browser's mini install icon in address bar
        // (we show our own bottom-sheet instead)

        // Check 14-day snooze
        try {
          var snoozedUntil = parseInt(localStorage.getItem('svms_install_snoozed') || '0', 10);
          if (snoozedUntil && Date.now() < snoozedUntil) return;
        } catch (_) {}

        // Show bottom-sheet after a 3-second delay so it doesn't interrupt
        setTimeout(showInstallSheet, 3000);
      });

      // iOS Safari: show tip once
      if (isIOS && !isInStandalone) {
        setTimeout(function () {
          try {
            if (!localStorage.getItem('svms_ios_tip_shown')) showIOSTip();
          } catch (_) { showIOSTip(); }
        }, 4000);
      }

      // appinstalled: hide install UI once installed
      window.addEventListener('appinstalled', function () {
        hideInstallSheet();
        deferredPrompt = null;
        var btn = document.getElementById('install-app-btn');
        if (btn) {
          var li = btn.closest('li') || btn.closest('.dropdown-item');
          if (li) li.style.display = 'none';
        }
      });

      // Wire the header "Install App" button
      document.addEventListener('click', function (e) {
        var btn = e.target.closest('#install-app-btn');
        if (!btn) return;
        e.preventDefault();
        if (deferredPrompt) {
          showInstallSheet(); // re-use sheet which triggers deferredPrompt.prompt()
        } else if (isIOS) {
          showIOSTip();
        } else {
          showInstallModal();
        }
      });
    }

    return { init: init, showInstallSheet: showInstallSheet };
  })();

  function initPWA() {
    _pwa.init();
  }

})();

