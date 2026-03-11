-- ============================================================
-- Migration 003: Add real-time chat system tables
-- Run this script once against the socialweb database.
-- ============================================================

SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Table: conversations
-- Represents a private conversation between two users.
-- user1_id is always the lower user ID to ensure uniqueness.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conversations` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user1_id`          INT UNSIGNED NOT NULL,               -- lower of the two user IDs
  `user2_id`          INT UNSIGNED NOT NULL,               -- higher of the two user IDs
  `last_message_time` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_conversation` (`user1_id`, `user2_id`),
  KEY `idx_user1_id` (`user1_id`),
  KEY `idx_user2_id` (`user2_id`),
  KEY `idx_last_message` (`last_message_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: chat_messages
-- Individual messages within a conversation.
-- message_text is NULL for image-only messages.
-- image_path is NULL for text-only messages.
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` INT UNSIGNED NOT NULL,
  `sender_id`       INT UNSIGNED NOT NULL,
  `message_text`    TEXT DEFAULT NULL,                     -- NULL for image-only messages
  `image_path`      VARCHAR(500) DEFAULT NULL,             -- relative path, e.g. uploads/chat/abc.jpg
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
