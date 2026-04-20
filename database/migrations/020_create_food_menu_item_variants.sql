-- Migration 020: Food menu item variants (Dum Sum / custom-variant items)
-- Used when food_menu_items.pricing_mode = 'custom_variants'.
-- Each row is one variant for a parent food item (e.g. "Veg 2Pcs" → 180).

CREATE TABLE IF NOT EXISTS `food_menu_item_variants` (
    `id`                BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `food_item_id`      BIGINT       UNSIGNED NOT NULL,
    `variant_label`     VARCHAR(100) NOT NULL,       -- e.g. "Veg 2Pcs"
    `price`             DECIMAL(10,2) NOT NULL,
    `variant_sort_order` INT         NOT NULL DEFAULT 0,
    `is_available`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_fmiv_item`   (`food_item_id`),
    INDEX `idx_fmiv_sort`   (`food_item_id`, `variant_sort_order`),
    CONSTRAINT `fk_fmiv_item`
        FOREIGN KEY (`food_item_id`)
        REFERENCES `food_menu_items` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
