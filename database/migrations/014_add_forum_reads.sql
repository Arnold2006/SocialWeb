-- Migration 014: Forum read tracking
-- Tracks when a user last read a forum thread (for unread indicators).
-- A thread is "unread" when last_post_at > read_at, or when no row exists.

CREATE TABLE IF NOT EXISTS `forum_reads` (
  `user_id`   INT UNSIGNED NOT NULL,
  `thread_id` INT UNSIGNED NOT NULL,
  `read_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `thread_id`),
  KEY `idx_fr_thread_id` (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
