-- Migration 022: Add blog post likes support

-- Allow likes to reference blog posts
ALTER TABLE `likes`
  ADD COLUMN `blog_post_id` INT UNSIGNED DEFAULT NULL AFTER `media_id`,
  ADD UNIQUE KEY `unique_blog_post_like` (`user_id`, `blog_post_id`),
  ADD KEY `idx_blog_post_id_likes` (`blog_post_id`);

-- Add blog_like notification type
ALTER TABLE `notifications`
  MODIFY COLUMN `type` ENUM('like','comment','message','blog_comment','photo_like','photo_comment','blog_like') NOT NULL;
