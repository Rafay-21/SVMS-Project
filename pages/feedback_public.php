<?php
/**
 * pages/feedback_public.php — Public one-time visitor feedback form
 * Accessed via signed HMAC link: ?vid={visit_log_id}&tok={hmac}
 * Link is generated and emailed during checkout.
 */
require_once __DIR__ . '/../config.php';
// No auth required — public page

/* ── Token generation helper (also used in api/checkout.php) ── */
function feedback_hmac(int $visit_log_id): string {
    return hash_hmac('sha256', 'fb:' . $visit_log_id, APP_KEY);
}

/* ── Validate parameters ─────────────────────────────────────── */
$visit_log_id = (int)($_GET['vid'] ?? 0);
$token        = trim($_GET['tok'] ?? '');
$error        = null;
$already_done = false;
$visit        = null;

if ($visit_log_id <= 0 || !$token) {
    $error = 'This feedback link is invalid or has expired.';
} else {
    // Constant-time HMAC validation
    $expected = feedback_hmac($visit_log_id);
    if (!hash_equals($expected, $token)) {
        $error = 'This feedback link is invalid or has expired.';
    } else {
        // Load visit
        $visit = query_one(
            "SELECT vl.id, vl.badge_number, vl.check_in_time, vl.check_out_time,
                    v.full_name, v.email,
                    COALESCE(d.name,'—') AS dept_name,
                    vl.person_to_meet
             FROM visit_log vl
             JOIN visitors v   ON v.id  = vl.visitor_id
             LEFT JOIN departments d ON d.id = vl.department_id
             WHERE vl.id = ? LIMIT 1",
            'i', [$visit_log_id]
        );
        if (!$visit) {
            $error = 'Visit record not found.';
        } else {
            // Check if public feedback already submitted
            $already_done = (bool)query_one(
                "SELECT id FROM feedback WHERE visit_log_id=? AND source='visitor' LIMIT 1",
                'i', [$visit_log_id]
            );
        }
    }
}

/* ── Handle POST submission ───────────────────────────────────── */
$submitted = false;
$submit_error = null;
if (!$error && !$already_done && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating  = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
    $notes   = trim(substr($_POST['notes'] ?? '', 0, 1000));

    // Re-validate HMAC on POST (CSRF equivalent for public page)
    $post_token = trim($_POST['tok'] ?? '');
    $post_vid   = (int)($_POST['vid'] ?? 0);
    if (!hash_equals(feedback_hmac($post_vid), $post_token) || $post_vid !== $visit_log_id) {
        $submit_error = 'Security check failed. Please use the original link.';
    } elseif ($rating !== null && ($rating < 1 || $rating > 5)) {
        $submit_error = 'Please select a rating between 1 and 5.';
    } else {
        // Insert public feedback
        query_exec(
            "INSERT INTO feedback (visit_log_id, rating, notes, created_by, source, created_at)
             VALUES (?, ?, ?, NULL, 'visitor', NOW())",
            'iis',
            [$visit_log_id, $rating, $notes ?: null]
        );
        log_action('public_feedback', $visit_log_id, json_encode(['rating' => $rating, 'has_notes' => !empty($notes)]));
        $submitted    = true;
        $already_done = true;
    }
}

/* ── Language detection (basic, no session needed) ────────────── */
$lang = isset($_COOKIE['svms_lang']) && $_COOKIE['svms_lang'] === 'ur' ? 'ur' : 'en';
$dir  = $lang === 'ur' ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Rate Your Visit — <?= defined('SITE_NAME') ? e(SITE_NAME) : 'SVMS' ?></title>

  <!-- Dark mode flicker prevention -->
  <script>
    (function(){
      var m=localStorage.getItem('svms_theme_mode')||'light';
      var r=m==='system'?(window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light'):m;
      document.documentElement.setAttribute('data-theme',r);
    })();
  </script>

  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tokens.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/base.css">
  <style>
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--bg);
      color: var(--text);
      font-family: var(--font-base, system-ui, sans-serif);
      padding: 20px;
    }
    .fb-card {
      width: 100%;
      max-width: 480px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0,0,0,.08);
      overflow: hidden;
    }
    .fb-header {
      background: linear-gradient(135deg, #1B3A5C 0%, #2563eb 100%);
      color: #fff;
      padding: 28px 28px 20px;
    }
    .fb-header h1 { font-size: 1.25rem; font-weight: 700; margin: 0 0 4px; }
    .fb-header p  { font-size: 13px; opacity: .85; margin: 0; }
    .fb-body { padding: 28px; }
    .visit-strip {
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 12px 14px;
      margin-bottom: 24px;
      font-size: 13px;
    }
    .visit-strip strong { color: var(--text); }
    .visit-strip span   { color: var(--text-muted); }

    /* Star rating */
    .star-group { display: flex; gap: 8px; margin-bottom: 20px; }
    .star-btn {
      font-size: 36px;
      background: none;
      border: none;
      cursor: pointer;
      padding: 2px;
      color: var(--border);
      transition: color .1s, transform .1s;
      line-height: 1;
    }
    .star-btn.active,
    .star-btn:hover  { color: #f59e0b; transform: scale(1.15); }
    .star-btn:focus-visible {
      outline: 2px solid var(--primary);
      border-radius: 4px;
    }
    .star-label {
      font-size: 13px;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 8px;
      display: block;
    }
    .star-hint {
      font-size: 12px;
      color: var(--text-muted);
      min-height: 18px;
      margin-top: 4px;
    }

    /* Textarea */
    .fb-textarea {
      width: 100%;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      background: var(--bg);
      color: var(--text);
      font-size: 14px;
      padding: 10px 12px;
      resize: vertical;
      box-sizing: border-box;
      min-height: 80px;
      font-family: inherit;
      transition: border-color .15s;
    }
    .fb-textarea:focus {
      outline: none;
      border-color: var(--primary, #3B82F6);
      box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    }
    .char-counter { font-size: 11px; color: var(--text-muted); text-align: right; margin-top: 3px; }

    /* Submit button */
    .fb-btn {
      width: 100%;
      padding: 13px;
      font-size: 15px;
      font-weight: 600;
      background: #1B3A5C;
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 20px;
      transition: background .15s, opacity .15s;
    }
    .fb-btn:hover { background: #2563eb; }
    .fb-btn:disabled { opacity: .5; cursor: not-allowed; }

    /* Success / error states */
    .fb-success {
      text-align: center;
      padding: 40px 20px;
    }
    .fb-success .check-icon {
      font-size: 64px;
      margin-bottom: 16px;
      color: #22c55e;
    }
    .fb-success h2  { font-size: 1.3rem; font-weight: 700; margin: 0 0 8px; }
    .fb-success p   { color: var(--text-muted); font-size: 14px; }

    .fb-error {
      text-align: center;
      padding: 40px 20px;
    }
    .fb-error .err-icon { font-size: 64px; margin-bottom: 16px; color: #ef4444; }
    .fb-error h2  { font-size: 1.1rem; font-weight: 700; margin: 0 0 8px; }
    .fb-error p   { color: var(--text-muted); font-size: 14px; }

    .alert-err {
      background: #fef2f2;
      border: 1px solid #fca5a5;
      color: #991b1b;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 13px;
      margin-bottom: 16px;
    }

    label { font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px; color: var(--text); }
  </style>
</head>
<body>
<div class="fb-card">
  <div class="fb-header">
    <h1>⭐ Rate Your Visit</h1>
    <p><?= defined('SITE_NAME') ? e(SITE_NAME) : 'Smart Visitor Management System' ?></p>
  </div>

  <div class="fb-body">

    <?php if ($error): ?>
    <!-- ── Invalid / expired link ──────────────────────────── -->
    <div class="fb-error">
      <div class="err-icon">⚠️</div>
      <h2>Link Invalid</h2>
      <p><?= e($error) ?></p>
    </div>

    <?php elseif ($submitted): ?>
    <!-- ── Thank-you (just submitted) ─────────────────────── -->
    <div class="fb-success">
      <div class="check-icon">✅</div>
      <h2>Thank You!</h2>
      <p>Your feedback has been recorded. We appreciate you taking the time to share your experience.</p>
    </div>

    <?php elseif ($already_done): ?>
    <!-- ── Already submitted ──────────────────────────────── -->
    <div class="fb-success">
      <div class="check-icon" style="color:#64748b;">✓</div>
      <h2>Already Submitted</h2>
      <p>Feedback for this visit has already been recorded. Thank you!</p>
    </div>

    <?php else: ?>
    <!-- ── Feedback form ──────────────────────────────────── -->

    <!-- Visit strip -->
    <div class="visit-strip">
      <div><strong><?= e($visit['full_name']) ?></strong></div>
      <div style="margin-top:4px;">
        <span>Badge: <?= e($visit['badge_number']) ?></span> &nbsp;·&nbsp;
        <span>Host: <?= e($visit['person_to_meet']) ?></span>
        <?php if ($visit['dept_name'] && $visit['dept_name'] !== '—'): ?>
        &nbsp;·&nbsp; <span><?= e($visit['dept_name']) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($visit['check_in_time']): ?>
      <div style="margin-top:2px;color:var(--text-muted);">
        Visit: <?= format_datetime($visit['check_in_time'], 'M d, Y g:i A') ?>
        <?php if ($visit['check_out_time']): ?>
         → <?= format_datetime($visit['check_out_time'], 'g:i A') ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($submit_error): ?>
    <div class="alert-err"><?= e($submit_error) ?></div>
    <?php endif; ?>

    <form method="post" action="" id="fb-form" novalidate>
      <input type="hidden" name="vid" value="<?= $visit_log_id ?>">
      <input type="hidden" name="tok" value="<?= e($token) ?>">
      <input type="hidden" name="rating" id="rating-input" value="">

      <!-- Star rating -->
      <div>
        <span class="star-label">How was your experience? <span style="color:var(--danger);">*</span></span>
        <div class="star-group" role="radiogroup" aria-label="Visit rating" id="star-group">
          <?php $labels = ['Terrible','Poor','Okay','Good','Excellent']; ?>
          <?php for ($s = 1; $s <= 5; $s++): ?>
          <button type="button" class="star-btn" data-star="<?= $s ?>"
                  role="radio" aria-checked="false"
                  aria-label="<?= $s ?> — <?= $labels[$s-1] ?>"
                  tabindex="<?= $s === 1 ? '0' : '-1' ?>">★</button>
          <?php endfor; ?>
        </div>
        <div class="star-hint" id="star-hint" aria-live="polite"></div>
      </div>

      <!-- Notes -->
      <div style="margin-top:4px;">
        <label for="fb-notes">Additional Comments <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
        <textarea id="fb-notes" name="notes" class="fb-textarea"
                  maxlength="1000"
                  placeholder="Tell us more about your experience…"
                  oninput="document.getElementById('char-count').textContent=this.value.length"></textarea>
        <div class="char-counter"><span id="char-count">0</span>/1000</div>
      </div>

      <button type="submit" class="fb-btn" id="fb-submit" disabled>
        Submit Feedback
      </button>
    </form>

    <script>
    (function () {
      var hints = ['Terrible','Poor','Okay','Good','Excellent'];
      var selected = 0;
      var stars  = document.querySelectorAll('.star-btn');
      var input  = document.getElementById('rating-input');
      var hint   = document.getElementById('star-hint');
      var submit = document.getElementById('fb-submit');
      var group  = document.getElementById('star-group');

      function paint(active) {
        stars.forEach(function (btn, i) {
          var on = i < active;
          btn.classList.toggle('active', on);
          btn.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        hint.textContent = active ? hints[active - 1] : '';
        input.value = active || '';
        submit.disabled = !active;
      }

      stars.forEach(function (btn, idx) {
        btn.addEventListener('click', function () {
          selected = idx + 1;
          paint(selected);
          // Move roving tabindex
          stars.forEach(function (b, i) { b.tabIndex = i === idx ? 0 : -1; });
          btn.focus();
        });
        btn.addEventListener('mouseenter', function () { if (!selected) paint(idx + 1); });
        btn.addEventListener('mouseleave', function () { if (!selected) paint(0); });
      });

      // Keyboard navigation: arrow keys within the radiogroup
      group.addEventListener('keydown', function (e) {
        var cur = Array.from(stars).findIndex(function (b) { return b.tabIndex === 0; });
        if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
          e.preventDefault();
          var next = (cur + 1) % 5;
          stars.forEach(function (b, i) { b.tabIndex = i === next ? 0 : -1; });
          stars[next].focus();
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
          e.preventDefault();
          var prev = (cur + 4) % 5;
          stars.forEach(function (b, i) { b.tabIndex = i === prev ? 0 : -1; });
          stars[prev].focus();
        } else if (e.key === ' ' || e.key === 'Enter') {
          e.preventDefault();
          selected = cur + 1;
          paint(selected);
        }
      });

      // Prevent submit if no rating
      document.getElementById('fb-form').addEventListener('submit', function (e) {
        if (!selected) {
          e.preventDefault();
          hint.textContent = 'Please select a star rating.';
          hint.style.color = '#ef4444';
          stars[0].focus();
        }
      });
    })();
    </script>
    <?php endif; ?>

  </div><!-- /fb-body -->
</div><!-- /fb-card -->
</body>
</html>
