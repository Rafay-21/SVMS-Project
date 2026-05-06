-- ============================================================
-- Migration 006 — Blacklist & Notifications hardening (Phase 4.4)
-- Run via phpMyAdmin or: mysql -u root svms_db < 006_blacklist_notifications.sql
-- ============================================================

-- ── Extend blacklist table ────────────────────────────────────────────────────
ALTER TABLE `blacklist`
  ADD COLUMN IF NOT EXISTS `notes`          TEXT          DEFAULT NULL               AFTER `severity`,
  ADD COLUMN IF NOT EXISTS `source`         ENUM('internal','lea_notice','court_order','self_blocked','other')
                                            NOT NULL DEFAULT 'internal'              AFTER `notes`,
  ADD COLUMN IF NOT EXISTS `expiry_date`    DATE          DEFAULT NULL               AFTER `source`,
  ADD COLUMN IF NOT EXISTS `removed_by`     INT UNSIGNED  DEFAULT NULL               AFTER `expiry_date`,
  ADD COLUMN IF NOT EXISTS `removed_at`     DATETIME      DEFAULT NULL               AFTER `removed_by`,
  ADD COLUMN IF NOT EXISTS `removed_reason` TEXT          DEFAULT NULL               AFTER `removed_at`,
  ADD COLUMN IF NOT EXISTS `block_count`    INT UNSIGNED  NOT NULL DEFAULT 0         AFTER `removed_reason`;

-- Indexes for fast lookup at check-in / registration
ALTER TABLE `blacklist`
  ADD INDEX IF NOT EXISTS `idx_bl_phone`  (`phone`),
  ADD INDEX IF NOT EXISTS `idx_bl_cnic`   (`cnic`),
  ADD INDEX IF NOT EXISTS `idx_bl_active` (`is_active`);

-- ── Extend notifications table ────────────────────────────────────────────────
-- Add role-scoping column (NULL = visible to all admins)
ALTER TABLE `notifications`
  ADD COLUMN IF NOT EXISTS `visible_to_role_id` INT DEFAULT NULL AFTER `recipient_id`;

-- Ensure legacy 'body' rows still render: add alias view is not practical in
-- MySQL without views, but standardise column to 'message' (already done in v3.1)

-- Indexes for notification polling performance
ALTER TABLE `notifications`
  ADD INDEX IF NOT EXISTS `idx_notif_recipient` (`recipient_id`),
  ADD INDEX IF NOT EXISTS `idx_notif_created`   (`created_at`),
  ADD INDEX IF NOT EXISTS `idx_notif_role`      (`visible_to_role_id`),
  ADD INDEX IF NOT EXISTS `idx_notif_read`      (`is_read`);

-- ── Auto-deactivation: mark expired blacklist entries inactive ────────────────
-- A cron or on-read check handles this; the column is set here for clarity.
-- (Optional event if MySQL events are enabled)
-- CREATE EVENT IF NOT EXISTS `evt_expire_blacklist`
--   ON SCHEDULE EVERY 1 HOUR
--   DO UPDATE `blacklist` SET `is_active`=0
--      WHERE `expiry_date` IS NOT NULL AND `expiry_date` < CURDATE() AND `is_active`=1;
