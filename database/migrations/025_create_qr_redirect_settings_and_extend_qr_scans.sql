CREATE TABLE `qr_redirect_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel` VARCHAR(20) NOT NULL,
    `destination_mode` VARCHAR(20) NOT NULL DEFAULT 'preset',
    `destination_key` VARCHAR(40) DEFAULT NULL,
    `manual_url` VARCHAR(2048) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` VARCHAR(120) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_qr_redirect_channel` (`channel`),
    KEY `idx_qr_redirect_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `qr_redirect_settings` (`channel`, `destination_mode`, `destination_key`, `manual_url`, `is_active`, `updated_by`)
VALUES
    ('customer', 'preset', 'menu', NULL, 1, 'migration'),
    ('admin', 'preset', 'admin', NULL, 1, 'migration');

ALTER TABLE `qr_scans`
    ADD COLUMN `channel` VARCHAR(20) NOT NULL DEFAULT 'customer' AFTER `scan_number`,
    ADD COLUMN `destination_key` VARCHAR(40) DEFAULT NULL AFTER `channel`,
    ADD COLUMN `destination_label` VARCHAR(120) DEFAULT NULL AFTER `destination_key`,
    ADD COLUMN `resolved_url` TEXT DEFAULT NULL AFTER `destination_label`,
    ADD INDEX `idx_qr_scans_channel` (`channel`),
    ADD INDEX `idx_qr_scans_channel_scanned_at` (`channel`, `scanned_at`);
