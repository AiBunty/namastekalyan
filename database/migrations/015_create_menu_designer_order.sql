-- Migration 015: Menu designer order table
-- Stores custom sort order for categories and items within each sheet.
-- scope_type='category' → scope_key is the category name, item_id=0
-- scope_type='item'     → scope_key is category name, item_id is menu_items.id

CREATE TABLE IF NOT EXISTS `menu_designer_order` (
    `id`          BIGINT   UNSIGNED NOT NULL AUTO_INCREMENT,
    `sheet_type`  ENUM('food','bar') NOT NULL,
    `scope_type`  ENUM('category','item') NOT NULL,
    `scope_key`   VARCHAR(200) NOT NULL DEFAULT '',
    `item_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sort_order`  SMALLINT NOT NULL DEFAULT 0,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_scope` (`sheet_type`, `scope_type`, `scope_key`, `item_id`),
    INDEX `idx_sheet_scope` (`sheet_type`, `scope_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
