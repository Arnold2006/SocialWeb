-- Migration 040: Add mail_message, mention_comment_blog, mention_comment_photo types
--
-- mail_message  — private-mail notification (replaces the dual-use 'message' type
--                 which was shared by chat and mail, causing the notification bell
--                 to show chat activity).  New private-mail sends use 'mail_message';
--                 existing 'message' rows are left as-is for backward compat.
--
-- mention_comment_blog  — @-mention inside a blog comment (ref_id = blog_post_id).
--                         Previously these were stored as generic 'mention' with a
--                         blog_post_id ref_id, causing the renderer to link to the
--                         wall-post feed instead of the blog page.
--
-- mention_comment_photo — @-mention inside a photo/gallery comment (ref_id = media_id).
--                         Same root cause as mention_comment_blog above.

ALTER TABLE `notifications`
    MODIFY COLUMN `type`
        ENUM('like','comment','message','mail_message','blog_comment','photo_like',
             'photo_comment','blog_like','friend_request','friend_accept','mention',
             'mention_post','comment_like','mention_comment','mention_comment_blog',
             'mention_comment_photo')
        NOT NULL;
