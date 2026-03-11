-- Migration: add site_theme setting
INSERT IGNORE INTO `site_settings` (`key`, `value`) VALUES ('site_theme', 'blue-red');
