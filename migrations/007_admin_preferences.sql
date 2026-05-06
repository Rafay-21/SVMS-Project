-- ============================================================
-- Migration 007: Admin preference columns (theme + language)
-- Adds per-admin theme mode and language preference columns
-- that are hydrated into session on login.
-- ============================================================

ALTER TABLE `admins`
  ADD COLUMN IF NOT EXISTS `theme`    VARCHAR(10)  NOT NULL DEFAULT 'system'
      COMMENT 'Preferred theme mode: light | dark | system'
      AFTER `last_login_ip`,
  ADD COLUMN IF NOT EXISTS `language` VARCHAR(5)   NOT NULL DEFAULT 'en'
      COMMENT 'Preferred UI language: en | ur'
      AFTER `theme`;

-- Update existing rows to sensible defaults
UPDATE `admins` SET `theme` = 'system' WHERE `theme`    = '';
UPDATE `admins` SET `language` = 'en'  WHERE `language` = '';
