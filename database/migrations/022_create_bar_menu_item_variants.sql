-- Migration 022: Bar menu item variants
-- Each row is one pricing variant for a bar item (e.g. "30Ml" → 250, "Btl" → 2200).
-- variant_label preserves the exact Excel column header.

CREATE TABLE IF NOT EXISTS `bar_menu_item_variants` (
    `id`                BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `bar_item_id`       BIGINT       UNSIGNED NOT NULL,
    `variant_label`     VARCHAR(100) NOT NULL,       -- exact Excel column name: "30Ml", "Btl", etc.
    `price`             DECIMAL(10,2) NOT NULL,
    `variant_sort_order` INT         NOT NULL DEFAULT 0,
    `is_available`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_bmiv_item`  (`bar_item_id`),
    INDEX `idx_bmiv_sort`  (`bar_item_id`, `variant_sort_order`),
    CONSTRAINT `fk_bmiv_item`
        FOREIGN KEY (`bar_item_id`)
        REFERENCES `bar_menu_items` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
