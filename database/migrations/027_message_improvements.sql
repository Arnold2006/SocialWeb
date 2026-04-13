-- Migration 027: Threaded conversations, drafts, and image attachments for private messages

-- Allow NULL receiver_id so messages can be saved as drafts before a recipient is chosen
ALTER TABLE `messages`
    MODIFY COLUMN `receiver_id` INT UNSIGNED DEFAULT NULL;

-- thread_id points to the root message (id) of a reply chain; NULL = root / standalone
ALTER TABLE `messages`
    ADD COLUMN `thread_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
    ADD KEY    `idx_messages_thread_id` (`thread_id`);

-- is_draft: 1 = draft (only visible to sender), 0 = sent
ALTER TABLE `messages`
    ADD COLUMN `is_draft` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_read`,
    ADD KEY    `idx_messages_is_draft` (`is_draft`);

-- Image / file attachments linked to messages
CREATE TABLE IF NOT EXISTS `message_attachments` (
  `id`            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `message_id`    INT UNSIGNED            DEFAULT NULL,   -- NULL until message is sent
  `sender_id`     INT UNSIGNED   NOT NULL,                -- used for orphan cleanup
  `file_path`     VARCHAR(500)   NOT NULL,
  `original_name` VARCHAR(255)   NOT NULL DEFAULT 'attachment',
  `mime_type`     VARCHAR(100)   NOT NULL,
  `file_size`     INT UNSIGNED   NOT NULL DEFAULT 0,
  `created_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msgatt_message_id` (`message_id`),
  KEY `idx_msgatt_sender_id`  (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
