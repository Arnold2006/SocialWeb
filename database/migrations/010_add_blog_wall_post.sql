-- Migration 010: Add blog_post type to wall posts
-- When a user publishes a new blog post a system wall post of type 'blog_post'
-- is created so it appears in the activity feed.

ALTER TABLE `posts`
  MODIFY COLUMN `post_type` ENUM('user','album_upload','blog_post') NOT NULL DEFAULT 'user';

ALTER TABLE `posts`
  ADD COLUMN `blog_post_id` INT UNSIGNED DEFAULT NULL AFTER `album_id`;
