-- Migration 019: Store up to 4 preview image IDs on album_upload wall posts

ALTER TABLE `posts`
  ADD COLUMN `media_ids` TEXT DEFAULT NULL COMMENT 'JSON array of up to 4 media IDs for album_upload preview thumbnails' AFTER `album_id`;
