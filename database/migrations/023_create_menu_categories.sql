CREATE TABLE IF NOT EXISTS `menu_categories` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `menu_type`     ENUM('food','bar') NOT NULL,
    `canonical_name` VARCHAR(120) NOT NULL,
    `display_name`  VARCHAR(120) NOT NULL,
    `slug`          VARCHAR(150) NOT NULL,
    `sort_order`    INT NOT NULL DEFAULT 0,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_menu_categories_menu_slug` (`menu_type`, `slug`),
    UNIQUE KEY `uq_menu_categories_menu_canonical` (`menu_type`, `canonical_name`),
    KEY `idx_menu_categories_sort` (`menu_type`, `sort_order`, `display_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
