-- Migration 032: Add image attachment support to comments
--
-- Allows comments to optionally reference a media (image) record.
-- The image is stored in the commenter's "Wall Images" album.

ALTER TABLE `comments`
    ADD COLUMN `image_media_id` INT UNSIGNED DEFAULT NULL AFTER `blog_post_id`,
    ADD KEY `idx_comment_image_media` (`image_media_id`);
