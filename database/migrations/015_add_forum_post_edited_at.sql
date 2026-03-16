-- Migration 015: Add edited_at column to forum_posts
-- Tracks when a post was last edited so users can see "(edited)" indicators.

ALTER TABLE `forum_posts`
    ADD COLUMN `edited_at` DATETIME DEFAULT NULL AFTER `created_at`;
