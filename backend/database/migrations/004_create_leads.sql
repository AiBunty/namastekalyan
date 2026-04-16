-- Migration 004: Leads (Spin & Win)
-- Maps from: Leads sheet
-- Columns: Timestamp, Name, Phone, Prize, Status, Date Of Birth,
--          Date Of Anniversary, Source, Visit Count, CRM Sync Status,
--          CRM Sync Code, CRM Sync Message, Coupon Code

CREATE TABLE IF NOT EXISTS `leads` (
    `id`                BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `name`              VARCHAR(150) NOT NULL,
    `phone`             VARCHAR(15)  NOT NULL,
    `prize`             VARCHAR(200) NOT NULL DEFAULT '',
    `status`            ENUM('Unredeemed','Redeemed') NOT NULL DEFAULT 'Unredeemed',
    `date_of_birth`     DATE         NULL DEFAULT NULL,
    `date_of_anniversary` DATE       NULL DEFAULT NULL,
    `source`            VARCHAR(60)  NOT NULL DEFAULT 'menu-blocker-web',
    `visit_count`       SMALLINT     UNSIGNED NOT NULL DEFAULT 1,
    `coupon_code`       VARCHAR(30)  NOT NULL DEFAULT '',
    `crm_sync_status`   ENUM('Pending','Success','Failed','Skipped') NOT NULL DEFAULT 'Pending',
    `crm_sync_code`     VARCHAR(20)  NOT NULL DEFAULT '',
    `crm_sync_message`  VARCHAR(255) NOT NULL DEFAULT '',
    `redeemed_at`       DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_phone`      (`phone`),
    INDEX `idx_coupon_code`(`coupon_code`),
    INDEX `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
