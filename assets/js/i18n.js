/* ============================================================
   i18n.js — Client-side Internationalisation v2.0
   Works with server-rendered translations from i18n.php.
   Supports {{name}} template syntax (matching PHP t()).
   Exposes: window.__i18n (primary) + window.SVMSi18n (compat)
   ============================================================ */
(function () {
  'use strict';

  var strings = window.SVMS_LANG_STRINGS || {};

  function translate(key, replacements) {
    var str = (key in strings) ? strings[key] : key;
    if (replacements && typeof replacements === 'object') {
      Object.keys(replacements).forEach(function (k) {
        str = str.replace(new RegExp('\\{\\{' + k + '\\}\\}', 'g'), replacements[k]);
      });
    }
    return str;
  }

  function translatePlural(key, count, replacements) {
    var r = replacements || {};
    r.count = count;
    return translate(count === 1 ? key + '.singular' : key + '.plural', r);
  }

  /* Primary API — window.__i18n */
  window.__i18n = {
    t:        translate,
    tp:       translatePlural,
    lang:     function () { return document.documentElement.getAttribute('lang') || 'en'; },
    isRtl:    function () { return document.documentElement.getAttribute('dir') === 'rtl'; },
    strings:  strings
  };

  /* Backward-compat — window.SVMSi18n */
  window.SVMSi18n = {
    strings: strings,
    t: function (key, replacements) {
      // Support old :variable syntax as well as {{variable}}
      var str = translate(key, replacements);
      if (replacements && typeof replacements === 'object') {
        Object.keys(replacements).forEach(function (k) {
          str = str.replace(new RegExp(':' + k, 'g'), replacements[k]);
        });
      }
      return str;
    },
    currentLang: window.__i18n.lang,
    isRtl:       window.__i18n.isRtl
  };

  /* RTL body class */
  document.addEventListener('DOMContentLoaded', function () {
    if (window.__i18n.isRtl()) {
      document.body.classList.add('rtl-layout');
    }
  });

  /* Language switcher persistence via AJAX */
  document.addEventListener('DOMContentLoaded', function () {
    var items = document.querySelectorAll('[data-lang-btn]');
    for (var i = 0; i < items.length; i++) {
      (function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var lang = btn.getAttribute('data-lang-btn');
          var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
          // Save to server + reload
          var url = (window.BASE_URL || '') + 'api/set_preference.php';
          fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ csrf_token: csrf, key: 'language', value: lang })
          }).then(function () {
            // Append ?lang= to current URL then reload so PHP re-renders in new lang
            var u = new URL(window.location.href);
            u.searchParams.set('lang', lang);
            window.location.href = u.toString();
          }).catch(function () {
            var u = new URL(window.location.href);
            u.searchParams.set('lang', lang);
            window.location.href = u.toString();
          });
        });
      })(items[i]);
    }
  });

})();
