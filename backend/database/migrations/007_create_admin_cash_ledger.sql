-- Migration 007: Admin cash ledger (per-cashier daily cash passes)
-- Maps from: ADMIN_CASH_LEDGER sheet

CREATE TABLE IF NOT EXISTS `admin_cash_ledger` (
    `id`                      BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `entry_id`                VARCHAR(60)  NOT NULL,  -- e.g. "CASH-..."
    `ledger_date`             DATE         NOT NULL,
    `admin_username`          VARCHAR(50)  NOT NULL,
    `admin_display_name`      VARCHAR(100) NOT NULL DEFAULT '',
    `transaction_id`          VARCHAR(60)  NOT NULL DEFAULT '',
    `event_id`                VARCHAR(50)  NOT NULL DEFAULT '',
    `event_title`             VARCHAR(200) NOT NULL DEFAULT '',
    `customer_name`           VARCHAR(150) NOT NULL DEFAULT '',
    `customer_phone`          VARCHAR(20)  NOT NULL DEFAULT '',
    `qty`                     SMALLINT     UNSIGNED NOT NULL DEFAULT 1,
    `amount`                  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency`                VARCHAR(5)   NOT NULL DEFAULT 'INR',
    `status`                  VARCHAR(30)  NOT NULL DEFAULT 'issued',
    -- possible: issued, handover_requested, handover_approved, cancelled
    `issued_at`               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `handover_requested_at`   DATETIME     NULL DEFAULT NULL,
    `handover_approved_at`    DATETIME     NULL DEFAULT NULL,
    `handover_approved_by`    VARCHAR(50)  NOT NULL DEFAULT '',
    `handover_batch_key`      VARCHAR(60)  NOT NULL DEFAULT '',
    `notes`                   VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_entry_id`     (`entry_id`),
    INDEX `idx_ledger_date`      (`ledger_date`),
    INDEX `idx_admin_username`   (`admin_username`),
    INDEX `idx_status`           (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
