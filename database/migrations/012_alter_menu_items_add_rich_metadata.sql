-- Migration 012: Preserve rich menu metadata used by legacy frontend parsers

ALTER TABLE `menu_items`
    ADD COLUMN `description`   TEXT         NULL AFTER `item_name`,
    ADD COLUMN `image_url`     VARCHAR(500) NULL AFTER `description`,
    ADD COLUMN `is_jain`       TINYINT(1)   NOT NULL DEFAULT 0 AFTER `is_available`,
    ADD COLUMN `is_chef_special` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_jain`,
    ADD COLUMN `spice_level`   VARCHAR(50)  NULL AFTER `is_chef_special`,
    ADD COLUMN `serving_unit`  VARCHAR(100) NULL AFTER `spice_level`,
    ADD COLUMN `meta_json`     JSON         NULL AFTER `price_columns`;