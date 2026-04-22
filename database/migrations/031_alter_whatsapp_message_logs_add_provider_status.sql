-- Migration 031: Add provider message ids and delivery status tracking for WhatsApp logs.

ALTER TABLE `whatsapp_message_logs`
    ADD COLUMN `provider_message_id` VARCHAR(120) NULL DEFAULT NULL AFTER `language_code`,
    ADD COLUMN `delivery_status` VARCHAR(40) NOT NULL DEFAULT '' AFTER `provider_message_id`,
    ADD COLUMN `status_updated_at` DATETIME NULL DEFAULT NULL AFTER `delivery_status`;

ALTER TABLE `whatsapp_message_logs`
    ADD KEY `idx_whatsapp_logs_provider_message_id` (`provider_message_id`),
    ADD KEY `idx_whatsapp_logs_delivery_status` (`delivery_status`);