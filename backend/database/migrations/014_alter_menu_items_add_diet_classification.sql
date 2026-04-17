-- Migration 014: Add diet classification columns to menu_items
-- Supports: veg, nonveg, jain, mixed (veg+nonveg), universal (always visible), bar (bar items), '' (uncategorised)
-- computed_diets: all detected diets from Excel flags e.g. ["veg","jain"]
-- primary_diet: single canonical diet used for public filter: veg|nonveg|jain|mixed|universal|bar|''
-- diet_column_map: maps serving/price column keys to diet class e.g. {"Chicken":"nonveg","Half":"veg","Full":"veg"}

ALTER TABLE `menu_items`
    ADD COLUMN `computed_diets` JSON         NULL          COMMENT 'All diet flags detected e.g. ["veg","jain"]'
        AFTER `food_category`,
    ADD COLUMN `primary_diet`   ENUM('veg','nonveg','jain','mixed','universal','bar','')
                                             NOT NULL DEFAULT ''
                                             COMMENT 'Canonical diet for public menu filter'
        AFTER `computed_diets`,
    ADD COLUMN `diet_column_map` JSON        NULL          COMMENT 'Per-column diet mapping {"Chicken":"nonveg","Half":"serving"}'
        AFTER `primary_diet`;

-- Back-fill primary_diet from existing food_category for rows already in DB
UPDATE `menu_items`
SET `primary_diet` = CASE
    WHEN `food_category` = 'Veg'    THEN 'veg'
    WHEN `food_category` = 'NonVeg' THEN 'nonveg'
    WHEN `food_category` = 'Jain'   THEN 'jain'
    WHEN `sheet_type`    = 'bar'    THEN 'bar'
    ELSE ''
END;

ALTER TABLE `menu_items`
    ADD INDEX `idx_primary_diet` (`primary_diet`);
