# Changelog

All notable changes to the Smart Visitor Management System are documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [2.0.0] — 2025-06-01

### Added — Core Platform

- **Visitor Management**
  - Visitor registration with CNIC, phone, email, organization, and custom fields
  - Auto-generated unique badge numbers (`VIS-YYMMDD-XXXXX`) and QR codes
  - Smart search across name, CNIC, phone, badge number, organization
  - Visit photo capture (webcam) and file upload at check-in
  - Visitor detail page with full visit history and feedback

- **Check-In / Check-Out**
  - QR code scan for fast check-in (badge and appointment e-pass)
  - Manual CNIC/phone lookup fallback
  - Real-time dashboard KPI cards (active visitors, today's visits, pending appointments)
  - Auto-checkout via cron after configurable `MAX_VISIT_HOURS`

- **Appointments & Calendar**
  - Pre-scheduled appointment creation with host, department, duration, purpose
  - Appointment e-pass: QR-coded confirmation email sent to visitor
  - FullCalendar-based calendar view (day/week/month) with per-department colors
  - Appointment status workflow: `scheduled → confirmed → arrived → completed / no_show / cancelled`
  - Automated 24-hour reminder emails via cron
  - Auto no-show marking via cron (2 hours past scheduled time)

- **Kiosk Mode**
  - Public-facing self-service tablet interface at `/kiosk/`
  - Walk-in and QR scan arrival flows
  - Receptionist approval workflow for kiosk arrivals

- **Analytics & Reports**
  - Visit trend charts (daily, weekly, monthly)
  - Department breakdown, peak-hour heatmap, feedback rating summary
  - PDF report generation (daily / weekly / monthly / visitor profile / department)
  - CSV export with server-side filters
  - All reports powered by TCPDF (Composer dependency)

- **Blacklist**
  - Block visitors by CNIC or phone number
  - Severity levels: Low / Medium / High
  - Source classification: Internal, LEA Notice, Court Order, Other
  - Expiry date support for temporary blocks
  - Automatic check-in gate — match on CNIC or phone shows red alert
  - Removal with mandatory reason, preserved in audit log

- **Notifications**
  - In-app notification feed (header bell icon)
  - Per-admin and broadcast (all admins) notifications
  - Role-scoped visibility
  - Polled every 30 seconds via AJAX

- **Feedback**
  - Post-visit star rating (1–5) with optional comment
  - Staff-submitted and visitor self-service modes
  - One-time public token links for visitor feedback via email

- **Custom Fields**
  - Admin-defined extra fields for registration and/or appointment forms
  - Types: text, number, date, select (dropdown), checkbox
  - Required/optional, display order, active/inactive

- **Email Queue**
  - Async email delivery via `email_queue` table
  - Cron-based processing every 5 minutes (up to 50 emails per run)
  - Retry on failure (max 3 attempts), error logged per email
  - Templates: OTP, appointment confirmation, reminder, daily digest

- **Emergency Control Panel**
  - Two modes: Evacuation, Lockdown
  - Snapshot saved with visitor list at time of trigger
  - Dashboard-wide emergency banner
  - All active sessions notified
  - Downloadable visitor snapshot for emergency personnel

- **Backup & Restore**
  - Manual on-demand backup from Settings page
  - Automated daily backup via cron (3:00 AM)
  - Primary: `mysqldump` with `--single-transaction --quick --routines --triggers`
  - Fallback: Pure-PHP dump (for environments without `mysqldump` in PATH)
  - All backups gzip-compressed (`.sql.gz`)
  - Auto-pruning: keep last 20 OR <30 days, whichever is more
  - Super-Admin-only restore with password re-authentication and `RESTORE` confirmation phrase
  - Restore from existing backup or uploaded file (max 50 MB)
  - Post-restore: all sessions terminated

- **Audit Log**
  - Append-only log of all admin actions (login, check-in, settings change, backup, restore, blacklist, emergency, etc.)
  - Records: admin ID, action, target row ID, details (JSON), IP address, user agent
  - Filterable by admin, action type, and date range
  - Not editable or deletable from the UI

- **Progressive Web App (PWA)**
  - `manifest.json` with icons and display mode
  - Service worker with offline fallback page
  - Installable on Android (Chrome) and iOS (Safari)

### Added — Authentication & Security

- **Two-Factor Authentication (2FA)**
  - Email OTP enforced on login
  - Configurable OTP expiry (`OTP_EXPIRY_MINUTES`, default 10 min)
  - Per-admin enable/disable toggle
  - Rate-limited: 5 OTP attempts per 60 seconds

- **Brute-Force Protection**
  - File-based sliding-window rate limiter (no Redis required)
  - Login: 10 attempts / 60 seconds; lockout returns HTTP 429 + `Retry-After`
  - OTP: 5 attempts / 60 seconds
  - Separate buckets per IP, admin ID, and action type

- **Password Policy**
  - bcrypt cost=12
  - Minimum 8 characters, uppercase + lowercase + digit + symbol required
  - Last 10 passwords blocked (stored in `admin_password_history`)

- **AES-256-CBC Encryption**
  - SMTP password and other sensitive settings encrypted at rest
  - `config/keys.php` auto-generated, chmod 0600, gitignored
  - `encrypt()` / `decrypt()` / `encrypt_setting()` / `decrypt_setting()` helpers

- **CSRF Protection**
  - Synchronizer token validated on all state-changing requests
  - One-time CSRF tokens for restore and other critical operations

- **Session Security**
  - Cookie name: `SVMS_SESSID` (kiosk: `SVMS_KIOSK`)
  - `HttpOnly=true`, `SameSite=Strict`, `Secure=true` (HTTPS), `path=/svms/`
  - `session_regenerate(true)` on login
  - Session lifetime enforced server-side

- **HTTP Security Headers** (`.htaccess`)
  - `Strict-Transport-Security` (HSTS, production)
  - `Content-Security-Policy`
  - `X-Frame-Options: DENY`
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy: strict-origin-when-cross-origin`

- **File Upload Security**
  - MIME type validated with `finfo_file()` — client MIME not trusted
  - PHP execution blocked in `assets/uploads/` and `vendor/` via `.htaccess`
  - Max file size: 5 MB (visitor photos); 50 MB (backup restore upload)

### Added — Internationalization

- English (`en`) and Urdu (`ur`) language support
- `__($key)` i18n helper with per-admin language preference
- Full RTL layout for Urdu via `lang_dir()` helper and `dir` HTML attribute
- Per-admin theme preference: `light`, `dark`, `system`

### Added — Documentation

- `docs/deployment_checklist.md` — production deployment guide
- `docs/database_setup.md` — database schema reference and import instructions
- `docs/database_setup.sql` — cumulative CREATE TABLE DDL for fresh installs
- `docs/seed.sql` — demo dataset (roles, default admin, departments, sample visitors)
- `docs/environment_config.php.example` — annotated config.php template
- `docs/htaccess_production.txt` — production-ready .htaccess with HTTPS redirect + HSTS
- `docs/post_deployment_test.md` — 10-step smoke test + 4 failure-mode tests
- `docs/user_manual.md` — 7-chapter end-user operations manual
- `docs/technical_reference.md` — architecture, schema, API, coding guide
- `docs/test_plan.md` — 100+ QA test cases across 18 areas
- `docs/fix_log.md` — security and accessibility fix log (FIX-001 through FIX-018)
- `dist/INSTALL.txt` — 30-line plain-text quick install guide
- `README.md` — project overview with badges, quickstart, tech stack

### Changed

- `.htaccess` refactored: HTTPS redirect and HSTS commented out for dev, documented for production
- `pages/backup.php` fully rewritten with accessibility improvements, ARIA roles, and modular AJAX
- `pages/login.php` hardened with rate limiter, CSRF, and account status checks

### Security Fixes (v2.0.0)

See `docs/fix_log.md` for full details.

| Fix ID | Description |
|--------|-------------|
| FIX-001 | Login brute-force rate limiting |
| FIX-002 | OTP rate limiting |
| FIX-003 | CSRF protection on all POST endpoints |
| FIX-004 | Session fixation prevention |
| FIX-005 | Password strength enforcement |
| FIX-006 | Password history (last 10) |
| FIX-007 | AES-256-CBC encryption for SMTP password |
| FIX-008 | ENCRYPTION_KEY auto-generation and chmod 0600 |
| FIX-009 | HSTS header in production .htaccess |
| FIX-010 | Content-Security-Policy header |
| FIX-011 | X-Frame-Options: DENY |
| FIX-012 | File upload MIME validation with finfo |
| FIX-013 | PHP execution blocked in uploads/ and vendor/ |
| FIX-014 | Backup page: Super Admin gate (403 for non-super-admin) |
| FIX-015 | Restore: password re-authentication + RESTORE phrase |
| FIX-016 | ARIA labels on icon-only buttons |
| FIX-017 | Form label associations and focus management |
| FIX-018 | Live region for screen reader alerts |

### Breaking Changes from v1.x

- `config.php` requires two new constants: `ENCRYPTION_KEY` (auto-generated in `config/keys.php`) and `APP_KEY`.
  Run the app once — `config/keys.php` is created automatically on first boot if absent.
- `admins` table: new columns `theme`, `language` — run `migrations/007_admin_preferences.sql`.
- `visit_log` table: `status` enum updated — run `migrations/008_features_v5_5.sql`.
- `feedback` table: new columns `public_token`, `source` — run `migrations/008_features_v5_5.sql`.
- `custom_fields` table: new columns `label`, `applies_to` — run `migrations/008_features_v5_5.sql`.
- `blacklist` table: new columns `source`, `expiry_date`, `removed_by`, `removed_at`, `removed_reason`, `block_count` — run `migrations/006_blacklist_notifications.sql`.
- New tables: `email_queue`, `emergency_snapshots`, `admin_password_history`, `backups` — run migrations 004, 008, 009, 010.

### Migration Order (v1.x → v2.0)

```bash
mysql -u svms_user -p svms_db < migrations/004_email_queue.sql
mysql -u svms_user -p svms_db < migrations/005_appointments_calendar.sql
mysql -u svms_user -p svms_db < migrations/006_blacklist_notifications.sql
mysql -u svms_user -p svms_db < migrations/007_admin_preferences.sql
mysql -u svms_user -p svms_db < migrations/008_features_v5_5.sql
mysql -u svms_user -p svms_db < migrations/009_security_hardening.sql
mysql -u svms_user -p svms_db < migrations/010_backup_table.sql
```

---

## [1.0.0] — Initial Release

- Basic visitor check-in/check-out
- Admin login
- Visitor registration with badge number
- Simple history table
- Basic roles (admin, receptionist)
