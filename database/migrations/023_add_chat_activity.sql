-- Track when a user was last active (polling messages) in a specific conversation.
-- Used to suppress chat notifications when the recipient already has the chat open.
CREATE TABLE `chat_activity` (
    `user_id`         INT UNSIGNED NOT NULL,
    `conversation_id` INT UNSIGNED NOT NULL,
    `last_active_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
