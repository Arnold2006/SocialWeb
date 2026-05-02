-- Migration 033: Add unique constraint for comment likes
--
-- The likes table already has comment_id column and index from the initial schema,
-- but lacked a unique key to prevent duplicate comment likes.

ALTER TABLE `likes`
    ADD CONSTRAINT `unique_comment_like` UNIQUE KEY (`user_id`, `comment_id`);
