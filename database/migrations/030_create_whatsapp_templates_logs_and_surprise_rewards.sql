-- Migration 030: WhatsApp Cloud templates, event mappings, logs, and surprise rewards.

ALTER TABLE `leads`
    ADD COLUMN `surprise_reward_label` VARCHAR(200) NULL DEFAULT NULL AFTER `prize`,
    ADD COLUMN `surprise_coupon_code` VARCHAR(30) NULL DEFAULT NULL AFTER `coupon_code`,
    ADD COLUMN `surprise_issued_at` DATETIME NULL DEFAULT NULL AFTER `surprise_coupon_code`,
    ADD COLUMN `surprise_issued_by` VARCHAR(30) NULL DEFAULT NULL AFTER `surprise_issued_at`,
    ADD COLUMN `surprise_redeemed_at` DATETIME NULL DEFAULT NULL AFTER `surprise_issued_by`;

CREATE TABLE IF NOT EXISTS `whatsapp_message_templates` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_uid` VARCHAR(80) NOT NULL,
    `template_name` VARCHAR(120) NOT NULL,
    `language_code` VARCHAR(20) NOT NULL,
    `category` VARCHAR(40) NOT NULL DEFAULT '',
    `status` VARCHAR(40) NOT NULL DEFAULT '',
    `quality_score` VARCHAR(40) NOT NULL DEFAULT '',
    `components_json` LONGTEXT NULL,
    `last_synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_whatsapp_template_uid` (`template_uid`),
    UNIQUE KEY `uq_whatsapp_template_name_lang` (`template_name`, `language_code`),
    KEY `idx_whatsapp_template_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_event_mappings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_key` VARCHAR(80) NOT NULL,
    `template_name` VARCHAR(120) NOT NULL DEFAULT '',
    `language_code` VARCHAR(20) NOT NULL DEFAULT '',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_by` VARCHAR(30) NOT NULL DEFAULT '',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_whatsapp_event_key` (`event_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_message_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `event_key` VARCHAR(80) NOT NULL DEFAULT '',
    `phone` VARCHAR(20) NOT NULL DEFAULT '',
    `template_name` VARCHAR(120) NOT NULL DEFAULT '',
    `language_code` VARCHAR(20) NOT NULL DEFAULT '',
    `attempted` TINYINT(1) NOT NULL DEFAULT 0,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `http_code` VARCHAR(20) NOT NULL DEFAULT '',
    `response_message` VARCHAR(500) NOT NULL DEFAULT '',
    `request_payload_json` LONGTEXT NULL,
    `response_payload_json` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_whatsapp_logs_event` (`event_key`),
    KEY `idx_whatsapp_logs_lead` (`lead_id`),
    KEY `idx_whatsapp_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `whatsapp_event_mappings` (`event_key`, `template_name`, `language_code`, `is_enabled`, `updated_by`)
VALUES ('try_again_surprise_issued', '', '', 0, 'migration-030');