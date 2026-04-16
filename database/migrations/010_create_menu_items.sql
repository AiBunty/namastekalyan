-- Migration 010: Menu items (food + bar)
-- Replaces: AWGNK MENU sheet (sheet_type='food') and BAR MENU NK sheet (sheet_type='bar')
-- Headers are dynamic in the source sheet; here we normalise to fixed columns.
-- Extra/price columns are stored in the JSON `price_columns` field.

CREATE TABLE IF NOT EXISTS `menu_items` (
    `id`              BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `sheet_type`      ENUM('food','bar') NOT NULL,  -- 'food' = AWGNK MENU, 'bar' = BAR MENU NK
    `category`        VARCHAR(100) NOT NULL DEFAULT '',
    `sub_category`    VARCHAR(100) NOT NULL DEFAULT '',
    `item_name`       VARCHAR(200) NOT NULL,
    -- Visibility flag (maps to "Availability" column in the sheet)
    `is_available`    TINYINT(1)   NOT NULL DEFAULT 1,
    -- Base/default price (kept for quick queries)
    `base_price`      DECIMAL(10,2) NULL DEFAULT NULL,
    -- All price-type columns stored as JSON: {"Half":150,"Full":250,"Veg":200,...}
    -- Column headers are preserved so the admin editor can reconstruct the original grid.
    `price_columns`   JSON         NULL,
    -- Food category classification (food sheet only)
    `food_category`   ENUM('Veg','NonVeg','Jain','') NOT NULL DEFAULT '',
    -- Display sort order within category
    `sort_order`      SMALLINT     UNSIGNED NOT NULL DEFAULT 0,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_sheet_type`  (`sheet_type`),
    INDEX `idx_category`    (`sheet_type`, `category`),
    INDEX `idx_available`   (`is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menu schema version tracking (stores the ordered column headers per sheet)
CREATE TABLE IF NOT EXISTS `menu_schema` (
    `id`          INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    `sheet_type`  ENUM('food','bar') NOT NULL,
    -- JSON array of column header names in sheet order
    `headers`     JSON         NOT NULL,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sheet_type` (`sheet_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
