-- Migration 003: Revoked JWT tokens
-- Maps from: AuthRevokedTokens sheet
-- Columns: Token Hash, Revoked At, Expires At, Username
-- Rows auto-purged after expires_at (via scheduled cleanup or query condition).

CREATE TABLE IF NOT EXISTS `revoked_tokens` (
    `id`          INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    `token_hash`  CHAR(64)     NOT NULL,    -- SHA-256 hex of the raw JWT
    `revoked_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`  DATETIME     NOT NULL,
    `username`    VARCHAR(50)  NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token_hash` (`token_hash`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
