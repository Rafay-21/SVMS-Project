<?php
/**
 * pages/print_badge.php
 * Renders a printable visitor badge.
 * ?id={visit_log_id}  — generates badge PNG if missing and shows print view
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/badge_helpers.php';
require_permission('view_visitors');

$visit_log_id = (int)($_GET['id'] ?? 0);
if (!$visit_log_id) {
    header('Location: ' . BASE_URL . 'pages/visitor_history.php');
    exit;
}

// Load visit data for the display
$visit = query_one(
    "SELECT vl.id, v.badge_number, vl.host_name AS person_to_meet, vl.check_in_time, vl.status,
            v.name AS full_name, v.phone, v.photo_path, v.qr_token,
            COALESCE(d.name,'—') AS dept_name
     FROM visit_log vl
     JOIN visitors v         ON v.id = vl.visitor_id
     LEFT JOIN departments d ON d.id = vl.department_id
     WHERE vl.id = ?
     LIMIT 1",
    'i', [$visit_log_id]
);

if (!$visit) {
    http_response_code(404);
    die('Visit not found.');
}

// Force-regenerate if ?regen=1 or file missing
if (isset($_GET['regen'])) {
    $old = UPLOAD_DIR . 'badges/badge_' . $visit_log_id . '.png';
    if (file_exists($old)) unlink($old);
}
$badgePngUrl = badge_url($visit_log_id);

// QR URL for display
$qrUrl = !empty($visit['qr_token']) ? badge_qr_payload($visit['qr_token']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Badge — <?= e($visit['full_name']) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #f0f4f8;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 32px 16px;
    }

    .controls {
      display: flex;
      gap: 12px;
      margin-bottom: 28px;
      flex-wrap: wrap;
      justify-content: center;
    }
    .btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 20px; border-radius: 8px; font-size: 14px;
      font-weight: 600; cursor: pointer; border: none; text-decoration: none;
      transition: opacity .15s;
    }
    .btn:hover { opacity: .85; }
    .btn-primary { background: #1a3c5e; color: #fff; }
    .btn-secondary { background: #e2e8f0; color: #334155; }

    /* Badge wrapper — matches 480×720 canvas */
    .badge-wrapper {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 40px rgba(0,0,0,.15);
      overflow: hidden;
      width: 480px;
      max-width: 100%;
    }

    .badge-wrapper img.badge-png {
      width: 100%;
      height: auto;
      display: block;
    }

    /* Fallback if GD badge generation failed */
    .badge-fallback {
      width: 480px;
      max-width: 100%;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 40px rgba(0,0,0,.15);
      overflow: hidden;
    }
    .badge-fallback .header-bar {
      background: #1a3c5e;
      padding: 20px;
      text-align: center;
      color: #fff;
    }
    .badge-fallback .badge-body {
      padding: 28px 32px;
      text-align: center;
    }
    .badge-fallback .visitor-name {
      font-size: 26px; font-weight: 800; color: #1a3c5e; margin-bottom: 4px;
    }
    .badge-fallback .badge-num {
      font-size: 13px; color: #64748b; letter-spacing: 1px; margin-bottom: 16px;
    }
    .badge-fallback dl {
      text-align: left; font-size: 13px; display: grid;
      grid-template-columns: 100px 1fr; gap: 8px 12px; margin-bottom: 20px;
    }
    .badge-fallback dt { color: #64748b; font-weight: 600; }
    .badge-fallback dd { color: #1e293b; }

    @media print {
      body { background: #fff; padding: 0; }
      .controls { display: none !important; }
      .badge-wrapper, .badge-fallback {
        box-shadow: none;
        border-radius: 0;
        width: 80mm;
        page-break-inside: avoid;
      }
      .badge-fallback { border: 1px solid #ccc; }
    }
  </style>
</head>
<body>

  <div class="controls no-print">
    <a href="<?= BASE_URL ?>pages/visitor_detail.php?id=<?= $visit_log_id ?>" class="btn btn-secondary">
      <i class="bi bi-arrow-left"></i> Back to Visit
    </a>
    <button onclick="window.print()" class="btn btn-primary">
      <i class="bi bi-printer-fill"></i> Print Badge
    </button>
    <?php if ($badgePngUrl): ?>
    <a href="<?= e($badgePngUrl) ?>" download="badge_<?= $visit_log_id ?>.png" class="btn btn-secondary">
      <i class="bi bi-download"></i> Download PNG
    </a>
    <?php endif; ?>
  </div>

  <?php if ($badgePngUrl): ?>
  <!-- GD-generated badge PNG -->
  <div class="badge-wrapper">
    <img class="badge-png"
         src="<?= e($badgePngUrl) ?>?v=<?= time() ?>"
         alt="Visitor Badge — <?= e($visit['full_name']) ?>">
  </div>
  <?php else: ?>
  <!-- HTML fallback badge (GD not available or generation failed) -->
  <div class="badge-fallback">
    <div class="header-bar">
      <div style="font-size:13px;font-weight:700;letter-spacing:2px;color:rgba(255,255,255,.8);"><?= e(SITE_NAME) ?></div>
      <div style="font-size:11px;letter-spacing:3px;margin-top:4px;color:#00b4d8;">V I S I T O R</div>
    </div>
    <div class="badge-body">
      <?php
      // Initials avatar
      $parts = explode(' ', trim($visit['full_name']));
      $ini   = strtoupper(substr($parts[0], 0, 1)) . (isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) : '');
      ?>
      <?php if ($visit['photo_path'] && file_exists(UPLOAD_DIR . $visit['photo_path'])): ?>
      <img src="<?= BASE_URL ?>assets/uploads/<?= e($visit['photo_path']) ?>"
           style="width:140px;height:140px;object-fit:cover;border-radius:50%;border:3px solid #2e75b6;margin:0 auto 16px;display:block;" alt="">
      <?php else: ?>
      <div style="width:140px;height:140px;border-radius:50%;background:#2e75b6;color:#fff;font-size:52px;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <?= e($ini) ?>
      </div>
      <?php endif; ?>

      <div class="visitor-name"><?= e($visit['full_name']) ?></div>
      <div class="badge-num"><?= e($visit['badge_number']) ?></div>

      <dl>
        <dt>Department</dt><dd><?= e($visit['dept_name']) ?></dd>
        <dt>Meeting</dt>   <dd><?= e($visit['person_to_meet']) ?></dd>
        <dt>Date</dt>      <dd><?= format_datetime($visit['check_in_time'], 'd M Y') ?></dd>
        <dt>Time</dt>      <dd><?= format_datetime($visit['check_in_time'], 'g:i A') ?></dd>
        <dt>Phone</dt>     <dd><?= e($visit['phone']) ?></dd>
      </dl>

      <?php if ($qrUrl): ?>
      <!-- QR via Google Charts fallback (requires internet) -->
      <img src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=<?= rawurlencode($qrUrl) ?>&choe=UTF-8"
           width="200" height="200" alt="QR Code" style="margin:0 auto;display:block;border:4px solid #e2e8f0;border-radius:8px;">
      <p style="font-size:10px;color:#94a3b8;margin-top:8px;">Scan to view visit details</p>
      <?php endif; ?>
    </div>
    <div style="background:#2e75b6;padding:10px;text-align:center;color:#fff;font-size:10px;letter-spacing:1px;">
      svms.com  |  Visitor Management System
    </div>
  </div>
  <?php endif; ?>

</body>
<script>
  // Auto-print if ?print=1 in URL
  if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', function() { window.print(); });
  }
</script>
</html>
