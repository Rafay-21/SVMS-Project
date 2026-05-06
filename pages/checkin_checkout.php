<?php
/**
 * pages/checkin_checkout.php — Check-In / Check-Out Control Room v2
 * Tab A: Quick Check-In (returning visitor smart search)
 * Tab B: Process Check-Out (active visitor cards)
 * Tab C: New Walk-In (links to register_visitor.php)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_permission('checkin_visitor');

// ── PHP data: active visitors for Tab B ───────────────────────
$active_visits = query_all(
    "SELECT
        vl.id              AS visit_log_id,
        vl.visitor_id,
        vl.badge_number,
        vl.check_in_time,
        vl.department_id,
        vl.person_to_meet,
        vl.vehicle_number,
        vl.visitor_type,
        vl.registered_by,
        v.full_name,
        v.phone,
        v.photo_path,
        v.vip,
        d.name             AS dept_name,
        a.full_name        AS registered_by_name
     FROM visit_log vl
     JOIN visitors v   ON v.id  = vl.visitor_id
     LEFT JOIN departments d ON d.id = vl.department_id
     LEFT JOIN admins a      ON a.id = vl.registered_by
     WHERE vl.status = 'checked_in'
     ORDER BY vl.check_in_time ASC"
);

$departments = query_all("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name");
$my_admin_id = (int)$_SESSION['admin_id'];

$page_title = 'Check-In / Check-Out';
include __DIR__ . '/../includes/header.php';
?>

<!-- ── Check-Out Modal ──────────────────────────────────────── -->
<div class="modal-backdrop" id="checkout-modal" role="dialog" aria-modal="true"
     aria-labelledby="co-modal-title" aria-hidden="true" style="display:none;">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <h5 class="modal-title" id="co-modal-title">
        <i class="bi bi-box-arrow-right" style="color:var(--secondary);margin-right:6px;"></i>Confirm Check-Out
      </h5>
      <button class="modal-close" id="co-modal-close" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <!-- Visitor identity strip -->
      <div id="co-visitor-strip" style="display:flex;align-items:center;gap:14px;padding:12px 16px;
           background:var(--bg);border-radius:8px;border:1px solid var(--border);margin-bottom:16px;">
        <div id="co-photo-wrap" style="width:52px;height:52px;border-radius:50%;overflow:hidden;flex-shrink:0;
             background:linear-gradient(135deg,var(--secondary),var(--accent));
             display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#fff;">
          <span id="co-initials"></span>
          <img id="co-photo" style="display:none;width:52px;height:52px;object-fit:cover;border-radius:50%;" alt="">
        </div>
        <div>
          <div id="co-name" style="font-weight:700;font-size:15px;color:var(--text);"></div>
          <div id="co-meta" style="font-size:12px;color:var(--text-muted);margin-top:2px;"></div>
        </div>
      </div>

      <!-- Star rating -->
      <div style="margin-bottom:16px;">
        <label style="font-size:13px;font-weight:600;color:var(--text);display:block;margin-bottom:8px;">
          Visit Experience Rating <span style="font-weight:400;color:var(--text-muted);">(optional)</span>
        </label>
        <div id="star-rating" style="display:flex;gap:6px;" role="radiogroup" aria-label="Visit rating">
          <?php for ($s = 1; $s <= 5; $s++): ?>
          <button type="button" class="star-btn" data-star="<?= $s ?>"
                  aria-label="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>"
                  style="font-size:28px;color:var(--border);background:none;border:none;cursor:pointer;padding:2px;
                         transition:transform .1s,color .1s;line-height:1;">★</button>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Notes -->
      <div class="form-group">
        <label for="co-notes" style="font-size:13px;font-weight:600;">Notes <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
        <textarea id="co-notes" class="form-control" rows="2" maxlength="500"
                  placeholder="Any remarks about the visit…"></textarea>
      </div>
    </div>
    <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end;">
      <button class="btn btn-secondary" id="co-cancel-btn">Cancel</button>
      <button class="btn btn-primary" id="co-confirm-btn" style="min-width:140px;">
        <span id="co-btn-label"><i class="bi bi-box-arrow-right"></i> Confirm Check-Out</span>
        <span id="co-btn-spinner" style="display:none;">
          <span class="spinner-border spinner-border-sm" role="status"></span> Processing…
        </span>
      </button>
    </div>
  </div>
</div>

<!-- ── Page Container ──────────────────────────────────────── -->
<div class="container" id="cc-container">

  <!-- Page header -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:var(--space-5);">
    <div>
      <h1 style="font-size:1.4rem;font-weight:700;margin:0 0 4px;color:var(--text);">
        <i class="bi bi-door-open-fill" style="color:var(--secondary);margin-right:8px;"></i>Check-In / Check-Out
      </h1>
      <p style="font-size:13px;color:var(--text-muted);margin:0;">Control room for visitor arrivals and departures.</p>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
      <span style="font-size:12px;color:var(--text-muted);">
        <i class="bi bi-person-check-fill" style="color:var(--success);"></i>
        <strong id="active-count-header"><?= count($active_visits) ?></strong> active now
      </span>
    </div>
  </div>

  <!-- ── Tab Nav ──────────────────────────────────────────────── -->
  <div style="position:relative;margin-bottom:28px;">
    <div role="tablist" style="display:flex;gap:0;border-bottom:2px solid var(--border);position:relative;">
      <button role="tab" id="tab-checkin-btn" class="cc-tab active" data-tab="checkin" aria-selected="true"
              style="padding:12px 24px;font-size:14px;font-weight:600;border:none;background:none;cursor:pointer;
                     color:var(--text-muted);border-bottom:2px solid transparent;margin-bottom:-2px;
                     display:flex;align-items:center;gap:8px;transition:color .15s;">
        <i class="bi bi-box-arrow-in-right"></i>Quick Check-In
      </button>
      <button role="tab" id="tab-checkout-btn" class="cc-tab" data-tab="checkout" aria-selected="false"
              style="padding:12px 24px;font-size:14px;font-weight:600;border:none;background:none;cursor:pointer;
                     color:var(--text-muted);border-bottom:2px solid transparent;margin-bottom:-2px;
                     display:flex;align-items:center;gap:8px;transition:color .15s;">
        <i class="bi bi-box-arrow-right"></i>Process Check-Out
        <span id="tab-checkout-badge" style="
          background:var(--secondary);color:#fff;font-size:10px;font-weight:700;
          padding:1px 7px;border-radius:10px;<?= count($active_visits) === 0 ? 'display:none;' : '' ?>">
          <?= count($active_visits) ?>
        </span>
      </button>
      <a href="<?= BASE_URL ?>pages/register_visitor.php"
         style="padding:12px 24px;font-size:14px;font-weight:600;text-decoration:none;
                color:var(--text-muted);display:flex;align-items:center;gap:8px;
                border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s;">
        <i class="bi bi-person-plus"></i>New Walk-In
      </a>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════
       TAB A — QUICK CHECK-IN
       ═══════════════════════════════════════════════════════════ -->
  <div id="tab-checkin" role="tabpanel" aria-labelledby="tab-checkin-btn">

    <!-- Blacklist banner -->
    <div id="ci-bl-banner" style="display:none;padding:12px 16px;background:#fef2f2;border:1.5px solid var(--danger);
         border-radius:8px;margin-bottom:16px;align-items:flex-start;gap:12px;">
      <i class="bi bi-slash-circle-fill" style="color:var(--danger);font-size:20px;flex-shrink:0;margin-top:2px;"></i>
      <div>
        <strong style="color:var(--danger);font-size:14px;" id="ci-bl-title">Watchlist Match</strong>
        <p style="font-size:13px;color:#7f1d1d;margin:4px 0 0;" id="ci-bl-detail"></p>
      </div>
    </div>

    <!-- Smart search card -->
    <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;
         box-shadow:0 4px 12px rgba(0,0,0,.06);padding:24px;margin-bottom:24px;">

      <!-- Search input -->
      <div style="position:relative;">
        <i class="bi bi-search" style="position:absolute;left:18px;top:50%;transform:translateY(-50%);
           font-size:20px;color:var(--text-muted);pointer-events:none;"></i>
        <input type="search"
               id="ci-search"
               autocomplete="off"
               placeholder="Search by name, phone, CNIC, or scan QR…"
               style="width:100%;height:56px;padding:0 120px 0 52px;font-size:18px;
                      border:2px solid var(--border);border-radius:10px;background:var(--bg);
                      color:var(--text);outline:none;transition:border-color .15s;box-sizing:border-box;"
               aria-label="Search visitors"
               aria-owns="ci-dropdown"
               aria-autocomplete="list"
               aria-expanded="false">
        <button id="ci-qr-btn" type="button"
                style="position:absolute;right:8px;top:50%;transform:translateY(-50%);
                       padding:8px 14px;font-size:13px;font-weight:600;border-radius:7px;
                       background:var(--bg);border:1.5px solid var(--border);color:var(--text-muted);
                       cursor:pointer;display:flex;align-items:center;gap:6px;transition:all .15s;"
                aria-label="Scan QR code">
          <i class="bi bi-qr-code-scan"></i>Scan QR
        </button>
      </div>

      <!-- Results dropdown -->
      <div id="ci-dropdown" role="listbox" aria-label="Visitor search results"
           style="display:none;background:var(--card);border:1px solid var(--border);border-radius:8px;
                  box-shadow:0 8px 24px rgba(0,0,0,.12);margin-top:6px;overflow:hidden;
                  animation:ci-fadein .12s ease-out;">
        <!-- populated by JS -->
      </div>

      <!-- Shortcut hint -->
      <p style="font-size:12px;color:var(--text-muted);margin:10px 0 0;text-align:right;">
        Press <kbd style="font-size:11px;padding:1px 5px;border:1px solid var(--border);border-radius:3px;background:var(--bg);">Ctrl+K</kbd>
        to focus search from anywhere
      </p>
    </div>

    <!-- Check-In Panel (hidden until visitor selected) -->
    <div id="ci-panel" style="display:none;">
      <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;
           box-shadow:0 4px 12px rgba(0,0,0,.06);padding:24px;">

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
          <h3 style="font-size:14px;font-weight:700;margin:0;color:var(--text);display:flex;align-items:center;gap:8px;">
            <i class="bi bi-person-check" style="color:var(--success);"></i>Check-In Details
          </h3>
          <button type="button" id="ci-close-panel"
                  style="background:none;border:none;font-size:18px;color:var(--text-muted);cursor:pointer;padding:4px;">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <!-- Visitor identity card -->
        <div id="ci-identity" style="display:flex;align-items:center;gap:16px;padding:16px;
             background:var(--bg);border-radius:8px;border:1px solid var(--border);margin-bottom:20px;">
          <div id="ci-id-photo-wrap" style="width:64px;height:64px;border-radius:50%;overflow:hidden;flex-shrink:0;
               background:linear-gradient(135deg,var(--secondary),var(--accent));
               display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;">
            <span id="ci-id-initials"></span>
            <img id="ci-id-photo" style="display:none;width:64px;height:64px;object-fit:cover;" alt="">
          </div>
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <span id="ci-id-name" style="font-size:16px;font-weight:700;color:var(--text);"></span>
              <span id="ci-id-vip" style="display:none;background:#fef3c7;color:#92400e;font-size:10px;font-weight:700;
                    padding:2px 8px;border-radius:20px;border:1px solid #fcd34d;">⭐ VIP</span>
            </div>
            <div id="ci-id-phone" style="font-size:13px;color:var(--text-muted);margin-top:2px;"></div>
            <div id="ci-id-visits" style="font-size:12px;color:var(--text-muted);margin-top:2px;"></div>
          </div>
        </div>

        <!-- Editable mini-form -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;" class="ci-form-grid">
          <div class="form-group">
            <label for="ci-dept">Department</label>
            <select id="ci-dept" class="form-control">
              <option value="">— Select —</option>
              <?php foreach ($departments as $d): ?>
              <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="ci-person-meet">Person to Meet <span style="color:var(--danger);">*</span></label>
            <input type="text" id="ci-person-meet" class="form-control"
                   placeholder="e.g. Dr. Ahmed" maxlength="100">
          </div>
          <div class="form-group" style="grid-column:span 2;">
            <label for="ci-purpose">Purpose <span style="color:var(--danger);">*</span></label>
            <input type="text" id="ci-purpose" class="form-control"
                   placeholder="Meeting, Interview, Delivery…" maxlength="500">
          </div>
          <div class="form-group">
            <label for="ci-vehicle">Vehicle Number <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(optional)</span></label>
            <input type="text" id="ci-vehicle" class="form-control"
                   placeholder="ABC-123" maxlength="20" style="text-transform:uppercase;">
          </div>
          <div class="form-group">
            <label for="ci-type">Visitor Type</label>
            <select id="ci-type" class="form-control">
              <option value="walk_in">Walk-In</option>
              <option value="appointment">Appointment</option>
              <option value="delivery">Delivery</option>
              <option value="vendor">Vendor</option>
              <option value="contractor">Contractor</option>
              <option value="vip">VIP</option>
            </select>
          </div>
        </div>

        <!-- Submit -->
        <div style="display:flex;gap:12px;margin-top:20px;">
          <button type="button" id="ci-checkin-btn" class="btn btn-primary"
                  style="flex:1;padding:14px;font-size:15px;font-weight:600;">
            <span id="ci-btn-label"><i class="bi bi-box-arrow-in-right"></i> Check In</span>
            <span id="ci-btn-spinner" style="display:none;">
              <span class="spinner-border spinner-border-sm" role="status"></span> Checking in…
            </span>
          </button>
          <button type="button" id="ci-cancel-btn" class="btn btn-secondary" style="padding:14px 20px;">
            Cancel
          </button>
        </div>
        <p style="font-size:12px;color:var(--text-muted);margin:8px 0 0;text-align:center;">
          <kbd style="font-size:10px;padding:1px 5px;border:1px solid var(--border);border-radius:3px;background:var(--bg);">Ctrl+Enter</kbd>
          to check in quickly
        </p>
      </div>
    </div>

  </div><!-- /tab-checkin -->

  <!-- ═══════════════════════════════════════════════════════════
       TAB B — PROCESS CHECK-OUT
       ═══════════════════════════════════════════════════════════ -->
  <div id="tab-checkout" role="tabpanel" aria-labelledby="tab-checkout-btn" style="display:none;">

    <!-- Top bar: search + filter pills -->
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
      <div style="position:relative;flex:1;min-width:200px;">
        <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
        <input type="search" id="co-search"
               placeholder="Filter by name, badge, host…"
               style="width:100%;padding:10px 12px 10px 36px;border:1.5px solid var(--border);border-radius:8px;
                      background:var(--bg);color:var(--text);font-size:13px;outline:none;box-sizing:border-box;"
               aria-label="Filter active visitors">
      </div>
      <div style="display:flex;gap:6px;" role="group" aria-label="Filter options">
        <button class="co-filter-btn active" data-filter="all"
                style="padding:8px 14px;font-size:12px;font-weight:600;border-radius:20px;border:1.5px solid var(--secondary);
                       background:var(--secondary);color:#fff;cursor:pointer;">All</button>
        <button class="co-filter-btn" data-filter="overstay"
                style="padding:8px 14px;font-size:12px;font-weight:600;border-radius:20px;border:1.5px solid var(--border);
                       background:var(--bg);color:var(--text-muted);cursor:pointer;">
          <i class="bi bi-exclamation-triangle"></i> Overstaying (&gt;<?= MAX_VISIT_HOURS ?>h)
        </button>
        <button class="co-filter-btn" data-filter="mine"
                style="padding:8px 14px;font-size:12px;font-weight:600;border-radius:20px;border:1.5px solid var(--border);
                       background:var(--bg);color:var(--text-muted);cursor:pointer;">
          <i class="bi bi-person-check"></i> My Check-Ins
        </button>
      </div>
    </div>

    <!-- Cards grid -->
    <div id="co-cards-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
      <?php if (empty($active_visits)): ?>
      <div id="co-empty-state" style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--text-muted);">
        <i class="bi bi-inbox" style="font-size:48px;opacity:.3;display:block;margin-bottom:12px;"></i>
        <p style="font-size:15px;font-weight:600;margin:0 0 4px;">No active visitors</p>
        <p style="font-size:13px;margin:0;">All visitors have checked out.</p>
      </div>
      <?php else: ?>
      <?php foreach ($active_visits as $av):
        $initials = strtoupper(substr($av['full_name'], 0, 1));
        $parts    = explode(' ', $av['full_name']);
        if (isset($parts[1])) $initials .= strtoupper(substr($parts[1], 0, 1));
      ?>
      <div class="co-card"
           data-visit-id="<?= (int)$av['visit_log_id'] ?>"
           data-visitor-name="<?= e($av['full_name']) ?>"
           data-visitor-initials="<?= e($initials) ?>"
           data-visitor-phone="<?= e($av['phone']) ?>"
           data-photo-path="<?= e($av['photo_path'] ? BASE_URL . 'assets/uploads/' . $av['photo_path'] : '') ?>"
           data-checkin="<?= e($av['check_in_time']) ?>"
           data-badge="<?= e($av['badge_number']) ?>"
           data-dept="<?= e($av['dept_name'] ?? '') ?>"
           data-host="<?= e($av['person_to_meet']) ?>"
           data-vehicle="<?= e($av['vehicle_number']) ?>"
           data-registered-by="<?= (int)$av['registered_by'] ?>"
           style="background:var(--card);border:1px solid var(--border);border-radius:10px;
                  border-left:4px solid var(--primary);overflow:hidden;
                  transition:box-shadow .15s,transform .15s;position:relative;">
        <div style="padding:16px;">
          <!-- Header row -->
          <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;">
            <div style="width:44px;height:44px;border-radius:50%;overflow:hidden;flex-shrink:0;
                 background:linear-gradient(135deg,var(--secondary),var(--accent));
                 display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff;">
              <?php if ($av['photo_path']): ?>
              <img src="<?= BASE_URL ?>assets/uploads/<?= e($av['photo_path']) ?>"
                   style="width:44px;height:44px;object-fit:cover;" alt="">
              <?php else: ?>
              <?= e($initials) ?>
              <?php endif; ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <span style="font-weight:700;font-size:14px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <?= e($av['full_name']) ?>
                </span>
                <?php if ($av['vip']): ?>
                <span style="font-size:9px;font-weight:700;padding:1px 6px;border-radius:10px;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;flex-shrink:0;">⭐ VIP</span>
                <?php endif; ?>
              </div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= e($av['badge_number']) ?></div>
            </div>
            <!-- Elapsed chip -->
            <span class="elapsed-chip" data-checkin-time="<?= e($av['check_in_time']) ?>"
                  style="font-size:12px;font-weight:600;padding:3px 8px;border-radius:20px;white-space:nowrap;flex-shrink:0;">
              <?= time_elapsed($av['check_in_time']) ?>
            </span>
          </div>
          <!-- Details -->
          <div style="font-size:12px;color:var(--text-muted);display:grid;grid-template-columns:auto 1fr;gap:3px 10px;margin-bottom:12px;">
            <?php if ($av['dept_name']): ?>
            <span><i class="bi bi-building" style="margin-right:2px;"></i>Dept:</span>
            <span style="color:var(--text);font-weight:500;"><?= e($av['dept_name']) ?></span>
            <?php endif; ?>
            <span><i class="bi bi-person" style="margin-right:2px;"></i>Host:</span>
            <span style="color:var(--text);font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($av['person_to_meet']) ?></span>
            <?php if ($av['vehicle_number']): ?>
            <span><i class="bi bi-car-front" style="margin-right:2px;"></i>Vehicle:</span>
            <span style="color:var(--text);font-weight:500;"><?= e($av['vehicle_number']) ?></span>
            <?php endif; ?>
          </div>
          <!-- Check-Out button -->
          <button type="button" class="btn btn-primary btn-sm co-checkout-btn"
                  style="width:100%;padding:8px;font-size:13px;font-weight:600;"
                  data-visit-id="<?= (int)$av['visit_log_id'] ?>">
            <i class="bi bi-box-arrow-right"></i> Check Out
          </button>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div><!-- /tab-checkout -->

</div><!-- /container -->

<!-- Inline data for JS -->
<script>
var CC = {
  baseUrl:     <?= json_encode(BASE_URL) ?>,
  csrfToken:   <?= json_encode(csrf_token_for_js()) ?>,
  maxHours:    <?= MAX_VISIT_HOURS ?>,
  myAdminId:   <?= $my_admin_id ?>,
  deptMap:     <?= json_encode(array_column($departments, 'name', 'id'), JSON_UNESCAPED_UNICODE) ?>
};
</script>

<!-- Page-specific styles -->
<style>
/* Tab active state */
.cc-tab.active { color: var(--secondary) !important; border-bottom-color: var(--secondary) !important; }
.cc-tab:hover  { color: var(--text) !important; }

/* Dropdown fade-in */
@keyframes ci-fadein { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:none} }

/* Search dropdown row */
.ci-result-row {
  display: flex; align-items: center; gap: 12px; padding: 10px 14px; cursor: pointer;
  border-bottom: 1px solid var(--border); transition: background .1s;
}
.ci-result-row:last-child { border-bottom: none; }
.ci-result-row:hover, .ci-result-row.ci-focused { background: var(--bg); }
.ci-result-row .ci-photo {
  width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
  background: linear-gradient(135deg, var(--secondary), var(--accent));
  display: flex; align-items: center; justify-content: center; color: #fff;
  font-size: 13px; font-weight: 700; overflow: hidden;
}

/* Active visitor cards */
.co-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.1); transform: translateY(-2px); }
.co-card.co-overstay-warn { border-left-color: var(--warning) !important; }
.co-card.co-overstay-danger{ border-left-color: var(--danger)  !important; }
.co-card.co-hidden { display: none !important; }
.co-card.co-removing {
  animation: co-slideout .35s ease forwards;
}
@keyframes co-slideout {
  to { transform: translateX(60px); opacity: 0; max-height: 0; padding: 0; margin: 0; overflow: hidden; }
}

/* Check-out filter buttons */
.co-filter-btn.active { background: var(--secondary) !important; color: #fff !important; border-color: var(--secondary) !important; }

/* Star rating */
.star-btn.star-lit { color: #f59e0b !important; }
.star-btn:hover    { transform: scale(1.15); }

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
  .co-card, .co-card:hover, .co-card.co-removing, .ci-result-row { animation: none !important; transition: none !important; transform: none !important; }
}

/* Tablet responsive */
@media (max-width: 768px) {
  .ci-form-grid { grid-template-columns: 1fr !important; }
  .ci-form-grid .form-group { grid-column: span 1 !important; }
}
</style>

<script>
/* ============================================================
   checkin_checkout.js — inline module
   ============================================================ */
(function () {
  'use strict';

  /* ── Utility: debounce ──────────────────────────────────── */
  function debounce(fn, ms) {
    var t;
    return function () {
      var ctx = this, args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, ms);
    };
  }

  /* ── Utility: elapsed colour class ─────────────────────── */
  function updateElapsedChips() {
    document.querySelectorAll('.elapsed-chip[data-checkin-time]').forEach(function (chip) {
      var t = chip.dataset.checkinTime;
      if (!t) return;
      var diff  = (Date.now() - new Date(t).getTime()) / 1000;
      var hours = diff / 3600;
      var h = Math.floor(diff / 3600);
      var m = Math.floor((diff % 3600) / 60);
      chip.textContent = h > 0 ? h + 'h ' + m + 'm' : m + 'm';
      var card = chip.closest('.co-card');
      if (card) {
        card.classList.remove('co-overstay-warn', 'co-overstay-danger');
        if (hours > CC.maxHours * 2) card.classList.add('co-overstay-danger');
        else if (hours > CC.maxHours) card.classList.add('co-overstay-warn');
      }
      if (hours <= 2)          { chip.style.cssText = 'font-size:12px;font-weight:600;padding:3px 8px;border-radius:20px;color:#065f46;background:#d1fae5;white-space:nowrap;'; }
      else if (hours <= CC.maxHours) { chip.style.cssText = 'font-size:12px;font-weight:600;padding:3px 8px;border-radius:20px;color:#92400e;background:#fef3c7;white-space:nowrap;'; }
      else                     { chip.style.cssText = 'font-size:12px;font-weight:600;padding:3px 8px;border-radius:20px;color:#991b1b;background:#fee2e2;white-space:nowrap;'; }
    });
  }
  updateElapsedChips();
  var _elapsedInterval = setInterval(updateElapsedChips, 60000);

  /* ── Tab navigation ─────────────────────────────────────── */
  var currentTab = 'checkin';

  function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.cc-tab').forEach(function (btn) {
      var active = btn.dataset.tab === tab;
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.getElementById('tab-checkin').style.display  = (tab === 'checkin')  ? 'block' : 'none';
    document.getElementById('tab-checkout').style.display = (tab === 'checkout') ? 'block' : 'none';
    history.replaceState(null, '', '#' + tab);
    if (tab === 'checkin' && document.getElementById('ci-search')) {
      document.getElementById('ci-search').focus();
    }
  }

  document.querySelectorAll('.cc-tab').forEach(function (btn) {
    btn.addEventListener('click', function () { switchTab(this.dataset.tab); });
  });

  // Restore from hash
  var initHash = (window.location.hash || '#checkin').slice(1);
  if (initHash === 'checkout') switchTab('checkout');
  else switchTab('checkin');

  // Browser back/forward
  window.addEventListener('hashchange', function () {
    var h = window.location.hash.slice(1);
    if (h === 'checkin' || h === 'checkout') switchTab(h);
  });

  /* ── Tab unload: clear intervals ─────────────────────────── */
  window.addEventListener('beforeunload', function () {
    clearInterval(_elapsedInterval);
  });

  /* ══════════════════════════════════════════════════════════
     TAB A — QUICK CHECK-IN
     ══════════════════════════════════════════════════════════ */
  var ciSearch   = document.getElementById('ci-search');
  var ciDropdown = document.getElementById('ci-dropdown');
  var ciPanel    = document.getElementById('ci-panel');
  var _focusIdx  = -1;
  var _selected  = null; // {id, full_name, phone, cnic, photo_path, vip, ...}

  /* ── Smart search AJAX ─────────────────────────────────── */
  function doSearch(q) {
    if (q.length < 2) { closeDropdown(); return; }
    fetch(CC.baseUrl + 'api/smart_search.php?q=' + encodeURIComponent(q), {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.ok) return;
      renderDropdown(data.results);
    })
    .catch(function () {});
  }

  var _debouncedSearch = debounce(doSearch, 250);

  if (ciSearch) {
    ciSearch.addEventListener('input', function () {
      _debouncedSearch(this.value.trim());
    });
    ciSearch.addEventListener('keydown', function (e) {
      handleDropdownNav(e);
    });
    ciSearch.addEventListener('focus', function () {
      if (this.value.trim().length >= 2 && ciDropdown.children.length) {
        ciDropdown.style.display = 'block';
      }
    });
  }

  // QR scan button — just focuses the input
  var qrBtn = document.getElementById('ci-qr-btn');
  if (qrBtn) {
    qrBtn.addEventListener('click', function () {
      if (ciSearch) { ciSearch.focus(); ciSearch.select(); }
    });
  }

  /* ── Render dropdown ───────────────────────────────────── */
  function renderDropdown(results) {
    ciDropdown.innerHTML = '';
    _focusIdx = -1;

    if (!results || results.length === 0) {
      ciDropdown.innerHTML =
        '<div style="padding:16px 14px;text-align:center;">' +
          '<p style="font-size:13px;color:var(--text-muted);margin:0 0 8px;">No visitor found</p>' +
          '<a href="' + CC.baseUrl + 'pages/register_visitor.php" class="btn btn-primary btn-sm">' +
            '<i class="bi bi-person-plus-fill"></i> Register as New Walk-In' +
          '</a>' +
        '</div>';
      ciDropdown.style.display = 'block';
      ciSearch.setAttribute('aria-expanded', 'true');
      return;
    }

    results.forEach(function (r, idx) {
      var row = document.createElement('div');
      row.className = 'ci-result-row';
      row.setAttribute('role', 'option');
      row.setAttribute('tabindex', '-1');
      row.setAttribute('id', 'ci-opt-' + idx);

      var initials = r.full_name.trim().split(/\s+/).map(function(w){ return w[0]||''; }).slice(0,2).join('').toUpperCase();
      var photoHtml = r.photo_path
        ? '<img src="' + r.photo_path + '" style="width:36px;height:36px;object-fit:cover;border-radius:50%;" alt="">'
        : '<span>' + initials + '</span>';

      var lastVisitStr = r.last_visit
        ? '<span style="font-size:11px;color:var(--text-muted);">Last: ' + relTime(r.last_visit) + '</span>'
        : '<span style="font-size:11px;color:var(--text-muted);">No visits yet</span>';

      var blHtml = r.blacklisted
        ? '<span style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;background:#fef2f2;color:var(--danger);border:1px solid var(--danger);margin-left:4px;flex-shrink:0;">BLOCKED</span>'
        : '';
      var vipHtml = r.vip
        ? '<span style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;margin-left:4px;flex-shrink:0;">⭐ VIP</span>'
        : '';

      row.innerHTML =
        '<div class="ci-photo">' + photoHtml + '</div>' +
        '<div style="flex:1;min-width:0;">' +
          '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:4px;">' +
            '<span style="font-weight:700;font-size:13px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escHtml(r.full_name) + '</span>' +
            vipHtml + blHtml +
          '</div>' +
          '<div style="font-size:12px;color:var(--text-muted);">' + escHtml(r.phone) + '</div>' +
        '</div>' +
        '<div style="text-align:right;flex-shrink:0;">' +
          lastVisitStr +
          '<div style="font-size:11px;color:var(--text-muted);">' + r.total_visits + ' visit' + (r.total_visits !== 1 ? 's' : '') + '</div>' +
        '</div>';

      row.addEventListener('click',    function () { selectVisitor(r); });
      row.addEventListener('mousedown', function (e) { e.preventDefault(); }); // prevent blur
      ciDropdown.appendChild(row);
    });

    ciDropdown.style.display = 'block';
    ciSearch.setAttribute('aria-expanded', 'true');
  }

  /* ── Keyboard nav in dropdown ──────────────────────────── */
  function handleDropdownNav(e) {
    var rows = ciDropdown.querySelectorAll('.ci-result-row');
    if (!rows.length || ciDropdown.style.display === 'none') return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      _focusIdx = Math.min(_focusIdx + 1, rows.length - 1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      _focusIdx = Math.max(_focusIdx - 1, 0);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (_focusIdx >= 0 && rows[_focusIdx]) rows[_focusIdx].click();
      return;
    } else if (e.key === 'Escape') {
      closeDropdown(); return;
    } else return;

    rows.forEach(function (r, i) { r.classList.toggle('ci-focused', i === _focusIdx); });
    if (rows[_focusIdx]) rows[_focusIdx].scrollIntoView({ block: 'nearest' });
  }

  document.addEventListener('click', function (e) {
    if (!e.target.closest('#ci-dropdown') && !e.target.closest('#ci-search')) closeDropdown();
  });

  function closeDropdown() {
    ciDropdown.style.display = 'none';
    if (ciSearch) ciSearch.setAttribute('aria-expanded', 'false');
  }

  /* ── Select a visitor → expand panel ──────────────────── */
  function selectVisitor(r) {
    _selected = r;
    closeDropdown();
    if (ciSearch) { ciSearch.value = r.full_name; }

    // Blacklist check
    var blBanner = document.getElementById('ci-bl-banner');
    if (r.blacklisted && r.bl_severity && ['high','critical'].indexOf(r.bl_severity.toLowerCase()) >= 0) {
      document.getElementById('ci-bl-title').textContent = 'Watchlist Match — Severity: ' + r.bl_severity.toUpperCase();
      document.getElementById('ci-bl-detail').textContent = 'Reason: ' + (r.bl_reason || 'On watchlist') + '. Check-in is disabled.';
      blBanner.style.display = 'flex';
      document.getElementById('ci-checkin-btn').disabled = true;
    } else {
      blBanner.style.display = 'none';
      document.getElementById('ci-checkin-btn').disabled = false;
    }

    // Populate identity
    var initials = r.full_name.trim().split(/\s+/).map(function(w){ return w[0]||''; }).slice(0,2).join('').toUpperCase();
    document.getElementById('ci-id-initials').textContent = initials;

    var photoEl = document.getElementById('ci-id-photo');
    if (r.photo_path) {
      photoEl.src = r.photo_path;
      photoEl.style.display = 'block';
      document.getElementById('ci-id-initials').style.display = 'none';
    } else {
      photoEl.style.display = 'none';
      document.getElementById('ci-id-initials').style.display = '';
    }

    document.getElementById('ci-id-name').textContent  = r.full_name;
    document.getElementById('ci-id-phone').textContent = r.phone + (r.cnic ? ' · ' + r.cnic : '');
    document.getElementById('ci-id-visits').textContent = 'Visited ' + r.total_visits + ' time' + (r.total_visits !== 1 ? 's' : '') + ' before';

    var vipTag = document.getElementById('ci-id-vip');
    if (r.vip) { vipTag.style.display = 'inline'; if (document.getElementById('ci-type')) document.getElementById('ci-type').value = 'vip'; }
    else        { vipTag.style.display = 'none'; }

    // Pre-fill defaults from last visit
    if (r.last_dept_id && document.getElementById('ci-dept'))
      document.getElementById('ci-dept').value = r.last_dept_id;
    if (r.last_person_meet && document.getElementById('ci-person-meet'))
      document.getElementById('ci-person-meet').value = r.last_person_meet;
    if (r.last_purpose && document.getElementById('ci-purpose'))
      document.getElementById('ci-purpose').value = r.last_purpose;
    if (r.last_vehicle && document.getElementById('ci-vehicle'))
      document.getElementById('ci-vehicle').value = r.last_vehicle;

    ciPanel.style.display = 'block';
    ciPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  /* ── Close panel ─────────────────────────────────────────── */
  document.getElementById('ci-close-panel')?.addEventListener('click', closePanel);
  document.getElementById('ci-cancel-btn')?.addEventListener('click', closePanel);
  function closePanel() {
    ciPanel.style.display = 'none';
    _selected = null;
    if (ciSearch) { ciSearch.value = ''; ciSearch.focus(); }
    document.getElementById('ci-bl-banner').style.display = 'none';
  }

  /* ── Check-In submission ─────────────────────────────────── */
  document.getElementById('ci-checkin-btn')?.addEventListener('click', submitCheckin);

  function submitCheckin() {
    if (!_selected) return;

    var personMeet = (document.getElementById('ci-person-meet')?.value || '').trim();
    var purpose    = (document.getElementById('ci-purpose')?.value    || '').trim();

    if (!personMeet) {
      document.getElementById('ci-person-meet').focus();
      document.getElementById('ci-person-meet').classList.add('is-invalid');
      return;
    }
    if (!purpose) {
      document.getElementById('ci-purpose').focus();
      document.getElementById('ci-purpose').classList.add('is-invalid');
      return;
    }

    var payload = {
      csrf_token:     CC.csrfToken,
      visitor_id:     _selected.id,
      department_id:  parseInt(document.getElementById('ci-dept')?.value) || 0,
      person_to_meet: personMeet,
      purpose:        purpose,
      vehicle_number: (document.getElementById('ci-vehicle')?.value || '').trim().toUpperCase(),
      visitor_type:   document.getElementById('ci-type')?.value || 'walk_in',
    };

    document.getElementById('ci-btn-label').style.display  = 'none';
    document.getElementById('ci-btn-spinner').style.display = 'inline';
    document.getElementById('ci-checkin-btn').disabled = true;

    fetch(CC.baseUrl + 'api/checkin.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      document.getElementById('ci-btn-label').style.display  = 'inline';
      document.getElementById('ci-btn-spinner').style.display = 'none';
      document.getElementById('ci-checkin-btn').disabled = false;

      if (data.ok) {
        closePanel();
        if (window.SVMS && SVMS.toast)
          SVMS.toast(_selected.full_name + ' checked in! Badge: ' + data.badge_number, 'success');

        // Offer print badge
        var toastEl = document.querySelector('.svms-toast:last-child');
        if (toastEl && data.badge_url) {
          var printBtn = document.createElement('a');
          printBtn.href = data.badge_url;
          printBtn.target = '_blank';
          printBtn.style.cssText = 'font-size:11px;margin-left:8px;color:inherit;text-decoration:underline;font-weight:700;';
          printBtn.textContent = 'Print Badge';
          toastEl.querySelector('.toast-body')?.appendChild(printBtn);
        }

        // Add card to Tab B
        addActiveCard(payload, data, _selected);
        _selected = null;
      } else {
        if (window.SVMS && SVMS.toast) SVMS.toast(data.error || 'Check-in failed.', 'error');
      }
    })
    .catch(function () {
      document.getElementById('ci-btn-label').style.display  = 'inline';
      document.getElementById('ci-btn-spinner').style.display = 'none';
      document.getElementById('ci-checkin-btn').disabled = false;
      if (window.SVMS && SVMS.toast) SVMS.toast('Network error. Please try again.', 'error');
    });
  }

  /* ── Add newly-checked-in card to Tab B grid ─────────────── */
  function addActiveCard(payload, data, visitor) {
    var grid = document.getElementById('co-cards-grid');
    var empty = document.getElementById('co-empty-state');
    if (empty) empty.remove();

    var initials = visitor.full_name.trim().split(/\s+/).map(function(w){ return w[0]||''; }).slice(0,2).join('').toUpperCase();
    var now = new Date().toISOString().slice(0,19).replace('T',' ');
    var deptName = payload.department_id ? (CC.deptMap[payload.department_id] || '') : '';

    var card = document.createElement('div');
    card.className = 'co-card';
    card.dataset.visitId = data.visit_log_id;
    card.dataset.visitorName = visitor.full_name;
    card.dataset.visitorInitials = initials;
    card.dataset.visitorPhone = visitor.phone || '';
    card.dataset.photoPath = visitor.photo_path || '';
    card.dataset.checkin = now;
    card.dataset.badge = data.badge_number;
    card.dataset.dept = deptName;
    card.dataset.host = payload.person_to_meet;
    card.dataset.vehicle = payload.vehicle_number || '';
    card.dataset.registeredBy = CC.myAdminId;
    card.style.cssText = 'background:var(--card);border:1px solid var(--border);border-radius:10px;border-left:4px solid var(--primary);overflow:hidden;transition:box-shadow .15s,transform .15s;position:relative;';

    var photoHtml = visitor.photo_path
      ? '<img src="' + visitor.photo_path + '" style="width:44px;height:44px;object-fit:cover;" alt="">'
      : initials;

    card.innerHTML =
      '<div style="padding:16px;">' +
        '<div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;">' +
          '<div style="width:44px;height:44px;border-radius:50%;overflow:hidden;flex-shrink:0;background:linear-gradient(135deg,var(--secondary),var(--accent));display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff;">' + photoHtml + '</div>' +
          '<div style="flex:1;min-width:0;">' +
            '<span style="font-weight:700;font-size:14px;color:var(--text);">' + escHtml(visitor.full_name) + '</span>' +
            '<div style="font-size:11px;color:var(--text-muted);margin-top:2px;">' + escHtml(data.badge_number) + '</div>' +
          '</div>' +
          '<span class="elapsed-chip" data-checkin-time="' + now + '" style="font-size:12px;font-weight:600;padding:3px 8px;border-radius:20px;white-space:nowrap;flex-shrink:0;color:#065f46;background:#d1fae5;">0m</span>' +
        '</div>' +
        '<div style="font-size:12px;color:var(--text-muted);display:grid;grid-template-columns:auto 1fr;gap:3px 10px;margin-bottom:12px;">' +
          (deptName ? '<span>Dept:</span><span style="color:var(--text);font-weight:500;">' + escHtml(deptName) + '</span>' : '') +
          '<span>Host:</span><span style="color:var(--text);font-weight:500;">' + escHtml(payload.person_to_meet) + '</span>' +
        '</div>' +
        '<button type="button" class="btn btn-primary btn-sm co-checkout-btn" data-visit-id="' + data.visit_log_id + '" style="width:100%;padding:8px;font-size:13px;font-weight:600;">' +
          '<i class="bi bi-box-arrow-right"></i> Check Out' +
        '</button>' +
      '</div>';

    grid.insertBefore(card, grid.firstChild);
    wireCheckoutBtn(card.querySelector('.co-checkout-btn'));
    updateActiveCount(1);
  }

  /* ══════════════════════════════════════════════════════════
     TAB B — PROCESS CHECK-OUT
     ══════════════════════════════════════════════════════════ */

  /* ── Filter: search box ─────────────────────────────────── */
  var _activeFilter = 'all';

  document.getElementById('co-search')?.addEventListener('input', debounce(applyFilter, 200));

  document.querySelectorAll('.co-filter-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      _activeFilter = this.dataset.filter;
      document.querySelectorAll('.co-filter-btn').forEach(function (b) { b.classList.remove('active'); });
      this.classList.add('active');
      applyFilter();
    });
  });

  function applyFilter() {
    var q = (document.getElementById('co-search')?.value || '').trim().toLowerCase();
    document.querySelectorAll('.co-card').forEach(function (card) {
      var name    = (card.dataset.visitorName  || '').toLowerCase();
      var badge   = (card.dataset.badge        || '').toLowerCase();
      var host    = (card.dataset.host         || '').toLowerCase();
      var matchQ  = !q || name.includes(q) || badge.includes(q) || host.includes(q);

      var matchF  = true;
      if (_activeFilter === 'overstay') {
        var h = (Date.now() - new Date(card.dataset.checkin).getTime()) / 3600000;
        matchF = h > CC.maxHours;
      } else if (_activeFilter === 'mine') {
        matchF = parseInt(card.dataset.registeredBy) === CC.myAdminId;
      }
      card.classList.toggle('co-hidden', !(matchQ && matchF));
    });
  }

  /* ── Wire checkout buttons ──────────────────────────────── */
  document.querySelectorAll('.co-checkout-btn').forEach(wireCheckoutBtn);

  function wireCheckoutBtn(btn) {
    btn.addEventListener('click', function () {
      var visitId = parseInt(this.dataset.visitId);
      var card    = this.closest('.co-card');
      openCheckoutModal(visitId, card);
    });
  }

  /* ── Check-out modal ────────────────────────────────────── */
  var _coVisitId  = null;
  var _coCard     = null;
  var _coRating   = null;
  var coModal     = document.getElementById('checkout-modal');
  var coModalClose= document.getElementById('co-modal-close');
  var coCancel    = document.getElementById('co-cancel-btn');
  var coConfirm   = document.getElementById('co-confirm-btn');

  function openCheckoutModal(visitId, card) {
    _coVisitId = visitId;
    _coCard    = card;
    _coRating  = null;

    // Populate modal identity
    var name     = card.dataset.visitorName     || '';
    var initials = card.dataset.visitorInitials || name.slice(0,2).toUpperCase();
    var badge    = card.dataset.badge           || '';
    var checkin  = card.dataset.checkin         || '';
    var host     = card.dataset.host            || '';
    var photo    = card.dataset.photoPath       || '';

    document.getElementById('co-initials').textContent = initials;
    document.getElementById('co-name').textContent     = name;
    document.getElementById('co-meta').textContent     = badge + (host ? ' · Visiting ' + host : '') + (checkin ? ' · Checked in ' + relTime(checkin) : '');

    var coPhoto = document.getElementById('co-photo');
    if (photo) {
      coPhoto.src = photo; coPhoto.style.display = 'block';
      document.getElementById('co-initials').style.display = 'none';
    } else {
      coPhoto.style.display = 'none';
      document.getElementById('co-initials').style.display = '';
    }

    // Reset stars + notes
    resetStars();
    document.getElementById('co-notes').value = '';
    document.getElementById('co-btn-label').style.display  = 'inline';
    document.getElementById('co-btn-spinner').style.display = 'none';
    coConfirm.disabled = false;

    coModal.style.display = 'flex';
    coModal.setAttribute('aria-hidden', 'false');
    setTimeout(function () { coConfirm.focus(); }, 50);
  }

  function closeCheckoutModal() {
    coModal.style.display = 'none';
    coModal.setAttribute('aria-hidden', 'true');
    _coVisitId = null;
    _coCard    = null;
    _coRating  = null;
    resetStars();
  }

  if (coModalClose) coModalClose.addEventListener('click', closeCheckoutModal);
  if (coCancel)     coCancel.addEventListener('click',     closeCheckoutModal);
  coModal?.addEventListener('click', function (e) {
    if (e.target === coModal) closeCheckoutModal();
  });

  // Star rating — click, hover, and keyboard navigation
  var _starGroup = document.getElementById('star-rating');
  document.querySelectorAll('.star-btn').forEach(function (btn, idx, all) {
    btn.setAttribute('role', 'radio');
    btn.setAttribute('aria-checked', 'false');
    btn.setAttribute('tabindex', idx === 0 ? '0' : '-1');
    btn.addEventListener('click', function () {
      _coRating = parseInt(this.dataset.star);
      highlightStars(_coRating);
      all.forEach(function (b, i) {
        b.setAttribute('aria-checked', parseInt(b.dataset.star) === _coRating ? 'true' : 'false');
        b.setAttribute('tabindex', i === _coRating - 1 ? '0' : '-1');
      });
    });
    btn.addEventListener('mouseenter', function () {
      highlightStars(parseInt(this.dataset.star));
    });
    btn.addEventListener('mouseleave', function () {
      highlightStars(_coRating || 0);
    });
  });
  if (_starGroup) {
    _starGroup.addEventListener('keydown', function (e) {
      var allStars = Array.from(document.querySelectorAll('.star-btn'));
      var cur = allStars.findIndex(function (b) { return b.getAttribute('tabindex') === '0'; });
      var next = cur;
      if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
        e.preventDefault(); next = (cur + 1) % 5;
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
        e.preventDefault(); next = (cur + 4) % 5;
      } else if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault(); allStars[cur].click(); return;
      } else { return; }
      allStars.forEach(function (b, i) { b.setAttribute('tabindex', i === next ? '0' : '-1'); });
      allStars[next].focus();
    });
  }

  function highlightStars(n) {
    document.querySelectorAll('.star-btn').forEach(function (btn) {
      btn.classList.toggle('star-lit', parseInt(btn.dataset.star) <= n);
    });
  }
  function resetStars() {
    _coRating = null;
    highlightStars(0);
  }

  // Confirm checkout
  if (coConfirm) {
    coConfirm.addEventListener('click', function () {
      if (!_coVisitId) return;

      var payload = {
        csrf_token:   CC.csrfToken,
        visit_log_id: _coVisitId,
        rating:       _coRating,
        notes:        document.getElementById('co-notes').value.trim(),
      };

      document.getElementById('co-btn-label').style.display  = 'none';
      document.getElementById('co-btn-spinner').style.display = 'inline';
      coConfirm.disabled = true;

      fetch(CC.baseUrl + 'api/checkout.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload)
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          closeCheckoutModal();
          if (window.SVMS && SVMS.toast) SVMS.toast(data.success_message || 'Checked out successfully.', 'success');

          // Animate card removal
          if (_coCard) {
            _coCard.classList.add('co-removing');
            _coCard.addEventListener('animationend', function () {
              _coCard.remove();
              updateActiveCount(-1);
              if (!document.querySelectorAll('.co-card').length) {
                var grid = document.getElementById('co-cards-grid');
                grid.innerHTML =
                  '<div id="co-empty-state" style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--text-muted);">' +
                    '<i class="bi bi-inbox" style="font-size:48px;opacity:.3;display:block;margin-bottom:12px;"></i>' +
                    '<p style="font-size:15px;font-weight:600;margin:0 0 4px;">No active visitors</p>' +
                    '<p style="font-size:13px;margin:0;">All visitors have checked out.</p>' +
                  '</div>';
              }
            }, { once: true });
          }
        } else {
          document.getElementById('co-btn-label').style.display  = 'inline';
          document.getElementById('co-btn-spinner').style.display = 'none';
          coConfirm.disabled = false;
          if (window.SVMS && SVMS.toast) SVMS.toast(data.error || 'Check-out failed.', 'error');
        }
      })
      .catch(function () {
        document.getElementById('co-btn-label').style.display  = 'inline';
        document.getElementById('co-btn-spinner').style.display = 'none';
        coConfirm.disabled = false;
        if (window.SVMS && SVMS.toast) SVMS.toast('Network error. Please try again.', 'error');
      });
    });
  }

  /* ── Active count badge ─────────────────────────────────── */
  function updateActiveCount(delta) {
    var header = document.getElementById('active-count-header');
    var badge  = document.getElementById('tab-checkout-badge');
    if (header) {
      var n = Math.max(0, parseInt(header.textContent) + delta);
      header.textContent = n;
    }
    if (badge) {
      var m = Math.max(0, parseInt(badge.textContent) + delta);
      badge.textContent = m;
      badge.style.display = m > 0 ? 'inline' : 'none';
    }
  }

  /* ══════════════════════════════════════════════════════════
     KEYBOARD SHORTCUTS
     ══════════════════════════════════════════════════════════ */
  document.addEventListener('keydown', function (e) {
    // Ctrl+K — focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      switchTab('checkin');
      if (ciSearch) { ciSearch.focus(); ciSearch.select(); }
      return;
    }
    // Esc — close modal or dropdown
    if (e.key === 'Escape') {
      if (coModal && coModal.style.display !== 'none') { closeCheckoutModal(); return; }
      if (ciDropdown && ciDropdown.style.display !== 'none') { closeDropdown(); return; }
      if (ciPanel && ciPanel.style.display !== 'none') { closePanel(); return; }
    }
    // Ctrl+Enter in check-in panel
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      if (ciPanel && ciPanel.style.display !== 'none') { submitCheckin(); }
    }
  });

  /* ══════════════════════════════════════════════════════════
     HELPERS
     ══════════════════════════════════════════════════════════ */
  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function relTime(isoStr) {
    if (!isoStr) return '—';
    var diff = Math.floor((Date.now() - new Date(isoStr).getTime()) / 1000);
    if (diff < 60)    return 'just now';
    if (diff < 3600)  return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
  }

})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


$visitor_id = (int)($_GET['visitor_id'] ?? 0);
$search     = sanitize($_GET['q'] ?? '');

// Handle checkout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    csrf_validate();
    $vid = (int)($_POST['visit_id'] ?? 0);
    if ($vid > 0) {
        query_exec('UPDATE visits SET check_out_time=NOW(), status="checked_out" WHERE id=? AND check_out_time IS NULL', 'i', [$vid]);
        log_action('checkout', $vid);
        flash('success', 'Visitor checked out successfully.');
    }
    header('Location: ' . BASE_URL . 'pages/checkin_checkout.php');
    exit;
}

// Handle check-in POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
    csrf_validate();
    $vid  = (int)($_POST['visitor_id'] ?? 0);
    $host = sanitize($_POST['host'] ?? '');
    $dept = sanitize($_POST['department'] ?? '');
    $purp = sanitize($_POST['purpose'] ?? '');
    if ($vid > 0 && $host && $purp) {
        $admin_id = (int)$_SESSION['admin_id'];
        query_exec(
            'INSERT INTO visits (visitor_id, host_name, department, purpose, check_in_time, status, created_by) VALUES (?,?,?,?,NOW(),"checked_in",?)',
            'isssi', [$vid, $host, $dept, $purp, $admin_id]
        );
        log_action('checkin', $vid);
        flash('success', 'Visitor checked in successfully.');
    }
    header('Location: ' . BASE_URL . 'pages/checkin_checkout.php');
    exit;
}

$active_visits = query_all(
    'SELECT v.id AS visit_id, vis.name, vis.badge_number, vis.photo_path, v.host_name, v.purpose, v.check_in_time
     FROM visits v JOIN visitors vis ON vis.id = v.visitor_id
     WHERE v.status = "checked_in" ORDER BY v.check_in_time DESC LIMIT 50'
);

$pre_visitor = $visitor_id > 0 ? query_one('SELECT * FROM visitors WHERE id=? LIMIT 1', 'i', [$visitor_id]) : null;
?>
<div class="container">
  <div class="page-header">
    <div>
      <h1 class="page-title"><i class="bi bi-door-open-fill" style="color:var(--secondary);"></i> Check-In / Check-Out</h1>
      <p class="page-subtitle">Manage visitor arrivals and departures.</p>
    </div>
    <a href="<?= BASE_URL ?>pages/register_visitor.php" class="btn btn-primary"><i class="bi bi-person-plus-fill"></i> Register New</a>
  </div>

  <div class="grid-2">
    <!-- Active Visitors -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><span class="live-dot"></span>&nbsp; Active Visitors (<?= count($active_visits) ?>)</h3>
        <form method="GET" action="" style="display:flex;gap:8px;">
          <div class="search-input">
            <i class="bi bi-search"></i>
            <input type="text" name="q" class="form-control form-control" value="<?= e($search) ?>" placeholder="Search…" style="padding-left:34px;font-size:13px;padding-top:7px;padding-bottom:7px;">
          </div>
        </form>
      </div>
      <?php if (empty($active_visits)): ?>
        <div class="empty-state">
          <img src="<?= BASE_URL ?>assets/img/empty-state.svg" width="120" alt="">
          <h3>No Active Visitors</h3>
          <p>All visitors have checked out.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr><th>Visitor</th><th>Host</th><th>Duration</th><th>Action</th></tr>
            </thead>
            <tbody>
              <?php foreach ($active_visits as $v): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <img src="<?= $v['photo_path'] ? BASE_URL . 'assets/uploads/' . e($v['photo_path']) : BASE_URL . 'assets/img/default-avatar.svg' ?>"
                         class="avatar avatar-sm" alt="">
                    <div>
                      <div style="font-weight:600;"><?= e($v['name']) ?></div>
                      <div style="font-size:11px;color:var(--text-muted);"><?= e($v['badge_number']) ?></div>
                    </div>
                  </div>
                </td>
                <td><?= e($v['host_name']) ?></td>
                <td><?= time_elapsed($v['check_in_time']) ?></td>
                <td>
                  <form method="POST" action="" style="display:inline;">
                    <?php csrf_field() ?>
                    <input type="hidden" name="action"   value="checkout">
                    <input type="hidden" name="visit_id" value="<?= (int)$v['visit_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Check out this visitor?')">
                      <i class="bi bi-box-arrow-right"></i> Check Out
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Check-In Form -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="bi bi-box-arrow-in-right" style="color:var(--success);"></i> New Check-In</h3>
      </div>
      <div class="card-body">
        <?php if (!$pre_visitor): ?>
        <div class="form-group">
          <label>Search Visitor by Name / CNIC / Badge</label>
          <div class="search-input">
            <i class="bi bi-search"></i>
            <input type="text" id="visitor-search-input" class="form-control" placeholder="Start typing…" autocomplete="off" style="padding-left:34px;">
          </div>
          <div id="visitor-search-results" style="border:1px solid var(--border);border-radius:var(--radius-sm);margin-top:4px;display:none;max-height:200px;overflow-y:auto;background:var(--card);"></div>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="checkin-form" <?= !$pre_visitor ? 'style="display:none;"' : '' ?>>
          <?php csrf_field() ?>
          <input type="hidden" name="action" value="checkin">
          <input type="hidden" name="visitor_id" id="ci-visitor-id" value="<?= $pre_visitor ? (int)$pre_visitor['id'] : '' ?>">

          <?php if ($pre_visitor): ?>
          <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--bg-alt);border-radius:var(--radius-sm);margin-bottom:16px;">
            <img src="<?= $pre_visitor['photo_path'] ? BASE_URL . 'assets/uploads/' . e($pre_visitor['photo_path']) : BASE_URL . 'assets/img/default-avatar.svg' ?>"
                 class="avatar" alt="">
            <div>
              <div style="font-weight:600;"><?= e($pre_visitor['name']) ?></div>
              <div style="font-size:12px;color:var(--text-muted);"><?= e($pre_visitor['badge_number']) ?></div>
            </div>
          </div>
          <?php endif; ?>

          <div id="visitor-preview" style="display:none;padding:12px;background:var(--bg-alt);border-radius:var(--radius-sm);margin-bottom:16px;"></div>

          <div class="form-group">
            <label for="ci-host">Host Name <span class="required">*</span></label>
            <input type="text" id="ci-host" name="host" class="form-control" data-rules="required" required>
          </div>
          <div class="form-group">
            <label for="ci-dept">Department</label>
            <input type="text" id="ci-dept" name="department" class="form-control" placeholder="e.g. HR">
          </div>
          <div class="form-group">
            <label for="ci-purpose">Purpose <span class="required">*</span></label>
            <select id="ci-purpose" name="purpose" class="form-control" data-rules="required" required>
              <option value="">Select…</option>
              <option value="Meeting">Meeting</option>
              <option value="Interview">Interview</option>
              <option value="Delivery">Delivery</option>
              <option value="Maintenance">Maintenance</option>
              <option value="Personal">Personal</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <button type="submit" class="btn btn-success btn-block">
            <i class="bi bi-box-arrow-in-right"></i> Confirm Check-In
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var searchInput  = document.getElementById('visitor-search-input');
  var searchResults = document.getElementById('visitor-search-results');
  var form         = document.getElementById('checkin-form');
  var vidInput     = document.getElementById('ci-visitor-id');
  var preview      = document.getElementById('visitor-preview');

  if (!searchInput) return;

  searchInput.addEventListener('input', SVMS.debounce(function() {
    var q = this.value.trim();
    if (q.length < 2) { searchResults.style.display='none'; return; }
    SVMS.fetch('/svms/api/search_visitor.php?q=' + encodeURIComponent(q))
      .then(function(data) {
        searchResults.innerHTML = '';
        if (!data || !data.length) {
          searchResults.innerHTML = '<div style="padding:10px 14px;color:var(--text-muted);font-size:13px;">No visitors found.</div>';
          searchResults.style.display = 'block';
          return;
        }
        data.forEach(function(v) {
          var el = document.createElement('div');
          el.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--divider);transition:background .1s;';
          el.innerHTML = '<strong>' + v.name + '</strong> &nbsp;<span style="color:var(--text-muted);">' + v.badge_number + '</span>';
          el.addEventListener('mouseenter', function(){ this.style.background='var(--bg-alt)'; });
          el.addEventListener('mouseleave', function(){ this.style.background=''; });
          el.addEventListener('click', function() {
            vidInput.value = v.id;
            preview.innerHTML = '<div style="display:flex;align-items:center;gap:12px;"><img src="' + (v.photo_url || '/svms/assets/img/default-avatar.svg') + '" style="width:40px;height:40px;border-radius:50%;object-fit:cover;" alt=""><div><strong>' + v.name + '</strong><div style="font-size:11px;color:var(--text-muted);">' + v.badge_number + '</div></div></div>';
            preview.style.display = 'block';
            form.style.display = 'block';
            searchResults.style.display = 'none';
            searchInput.value = v.name;
          });
          searchResults.appendChild(el);
        });
        searchResults.style.display = 'block';
      }).catch(function(){});
  }, 300));
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
