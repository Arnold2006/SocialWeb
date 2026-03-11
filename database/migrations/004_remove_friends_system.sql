-- ============================================================
-- Migration 004: Remove friends system
-- Run this script once against the socialweb database.
-- ============================================================

SET NAMES utf8mb4;

-- Remove any existing friend_request notifications
DELETE FROM `notifications` WHERE `type` = 'friend_request';

-- Update the notifications ENUM to remove friend_request
ALTER TABLE `notifications`
  MODIFY COLUMN `type` ENUM('like','comment','message') NOT NULL;

-- Drop the friends table
DROP TABLE IF EXISTS `friends`;
