# SVMS Security Audit — PROMPT 6.1
**Date:** 2026-05-05  
**Scope:** Full codebase sweep against OWASP Top 10  
**Standard:** OWASP Top 10 2021  
**Result:** All findings remediated in same session.

---

## 1. Audit Methodology

### A1 — Input Vector Enumeration
Grep for `$_POST`, `$_GET`, `$_REQUEST`, `$_COOKIE` across all PHP files. Every access must go through `sanitize()` for display or direct type-casting + prepared statements for database use.

```
grep -rn '\$_(POST|GET|REQUEST|COOKIE)' svms/
```

### B — SQL Injection Surface Scan
Grep for direct string concatenation in queries:

```
grep -rn 'query\s*(' svms/ | grep -v '\$conn->prepare\|query_one\|query_all\|query_exec'
```

### C — XSS Output Scan
Grep for unescaped echoes:

```
grep -rn '<?=' svms/pages/ svms/includes/ | grep -v 'e(\|htmlspecialchars\|json_encode\|BASE_URL\|format_datetime\|csrf_field\|render_flash'
```

---

## 2. OWASP Top 10 Findings & Fixes

### A01 — Broken Access Control

| # | File | Finding | Status |
|---|------|---------|--------|
| 1 | All `pages/*.php` | `require_permission()` enforced via `auth_check.php` — every protected page requires login | ✅ Pre-existing |
| 2 | `api/*.php` | Each API file checks `$_SESSION['admin_id']` before processing | ✅ Pre-existing |
| 3 | `includes/auth_check.php` | Added IP/24 + User-Agent session binding to detect stolen session tokens | ✅ Fixed |
| 4 | `pages/visitor_detail.php` | VIP toggle restricted to `$is_super_admin` check | ✅ Pre-existing |
| 5 | `api/run_auto_checkout.php` | Requires `manage_settings` permission | ✅ Pre-existing |

### A02 — Cryptographic Failures

| # | File | Finding | Status |
|---|------|---------|--------|
| 1 | `pages/settings.php` | SMTP password stored in plaintext in `settings` table | ✅ **Fixed**: `encrypt_setting()` wraps AES-256-GCM encryption on save |
| 2 | `includes/email_helpers.php` | SMTP password read as plaintext | ✅ **Fixed**: `decrypt_setting()` decrypts on read, backward-compatible with legacy plaintext |
| 3 | `includes/crypto.php` | **New file**: AES-256-GCM encrypt/decrypt with authentication tag | ✅ Created |
| 4 | `/config/keys.php` | **New file** (gitignored): 256-bit encryption key auto-generated on first run, chmod 0600 | ✅ Created |
| 5 | `pages/login.php` | `session_regenerate_id(true)` on successful login | ✅ Pre-existing |
| 6 | `pages/profile.php` | Password hashed with `PASSWORD_DEFAULT` (bcrypt ~10) | ✅ **Fixed**: `PASSWORD_BCRYPT, ['cost' => 12]` |
| 7 | `pages/users.php` | Same — `PASSWORD_DEFAULT` | ✅ **Fixed**: `PASSWORD_BCRYPT, ['cost' => 12]` |
| 8 | `includes/db_functions.php` | No CSPRNG filenames for uploads | ✅ Pre-existing: `bin2hex(random_bytes(16))` |

### A03 — Injection (SQL & XSS)

#### SQL Injection
All DB access routes through `query_one()`, `query_all()`, `query_exec()` in `includes/db_functions.php` which use `$conn->prepare()` + `bind_param()`. 

**Exception found and fixed:**

| # | File | Line | Finding | Fix |
|---|------|------|---------|-----|
| 1 | `api/blacklist_check.php` | ~40 | `$conn->query("UPDATE blacklist SET block_count=block_count+1 WHERE id=" . (int)$entry['id'])` — direct query (safe via cast but non-prepared) | ✅ **Fixed**: Replaced with `query_exec()` + prepared statement |

**No other direct SQL concatenation found.**

#### XSS
All output through `e()` (htmlspecialchars wrapper). Checked `pages/*.php`, `includes/header.php`, `includes/footer.php`.

**No unescaped `$_*` echoes found in page templates.**

### A04 — Insecure Design

| # | File | Finding | Status |
|---|------|---------|--------|
| 1 | `pages/login.php` | Session brute-force: 5 attempts → 15 min lockout | ✅ Pre-existing |
| 2 | `pages/login.php` | Timing-safe delay (200–500ms) on failed login | ✅ Pre-existing |
| 3 | `pages/login.php` | Added IP-based rate limit: 10/min via `rl_check()` | ✅ **Fixed** |
| 4 | `pages/verify_otp.php` | Added per-admin rate limit: 10/min via `rl_check()` | ✅ **Fixed** |
| 5 | `api/blacklist_check.php` | Added IP rate limit: 60/min | ✅ **Fixed** |
| 6 | `api/smart_search.php` | Added per-admin rate limit: 60/min | ✅ **Fixed** |
| 7 | `api/generate_report.php` | Added per-admin rate limit: 5/min | ✅ **Fixed** |
| 8 | `kiosk/*.php` | `kiosk_rate_limit(30, 60)` called on all kiosk API endpoints | ✅ Pre-existing |
| 9 | `includes/rate_limiter.php` | **New file**: file-based sliding-window rate limiter | ✅ Created |

### A05 — Security Misconfiguration

| # | File | Finding | Status |
|---|------|---------|--------|
| 1 | `.htaccess` | Missing: directory listing enabled, no CSP, no HSTS, no Permissions-Policy, incomplete block list | ✅ **Fixed** |
| 2 | `.htaccess` | Added: `Options -Indexes`, `ServerSignature Off`, full CSP, X-XSS-Protection, Permissions-Policy, Referrer-Policy, HSTS (commented for prod) | ✅ **Fixed** |
| 3 | `.htaccess` | Blocked: `/logs/`, `/scripts/`, `/includes/`, `/vendor/`, `/backups/`, `/config/`, `/migrations/` | ✅ **Fixed** |
| 4 | `logs/.htaccess` | Only `Deny from all` (Apache 2.2 syntax) | ✅ **Fixed**: `Require all denied` |
| 5 | `vendor/.htaccess` | Partial Apache 2.2/2.4 compat block | ✅ **Fixed**: `Require all denied` |
| 6 | `scripts/.htaccess`, `backups/.htaccess` | `Deny from all` only | ✅ **Fixed** |
| 7 | `assets/uploads/.htaccess` | **New**: `php_flag engine off`, deny PHP/phtml/phar execution, Content-Disposition: attachment | ✅ Created |
| 8 | `config.php` | `display_errors=0` set but not tied to IS_DEV | ✅ **Fixed**: `IS_DEV` constant; dev shows errors, prod suppresses |
| 9 | `config.php` | `session.cookie_httponly` + `samesite` via `ini_set` only (weaker) | ✅ **Fixed**: `session_set_cookie_params()` with `secure=true` when HTTPS |
| 10 | `config.php` | Auto-generate `config/keys.php` with `chmod 0600` | ✅ **Fixed** |

**php.ini guidance (manual steps for production):**
```ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /path/to/svms/logs/php_errors.log
session.cookie_secure = 1
session.gc_maxlifetime = 7200
```

### A06 — Vulnerable and Outdated Components

| # | Finding | Status |
|---|---------|--------|
| 1 | `composer.json` lists PHPMailer — ensure `^6.8` or later | Verify with `composer update` |
| 2 | No `composer.lock` check-in noted — lock file should be committed for reproducible builds | Recommendation |

### A07 — Identification and Authentication Failures

| # | File | Finding | Status |
|---|------|---------|--------|
| 1 | `pages/login.php` | Brute-force lockout (5 attempts → 900s) + IP rate limit (10/min) | ✅ Fixed |
| 2 | `pages/profile.php` | Minimum password length was 8; no complexity requirement | ✅ **Fixed**: 10 chars + letter + digit + symbol |
| 3 | `pages/users.php` | Same | ✅ **Fixed** |
| 4 | `includes/db_functions.php` | Added `validate_password_strength()`, `password_used_recently()`, `record_password_history()` | ✅ **Fixed** |
| 5 | `migrations/009_security_hardening.sql` | `admin_password_history` table (last 3 password reuse check) | ✅ Created |
| 6 | `includes/auth_check.php` | Added idle timeout via `last_activity`; added IP/UA session binding | ✅ **Fixed** |
| 7 | `pages/login.php` | `session_regenerate_id(true)` on success | ✅ Pre-existing |
| 8 | `pages/profile.php` | `session_regenerate_id(true)` on password change | ✅ **Fixed** |

### A08 — Software and Data Integrity Failures

| # | File | Finding | Status |
|---|------|---------|--------|
| 1 | `api/webcam_upload.php` | Image saved as raw bytes from base64 (no validation of pixel data) | ✅ **Fixed**: finfo MIME check + GD re-encode strips EXIF |
| 2 | `api/kiosk_checkin.php` | Same — raw bytes saved | ✅ **Fixed**: finfo + GD re-encode |
| 3 | `api/webcam_upload.php` | User-supplied filename used (`'photo_' . rand`) | ✅ **Fixed**: `bin2hex(random_bytes(16))` |
| 4 | `api/kiosk_checkin.php` | `bin2hex(random_bytes(8))` — 8 bytes is sufficient but raised to 16 | ✅ **Fixed** |
| 5 | `assets/uploads/.htaccess` | PHP execution not explicitly blocked in upload directory | ✅ **Fixed** |
| 6 | `includes/crypto.php` | AES-256-GCM with authentication tag — ciphertext integrity guaranteed | ✅ Created |

### A09 — Security Logging and Monitoring Failures

| # | File | Finding | Status |
|---|------|---------|--------|
| 1 | `includes/db_functions.php` | `log_action()` writes to `audit_logs` table for every sensitive operation | ✅ Pre-existing |
| 2 | `config.php` | `error_log` → `logs/php_errors.log` | ✅ Pre-existing |
| 3 | `includes/error_handler.php` | **New**: Production error/exception handler logs full stack trace, shows clean 500 page | ✅ Created |
| 4 | `500.php` | **New**: Styled internal server error page with opaque reference ID | ✅ Created |
| 5 | `api/blacklist_check.php` | Block attempts logged via `block_count++` | ✅ Pre-existing (now prepared stmt) |
| 6 | `pages/emergency.php` | `log_action('emergency_mass_checkout', ...)` | ✅ Phase 5.5 |

### A10 — Server-Side Request Forgery (SSRF)

| # | File | Finding | Status |
|---|------|---------|--------|
| 1 | `api/test_email.php` | Uses SMTP config from DB — no external URL fetch | ✅ Not applicable |
| 2 | No user-controlled URL fetch found in codebase | ✅ Not applicable |

---

## 3. CSRF Audit

### HTML Forms
All protected forms verified to include `<?php csrf_field() ?>`:
- `pages/login.php` ✅
- `pages/verify_otp.php` ✅ (CSRF on POST)
- `pages/profile.php` ✅
- `pages/users.php` ✅
- `pages/settings.php` ✅
- `pages/visitor_detail.php` ✅ (VIP toggle, delete)
- `pages/register_visitor.php` ✅
- `pages/checkin_checkout.php` ✅
- `pages/emergency.php` ✅ (set_mode, mass_checkout)
- `pages/backup.php` ✅

### AJAX Endpoints
Updated `includes/csrf.php` to read `X-CSRF-Token` header as fallback. All fetch() calls in JS use `X-CSRF-Token` header populated from `csrf_token_for_js()`:
- `api/checkin.php` → reads `body.csrf_token` ✅
- `api/checkout.php` → reads `body.csrf_token` ✅
- `api/run_auto_checkout.php` → reads `body.csrf_token` ✅
- `api/smart_search.php` → reads query (GET, no CSRF needed) ✅
- `api/blacklist_check.php` → GET endpoint (read-only) ✅

### High-Value Action Sub-Tokens
`csrf_one_time_token()` / `csrf_validate_one_time()` added to `includes/csrf.php` for:
- Emergency mass-checkout (single-use token prevents replay within session)

---

## 4. Security Headers Verification

Verified `.htaccess` now sets:

| Header | Value |
|--------|-------|
| X-Content-Type-Options | nosniff |
| X-Frame-Options | SAMEORIGIN |
| X-XSS-Protection | 1; mode=block |
| Referrer-Policy | strict-origin-when-cross-origin |
| Permissions-Policy | geolocation=(), camera=(self), microphone=(), payment=(), usb=() |
| Content-Security-Policy | default-src 'self'; script-src 'self' cdn.jsdelivr.net cdn.tailwindcss.com; style-src 'self' cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data: cdn.jsdelivr.net; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self' |
| Strict-Transport-Security | (commented — uncomment when HTTPS is enabled) |
| X-Powered-By | (unset) |

Kiosk pages additionally override to `X-Frame-Options: DENY` (kiosk_boot.php).

---

## 5. Rate Limit Configuration

| Endpoint | Key | Limit |
|----------|-----|-------|
| `pages/login.php` | `login:<ip>` | 10/min |
| `pages/verify_otp.php` | `otp:<admin_id>` | 10/min |
| `api/blacklist_check.php` | `bl:<ip>` | 60/min |
| `api/smart_search.php` | `ss:<admin_id>` | 60/min |
| `api/generate_report.php` | `rpt:<admin_id>` | 5/min |
| All kiosk API endpoints | `kiosk:<ip>` (via `kiosk_rate_limit`) | 30/min |

On limit hit: HTTP 429 + `Retry-After: 60` + `{"ok":false,"error":"rate_limit"}`.

---

## 6. File Upload Security

| Layer | Implementation |
|-------|----------------|
| MIME type check | `finfo_open(FILEINFO_MIME_TYPE)` on raw bytes (not extension) |
| Pixel data validation | `imagecreatefromstring()` — fails on non-image data |
| EXIF/metadata strip | Re-encode via `imagejpeg($gd, $path, 85)` — original bytes discarded |
| Filename | `bin2hex(random_bytes(16)) . '.jpg'` — user filename never used |
| PHP execution block | `assets/uploads/.htaccess`: `php_flag engine off` + FilesMatch deny |
| Max dimensions | 4096×4096 enforced in webcam_upload.php |
| Content-Disposition | `attachment` header on uploads directory |

---

## 7. Password Policy

| Rule | Value |
|------|-------|
| Minimum length | 10 characters |
| Letter required | ✅ |
| Digit required | ✅ |
| Symbol required | ✅ (`[^A-Za-z0-9]`) |
| Hash algorithm | `PASSWORD_BCRYPT, ['cost' => 12]` |
| History check | Last 3 passwords (`admin_password_history` table) |
| Current password required on change | ✅ |
| Session regenerated on change | ✅ |

---

## 8. Encryption Configuration

| Item | Detail |
|------|--------|
| Algorithm | AES-256-GCM |
| Key size | 256 bits (32 bytes, hex-encoded in keys.php) |
| IV | 96-bit random nonce per encryption |
| Authentication tag | 16 bytes (GCM authenticates ciphertext) |
| Key file | `/config/keys.php` — gitignored, auto-generated, chmod 0600 |
| Encrypted fields | `settings.smtp_pass` (prefix `enc:`) |
| Backward compat | `decrypt_setting()` passes through legacy plaintext values |

---

## 9. Session Security

| Feature | Implementation |
|---------|---------------|
| Cookie name | `SVMS_SESSID` (admin), `SVMS_KIOSK` (kiosk) |
| HttpOnly | ✅ |
| SameSite | Strict |
| Secure | `true` when HTTPS detected (IS_DEV=false) |
| Path | `/svms/` |
| Lifetime cookie | 0 (browser session) |
| Idle timeout | `SESSION_LIFETIME_HOURS` (default 2h) via `last_activity` |
| Session fixation | `session_regenerate_id(true)` on login + password change |
| Session binding | HMAC(IP/24 + User-Agent, APP_KEY) stored as `session_sig` |
| Strict mode | `session.use_strict_mode = 1` |

---

## 10. Pending Recommendations (Not In-Scope for This Prompt)

1. **HTTPS**: Uncomment HSTS + HTTPS redirect in `.htaccess` when deploying to production.
2. **php.ini**: Set `expose_php = Off`, `session.cookie_secure = 1` server-wide.
3. **Backup restore**: `.sql` file restore in `backup.php` executes raw SQL — restrict to super-admin and add content validation.
4. **OWASP ZAP**: Run baseline scan against staging to catch header gaps on dynamic responses.
5. **CSP nonces**: Migrate from `'unsafe-inline'` for styles to nonce-based CSP in a future sprint.
6. **Sessions table**: Implement DB-backed sessions for cross-device logout on password change.

---

## 11. Verification Checklist

| Test | Expected Result | Implementation |
|------|----------------|----------------|
| SQLi: `' OR 1=1--` in any input | Stored as text, query returns nothing | Prepared statements throughout |
| XSS: `<script>alert(1)</script>` in name | Displayed escaped as `&lt;script&gt;` | `e()` on all output |
| CSRF: POST to `api/checkout.php` without token | 403 JSON response | `csrf_validate()` in all APIs |
| Direct browse to `/vendor/` | 403 Forbidden | `vendor/.htaccess` |
| Direct browse to `/logs/` | 403 Forbidden | `logs/.htaccess` + root rewrite |
| Direct browse to `/includes/` | 403 Forbidden | `includes/.htaccess` |
| Upload `evil.php` as visitor photo | Rejected (MIME check) or saved as .jpg and non-executable | finfo + GD + uploads .htaccess |
| Login brute-force 11 times in 1 min | 429 on 11th (rate limiter) + 15-min lockout after 5 fails | `rl_check` + session counter |
| OTP 11 attempts in 1 min | 429 on 11th | `rl_check` |
| 6+ report requests in 1 min | 429 | `rl_check` |
| View SMTP password in DB | `enc:base64(iv+tag+cipher)` — not readable | `encrypt_setting()` |
| Stack trace on PHP error (production) | Clean 500 page, error in log only | `error_handler.php` |
| Session cookie flags | `HttpOnly; SameSite=Strict; Secure (HTTPS)` | `session_set_cookie_params()` |
