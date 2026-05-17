-- Migration 039: Add secondary_ref_id column to notifications
--
-- Allows notification inserts to carry two contextual IDs without overloading
-- ref_id (e.g. ref_id = parent post/blog-post/media ID, secondary_ref_id = comment ID).
-- Existing rows receive NULL (no behaviour change).
-- New insert sites (blog_comment, comment, mention_comment) will populate this
-- field so renderers can link directly to the parent object without a JOIN.

ALTER TABLE `notifications`
    ADD COLUMN `secondary_ref_id` INT UNSIGNED DEFAULT NULL
        AFTER `ref_id`;
