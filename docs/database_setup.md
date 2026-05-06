# SVMS Database Setup
**Version 2.0** | MySQL 8.0+ / MariaDB 10.6+

---

## Overview

SVMS uses a single MySQL database (`svms_db`) with 17 core tables. All schema changes are
versioned as numbered migration files in `/migrations/`. The `docs/database_setup.sql` file is
the **cumulative schema** — a single file you can import on a fresh server to create all tables
and seed the required roles and settings.

---

## Quick Setup (Fresh Server)

```bash
# 1. Log into MySQL
mysql -u root -p

# 2. Create the database
CREATE DATABASE svms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'svms_user'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON svms_db.* TO 'svms_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# 3. Import full schema
mysql -u svms_user -p svms_db < docs/database_setup.sql

# 4. (Optional) Import demo dataset
mysql -u svms_user -p svms_db < docs/seed.sql
```

---

## Table Reference

### `roles`
Stores the three default RBAC roles. Permissions are stored as a JSON column.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `slug` | VARCHAR(50) UNIQUE | e.g. `super_admin`, `admin`, `receptionist` |
| `label` | VARCHAR(100) | Display name |
| `permissions` | JSON | Key-value map of permission slugs → bool |
| `created_at` | DATETIME | |

---

### `admins`
System users (administrators, receptionists, etc.).

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `name` | VARCHAR(150) | Full name |
| `username` | VARCHAR(100) UNIQUE | Login username |
| `email` | VARCHAR(320) UNIQUE | |
| `password` | VARCHAR(255) | bcrypt hash (`$2y$12$…`) |
| `role_id` | INT UNSIGNED FK → roles.id | |
| `is_active` | TINYINT | 1 = active |
| `otp_secret` | VARCHAR(64) NULL | 2FA secret |
| `otp_enabled` | TINYINT | |
| `last_login_at` | DATETIME | |
| `last_login_ip` | VARCHAR(45) | |
| `theme` | VARCHAR(10) | `light` / `dark` / `system` |
| `language` | VARCHAR(5) | `en` / `ur` |
| `created_at` | DATETIME | |

---

### `admin_password_history`
Stores last 10 bcrypt hashes per admin to enforce password reuse policy.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `admin_id` | INT UNSIGNED FK → admins.id | |
| `pass_hash` | VARCHAR(255) | bcrypt hash |
| `changed_at` | DATETIME | |

---

### `departments`
Organizational units that visitors are associated with.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `name` | VARCHAR(150) | |
| `colour` | VARCHAR(7) | Hex colour for calendar/charts (e.g. `#1a3c5e`) |
| `created_at` | DATETIME | |

---

### `visitors`
Master record for each person who visits. One row per unique individual.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `name` | VARCHAR(150) | |
| `cnic` | VARCHAR(20) | Pakistani NIC number |
| `phone` | VARCHAR(20) | |
| `email` | VARCHAR(320) | |
| `organization` | VARCHAR(150) | |
| `badge_number` | VARCHAR(50) UNIQUE | e.g. `VIS-250601-A3F2C` |
| `qr_token` | VARCHAR(64) UNIQUE | QR scan token |
| `photo_path` | VARCHAR(300) | Relative path under `assets/uploads/` |
| `department_id` | INT UNSIGNED FK → departments.id | |
| `notes` | TEXT | |
| `created_by` | INT UNSIGNED FK → admins.id | |
| `created_at` | DATETIME | |

---

### `visit_log`
One row per visit (check-in / check-out event). Multiple rows may exist per visitor.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `visitor_id` | INT UNSIGNED FK → visitors.id | |
| `appointment_id` | INT UNSIGNED FK → appointments.id NULL | |
| `host_name` | VARCHAR(150) | Person being visited |
| `department_id` | INT UNSIGNED FK → departments.id | |
| `purpose` | TEXT | |
| `vehicle_number` | VARCHAR(30) | |
| `check_in_time` | DATETIME | |
| `check_out_time` | DATETIME NULL | |
| `status` | ENUM | `checked_in` / `checked_out` / `no_show` / `auto_checkout` |
| `check_in_photo` | VARCHAR(300) | Path under `assets/uploads/` |
| `remarks` | TEXT | |
| `checked_in_by` | INT UNSIGNED FK → admins.id | |
| `checked_out_by` | INT UNSIGNED FK → admins.id NULL | |
| `created_at` | DATETIME | |

---

### `appointments`
Pre-scheduled visits.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `visitor_id` | INT UNSIGNED FK → visitors.id NULL | Linked after registration |
| `visitor_name` | VARCHAR(150) | Name at time of booking |
| `cnic` | VARCHAR(20) | |
| `phone` | VARCHAR(20) | |
| `email` | VARCHAR(320) | |
| `department_id` | INT UNSIGNED FK → departments.id | |
| `person_to_meet` | VARCHAR(120) | Host name |
| `host_name` | VARCHAR(150) | Legacy alias for `person_to_meet` |
| `purpose` | TEXT | |
| `notes` | TEXT | |
| `scheduled_at` | DATETIME | |
| `duration_minutes` | INT UNSIGNED | Default 30 |
| `status` | ENUM | `scheduled` / `confirmed` / `arrived` / `completed` / `cancelled` / `no_show` |
| `qr_token` | VARCHAR(64) UNIQUE | For e-pass QR scan |
| `reminder_sent` | TINYINT | |
| `created_by` | INT UNSIGNED FK → admins.id | |
| `created_at` | DATETIME | |

---

### `custom_fields`
Admin-defined extra fields for registration or appointment forms.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `field_name` | VARCHAR(100) | Internal key (snake_case) |
| `label` | VARCHAR(200) | Display label |
| `field_type` | ENUM | `text` / `number` / `date` / `select` / `checkbox` |
| `options` | TEXT | JSON array for `select` types |
| `is_required` | TINYINT | |
| `applies_to` | ENUM | `registration` / `appointment` / `both` |
| `sort_order` | INT | |
| `created_at` | DATETIME | |

---

### `custom_field_values`
Stored responses for custom fields per visitor or appointment.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `field_id` | INT UNSIGNED FK → custom_fields.id | |
| `entity_type` | ENUM | `visitor` / `appointment` |
| `entity_id` | INT UNSIGNED | FK to visitors.id or appointments.id |
| `field_value` | TEXT | |
| `created_at` | DATETIME | |

---

### `blacklist`
Flagged individuals blocked from checking in.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `name` | VARCHAR(150) | |
| `cnic` | VARCHAR(20) | |
| `phone` | VARCHAR(20) | |
| `reason` | TEXT | |
| `severity` | ENUM | `low` / `medium` / `high` |
| `notes` | TEXT | |
| `source` | ENUM | `internal` / `lea_notice` / `court_order` / `self_blocked` / `other` |
| `expiry_date` | DATE NULL | Auto-expires on this date |
| `is_active` | TINYINT | |
| `added_by` | INT UNSIGNED FK → admins.id | |
| `removed_by` | INT UNSIGNED FK → admins.id NULL | |
| `removed_at` | DATETIME NULL | |
| `removed_reason` | TEXT NULL | |
| `block_count` | INT UNSIGNED | Number of times this person was blocked at check-in |
| `created_at` | DATETIME | |

---

### `feedback`
Post-visit feedback submitted via modal or public email link.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `visit_id` | INT UNSIGNED FK → visit_log.id | |
| `visitor_id` | INT UNSIGNED FK → visitors.id | |
| `rating` | TINYINT | 1–5 stars |
| `comment` | TEXT | |
| `source` | ENUM | `staff` / `visitor` |
| `public_token` | VARCHAR(64) UNIQUE NULL | HMAC token for visitor self-service link |
| `created_at` | DATETIME | |

---

### `notifications`
In-app notification feed. Polled every 30 seconds by the header.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `type` | VARCHAR(64) | e.g. `checkin`, `appointment`, `emergency` |
| `title` | VARCHAR(255) | |
| `message` | TEXT | |
| `link` | VARCHAR(500) | Target URL |
| `recipient_id` | INT UNSIGNED FK → admins.id NULL | NULL = all admins |
| `visible_to_role_id` | INT FK → roles.id NULL | NULL = all roles |
| `is_read` | TINYINT | |
| `created_at` | DATETIME | |

---

### `audit_logs`
Immutable log of every admin action.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `admin_id` | INT UNSIGNED FK → admins.id | |
| `action` | VARCHAR(100) | e.g. `backup_create`, `restore`, `blacklist_add` |
| `target_id` | INT | Row ID affected (0 = no specific row) |
| `details` | JSON | Free-form context |
| `ip_address` | VARCHAR(45) | |
| `user_agent` | VARCHAR(500) | |
| `created_at` | DATETIME | |

---

### `settings`
Key-value store for runtime configuration (SMTP, site name, emergency mode, etc.).

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `setting_key` | VARCHAR(100) UNIQUE | |
| `setting_value` | TEXT | Encrypted values prefixed `enc:` |
| `updated_at` | DATETIME | |

---

### `email_queue`
Async email delivery queue processed by `cron_email_queue.php`.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `to_email` | VARCHAR(320) | |
| `subject` | VARCHAR(998) | |
| `body_html` | MEDIUMTEXT | |
| `body_plain` | MEDIUMTEXT | |
| `status` | ENUM | `pending` / `sending` / `sent` / `failed` |
| `attempts` | TINYINT UNSIGNED | Max 3 |
| `last_error` | TEXT | |
| `scheduled_at` | DATETIME | |
| `sent_at` | DATETIME NULL | |
| `related_type` | VARCHAR(64) | Context (e.g. `appointment`) |
| `related_id` | INT UNSIGNED | |
| `created_at` | DATETIME | |

---

### `emergency_snapshots`
Saved state captures triggered from the Emergency Control Panel.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `triggered_by` | INT UNSIGNED FK → admins.id NULL | |
| `mode` | VARCHAR(20) | `evacuation` / `lockdown` |
| `visitor_count` | INT UNSIGNED | |
| `snapshot_file` | VARCHAR(300) | Path in `LOG_DIR` |
| `notes` | TEXT | |
| `created_at` | DATETIME | |

---

### `backups`
Metadata record for each database backup file.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED PK | |
| `filename` | VARCHAR(300) | File in `logs/backups/` |
| `size_bytes` | BIGINT UNSIGNED | |
| `created_by` | INT UNSIGNED FK → admins.id NULL | NULL = automated cron |
| `type` | ENUM | `manual` / `automated` |
| `status` | ENUM | `ok` / `error` / `deleted` |
| `error` | TEXT NULL | |
| `created_at` | DATETIME | |

---

## Verify Installation

After importing, run these queries to confirm:

```sql
USE svms_db;

-- Expect 17 tables
SELECT COUNT(*) AS table_count FROM information_schema.tables
WHERE table_schema = 'svms_db';

-- Expect ≥ 3 roles
SELECT slug, label FROM roles;

-- Expect ≥ 1 admin
SELECT username, email FROM admins;

-- Expect ≥ 8 departments
SELECT name, colour FROM departments;

-- Expect ≥ 5 settings rows
SELECT setting_key FROM settings;
```

---

## Incremental Migrations

When upgrading from a previous installation, run only the migration files
you have not yet applied (check `CHANGELOG.md` for version ↔ migration mapping):

```bash
# Example: upgrading from v1.x to v2.0
mysql -u svms_user -p svms_db < migrations/004_email_queue.sql
mysql -u svms_user -p svms_db < migrations/005_appointments_calendar.sql
mysql -u svms_user -p svms_db < migrations/006_blacklist_notifications.sql
mysql -u svms_user -p svms_db < migrations/007_admin_preferences.sql
mysql -u svms_user -p svms_db < migrations/008_features_v5_5.sql
mysql -u svms_user -p svms_db < migrations/009_security_hardening.sql
mysql -u svms_user -p svms_db < migrations/010_backup_table.sql
```

All migration files are idempotent (use `IF NOT EXISTS` / `ADD COLUMN IF NOT EXISTS`)
and safe to re-run.
