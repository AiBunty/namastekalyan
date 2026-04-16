-- Migration 008: Superadmin cash ledger (per-day batch handover approvals)
-- Maps from: SUPERADMIN_CASH_LEDGER sheet

CREATE TABLE IF NOT EXISTS `superadmin_cash_ledger` (
    `id`               BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `batch_key`        VARCHAR(60)  NOT NULL,
    `ledger_date`      DATE         NOT NULL,
    `admin_username`   VARCHAR(50)  NOT NULL,
    `admin_display_name` VARCHAR(100) NOT NULL DEFAULT '',
    `total_transactions` INT        UNSIGNED NOT NULL DEFAULT 0,
    `total_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `requested_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `requested_by`     VARCHAR(50)  NOT NULL DEFAULT '',
    `approved_at`      DATETIME     NULL DEFAULT NULL,
    `approved_by`      VARCHAR(50)  NOT NULL DEFAULT '',
    `status`           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `notes`            VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_batch_key`   (`batch_key`),
    INDEX `idx_ledger_date`     (`ledger_date`),
    INDEX `idx_admin_username`  (`admin_username`),
    INDEX `idx_status`          (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
