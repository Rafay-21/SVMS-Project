# Smart Visitor Management System (SVMS)

**Secure. Smart. Seamless.**

A full-featured, self-hosted visitor management system built with PHP 8+ and MySQL — no cloud subscription required.

![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-blue?logo=php)
![MySQL 8.0+](https://img.shields.io/badge/MySQL-8.0%2B-orange?logo=mysql)
![Apache 2.4+](https://img.shields.io/badge/Apache-2.4%2B-red?logo=apache)
![License: MIT](https://img.shields.io/badge/License-MIT-green)

---

## Screenshots

| Login | Dashboard | Register Visitor |
|-------|-----------|-----------------|
| ![Login](docs/screenshots/login-page.png) | ![Dashboard](docs/screenshots/dashboard.png) | ![Register](docs/screenshots/register-visitor.png) |

| Appointments Calendar | Reports | Dark Mode |
|----------------------|---------|-----------|
| ![Calendar](docs/screenshots/appointments-calendar.png) | ![Reports](docs/screenshots/reports.png) | ![Dark Mode](docs/screenshots/dark-mode.png) |

---

## Features

- **Visitor Registration** — name, CNIC, phone, photo, custom fields, auto-generated badge number + QR code
- **Smart Check-In/Out** — QR scan, manual search, real-time dashboard counts
- **Appointments & Calendar** — schedule visits, send e-passes, color-coded calendar by department
- **Kiosk Mode** — self-service tablet interface for walk-in or appointment arrivals
- **Blacklist** — CNIC/phone matching at check-in with severity levels and expiry dates
- **Emergency Mode** — evacuation and lockdown with timestamped visitor snapshots
- **Analytics & Reports** — visit trends, department breakdowns, peak hours; PDF + CSV export
- **Two-Factor Authentication** — email OTP enforced per admin
- **Role-Based Access Control** — Super Admin / Admin / Receptionist with granular permissions
- **Custom Fields** — add extra fields to registration and appointment forms without code changes
- **SMTP Email Queue** — confirmation emails, appointment reminders, daily digest
- **Backup & Restore** — one-click manual backup, daily automated cron backup, password-confirmed restore
- **Dark Mode + RTL (Urdu)** — per-user theme and language preferences
- **PWA** — installable as a web app on Android and iOS
- **Audit Log** — immutable record of all admin actions

---

## Quickstart

> **Requirements:** PHP 8.1+, MySQL 8.0+ (or MariaDB 10.6+), Apache 2.4+ with `mod_rewrite`

```bash
# 1. Copy files to your web server
cp -r svms/ /var/www/html/

# 2. Create database and import schema
mysql -u root -p -e "CREATE DATABASE svms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p svms_db < docs/database_setup.sql

# 3. (Optional) Load demo data
mysql -u root -p svms_db < docs/seed.sql

# 4. Configure the application
cp docs/environment_config.php.example config.php
nano config.php   # set DB credentials, BASE_URL, SMTP, etc.

# 5. Set permissions
chmod 770 logs/ assets/uploads/
chown -R www-data:www-data /var/www/html/svms/

# 6. Browse to your URL
# http://localhost/svms/
```

---

## Default Credentials

> **Login:** `admin` / `Admin@1234!`
>
> **Change this password immediately after first login.**

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.1+ (procedural + OOP helpers) |
| Database | MySQL 8.0+ / MariaDB 10.6+ |
| Web Server | Apache 2.4+ (mod_rewrite, mod_headers, mod_ssl) |
| PDF Generation | [TCPDF](https://tcpdf.org/) via Composer |
| Frontend | Bootstrap 5, Vanilla JS (ES2020), FullCalendar |
| Email | SMTP via PHPMailer-compatible queue |
| Auth | bcrypt (cost=12), email OTP (2FA), CSRF tokens |
| Encryption | AES-256-CBC for sensitive settings |

---

## Documentation

| File | Description |
|------|-------------|
| [docs/deployment_checklist.md](docs/deployment_checklist.md) | Step-by-step production deployment guide |
| [docs/database_setup.md](docs/database_setup.md) | Database tables reference and import guide |
| [docs/database_setup.sql](docs/database_setup.sql) | Cumulative CREATE TABLE schema |
| [docs/seed.sql](docs/seed.sql) | Demo dataset |
| [docs/user_manual.md](docs/user_manual.md) | End-user operations manual (7 chapters) |
| [docs/technical_reference.md](docs/technical_reference.md) | Architecture, API reference, coding guide |
| [docs/test_plan.md](docs/test_plan.md) | 100+ QA test cases |
| [docs/fix_log.md](docs/fix_log.md) | Security and accessibility fix log |
| [docs/post_deployment_test.md](docs/post_deployment_test.md) | 10-step post-deployment smoke test |
| [CHANGELOG.md](CHANGELOG.md) | Version history |
| [dist/INSTALL.txt](dist/INSTALL.txt) | Plain-text quick install guide |

---

## Cron Setup

```cron
*/5 * * * *  php /var/www/html/svms/scripts/cron_email_queue.php
0   * * * *  php /var/www/html/svms/scripts/cron_appointment_reminders.php
*/30 * * * * php /var/www/html/svms/scripts/cron_appointment_no_show.php
0   * * * *  php /var/www/html/svms/scripts/cron_auto_checkout.php
0   3 * * *  php /var/www/html/svms/scripts/cron_daily_backup.php
0   7 * * *  php /var/www/html/svms/scripts/cron_daily_digest.php
```

---

## Security

SVMS implements the following OWASP-aligned security controls:

- **SQL Injection** — all queries use MySQLi prepared statements
- **XSS** — all output escaped with `htmlspecialchars` via `e()` helper
- **CSRF** — synchronizer token + single-use tokens for sensitive actions
- **Brute Force** — sliding-window rate limiter on login, OTP, and all sensitive endpoints
- **Session Fixation** — `session_regenerate(true)` on login
- **Secrets at rest** — `config/keys.php` chmod 0600, gitignored; SMTP password AES-256-CBC encrypted
- **File uploads** — MIME validated with `finfo`; PHP execution blocked in uploads dir via `.htaccess`
- **Password policy** — bcrypt cost 12, complexity rules enforced, last 10 passwords blocked
- **Transport security** — HSTS, Secure cookie flag, `SameSite=Strict`
- **Content Security Policy** — set in `.htaccess`

To report a vulnerability, contact the project maintainer privately before public disclosure.

---

## License

MIT License — see [LICENSE](LICENSE) for details.

---

## Credits

Built with PHP, MySQL, Apache, Bootstrap 5, TCPDF, and FullCalendar.
