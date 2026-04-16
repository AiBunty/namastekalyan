-- Migration 002: Auth audit log
-- Maps from: AuthAudit sheet
-- Columns: Timestamp, Action, Username, Outcome, Source, Details

CREATE TABLE IF NOT EXISTS `auth_audit` (
    `id`         BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `logged_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `action`     VARCHAR(60)  NOT NULL,
    `username`   VARCHAR(50)  NOT NULL DEFAULT '',
    `outcome`    VARCHAR(30)  NOT NULL,
    `source`     VARCHAR(30)  NOT NULL DEFAULT 'web',
    `details`    VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    INDEX `idx_username`  (`username`),
    INDEX `idx_logged_at` (`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
