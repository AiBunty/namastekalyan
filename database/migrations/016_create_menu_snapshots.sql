-- Migration 016: Menu snapshots table
-- Stores point-in-time full copies of a sheet for restore/rollback.

CREATE TABLE IF NOT EXISTS `menu_snapshots` (
    `id`              BIGINT   UNSIGNED NOT NULL AUTO_INCREMENT,
    `sheet_type`      ENUM('food','bar') NOT NULL,
    `label`           VARCHAR(200) NOT NULL DEFAULT '',
    `snapshot_data`   LONGTEXT    NOT NULL,
    `row_count`       INT UNSIGNED NOT NULL DEFAULT 0,
    `triggered_by`    VARCHAR(100) NOT NULL DEFAULT '',
    `created_by`      VARCHAR(100) NOT NULL DEFAULT '',
    `created_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_sheet_type` (`sheet_type`),
    INDEX `idx_created`    (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
