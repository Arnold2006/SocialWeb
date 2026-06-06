-- Migration 045: Add is_ai_generated flag to media table
-- Allows users to mark images as AI-generated

ALTER TABLE `media` ADD COLUMN `is_ai_generated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_deleted`;
