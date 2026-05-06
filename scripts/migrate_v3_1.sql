-- ============================================================
-- SVMS v2 — Database Migration v3.1
-- Visitor Registration tables + schema updates
-- Run once via phpMyAdmin or CLI: mysql -u root svms_db < migrate_v3_1.sql
-- ============================================================

-- ── visitors ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `visitors` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `full_name`   VARCHAR(100) NOT NULL,
  `cnic`        VARCHAR(15)  DEFAULT '',
  `phone`       VARCHAR(20)  NOT NULL,
  `email`       VARCHAR(150) DEFAULT '',
  `photo_path`  VARCHAR(255) DEFAULT '',
  `vip`         TINYINT(1)   NOT NULL DEFAULT 0,
  `qr_token`    VARCHAR(64)  NOT NULL UNIQUE,
  `custom_data` JSON,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_cnic`  (`cnic`),
  INDEX `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── visit_log ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `visit_log` (
  `id`             INT          NOT NULL AUTO_INCREMENT,
  `visitor_id`     INT          NOT NULL,
  `department_id`  INT          DEFAULT NULL,
  `person_to_meet` VARCHAR(100) DEFAULT '',
  `purpose`        TEXT,
  `vehicle_number` VARCHAR(20)  DEFAULT '',
  `badge_number`   VARCHAR(40)  DEFAULT '',
  `visitor_type`   ENUM('walk_in','appointment','delivery','vendor','contractor','vip') NOT NULL DEFAULT 'walk_in',
  `check_in_time`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `check_out_time` DATETIME     DEFAULT NULL,
  `status`         ENUM('checked_in','checked_out','no_show') NOT NULL DEFAULT 'checked_in',
  `registered_by`  INT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_vl_visitor`    FOREIGN KEY (`visitor_id`)    REFERENCES `visitors`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_vl_dept`       FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vl_registered` FOREIGN KEY (`registered_by`) REFERENCES `admins`(`id`)      ON DELETE SET NULL,
  INDEX `idx_checkin`  (`check_in_time`),
  INDEX `idx_status`   (`status`),
  INDEX `idx_visitor`  (`visitor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── departments ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `departments` (
  `id`        INT          NOT NULL AUTO_INCREMENT,
  `name`      VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed sample departments if table is empty
INSERT IGNORE INTO `departments` (`id`, `name`, `is_active`) VALUES
(1, 'Administration', 1),
(2, 'Human Resources', 1),
(3, 'Finance', 1),
(4, 'IT Department', 1),
(5, 'Security', 1),
(6, 'Operations', 1),
(7, 'Legal', 1),
(8, 'Reception', 1);

-- ── custom_fields ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `custom_fields` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `field_name`    VARCHAR(100) NOT NULL,
  `field_type`    ENUM('text','number','select','checkbox','date','textarea') NOT NULL DEFAULT 'text',
  `is_required`   TINYINT(1)   NOT NULL DEFAULT 0,
  `options`       TEXT         DEFAULT NULL   COMMENT 'JSON array of select options',
  `display_order` INT          NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── blacklist — add severity column if missing ───────────────
ALTER TABLE `blacklist`
  ADD COLUMN IF NOT EXISTS `severity`
    ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium'
    AFTER `reason`;

-- ── notifications — ensure message column exists ─────────────
-- (Some scaffolds may have used 'body'; standardise to 'message')
ALTER TABLE `notifications`
  ADD COLUMN IF NOT EXISTS `message` TEXT DEFAULT NULL AFTER `title`,
  ADD COLUMN IF NOT EXISTS `link`    VARCHAR(500) DEFAULT NULL AFTER `message`;

-- ── feedback — check-out experience rating (v3.2) ────────────
CREATE TABLE IF NOT EXISTS `feedback` (
  `id`           INT      NOT NULL AUTO_INCREMENT,
  `visit_log_id` INT      NOT NULL,
  `rating`       TINYINT  NOT NULL COMMENT '1-5 stars',
  `notes`        TEXT,
  `created_by`   INT      DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_visit` (`visit_log_id`),
  CONSTRAINT `fk_fb_visit` FOREIGN KEY (`visit_log_id`) REFERENCES `visit_log`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
