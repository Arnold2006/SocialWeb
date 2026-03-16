-- Migration 017: Add blog_comment notification type for blog post comments

ALTER TABLE `notifications`
  MODIFY COLUMN `type` ENUM('like','comment','message','blog_comment') NOT NULL;
