-- Migration 006: Event transactions (Razorpay payments + free registrations + cash passes)
-- Maps from: EVENT_TRANSACTIONS sheet

CREATE TABLE IF NOT EXISTS `event_transactions` (
    `id`                     BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_id`         VARCHAR(60)  NOT NULL,  -- e.g. "TXN-..." or "REG-..."
    `event_id`               VARCHAR(50)  NOT NULL,
    `event_title`            VARCHAR(200) NOT NULL DEFAULT '',
    `customer_name`          VARCHAR(150) NOT NULL DEFAULT '',
    `customer_email`         VARCHAR(150) NOT NULL DEFAULT '',
    `customer_phone`         VARCHAR(20)  NOT NULL DEFAULT '',
    `qty`                    SMALLINT     UNSIGNED NOT NULL DEFAULT 1,
    `amount`                 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency`               VARCHAR(5)   NOT NULL DEFAULT 'INR',
    `gateway`                ENUM('razorpay','cash','free') NOT NULL DEFAULT 'free',
    `order_id`               VARCHAR(40)  NULL DEFAULT NULL,   -- Razorpay order ID
    `payment_id`             VARCHAR(40)  NULL DEFAULT NULL,   -- Razorpay payment ID
    `status`                 VARCHAR(30)  NOT NULL DEFAULT 'pending',
    --  possible: pending, paid, free_confirmed, cancelled, cancel_requested
    `qr_url`                 VARCHAR(500) NOT NULL DEFAULT '',
    `qr_payload`             VARCHAR(500) NOT NULL DEFAULT '',
    `crm_sync_status`        VARCHAR(20)  NOT NULL DEFAULT 'Pending',
    `crm_sync_code`          VARCHAR(20)  NOT NULL DEFAULT '',
    `crm_sync_message`       VARCHAR(255) NOT NULL DEFAULT '',
    `email_status`           VARCHAR(20)  NOT NULL DEFAULT 'Pending',
    `email_sent_at`          DATETIME     NULL DEFAULT NULL,
    `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `paid_at`                DATETIME     NULL DEFAULT NULL,
    `cancel_requested_at`    DATETIME     NULL DEFAULT NULL,
    `cancelled_at`           DATETIME     NULL DEFAULT NULL,
    `refund_status`          VARCHAR(20)  NOT NULL DEFAULT '',
    `refund_id`              VARCHAR(40)  NOT NULL DEFAULT '',
    `checkin_status`         ENUM('pending','checked_in') NOT NULL DEFAULT 'pending',
    `checked_in_at`          DATETIME     NULL DEFAULT NULL,
    `verified_by`            VARCHAR(50)  NOT NULL DEFAULT '',
    `attendee_details`       JSON         NULL,  -- [{name, phone, ...}]
    `guest_passes_json`      JSON         NULL,
    `issued_by`              VARCHAR(50)  NOT NULL DEFAULT '',
    `cancel_request_by`      VARCHAR(50)  NOT NULL DEFAULT '',
    `cancel_request_reason`  VARCHAR(500) NOT NULL DEFAULT '',
    `cancel_reviewed_by`     VARCHAR(50)  NOT NULL DEFAULT '',
    `cancel_reviewed_at`     DATETIME     NULL DEFAULT NULL,
    `cancel_decision`        VARCHAR(20)  NOT NULL DEFAULT '',
    `cash_ledger_entry_id`   VARCHAR(60)  NOT NULL DEFAULT '',
    `checked_in_count`       SMALLINT     UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_transaction_id` (`transaction_id`),
    INDEX `idx_event_id`    (`event_id`),
    INDEX `idx_status`      (`status`),
    INDEX `idx_order_id`    (`order_id`),
    INDEX `idx_payment_id`  (`payment_id`),
    INDEX `idx_customer_phone` (`customer_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
