-- ============================================================
-- Migration 005: Appointments Calendar
-- Adds department colour, extends appointments table for
-- full calendar support (Phase 4.3).
-- Safe to run multiple times (IF NOT EXISTS guards).
-- ============================================================

-- ── 1. Departments: colour column ───────────────────────────
ALTER TABLE `departments`
  ADD COLUMN IF NOT EXISTS `colour` VARCHAR(7) NOT NULL DEFAULT '#2e75b6';

-- Seed colours for the 8 default departments
UPDATE `departments` SET `colour` = CASE `id`
  WHEN 1 THEN '#1a3c5e'
  WHEN 2 THEN '#7c3aed'
  WHEN 3 THEN '#059669'
  WHEN 4 THEN '#0284c7'
  WHEN 5 THEN '#dc2626'
  WHEN 6 THEN '#d97706'
  WHEN 7 THEN '#9333ea'
  WHEN 8 THEN '#0891b2'
  ELSE `colour`
END
WHERE `id` BETWEEN 1 AND 8;

-- ── 2. Appointments: add missing columns ────────────────────
ALTER TABLE `appointments`
  ADD COLUMN IF NOT EXISTS `visitor_id`       INT UNSIGNED NULL                     AFTER `id`,
  ADD COLUMN IF NOT EXISTS `email`            VARCHAR(320) NOT NULL DEFAULT ''       AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `department_id`    INT UNSIGNED NULL                     AFTER `email`,
  ADD COLUMN IF NOT EXISTS `person_to_meet`   VARCHAR(120) NOT NULL DEFAULT ''       AFTER `department_id`,
  ADD COLUMN IF NOT EXISTS `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 30       AFTER `scheduled_at`,
  ADD COLUMN IF NOT EXISTS `qr_token`         VARCHAR(64)  NULL                     AFTER `duration_minutes`,
  ADD COLUMN IF NOT EXISTS `notes`            TEXT         NULL                     AFTER `purpose`,
  ADD COLUMN IF NOT EXISTS `reminder_sent`    TINYINT UNSIGNED NOT NULL DEFAULT 0;

-- ── 3. Widen status enum to accept all new values safely ────
ALTER TABLE `appointments`
  MODIFY `status`
    ENUM('pending','scheduled','confirmed','arrived','completed','cancelled','no_show')
    NOT NULL DEFAULT 'scheduled';

-- Rename legacy pending → scheduled
UPDATE `appointments` SET `status` = 'scheduled' WHERE `status` = 'pending';

-- Remove 'pending' from enum now that no rows use it
ALTER TABLE `appointments`
  MODIFY `status`
    ENUM('scheduled','confirmed','arrived','completed','cancelled','no_show')
    NOT NULL DEFAULT 'scheduled';

-- Sync host_name → person_to_meet for pre-existing rows
UPDATE `appointments`
   SET `person_to_meet` = `host_name`
 WHERE `person_to_meet` = '' AND `host_name` != '';

-- ── 4. Indexes for calendar queries ─────────────────────────
CREATE INDEX IF NOT EXISTS `idx_appt_cal`     ON `appointments` (`scheduled_at`, `status`);
CREATE INDEX IF NOT EXISTS `idx_appt_dept`    ON `appointments` (`department_id`);
CREATE INDEX IF NOT EXISTS `idx_appt_visitor` ON `appointments` (`visitor_id`);
CREATE UNIQUE INDEX IF NOT EXISTS `idx_appt_qr` ON `appointments` (`qr_token`);
