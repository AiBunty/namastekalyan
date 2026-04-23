-- Migration 033: persistent event-specific WhatsApp message versions and mapping linkage.

CREATE TABLE IF NOT EXISTS `whatsapp_event_message_versions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_key` VARCHAR(80) NOT NULL,
    `source_draft_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `version_label` VARCHAR(160) NOT NULL DEFAULT '',
    `template_name` VARCHAR(120) NOT NULL DEFAULT '',
    `language_code` VARCHAR(20) NOT NULL DEFAULT 'en',
    `category` VARCHAR(30) NOT NULL DEFAULT 'UTILITY',
    `header_type` VARCHAR(20) NOT NULL DEFAULT 'NONE',
    `header_text` VARCHAR(120) NOT NULL DEFAULT '',
    `body_text` TEXT NOT NULL,
    `footer_text` VARCHAR(120) NOT NULL DEFAULT '',
    `buttons_json` LONGTEXT NULL,
    `sample_variables_json` LONGTEXT NULL,
    `example_media_handle` VARCHAR(255) NOT NULL DEFAULT '',
    `source_template_uid` VARCHAR(80) NOT NULL DEFAULT '',
    `meta_template_uid` VARCHAR(80) NOT NULL DEFAULT '',
    `meta_status` VARCHAR(40) NOT NULL DEFAULT 'draft',
    `is_current` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` VARCHAR(30) NOT NULL DEFAULT '',
    `updated_by` VARCHAR(30) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_whatsapp_event_versions_event` (`event_key`),
    KEY `idx_whatsapp_event_versions_current` (`event_key`, `is_current`),
    KEY `idx_whatsapp_event_versions_template` (`template_name`, `language_code`),
    KEY `idx_whatsapp_event_versions_draft` (`source_draft_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `whatsapp_event_mappings`
    ADD COLUMN `mapped_version_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `language_code`,
    ADD COLUMN `mapped_template_uid` VARCHAR(80) NOT NULL DEFAULT '' AFTER `mapped_version_id`;

CREATE INDEX `idx_whatsapp_event_mappings_version` ON `whatsapp_event_mappings` (`mapped_version_id`);