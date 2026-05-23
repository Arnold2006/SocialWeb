-- ============================================================
-- Migration 044: Add description to file_share_files
--
-- Adds an optional description column so uploaders can provide
-- context about the files they share.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `file_share_files`
    ADD COLUMN `description` TEXT DEFAULT NULL
        AFTER `original_name`;
