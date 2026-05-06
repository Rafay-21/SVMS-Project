<?php
/**
 * kiosk/step_photo.php
 * Step 2 (checkin): Capture visitor photo or skip.
 * POSTs asynchronously to api/kiosk_checkin.php.
 */
require_once __DIR__ . '/kiosk_boot.php';

$visitor_id   = (int)($_GET['visitor_id']   ?? 0);
$visit_log_id = (int)($_GET['visit_log_id'] ?? 0);
$lang         = preg_replace('/[^a-z]/', '', $_COOKIE['svms_lang'] ?? DEFAULT_LANG);
$lang         = in_array($lang, ['en','ur']) ? $lang : DEFAULT_LANG;

if ($visitor_id < 1) {
    header('Location: ' . BASE_URL . 'kiosk/index.php');
    exit;
}

// Fetch visitor info
$visitor = query_one(
    "SELECT v.id, v.full_name, v.phone, v.photo_path,
            vl.id AS existing_visit_id
     FROM visitors v
     LEFT JOIN visit_log vl ON vl.visitor_id = v.id AND vl.status = 'checked_in'
     WHERE v.id = ?",
    [$visitor_id]
);

if (!$visitor) {
    header('Location: ' . BASE_URL . 'kiosk/step_identify.php?action=checkin&err=notfound');
    exit;
}

$existingVisitLogId = $visit_log_id ?: (int)($visitor['existing_visit_id'] ?? 0);

kiosk_head('Capture Photo', true, BASE_URL . 'kiosk/step_identify.php?action=checkin');
?>

<div class="kiosk-card kiosk-animate-in" style="max-width:700px;text-align:center;">

  <!-- Step indicator -->
  <div class="kiosk-steps" style="margin-bottom:28px;">
    <div class="kiosk-step"><div class="kiosk-step-circle done">&#10003;</div><div class="kiosk-step-label">Identify</div></div>
    <div class="kiosk-step-connector"></div>
    <div class="kiosk-step"><div class="kiosk-step-circle active">2</div><div class="kiosk-step-label">Photo</div></div>
    <div class="kiosk-step-connector"></div>
    <div class="kiosk-step"><div class="kiosk-step-circle">3</div><div class="kiosk-step-label">Done</div></div>
  </div>

  <h2 style="font-size:clamp(20px,3vw,28px);font-weight:800;color:#1a3c5e;margin-bottom:6px;">
    <?= $lang === 'ur' ? 'تصویر لیں' : 'Capture Your Photo' ?>
  </h2>
  <p style="font-size:16px;color:#64748b;margin-bottom:24px;">
    <?= $lang === 'ur' ? 'اپنا چہرہ فریم میں رکھیں' : 'Centre your face in the frame and tap Capture.' ?>
  </p>

  <!-- Camera preview -->
  <div class="kiosk-camera-box" id="camera-box"
       style="max-width:380px;margin:0 auto 20px;border:3px solid #2e75b6;border-radius:20px;overflow:hidden;position:relative;background:#0f172a;aspect-ratio:3/4;">
    <video id="kiosk-video" autoplay playsinline muted
           style="width:100%;height:100%;object-fit:cover;display:block;"></video>
    <canvas id="kiosk-canvas"
            style="display:none;width:100%;height:100%;object-fit:cover;position:absolute;inset:0;"></canvas>
    <!-- Face guide -->
    <div style="position:absolute;inset:0;pointer-events:none;display:flex;align-items:center;justify-content:center;">
      <div style="width:180px;height:220px;border:2px dashed rgba(255,255,255,.4);border-radius:50%;"></div>
    </div>
    <!-- Countdown overlay -->
    <div id="cam-countdown"
         style="display:none;position:absolute;inset:0;background:rgba(0,0,0,.55);
                align-items:center;justify-content:center;font-size:80px;font-weight:900;color:#fff;">
      <span id="cam-count-num">3</span>
    </div>
  </div>

  <!-- Capture controls -->
  <div id="cam-controls" style="display:flex;gap:14px;justify-content:center;margin-bottom:20px;flex-wrap:wrap;">
    <button id="cam-capture-btn" class="kiosk-btn kiosk-btn-primary" style="min-height:70px;padding:16px 36px;">
      <i class="bi bi-camera-fill" style="font-size:28px;"></i>
      <span style="font-size:18px;"><?= $lang === 'ur' ? 'تصویر لیں' : 'Capture' ?></span>
    </button>
    <button id="cam-skip-btn" class="kiosk-btn kiosk-btn-secondary" style="min-height:70px;padding:16px 28px;">
      <i class="bi bi-skip-forward" style="font-size:24px;color:#1a3c5e;"></i>
      <span style="font-size:16px;color:#1a3c5e;"><?= $lang === 'ur' ? 'چھوڑیں' : 'Skip Photo' ?></span>
    </button>
  </div>

  <!-- Review controls -->
  <div id="review-controls" style="display:none;gap:14px;justify-content:center;margin-bottom:20px;flex-wrap:wrap;">
    <button id="cam-retake-btn" class="kiosk-btn kiosk-btn-secondary" style="min-height:70px;padding:16px 28px;">
      <i class="bi bi-arrow-counterclockwise" style="font-size:24px;color:#1a3c5e;"></i>
      <span style="font-size:16px;color:#1a3c5e;"><?= $lang === 'ur' ? 'دوبارہ لیں' : 'Retake' ?></span>
    </button>
    <button id="cam-use-btn" class="kiosk-btn kiosk-btn-primary" style="min-height:70px;padding:16px 36px;">
      <i class="bi bi-check-circle-fill" style="font-size:28px;"></i>
      <span style="font-size:18px;"><?= $lang === 'ur' ? 'استعمال کریں' : 'Use This Photo' ?></span>
    </button>
  </div>

  <div id="cam-status" style="font-size:14px;color:#94a3b8;min-height:20px;"></div>
  <div id="cam-error"  style="font-size:14px;color:#ef4444;min-height:20px;margin-top:4px;"></div>

  <!-- Camera unavailable fallback -->
  <div id="cam-unavailable" style="display:none;padding:20px 0;">
    <i class="bi bi-camera-video-off" style="font-size:40px;color:#94a3b8;"></i>
    <p style="color:#64748b;font-size:15px;margin:12px 0 20px;">Camera not available on this device.</p>
    <button id="cam-skip-fallback" class="kiosk-btn kiosk-btn-primary" style="min-height:70px;padding:16px 36px;">
      Continue Without Photo &#8594;
    </button>
  </div>
</div>

<script>
(function() {
  var base         = window.KIOSK_BASE || '/svms/';
  var csrf         = window.KIOSK_CSRF || '';
  var visitorId    = <?= json_encode($visitor_id) ?>;
  var existingVLId = <?= json_encode($existingVisitLogId) ?>;
  var stream       = null;
  var capturedData = null;

  var video       = document.getElementById('kiosk-video');
  var canvas      = document.getElementById('kiosk-canvas');
  var captureBtn  = document.getElementById('cam-capture-btn');
  var skipBtn     = document.getElementById('cam-skip-btn');
  var skipFallBtn = document.getElementById('cam-skip-fallback');
  var retakeBtn   = document.getElementById('cam-retake-btn');
  var useBtn      = document.getElementById('cam-use-btn');
  var statusEl    = document.getElementById('cam-status');
  var errorEl     = document.getElementById('cam-error');

  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 480 } })
    .then(function(s) {
      stream = s;
      video.srcObject = s;
    })
    .catch(function() {
      document.getElementById('camera-box').style.display      = 'none';
      document.getElementById('cam-controls').style.display    = 'none';
      document.getElementById('cam-unavailable').style.display = 'block';
    });

  captureBtn.addEventListener('click', function() {
    captureBtn.disabled = true;
    var cntEl  = document.getElementById('cam-count-num');
    var cntDiv = document.getElementById('cam-countdown');
    cntDiv.style.display = 'flex';
    var n = 3; cntEl.textContent = n;
    var iv = setInterval(function() {
      n--;
      if (n > 0) { cntEl.textContent = n; }
      else {
        clearInterval(iv);
        cntDiv.style.display = 'none';
        doCapture();
      }
    }, 1000);
  });

  function doCapture() {
    canvas.width  = video.videoWidth  || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0);
    capturedData = canvas.toDataURL('image/jpeg', 0.85);

    video.style.display  = 'none';
    canvas.style.display = 'block';
    document.getElementById('cam-controls').style.display    = 'none';
    document.getElementById('review-controls').style.display = 'flex';
    if (statusEl) statusEl.textContent = 'Photo captured. Tap "Use This Photo" or Retake.';
    captureBtn.disabled = false;
  }

  retakeBtn.addEventListener('click', function() {
    capturedData = null;
    video.style.display  = 'block';
    canvas.style.display = 'none';
    document.getElementById('cam-controls').style.display    = 'flex';
    document.getElementById('review-controls').style.display = 'none';
    if (statusEl) statusEl.textContent = '';
  });

  useBtn.addEventListener('click',     function() { submitCheckin(capturedData); });
  skipBtn.addEventListener('click',    function() { submitCheckin(null); });
  if (skipFallBtn) skipFallBtn.addEventListener('click', function() { submitCheckin(null); });

  function submitCheckin(photoData) {
    if (useBtn)  useBtn.disabled  = true;
    if (skipBtn) skipBtn.disabled = true;
    if (statusEl) statusEl.textContent = 'Processing check-in\u2026';
    if (errorEl)  errorEl.textContent  = '';

    fetch(base + 'api/kiosk_checkin.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        csrf_token:   csrf,
        visitor_id:   visitorId,
        visit_log_id: existingVLId || null,
        photo_data:   photoData || null
      })
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (stream) stream.getTracks().forEach(function(t) { t.stop(); });
      if (d.ok && d.visit_log_id) {
        var url = base + 'kiosk/step_done.php?visit_log_id=' + d.visit_log_id + '&action=checkin';
        if (window.KIOSK) KIOSK.navigate(url); else window.location.href = url;
      } else {
        if (errorEl) errorEl.textContent = d.error || 'Check-in failed. Please see reception.';
        if (useBtn)  useBtn.disabled  = false;
        if (skipBtn) skipBtn.disabled = false;
        if (statusEl) statusEl.textContent = '';
      }
    })
    .catch(function() {
      if (stream) stream.getTracks().forEach(function(t) { t.stop(); });
      if (errorEl) errorEl.textContent = 'Network error. Please try again or see reception.';
      if (useBtn)  useBtn.disabled  = false;
      if (skipBtn) skipBtn.disabled = false;
    });
  }

  window.addEventListener('pagehide', function() {
    if (stream) stream.getTracks().forEach(function(t) { t.stop(); });
  });
})();
</script>

<?php kiosk_foot(); ?>
