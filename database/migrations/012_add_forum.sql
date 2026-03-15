-- Forum module tables
-- Adds: forum_categories, forum_forums, forum_threads, forum_posts

SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Table: forum_categories
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: forum_forums
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_forums` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `title`       VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: forum_threads
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_threads` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `forum_id`     INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NOT NULL,
  `title`        VARCHAR(200) NOT NULL,
  `is_locked`    TINYINT(1) NOT NULL DEFAULT 0,
  `is_deleted`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_post_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reply_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_forum_id`     (`forum_id`),
  KEY `idx_user_id`      (`user_id`),
  KEY `idx_last_post_at` (`last_post_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: forum_posts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_posts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `thread_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `content`    TEXT NOT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_thread_id` (`thread_id`),
  KEY `idx_user_id`   (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
