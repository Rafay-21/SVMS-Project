<?php
/**
 * kiosk/step_identify.php
 * Step 2: Identify the visitor.
 * Three sub-modes: appointment (QR/code), returning (phone search), new (register).
 * Check-out path: phone search → confirm identity card.
 */
require_once __DIR__ . '/kiosk_boot.php';

$action = ($_GET['action'] ?? 'checkin') === 'checkout' ? 'checkout' : 'checkin';
$err    = sanitize($_GET['err'] ?? '');
$lang   = preg_replace('/[^a-z]/', '', $_COOKIE['svms_lang'] ?? DEFAULT_LANG);
$lang   = in_array($lang, ['en','ur']) ? $lang : DEFAULT_LANG;

// Departments list for new-visitor form
$departments = query_all("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name");

kiosk_head($action === 'checkin' ? 'Check In' : 'Check Out', true, BASE_URL . 'kiosk/');
?>

<div class="kiosk-card kiosk-animate-in" style="max-width:720px;text-align:left;padding:40px 44px;">

  <!-- Step indicator -->
  <div class="kiosk-steps" style="justify-content:flex-start;gap:0;margin-bottom:32px;overflow-x:auto;padding-bottom:4px;">
    <?php $steps = $action === 'checkin'
        ? [['Identify','active'],['Photo',''],['Done','']]
        : [['Identify','active'],['Done','']];
    foreach ($steps as $i => [$label, $cls]): ?>
    <?php if ($i > 0): ?><div class="kiosk-step-connector"></div><?php endif; ?>
    <div class="kiosk-step">
      <div class="kiosk-step-circle <?= $cls ?>"><?= $i + 1 ?></div>
      <div class="kiosk-step-label"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <h2 style="font-size:clamp(22px,3vw,30px);font-weight:800;color:#1a3c5e;margin-bottom:6px;">
    <?= $action === 'checkin' ? '<i class="bi bi-box-arrow-in-right" style="margin-right:8px;"></i>Check In' : '<i class="bi bi-box-arrow-right" style="margin-right:8px;"></i>Check Out' ?>
  </h2>

  <?php if ($err): ?>
  <div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:14px;color:#991b1b;">
    <i class="bi bi-exclamation-triangle-fill" style="margin-right:6px;"></i>
    <?php if ($err === 'notfound'): ?><?= $lang === 'ur' ? 'ریکارڈ نہیں ملا۔' : 'No matching visitor found.' ?>
    <?php elseif ($err === 'blacklisted'): ?><?= $lang === 'ur' ? 'آپ کا اندراج بلاک ہے۔' : 'Access denied. Please see reception.' ?>
    <?php else: ?><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($action === 'checkout'): ?>
  <!-- ── CHECK-OUT PATH: Phone number entry ─────────────── -->
  <p style="font-size:17px;color:#64748b;margin-bottom:24px;">Enter your phone number to find your visit record.</p>

  <div style="margin-bottom:16px;">
    <input type="tel" id="co-phone" class="kiosk-input kiosk-input-lg" inputmode="numeric"
           placeholder="0300 — — — — — —" maxlength="15" autocomplete="off" aria-label="Phone number">
  </div>

  <!-- Phone numpad -->
  <div class="kiosk-numpad" style="max-width:340px;margin:0 auto 28px;">
    <?php for ($r=0;$r<3;$r++): for ($c=1;$c<=3;$c++): $n=$r*3+$c; ?>
    <button class="kiosk-key" data-digit="<?= $n ?>" type="button"><?= $n ?></button>
    <?php endfor; endfor; ?>
    <button class="kiosk-key kiosk-key-del" data-action="del" type="button">⌫</button>
    <button class="kiosk-key" data-digit="0" type="button">0</button>
    <button class="kiosk-key kiosk-key-action" id="co-search-btn" type="button" style="font-size:16px;">Search</button>
  </div>

  <!-- Results -->
  <div id="co-results" style="display:none;"></div>

  <?php else: ?>
  <!-- ── CHECK-IN PATH: 3 tabs ───────────────────────────── -->
  <div class="kiosk-tabs" style="margin-bottom:0;">
    <button class="kiosk-tab active" data-tab="returning" id="tab-returning">
      <i class="bi bi-person-check"></i> <?= $lang === 'ur' ? 'واپس آنے والے' : 'Returning Visitor' ?>
    </button>
    <button class="kiosk-tab" data-tab="appointment" id="tab-appt">
      <i class="bi bi-qr-code-scan"></i> <?= $lang === 'ur' ? 'اپوائنٹمنٹ' : 'Appointment' ?>
    </button>
    <button class="kiosk-tab" data-tab="new" id="tab-new">
      <i class="bi bi-person-plus"></i> <?= $lang === 'ur' ? 'نیا وزیٹر' : 'New Visitor' ?>
    </button>
  </div>

  <!-- ── Tab: Returning visitor (phone search) ──────────── -->
  <div id="panel-returning" class="tab-panel" style="padding-top:24px;">
    <p style="font-size:16px;color:#64748b;margin-bottom:20px;"><?= $lang === 'ur' ? 'اپنا فون نمبر درج کریں:' : 'Search by phone number:' ?></p>
    <input type="tel" id="ret-phone" class="kiosk-input kiosk-input-lg" inputmode="numeric"
           placeholder="03XX-XXXXXXX" maxlength="15" autocomplete="off" aria-label="Phone number">

    <div class="kiosk-numpad" style="max-width:320px;margin:16px auto 20px;">
      <?php for ($r=0;$r<3;$r++): for ($c=1;$c<=3;$c++): $n=$r*3+$c; ?>
      <button class="kiosk-key" data-digit="<?= $n ?>" type="button"><?= $n ?></button>
      <?php endfor; endfor; ?>
      <button class="kiosk-key kiosk-key-del" data-action="del" type="button">⌫</button>
      <button class="kiosk-key" data-digit="0" type="button">0</button>
      <button class="kiosk-key kiosk-key-action" id="ret-search-btn" type="button" style="font-size:15px;">Search</button>
    </div>

    <div id="ret-results" style="min-height:60px;"></div>
  </div>

  <!-- ── Tab: Appointment (QR or code) ─────────────────── -->
  <div id="panel-appointment" class="tab-panel" style="display:none;padding-top:24px;">
    <p style="font-size:16px;color:#64748b;margin-bottom:20px;"><?= $lang === 'ur' ? 'اپنا اپوائنٹمنٹ QR اسکین کریں:' : 'Scan your appointment QR code:' ?></p>

    <!-- QR scanner (html5-qrcode) -->
    <div id="qr-reader" style="width:100%;max-width:400px;margin:0 auto 20px;border-radius:16px;overflow:hidden;border:2px solid #2e75b6;"></div>
    <div id="qr-fallback" style="display:none;">
      <p style="font-size:14px;color:#64748b;margin-bottom:12px;text-align:center;">Camera unavailable — enter code manually:</p>
      <input type="text" id="appt-code" class="kiosk-input" placeholder="Appointment code" autocomplete="off" inputmode="numeric">
    </div>
    <div id="qr-result" style="min-height:40px;margin-top:12px;"></div>
  </div>

  <!-- ── Tab: New visitor (multi-step mini form) ────────── -->
  <div id="panel-new" class="tab-panel" style="display:none;padding-top:24px;">
    <!-- Step dots -->
    <div class="kiosk-dots" id="new-dots">
      <div class="kiosk-dot active" data-step="0"></div>
      <div class="kiosk-dot" data-step="1"></div>
      <div class="kiosk-dot" data-step="2"></div>
    </div>

    <!-- Mini-step 0: Identity -->
    <div id="new-step-0" class="new-step">
      <p style="font-size:16px;color:#64748b;margin-bottom:20px;text-align:center;">
        <?= $lang === 'ur' ? 'اپنا نام اور نمبر درج کریں:' : 'Enter your name and phone:' ?>
      </p>
      <div style="margin-bottom:14px;">
        <label style="font-size:13px;font-weight:700;color:#475569;display:block;margin-bottom:6px;"><?= $lang === 'ur' ? 'پورا نام *' : 'Full Name *' ?></label>
        <input type="text" id="nv-name" class="kiosk-input" placeholder="<?= $lang === 'ur' ? 'پورا نام' : 'Full name' ?>" autocomplete="off">
        <div id="nv-keyboard-name" class="kiosk-keyboard" style="margin-top:8px;"></div>
      </div>
      <div style="margin-bottom:14px;">
        <label style="font-size:13px;font-weight:700;color:#475569;display:block;margin-bottom:6px;"><?= $lang === 'ur' ? 'فون نمبر *' : 'Phone *' ?></label>
        <input type="tel" id="nv-phone" class="kiosk-input" placeholder="03XX-XXXXXXX" inputmode="numeric" maxlength="15">
      </div>
      <div style="margin-bottom:14px;">
        <label style="font-size:13px;font-weight:700;color:#475569;display:block;margin-bottom:6px;">CNIC</label>
        <input type="text" id="nv-cnic" class="kiosk-input" placeholder="XXXXX-XXXXXXX-X" maxlength="15" inputmode="numeric">
      </div>
    </div>

    <!-- Mini-step 1: Visit details -->
    <div id="new-step-1" class="new-step" style="display:none;">
      <p style="font-size:16px;color:#64748b;margin-bottom:20px;text-align:center;">
        <?= $lang === 'ur' ? 'وزٹ کی تفصیلات:' : 'About your visit:' ?>
      </p>
      <div style="margin-bottom:14px;">
        <label style="font-size:13px;font-weight:700;color:#475569;display:block;margin-bottom:6px;"><?= $lang === 'ur' ? 'محکمہ *' : 'Department *' ?></label>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <?php foreach ($departments as $d): ?>
          <button class="kiosk-option" style="min-height:60px;padding:12px 18px;flex:0 0 auto;font-size:14px;"
                  data-dept-id="<?= (int)$d['id'] ?>" data-dept-name="<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>"
                  onclick="selectDept(this)">
            <?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>
          </button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="nv-dept" value="">
      </div>
      <div style="margin-bottom:14px;">
        <label style="font-size:13px;font-weight:700;color:#475569;display:block;margin-bottom:6px;"><?= $lang === 'ur' ? 'کس سے ملنا ہے *' : 'Person to Meet *' ?></label>
        <input type="text" id="nv-host" class="kiosk-input" placeholder="Name of person you are visiting" autocomplete="off">
        <div id="nv-keyboard-host" style="margin-top:8px;"></div>
      </div>
      <div>
        <label style="font-size:13px;font-weight:700;color:#475569;display:block;margin-bottom:6px;"><?= $lang === 'ur' ? 'مقصد *' : 'Purpose *' ?></label>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <?php foreach (['Meeting','Delivery','Interview','Personal','Other'] as $p): ?>
          <button class="kiosk-option" style="min-height:56px;padding:10px 16px;flex:0 0 auto;font-size:14px;"
                  data-purpose="<?= $p ?>" onclick="selectPurpose(this)"><?= $p ?></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" id="nv-purpose" value="">
      </div>
    </div>

    <!-- Mini-step 2: Confirm + submit -->
    <div id="new-step-2" class="new-step" style="display:none;">
      <p style="font-size:16px;color:#64748b;margin-bottom:20px;text-align:center;"><?= $lang === 'ur' ? 'تصدیق کریں:' : 'Please confirm your details:' ?></p>
      <div id="nv-summary" style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:14px;padding:20px;margin-bottom:20px;font-size:15px;line-height:2;"></div>
      <p style="font-size:12px;color:#94a3b8;text-align:center;"><?= $lang === 'ur' ? 'جمع کر کے آپ ہماری پرائیویسی پالیسی سے متفق ہیں۔' : 'By submitting you agree to our visitor privacy policy.' ?></p>
    </div>

    <!-- New-visitor nav buttons -->
    <div style="display:flex;gap:12px;justify-content:space-between;margin-top:24px;" id="new-nav">
      <button class="kiosk-btn kiosk-btn-secondary" id="new-prev-btn" onclick="newStep(-1)" style="min-height:60px;padding:14px 24px;font-size:17px;display:none;">
        ← Back
      </button>
      <button class="kiosk-btn kiosk-btn-primary" id="new-next-btn" onclick="newStep(1)" style="min-height:60px;padding:14px 24px;font-size:17px;margin-left:auto;">
        Next →
      </button>
    </div>
    <div id="nv-error" style="color:#ef4444;font-size:13px;margin-top:8px;text-align:center;min-height:18px;"></div>
  </div>

  <?php endif; ?>
</div>

<script>
(function() {
  var action = <?= json_encode($action) ?>;
  var base   = window.KIOSK_BASE || '/svms/';
  var csrf   = window.KIOSK_CSRF || '';
  var lang   = <?= json_encode($lang) ?>;

  /* ── Tab switching ──────────────────────────────────────── */
  document.querySelectorAll('.kiosk-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.kiosk-tab').forEach(function(b) { b.classList.remove('active'); });
      document.querySelectorAll('.tab-panel').forEach(function(p) { p.style.display = 'none'; });
      btn.classList.add('active');
      var panel = document.getElementById('panel-' + btn.dataset.tab);
      if (panel) panel.style.display = 'block';
      if (btn.dataset.tab === 'appointment') initQrScanner();
      if (btn.dataset.tab === 'new')         initNewVisitor();
    });
  });

  /* ── Numpad for checkout / returning search ─────────────── */
  function bindNumpad(inputId, searchBtnId) {
    if (window.KIOSK) KIOSK.initNumpad(inputId);
    var btn = document.getElementById(searchBtnId);
    if (btn) btn.addEventListener('click', function() { doSearch(inputId); });
    var inp = document.getElementById(inputId);
    if (inp) inp.addEventListener('keydown', function(e) { if (e.key === 'Enter') doSearch(inputId); });
  }

  if (action === 'checkout') {
    bindNumpad('co-phone', 'co-search-btn');
  } else {
    bindNumpad('ret-phone', 'ret-search-btn');
  }

  /* ── Visitor search AJAX ────────────────────────────────── */
  function doSearch(inputId) {
    var phone  = (document.getElementById(inputId) || {}).value || '';
    var resId  = inputId === 'co-phone' ? 'co-results' : 'ret-results';
    var resEl  = document.getElementById(resId);
    if (!phone.trim() || phone.replace(/\D/g,'').length < 7) {
      if (resEl) resEl.innerHTML = '<p style="color:#ef4444;font-size:14px;text-align:center;">Please enter a valid phone number.</p>';
      return;
    }
    if (resEl) resEl.innerHTML = '<p style="color:#94a3b8;font-size:14px;text-align:center;">Searching…</p>';

    fetch(base + 'api/kiosk_search.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ csrf_token: csrf, phone: phone, action: action })
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (!resEl) return;
      if (!d.ok || !d.visitors || !d.visitors.length) {
        resEl.innerHTML = '<p style="color:#ef4444;font-size:14px;text-align:center;">' +
          (d.error || 'No visitor found with that phone number.') + '</p>';
        return;
      }
      var ul = '<ul class="kiosk-result-list">';
      d.visitors.forEach(function(v) {
        var ini = (v.full_name || '?').split(' ').map(function(w){return w[0];}).join('').toUpperCase().slice(0,2);
        ul += '<li onclick="selectVisitor(' + v.id + ',\'' + encodeURIComponent(v.full_name) + '\')">' +
              '<div class="kiosk-avatar" style="width:48px;height:48px;font-size:16px;flex-shrink:0;">' +
              (v.photo_path ? '<img src="' + base + 'assets/uploads/' + v.photo_path + '" style="width:100%;height:100%;object-fit:cover;">' : ini) +
              '</div>' +
              '<div><div class="rname">' + (v.full_name || '') + '</div>' +
              '<div class="rmeta">' + (v.phone || '') + (v.vip ? ' ⭐ VIP' : '') + '</div></div>' +
              '</li>';
      });
      ul += '</ul>';
      resEl.innerHTML = ul;
    })
    .catch(function() {
      if (resEl) resEl.innerHTML = '<p style="color:#ef4444;font-size:14px;text-align:center;">Search error. Please try again.</p>';
    });
  }

  window.selectVisitor = function(vid, nameEnc) {
    var nextUrl = action === 'checkout'
      ? base + 'kiosk/step_confirm.php?visitor_id=' + vid + '&action=checkout'
      : base + 'kiosk/step_photo.php?visitor_id=' + vid;
    if (window.KIOSK) KIOSK.navigate(nextUrl);
    else window.location.href = nextUrl;
  };

  /* ── QR Scanner (html5-qrcode) ──────────────────────────── */
  var qrScanner = null;

  function initQrScanner() {
    if (qrScanner) return;
    if (typeof Html5Qrcode === 'undefined') {
      loadScript('https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js', function() {
        startQrScanner();
      });
    } else {
      startQrScanner();
    }
  }

  function loadScript(src, cb) {
    var s = document.createElement('script'); s.src = src; s.onload = cb;
    document.head.appendChild(s);
  }

  function startQrScanner() {
    try {
      qrScanner = new Html5Qrcode('qr-reader');
      qrScanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 240, height: 240 } },
        function(decodedText) {
          qrScanner.stop().catch(function(){});
          handleQrResult(decodedText);
        },
        null
      ).catch(function() {
        document.getElementById('qr-reader').style.display = 'none';
        document.getElementById('qr-fallback').style.display = 'block';
      });
    } catch(e) {
      document.getElementById('qr-reader').style.display = 'none';
      document.getElementById('qr-fallback').style.display = 'block';
    }
  }

  function handleQrResult(text) {
    var resEl = document.getElementById('qr-result');
    // If it's a full URL with token=, redirect
    var tokenMatch = text.match(/[?&]token=([a-f0-9]+)/i);
    if (tokenMatch) {
      if (window.KIOSK) KIOSK.navigate(base + 'pages/visitor_detail.php?token=' + tokenMatch[1]);
      else window.location.href = base + 'pages/visitor_detail.php?token=' + tokenMatch[1];
      return;
    }
    if (resEl) resEl.innerHTML = '<p style="color:#94a3b8;font-size:14px;text-align:center;">Looking up "' + text + '"…</p>';
  }

  var apptInput = document.getElementById('appt-code');
  if (apptInput) {
    apptInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') handleQrResult(this.value);
    });
  }

  /* ── New-visitor multi-step form ────────────────────────── */
  var nvStep = 0;
  var kbName = null, kbHost = null;

  function initNewVisitor() {
    if (kbName) return; // already inited
    if (window.KIOSK) {
      kbName = KIOSK.buildKeyboard('nv-keyboard-name', 'nv-name', lang);
      kbHost = KIOSK.buildKeyboard('nv-keyboard-host', 'nv-host', lang);
    }
  }

  window.selectDept = function(btn) {
    document.querySelectorAll('[data-dept-id]').forEach(function(b) { b.classList.remove('selected'); });
    btn.classList.add('selected');
    document.getElementById('nv-dept').value = btn.dataset.deptId;
  };

  window.selectPurpose = function(btn) {
    document.querySelectorAll('[data-purpose]').forEach(function(b) { b.classList.remove('selected'); });
    btn.classList.add('selected');
    document.getElementById('nv-purpose').value = btn.dataset.purpose;
  };

  function updateDots(step) {
    document.querySelectorAll('.kiosk-dot').forEach(function(d, i) {
      d.classList.remove('active','done');
      if (i < step) d.classList.add('done');
      else if (i === step) d.classList.add('active');
    });
  }

  window.newStep = function(dir) {
    var errEl = document.getElementById('nv-error');
    if (errEl) errEl.textContent = '';

    if (dir > 0) {
      // Validate current step
      if (nvStep === 0) {
        var name  = (document.getElementById('nv-name')  || {}).value || '';
        var phone = (document.getElementById('nv-phone') || {}).value || '';
        if (name.trim().length < 2)  { if (errEl) errEl.textContent = 'Please enter your full name.'; return; }
        if (phone.replace(/\D/g,'').length < 10) { if (errEl) errEl.textContent = 'Please enter a valid phone number.'; return; }
      }
      if (nvStep === 1) {
        var dept    = (document.getElementById('nv-dept')    || {}).value || '';
        var host    = (document.getElementById('nv-host')    || {}).value || '';
        var purpose = (document.getElementById('nv-purpose') || {}).value || '';
        if (!dept)              { if (errEl) errEl.textContent = 'Please select a department.'; return; }
        if (host.trim().length < 2) { if (errEl) errEl.textContent = 'Please enter the person you are meeting.'; return; }
        if (!purpose)           { if (errEl) errEl.textContent = 'Please select a purpose.'; return; }
      }
    }

    document.getElementById('new-step-' + nvStep).style.display = 'none';
    nvStep += dir;
    nvStep = Math.max(0, Math.min(2, nvStep));

    var stepEl = document.getElementById('new-step-' + nvStep);
    if (stepEl) stepEl.style.display = 'block';

    document.getElementById('new-prev-btn').style.display = nvStep > 0 ? 'inline-flex' : 'none';
    var nextBtn = document.getElementById('new-next-btn');

    if (nvStep === 2) {
      // Build summary
      var summary = document.getElementById('nv-summary');
      if (summary) {
        var deptName = '';
        var sel = document.querySelector('[data-dept-id].selected');
        if (sel) deptName = sel.dataset.deptName || sel.textContent.trim();
        summary.innerHTML =
          '<dl style="display:grid;grid-template-columns:120px 1fr;gap:8px 12px;font-size:15px;">' +
          '<dt style="color:#64748b;font-weight:600;">Name</dt><dd>' + ((document.getElementById('nv-name')||{}).value||'') + '</dd>' +
          '<dt style="color:#64748b;font-weight:600;">Phone</dt><dd>' + ((document.getElementById('nv-phone')||{}).value||'') + '</dd>' +
          '<dt style="color:#64748b;font-weight:600;">Department</dt><dd>' + (deptName || '—') + '</dd>' +
          '<dt style="color:#64748b;font-weight:600;">Meeting</dt><dd>' + ((document.getElementById('nv-host')||{}).value||'') + '</dd>' +
          '<dt style="color:#64748b;font-weight:600;">Purpose</dt><dd>' + ((document.getElementById('nv-purpose')||{}).value||'') + '</dd>' +
          '</dl>';
      }
      nextBtn.textContent = '✓ Submit';
      nextBtn.onclick = submitNewVisitor;
    } else {
      nextBtn.textContent = 'Next →';
      nextBtn.onclick = function() { newStep(1); };
    }

    updateDots(nvStep);
  };

  function submitNewVisitor() {
    var errEl = document.getElementById('nv-error');
    var btn   = document.getElementById('new-next-btn');
    if (btn) { btn.disabled = true; btn.textContent = '…'; }

    var payload = {
      csrf_token:    csrf,
      full_name:     (document.getElementById('nv-name')    || {}).value || '',
      phone:         (document.getElementById('nv-phone')   || {}).value || '',
      cnic:          (document.getElementById('nv-cnic')    || {}).value || '',
      department_id: (document.getElementById('nv-dept')    || {}).value || '',
      person_to_meet:(document.getElementById('nv-host')    || {}).value || '',
      purpose:       (document.getElementById('nv-purpose') || {}).value || '',
    };

    fetch(base + 'api/kiosk_register.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.ok && d.visit_log_id) {
        var url = base + 'kiosk/step_photo.php?visitor_id=' + d.visitor_id + '&visit_log_id=' + d.visit_log_id;
        if (window.KIOSK) KIOSK.navigate(url); else window.location.href = url;
      } else {
        if (errEl) errEl.textContent = d.error || 'Registration failed. Please see reception.';
        if (btn) { btn.disabled = false; btn.textContent = '✓ Submit'; }
      }
    })
    .catch(function() {
      if (errEl) errEl.textContent = 'Network error. Please try again.';
      if (btn) { btn.disabled = false; btn.textContent = '✓ Submit'; }
    });
  }

})();
</script>

<?php kiosk_foot(); ?>
