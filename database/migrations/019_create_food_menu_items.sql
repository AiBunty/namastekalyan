-- Migration 019: Food menu items (AWGNK MENU sheet)
-- Replaces: menu_items WHERE sheet_type='food'
-- Individual price columns instead of JSON blob.
-- Supports pricing_mode='standard' (pre-defined price columns)
-- and pricing_mode='custom_variants' (Dum Sum style, see food_menu_item_variants table).

CREATE TABLE IF NOT EXISTS `food_menu_items` (
    `id`                BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `category`          VARCHAR(100) NOT NULL DEFAULT '',
    `item_name`         VARCHAR(200) NOT NULL,
    `description`       TEXT         NULL,
    `image_url`         VARCHAR(500) NULL,

    -- ── Availability ──────────────────────────────────────────────────────────
    `is_available`      TINYINT(1)   NOT NULL DEFAULT 1,

    -- ── Diet classification (inferred from which price cols are non-null) ──────
    `is_veg`            TINYINT(1)   NOT NULL DEFAULT 0,
    `is_nonveg`         TINYINT(1)   NOT NULL DEFAULT 0,
    `is_jain`           TINYINT(1)   NOT NULL DEFAULT 0,
    -- Universal = shown regardless of veg/nonveg filter (e.g. Breads, Mocktails)
    `is_universal`      TINYINT(1)   NOT NULL DEFAULT 0,

    -- ── Display flags ─────────────────────────────────────────────────────────
    `is_chef_special`   TINYINT(1)   NOT NULL DEFAULT 0,
    `spice_level`       VARCHAR(50)  NULL,
    `serving_unit`      VARCHAR(100) NULL,           -- e.g. "Serving: 3 Pcs"

    -- ── Pricing mode ──────────────────────────────────────────────────────────
    -- 'standard'        → price columns below hold the actual prices
    -- 'custom_variants' → prices stored in food_menu_item_variants (Dum Sum etc.)
    `pricing_mode`      ENUM('standard','custom_variants') NOT NULL DEFAULT 'standard',

    -- ── Individual price columns (AWGNK MENU master column order) ────────────
    `price_veg`         DECIMAL(10,2) NULL,
    `price_jain`        DECIMAL(10,2) NULL,
    `price_chicken`     DECIMAL(10,2) NULL,
    `price_mutton`      DECIMAL(10,2) NULL,
    `price_basa`        DECIMAL(10,2) NULL,
    `price_prawns`      DECIMAL(10,2) NULL,
    `price_surmai`      DECIMAL(10,2) NULL,
    `price_pomfret`     DECIMAL(10,2) NULL,
    `price_crab`        DECIMAL(10,2) NULL,
    `price_egg`         DECIMAL(10,2) NULL,
    `price_half`        DECIMAL(10,2) NULL,
    `price_full`        DECIMAL(10,2) NULL,
    `price_plain`       DECIMAL(10,2) NULL,
    `price_butter`      DECIMAL(10,2) NULL,
    `price_medium`      DECIMAL(10,2) NULL,
    `price_large`       DECIMAL(10,2) NULL,
    `price_direct`      DECIMAL(10,2) NULL,          -- single-price items (no variants)

    -- ── Sort order (preserves Excel row order) ────────────────────────────────
    `category_sort_order` INT         NOT NULL DEFAULT 0,
    `item_sort_order`     INT         NOT NULL DEFAULT 0,
    `source_row`          INT         NULL,          -- original row number in Excel

    -- ── Edit tracking ─────────────────────────────────────────────────────────
    `manually_edited`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_food_category`     (`category`),
    INDEX `idx_food_available`    (`is_available`),
    INDEX `idx_food_sort`         (`category_sort_order`, `item_sort_order`),
    INDEX `idx_food_is_veg`       (`is_veg`),
    INDEX `idx_food_is_nonveg`    (`is_nonveg`),
    INDEX `idx_food_is_universal` (`is_universal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
