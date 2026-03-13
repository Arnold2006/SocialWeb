-- Migration 008: Add bumped_at column to posts for bump/feed-ordering feature.
-- When a post receives a new comment or like it is "bumped" to the top of the feed.

ALTER TABLE `posts`
  ADD COLUMN `bumped_at` DATETIME DEFAULT NULL AFTER `updated_at`,
  ADD KEY `idx_bumped_at` (`bumped_at`);
