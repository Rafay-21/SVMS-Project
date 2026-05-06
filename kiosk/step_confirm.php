<?php
/**
 * kiosk/step_confirm.php
 * Step 2 (checkout): Show visitor identity card; confirm "Yes, Check Out".
 * POSTs to api/kiosk_checkout.php via AJAX on confirm.
 */
require_once __DIR__ . '/kiosk_boot.php';

$visitor_id = (int)($_GET['visitor_id'] ?? 0);
$action     = ($_GET['action'] ?? 'checkout') === 'checkin' ? 'checkin' : 'checkout';
$lang       = preg_replace('/[^a-z]/', '', $_COOKIE['svms_lang'] ?? DEFAULT_LANG);
$lang       = in_array($lang, ['en','ur']) ? $lang : DEFAULT_LANG;

if ($visitor_id < 1) {
    header('Location: ' . BASE_URL . 'kiosk/index.php');
    exit;
}

// Fetch visitor + active visit
$visitor = query_one(
    "SELECT v.id, v.full_name, v.phone, v.cnic, v.photo_path, v.badge_number,
            vl.id AS visit_id, vl.check_in_time, vl.purpose, vl.person_to_meet,
            d.name AS dept_name
     FROM visitors v
     LEFT JOIN visit_log vl ON vl.visitor_id = v.id AND vl.status = 'checked_in'
     LEFT JOIN departments d ON d.id = vl.department_id
     WHERE v.id = ?",
    [$visitor_id]
);

if (!$visitor) {
    header('Location: ' . BASE_URL . 'kiosk/step_identify.php?action=' . $action . '&err=notfound');
    exit;
}

$hasActiveVisit = !empty($visitor['visit_id']);

kiosk_head($action === 'checkout' ? 'Check Out' : 'Confirm', true,
           BASE_URL . 'kiosk/step_identify.php?action=' . $action);
?>

<div class="kiosk-card kiosk-animate-in" style="max-width:600px;text-align:center;">

  <!-- Step indicator -->
  <div class="kiosk-steps" style="margin-bottom:28px;">
    <div class="kiosk-step"><div class="kiosk-step-circle done">&#10003;</div><div class="kiosk-step-label">Identify</div></div>
    <div class="kiosk-step-connector"></div>
    <div class="kiosk-step"><div class="kiosk-step-circle active">2</div><div class="kiosk-step-label">Confirm</div></div>
    <div class="kiosk-step-connector"></div>
    <div class="kiosk-step"><div class="kiosk-step-circle">3</div><div class="kiosk-step-label">Done</div></div>
  </div>

  <h2 style="font-size:clamp(20px,3vw,28px);font-weight:800;color:#1a3c5e;margin-bottom:6px;">
    <?= $lang === 'ur' ? 'کیا یہ آپ ہیں؟' : 'Is this you?' ?>
  </h2>
  <p style="font-size:15px;color:#64748b;margin-bottom:24px;">
    <?= $lang === 'ur' ? 'اپنی شناخت تصدیق کریں' : 'Please confirm your identity before checking out.' ?>
  </p>

  <!-- Visitor card -->
  <div class="kiosk-visitor-card" style="margin-bottom:28px;text-align:left;gap:20px;padding:20px 24px;">
    <?php if ($visitor['photo_path']): ?>
    <img src="<?= BASE_URL ?>assets/uploads/<?= htmlspecialchars($visitor['photo_path'], ENT_QUOTES, 'UTF-8') ?>"
         class="kiosk-avatar" style="width:80px;height:80px;" alt="">
    <?php else:
      $ini = strtoupper(substr($visitor['full_name'] ?? '?', 0, 1)); ?>
    <div class="kiosk-avatar" style="width:80px;height:80px;font-size:30px;flex-shrink:0;">
      <?= htmlspecialchars($ini, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>
    <div style="flex:1;">
      <div style="font-size:24px;font-weight:800;color:#1a3c5e;margin-bottom:4px;">
        <?= htmlspecialchars($visitor['full_name'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php if ($visitor['phone']): ?>
      <div style="font-size:14px;color:#64748b;">
        <i class="bi bi-telephone" style="margin-right:4px;"></i><?= htmlspecialchars($visitor['phone'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>
      <?php if ($visitor['badge_number']): ?>
      <div style="font-size:13px;color:#94a3b8;font-family:monospace;margin-top:2px;">
        <?= htmlspecialchars($visitor['badge_number'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>

      <?php if ($hasActiveVisit): ?>
      <div style="margin-top:10px;padding:10px 14px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;">
        <div style="font-size:13px;font-weight:700;color:#166534;margin-bottom:4px;">
          <i class="bi bi-check-circle-fill" style="margin-right:4px;"></i>Active Visit
        </div>
        <?php if ($visitor['check_in_time']): ?>
        <div style="font-size:13px;color:#64748b;">
          Checked in: <?= htmlspecialchars(format_datetime($visitor['check_in_time']), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>
        <?php if ($visitor['person_to_meet']): ?>
        <div style="font-size:13px;color:#64748b;">
          Meeting: <?= htmlspecialchars($visitor['person_to_meet'], ENT_QUOTES, 'UTF-8') ?>
          <?= $visitor['dept_name'] ? '— ' . htmlspecialchars($visitor['dept_name'], ENT_QUOTES, 'UTF-8') : '' ?>
        </div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div style="margin-top:10px;padding:10px 14px;background:#fef9c3;border:1.5px solid #fde047;border-radius:10px;font-size:13px;color:#713f12;">
        <i class="bi bi-exclamation-triangle-fill" style="margin-right:4px;"></i>
        No active visit found for this visitor.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$hasActiveVisit && $action === 'checkout'): ?>
  <!-- No active visit — send back home -->
  <a href="<?= BASE_URL ?>kiosk/index.php"
     class="kiosk-btn kiosk-btn-secondary" style="min-height:70px;padding:16px 36px;font-size:18px;">
    &#8592; <?= $lang === 'ur' ? 'واپس جائیں' : 'Back to Home' ?>
  </a>
  <?php else: ?>
  <!-- Action buttons -->
  <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>kiosk/step_identify.php?action=<?= $action ?>"
       class="kiosk-btn kiosk-btn-secondary" style="min-height:70px;padding:16px 28px;font-size:17px;">
      <i class="bi bi-x-lg" style="color:#1a3c5e;font-size:22px;"></i>
      <span style="color:#1a3c5e;"><?= $lang === 'ur' ? 'یہ میں نہیں' : "Not Me" ?></span>
    </a>
    <button id="confirm-btn" class="kiosk-btn kiosk-btn-<?= $action === 'checkout' ? 'danger' : 'primary' ?>"
            style="min-height:70px;padding:16px 36px;font-size:18px;">
      <i class="bi bi-<?= $action === 'checkout' ? 'box-arrow-right' : 'check-circle-fill' ?>"
         style="font-size:28px;"></i>
      <span><?= $action === 'checkout'
        ? ($lang === 'ur' ? 'چیک آؤٹ کریں' : 'Yes, Check Out')
        : ($lang === 'ur' ? 'جاری رکھیں' : 'Yes, Continue') ?></span>
    </button>
  </div>
  <div id="confirm-status" style="font-size:14px;color:#94a3b8;margin-top:14px;min-height:18px;"></div>
  <div id="confirm-error"  style="font-size:14px;color:#ef4444;margin-top:4px;min-height:18px;"></div>
  <?php endif; ?>

</div>

<script>
(function() {
  var btn      = document.getElementById('confirm-btn');
  if (!btn) return;
  var statusEl = document.getElementById('confirm-status');
  var errorEl  = document.getElementById('confirm-error');
  var base     = window.KIOSK_BASE || '/svms/';
  var csrf     = window.KIOSK_CSRF || '';
  var action   = <?= json_encode($action) ?>;
  var visitId  = <?= json_encode((int)($visitor['visit_id'] ?? 0)) ?>;
  var visitorId= <?= json_encode($visitor_id) ?>;

  btn.addEventListener('click', function() {
    btn.disabled = true;
    if (statusEl) statusEl.textContent = action === 'checkout' ? 'Processing check-out\u2026' : 'Processing\u2026';

    if (action === 'checkout') {
      fetch(base + 'api/kiosk_checkout.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ csrf_token: csrf, visit_log_id: visitId })
      })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.ok) {
          var url = base + 'kiosk/step_done.php?visit_log_id=' + visitId + '&action=checkout';
          if (window.KIOSK) KIOSK.navigate(url); else window.location.href = url;
        } else {
          if (errorEl) errorEl.textContent = d.error || 'Check-out failed. Please see reception.';
          btn.disabled = false;
          if (statusEl) statusEl.textContent = '';
        }
      })
      .catch(function() {
        if (errorEl) errorEl.textContent = 'Network error. Please try again.';
        btn.disabled = false;
        if (statusEl) statusEl.textContent = '';
      });
    } else {
      // checkin: just navigate to step_photo
      var url = base + 'kiosk/step_photo.php?visitor_id=' + visitorId;
      if (window.KIOSK) KIOSK.navigate(url); else window.location.href = url;
    }
  });
})();
</script>

<?php kiosk_foot(); ?>
