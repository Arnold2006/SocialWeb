-- ============================================================
-- Migration 005: Add album upload system posts
-- Run this script once against the socialweb database.
-- ============================================================

SET NAMES utf8mb4;

-- Add post_type to distinguish user posts from system-generated album upload posts.
-- Add album_id so album-upload posts can link back to the source album.
ALTER TABLE `posts`
  ADD COLUMN `post_type` ENUM('user','album_upload') NOT NULL DEFAULT 'user' AFTER `is_deleted`,
  ADD COLUMN `album_id`  INT UNSIGNED DEFAULT NULL AFTER `post_type`;
