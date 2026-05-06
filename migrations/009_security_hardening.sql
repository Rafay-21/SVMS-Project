-- ============================================================
-- Migration 009 — Security Hardening (PROMPT 6.1)
-- Run once in phpMyAdmin against svms_db.
-- ============================================================

-- Admin password history (for last-3-reuse check)
CREATE TABLE IF NOT EXISTS admin_password_history (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NOT NULL,
    pass_hash   VARCHAR(255) NOT NULL,
    changed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aph_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate-limit log (optional DB-backed, currently file-backed — kept for future use)
-- No schema change needed for file-based rate limiter.

-- Ensure admin_id FK (if admins table exists; adjust table name if needed)
-- ALTER TABLE admin_password_history
--   ADD CONSTRAINT fk_aph_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE;
