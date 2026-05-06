-- SVMS Migration 004 — Email Queue
-- Run once: php scripts/run_migration.php 004  (or execute manually in phpMyAdmin)

CREATE TABLE IF NOT EXISTS `email_queue` (
    `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `to_email`     VARCHAR(320)     NOT NULL,
    `subject`      VARCHAR(998)     NOT NULL,
    `body_html`    MEDIUMTEXT       NOT NULL,
    `body_plain`   MEDIUMTEXT,
    `status`       ENUM('pending','sending','sent','failed')
                                    NOT NULL DEFAULT 'pending',
    `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_error`   TEXT,
    `scheduled_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at`      DATETIME,
    `related_type` VARCHAR(64),
    `related_id`   INT UNSIGNED,
    PRIMARY KEY (`id`),
    INDEX `idx_status_sched` (`status`, `scheduled_at`),
    INDEX `idx_related`      (`related_type`, `related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
