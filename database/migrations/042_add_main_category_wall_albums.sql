-- ============================================================
-- Migration 042: Add "Main" category and Wall albums for existing users
--
-- New users receive a "Main" category with "Wall Images" and
-- "Wall Videos" albums automatically on registration (core/auth.php).
-- This migration back-fills that structure for any user who was
-- created before that logic existed or whose setup failed.
--
-- Steps:
--   1. Create a "Main" category for every user who has none.
--   2. Create a "Wall Images" album in "Main" for users who lack it.
--   3. Create a "Wall Videos" album in "Main" for users who lack it.
-- ============================================================

SET NAMES utf8mb4;

-- Step 1: Create a "Main" category for every user in the users table
--         who does not already have one.
INSERT INTO `album_categories` (`user_id`, `title`)
  SELECT u.`id`, 'Main'
  FROM `users` u
  WHERE NOT EXISTS (
      SELECT 1
      FROM `album_categories` c
      WHERE c.`user_id`    = u.`id`
        AND c.`title`      = 'Main'
        AND c.`is_deleted` = 0
    );

-- Step 2: Create a "Wall Images" album inside the "Main" category for
--         every user who does not already have one.
INSERT INTO `albums` (`user_id`, `category_id`, `title`)
  SELECT c.`user_id`, c.`id`, 'Wall Images'
  FROM `album_categories` c
  WHERE c.`title`      = 'Main'
    AND c.`is_deleted` = 0
    AND NOT EXISTS (
      SELECT 1
      FROM `albums` a
      WHERE a.`user_id`    = c.`user_id`
        AND a.`category_id` = c.`id`
        AND a.`title`      = 'Wall Images'
        AND a.`is_deleted` = 0
    );

-- Step 3: Create a "Wall Videos" album inside the "Main" category for
--         every user who does not already have one.
INSERT INTO `albums` (`user_id`, `category_id`, `title`)
  SELECT c.`user_id`, c.`id`, 'Wall Videos'
  FROM `album_categories` c
  WHERE c.`title`      = 'Main'
    AND c.`is_deleted` = 0
    AND NOT EXISTS (
      SELECT 1
      FROM `albums` a
      WHERE a.`user_id`    = c.`user_id`
        AND a.`category_id` = c.`id`
        AND a.`title`      = 'Wall Videos'
        AND a.`is_deleted` = 0
    );
