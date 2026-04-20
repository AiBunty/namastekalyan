CREATE TABLE `qr_redirects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(160) NOT NULL,
    `slug` VARCHAR(160) NOT NULL,
    `redirect_mode` VARCHAR(20) NOT NULL DEFAULT 'preset',
    `preset_key` VARCHAR(40) DEFAULT NULL,
    `manual_url` VARCHAR(2048) DEFAULT NULL,
    `legacy_channel` VARCHAR(20) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` VARCHAR(120) DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` VARCHAR(120) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_qr_redirect_slug` (`slug`),
    UNIQUE KEY `uq_qr_redirect_legacy_channel` (`legacy_channel`),
    KEY `idx_qr_redirect_active` (`is_active`),
    KEY `idx_qr_redirect_system` (`is_system`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `qr_redirects` (
    `name`, `slug`, `redirect_mode`, `preset_key`, `manual_url`, `legacy_channel`, `notes`, `is_active`, `is_system`, `created_by`, `updated_by`
) VALUES
    ('Guest QR', 'guest-menu', 'preset', 'menu', NULL, 'customer', 'Legacy guest QR record seeded from migration.', 1, 1, 'migration', 'migration'),
    ('Admin QR', 'admin-portal', 'preset', 'admin', NULL, 'admin', 'Legacy admin QR record seeded from migration.', 1, 1, 'migration', 'migration');

ALTER TABLE `qr_scans`
    ADD COLUMN `qr_id` INT UNSIGNED DEFAULT NULL AFTER `channel`,
    ADD COLUMN `qr_slug` VARCHAR(160) DEFAULT NULL AFTER `qr_id`,
    ADD INDEX `idx_qr_scans_qr_id` (`qr_id`),
    ADD INDEX `idx_qr_scans_qr_slug` (`qr_slug`);

UPDATE `qr_scans`
SET `qr_slug` = CASE
    WHEN `channel` = 'admin' THEN 'admin-portal'
    ELSE 'guest-menu'
END
WHERE `qr_slug` IS NULL OR `qr_slug` = '';

UPDATE `qr_scans` s
INNER JOIN `qr_redirects` q ON q.slug = s.qr_slug
SET s.qr_id = q.id
WHERE s.qr_id IS NULL;