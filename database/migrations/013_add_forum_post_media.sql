-- Migration 013: Add media_id to forum_posts
-- Allows forum posts to attach a gallery image as a thumbnail.

ALTER TABLE `forum_posts`
  ADD COLUMN `media_id` INT UNSIGNED DEFAULT NULL AFTER `content`,
  ADD KEY `idx_forum_post_media_id` (`media_id`);
