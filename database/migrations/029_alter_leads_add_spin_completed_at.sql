ALTER TABLE `leads`
    ADD COLUMN `spin_completed_at` DATETIME NULL DEFAULT NULL AFTER `created_at`,
    ADD INDEX `idx_spin_completed_at` (`spin_completed_at`);