-- Migration 017: Menu item images table
-- Stores file paths for uploaded item images (full-size and thumbnail).
-- item_id references menu_items.id.

CREATE TABLE IF NOT EXISTS `menu_item_images` (
    `id`          BIGINT   UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id`     BIGINT   UNSIGNED NOT NULL,
    `full_path`   VARCHAR(500) NOT NULL DEFAULT '',
    `thumb_path`  VARCHAR(500) NOT NULL DEFAULT '',
    `sort_order`  SMALLINT NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_item_path` (`item_id`, `full_path`(200)),
    INDEX `idx_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
