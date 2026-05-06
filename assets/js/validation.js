/* ============================================================
   validation.js — Client-side Form Validation v2
   ============================================================ */
(function () {
  'use strict';

  window.SVMSValidation = {};

  const rules = {
    required:  function (val) { return val.trim() !== ''; },
    email:     function (val) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val); },
    // optional variants — only validate if non-empty
    email_opt: function (val) { return val.trim() === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val); },
    cnic_opt:  function (val) { return val.trim() === '' || /^\d{5}-\d{7}-\d{1}$/.test(val); },
    phone:     function (val) { return /^[+\d\s\-()]{7,20}$/.test(val); },
    // Pakistani mobile format: 03XX-XXXXXXX
    phone_pk:  function (val) { return /^03[0-9]{2}-[0-9]{7}$/.test(val); },
    cnic:      function (val) { return /^\d{5}-\d{7}-\d{1}$/.test(val); },
    minLen:    function (val, n) { return val.length >= parseInt(n); },
    maxLen:    function (val, n) { return val.length <= parseInt(n); },
    numeric:   function (val) { return /^\d+$/.test(val); },
    alphaNum:  function (val) { return /^[a-zA-Z0-9]+$/.test(val); }
  };

  const messages = {
    required:  'This field is required.',
    email:     'Please enter a valid email address.',
    email_opt: 'Please enter a valid email address.',
    phone:     'Please enter a valid phone number.',
    phone_pk:  'Format must be 03XX-XXXXXXX (e.g. 0300-1234567).',
    cnic:      'CNIC format: 12345-1234567-1',
    cnic_opt:  'CNIC format: 12345-1234567-1',
    minLen:    'Minimum {n} characters required.',
    maxLen:    'Maximum {n} characters allowed.',
    numeric:   'Only numbers are allowed.',
    alphaNum:  'Only letters and numbers are allowed.'
  };

  /* ── Single-field validation ──────────────────────────────── */
  SVMSValidation.validateField = function (input) {
    var group    = input.closest('.form-group');
    var ruleAttr = input.dataset.rules || '';
    if (!ruleAttr) return true;

    var fieldRules = ruleAttr.split('|');
    var valid      = true;
    var errorMsg   = '';

    for (var i = 0; i < fieldRules.length; i++) {
      var parts = fieldRules[i].split(':');
      var rule  = parts[0];
      var param = parts[1];

      if (rules[rule] && !rules[rule](input.value, param)) {
        valid    = false;
        errorMsg = (messages[rule] || 'Invalid value.').replace('{n}', param);
        break;
      }
    }

    input.classList.toggle('is-valid',   valid);
    input.classList.toggle('is-invalid', !valid);

    if (group) {
      var fb = group.querySelector('.invalid-feedback');
      if (!fb) {
        fb = document.createElement('div');
        fb.className = 'invalid-feedback';
        // Insert after the input (or its wrapper if present)
        var refEl = input.parentNode === group ? input : input.parentNode;
        refEl.parentNode.insertBefore(fb, refEl.nextSibling);
      }
      fb.textContent = valid ? '' : errorMsg;
      fb.style.display = valid ? 'none' : 'flex';
    }

    return valid;
  };

  /* ── Whole-form validation ────────────────────────────────── */
  SVMSValidation.validateForm = function (form) {
    var allValid = true;
    form.querySelectorAll('[data-rules]').forEach(function (input) {
      if (!SVMSValidation.validateField(input)) allValid = false;
    });

    if (!allValid) {
      var first = form.querySelector('.is-invalid');
      if (first) {
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        first.focus();
        // shake
        first.classList.remove('input-shake');
        void first.offsetWidth; // reflow to restart animation
        first.classList.add('input-shake');
        setTimeout(function () { first.classList.remove('input-shake'); }, 450);
      }
      if (window.SVMS && SVMS.toast) {
        SVMS.toast('Please fix the highlighted fields.', 'warning');
      }
    }
    return allValid;
  };

  /* ── Auto-validate on blur ─────────────────────────────────── */
  document.addEventListener('blur', function (e) {
    if (e.target.dataset && e.target.dataset.rules) SVMSValidation.validateField(e.target);
  }, true);

  /* ── Auto-validate on submit if data-validate="true" ──────── */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.dataset.validate === 'true') {
      if (!SVMSValidation.validateForm(form)) e.preventDefault();
    }
  });

  /* ── CNIC auto-format: 12345-1234567-1 ────────────────────── */
  document.addEventListener('input', function (e) {
    if (e.target.dataset.format === 'cnic') {
      var pos = e.target.selectionStart;
      var raw = e.target.value.replace(/\D/g, '').substring(0, 13);
      var fmt = raw;
      if (raw.length > 5)  fmt = raw.slice(0, 5)  + '-' + raw.slice(5);
      if (raw.length > 12) fmt = fmt.slice(0, 13)  + '-' + raw.slice(12);
      e.target.value = fmt;
    }
  });

  /* ── Pakistani phone auto-format: 03XX-XXXXXXX ────────────── */
  document.addEventListener('input', function (e) {
    if (e.target.dataset.format === 'phone_pk') {
      var raw = e.target.value.replace(/\D/g, '').substring(0, 11);
      if (raw.length > 4) {
        e.target.value = raw.slice(0, 4) + '-' + raw.slice(4);
      } else {
        e.target.value = raw;
      }
    }
    // Legacy generic phone strip
    if (e.target.dataset.format === 'phone') {
      e.target.value = e.target.value.replace(/[^+\d\s\-()]/g, '');
    }
  });

  /* ── Character counter for [data-maxlen] inputs ───────────── */
  document.addEventListener('input', function (e) {
    var maxLen = e.target.dataset.maxlen;
    if (!maxLen) return;
    var counter = e.target.parentNode.querySelector('.char-counter');
    if (counter) {
      var remaining = parseInt(maxLen) - e.target.value.length;
      counter.textContent = remaining;
      counter.style.color = remaining < 20 ? 'var(--warning)' : '';
    }
  });

  /* ── Inject shake keyframes if not already present ─────────── */
  if (!document.getElementById('svms-validation-styles')) {
    var style = document.createElement('style');
    style.id  = 'svms-validation-styles';
    style.textContent = [
      '@keyframes input-shake {',
      '  0%,100%{transform:translateX(0)}',
      '  20%{transform:translateX(-5px)}',
      '  40%{transform:translateX(5px)}',
      '  60%{transform:translateX(-3px)}',
      '  80%{transform:translateX(3px)}',
      '}',
      '.input-shake { animation: input-shake 0.4s ease; }',
      '.invalid-feedback { font-size:12px; color:var(--danger,#ef4444); margin-top:4px; display:flex; align-items:center; gap:4px; }'
    ].join('\n');
    document.head.appendChild(style);
  }

})();
