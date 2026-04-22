-- Migration 032: WhatsApp scheduled reminders and local template drafts.

CREATE TABLE IF NOT EXISTS `whatsapp_scheduled_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_key` VARCHAR(80) NOT NULL,
    `transaction_id` VARCHAR(80) NULL DEFAULT NULL,
    `lead_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `phone` VARCHAR(20) NOT NULL DEFAULT '',
    `customer_name` VARCHAR(160) NOT NULL DEFAULT '',
    `event_id` VARCHAR(80) NOT NULL DEFAULT '',
    `event_title` VARCHAR(200) NOT NULL DEFAULT '',
    `due_at` DATETIME NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
    `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_result_code` VARCHAR(40) NOT NULL DEFAULT '',
    `last_result_message` VARCHAR(500) NOT NULL DEFAULT '',
    `payload_json` LONGTEXT NULL,
    `sent_at` DATETIME NULL DEFAULT NULL,
    `cancelled_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_whatsapp_schedule_event_tx` (`event_key`, `transaction_id`),
    KEY `idx_whatsapp_schedule_due_status` (`status`, `due_at`),
    KEY `idx_whatsapp_schedule_event` (`event_key`),
    KEY `idx_whatsapp_schedule_tx` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_template_drafts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `draft_name` VARCHAR(160) NOT NULL DEFAULT '',
    `template_name` VARCHAR(120) NOT NULL,
    `category` VARCHAR(30) NOT NULL DEFAULT 'UTILITY',
    `language_code` VARCHAR(20) NOT NULL DEFAULT 'en',
    `header_type` VARCHAR(20) NOT NULL DEFAULT 'NONE',
    `header_text` VARCHAR(120) NOT NULL DEFAULT '',
    `body_text` TEXT NOT NULL,
    `footer_text` VARCHAR(120) NOT NULL DEFAULT '',
    `buttons_json` LONGTEXT NULL,
    `sample_variables_json` LONGTEXT NULL,
    `example_media_handle` VARCHAR(255) NOT NULL DEFAULT '',
    `status` VARCHAR(40) NOT NULL DEFAULT 'draft',
    `meta_template_id` VARCHAR(80) NOT NULL DEFAULT '',
    `submitted_at` DATETIME NULL DEFAULT NULL,
    `last_synced_at` DATETIME NULL DEFAULT NULL,
    `rejection_reason` VARCHAR(500) NOT NULL DEFAULT '',
    `created_by` VARCHAR(30) NOT NULL DEFAULT '',
    `updated_by` VARCHAR(30) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_whatsapp_template_drafts_status` (`status`),
    KEY `idx_whatsapp_template_drafts_name_lang` (`template_name`, `language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `whatsapp_event_mappings` (`event_key`, `template_name`, `language_code`, `is_enabled`, `updated_by`)
VALUES
    ('event_registration_confirmed', '', '', 0, 'migration-032'),
    ('winner_coupon_issued', '', '', 0, 'migration-032'),
    ('coupon_redeemed', '', '', 0, 'migration-032'),
    ('guest_checked_in', '', '', 0, 'migration-032'),
    ('event_reminder_24h_image', '', '', 0, 'migration-032'),
    ('event_reminder_6h_image', '', '', 0, 'migration-032'),
    ('event_reminder_2h_image', '', '', 0, 'migration-032'),
    ('event_checkin_pending_30m', '', '', 0, 'migration-032'),
    ('event_checkin_thank_you_12h', '', '', 0, 'migration-032');