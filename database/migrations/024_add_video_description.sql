-- Add description column to media table for video captions.
ALTER TABLE `media`
    ADD COLUMN `description` VARCHAR(500) DEFAULT NULL AFTER `original_name`;
