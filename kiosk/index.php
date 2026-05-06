<?php
/**
 * kiosk/index.php — Welcome / Home screen.
 * Two primary actions: Check In | Check Out.
 * Language toggle + discreet Staff button in bottom.
 */
require_once __DIR__ . '/kiosk_boot.php';

$emergency = get_setting('emergency_mode', 'normal');
$lang      = preg_replace('/[^a-z]/', '', $_COOKIE['svms_lang'] ?? DEFAULT_LANG);
$lang      = in_array($lang, ['en','ur']) ? $lang : DEFAULT_LANG;

kiosk_head('Welcome');
?>

<?php if ($emergency !== 'normal'): ?>
<div style="position:fixed;inset:0;background:<?= $emergency === 'lockdown' ? 'rgba(220,38,38,.95)' : 'rgba(202,138,4,.95)' ?>;
     z-index:9999;display:flex;align-items:center;justify-content:center;flex-direction:column;
     text-align:center;color:#fff;padding:40px;">
  <i class="bi bi-<?= $emergency === 'lockdown' ? 'lock-fill' : 'door-open-fill' ?>"
     style="font-size:100px;margin-bottom:24px;"></i>
  <h1 style="font-size:clamp(28px,5vw,52px);font-weight:900;text-transform:uppercase;letter-spacing:4px;margin-bottom:16px;">
    <?= $emergency === 'lockdown' ? 'FACILITY LOCKDOWN' : 'EVACUATION IN PROGRESS' ?>
  </h1>
  <p style="font-size:clamp(16px,2vw,22px);max-width:560px;opacity:.9;">
    <?= $emergency === 'lockdown'
      ? 'No visitors are permitted entry. Please follow staff instructions.'
      : 'Please proceed to the nearest exit immediately.' ?>
  </p>
</div>
<?php endif; ?>

<div class="kiosk-card" style="max-width:680px;padding-top:56px;padding-bottom:56px;">
  <img src="<?= BASE_URL ?>assets/img/logo.svg" width="80" height="80" alt=""
       style="margin:0 auto 20px;display:block;filter:drop-shadow(0 4px 16px rgba(26,60,94,.18));">

  <h1 class="kiosk-title">
    <?= $lang === 'ur' ? 'خوش آمدید' : 'Welcome' ?>
  </h1>
  <p class="kiosk-subtitle">
    <?= $lang === 'ur' ? 'براہ کرم اپنا آپشن منتخب کریں' : 'Please choose an option to get started' ?>
  </p>

  <!-- Primary action buttons -->
  <div style="display:flex;gap:20px;flex-wrap:wrap;justify-content:center;margin-bottom:40px;">
    <a href="<?= BASE_URL ?>kiosk/step_identify.php?action=checkin"
       class="kiosk-btn kiosk-btn-primary" style="flex:1;max-width:280px;min-height:140px;">
      <i class="bi bi-box-arrow-in-right" style="font-size:44px;"></i>
      <span style="font-size:24px;"><?= $lang === 'ur' ? 'چیک ان' : 'Check In' ?></span>
      <span style="font-size:14px;opacity:.8;font-weight:500;"><?= $lang === 'ur' ? 'وزیٹر ہیں؟' : "I'm visiting today" ?></span>
    </a>
    <a href="<?= BASE_URL ?>kiosk/step_identify.php?action=checkout"
       class="kiosk-btn kiosk-btn-secondary" style="flex:1;max-width:280px;min-height:140px;">
      <i class="bi bi-box-arrow-right" style="font-size:44px;color:#1a3c5e;"></i>
      <span style="font-size:24px;color:#1a3c5e;"><?= $lang === 'ur' ? 'چیک آؤٹ' : 'Check Out' ?></span>
      <span style="font-size:14px;color:#64748b;font-weight:500;"><?= $lang === 'ur' ? 'جا رہے ہیں؟' : "I'm leaving now" ?></span>
    </a>
  </div>

  <p style="font-size:13px;color:#94a3b8;">
    <?= $lang === 'ur' ? 'بیج کیو آر کوڈ اسکین کریں' : 'Registered visitors may scan their badge QR code anytime.' ?>
  </p>
</div>

<!-- Language toggle (bottom, visible in navbar area) -->
<div style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);display:flex;gap:10px;z-index:100;">
  <button class="kiosk-lang-btn <?= $lang === 'en' ? 'active' : '' ?>" data-lang="en">English</button>
  <button class="kiosk-lang-btn <?= $lang === 'ur' ? 'active' : '' ?>" data-lang="ur">اردو</button>
</div>

<?php kiosk_foot(); ?>
