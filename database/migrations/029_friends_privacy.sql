-- Friends & Privacy system
-- Migration 029

-- Friendships table
CREATE TABLE IF NOT EXISTS `friendships` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `requester_id` INT UNSIGNED NOT NULL,
  `addressee_id` INT UNSIGNED NOT NULL,
  `status`       ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pair` (`requester_id`, `addressee_id`),
  KEY `idx_addressee` (`addressee_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user privacy settings
CREATE TABLE IF NOT EXISTS `user_privacy_settings` (
  `user_id`    INT UNSIGNED NOT NULL,
  `action_key` VARCHAR(64) NOT NULL,
  `value`      ENUM('everybody','members','friends_only','only_me') NOT NULL DEFAULT 'members',
  PRIMARY KEY (`user_id`, `action_key`),
  KEY `idx_action_key` (`action_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extend notifications type ENUM to include friend events
ALTER TABLE `notifications`
  MODIFY COLUMN `type`
  ENUM('like','comment','message','blog_comment','photo_like','photo_comment','blog_like','friend_request','friend_accept','mention','mention_post')
  NOT NULL;
