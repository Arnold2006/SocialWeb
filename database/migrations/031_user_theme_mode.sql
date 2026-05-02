-- Migration: add per-user theme mode (dark / light)
ALTER TABLE `users`
    ADD COLUMN `theme_mode` ENUM('dark','light') NOT NULL DEFAULT 'dark'
    AFTER `bio`;
