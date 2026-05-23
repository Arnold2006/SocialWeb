-- ============================================================
-- Migration 043: Add file share tables
--
-- Introduces a community file-sharing system where members can
-- upload files (zip, rar, 7z, safetensors, json, png, pth) and
-- admins can organise them into folders.
--
-- Tables:
--   file_share_folders – folders created by admins to organise files
--   file_share_files   – uploaded files with optional folder assignment
-- ============================================================

SET NAMES utf8mb4;

-- ── Folders ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `file_share_folders` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Files ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `file_share_files` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder_id`     INT UNSIGNED DEFAULT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `filename`      VARCHAR(255) NOT NULL,        -- stored filename (random hex)
  `original_name` VARCHAR(255) NOT NULL,        -- original filename shown to users
  `size`          BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_folder_id` (`folder_id`),
  KEY `idx_user_id`   (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
