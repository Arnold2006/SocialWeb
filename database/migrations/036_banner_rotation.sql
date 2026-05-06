-- Migration 036: Banner image rotation settings
-- Adds enable/disable toggle and rotation interval (in days) for the banner library

INSERT IGNORE INTO `site_settings` (`key`, `value`) VALUES
  ('banner_rotation_enabled', '0'),
  ('banner_rotation_days',    '7');
