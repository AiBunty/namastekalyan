-- Migration 021: Bar menu items (BAR MENU NK sheet)
-- Replaces: menu_items WHERE sheet_type='bar'
-- Bar items have NO diet classification (food logic does not apply to drinks).
-- All variant prices are stored in bar_menu_item_variants (one row per price column).

CREATE TABLE IF NOT EXISTS `bar_menu_items` (
    `id`                BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `category`          VARCHAR(100) NOT NULL DEFAULT '',
    `item_name`         VARCHAR(200) NOT NULL,
    `description`       TEXT         NULL,
    `image_url`         VARCHAR(500) NULL,

    -- ── Availability ──────────────────────────────────────────────────────────
    `is_available`      TINYINT(1)   NOT NULL DEFAULT 1,

    -- ── Bar-specific flags ────────────────────────────────────────────────────
    `barman_special`    TINYINT(1)   NOT NULL DEFAULT 0,   -- "Bar Man Special" column

    -- ── Sort order (preserves Excel row order) ────────────────────────────────
    `category_sort_order` INT         NOT NULL DEFAULT 0,
    `item_sort_order`     INT         NOT NULL DEFAULT 0,
    `source_row`          INT         NULL,

    -- ── Edit tracking ─────────────────────────────────────────────────────────
    `manually_edited`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_bar_category`     (`category`),
    INDEX `idx_bar_available`    (`is_available`),
    INDEX `idx_bar_sort`         (`category_sort_order`, `item_sort_order`),
    INDEX `idx_bar_barman`       (`barman_special`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
