-- Migration 009: QR scan tracking (public menu QR code scans)
-- Maps from: QR Scans sheet

CREATE TABLE IF NOT EXISTS `qr_scans` (
    `id`           BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `scanned_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `user_agent`   VARCHAR(500) NOT NULL DEFAULT '',
    `referer`      VARCHAR(500) NOT NULL DEFAULT '',
    `ip_address`   VARCHAR(64)  NOT NULL DEFAULT '',
    `scan_number`  INT          UNSIGNED NOT NULL DEFAULT 0,  -- cumulative counter
    `city`         VARCHAR(100) NOT NULL DEFAULT '',
    `region`       VARCHAR(100) NOT NULL DEFAULT '',
    `country`      VARCHAR(50)  NOT NULL DEFAULT '',
    `device`       VARCHAR(100) NOT NULL DEFAULT '',
    `browser`      VARCHAR(100) NOT NULL DEFAULT '',
    `os`           VARCHAR(100) NOT NULL DEFAULT '',
    `language`     VARCHAR(20)  NOT NULL DEFAULT '',
    `screen`       VARCHAR(50)  NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    INDEX `idx_scanned_at` (`scanned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
