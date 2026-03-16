-- ============================================================
-- Migration 016: Add album categories
-- Introduces a "Category" level between the gallery and albums:
--   Gallery → Category → Album
-- Existing albums are automatically placed in a "Main" category.
-- ============================================================

SET NAMES utf8mb4;

-- Create the album_categories table
CREATE TABLE IF NOT EXISTS `album_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add category_id column to albums
ALTER TABLE `albums`
  ADD COLUMN `category_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`;

-- Create a "Main" category for every user who already has albums,
-- then assign all their existing albums to that category.
INSERT INTO `album_categories` (`user_id`, `title`)
  SELECT DISTINCT `user_id`, 'Main'
  FROM `albums`
  WHERE `is_deleted` = 0;

UPDATE `albums` a
  JOIN `album_categories` c ON c.`user_id` = a.`user_id` AND c.`title` = 'Main'
SET a.`category_id` = c.`id`
WHERE a.`is_deleted` = 0;
