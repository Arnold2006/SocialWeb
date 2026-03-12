-- Migration 006: Add site_fonts table for custom font uploads
CREATE TABLE IF NOT EXISTS `site_fonts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `filename`    VARCHAR(255) NOT NULL,
  `format`      ENUM('woff2','woff','ttf','otf') NOT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
