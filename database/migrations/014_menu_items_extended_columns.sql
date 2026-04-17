-- Migration 014: Add extended columns to menu_items
-- Adds: description, image_url, is_jain, is_chef_special, spice_level,
--       serving_unit, primary_diet, meta_json, manually_edited
-- These columns were referenced in addItem/updateItem logic but absent from
-- the original 010_create_menu_items.sql definition.

ALTER TABLE `menu_items`
    ADD COLUMN IF NOT EXISTS `description`      VARCHAR(500)  NOT NULL DEFAULT '' AFTER `item_name`,
    ADD COLUMN IF NOT EXISTS `image_url`        VARCHAR(500)  NOT NULL DEFAULT '' AFTER `description`,
    ADD COLUMN IF NOT EXISTS `is_jain`          TINYINT(1)    NOT NULL DEFAULT 0  AFTER `is_available`,
    ADD COLUMN IF NOT EXISTS `is_chef_special`  TINYINT(1)    NOT NULL DEFAULT 0  AFTER `is_jain`,
    ADD COLUMN IF NOT EXISTS `spice_level`      VARCHAR(20)   NOT NULL DEFAULT '' AFTER `is_chef_special`,
    ADD COLUMN IF NOT EXISTS `serving_unit`     VARCHAR(30)   NOT NULL DEFAULT '' AFTER `spice_level`,
    ADD COLUMN IF NOT EXISTS `primary_diet`     VARCHAR(20)   NOT NULL DEFAULT '' AFTER `food_category`,
    ADD COLUMN IF NOT EXISTS `meta_json`        JSON          NULL                AFTER `primary_diet`,
    ADD COLUMN IF NOT EXISTS `manually_edited`  TINYINT(1)    NOT NULL DEFAULT 0  AFTER `meta_json`;
