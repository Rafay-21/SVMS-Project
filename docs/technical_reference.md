# SVMS Technical Reference
**Smart Visitor Management System — Version 2.0**  
Audience: Developers, System Integrators, System Administrators

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Folder Structure](#2-folder-structure)
3. [Authentication & RBAC Flow](#3-authentication--rbac-flow)
4. [Database Schema Reference](#4-database-schema-reference)
5. [API Reference](#5-api-reference)
6. [Cron Jobs Reference](#6-cron-jobs-reference)
7. [Configuration Reference](#7-configuration-reference)
8. [Helper Function Reference](#8-helper-function-reference)
9. [Logging Conventions](#9-logging-conventions)
10. [How to Add a New Page](#10-how-to-add-a-new-page)
11. [How to Add a New Email Template](#11-how-to-add-a-new-email-template)
12. [Coding Standards](#12-coding-standards)

---

## 1. Architecture Overview

```
┌──────────────────────────────────────────────────────────┐
│                      Browser / Tablet                    │
│            HTML + CSS + Vanilla JS  (PWA)                │
└────────────────────────┬─────────────────────────────────┘
                         │ HTTPS
┌────────────────────────▼─────────────────────────────────┐
│               Apache 2.4  (mod_rewrite, mod_headers)     │
│               .htaccess  →  HSTS, CSP, no-sniff, etc.   │
└──────┬─────────────────┬────────────────────────┬────────┘
       │                 │                        │
  pages/*.php       api/*.php              kiosk/*.php
  (HTML views)   (JSON endpoints)        (self-service)
       │                 │                        │
       └────────┬────────┘                        │
                │  require config.php             │
┌───────────────▼─────────────────────────────────▼───────┐
│                    config.php                            │
│  Constants → includes/ helpers → DB connection          │
└──────────┬────────────────────────────┬─────────────────┘
           │                            │
    ┌──────▼──────┐              ┌──────▼──────┐
    │   MySQL 8   │              │  File System │
    │  svms_db    │              │  logs/       │
    │  17 tables  │              │  assets/     │
    └─────────────┘              │  uploads/    │
                                 └─────────────┘
                                        │
                    ┌───────────────────▼──────────────┐
                    │  Cron (Linux crontab / Task Sched)│
                    │  scripts/cron_*.php               │
                    └───────────────────┬──────────────┘
                                        │
                                 ┌──────▼──────┐
                                 │  SMTP Server │
                                 └─────────────┘
```

**Key design decisions:**

- **No framework** — vanilla PHP with procedural helpers. Low dependency surface, easy to audit.
- **Single entry point per page** — each `pages/*.php` file requires `config.php` at the top.
- **All API calls return JSON** with consistent `{ok: bool, ...}` shape.
- **Security headers** are enforced in `.htaccess` (CSP, HSTS, X-Frame-Options, etc.).
- **Rate limiting** is file-based (no Redis required) in `logs/rate_limits/`.
- **Encryption at rest** for sensitive settings (SMTP passwords) via AES-256-CBC.

---

## 2. Folder Structure

```
svms/
├── .htaccess                    Apache security rules + URL rewrites
├── config.php                   Bootstraps the entire application
├── index.php                    Root redirect (dashboard or login)
├── 404.php  500.php             Custom error pages
├── manifest.json  sw.js         PWA manifest + service worker
├── offline.html                 PWA offline fallback
├── composer.json                PHP dependencies (tcpdf)
│
├── api/                         25 JSON API endpoints
│   ├── checkin.php
│   ├── checkout.php
│   ├── create_backup.php
│   ├── restore_backup.php
│   └── ... (22 more)
│
├── assets/
│   ├── css/
│   ├── js/
│   ├── img/
│   └── uploads/                 Visitor photos  (.htaccess blocks PHP)
│
├── config/
│   └── keys.php                 Auto-generated ENCRYPTION_KEY (gitignored)
│
├── docs/                        All project documentation
│
├── includes/
│   ├── auth_check.php           Session & role gate
│   ├── rbac.php                 can() / require_permission() helpers
│   ├── db.php                   MySQLi connect + query helpers
│   ├── helpers.php              General helpers (e, sanitize, flash, etc.)
│   ├── crypto.php               encrypt/decrypt, password history
│   ├── rate_limiter.php         rl_check() / rl_abort() sliding window
│   ├── email.php                queue_email() + send helper
│   ├── i18n.php                 __() translation helper
│   └── lang/
│       ├── en.php               English strings
│       └── ur.php               Urdu strings
│
├── kiosk/                       Public-facing self-service terminal
│   └── index.php
│
├── logs/
│   ├── php_errors.log
│   ├── cron.log
│   ├── rate_limits/             rl_<sha256(key)>.json bucket files
│   └── backups/                 Backup .sql.gz files
│
├── migrations/
│   ├── 004_email_queue.sql
│   ├── 005_appointments_calendar.sql
│   ├── 006_blacklist_notifications.sql
│   ├── 007_admin_preferences.sql
│   ├── 008_features_v5_5.sql
│   ├── 009_security_hardening.sql
│   └── 010_backup_table.sql
│
├── pages/                       22 HTML/PHP page views
│   ├── login.php
│   ├── dashboard.php
│   ├── register_visitor.php
│   └── ... (19 more)
│
├── scripts/                     CLI cron scripts
│   ├── cron_email_queue.php
│   ├── cron_appointment_reminders.php
│   ├── cron_appointment_no_show.php
│   ├── cron_auto_checkout.php
│   ├── cron_daily_backup.php
│   ├── cron_daily_digest.php
│   └── regenerate_badges.php
│
└── vendor/                      Composer packages (.htaccess blocks access)
```

---

## 3. Authentication & RBAC Flow

### Login Flow

```
POST pages/login.php
  → CSRF check (hash_equals)
  → rl_check('login:<ip>', 10)     # 10 attempts / 60 sec
  → SELECT admin by username
  → password_verify()
  → if 2FA enabled: redirect to verify_otp.php
      → rl_check('otp:<admin_id>', 5)
      → validate OTP (hash_equals, expiry check)
  → session_regenerate(true)
  → $_SESSION['admin_id'], ['role_id'], ['role_slug'], ['admin_name']
  → redirect dashboard.php
```

### Auth Guard (`includes/auth_check.php`)

Every `pages/*.php` file begins with:

```php
require_once __DIR__ . '/../config.php';
require_once INCLUDES . '/auth_check.php';
```

`auth_check.php` verifies:
1. Session cookie name is `SVMS_SESSID` (separate from kiosk `SVMS_KIOSK`).
2. `$_SESSION['admin_id']` exists and is a positive integer.
3. Admin is still active in the database.
4. Session lifetime has not exceeded `SESSION_LIFETIME_HOURS`.
5. If any check fails: `session_destroy()` + redirect to login.

### Permission Check (`includes/rbac.php`)

```php
// Check without blocking
can('manage_settings')    // bool

// Check with 403 abort
require_permission('manage_settings');

// Get current role slug
role_slug()   // 'super_admin' | 'admin' | 'receptionist'
```

Permissions are loaded from `roles.permissions` JSON column into `$_SESSION['permissions']`
at login time. `can()` reads from the session — no per-request DB query.

---

## 4. Database Schema Reference

> Full `CREATE TABLE` DDL is in `docs/database_setup.sql`.  
> Column details are in `docs/database_setup.md`.

### Foreign Key Map

```
admins.role_id              → roles.id
admin_password_history.admin_id  → admins.id  (CASCADE DELETE)
visitors.department_id      → departments.id  (SET NULL)
appointments.visitor_id     → visitors.id     (SET NULL)
appointments.department_id  → departments.id  (SET NULL)
visit_log.visitor_id        → visitors.id
visit_log.appointment_id    → appointments.id (SET NULL)
custom_field_values.field_id → custom_fields.id (CASCADE DELETE)
feedback.visit_id           → visit_log.id    (SET NULL)
feedback.visitor_id         → visitors.id     (SET NULL)
```

### Key Indexes

| Table | Index | Purpose |
|-------|-------|---------|
| `visitors` | `cnic`, `phone`, `name` | Smart search lookups |
| `visit_log` | `(status, check_in_time)` | Dashboard live count |
| `appointments` | `(scheduled_at, status)` | Calendar range queries |
| `email_queue` | `(status, scheduled_at)` | Cron pickup |
| `audit_logs` | `(admin_id, action, created_at)` | Log filtering |
| `blacklist` | `cnic`, `phone` | Check-in gate lookups |

---

## 5. API Reference

All API endpoints live in `api/`. Authentication: valid `SVMS_SESSID` session cookie required unless noted.

CSRF protection: Every state-changing endpoint validates either:
- `$_POST['csrf_token']` against `$_SESSION['csrf_token']` (form submissions), or
- `X-CSRF-Token` header / JSON body `csrf_token` field (AJAX).

Standard JSON response shape:
```json
{
  "ok": true,
  "message": "Human-readable result",
  ...additional fields...
}
```

Error response:
```json
{
  "ok": false,
  "error": "Description of what went wrong"
}
```

---

### `GET /api/get_stats.php`
Returns live KPI counts for the dashboard.

**Auth:** Any logged-in admin  
**Response:**
```json
{
  "active_visitors": 3,
  "today_visits": 12,
  "total_visitors": 1547,
  "pending_appointments": 5
}
```

---

### `POST /api/checkin.php`
Check in a visitor.

**Auth:** `checkin_visitor` permission  
**Body (form or JSON):**
```json
{
  "visitor_id": 42,
  "host_name": "Director Admin",
  "department_id": 1,
  "purpose": "Meeting",
  "vehicle_number": "",
  "csrf_token": "..."
}
```
**Response:** `{ok, visit_id, message}`

---

### `POST /api/checkout.php`
Check out a visitor.

**Auth:** `checkout_visitor` permission  
**Body:** `{visit_id, remarks, csrf_token}`  
**Response:** `{ok, duration_minutes, message}`

---

### `POST /api/register_visitor.php`
Register a new visitor. Supports multipart/form-data for photo upload.

**Auth:** `register_visitor` permission  
**Fields:** `name`, `cnic`, `phone`, `email`, `organization`, `department_id`, `notes`, `photo` (file), `csrf_token`  
**Response:** `{ok, visitor_id, badge_number, message}`

---

### `GET /api/search_visitors.php?q=<term>`
Smart search across name, CNIC, phone, badge number.

**Auth:** Any logged-in admin  
**Response:** `{ok, results: [{id, name, cnic, phone, badge_number, last_visit}]}`

---

### `POST /api/save_appointment.php`
Create or update an appointment.

**Auth:** `manage_appointments` permission  
**Body:** appointment fields + optional `id` for update + `csrf_token`  
**Response:** `{ok, appointment_id, message}`

---

### `GET /api/get_appointments.php`
List appointments for the calendar.

**Auth:** `manage_appointments` permission  
**Query params:** `start=<ISO date>`, `end=<ISO date>`, `department_id=<int>`  
**Response:** `{ok, appointments: [{id, title, start, end, status, colour}]}`

---

### `POST /api/scan_qr.php`
Process a QR token scan (visitor badge or appointment e-pass).

**Auth:** Any logged-in admin  
**Body:** `{token, csrf_token}`  
**Response:** `{ok, type: 'visitor'|'appointment', data: {...}}`

---

### `POST /api/add_blacklist.php`
Add a person to the blacklist.

**Auth:** `manage_blacklist` permission  
**Body:** `{name, cnic, phone, reason, severity, source, expiry_date, csrf_token}`  
**Response:** `{ok, id, message}`

---

### `POST /api/remove_blacklist.php`
Deactivate a blacklist entry.

**Auth:** `manage_blacklist` permission  
**Body:** `{id, removed_reason, csrf_token}`  
**Response:** `{ok, message}`

---

### `GET /api/check_blacklist.php?cnic=<cnic>&phone=<phone>`
Check if a CNIC or phone is blacklisted.

**Auth:** `checkin_visitor` or `register_visitor` permission  
**Response:** `{ok, blocked: bool, entry: {name, severity, reason} | null}`

---

### `POST /api/save_settings.php`
Update one or more settings keys.

**Auth:** `manage_settings` permission  
**Body:** `{settings: {key: value, ...}, csrf_token}`  
**Response:** `{ok, message}`

---

### `POST /api/test_smtp.php`
Send a test email using current SMTP settings.

**Auth:** `manage_settings` permission  
**Body:** `{csrf_token}`  
**Response:** `{ok, message}`

---

### `POST /api/save_admin.php`
Create or update an admin user.

**Auth:** `manage_users` permission  
**Body:** `{id?, name, username, email, password?, role_id, is_active, csrf_token}`  
**Response:** `{ok, admin_id, message}`

---

### `POST /api/terminate_session.php`
Force-logout a specific admin session.

**Auth:** `manage_users` + Super Admin  
**Body:** `{admin_id, csrf_token}`  
**Response:** `{ok, message}`

---

### `POST /api/save_custom_field.php`
Create or update a custom form field.

**Auth:** `manage_custom_fields` permission  
**Body:** field definition + `csrf_token`  
**Response:** `{ok, field_id, message}`

---

### `GET /api/get_notifications.php`
Fetch unread notifications for the current admin.

**Auth:** Any logged-in admin  
**Response:** `{ok, count, notifications: [{id, type, title, message, link, created_at}]}`

---

### `POST /api/mark_notifications_read.php`
Mark all notifications as read.

**Auth:** Any logged-in admin  
**Body:** `{csrf_token}`  
**Response:** `{ok}`

---

### `POST /api/trigger_emergency.php`
Activate emergency mode.

**Auth:** `manage_emergency` permission  
**Body:** `{mode: 'evacuation'|'lockdown', notes, csrf_token}`  
**Response:** `{ok, snapshot_id, visitor_count, message}`

---

### `POST /api/clear_emergency.php`
Deactivate emergency mode.

**Auth:** `manage_emergency` permission  
**Body:** `{csrf_token}`  
**Response:** `{ok, message}`

---

### `GET /api/get_visit_history.php`
Paginated visit history with filters.

**Auth:** `view_history` permission  
**Query params:** `from`, `to`, `department_id`, `status`, `q`, `page`, `per_page`  
**Response:** `{ok, total, page, per_page, rows: [...]}`

---

### `GET /api/export_csv.php`
Download visit history as CSV.

**Auth:** `generate_reports` permission  
**Query params:** same as `get_visit_history.php` + `download=1`  
**Response:** CSV file stream (Content-Disposition attachment)

---

### `POST /api/save_feedback.php`
Save a feedback rating for a visit.

**Auth:** `manage_feedback` permission (or valid public token)  
**Body:** `{visit_id, rating, comment, csrf_token}`  
**Response:** `{ok, message}`

---

### `POST /api/create_backup.php`
Trigger a manual database backup.

**Auth:** `manage_settings` permission  
**Body:** `{csrf_token}` (JSON)  
**Response:** `{ok, filename, size_bytes, message}`

---

### `POST /api/restore_backup.php`
Restore the database from a backup file.

**Auth:** Super Admin only  
**Body:** `{csrf_token, source: 'existing'|'upload', filename?, password, confirm_word: 'RESTORE'}`  
**Response:** `{ok, message}` (session is destroyed on success)

---

## 6. Cron Jobs Reference

All scripts enforce `php_sapi_name() !== 'cli'` — they refuse HTTP requests.

| Script | Schedule | Purpose |
|--------|----------|---------|
| `cron_email_queue.php` | `*/5 * * * *` | Process up to 50 pending emails from `email_queue` |
| `cron_appointment_reminders.php` | `0 * * * *` | Send reminder emails for appointments 24h out |
| `cron_appointment_no_show.php` | `*/30 * * * *` | Mark appointments as `no_show` if 2h past scheduled time |
| `cron_auto_checkout.php` | `0 * * * *` | Check out visitors still active after `MAX_VISIT_HOURS` |
| `cron_daily_backup.php` | `0 3 * * *` | Create automated DB backup; prune old files |
| `cron_daily_digest.php` | `0 7 * * *` | Email daily summary to Super Admins |

**Example crontab (Linux):**
```cron
*/5 * * * *  php /var/www/html/svms/scripts/cron_email_queue.php        >> /var/www/html/svms/logs/cron.log 2>&1
0   * * * *  php /var/www/html/svms/scripts/cron_appointment_reminders.php >> /var/www/html/svms/logs/cron.log 2>&1
*/30 * * * * php /var/www/html/svms/scripts/cron_appointment_no_show.php  >> /var/www/html/svms/logs/cron.log 2>&1
0   * * * *  php /var/www/html/svms/scripts/cron_auto_checkout.php        >> /var/www/html/svms/logs/cron.log 2>&1
0   3 * * *  php /var/www/html/svms/scripts/cron_daily_backup.php         >> /var/www/html/svms/logs/cron.log 2>&1
0   7 * * *  php /var/www/html/svms/scripts/cron_daily_digest.php         >> /var/www/html/svms/logs/cron.log 2>&1
```

---

## 7. Configuration Reference

All constants are defined in `config.php` (and `config/keys.php` for secrets).

| Constant | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `localhost` | MySQL host |
| `DB_USER` | `root` | MySQL username |
| `DB_PASS` | `''` | MySQL password |
| `DB_NAME` | `svms_db` | Database name |
| `BASE_URL` | `http://localhost/svms/` | Full URL with trailing slash |
| `BADGE_PREFIX` | `VIS` | Badge number prefix |
| `SESSION_LIFETIME_HOURS` | `8` | Auto-logout after N hours |
| `OTP_EXPIRY_MINUTES` | `10` | OTP validity window |
| `ENABLE_2FA` | `true` | Enforce 2FA for all admins |
| `DEFAULT_LANG` | `en` | `en` or `ur` |
| `DEFAULT_THEME` | `light` | `light`, `dark`, `system` |
| `MAX_VISIT_HOURS` | `8` | Auto-checkout threshold |
| `LOG_DIR` | `__DIR__ . '/logs'` | Log directory path |
| `UPLOAD_DIR` | `__DIR__ . '/assets/uploads/'` | Visitor photo storage |
| `IS_DEV` | `true` | Dev mode — shows full errors |
| `APP_KEY` | auto-generated | HMAC key for tokens |
| `ENCRYPTION_KEY` | auto-generated | AES-256-CBC key for settings |

---

## 8. Helper Function Reference

### Database (`includes/db.php`)

| Function | Description |
|----------|-------------|
| `query_one($sql, $types, ...$params)` | Returns first row as assoc array or `null` |
| `query_all($sql, $types, ...$params)` | Returns all rows as array of assoc arrays |
| `query_exec($sql, $types, ...$params)` | Execute INSERT/UPDATE/DELETE; returns `true`/`false` |
| `last_insert_id()` | Returns last auto-increment ID |

### General (`includes/helpers.php`)

| Function | Description |
|----------|-------------|
| `e($str)` | `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')` — XSS-safe output |
| `sanitize($str)` | Trims and strips null bytes |
| `flash($key, $msg, $type='info')` | Store a flash message in session |
| `render_flash()` | Output and clear all flash messages as HTML |
| `format_datetime($dt)` | Format a MySQL datetime for display |
| `time_elapsed($dt)` | Human-readable relative time (e.g. "3 hours ago") |
| `log_action($action, $target_id, $details)` | Write to `audit_logs` |
| `get_setting($key, $default='')` | Read from `settings` table |
| `update_setting($key, $value)` | Write to `settings` table |
| `csrf_token_for_js()` | Return current CSRF token for embedding in JS |
| `csrf_one_time_token($action)` | Generate a single-use CSRF token |
| `csrf_validate_one_time($action, $submitted)` | Validate and consume one-time token |

### Auth/RBAC (`includes/rbac.php`)

| Function | Description |
|----------|-------------|
| `can($permission)` | `bool` — check if current admin has permission |
| `require_permission($permission)` | Abort with 403 if not permitted |
| `role_slug()` | Return current admin's role slug string |

### Crypto (`includes/crypto.php`)

| Function | Description |
|----------|-------------|
| `encrypt($plain)` | AES-256-CBC encrypt; returns `iv:ciphertext` base64 string |
| `decrypt($encoded)` | Decrypt an `encrypt()` output |
| `encrypt_setting($plain)` | Encrypt with `enc:` prefix for settings storage |
| `decrypt_setting($stored)` | Decrypt if `enc:` prefixed, otherwise return raw |
| `validate_password_strength($pass)` | Returns `[]` on pass, or array of error messages |
| `password_used_recently($plain, $admin_id, $limit)` | `bool` — was password used in last N records? |
| `record_password_history($admin_id, $hash)` | Append hash to history, prune > 10 |

### Rate Limiter (`includes/rate_limiter.php`)

| Function | Description |
|----------|-------------|
| `rl_check($key, $max, $window_sec=60)` | Increment counter; returns `true` if limit exceeded |
| `rl_abort($retry_after=60)` | Send HTTP 429 + `Retry-After` header + exit |

### i18n (`includes/i18n.php`)

| Function | Description |
|----------|-------------|
| `__($key, ...$args)` | Translate `$key` using current language; `sprintf` with `$args` |
| `lang_dir()` | Return `'rtl'` for Urdu, `'ltr'` otherwise |

---

## 9. Logging Conventions

| Log File | Written By | Content |
|----------|-----------|---------|
| `logs/php_errors.log` | PHP error handler | PHP errors, warnings, fatal exceptions |
| `logs/cron.log` | All cron scripts | Timestamped status lines; errors |
| `logs/rate_limits/rl_<hash>.json` | `rate_limiter.php` | Sliding window buckets per key |
| `logs/backups/` | Backup API + cron | `.sql.gz` backup archives |
| `audit_logs` (DB table) | `log_action()` | Every admin action with IP + user-agent |

**Log entry format (cron.log):**
```
[2025-06-01 03:00:01] [cron_daily_backup] Backup created: svms_backup_20250601_030001.sql.gz (2.1 MB)
[2025-06-01 03:00:05] [cron_daily_backup] Pruned 2 old backup(s)
```

**Sensitive data:** Passwords, OTP codes, and encryption keys are **never** written to logs.

---

## 10. How to Add a New Page

1. **Create `pages/my_page.php`:**

```php
<?php
require_once __DIR__ . '/../config.php';
require_once INCLUDES . '/auth_check.php';
require_permission('view_dashboard'); // or the appropriate permission

$page_title = 'My New Page';
// ... fetch data ...
require_once INCLUDES . '/header.php';
?>

<div class="container mt-4">
  <h1><?= e($page_title) ?></h1>
  <!-- page HTML here -->
</div>

<?php require_once INCLUDES . '/footer.php'; ?>
```

2. **Add sidebar link** in `includes/sidebar.php`:

```html
<li class="nav-item">
  <a class="nav-link <?= active('my_page') ?>" href="<?= BASE_URL ?>pages/my_page.php">
    <i class="bi bi-star"></i> My Page
  </a>
</li>
```

3. **Protect it** — if only certain roles should see the link, wrap in `<?php if(can('some_permission')): ?>`.

4. **Add an API endpoint** in `api/my_action.php` if the page needs AJAX calls.

---

## 11. How to Add a New Email Template

1. **Queue the email** from anywhere in PHP:

```php
queue_email(
    to_email: 'user@example.com',
    subject:  'Your Subject',
    body_html: build_email_template('my_template', ['var1' => 'value']),
    related_type: 'appointment',
    related_id: $appointment_id
);
```

2. **Create the template function** in `includes/email.php`:

```php
function build_email_template(string $name, array $vars = []): string {
    // already exists — add a new case:
    if ($name === 'my_template') {
        return "
        <html><body>
        <h2>Hello, {$vars['var1']}</h2>
        <p>Your message here.</p>
        </body></html>";
    }
    // ... existing templates ...
}
```

3. The cron job `scripts/cron_email_queue.php` picks up the email within 5 minutes and sends it via the configured SMTP server.

---

## 12. Coding Standards

SVMS follows **PSR-1** (basic coding standard) with the following project-specific notes:

### PHP
- Files: UTF-8, Unix LF, no BOM.
- Indentation: 4 spaces (no tabs).
- Short open tags: use `<?php` only. Use `<?= e($var) ?>` for output.
- Always escape output with `e()` (htmlspecialchars wrapper).
- Never use `$_GET` / `$_POST` directly in SQL — always use prepared statements via `query_*()` helpers.
- CSRF token required on every state-changing POST request.
- File uploads: validate MIME type with `finfo_file()`, do not trust the client's declared type.
- Passwords: use `password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])` — never MD5/SHA1.
- Error handling: in production (`IS_DEV = false`), errors are logged not displayed.
- Permission check at the top of every page and API endpoint — do not rely on client-side hiding.

### SQL
- Use parameterized queries (`bind_param`) exclusively.
- Table names and column names in backticks.
- ORDER BY in all queries that display to users.
- Use `DATETIME` for all timestamps, stored in `Asia/Karachi` timezone.

### JavaScript
- No build tools — plain JS, ES2020+ features acceptable.
- Always escape user-supplied content before inserting into the DOM (`textContent` or an `escHtml()` helper — never `innerHTML` with raw data).
- AJAX: use `fetch()` with `credentials: 'same-origin'`; include `csrf_token` in request body.
- No third-party JS CDN in production — all assets are local.

### CSS
- Bootstrap 5 utility classes for layout.
- Custom CSS in `assets/css/custom.css`.
- Dark mode via CSS custom properties toggled with a `.dark-mode` body class.

### File Naming
- PHP pages: `snake_case.php`
- PHP includes: `snake_case.php`
- JS files: `snake_case.js`
- SQL migrations: `NNN_description.sql` (zero-padded three-digit number)
