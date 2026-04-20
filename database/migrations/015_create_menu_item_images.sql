-- Migration 015: Menu item images table
-- Stores self-hosted WebP images per menu item.
-- thumb_path is auto-generated at 300×300, full_path at original aspect ratio max 1200px.

CREATE TABLE IF NOT EXISTS `menu_item_images` (
    `id`          BIGINT  UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id`     BIGINT  UNSIGNED NOT NULL,
    `sheet_type`  ENUM('food','bar') NOT NULL,
    `full_path`   VARCHAR(500)       NOT NULL  COMMENT 'Relative path from backend/public/',
    `thumb_path`  VARCHAR(500)       NULL      COMMENT '300x300 crop path',
    `original_name` VARCHAR(256)     NULL      COMMENT 'Original upload filename',
    `file_size`   INT     UNSIGNED   NULL      COMMENT 'Bytes of WebP full image',
    `width`       SMALLINT UNSIGNED  NULL,
    `height`      SMALLINT UNSIGNED  NULL,
    `sort_order`  TINYINT UNSIGNED   NOT NULL DEFAULT 0 COMMENT '0=primary',
    `uploaded_at` DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_item_id`    (`item_id`),
    INDEX `idx_sheet_type` (`sheet_type`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
