-- Migration 028: Event check-in logs
-- Stores each partial/full QR admission separately for history and reporting.

CREATE TABLE IF NOT EXISTS `event_checkin_logs` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_id`       VARCHAR(60) NOT NULL,
    `event_id`             VARCHAR(50) NOT NULL,
    `admitted_count`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `guest_names_json`     JSON NULL,
    `verified_by`          VARCHAR(60) NOT NULL DEFAULT '',
    `source`               VARCHAR(30) NOT NULL DEFAULT 'scanner',
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_checkin_logs_tx` (`transaction_id`),
    INDEX `idx_checkin_logs_event` (`event_id`),
    INDEX `idx_checkin_logs_created` (`created_at`),
    CONSTRAINT `fk_checkin_logs_transaction`
        FOREIGN KEY (`transaction_id`) REFERENCES `event_transactions` (`transaction_id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;