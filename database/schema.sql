-- SocialWeb Database Schema
-- PHP 8.3 / MySQL / MariaDB
-- Invite-only social network platform

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(50)  NOT NULL UNIQUE,
  `email`        VARCHAR(255) NOT NULL UNIQUE,
  `password`     VARCHAR(255) NOT NULL,               -- password_hash()
  `role`         ENUM('user','admin') NOT NULL DEFAULT 'user',
  `avatar_path`  VARCHAR(500) DEFAULT NULL,
  `bio`          TEXT DEFAULT NULL,
  `is_banned`    TINYINT(1) NOT NULL DEFAULT 0,
  `invite_id`    INT UNSIGNED DEFAULT NULL,           -- invite used to register
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login`   DATETIME DEFAULT NULL,
  `last_seen`    DATETIME DEFAULT NULL,               -- updated on every page load
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: invites
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invites` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(64)  NOT NULL UNIQUE,
  `created_by`  INT UNSIGNED NOT NULL,
  `max_uses`    INT UNSIGNED NOT NULL DEFAULT 1,
  `uses`        INT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`  DATETIME DEFAULT NULL,
  `is_disabled` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: sessions (server-side session tracking)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id`         VARCHAR(128) NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `ip_address` VARCHAR(45)  NOT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: posts (wall posts)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `posts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `content`    TEXT NOT NULL,
  `media_id`   INT UNSIGNED DEFAULT NULL,             -- optional attached media
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `bumped_at`  DATETIME DEFAULT NULL,                 -- updated on new comment or like for feed ordering
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `post_type`  ENUM('user','album_upload') NOT NULL DEFAULT 'user',
  `album_id`   INT UNSIGNED DEFAULT NULL,             -- set for album_upload system posts
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_bumped_at` (`bumped_at`),
  KEY `idx_is_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: comments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`    INT UNSIGNED DEFAULT NULL,             -- wall post (nullable; set for post comments)
  `media_id`   INT UNSIGNED DEFAULT NULL,             -- album media item (nullable; set for media comments)
  `user_id`    INT UNSIGNED NOT NULL,
  `content`    TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_media_id` (`media_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `chk_comment_ref` CHECK (`post_id` IS NOT NULL OR `media_id` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: likes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `likes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `post_id`    INT UNSIGNED DEFAULT NULL,
  `media_id`   INT UNSIGNED DEFAULT NULL,             -- album media item like
  `comment_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_like` (`user_id`, `post_id`),
  UNIQUE KEY `unique_media_like` (`user_id`, `media_id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_media_id_likes` (`media_id`),
  KEY `idx_comment_id` (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: messages (private messaging / mail system)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id`   INT UNSIGNED NOT NULL,
  `receiver_id` INT UNSIGNED NOT NULL,
  `subject`     VARCHAR(255) NOT NULL DEFAULT '(no subject)',
  `content`     TEXT NOT NULL,
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted_sender`   TINYINT(1) NOT NULL DEFAULT 0,
  `is_deleted_receiver` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_receiver_id` (`receiver_id`),
  KEY `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: notifications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,               -- recipient
  `type`        ENUM('like','comment','message') NOT NULL,
  `from_user_id` INT UNSIGNED DEFAULT NULL,          -- who triggered it
  `ref_id`      INT UNSIGNED DEFAULT NULL,           -- post/comment/message id
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: shoutbox
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shoutbox` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `message`    VARCHAR(500) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_is_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: albums
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `albums` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `title`       VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `cover_id`    INT UNSIGNED DEFAULT NULL,            -- media cover image id
  `cover_path`  VARCHAR(500) DEFAULT NULL,             -- cropped cover image URL path
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted`  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: media
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `media` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `album_id`         INT UNSIGNED DEFAULT NULL,
  `type`             ENUM('image','video') NOT NULL,
  `file_hash`        VARCHAR(64)  NOT NULL,            -- SHA256 hash for deduplication
  `storage_path`     VARCHAR(500) NOT NULL,            -- path to original
  `large_path`       VARCHAR(500) DEFAULT NULL,
  `medium_path`      VARCHAR(500) DEFAULT NULL,
  `thumb_path`       VARCHAR(500) DEFAULT NULL,
  `thumbnail_path`   VARCHAR(500) DEFAULT NULL,        -- video thumbnail
  `size`             BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `mime_type`        VARCHAR(100) NOT NULL,
  `original_name`    VARCHAR(255) DEFAULT NULL,
  `width`            INT UNSIGNED DEFAULT NULL,
  `height`           INT UNSIGNED DEFAULT NULL,
  `duration`         INT UNSIGNED DEFAULT NULL,        -- video duration in seconds
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_deleted`       TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_album_id` (`album_id`),
  KEY `idx_file_hash` (`file_hash`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: plugins
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plugins` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL UNIQUE,
  `slug`       VARCHAR(100) NOT NULL UNIQUE,
  `version`    VARCHAR(20)  NOT NULL DEFAULT '1.0.0',
  `is_enabled` TINYINT(1)   NOT NULL DEFAULT 1,
  `settings`   JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_is_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: site_settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`   VARCHAR(100) NOT NULL UNIQUE,
  `value` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Default site settings
-- --------------------------------------------------------
INSERT IGNORE INTO `site_settings` (`key`, `value`) VALUES
  ('site_name', 'SocialWeb'),
  ('site_description', 'An invite-only social network'),
  ('allow_registration', '1'),
  ('posts_per_page', '20'),
  ('max_upload_size', '10485760'),
  ('max_video_duration', '300'),
  ('cache_ttl', '30'),
  ('banner_overlay_x',    '50'),
  ('banner_overlay_y',    '50'),
  ('banner_overlay_size', '2.4'),
  ('site_theme',          'blue-red');

-- --------------------------------------------------------
-- Table: site_fonts (custom font uploads)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_fonts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `filename`    VARCHAR(255) NOT NULL,
  `format`      ENUM('woff2','woff','ttf','otf') NOT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: conversations (real-time chat)
-- user1_id is always the smaller user ID for uniqueness.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conversations` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user1_id`          INT UNSIGNED NOT NULL,
  `user2_id`          INT UNSIGNED NOT NULL,
  `last_message_time` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_conversation` (`user1_id`, `user2_id`),
  KEY `idx_user1_id` (`user1_id`),
  KEY `idx_user2_id` (`user2_id`),
  KEY `idx_last_message` (`last_message_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: chat_messages (real-time chat messages)
-- message_text is NULL for image-only messages.
-- image_path is NULL for text-only messages.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` INT UNSIGNED NOT NULL,
  `sender_id`       INT UNSIGNED NOT NULL,
  `message_text`    TEXT DEFAULT NULL,
  `image_path`      VARCHAR(500) DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read`         TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_is_read` (`is_read`),
  CONSTRAINT `chk_chat_message_content`
    CHECK (`message_text` IS NOT NULL OR `image_path` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
