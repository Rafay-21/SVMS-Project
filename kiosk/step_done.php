<?php
/**
 * kiosk/step_done.php
 * Final screen: success animation, rating (checkout only), auto-return to home.
 */
require_once __DIR__ . '/kiosk_boot.php';

$visit_log_id = (int)($_GET['visit_log_id'] ?? 0);
$action       = ($_GET['action'] ?? 'checkin') === 'checkout' ? 'checkout' : 'checkin';
$lang         = preg_replace('/[^a-z]/', '', $_COOKIE['svms_lang'] ?? DEFAULT_LANG);
$lang         = in_array($lang, ['en','ur']) ? $lang : DEFAULT_LANG;

// Fetch visit + visitor info
$visit = null;
if ($visit_log_id) {
    $visit = query_one(
        "SELECT vl.id, vl.check_in_time, vl.check_out_time, vl.person_to_meet, vl.purpose,
                vl.badge_number, d.name AS dept_name,
                v.full_name, v.photo_path
         FROM visit_log vl
         JOIN visitors v ON v.id = vl.visitor_id
         LEFT JOIN departments d ON d.id = vl.department_id
         WHERE vl.id = ?",
        [$visit_log_id]
    );
}

kiosk_head($action === 'checkin' ? 'Checked In!' : 'Checked Out!');
?>

<div class="kiosk-card kiosk-animate-in" style="max-width:620px;text-align:center;padding-top:56px;padding-bottom:56px;">

  <!-- Animated checkmark -->
  <div class="kiosk-success-icon">
    <svg class="kiosk-check-svg" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="30" cy="30" r="28" stroke="<?= $action === 'checkin' ? '#22c55e' : '#2e75b6' ?>" stroke-width="4"/>
      <polyline points="16,30 26,40 44,20" stroke="<?= $action === 'checkin' ? '#22c55e' : '#2e75b6' ?>" stroke-width="4"
                stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </div>

  <h1 style="font-size:clamp(28px,5vw,44px);font-weight:900;color:#1a3c5e;margin-bottom:8px;">
    <?php if ($action === 'checkin'): ?>
      <?= $lang === 'ur' ? 'خوش آمدید!' : "You're all set!" ?>
    <?php else: ?>
      <?= $lang === 'ur' ? 'شکریہ!' : 'Thanks for visiting!' ?>
    <?php endif; ?>
  </h1>

  <?php if ($visit): ?>
  <p style="font-size:18px;color:#64748b;margin-bottom:8px;">
    <?= htmlspecialchars($visit['full_name'], ENT_QUOTES, 'UTF-8') ?>
  </p>
  <?php if ($action === 'checkin' && $visit['person_to_meet']): ?>
  <p style="font-size:15px;color:#94a3b8;margin-bottom:6px;">
    Meeting <strong style="color:#475569;"><?= htmlspecialchars($visit['person_to_meet'], ENT_QUOTES, 'UTF-8') ?></strong>
    <?= $visit['dept_name'] ? '— ' . htmlspecialchars($visit['dept_name'], ENT_QUOTES, 'UTF-8') : '' ?>
  </p>
  <?php endif; ?>
  <?php if ($visit['badge_number']): ?>
  <p style="font-size:14px;color:#94a3b8;font-family:monospace;margin-bottom:0;">
    Badge: <?= htmlspecialchars($visit['badge_number'], ENT_QUOTES, 'UTF-8') ?>
  </p>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($action === 'checkin'): ?>
  <!-- Badge prompt -->
  <div style="margin:28px auto;max-width:420px;padding:18px 24px;background:#eff6ff;border:2px solid #bfdbfe;border-radius:14px;">
    <i class="bi bi-printer-fill" style="font-size:28px;color:#2e75b6;display:block;margin-bottom:8px;"></i>
    <p style="font-size:15px;color:#1e40af;font-weight:600;margin-bottom:4px;">
      <?= $lang === 'ur' ? 'برائے مہربانی پرنٹر سے اپنا بیج لیں۔' : 'Please collect your visitor badge from the printer.' ?>
    </p>
    <p style="font-size:13px;color:#3b82f6;">Reception will issue your badge shortly.</p>
  </div>
  <?php else: ?>
  <!-- Emoji rating (checkout only) -->
  <div style="margin:28px auto;max-width:460px;">
    <p style="font-size:17px;color:#475569;font-weight:600;margin-bottom:14px;">
      <?= $lang === 'ur' ? 'آج کا تجربہ کیسا رہا؟' : 'How was your experience today?' ?>
    </p>
    <div id="rating-row" style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
      <?php
      $emojis = ['😞','😐','🙂','😊','🤩'];
      $labels = ['Poor','Fair','Good','Great','Excellent'];
      foreach ($emojis as $i => $emoji):
        $val = $i + 1;
      ?>
      <button class="kiosk-emoji-btn" data-rating="<?= $val ?>" aria-label="<?= $labels[$i] ?>"
              onclick="setRating(<?= $val ?>)"><?= $emoji ?></button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" id="rating-val" value="0">
    <div id="rating-thanks" style="font-size:15px;color:#22c55e;font-weight:700;margin-top:12px;min-height:20px;display:none;">
      Thank you for your feedback!
    </div>
  </div>
  <?php endif; ?>

  <!-- Auto-return countdown -->
  <div id="auto-return" style="margin-top:12px;">
    <p style="font-size:14px;color:#94a3b8;">
      <?= $lang === 'ur' ? 'ہوم پیج پر واپس جا رہے ہیں' : 'Returning to home' ?> <strong id="auto-count">8</strong>s…
    </p>
    <a href="<?= BASE_URL ?>kiosk/index.php" id="home-link"
       class="kiosk-btn kiosk-btn-secondary" style="min-height:56px;padding:14px 28px;font-size:16px;margin-top:8px;">
      <i class="bi bi-house-fill" style="color:#1a3c5e;"></i>
      <span style="color:#1a3c5e;"><?= $lang === 'ur' ? 'ابھی واپس جائیں' : 'Back to Home Now' ?></span>
    </a>
  </div>

</div>

<script>
(function() {
  var base  = window.KIOSK_BASE || '/svms/';
  var csrf  = window.KIOSK_CSRF || '';
  var vlId  = <?= json_encode($visit_log_id) ?>;
  var action= <?= json_encode($action) ?>;

  /* ── Emoji rating ───────────────────────────── */
  var selectedRating = 0;
  window.setRating = function(n) {
    selectedRating = n;
    document.querySelectorAll('.kiosk-emoji-btn').forEach(function(b, i) {
      b.classList.toggle('selected', i + 1 <= n);
    });
    document.getElementById('rating-val').value = n;
    if (vlId) submitRating(n);
  };

  function submitRating(n) {
    fetch(base + 'api/kiosk_checkout.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ csrf_token: csrf, visit_log_id: vlId, rating: n, rating_only: true })
    }).then(function(r) { return r.json(); }).then(function(d) {
      if (d.ok) {
        var row   = document.getElementById('rating-row');
        var thanks= document.getElementById('rating-thanks');
        if (row)   row.style.opacity = '.4';
        if (thanks) { thanks.style.display = 'block'; }
      }
    }).catch(function(){});
  }

  /* ── Auto-return ───────────────────────────── */
  var count    = 8;
  var countEl  = document.getElementById('auto-count');
  var homeUrl  = base + 'kiosk/index.php';
  var timer    = setInterval(function() {
    count--;
    if (countEl) countEl.textContent = count;
    if (count <= 0) {
      clearInterval(timer);
      if (window.KIOSK) KIOSK.navigate(homeUrl);
      else window.location.href = homeUrl;
    }
  }, 1000);

  // Reset auto-return on rating tap
  document.querySelectorAll('.kiosk-emoji-btn').forEach(function(b) {
    b.addEventListener('click', function() { count = 8; });
  });
})();
</script>

<?php kiosk_foot(); ?>
