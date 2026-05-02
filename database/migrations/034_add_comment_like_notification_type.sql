-- Migration 034: Add 'comment_like' notification type
--
-- The notifications table ENUM was missing 'comment_like',
-- causing notify_user() to throw a PDOException whenever someone liked
-- another user's comment. Because notify_user() skips the INSERT for
-- self-likes, liking your own comment worked fine while liking anyone
-- else's comment silently failed.

ALTER TABLE `notifications`
    MODIFY COLUMN `type`
        ENUM('like','comment','message','blog_comment','photo_like','photo_comment','blog_like','friend_request','friend_accept','mention','mention_post','comment_like')
        NOT NULL;
