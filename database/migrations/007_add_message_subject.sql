-- Migration 007: add subject column to messages table
ALTER TABLE `messages`
    ADD COLUMN `subject` VARCHAR(255) NOT NULL DEFAULT '(no subject)'
    AFTER `receiver_id`;
