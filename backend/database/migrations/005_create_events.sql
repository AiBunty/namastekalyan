-- Migration 005: Events
-- Maps from: EVENTS sheet
-- Columns: Event ID, Title, Subtitle, Description, Image URL, Video URL,
--          Show Video, CTA Text, CTA URL, Badge Text, Start Date, Start Time,
--          End Date, End Time, Time Display Format, Is Active, Priority,
--          Popup Enabled, Show Once Per Session, Popup Delay Hours,
--          Popup Cooldown Hours, Event Type, Ticket Price, Currency,
--          Max Tickets, Payment Enabled, Cancellation Policy Text, Refund Policy

CREATE TABLE IF NOT EXISTS `events` (
    `id`                     INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id`               VARCHAR(50)  NOT NULL,   -- e.g. "EVT-2026-001"
    `title`                  VARCHAR(200) NOT NULL,
    `subtitle`               VARCHAR(300) NOT NULL DEFAULT '',
    `description`            TEXT         NOT NULL,
    `image_url`              VARCHAR(500) NOT NULL DEFAULT '',
    `video_url`              VARCHAR(500) NOT NULL DEFAULT '',
    `show_video`             TINYINT(1)   NOT NULL DEFAULT 0,
    `cta_text`               VARCHAR(100) NOT NULL DEFAULT '',
    `cta_url`                VARCHAR(500) NOT NULL DEFAULT '',
    `badge_text`             VARCHAR(60)  NOT NULL DEFAULT '',
    `start_date`             DATE         NULL DEFAULT NULL,
    `start_time`             TIME         NULL DEFAULT NULL,
    `end_date`               DATE         NULL DEFAULT NULL,
    `end_time`               TIME         NULL DEFAULT NULL,
    `time_display_format`    ENUM('12h','24h') NOT NULL DEFAULT '12h',
    `is_active`              TINYINT(1)   NOT NULL DEFAULT 1,
    `priority`               SMALLINT     NOT NULL DEFAULT 0,
    `popup_enabled`          TINYINT(1)   NOT NULL DEFAULT 0,
    `show_once_per_session`  TINYINT(1)   NOT NULL DEFAULT 0,
    `popup_delay_hours`      DECIMAL(5,2) NOT NULL DEFAULT 0,
    `popup_cooldown_hours`   DECIMAL(5,2) NOT NULL DEFAULT 24,
    `event_type`             ENUM('free','paid') NOT NULL DEFAULT 'free',
    `ticket_price`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency`               VARCHAR(5)   NOT NULL DEFAULT 'INR',
    `max_tickets`            INT          UNSIGNED NOT NULL DEFAULT 0,  -- 0 = unlimited
    `payment_enabled`        TINYINT(1)   NOT NULL DEFAULT 0,
    `cancellation_policy`    TEXT         NOT NULL,
    `refund_policy`          TEXT         NOT NULL,
    `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_id`  (`event_id`),
    INDEX     `idx_is_active` (`is_active`),
    INDEX     `idx_start_date`(`start_date`),
    INDEX     `idx_priority`  (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
