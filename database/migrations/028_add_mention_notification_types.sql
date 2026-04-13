-- Migration 028: Add 'mention' and 'mention_post' notification types
--
-- The notifications table ENUM was missing 'mention' and 'mention_post',
-- causing notify_user() to throw a PDOException whenever notify_mentions()
-- was called for wall or blog comments containing @usernames.
-- This exception was uncaught, preventing the JSON response from being sent
-- and leaving the comment input box un-cleared in the browser.

ALTER TABLE `notifications`
    MODIFY COLUMN `type`
        ENUM('like','comment','message','blog_comment','photo_like','photo_comment','blog_like','mention','mention_post')
        NOT NULL;
