-- Migration 035: Banner image library
-- Stores all uploaded banner images so they can be reused

CREATE TABLE IF NOT EXISTS `banner_images` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `path`        VARCHAR(500) NOT NULL,
  `uploaded_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_path` (`path`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
