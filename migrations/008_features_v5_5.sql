-- ============================================================
-- Migration 008 — SVMS v5.5 Features
-- Run once in phpMyAdmin or: mysql -u root svms_db < 008_features_v5_5.sql
-- ============================================================

-- ── Feature D: Add auto_checkout to visit_log.status ENUM ───
ALTER TABLE visit_log
  MODIFY COLUMN status ENUM('checked_in','checked_out','no_show','auto_checkout')
  NOT NULL DEFAULT 'checked_in';

-- ── Feature A: Allow public (visitor-submitted) feedback ─────
-- Add token column for signed one-time public feedback links
ALTER TABLE feedback
  ADD COLUMN IF NOT EXISTS public_token VARCHAR(64) NULL UNIQUE COMMENT 'HMAC token for visitor self-service feedback link',
  ADD COLUMN IF NOT EXISTS source ENUM('staff','visitor') NOT NULL DEFAULT 'staff' COMMENT 'staff=checkout modal, visitor=email link';

-- ── Feature C: Ensure custom_fields has all needed columns ───
ALTER TABLE custom_fields
  ADD COLUMN IF NOT EXISTS label VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'Human-readable label',
  ADD COLUMN IF NOT EXISTS applies_to ENUM('registration','appointment','both') NOT NULL DEFAULT 'registration';

-- Back-fill label from field_name if empty
UPDATE custom_fields SET label = REPLACE(field_name, '_', ' ') WHERE label = '' OR label IS NULL;

-- ── Feature E: Emergency snapshot table ──────────────────────
CREATE TABLE IF NOT EXISTS emergency_snapshots (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
  triggered_by INT            NULL COMMENT 'FK admins.id',
  mode        VARCHAR(20)     NOT NULL DEFAULT 'evacuation',
  visitor_count INT UNSIGNED  NOT NULL DEFAULT 0,
  snapshot_file VARCHAR(300)  NULL COMMENT 'Relative path under LOG_DIR',
  notes       TEXT            NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (triggered_by),
  INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
