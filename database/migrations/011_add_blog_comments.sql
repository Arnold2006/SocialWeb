-- Migration 011: Add blog_post_id to comments table to support blog post commenting

ALTER TABLE `comments`
  ADD COLUMN `blog_post_id` INT UNSIGNED DEFAULT NULL AFTER `media_id`;

ALTER TABLE `comments`
  ADD KEY `idx_blog_post_id` (`blog_post_id`);

-- Drop and recreate the reference check constraint to include blog_post_id.
-- On MySQL 5.7 the constraint was never enforced (and may not exist in metadata),
-- so the DROP may return error 1091 which upgrade.php handles gracefully.
ALTER TABLE `comments`
  DROP CONSTRAINT `chk_comment_ref`;

ALTER TABLE `comments`
  ADD CONSTRAINT `chk_comment_ref`
    CHECK (`post_id` IS NOT NULL OR `media_id` IS NOT NULL OR `blog_post_id` IS NOT NULL);
