-- ============================================================
-- Migration 010 — Backup Tracking Table (PROMPT 6.2)
-- Run once in phpMyAdmin against svms_db.
-- ============================================================

CREATE TABLE IF NOT EXISTS backups (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    filename     VARCHAR(300)    NOT NULL,
    size_bytes   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_by   INT UNSIGNED    NULL COMMENT 'FK admins.id; NULL = cron/automated',
    type         ENUM('manual','automated') NOT NULL DEFAULT 'manual',
    status       ENUM('ok','error')         NOT NULL DEFAULT 'ok',
    error        TEXT            NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bk_created (created_at),
    INDEX idx_bk_type    (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
