/* ============================================================
   kiosk.js — Self-Service Kiosk Logic
   Handles: clock, idle timer, cursor-hide, PIN modal,
            page transitions, QWERTY keyboard, numpad.
   ============================================================ */
(function () {
  'use strict';

  /* ── Config ───────────────────────────────────────────────── */
  var IDLE_SECONDS   = 60;   // redirect to home after this many idle seconds
  var WARN_SECONDS   = 5;    // countdown overlay duration
  var CURSOR_SECONDS = 8;    // hide cursor after this many idle seconds
  var HOME_URL       = (window.KIOSK_BASE || '/') + 'kiosk/';

  /* ── Clock ────────────────────────────────────────────────── */
  function updateClock() {
    var el = document.getElementById('kiosk-clock');
    if (!el) return;
    var now  = new Date();
    var time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    var date = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
    el.innerHTML = '<strong>' + time + '</strong><br><span style="font-size:11px;">' + date + '</span>';
  }

  /* ── Idle / Countdown overlay ─────────────────────────────── */
  var idleTimer, cursorTimer, warnInterval, warnCount;
  var overlay = null;

  function getOverlay() {
    if (!overlay) overlay = document.getElementById('kiosk-idle-overlay');
    return overlay;
  }

  function resetActivity() {
    clearTimeout(idleTimer);
    clearTimeout(cursorTimer);
    document.body.classList.remove('kiosk-cursor-hidden');

    var ov = getOverlay();
    if (ov) { ov.classList.remove('visible'); clearInterval(warnInterval); }

    // Cursor hide
    cursorTimer = setTimeout(function () {
      document.body.classList.add('kiosk-cursor-hidden');
    }, CURSOR_SECONDS * 1000);

    // Idle → show countdown
    idleTimer = setTimeout(function () {
      showIdleWarning();
    }, (IDLE_SECONDS - WARN_SECONDS) * 1000);
  }

  function showIdleWarning() {
    var ov = getOverlay();
    if (!ov) {
      // Instant redirect if no overlay element
      window.location.href = HOME_URL;
      return;
    }
    warnCount = WARN_SECONDS;
    var cntEl = ov.querySelector('.kiosk-idle-countdown');
    if (cntEl) cntEl.textContent = warnCount;
    ov.classList.add('visible');

    warnInterval = setInterval(function () {
      warnCount--;
      if (cntEl) cntEl.textContent = warnCount;
      if (warnCount <= 0) {
        clearInterval(warnInterval);
        window.location.href = HOME_URL;
      }
    }, 1000);
  }

  function dismissIdleWarning() {
    var ov = getOverlay();
    if (ov) ov.classList.remove('visible');
    clearInterval(warnInterval);
    resetActivity();
  }

  function initIdleReset() {
    var events = ['click', 'touchstart', 'keydown', 'mousemove', 'scroll'];
    events.forEach(function (ev) {
      document.addEventListener(ev, resetActivity, { passive: true });
    });
    var ov = getOverlay();
    if (ov) {
      ov.addEventListener('click', dismissIdleWarning);
      ov.addEventListener('touchstart', dismissIdleWarning, { passive: true });
    }
    resetActivity();
  }

  /* ── Page transition helper ──────────────────────────────────
     Call: KIOSK.navigate(url)  — slides out current page then navigates
  ─────────────────────────────────────────────────────────── */
  function navigate(url) {
    var card = document.querySelector('.kiosk-card');
    if (card && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      card.style.animation = 'kiosk-slide-out .35s ease-in-out forwards';
      setTimeout(function () { window.location.href = url; }, 320);
    } else {
      window.location.href = url;
    }
  }

  /* ── Numeric Pad ──────────────────────────────────────────── */
  function initNumpad(outputId) {
    var output = document.getElementById(outputId);
    if (!output) return;

    document.querySelectorAll('.kiosk-key[data-digit]').forEach(function (key) {
      key.addEventListener('click', function () {
        if (output.value.length < 15) {
          output.value += this.dataset.digit;
          output.dispatchEvent(new Event('input'));
        }
      });
    });

    document.querySelectorAll('.kiosk-key[data-action="del"]').forEach(function (key) {
      key.addEventListener('click', function () {
        output.value = output.value.slice(0, -1);
        output.dispatchEvent(new Event('input'));
      });
    });

    document.querySelectorAll('.kiosk-key[data-action="clear"]').forEach(function (key) {
      key.addEventListener('click', function () {
        output.value = '';
        output.dispatchEvent(new Event('input'));
      });
    });
  }

  /* ── On-screen QWERTY keyboard ───────────────────────────────
     Attaches to a target input by ID.
     KIOSK.initKeyboard('input-id', containerEl)
  ─────────────────────────────────────────────────────────── */
  var KB_ROWS_EN = [
    ['Q','W','E','R','T','Y','U','I','O','P'],
    ['A','S','D','F','G','H','J','K','L'],
    ['SHIFT','Z','X','C','V','B','N','M','⌫'],
    ['123','@','.','SPACE','-','_','OK']
  ];
  var KB_ROWS_NUM = [
    ['1','2','3','4','5','6','7','8','9','0'],
    ['!','@','#','$','%','^','&','*','(',')'],
    ['-','_','+','=','[',']','{','}','|','\\'],
    ['ABC',',','.','/','SPACE','\'','"','OK']
  ];
  // Urdu Phonetic mapping (common Pakistan phonetic layout)
  var KB_ROWS_UR = [
    ['ق','و','ع','ر','ت','ے','ء','ی','ہ','پ'],
    ['ا','س','د','ف','گ','ح','ج','ک','ل'],
    ['SHIFT','ز','ش','ص','ث','ب','ن','م','⌫'],
    ['123','،','۔','SPACE','ٹ','ڈ','ڑ','OK']
  ];

  function buildKeyboard(containerId, targetInputId, lang) {
    var container = document.getElementById(containerId);
    var target    = document.getElementById(targetInputId);
    if (!container || !target) return;

    var rows    = lang === 'ur' ? KB_ROWS_UR : KB_ROWS_EN;
    var shifted = false;
    var mode    = 'alpha'; // 'alpha' | 'num'

    function render() {
      container.innerHTML = '';
      container.className = 'kiosk-keyboard';
      var activeRows = (mode === 'num') ? KB_ROWS_NUM : rows;

      activeRows.forEach(function (row) {
        var rowEl = document.createElement('div');
        rowEl.className = 'kiosk-kb-row';
        row.forEach(function (key) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'kiosk-kb-key';

          var display = (shifted && key.length === 1 && key !== ' ') ? key.toUpperCase() : key.toLowerCase();
          if (['SHIFT','⌫','SPACE','OK','123','ABC','@','-','_',',','.'].indexOf(key) !== -1) display = key;

          btn.textContent = display;

          if (key === 'SPACE') { btn.classList.add('kiosk-kb-key-xl'); btn.textContent = ''; }
          else if (['SHIFT','⌫','OK','123','ABC'].indexOf(key) !== -1) { btn.classList.add('kiosk-kb-key-wide', 'kiosk-kb-key-mod'); }

          btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (key === '⌫') {
              target.value = target.value.slice(0, -1);
            } else if (key === 'SPACE') {
              target.value += ' ';
            } else if (key === 'SHIFT') {
              shifted = !shifted; render(); return;
            } else if (key === 'OK') {
              target.dispatchEvent(new Event('kb-confirm'));
              return;
            } else if (key === '123') {
              mode = 'num'; render(); return;
            } else if (key === 'ABC') {
              mode = 'alpha'; render(); return;
            } else {
              target.value += (shifted && key.length === 1) ? key.toUpperCase() : (mode === 'num' ? key : key.toLowerCase());
              if (shifted) { shifted = false; render(); return; }
            }
            target.dispatchEvent(new Event('input'));
            target.focus();
          });

          rowEl.appendChild(btn);
        });
        container.appendChild(rowEl);
      });
    }

    render();
    return { setLang: function(l) { rows = l === 'ur' ? KB_ROWS_UR : KB_ROWS_EN; render(); } };
  }

  /* ── PIN Modal ────────────────────────────────────────────── */
  function initPinModal(correctPinFallback) {
    var staffBtn = document.getElementById('kiosk-staff-btn');
    var pinModal = document.getElementById('kiosk-pin-modal');
    if (!staffBtn || !pinModal) return;

    var pinValue  = '';
    var pinDots   = pinModal.querySelectorAll('.kiosk-pin-dot');
    var pinKeys   = pinModal.querySelectorAll('.kiosk-key[data-digit]');
    var pinDel    = pinModal.querySelector('.kiosk-key[data-action="del"]');
    var pinCancel = pinModal.querySelector('[data-action="pin-cancel"]');
    var pinError  = pinModal.querySelector('.pin-error');

    function updateDots() {
      pinDots.forEach(function (d, i) {
        d.classList.toggle('filled', i < pinValue.length);
      });
    }

    staffBtn.addEventListener('click', function () {
      pinValue = ''; updateDots();
      if (pinError) pinError.textContent = '';
      pinModal.classList.add('open');
    });

    if (pinCancel) {
      pinCancel.addEventListener('click', function () {
        pinModal.classList.remove('open');
        pinValue = '';
      });
    }

    if (pinDel) {
      pinDel.addEventListener('click', function () {
        pinValue = pinValue.slice(0, -1);
        updateDots();
      });
    }

    pinKeys.forEach(function (key) {
      key.addEventListener('click', function () {
        if (pinValue.length < 4) {
          pinValue += this.dataset.digit;
          updateDots();
          if (pinValue.length === 4) {
            // Submit
            submitPin(pinValue);
          }
        }
      });
    });

    function submitPin(pin) {
      var base = window.KIOSK_BASE || '/';
      fetch(base + 'kiosk/exit.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'pin=' + encodeURIComponent(pin) + '&csrf_token=' + encodeURIComponent(window.KIOSK_CSRF || '')
      })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) {
          window.location.href = base + 'pages/login.php';
        } else {
          if (pinError) pinError.textContent = 'Incorrect PIN. Try again.';
          pinValue = ''; updateDots();
        }
      })
      .catch(function () {
        if (pinError) pinError.textContent = 'Error. Please try again.';
        pinValue = ''; updateDots();
      });
    }
  }

  /* ── Language toggle ──────────────────────────────────────── */
  function initLangToggle() {
    document.querySelectorAll('.kiosk-lang-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var lang = this.dataset.lang || 'en';
        document.cookie = 'svms_lang=' + lang + ';path=/;max-age=31536000';
        window.location.reload();
      });
    });
  }

  /* ── Animate-in on load ───────────────────────────────────── */
  function animateIn() {
    var card = document.querySelector('.kiosk-card');
    if (card && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      card.classList.add('kiosk-animate-in');
    }
  }

  /* ── Public API ───────────────────────────────────────────── */
  window.KIOSK = {
    navigate:       navigate,
    initNumpad:     initNumpad,
    buildKeyboard:  buildKeyboard,
    initPinModal:   initPinModal,
    initIdleReset:  initIdleReset,
    initLangToggle: initLangToggle,
    animateIn:      animateIn,
    resetActivity:  resetActivity,
  };

  /* ── Auto-init on DOMContentLoaded ───────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    updateClock();
    setInterval(updateClock, 1000);
    initIdleReset();
    initLangToggle();
    animateIn();
  });

})();

(function () {
  'use strict';

  // ── Kiosk Clock ───────────────────────────────────────────
  function updateClock() {
    const el = document.getElementById('kiosk-clock');
    if (!el) return;
    const now  = new Date();
    const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    const date = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
    el.innerHTML = '<strong>' + time + '</strong><br><span>' + date + '</span>';
  }

  // ── Idle Screen ───────────────────────────────────────────
  let idleTimer;

  function resetIdleTimer() {
    clearTimeout(idleTimer);
    const idle = document.getElementById('kiosk-idle');
    if (idle) idle.style.display = 'none';
    idleTimer = setTimeout(function () {
      const idle = document.getElementById('kiosk-idle');
      if (idle) idle.style.display = 'flex';
    }, 60000); // 60s idle
  }

  function initIdleScreen() {
    document.addEventListener('click',       resetIdleTimer);
    document.addEventListener('touchstart',  resetIdleTimer);
    document.addEventListener('keydown',     resetIdleTimer);

    const idle = document.getElementById('kiosk-idle');
    if (idle) {
      idle.addEventListener('click', function () {
        this.style.display = 'none';
        resetIdleTimer();
        window.location.href = (window.KIOSK_BASE || '/') + 'kiosk/';
      });
    }

    resetIdleTimer();
  }

  // ── QR / Badge Scan ──────────────────────────────────────
  function initBadgeScan() {
    const input = document.getElementById('badge-scan-input');
    if (!input) return;

    let buffer = '';
    let scanTimer;

    document.addEventListener('keypress', function (e) {
      if (e.key === 'Enter') {
        if (buffer.length >= 4) {
          processBarcode(buffer.trim());
        }
        buffer = '';
        clearTimeout(scanTimer);
        return;
      }
      buffer += e.key;
      clearTimeout(scanTimer);
      scanTimer = setTimeout(function () { buffer = ''; }, 500);
    });

    if (input) {
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          processBarcode(this.value.trim());
          this.value = '';
        }
      });
    }
  }

  function processBarcode(code) {
    if (!code) return;
    SVMS.fetch((window.KIOSK_BASE || window.BASE_URL || '/') + 'api/scan.php', {
      method: 'POST',
      body: JSON.stringify({ badge: code })
    })
      .then(function (data) {
        if (data.success) {
          window.location.href = (window.KIOSK_BASE || window.BASE_URL || '/') + 'kiosk/step_confirm.php?token=' + encodeURIComponent(data.token);
        } else {
          SVMS.toast(data.message || 'Badge not found.', 'error');
        }
      })
      .catch(function () {
        SVMS.toast('Scan error. Please try again.', 'error');
      });
  }

  // ── OTP digit auto-advance ────────────────────────────────
  function initOtpInputs() {
    const inputs = document.querySelectorAll('.otp-input');
    if (!inputs.length) return;

    inputs.forEach(function (input, idx) {
      input.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(-1);
        if (this.value && idx < inputs.length - 1) {
          inputs[idx + 1].focus();
        }
      });

      input.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !this.value && idx > 0) {
          inputs[idx - 1].focus();
        }
      });

      input.addEventListener('paste', function (e) {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        inputs.forEach(function (inp, i) {
          inp.value = pasted[i] || '';
        });
        const last = Math.min(pasted.length, inputs.length) - 1;
        if (last >= 0) inputs[last].focus();
      });
    });
  }

  // ── Step progress helper ──────────────────────────────────
  function markStep(stepNum) {
    document.querySelectorAll('.kiosk-step').forEach(function (el, idx) {
      const num = idx + 1;
      el.classList.toggle('active', num === stepNum);
      el.classList.toggle('done',   num < stepNum);
    });
    document.querySelectorAll('.kiosk-step-line').forEach(function (el, idx) {
      el.classList.toggle('done', idx + 1 < stepNum);
    });
  }

  window.KioskStep = { mark: markStep };

  document.addEventListener('DOMContentLoaded', function () {
    updateClock();
    setInterval(updateClock, 1000);
    initIdleScreen();
    initBadgeScan();
    initOtpInputs();
  });

})();
