-- Migration 027: CRM contacts and CRM push logs

CREATE TABLE IF NOT EXISTS `crm_contacts` (
    `id`                       BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `created_at`               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `phone`                    VARCHAR(15)  NOT NULL,
    `name`                     VARCHAR(150) NOT NULL DEFAULT '',
    `date_of_birth`            DATE         NULL DEFAULT NULL,
    `date_of_anniversary`      DATE         NULL DEFAULT NULL,
    `first_seen_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `latest_source`            VARCHAR(60)  NOT NULL DEFAULT 'menu-blocker-web',
    `latest_lead_id`           BIGINT       UNSIGNED NULL DEFAULT NULL,
    `latest_lead_created_at`   DATETIME     NULL DEFAULT NULL,
    `total_submissions`        INT          UNSIGNED NOT NULL DEFAULT 1,
    `latest_crm_sync_status`   ENUM('Pending','Success','Failed','Skipped') NOT NULL DEFAULT 'Pending',
    `latest_crm_sync_code`     VARCHAR(20)  NOT NULL DEFAULT '',
    `latest_crm_sync_message`  VARCHAR(500) NOT NULL DEFAULT '',
    `last_crm_attempted_at`    DATETIME     NULL DEFAULT NULL,
    `last_crm_pushed_at`       DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_crm_contacts_phone` (`phone`),
    INDEX `idx_latest_source` (`latest_source`),
    INDEX `idx_latest_crm_sync_status` (`latest_crm_sync_status`),
    INDEX `idx_last_seen_at` (`last_seen_at`),
    INDEX `idx_latest_lead_id` (`latest_lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_push_logs` (
    `id`                 BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `contact_id`         BIGINT       UNSIGNED NULL DEFAULT NULL,
    `lead_id`            BIGINT       UNSIGNED NULL DEFAULT NULL,
    `phone`              VARCHAR(15)  NOT NULL DEFAULT '',
    `contact_name`       VARCHAR(150) NOT NULL DEFAULT '',
    `trigger_source`     VARCHAR(60)  NOT NULL DEFAULT 'menu-blocker-web',
    `crm_endpoint`       VARCHAR(255) NOT NULL DEFAULT '',
    `attempted`          TINYINT(1)   NOT NULL DEFAULT 0,
    `success`            TINYINT(1)   NOT NULL DEFAULT 0,
    `http_code`          VARCHAR(20)  NOT NULL DEFAULT '',
    `retry_count`        SMALLINT     UNSIGNED NOT NULL DEFAULT 0,
    `attempt_count`      SMALLINT     UNSIGNED NOT NULL DEFAULT 0,
    `response_message`   VARCHAR(500) NOT NULL DEFAULT '',
    `request_payload_json` MEDIUMTEXT NULL,
    `attempts_json`      MEDIUMTEXT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_contact_id` (`contact_id`),
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_phone` (`phone`),
    INDEX `idx_success` (`success`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_crm_push_logs_contact`
        FOREIGN KEY (`contact_id`) REFERENCES `crm_contacts` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_crm_push_logs_lead`
        FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;