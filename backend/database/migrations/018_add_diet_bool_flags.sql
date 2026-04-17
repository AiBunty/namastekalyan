-- Migration 018: Add boolean diet flags to menu_items
-- is_veg, is_nonveg, is_universal allow per-item diet assignment independent of food_category
-- These complement the existing primary_diet / computed_diets JSON fields.

ALTER TABLE `menu_items`
    ADD COLUMN `is_veg`       TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Explicitly marked as vegetarian'
        AFTER `is_jain`,
    ADD COLUMN `is_nonveg`    TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Explicitly marked as non-vegetarian'
        AFTER `is_veg`,
    ADD COLUMN `is_universal` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Available for all diet types (e.g. drinks, desserts)'
        AFTER `is_nonveg`;

-- Back-fill from existing food_category and primary_diet
UPDATE `menu_items`
SET
    `is_veg`       = CASE WHEN `food_category` = 'Veg'    OR `primary_diet` = 'veg'       THEN 1 ELSE 0 END,
    `is_nonveg`    = CASE WHEN `food_category` = 'NonVeg' OR `primary_diet` = 'nonveg'    THEN 1 ELSE 0 END,
    `is_universal` = CASE WHEN `primary_diet` = 'universal'                               THEN 1 ELSE 0 END;

ALTER TABLE `menu_items`
    ADD INDEX `idx_is_veg`       (`is_veg`),
    ADD INDEX `idx_is_nonveg`    (`is_nonveg`),
    ADD INDEX `idx_is_universal` (`is_universal`);
