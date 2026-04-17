-- Migration 016: Menu import snapshots
-- Keeps up to the last 10 snapshots per sheet_type for rollback.
-- snapshot_data: full JSON of all menu_items rows at time of snapshot.
-- triggered_by: 'import' | 'manual' | 'schedule'

CREATE TABLE IF NOT EXISTS `menu_snapshots` (
    `id`           INT      UNSIGNED NOT NULL AUTO_INCREMENT,
    `sheet_type`   ENUM('food','bar') NOT NULL,
    `label`        VARCHAR(120)       NOT NULL DEFAULT '' COMMENT 'Human readable label e.g. "Before Excel import 2026-04-17"',
    `snapshot_data` LONGTEXT          NOT NULL             COMMENT 'JSON array of all menu_items for this sheet_type',
    `row_count`    SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    `triggered_by` ENUM('import','manual','schedule') NOT NULL DEFAULT 'import',
    `created_by`   VARCHAR(100)       NULL COMMENT 'Admin username',
    `created_at`   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_sheet_created` (`sheet_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
