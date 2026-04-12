-- ============================================================
-- Migration 025: Assign uncategorised albums to "Main" category
--
-- Albums created by the wall-post image uploader (and any other
-- code path) land with category_id = NULL.  This migration ensures
-- every such album is placed in the owner's "Main" category,
-- creating that category first for any user who does not yet have
-- one.
-- ============================================================

SET NAMES utf8mb4;

-- Step 1: Create a "Main" category for every user who has at least
--         one album with category_id IS NULL but does not yet have
--         a "Main" category.
INSERT INTO `album_categories` (`user_id`, `title`)
  SELECT DISTINCT a.`user_id`, 'Main'
  FROM `albums` a
  WHERE a.`category_id` IS NULL
    AND a.`is_deleted` = 0
    AND NOT EXISTS (
      SELECT 1
      FROM `album_categories` c
      WHERE c.`user_id` = a.`user_id`
        AND c.`title`   = 'Main'
        AND c.`is_deleted` = 0
    );

-- Step 2: Assign all NULL-category albums to the owner's "Main" category.
UPDATE `albums` a
  JOIN `album_categories` c
    ON  c.`user_id`    = a.`user_id`
    AND c.`title`      = 'Main'
    AND c.`is_deleted` = 0
SET a.`category_id` = c.`id`
WHERE a.`category_id` IS NULL
  AND a.`is_deleted`  = 0;
