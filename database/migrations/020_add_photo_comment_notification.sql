-- Migration 020: Add photo_comment notification type for media/photo comments

ALTER TABLE `notifications`
  MODIFY COLUMN `type` ENUM('like','comment','message','blog_comment','photo_like','photo_comment') NOT NULL;
