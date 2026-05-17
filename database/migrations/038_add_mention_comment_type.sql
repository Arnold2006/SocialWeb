-- Migration 038: Add 'mention_comment' notification type
--
-- modules/wall/add_comment.php calls notify_mentions() with type='mention_comment'
-- but that value was absent from the notifications ENUM, causing notify_user()
-- to throw a PDOException on every wall-comment mention. The try/catch in
-- add_comment.php swallowed the exception but the notification was never stored.
-- The renderer in pages/notifications.php already handles this type.

ALTER TABLE `notifications`
    MODIFY COLUMN `type`
        ENUM('like','comment','message','blog_comment','photo_like','photo_comment',
             'blog_like','friend_request','friend_accept','mention','mention_post',
             'comment_like','mention_comment')
        NOT NULL;
