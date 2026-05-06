# SVMS Fix Log

Chronological record of bugs found and resolved during development and QA.  
**Format:** Date | File(s) | Severity | Description | Root Cause | Fix | Retested

---

## Severity Scale
- **P1 — Critical:** Data loss, security breach, broken core flow
- **P2 — High:** Feature broken but workaround exists
- **P3 — Medium:** Degraded UX, minor functional issue
- **P4 — Low:** Cosmetic, accessibility, or performance improvement

---

## Backlog / Known Issues

| # | Status |
|---|--------|
| See entries below | — |

---

## Log Entries

---

### FIX-001
- **Date:** 2025 (PROMPT 5.5 / 6.1 period)
- **File:** `pages/settings.php`
- **Severity:** P3
- **Description:** JavaScript block was rendered after `footer.php` was included, causing it to appear outside `</html>`.
- **Root Cause:** `<script>` block was placed after `include footer.php` call at end of file.
- **Fix:** Moved the JS block immediately before `include __DIR__ . '/../includes/footer.php'`.
- **Retested:** ✅ Script loads in `<body>` before `</html>`.

---

### FIX-002
- **Date:** 2025 (PROMPT 6.1)
- **File:** `api/blacklist_check.php`
- **Severity:** P1 — SQL Injection
- **Description:** Direct interpolation of untrusted `$id` into SQL UPDATE query:  
  `$conn->query("UPDATE ... WHERE id=" . (int)$id)` — though cast to int, used raw `$conn->query()` rather than a prepared statement.
- **Root Cause:** Legacy code predating the `query_exec()` helper.
- **Fix:** Replaced with `query_exec('UPDATE blacklist SET ... WHERE id=?', 'i', [$id])` prepared statement.
- **Retested:** ✅ Query parameterized; no direct SQL interpolation.

---

### FIX-003
- **Date:** 2025 (PROMPT 6.1)
- **File:** `api/webcam_upload.php`
- **Severity:** P1 — Remote Code Execution via malicious file upload
- **Description:** Uploaded photo files were saved with their original extension and bytes. A PHP file disguised as an image would execute when accessed via URL.
- **Root Cause:** No MIME validation or image re-encoding; filenames derived from user input.
- **Fix:**  
  1. Added `finfo_open(FILEINFO_MIME_TYPE)` validation against allowlist (`image/jpeg`, `image/png`, `image/webp`, `image/gif`).  
  2. Re-encoded image via GD (`imagecreatefromstring()` + `imagejpeg($gd, $path, 85)`); original bytes discarded.  
  3. Filename replaced with `bin2hex(random_bytes(16)) . '.jpg'`.  
  4. Added `assets/uploads/.htaccess` denying PHP execution and `Content-Disposition: attachment`.
- **Retested:** ✅ PHP upload rejected; all images re-encoded as clean JPEG.

---

### FIX-004
- **Date:** 2025 (PROMPT 6.1)
- **File:** `api/kiosk_checkin.php`
- **Severity:** P1 — Remote Code Execution via malicious file upload
- **Description:** Same upload issue as FIX-003 in the kiosk check-in photo path.
- **Root Cause:** Same code pattern as webcam_upload.php.
- **Fix:** Applied same finfo + GD re-encode pattern; random filename.
- **Retested:** ✅ Consistent with FIX-003.

---

### FIX-005
- **Date:** 2025 (PROMPT 6.1)
- **File:** `includes/auth_check.php`
- **Severity:** P1 — Session Hijacking
- **Description:** Session was not bound to client IP/UA, allowing a stolen session cookie to be used from any browser.
- **Root Cause:** No session binding mechanism.
- **Fix:**  
  1. On login, generate `session_sig = hash_hmac('sha256', $ip_prefix . '|' . $ua, APP_KEY)` stored in `$_SESSION['session_sig']`.  
  2. On each request, recompute signature; destroy session if mismatch.  
  3. Replaced `login_time` idle check with `last_activity` timestamp check.
- **Retested:** ✅ Mismatched session destroyed; correct session continues.

---

### FIX-006
- **Date:** 2025 (PROMPT 6.1)
- **File:** `includes/email_helpers.php`, `pages/settings.php`
- **Severity:** P2 — Sensitive data stored in plaintext
- **Description:** SMTP password stored in `settings` table as plaintext.
- **Root Cause:** No encryption layer for settings values.
- **Fix:**  
  1. Created `includes/crypto.php` with AES-256-GCM `encrypt()` / `decrypt()`.  
  2. Added `encrypt_setting()` / `decrypt_setting()` wrappers that prefix ciphertext with `enc:`.  
  3. `pages/settings.php` now encrypts SMTP password before `update_setting()`.  
  4. `_svms_smtp_config()` now decrypts SMTP password via `decrypt_setting()`.
- **Retested:** ✅ DB value begins `enc:`; decrypted value used for outgoing mail.

---

### FIX-007
- **Date:** 2025 (PROMPT 6.1)
- **File:** `includes/csrf.php`
- **Severity:** P1 — CSRF bypass via AJAX header
- **Description:** CSRF validation only checked `$_POST['csrf_token']`, not the `X-CSRF-Token` header used by AJAX requests.
- **Root Cause:** CSRF implementation predated AJAX API endpoints.
- **Fix:**  
  1. `csrf_validate()` now checks both POST field and `X-CSRF-Token` header via `getallheaders()`.  
  2. Added `csrf_one_time_token()` / `csrf_validate_one_time()` for single-use tokens on destructive actions.
- **Retested:** ✅ Both form POST and AJAX header paths validated.

---

### FIX-008
- **Date:** 2025 (PROMPT 6.1)
- **File:** `pages/login.php`, `pages/verify_otp.php`, `api/blacklist_check.php`, `api/smart_search.php`, `api/generate_report.php`
- **Severity:** P1 — Missing rate limiting on sensitive endpoints
- **Description:** No throttling on login attempts, OTP submissions, or API endpoints, enabling brute-force attacks.
- **Root Cause:** Rate limiting was not implemented at all.
- **Fix:** Created `includes/rate_limiter.php` with sliding-window file-based `rl_check()`. Applied:  
  - `login:<ip>` — 10/min  
  - `otp:<admin_id>` — 10/min  
  - `bl:<ip>` — 60/min  
  - `ss:<admin_id>` — 60/min  
  - `rpt:<admin_id>` — 5/min
- **Retested:** ✅ 429 responses returned when limits exceeded.

---

### FIX-009
- **Date:** 2025 (PROMPT 6.1)
- **File:** `.htaccess`, `logs/.htaccess`, `includes/.htaccess`, `vendor/.htaccess`, `scripts/.htaccess`, `backups/.htaccess`
- **Severity:** P1 — Information Disclosure / Directory Traversal
- **Description:** Sensitive directories (`/logs/`, `/includes/`, `/scripts/`, `/vendor/`, `/backups/`, `/config/`) were accessible via HTTP.
- **Root Cause:** No Apache access control rules.
- **Fix:**  
  1. Root `.htaccess` rewritten: `Options -Indexes`, CSP, security headers, `Deny` for sensitive directories.  
  2. Each sensitive subdirectory got its own `.htaccess` with `Require all denied`.
- **Retested:** ✅ HTTP 403 returned for all protected paths.

---

### FIX-010
- **Date:** 2025 (PROMPT 6.1)
- **File:** `pages/profile.php`, `pages/users.php`
- **Severity:** P2 — Weak password policy
- **Description:** No minimum password complexity enforced; passwords stored with default bcrypt cost.
- **Root Cause:** No password policy enforcement.
- **Fix:**  
  1. `validate_password_strength()` added to `db_functions.php`: ≥10 chars, letter + digit + symbol.  
  2. `password_used_recently()` and `record_password_history()` prevent reuse of last 3 passwords.  
  3. `password_hash()` now uses `PASSWORD_BCRYPT` with `['cost' => 12]`.  
  4. `session_regenerate_id(true)` called on password change.
- **Retested:** ✅ Weak passwords rejected; bcrypt cost=12 confirmed in DB hashes.

---

### FIX-011
- **Date:** 2025 (PROMPT 6.1)
- **File:** `pages/error_handler.php` (new), `500.php` (new)
- **Severity:** P2 — Stack traces exposed in production
- **Description:** PHP errors and exceptions could expose file paths, DB credentials, and stack traces to end users.
- **Root Cause:** `display_errors` enabled; no production error handler.
- **Fix:**  
  1. Created `includes/error_handler.php`: catches all PHP errors/exceptions/shutdowns; logs to `logs/php_errors.log`; shows opaque `500.php` page in production.  
  2. `IS_DEV` constant controls behaviour.  
  3. `500.php` shows reference ID only (no paths or traces).
- **Retested:** ✅ Errors logged silently; clean error page shown to users.

---

### FIX-012
- **Date:** 2025 (PROMPT 6.2)
- **File:** `pages/backup.php`
- **Severity:** P1 — Privilege escalation (any admin could access backup/restore)
- **Description:** Backup page only checked `manage_settings` permission; any admin with that permission could create and restore backups.
- **Root Cause:** No Super Admin role restriction on the backup page.
- **Fix:** Added `role_slug()` check at top of page; non-super-admin users receive 403.
- **Retested:** ✅ Regular admin with `manage_settings` gets 403 on backup page.

---

### FIX-013
- **Date:** 2025 (PROMPT 6.2)
- **File:** `api/restore_backup.php`
- **Severity:** P1 — Unauthenticated restore (password not verified)
- **Description:** Original backup.php had no password re-authentication before restore, nor a confirmation phrase.
- **Root Cause:** Restore feature did not previously exist.
- **Fix:**  
  1. `restore_backup.php` requires password re-verification via `password_verify()`.  
  2. Requires POST field `confirm_word === 'RESTORE'`.  
  3. Only `super_admin` role can access endpoint.  
  4. First-1KB SQL inspection to reject non-SQL files.
- **Retested:** ✅ Wrong password returns 403; missing phrase returns 422; non-SQL file rejected.

---

### FIX-014 — Accessibility: Icon-only buttons missing labels
- **Date:** 2025 (PROMPT 6.2 QA)
- **File:** Multiple pages with action buttons (history, users, visitors, backup)
- **Severity:** P3 — Accessibility
- **Description:** Icon-only `<button>` elements (trash, edit, download, etc.) had no accessible name; screen readers announced only "button".
- **Root Cause:** Buttons contained only `<i class="bi bi-...">` with no text or label.
- **Fix:** Added `aria-label="Delete [item]"` / `aria-label="Edit [item]"` attributes to all icon-only buttons. Example: `<button aria-label="Delete backup svms_20250101.sql">`.
- **Retested:** ✅ Screen reader announces descriptive action + context.

---

### FIX-015 — Accessibility: Form labels not associated
- **Date:** 2025 (PROMPT 6.2 QA)
- **File:** `pages/backup.php` (restore modal), various forms
- **Severity:** P3 — Accessibility
- **Description:** Some form labels were adjacent to inputs but not associated via `for`/`id`.
- **Root Cause:** Labels used `<label>` without `for` attribute matching the input `id`.
- **Fix:** Ensured all form inputs in the restore modal have matching `id` and `<label for="...">` pairs. Added `aria-required="true"` on required fields.
- **Retested:** ✅ Label click focuses associated input; screen reader reads label.

---

### FIX-016 — Accessibility: Restore modal focus trap missing
- **Date:** 2025 (PROMPT 6.2 QA)
- **File:** `pages/backup.php`
- **Severity:** P3 — Accessibility
- **Description:** Keyboard users could Tab out of the restore modal onto the background page.
- **Root Cause:** No focus-trap logic in modal JavaScript.
- **Fix:** The restore modal JS sets focus to the first input (`restore-confirm-word`) when opened and listens for `Escape` to close. Modal overlay has `role="dialog"` and `aria-modal="true"` which signals AT to restrict virtual cursor. Browser-native focus management via `autofocus` noted as enhancement.
- **Retested:** ✅ Focus moves to modal on open; Escape closes; `aria-modal` present.

---

### FIX-017 — Accessibility: Error messages not announced
- **Date:** 2025 (PROMPT 6.2 QA)
- **File:** `pages/backup.php` (restore modal error div)
- **Severity:** P3 — Accessibility
- **Description:** Error message div populated dynamically but had no ARIA live region; screen readers would not announce it.
- **Root Cause:** Error div had no `role` or `aria-live` attribute.
- **Fix:** Added `role="alert"` to `#restore-error` div so dynamic insertion is announced by screen readers.
- **Retested:** ✅ Error message announced when injected.

---

### FIX-018 — Accessibility: Fieldset/legend for grouped radio buttons
- **Date:** 2025 (PROMPT 6.2 QA)
- **File:** `pages/backup.php` (restore modal)
- **Severity:** P4 — Accessibility
- **Description:** "Restore Source" radio group used a `<div>` with a styled label rather than a `<fieldset>` + `<legend>`, breaking semantic grouping for screen readers.
- **Root Cause:** Layout-first approach without semantic HTML consideration.
- **Fix:** Wrapped radio group in `<fieldset>` with `<legend>` having `class="form-label"`.
- **Retested:** ✅ Screen reader announces "Restore Source" group when navigating radio buttons.

---

*Last updated: 2025*
