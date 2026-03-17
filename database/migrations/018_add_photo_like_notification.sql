-- Migration 018: Add photo_like notification type for media/photo likes

ALTER TABLE `notifications`
  MODIFY COLUMN `type` ENUM('like','comment','message','blog_comment','photo_like') NOT NULL;
