-- Migration 017: Menu item scheduling + edit tracking
-- schedule_from / schedule_until: TIME windows (null = all day)
-- schedule_days: JSON array of day numbers 0=Sun..6=Sat, null = every day
-- manually_edited: set to 1 when an admin edits a row in the grid editor

ALTER TABLE `menu_items`
    ADD COLUMN `manually_edited`  TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Set to 1 when admin edits this row manually; prevents next import from overwriting'
        AFTER `sort_order`,
    ADD COLUMN `schedule_enabled` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'When 1, availability is controlled by schedule window'
        AFTER `manually_edited`,
    ADD COLUMN `schedule_from`    TIME       NULL
        COMMENT 'Start of availability window e.g. 11:00:00'
        AFTER `schedule_enabled`,
    ADD COLUMN `schedule_until`   TIME       NULL
        COMMENT 'End of availability window e.g. 23:00:00'
        AFTER `schedule_from`,
    ADD COLUMN `schedule_days`    JSON       NULL
        COMMENT 'Days of week item is available e.g. [1,2,3,4,5] null=every day'
        AFTER `schedule_until`;
