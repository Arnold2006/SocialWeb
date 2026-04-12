-- Migration 026: Add updated_at column to comments for edit tracking
ALTER TABLE `comments`
    ADD COLUMN `updated_at` DATETIME DEFAULT NULL AFTER `created_at`;
