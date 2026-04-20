-- Migration 013: Persist menu category and item ordering for designer UI

CREATE TABLE IF NOT EXISTS `menu_designer_order` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sheet_type` ENUM('food','bar') NOT NULL,
    `scope_type` ENUM('category','item') NOT NULL,
    `scope_key`  VARCHAR(160) NOT NULL DEFAULT '',
    -- item_id=0 is used for category ordering rows
    `item_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_designer_order` (`sheet_type`, `scope_type`, `scope_key`, `item_id`),
    KEY `idx_designer_scope` (`sheet_type`, `scope_type`, `scope_key`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
