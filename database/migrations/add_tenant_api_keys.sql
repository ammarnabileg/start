-- Migration: Per-tenant API keys
-- Run this on existing installs to add API key columns to the tenants table.
-- New installs get these columns automatically from schema.sql.
--
-- Each company stores its OWN OpenAI/HeyGen keys — the platform owner no
-- longer bears AI costs. Keys are encrypted at rest by ApiKeyManager.

ALTER TABLE `tenants`
  ADD COLUMN IF NOT EXISTS `slug`          VARCHAR(120) NULL AFTER `name`,
  ADD COLUMN IF NOT EXISTS `domain`        VARCHAR(255) NULL AFTER `subdomain`,
  ADD COLUMN IF NOT EXISTS `openai_api_key` VARCHAR(600) NULL
      COMMENT 'AES-256-CBC encrypted OpenAI API key (ApiKeyManager)',
  ADD COLUMN IF NOT EXISTS `heygen_api_key` VARCHAR(600) NULL
      COMMENT 'AES-256-CBC encrypted HeyGen API key (ApiKeyManager)',
  ADD COLUMN IF NOT EXISTS `openai_model`   VARCHAR(100) NULL DEFAULT 'gpt-4o'
      COMMENT 'Preferred OpenAI model for this company';

-- Back-fill slug from subdomain for existing rows
UPDATE `tenants` SET `slug` = `subdomain` WHERE `slug` IS NULL;

-- Make slug unique + not null after back-fill
ALTER TABLE `tenants`
  MODIFY COLUMN `slug` VARCHAR(120) NOT NULL;

-- Add unique index on slug if not exists
ALTER TABLE `tenants`
  ADD UNIQUE INDEX IF NOT EXISTS `uq_tenants_slug` (`slug`);
