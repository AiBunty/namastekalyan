-- Migration 001: Admin users table
-- Maps from: Users sheet
-- Columns: Username, Display Name, Role, Password Hash, Password Salt,
--          Status, Force Password Change, Failed Attempts, Lockout Until,
--          Last Login At, Last Login IP, Created At, Created By, Updated At,
--          Updated By, Permissions

CREATE TABLE IF NOT EXISTS `users` (
    `id`                    INT           UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`              VARCHAR(50)   NOT NULL,
    `display_name`          VARCHAR(100)  NOT NULL DEFAULT '',
    `role`                  ENUM('admin','superadmin') NOT NULL DEFAULT 'admin',
    `password_hash`         VARCHAR(130)  NOT NULL,
    `password_salt`         VARCHAR(64)   NOT NULL,
    `status`                ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `force_password_change` TINYINT(1)    NOT NULL DEFAULT 0,
    `failed_attempts`       TINYINT       UNSIGNED NOT NULL DEFAULT 0,
    `lockout_until`         DATETIME      NULL DEFAULT NULL,
    `last_login_at`         DATETIME      NULL DEFAULT NULL,
    `last_login_ip`         VARCHAR(64)   NULL DEFAULT NULL,
    `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by`            VARCHAR(50)   NULL DEFAULT NULL,
    `updated_at`            DATETIME      NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`            VARCHAR(50)   NULL DEFAULT NULL,
    -- JSON object: {"dashboard":true,"cashier":false,...}
    `permissions`           JSON          NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
