<?php
/**
 * includes/partials/confirm_modal.php
 *
 * NOTE: The #confirm-modal HTML already lives in includes/footer.php and is
 * present on every admin page. This file ONLY provides the JS helper function
 * `window.confirmModal()` so that any page can trigger the shared modal.
 *
 * Usage:
 *   confirmModal(
 *     'Are you sure you want to delete this visitor?',  // message
 *     function() { /* onConfirm callback * / },
 *     function() { /* onCancel callback (optional) * / }
 *   );
 *
 * The confirm button text can be customised via a fourth argument:
 *   confirmModal('Delete this record?', onConfirm, null, { confirmText: 'Delete', danger: true });
 *
 * Options object (4th argument):
 *   confirmText  {string}   Label on the confirm button   (default "Confirm")
 *   cancelText   {string}   Label on the cancel button    (default "Cancel")
 *   title        {string}   Modal title                   (default "Confirm Action")
 *   danger       {bool}     Use .btn-danger on confirm    (default true)
 *   warning      {bool}     Use .btn-warning on confirm   (default false)
 */
?>
<script>
(function () {
  'use strict';

  /**
   * @param {string}        message
   * @param {Function}      onConfirm
   * @param {Function|null} [onCancel]
   * @param {Object}        [opts]
   */
  window.confirmModal = function (message, onConfirm, onCancel, opts) {
    opts = opts || {};

    var backdrop = document.getElementById('confirm-modal');
    if (!backdrop) {
      // Graceful degradation: no modal in DOM — fall back to native confirm
      if (window.confirm(message)) {
        if (typeof onConfirm === 'function') onConfirm();
      } else {
        if (typeof onCancel === 'function') onCancel();
      }
      return;
    }

    /* ── Populate content ─────────────────────────────── */
    var msgEl    = backdrop.querySelector('.confirm-message');
    var titleEl  = backdrop.querySelector('.modal-title');
    var yesBtn   = document.getElementById('confirm-yes');
    var noBtn    = document.getElementById('confirm-no');

    if (msgEl)   msgEl.textContent    = message;
    if (titleEl) titleEl.textContent  = opts.title       || 'Confirm Action';
    if (yesBtn)  yesBtn.textContent   = opts.confirmText || 'Confirm';
    if (noBtn)   noBtn.textContent    = opts.cancelText  || 'Cancel';

    // Button colour
    if (yesBtn) {
      yesBtn.className = 'btn ' + (opts.warning ? 'btn-warning' : 'btn-danger');
    }

    /* ── Show modal ───────────────────────────────────── */
    backdrop.setAttribute('aria-hidden', 'false');
    backdrop.classList.add('is-open');
    if (yesBtn) yesBtn.focus();

    /* ── One-time handlers ────────────────────────────── */
    function cleanup() {
      backdrop.setAttribute('aria-hidden', 'true');
      backdrop.classList.remove('is-open');
      yesBtn && yesBtn.removeEventListener('click', handleYes);
      noBtn  && noBtn.removeEventListener('click',  handleNo);
      document.removeEventListener('keydown', handleKey);
      backdrop.querySelectorAll('[data-modal-close]').forEach(function (b) {
        b.removeEventListener('click', handleNo);
      });
    }

    function handleYes() {
      cleanup();
      if (typeof onConfirm === 'function') onConfirm();
    }

    function handleNo() {
      cleanup();
      if (typeof onCancel === 'function') onCancel();
    }

    function handleKey(e) {
      if (e.key === 'Escape') handleNo();
      if (e.key === 'Enter' && document.activeElement === yesBtn) handleYes();
    }

    yesBtn && yesBtn.addEventListener('click', handleYes, { once: true });
    noBtn  && noBtn.addEventListener('click',  handleNo,  { once: true });
    document.addEventListener('keydown', handleKey);
    backdrop.querySelectorAll('[data-modal-close]').forEach(function (b) {
      b.addEventListener('click', handleNo, { once: true });
    });

    // Click on backdrop itself closes
    backdrop.addEventListener('click', function backdropClick(e) {
      if (e.target === backdrop) {
        handleNo();
        backdrop.removeEventListener('click', backdropClick);
      }
    });
  };

  /* ── Auto-wire any element with data-confirm ──────── */
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (!el) return;
    e.preventDefault();
    var msg  = el.dataset.confirm || 'Are you sure?';
    var href = el.href || null;
    var form = el.closest('form');

    confirmModal(msg, function () {
      if (href) {
        window.location.href = href;
      } else if (form && el.type === 'submit') {
        // Remove listener to avoid re-triggering, then submit
        form.submit();
      } else if (el.tagName === 'BUTTON' || el.tagName === 'INPUT') {
        el.closest('form') && el.closest('form').submit();
      }
    });
  });

})();
</script>
