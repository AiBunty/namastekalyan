-- Migration 011: API settings (Razorpay keys, CRM token, etc.)
-- Replaces: Google Apps Script Script Properties for managed secrets.

CREATE TABLE IF NOT EXISTS `api_settings` (
    `id`            INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(60)  NOT NULL,
    `setting_value` TEXT         NOT NULL,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed with empty placeholder rows so the admin UI can always read/write them
INSERT IGNORE INTO `api_settings` (`setting_key`, `setting_value`) VALUES
    ('RAZORPAY_KEY_ID',          ''),
    ('RAZORPAY_KEY_SECRET',      ''),
    ('RAZORPAY_WEBHOOK_SECRET',  ''),
    ('CRM_API_TOKEN',            ''),
    ('EVENT_QR_SIGNING_SECRET',  '');
