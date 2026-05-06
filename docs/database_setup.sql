-- ============================================================
-- SVMS v2.0 — Cumulative Database Schema
-- docs/database_setup.sql
--
-- Usage (fresh install):
--   mysql -u svms_user -p svms_db < docs/database_setup.sql
--
-- This file creates ALL tables from scratch.
-- For upgrades from v1.x, run migrations/004_*.sql through
-- migrations/010_*.sql instead (they are idempotent).
--
-- Character set: utf8mb4 / utf8mb4_unicode_ci
-- Engine: InnoDB
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ── roles ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `roles` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(50)     NOT NULL,
  `label`       VARCHAR(100)    NOT NULL,
  `permissions` JSON            NOT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── admins ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150)  NOT NULL,
  `username`      VARCHAR(100)  NOT NULL,
  `email`         VARCHAR(320)  NOT NULL,
  `password`      VARCHAR(255)  NOT NULL,
  `role_id`       INT UNSIGNED  NOT NULL,
  `is_active`     TINYINT       NOT NULL DEFAULT 1,
  `otp_secret`    VARCHAR(64)   NULL,
  `otp_enabled`   TINYINT       NOT NULL DEFAULT 0,
  `last_login_at` DATETIME      NULL,
  `last_login_ip` VARCHAR(45)   NULL,
  `theme`         VARCHAR(10)   NOT NULL DEFAULT 'system'
      COMMENT 'light | dark | system',
  `language`      VARCHAR(5)    NOT NULL DEFAULT 'en'
      COMMENT 'en | ur',
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  UNIQUE KEY `uq_email`    (`email`),
  KEY `fk_admin_role` (`role_id`),
  CONSTRAINT `fk_admin_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── admin_password_history ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_password_history` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `admin_id`   INT UNSIGNED  NOT NULL,
  `pass_hash`  VARCHAR(255)  NOT NULL,
  `changed_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pwh_admin` (`admin_id`),
  CONSTRAINT `fk_pwh_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── departments ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `departments` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150)  NOT NULL,
  `colour`     VARCHAR(7)    NOT NULL DEFAULT '#2e75b6',
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── visitors ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `visitors` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150)  NOT NULL,
  `cnic`          VARCHAR(20)   NOT NULL DEFAULT '',
  `phone`         VARCHAR(20)   NOT NULL DEFAULT '',
  `email`         VARCHAR(320)  NOT NULL DEFAULT '',
  `organization`  VARCHAR(150)  NOT NULL DEFAULT '',
  `badge_number`  VARCHAR(50)   NULL,
  `qr_token`      VARCHAR(64)   NULL,
  `photo_path`    VARCHAR(300)  NULL,
  `department_id` INT UNSIGNED  NULL,
  `notes`         TEXT          NULL,
  `created_by`    INT UNSIGNED  NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_badge`    (`badge_number`),
  UNIQUE KEY `uq_qr_token` (`qr_token`),
  KEY `idx_vis_cnic`  (`cnic`),
  KEY `idx_vis_phone` (`phone`),
  KEY `idx_vis_name`  (`name`),
  KEY `fk_vis_dept`   (`department_id`),
  CONSTRAINT `fk_vis_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── appointments ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `appointments` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `visitor_id`       INT UNSIGNED  NULL,
  `visitor_name`     VARCHAR(150)  NOT NULL,
  `cnic`             VARCHAR(20)   NOT NULL DEFAULT '',
  `phone`            VARCHAR(20)   NOT NULL DEFAULT '',
  `email`            VARCHAR(320)  NOT NULL DEFAULT '',
  `department_id`    INT UNSIGNED  NULL,
  `person_to_meet`   VARCHAR(120)  NOT NULL DEFAULT '',
  `host_name`        VARCHAR(150)  NOT NULL DEFAULT '',
  `purpose`          TEXT          NULL,
  `notes`            TEXT          NULL,
  `scheduled_at`     DATETIME      NOT NULL,
  `duration_minutes` INT UNSIGNED  NOT NULL DEFAULT 30,
  `status`           ENUM('scheduled','confirmed','arrived','completed','cancelled','no_show')
                                   NOT NULL DEFAULT 'scheduled',
  `qr_token`         VARCHAR(64)   NULL,
  `reminder_sent`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_by`       INT UNSIGNED  NULL,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appt_qr`      (`qr_token`),
  KEY `idx_appt_cal`     (`scheduled_at`, `status`),
  KEY `idx_appt_dept`    (`department_id`),
  KEY `idx_appt_visitor` (`visitor_id`),
  CONSTRAINT `fk_appt_visitor` FOREIGN KEY (`visitor_id`)    REFERENCES `visitors`    (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_appt_dept`    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── visit_log ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `visit_log` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `visitor_id`      INT UNSIGNED  NOT NULL,
  `appointment_id`  INT UNSIGNED  NULL,
  `host_name`       VARCHAR(150)  NOT NULL DEFAULT '',
  `department_id`   INT UNSIGNED  NULL,
  `purpose`         TEXT          NULL,
  `vehicle_number`  VARCHAR(30)   NOT NULL DEFAULT '',
  `check_in_time`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `check_out_time`  DATETIME      NULL,
  `status`          ENUM('checked_in','checked_out','no_show','auto_checkout')
                                  NOT NULL DEFAULT 'checked_in',
  `check_in_photo`  VARCHAR(300)  NULL,
  `remarks`         TEXT          NULL,
  `checked_in_by`   INT UNSIGNED  NULL,
  `checked_out_by`  INT UNSIGNED  NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vl_visitor`  (`visitor_id`),
  KEY `idx_vl_status`   (`status`),
  KEY `idx_vl_checkin`  (`check_in_time`),
  KEY `fk_vl_appt`      (`appointment_id`),
  CONSTRAINT `fk_vl_visitor` FOREIGN KEY (`visitor_id`)     REFERENCES `visitors`     (`id`),
  CONSTRAINT `fk_vl_appt`    FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── custom_fields ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `custom_fields` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `field_name`  VARCHAR(100)  NOT NULL,
  `label`       VARCHAR(200)  NOT NULL DEFAULT '',
  `field_type`  ENUM('text','number','date','select','checkbox') NOT NULL DEFAULT 'text',
  `options`     TEXT          NULL COMMENT 'JSON array for select types',
  `is_required` TINYINT       NOT NULL DEFAULT 0,
  `applies_to`  ENUM('registration','appointment','both') NOT NULL DEFAULT 'registration',
  `sort_order`  INT           NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_field_name` (`field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── custom_field_values ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `custom_field_values` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `field_id`    INT UNSIGNED  NOT NULL,
  `entity_type` ENUM('visitor','appointment') NOT NULL,
  `entity_id`   INT UNSIGNED  NOT NULL,
  `field_value` TEXT          NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cfv_entity`  (`entity_type`, `entity_id`),
  KEY `fk_cfv_field`    (`field_id`),
  CONSTRAINT `fk_cfv_field` FOREIGN KEY (`field_id`) REFERENCES `custom_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── blacklist ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `blacklist` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(150)  NOT NULL,
  `cnic`           VARCHAR(20)   NOT NULL DEFAULT '',
  `phone`          VARCHAR(20)   NOT NULL DEFAULT '',
  `reason`         TEXT          NULL,
  `severity`       ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `notes`          TEXT          NULL,
  `source`         ENUM('internal','lea_notice','court_order','self_blocked','other')
                                 NOT NULL DEFAULT 'internal',
  `expiry_date`    DATE          NULL,
  `is_active`      TINYINT       NOT NULL DEFAULT 1,
  `added_by`       INT UNSIGNED  NULL,
  `removed_by`     INT UNSIGNED  NULL,
  `removed_at`     DATETIME      NULL,
  `removed_reason` TEXT          NULL,
  `block_count`    INT UNSIGNED  NOT NULL DEFAULT 0,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bl_phone`  (`phone`),
  KEY `idx_bl_cnic`   (`cnic`),
  KEY `idx_bl_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── feedback ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `feedback` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `visit_id`     INT UNSIGNED  NULL,
  `visitor_id`   INT UNSIGNED  NULL,
  `rating`       TINYINT       NOT NULL DEFAULT 0 COMMENT '1–5',
  `comment`      TEXT          NULL,
  `source`       ENUM('staff','visitor') NOT NULL DEFAULT 'staff',
  `public_token` VARCHAR(64)   NULL UNIQUE COMMENT 'HMAC token for public link',
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_fb_visit`   (`visit_id`),
  KEY `fk_fb_visitor` (`visitor_id`),
  CONSTRAINT `fk_fb_visit`   FOREIGN KEY (`visit_id`)   REFERENCES `visit_log` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fb_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── notifications ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `type`                VARCHAR(64)   NOT NULL,
  `title`               VARCHAR(255)  NOT NULL,
  `message`             TEXT          NULL,
  `link`                VARCHAR(500)  NULL,
  `recipient_id`        INT UNSIGNED  NULL COMMENT 'NULL = all admins',
  `visible_to_role_id`  INT           NULL COMMENT 'NULL = all roles',
  `is_read`             TINYINT       NOT NULL DEFAULT 0,
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_recipient` (`recipient_id`),
  KEY `idx_notif_created`   (`created_at`),
  KEY `idx_notif_role`      (`visible_to_role_id`),
  KEY `idx_notif_read`      (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── audit_logs ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `admin_id`    INT UNSIGNED  NULL,
  `action`      VARCHAR(100)  NOT NULL,
  `target_id`   INT           NOT NULL DEFAULT 0,
  `details`     TEXT          NULL,
  `ip_address`  VARCHAR(45)   NOT NULL DEFAULT '',
  `user_agent`  VARCHAR(500)  NOT NULL DEFAULT '',
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_admin`   (`admin_id`),
  KEY `idx_al_action`  (`action`),
  KEY `idx_al_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── settings ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100)  NOT NULL,
  `setting_value` TEXT          NOT NULL,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── email_queue ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `email_queue` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `to_email`     VARCHAR(320)     NOT NULL,
  `subject`      VARCHAR(998)     NOT NULL,
  `body_html`    MEDIUMTEXT       NOT NULL,
  `body_plain`   MEDIUMTEXT,
  `status`       ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `last_error`   TEXT,
  `scheduled_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`      DATETIME         NULL,
  `related_type` VARCHAR(64)      NULL,
  `related_id`   INT UNSIGNED     NULL,
  `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eq_status_sched` (`status`, `scheduled_at`),
  KEY `idx_eq_related`      (`related_type`, `related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── emergency_snapshots ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `emergency_snapshots` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `triggered_by`  INT UNSIGNED  NULL,
  `mode`          VARCHAR(20)   NOT NULL DEFAULT 'evacuation',
  `visitor_count` INT UNSIGNED  NOT NULL DEFAULT 0,
  `snapshot_file` VARCHAR(300)  NULL,
  `notes`         TEXT          NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_es_triggered` (`triggered_by`),
  KEY `idx_es_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── backups ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `backups` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `filename`    VARCHAR(300)  NOT NULL,
  `size_bytes`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_by`  INT UNSIGNED  NULL COMMENT 'NULL = cron/automated',
  `type`        ENUM('manual','automated') NOT NULL DEFAULT 'manual',
  `status`      ENUM('ok','error','deleted') NOT NULL DEFAULT 'ok',
  `error`       TEXT          NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bk_created` (`created_at`),
  KEY `idx_bk_type`    (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════
-- SEED: Required roles
-- ════════════════════════════════════════════════════════════
INSERT IGNORE INTO `roles` (`slug`, `label`, `permissions`) VALUES
('super_admin', 'Super Administrator', JSON_OBJECT(
  'view_dashboard',       true, 'manage_users',   true,
  'register_visitor',     true, 'checkin_visitor', true,
  'checkout_visitor',     true, 'view_history',    true,
  'manage_appointments',  true, 'view_analytics',  true,
  'generate_reports',     true, 'manage_blacklist', true,
  'manage_settings',      true, 'manage_roles',     true,
  'manage_feedback',      true, 'view_audit_log',   true,
  'manage_custom_fields', true, 'manage_emergency', true
)),
('admin', 'Administrator', JSON_OBJECT(
  'view_dashboard',      true, 'register_visitor',    true,
  'checkin_visitor',     true, 'checkout_visitor',    true,
  'view_history',        true, 'manage_appointments', true,
  'view_analytics',      true, 'generate_reports',    true,
  'manage_blacklist',    true, 'manage_settings',     true,
  'manage_feedback',     true, 'view_audit_log',      true,
  'manage_emergency',    true
)),
('receptionist', 'Receptionist', JSON_OBJECT(
  'view_dashboard',   true, 'register_visitor', true,
  'checkin_visitor',  true, 'checkout_visitor', true,
  'view_history',     true, 'manage_feedback',  true,
  'manage_appointments', true
));

-- ════════════════════════════════════════════════════════════
-- SEED: Default departments
-- ════════════════════════════════════════════════════════════
INSERT IGNORE INTO `departments` (`id`, `name`, `colour`) VALUES
(1, 'Administration',    '#1a3c5e'),
(2, 'Human Resources',   '#7c3aed'),
(3, 'Finance',           '#059669'),
(4, 'IT & Systems',      '#0284c7'),
(5, 'Security',          '#dc2626'),
(6, 'Operations',        '#d97706'),
(7, 'Legal',             '#9333ea'),
(8, 'Public Relations',  '#0891b2');

-- ════════════════════════════════════════════════════════════
-- SEED: Default settings
-- ════════════════════════════════════════════════════════════
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name',         'Smart Visitor Management System'),
('site_tagline',      'Secure. Smart. Seamless.'),
('emergency_mode',    'normal'),
('smtp_host',         ''),
('smtp_user',         ''),
('smtp_pass',         ''),
('smtp_port',         '587'),
('smtp_from_email',   'no-reply@svms.com'),
('smtp_from_name',    'SVMS Notifications'),
('max_visit_hours',   '8'),
('default_lang',      'en'),
('default_theme',     'light');

SET FOREIGN_KEY_CHECKS = 1;
