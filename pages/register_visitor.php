<?php
/**
 * pages/register_visitor.php — Visitor Registration v2.3
 * Captures visitor details, live webcam photo, blacklist check,
 * custom fields. Creates visitors + visit_log atomically.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/email_helpers.php';
require_permission('register_visitor');

/* ── helpers ─────────────────────────────────────────────────── */
function rv_err(array &$errors, string $msg): void { $errors[] = $msg; }

/* ── POST handler ────────────────────────────────────────────── */
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $conn;
    csrf_validate();

    // Raw inputs (no htmlspecialchars on INSERT — only on output)
    $full_name    = trim($_POST['full_name']       ?? '');
    $cnic         = trim($_POST['cnic']            ?? '');
    $phone        = trim($_POST['phone']           ?? '');
    $email_in     = trim($_POST['email']           ?? '');
    $visitor_type = trim($_POST['visitor_type']    ?? 'walk_in');
    $dept_id      = (int)($_POST['department_id']  ?? 0);
    $person_meet  = trim($_POST['person_to_meet']  ?? '');
    $purpose      = trim($_POST['purpose']         ?? '');
    $vehicle      = strtoupper(trim($_POST['vehicle_number'] ?? ''));
    $is_vip       = ($visitor_type === 'vip') ? 1 : 0;
    $photo_b64    = $_POST['photo_data']           ?? '';

    // Custom fields raw
    $custom_raw   = $_POST['custom'] ?? [];

    // ── server-side validation ────────────────────────────────
    if ($full_name === '')  rv_err($errors, 'Full Name is required.');
    if (strlen($full_name) > 100) rv_err($errors, 'Full Name must not exceed 100 characters.');
    if ($phone === '')      rv_err($errors, 'Phone Number is required.');

    $phone_pattern = '/^03[0-9]{2}-[0-9]{7}$/';
    if ($phone !== '' && !preg_match($phone_pattern, $phone))
        rv_err($errors, 'Phone format must be 03XX-XXXXXXX (e.g. 0300-1234567).');

    $cnic_pattern = '/^\d{5}-\d{7}-\d$/';
    if ($cnic !== '' && !preg_match($cnic_pattern, $cnic))
        rv_err($errors, 'CNIC format must be 12345-1234567-1.');

    if ($email_in !== '' && !filter_var($email_in, FILTER_VALIDATE_EMAIL))
        rv_err($errors, 'Email address is not valid.');

    if ($person_meet === '') rv_err($errors, 'Person to Meet is required.');
    if ($purpose === '')     rv_err($errors, 'Purpose of Visit is required.');
    if (strlen($purpose) > 500) rv_err($errors, 'Purpose must not exceed 500 characters.');

    // Validate required custom fields
    if (empty($errors)) {
        $cf_rows = query_all("SELECT * FROM custom_fields WHERE is_active=1 ORDER BY sort_order ASC, id ASC");        
        foreach ($cf_rows as $cf) {
            if ($cf['is_required'] && empty($custom_raw[$cf['id']])) {
                rv_err($errors, htmlspecialchars($cf['field_name'], ENT_QUOTES, 'UTF-8') . ' is required.');
            }
        }
    }

    // ── blacklist re-check server-side ────────────────────────
    if (empty($errors)) {
        $bl = query_one(
            "SELECT id, reason, severity FROM blacklist
             WHERE (phone=? OR (cnic!='' AND cnic=?)) AND is_active=1 LIMIT 1",
            'ss', [$phone, $cnic]
        );
        if ($bl && in_array(strtolower($bl['severity'] ?? 'high'), ['high', 'critical'], true)) {
            rv_err($errors, 'This visitor is on the watchlist and cannot be registered. Reason: ' . htmlspecialchars($bl['reason'], ENT_QUOTES, 'UTF-8'));
            log_action('registration_blocked', 0, json_encode(['phone' => $phone, 'cnic' => $cnic, 'reason' => $bl['reason']]));
        }
    }

    // ── process photo ──────────────────────────────────────────
    $photo_path = '';
    if (empty($errors)) {
        $photo_dir = UPLOAD_DIR . 'visitor_photos/';
        if (!is_dir($photo_dir)) mkdir($photo_dir, 0750, true);

        if (!empty($photo_b64) && str_starts_with($photo_b64, 'data:image')) {
            // Webcam base64
            $img_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $photo_b64));
            if ($img_data && strlen($img_data) <= 5 * 1024 * 1024) {
                $tmpfile = tempnam(sys_get_temp_dir(), 'svms_');
                file_put_contents($tmpfile, $img_data);
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $tmpfile);
                finfo_close($finfo);
                if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
                    $src = imagecreatefromstring($img_data);
                    if ($src) {
                        [$ow, $oh] = [imagesx($src), imagesy($src)];
                        $max = 800;
                        if ($ow > $max) {
                            $nw = $max; $nh = (int)round($oh * $max / $ow);
                        } else { $nw = $ow; $nh = $oh; }
                        $dst = imagecreatetruecolor($nw, $nh);
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
                        $fname = 'visitor_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
                        if (imagejpeg($dst, $photo_dir . $fname, 85)) {
                            $photo_path = 'visitor_photos/' . $fname;
                        }
                        imagedestroy($src); imagedestroy($dst);
                    }
                }
                unlink($tmpfile);
            }
        } elseif (!empty($_FILES['photo_file']['tmp_name'])) {
            // File upload
            $tmpfile = $_FILES['photo_file']['tmp_name'];
            $size    = $_FILES['photo_file']['size'] ?? 0;
            if ($size <= 5 * 1024 * 1024) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $tmpfile);
                finfo_close($finfo);
                if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
                    $img_data = file_get_contents($tmpfile);
                    $src = imagecreatefromstring($img_data);
                    if ($src) {
                        [$ow, $oh] = [imagesx($src), imagesy($src)];
                        $max = 800;
                        if ($ow > $max) { $nw = $max; $nh = (int)round($oh * $max / $ow); }
                        else { $nw = $ow; $nh = $oh; }
                        $dst = imagecreatetruecolor($nw, $nh);
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
                        $fname = 'visitor_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
                        if (imagejpeg($dst, $photo_dir . $fname, 85)) {
                            $photo_path = 'visitor_photos/' . $fname;
                        }
                        imagedestroy($src); imagedestroy($dst);
                    }
                } else {
                    rv_err($errors, 'Uploaded file must be JPEG or PNG.');
                }
            } else {
                rv_err($errors, 'Photo must be 5 MB or smaller.');
            }
        }
    }

    // ── DB transaction ─────────────────────────────────────────
    if (empty($errors)) {
        $conn->begin_transaction();
        $new_photo_path = $photo_path; // track for rollback
        try {
            // custom_data JSON
            $custom_data = [];
            foreach ($cf_rows ?? [] as $cf) {
                $val = $custom_raw[$cf['id']] ?? '';
                if ($cf['field_type'] === 'checkbox') {
                    $val = isset($custom_raw[$cf['id']]) ? 1 : 0;
                }
                $custom_data[$cf['field_name']] = $val;
            }
            $custom_json = json_encode($custom_data, JSON_UNESCAPED_UNICODE);

            $qr_token = bin2hex(random_bytes(16));

            // INSERT visitors
            $stmt = $conn->prepare(
                "INSERT INTO visitors (full_name, cnic, phone, email, photo_path, vip, qr_token, custom_data, created_at)
                 VALUES (?,?,?,?,?,?,?,?,NOW())"
            );
            $stmt->bind_param('sssssiss',
                $full_name, $cnic, $phone, $email_in,
                $photo_path, $is_vip, $qr_token, $custom_json
            );
            $stmt->execute();
            $visitor_id = (int)$conn->insert_id;
            $stmt->close();

            // badge_number: BADGE_PREFIX-YYMMDD-NNNN (sequence from visit_log count today)
            $seq_row    = query_one("SELECT COUNT(*)+1 AS seq FROM visit_log WHERE DATE(check_in_time)=CURDATE()");
            $seq        = str_pad((int)($seq_row['seq'] ?? 1), 4, '0', STR_PAD_LEFT);
            $badge_num  = BADGE_PREFIX . '-' . date('ymd') . '-' . $seq;

            // INSERT visit_log
            $stmt = $conn->prepare(
                "INSERT INTO visit_log (visitor_id, department_id, person_to_meet, purpose, vehicle_number,
                  badge_number, visitor_type, check_in_time, status, registered_by)
                 VALUES (?,?,?,?,?,?,?,NOW(),'checked_in',?)"
            );
            $dept_id_val = $dept_id ?: null;
            $stmt->bind_param('iisssssi',
                $visitor_id, $dept_id_val, $person_meet, $purpose,
                $vehicle, $badge_num, $visitor_type,
                $_SESSION['admin_id']
            );
            $stmt->execute();
            $visit_id = (int)$conn->insert_id;
            $stmt->close();

            // INSERT notification
            $notif_title = $is_vip ? '⭐ VIP Visitor Checked In' : 'New Visitor Checked In';
            $notif_msg   = $full_name . ' is here to meet ' . $person_meet . ($is_vip ? ' (VIP)' : '');
            $notif_type  = $is_vip ? 'vip_arrival' : 'visitor_in';
            $stmt = $conn->prepare(
                "INSERT INTO notifications (type, title, message, link, recipient_id, is_read, created_at)
                 VALUES (?,?,?,?,NULL,0,NOW())"
            );
            $notif_link = BASE_URL . 'pages/visitor_detail.php?id=' . $visit_id;
            $stmt->bind_param('ssss', $notif_type, $notif_title, $notif_msg, $notif_link);
            $stmt->execute();
            $stmt->close();

            // log action
            log_action('register_visitor', $visit_id, json_encode([
                'visitor_id' => $visitor_id, 'name' => $full_name, 'badge' => $badge_num
            ]));

            // optional welcome/confirmation email (Phase 4.2 will wire SMTP properly)
            if ($email_in !== '') {
                $subj = 'Visit Confirmation — ' . SITE_NAME;
                $html = '<p>Dear ' . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . ',</p>'
                      . '<p>Your visit has been registered. Badge: <strong>' . htmlspecialchars($badge_num, ENT_QUOTES, 'UTF-8') . '</strong></p>'
                      . '<p>You are here to meet: ' . htmlspecialchars($person_meet, ENT_QUOTES, 'UTF-8') . '</p>';
                @send_email($email_in, $subj, $html);
            }

            $conn->commit();

            // POST → GET redirect
            header('Location: ' . BASE_URL . 'pages/visitor_detail.php?id=' . $visit_id . '&new=1');
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            if ($new_photo_path && file_exists(UPLOAD_DIR . $new_photo_path)) {
                @unlink(UPLOAD_DIR . $new_photo_path);
            }
            error_log('register_visitor error: ' . $e->getMessage());
            rv_err($errors, 'A system error occurred. Please try again.');
        }
    }
}

/* ── Load page data ──────────────────────────────────────────── */
$departments = query_all("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name");
$custom_fields = query_all("SELECT * FROM custom_fields WHERE is_active=1 ORDER BY sort_order ASC, id ASC");

$page_title    = 'Register New Visitor';
$page_extra_js = ['webcam.js', 'validation.js'];
include __DIR__ . '/../includes/header.php';

// Re-populate on validation fail
$rv = $_POST;
?>

<!-- Blacklist blocking banner (hidden by default; JS shows it) -->
<div id="blacklist-banner" role="alert" style="display:none;position:sticky;top:72px;z-index:900;
  background:#fef2f2;border:1.5px solid var(--danger);border-radius:var(--radius-md);
  padding:14px 18px;margin:0 24px 16px;display:none;align-items:flex-start;gap:12px;">
  <i class="bi bi-slash-circle-fill" style="font-size:20px;color:var(--danger);flex-shrink:0;margin-top:2px;"></i>
  <div style="flex:1;">
    <strong style="color:var(--danger);font-size:14px;" id="bl-title">Watchlist Match</strong>
    <p style="font-size:13px;color:#7f1d1d;margin:4px 0 0;" id="bl-detail"></p>
  </div>
</div>

<div class="container" id="rv-container">

  <!-- Page header -->
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:var(--space-6);">
    <div>
      <h1 style="font-size:1.4rem;font-weight:700;margin:0 0 4px;color:var(--text);">
        <i class="bi bi-person-plus-fill" style="color:var(--secondary);margin-right:8px;"></i>Register New Visitor
      </h1>
      <p style="font-size:13px;color:var(--text-muted);margin:0;">Capture visitor details and check them in.</p>
    </div>
    <a href="<?= BASE_URL ?>pages/visitor_history.php" class="btn btn-secondary btn-sm">
      <i class="bi bi-clock-history"></i> View History
    </a>
  </div>

  <?php foreach ($errors as $err): ?>
  <div style="display:flex;gap:10px;align-items:flex-start;padding:12px 16px;background:#fef2f2;border:1.5px solid var(--danger);border-radius:var(--radius-md);margin-bottom:var(--space-4);">
    <i class="bi bi-exclamation-circle-fill" style="color:var(--danger);flex-shrink:0;margin-top:2px;"></i>
    <span style="font-size:13px;color:#7f1d1d;"><?= e($err) ?></span>
  </div>
  <?php endforeach; ?>

  <form method="POST" action="" enctype="multipart/form-data" id="rv-form" novalidate>
    <?php csrf_field(); ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;" class="rv-grid">

      <!-- ═══════════════════════════════════════════════════════
           LEFT COLUMN — form card
           ═══════════════════════════════════════════════════════ -->
      <div>

        <!-- ── Photo Widget ── -->
        <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.06);padding:24px;margin-bottom:24px;">
          <h3 style="font-size:14px;font-weight:700;margin:0 0 16px;color:var(--text);display:flex;align-items:center;gap:8px;">
            <i class="bi bi-camera-fill" style="color:var(--secondary);"></i>Visitor Photo
          </h3>

          <!-- Source toggle pills -->
          <div style="display:flex;gap:8px;margin-bottom:16px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:500;">
              <input type="radio" name="photo_source" value="webcam" id="ps-webcam" checked style="accent-color:var(--secondary);">
              <i class="bi bi-camera-video-fill"></i> Use Webcam
            </label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:500;">
              <input type="radio" name="photo_source" value="upload" id="ps-upload" style="accent-color:var(--secondary);">
              <i class="bi bi-upload"></i> Upload File
            </label>
          </div>

          <!-- Webcam panel -->
          <div id="webcam-panel">
            <div style="display:flex;flex-direction:column;align-items:center;gap:12px;">
              <div style="position:relative;width:320px;max-width:100%;">
                <video id="webcam-video"
                       style="width:320px;max-width:100%;height:240px;object-fit:cover;border-radius:8px;
                              border:2px dashed var(--secondary);background:#0a0a0a;display:none;"
                       autoplay muted playsinline></video>
                <canvas id="webcam-canvas"
                        style="width:320px;max-width:100%;height:240px;object-fit:cover;border-radius:8px;
                               border:2px solid var(--success);display:none;"></canvas>
                <div id="webcam-idle"
                     style="width:320px;max-width:100%;height:240px;border-radius:8px;border:2px dashed var(--border);
                            background:var(--bg);display:flex;flex-direction:column;align-items:center;
                            justify-content:center;gap:8px;color:var(--text-muted);">
                  <i class="bi bi-camera-video" style="font-size:36px;opacity:.4;"></i>
                  <span style="font-size:13px;">Camera not started</span>
                </div>
              </div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                <button type="button" id="btn-start-cam" class="btn btn-secondary btn-sm">
                  <i class="bi bi-camera-video-fill"></i> Start Camera
                </button>
                <button type="button" id="btn-capture" class="btn btn-primary btn-sm" style="display:none;">
                  <i class="bi bi-camera2"></i> Capture
                </button>
                <button type="button" id="btn-retake" class="btn btn-secondary btn-sm" style="display:none;">
                  <i class="bi bi-arrow-counterclockwise"></i> Retake
                </button>
              </div>
              <p id="webcam-status" style="font-size:12px;color:var(--text-muted);margin:0;text-align:center;"></p>
            </div>
          </div>

          <!-- File upload panel -->
          <div id="upload-panel" style="display:none;">
            <label for="photo_file" style="display:block;cursor:pointer;">
              <div id="upload-drop-zone" style="border:2px dashed var(--border);border-radius:8px;padding:24px;
                   text-align:center;color:var(--text-muted);transition:border-color .15s,background .15s;">
                <i class="bi bi-cloud-arrow-up" style="font-size:36px;opacity:.5;display:block;margin-bottom:8px;"></i>
                <span style="font-size:13px;">Drag & drop or <strong style="color:var(--secondary);">click to browse</strong></span>
                <br><span style="font-size:11px;">JPEG or PNG, max 5 MB</span>
              </div>
            </label>
            <input type="file" id="photo_file" name="photo_file" accept="image/jpeg,image/png"
                   style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
            <img id="upload-preview" style="display:none;width:100%;max-height:220px;object-fit:cover;border-radius:8px;margin-top:10px;border:1px solid var(--border);" alt="Preview">
          </div>

          <input type="hidden" name="photo_data" id="webcam-hidden">
          <p style="font-size:11px;color:var(--text-muted);margin:12px 0 0;text-align:center;">
            Photo is optional — a coloured-initials avatar will be used if omitted.
          </p>
        </div>

        <!-- ── Visitor Information ── -->
        <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.06);padding:24px;">
          <h3 style="font-size:14px;font-weight:700;margin:0 0 20px;color:var(--text);display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--border);padding-bottom:12px;">
            <i class="bi bi-person-vcard" style="color:var(--secondary);"></i>Visitor Information
          </h3>

          <!-- Full Name -->
          <div class="form-group">
            <label for="full_name">Full Name <span style="color:var(--danger);">*</span></label>
            <input type="text" id="full_name" name="full_name" class="form-control"
                   value="<?= e($rv['full_name'] ?? '') ?>"
                   placeholder="Ahmed Khan" maxlength="100" autocomplete="name" required
                   data-rules="required|maxLen:100"
                   oninput="rvUpdatePreview()">
          </div>

          <!-- CNIC -->
          <div class="form-group">
            <label for="cnic">CNIC / National ID <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(optional)</span></label>
            <input type="text" id="cnic" name="cnic" class="form-control"
                   value="<?= e($rv['cnic'] ?? '') ?>"
                   placeholder="12345-1234567-1" maxlength="15"
                   data-format="cnic" data-rules="cnic_opt"
                   onblur="rvCheckBlacklist()">
          </div>

          <!-- Phone -->
          <div class="form-group">
            <label for="phone">Phone Number <span style="color:var(--danger);">*</span></label>
            <div style="position:relative;">
              <input type="tel" id="phone" name="phone" class="form-control"
                     value="<?= e($rv['phone'] ?? '') ?>"
                     placeholder="0300-1234567" maxlength="12"
                     data-format="phone_pk" data-rules="required|phone_pk"
                     onblur="rvCheckBlacklist()" oninput="rvUpdatePreview()"
                     style="padding-right:36px;">
              <span id="phone-check-icon" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);display:none;"></span>
            </div>
          </div>

          <!-- Email -->
          <div class="form-group">
            <label for="email">Email <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(optional — sends confirmation)</span></label>
            <input type="email" id="email" name="email" class="form-control"
                   value="<?= e($rv['email'] ?? '') ?>"
                   placeholder="visitor@example.com" maxlength="150"
                   data-rules="email_opt">
          </div>

          <!-- Visitor Type -->
          <div class="form-group">
            <label for="visitor_type">Visitor Type <span style="color:var(--danger);">*</span></label>
            <select id="visitor_type" name="visitor_type" class="form-control" onchange="rvVisitorTypeChange(this);rvUpdatePreview()">
              <?php
              $vt_options = ['walk_in'=>'Walk-In','appointment'=>'Appointment','delivery'=>'Delivery',
                             'vendor'=>'Vendor','contractor'=>'Contractor','vip'=>'VIP'];
              foreach ($vt_options as $vv => $vl): ?>
              <option value="<?= e($vv) ?>" <?= ($rv['visitor_type'] ?? 'walk_in') === $vv ? 'selected' : '' ?>><?= e($vl) ?></option>
              <?php endforeach; ?>
            </select>
            <div id="vip-note" style="display:none;margin-top:8px;padding:8px 12px;background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;font-size:12px;color:#92400e;">
              <i class="bi bi-star-fill" style="color:#f59e0b;"></i>
              VIP visitors are fast-tracked and the host receives a priority notification.
            </div>
          </div>

          <!-- Department -->
          <div class="form-group">
            <label for="department_id">Department to Visit <span style="color:var(--danger);">*</span></label>
            <select id="department_id" name="department_id" class="form-control" onchange="rvUpdatePreview()">
              <option value="">— Select Department —</option>
              <?php foreach ($departments as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= (int)($rv['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                <?= e($d['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Person to Meet -->
          <div class="form-group">
            <label for="person_to_meet">Person to Meet <span style="color:var(--danger);">*</span></label>
            <input type="text" id="person_to_meet" name="person_to_meet" class="form-control"
                   value="<?= e($rv['person_to_meet'] ?? '') ?>"
                   placeholder="Dr. Sarah Ahmed" maxlength="100" required
                   data-rules="required" oninput="rvUpdatePreview()">
          </div>

          <!-- Purpose -->
          <div class="form-group" style="position:relative;">
            <label for="purpose">Purpose of Visit <span style="color:var(--danger);">*</span></label>
            <textarea id="purpose" name="purpose" class="form-control" rows="2" maxlength="500"
                      placeholder="Brief description of the visit…" required
                      data-rules="required"
                      oninput="document.getElementById('purpose-count').textContent=this.value.length"><?= e($rv['purpose'] ?? '') ?></textarea>
            <span id="purpose-count" style="position:absolute;bottom:8px;right:10px;font-size:11px;color:var(--text-muted);">
              <?= strlen($rv['purpose'] ?? '') ?>
            </span>
            <span style="font-size:11px;color:var(--text-muted);">Max 500 characters</span>
          </div>

          <!-- Vehicle Number -->
          <div class="form-group">
            <label for="vehicle_number">Vehicle Number <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(optional)</span></label>
            <input type="text" id="vehicle_number" name="vehicle_number" class="form-control"
                   value="<?= e($rv['vehicle_number'] ?? '') ?>"
                   placeholder="ABC-123" maxlength="20"
                   style="text-transform:uppercase;">
          </div>

          <?php if (!empty($custom_fields)): ?>
          <!-- ── Custom Fields ── -->
          <div style="border-top:1px solid var(--border);margin-top:20px;padding-top:20px;">
            <h4 style="font-size:13px;font-weight:700;margin:0 0 16px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;">
              Additional Information
            </h4>
            <?php foreach ($custom_fields as $cf):
              $cf_val  = $rv['custom'][$cf['id']] ?? '';
              $cf_req  = $cf['is_required'] ? ' *' : '';
              $cf_id   = 'cf_' . (int)$cf['id'];
              $cf_name = 'custom[' . (int)$cf['id'] . ']';
              $cf_opts = !empty($cf['options']) ? json_decode($cf['options'], true) : [];
            ?>
            <div class="form-group">
              <label for="<?= $cf_id ?>">
                <?= e(!empty($cf['label']) ? $cf['label'] : ucwords(str_replace('_', ' ', $cf['field_name']))) ?><?php if ($cf['is_required']): ?><span style="color:var(--danger);"> *</span><?php else: ?><span style="font-size:11px;color:var(--text-muted);font-weight:400;"> (optional)</span><?php endif; ?>
              </label>
              <?php if ($cf['field_type'] === 'textarea'): ?>
                <textarea id="<?= $cf_id ?>" name="<?= $cf_name ?>" class="form-control" rows="2"
                          <?= $cf['is_required'] ? 'required data-rules="required"' : '' ?>><?= e((string)$cf_val) ?></textarea>
              <?php elseif ($cf['field_type'] === 'select' && !empty($cf_opts)): ?>
                <select id="<?= $cf_id ?>" name="<?= $cf_name ?>" class="form-control"
                        <?= $cf['is_required'] ? 'required data-rules="required"' : '' ?>>
                  <option value="">— Select —</option>
                  <?php foreach ($cf_opts as $opt): ?>
                  <option value="<?= e($opt) ?>" <?= $cf_val === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($cf['field_type'] === 'checkbox'): ?>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:400;">
                  <input type="checkbox" id="<?= $cf_id ?>" name="<?= $cf_name ?>" value="1"
                         style="accent-color:var(--secondary);width:16px;height:16px;"
                         <?= $cf_val ? 'checked' : '' ?>>
                  <?= e($cf['field_name']) ?>
                </label>
              <?php elseif ($cf['field_type'] === 'date'): ?>
                <input type="date" id="<?= $cf_id ?>" name="<?= $cf_name ?>" class="form-control"
                       value="<?= e((string)$cf_val) ?>"
                       <?= $cf['is_required'] ? 'required data-rules="required"' : '' ?>>
              <?php elseif ($cf['field_type'] === 'number'): ?>
                <input type="number" id="<?= $cf_id ?>" name="<?= $cf_name ?>" class="form-control"
                       value="<?= e((string)$cf_val) ?>"
                       <?= $cf['is_required'] ? 'required data-rules="required"' : '' ?>>
              <?php else: /* text */ ?>
                <input type="text" id="<?= $cf_id ?>" name="<?= $cf_name ?>" class="form-control"
                       value="<?= e((string)$cf_val) ?>"
                       <?= $cf['is_required'] ? 'required data-rules="required"' : '' ?>>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

        </div><!-- /Visitor Information card -->
      </div><!-- /Left column -->

      <!-- ═══════════════════════════════════════════════════════
           RIGHT COLUMN — live badge preview
           ═══════════════════════════════════════════════════════ -->
      <div style="position:sticky;top:88px;">
        <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.06);padding:24px;">
          <h3 style="font-size:14px;font-weight:700;margin:0 0 20px;color:var(--text);display:flex;align-items:center;gap:8px;">
            <i class="bi bi-credit-card" style="color:var(--secondary);"></i>Badge Preview
          </h3>

          <!-- Badge card (80×120mm proportioned at screen scale ≈ 300×420px) -->
          <div id="badge-preview" style="
            width:300px; max-width:100%; margin:0 auto;
            background:#fff; border-radius:10px;
            box-shadow:0 4px 18px rgba(0,0,0,.18);
            overflow:hidden; font-family:var(--font-base);
            transition:transform .2s ease-out;
          ">
            <!-- Header bar -->
            <div id="badge-header" style="background:var(--primary);padding:14px 16px;text-align:center;">
              <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="Logo"
                   style="height:28px;filter:brightness(0) invert(1);vertical-align:middle;margin-right:8px;">
              <span style="color:#fff;font-size:13px;font-weight:700;vertical-align:middle;"><?= SITE_NAME ?></span>
            </div>
            <!-- Photo area -->
            <div style="padding:16px;display:flex;flex-direction:column;align-items:center;gap:12px;">
              <div id="badge-photo-wrap" style="width:80px;height:80px;border-radius:50%;overflow:hidden;flex-shrink:0;border:3px solid var(--border);">
                <div id="badge-initials-avatar"
                     style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--secondary),var(--accent));
                            color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;font-family:var(--font-base);">
                  <span id="badge-initials">?</span>
                </div>
                <img id="badge-photo-img" style="display:none;width:80px;height:80px;object-fit:cover;border-radius:50%;" alt="Photo">
              </div>
              <!-- VIP badge -->
              <span id="badge-vip-tag" style="display:none;background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;padding:2px 10px;border-radius:20px;border:1px solid #fcd34d;">
                ⭐ VIP VISITOR
              </span>
              <!-- Details -->
              <div style="text-align:center;width:100%;">
                <div id="badge-name" style="font-size:16px;font-weight:700;color:#1a202c;word-break:break-word;">—</div>
                <div id="badge-type" style="font-size:11px;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.06em;">Walk-In</div>
              </div>
              <div style="width:100%;font-size:12px;color:#374151;display:grid;grid-template-columns:auto 1fr;gap:4px 10px;">
                <span style="color:#64748b;">Purpose:</span> <span id="badge-purpose" style="font-weight:500;">—</span>
                <span style="color:#64748b;">Host:</span>    <span id="badge-host" style="font-weight:500;">—</span>
                <span style="color:#64748b;">Dept:</span>    <span id="badge-dept" style="font-weight:500;">—</span>
                <span style="color:#64748b;">Date:</span>    <span style="font-weight:500;"><?= date('d M Y') ?></span>
              </div>
            </div>
            <!-- QR placeholder -->
            <div style="padding:0 16px 16px;display:flex;flex-direction:column;align-items:center;gap:6px;">
              <div style="width:80px;height:80px;background:repeating-linear-gradient(45deg,#111 0,#111 2px,#fff 0,#fff 6px);opacity:.15;border-radius:4px;"></div>
              <span style="font-size:10px;color:#9ca3af;letter-spacing:.08em;">SCAN TO VERIFY</span>
            </div>
            <!-- Footer stripe -->
            <div id="badge-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:8px 16px;text-align:center;">
              <span style="font-size:10px;color:#94a3b8;letter-spacing:.06em;"><?= BADGE_PREFIX ?>-<?= date('ymd') ?>-XXXX</span>
            </div>
          </div>

          <p style="font-size:11px;color:var(--text-muted);margin:12px 0 0;text-align:center;">
            <i class="bi bi-info-circle" style="margin-right:4px;"></i>This is how the badge will be printed.
          </p>
        </div>
      </div><!-- /Right column -->

    </div><!-- /rv-grid -->

    <!-- ── Submit row ── -->
    <div style="margin-top:24px;">
      <button type="submit" id="rv-submit-btn" class="btn btn-primary btn-lg"
              style="width:100%;padding:14px;font-size:16px;font-weight:600;position:relative;">
        <span id="rv-btn-label">
          <i class="bi bi-person-check-fill"></i> Register &amp; Check In
        </span>
        <span id="rv-btn-spinner" style="display:none;">
          <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
          Registering…
        </span>
      </button>
      <p style="text-align:center;margin-top:10px;font-size:12px;color:var(--text-muted);">
        <a href="<?= BASE_URL ?>pages/dashboard.php" style="color:var(--text-muted);">← Back to Dashboard</a>
      </p>
    </div>

  </form>
</div><!-- /container -->

<?php
// Dept labels for live preview JS
$dept_map = [];
foreach ($departments as $d) $dept_map[(int)$d['id']] = $d['name'];
?>
<script>
var RV_DEPT_MAP = <?= json_encode($dept_map, JSON_UNESCAPED_UNICODE) ?>;
var RV_BASE_URL = <?= json_encode(BASE_URL) ?>;
var RV_VT_LABELS = <?= json_encode(['walk_in'=>'Walk-In','appointment'=>'Appointment','delivery'=>'Delivery','vendor'=>'Vendor','contractor'=>'Contractor','vip'=>'VIP']) ?>;

/* ── Live badge preview ── */
function rvUpdatePreview() {
  var name = (document.getElementById('full_name')?.value || '').trim();
  var host = (document.getElementById('person_to_meet')?.value || '').trim();
  var purp = (document.getElementById('purpose')?.value || '').trim();
  var deptId = parseInt(document.getElementById('department_id')?.value) || 0;
  var vtVal = document.getElementById('visitor_type')?.value || 'walk_in';

  // Name + initials
  document.getElementById('badge-name').textContent = name || '—';
  var parts = name.replace(/\s+/g,' ').split(' ');
  var initials = (parts[0]?.[0] || '').toUpperCase() + (parts[1]?.[0] || '').toUpperCase();
  document.getElementById('badge-initials').textContent = initials || '?';

  document.getElementById('badge-host').textContent    = host || '—';
  document.getElementById('badge-purpose').textContent = purp.length > 40 ? purp.slice(0,40)+'…' : (purp || '—');
  document.getElementById('badge-dept').textContent    = (deptId && RV_DEPT_MAP[deptId]) ? RV_DEPT_MAP[deptId] : '—';
  document.getElementById('badge-type').textContent    = RV_VT_LABELS[vtVal] || vtVal;

  // Animate preview
  var preview = document.getElementById('badge-preview');
  if (preview) { preview.style.transform = 'scale(1.01)'; setTimeout(function(){ preview.style.transform = ''; }, 200); }
}

/* ── VIP note toggle ── */
function rvVisitorTypeChange(sel) {
  var note = document.getElementById('vip-note');
  var vtag = document.getElementById('badge-vip-tag');
  var hdr  = document.getElementById('badge-header');
  if (sel.value === 'vip') {
    if (note) note.style.display = 'block';
    if (vtag) vtag.style.display = 'inline-block';
    if (hdr)  hdr.style.background = '#92400e';
  } else {
    if (note) note.style.display = 'none';
    if (vtag) vtag.style.display = 'none';
    if (hdr)  hdr.style.background = 'var(--primary)';
  }
}

/* ── Blacklist AJAX check ── */
var _blLocked = false;
function rvCheckBlacklist() {
  var phone = (document.getElementById('phone')?.value || '').trim();
  var cnic  = (document.getElementById('cnic')?.value  || '').trim();
  if (!phone && !cnic) return;

  var qs = [];
  if (phone) qs.push('phone=' + encodeURIComponent(phone));
  if (cnic)  qs.push('cnic='  + encodeURIComponent(cnic));

  fetch(RV_BASE_URL + 'api/blacklist_check.php?' + qs.join('&'), { credentials: 'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      var banner = document.getElementById('blacklist-banner');
      var icon   = document.getElementById('phone-check-icon');
      if (data.blacklisted) {
        _blLocked = true;
        if (banner) {
          document.getElementById('bl-title').textContent = 'Watchlist Match — severity: ' + (data.severity || 'HIGH').toUpperCase();
          document.getElementById('bl-detail').textContent = 'Reason: ' + (data.reason || 'On watchlist') + '. Registration is disabled.';
          banner.style.display = 'flex';
        }
        if (icon) { icon.innerHTML = '<i class="bi bi-slash-circle-fill" style="color:var(--danger);font-size:16px;"></i>'; icon.style.display = 'block'; }
        document.getElementById('rv-submit-btn').disabled = true;
      } else {
        _blLocked = false;
        if (banner) banner.style.display = 'none';
        if (icon) { icon.innerHTML = '<i class="bi bi-check-circle-fill" style="color:var(--success);font-size:16px;"></i>'; icon.style.display = 'block'; }
        document.getElementById('rv-submit-btn').disabled = false;
      }
    })
    .catch(function(){});
}

/* ── Submit spinner ── */
document.getElementById('rv-form')?.addEventListener('submit', function(e) {
  // run custom validation first
  var form = this;
  var valid = true;
  form.querySelectorAll('[data-rules]').forEach(function(el) {
    if (window.SVMSValidation && !SVMSValidation.validateField(el)) valid = false;
  });

  // check required selects / plain required
  form.querySelectorAll('[required]').forEach(function(el) {
    if (!el.value.trim()) {
      el.classList.add('is-invalid');
      valid = false;
    }
  });

  if (!valid) {
    e.preventDefault();
    var first = form.querySelector('.is-invalid');
    if (first) { first.scrollIntoView({behavior:'smooth',block:'center'}); first.focus(); }
    if (window.SVMS?.toast) SVMS.toast('Please fix the highlighted fields.', 'warning');
    return;
  }

  if (_blLocked) { e.preventDefault(); return; }

  document.getElementById('rv-btn-label').style.display  = 'none';
  document.getElementById('rv-btn-spinner').style.display = 'inline';
  document.getElementById('rv-submit-btn').disabled = true;
});

/* ── Photo source toggle ── */
document.querySelectorAll('[name="photo_source"]').forEach(function(radio) {
  radio.addEventListener('change', function() {
    var isWebcam = this.value === 'webcam';
    document.getElementById('webcam-panel').style.display  = isWebcam ? 'block' : 'none';
    document.getElementById('upload-panel').style.display  = isWebcam ? 'none' : 'block';
    if (!isWebcam && window.Webcam) Webcam.stop();
  });
});

/* ── Upload preview + drag & drop ── */
var _uploadInput = document.getElementById('photo_file');
var _dropZone    = document.getElementById('upload-drop-zone');

function showUploadPreview(file) {
  if (!file) return;
  var reader = new FileReader();
  reader.onload = function(ev) {
    var img = document.getElementById('upload-preview');
    img.src = ev.target.result;
    img.style.display = 'block';
    document.getElementById('webcam-hidden').value = '';
  };
  reader.readAsDataURL(file);
}

if (_uploadInput) {
  _uploadInput.addEventListener('change', function() { showUploadPreview(this.files[0]); });
}
if (_dropZone) {
  _dropZone.addEventListener('dragover', function(e) { e.preventDefault(); this.style.borderColor='var(--secondary)'; this.style.background='rgba(46,117,182,.04)'; });
  _dropZone.addEventListener('dragleave', function() { this.style.borderColor=''; this.style.background=''; });
  _dropZone.addEventListener('drop', function(e) {
    e.preventDefault(); this.style.borderColor=''; this.style.background='';
    var file = e.dataTransfer.files[0];
    if (file) { _uploadInput.files = e.dataTransfer.files; showUploadPreview(file); }
  });
  _dropZone.addEventListener('click', function() { _uploadInput.click(); });
}

/* ── Initial preview state ── */
document.addEventListener('DOMContentLoaded', rvUpdatePreview);
<?php if (($rv['visitor_type'] ?? '') === 'vip'): ?>
rvVisitorTypeChange(document.getElementById('visitor_type'));
<?php endif; ?>
</script>

<!-- Register Visitor page styles -->
<style>
@media (max-width:1024px) { .rv-grid { grid-template-columns:1fr !important; } }

.form-control.is-invalid { border-color: var(--danger); background: #fff5f5; }
.form-control.is-valid   { border-color: var(--success); }
.invalid-feedback { font-size:12px; color:var(--danger); margin-top:4px; display:flex; align-items:center; gap:4px; }
.invalid-feedback::before { content:'⚠'; }

@keyframes rv-shake {
  0%,100%{transform:translateX(0)}
  20%    {transform:translateX(-6px)}
  40%    {transform:translateX(6px)}
  60%    {transform:translateX(-4px)}
  80%    {transform:translateX(4px)}
}
.is-invalid { animation: rv-shake 0.4s ease; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
