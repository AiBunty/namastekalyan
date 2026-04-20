CREATE TABLE IF NOT EXISTS `menu_category_aliases` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id`       BIGINT UNSIGNED NOT NULL,
    `alias_name`        VARCHAR(120) NOT NULL,
    `normalized_alias`  VARCHAR(150) NOT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_menu_category_aliases_category_alias` (`category_id`, `normalized_alias`),
    KEY `idx_menu_category_aliases_lookup` (`normalized_alias`),
    CONSTRAINT `fk_menu_category_aliases_category`
        FOREIGN KEY (`category_id`) REFERENCES `menu_categories` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
