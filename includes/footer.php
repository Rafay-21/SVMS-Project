<?php
/**
 * includes/footer.php
 * Closes the .main wrapper, outputs footer and JS.
 */
?>
</main><!-- /#main-content -->

<!-- ── Site Footer ──────────────────────────────────────────── -->
<footer style="
  margin-left: var(--sidebar-width);
  padding: 16px 24px;
  border-top: 1px solid var(--border);
  font-size: var(--text-xs);
  color: var(--text-muted);
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--card);
  transition: margin var(--transition-slow);
" id="site-footer">
  <span>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</span>
  <span>Version 2.0</span>
</footer>

<!-- ── Confirm Modal ─────────────────────────────────────────── -->
<div class="modal-backdrop" id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title" aria-hidden="true">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h5 class="modal-title" id="confirm-modal-title">Confirm Action</h5>
      <button class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <div class="confirm-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <p class="confirm-message"></p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" id="confirm-no">Cancel</button>
      <button class="btn btn-danger"    id="confirm-yes">Confirm</button>
    </div>
  </div>
</div>

<!-- ── JavaScript ─────────────────────────────────────────────── -->
<script src="<?= BASE_URL ?>assets/js/theme.js"></script>
<script src="<?= BASE_URL ?>assets/js/i18n.js"></script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script src="<?= BASE_URL ?>assets/js/validation.js"></script>
<script src="<?= BASE_URL ?>assets/js/notifications.js"></script>
<?php require_once __DIR__ . '/partials/confirm_modal.php'; ?>
<?php if (!empty($page_extra_js)): ?>
  <?php foreach ((array)$page_extra_js as $_js): ?>
  <script src="<?= BASE_URL ?>assets/js/<?= e($_js) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Service Worker registration -->
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register((window.BASE_URL || '/') + 'sw.js').catch(function(){});
  }
</script>

<!-- RTL footer adjustment -->
<script>
  (function(){
    var dir = document.documentElement.getAttribute('dir');
    if (dir === 'rtl') {
      var f = document.getElementById('site-footer');
      if (f) { f.style.marginLeft = '0'; f.style.marginRight = 'var(--sidebar-width)'; }
    }
  })();
</script>

</body>
</html>
