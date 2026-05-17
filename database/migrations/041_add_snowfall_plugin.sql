-- Migration 041: Add Snowfall plugin
-- Adds the snowfall plugin record to the plugins table (disabled by default).
-- Enable it through Admin → Plugins.

INSERT IGNORE INTO `plugins` (`name`, `slug`, `version`, `is_enabled`)
VALUES ('Snowfall', 'snowfall', '1.0.0', 0);
