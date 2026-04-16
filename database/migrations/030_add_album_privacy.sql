-- Per-album privacy setting
-- Migration 030

ALTER TABLE `albums`
  ADD COLUMN `privacy` ENUM('everybody','members','friends_only','only_me') NOT NULL DEFAULT 'members'
  AFTER `description`;
