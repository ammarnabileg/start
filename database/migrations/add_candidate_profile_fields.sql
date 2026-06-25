-- Migration: add_candidate_profile_fields
-- Adds missing candidate profile columns and related tables
-- Run once against the production database.

-- ─── 1. Additional candidate profile fields ──────────────────────────────────

ALTER TABLE `candidates`
    ADD COLUMN IF NOT EXISTS `current_title`          VARCHAR(180)  NULL AFTER `last_name`,
    ADD COLUMN IF NOT EXISTS `current_company`        VARCHAR(255)  NULL AFTER `current_title`,
    ADD COLUMN IF NOT EXISTS `professional_summary`   LONGTEXT      NULL AFTER `current_company`,
    ADD COLUMN IF NOT EXISTS `nationality`            VARCHAR(100)  NULL AFTER `location`,
    ADD COLUMN IF NOT EXISTS `languages_spoken`       JSON          NULL AFTER `nationality`,
    ADD COLUMN IF NOT EXISTS `linkedin_url`           VARCHAR(512)  NULL AFTER `languages_spoken`,
    ADD COLUMN IF NOT EXISTS `cv_url`                 VARCHAR(1024) NULL,
    ADD COLUMN IF NOT EXISTS `cv_text`                LONGTEXT      NULL,
    ADD COLUMN IF NOT EXISTS `avatar`                 VARCHAR(512)  NULL;

-- ─── 2. Candidate work experience ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `candidate_experiences` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_id` BIGINT UNSIGNED NOT NULL,
    `company`      VARCHAR(255)  NULL,
    `job_title`    VARCHAR(180)  NULL,
    `start_date`   DATE          NULL,
    `end_date`     DATE          NULL,
    `is_current`   TINYINT(1)    NOT NULL DEFAULT 0,
    `description`  LONGTEXT      NULL,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_cand` (`candidate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. Candidate education ──────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `candidate_education` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_id`     BIGINT UNSIGNED NOT NULL,
    `institution`      VARCHAR(255) NULL,
    `degree`           VARCHAR(180) NULL,
    `field_of_study`   VARCHAR(180) NULL,
    `graduation_year`  SMALLINT     NULL,
    `description`      TEXT         NULL,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_cand` (`candidate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. Candidate skills (structured, separate from JSON column) ─────────────

CREATE TABLE IF NOT EXISTS `candidate_skills` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_id`     BIGINT UNSIGNED NOT NULL,
    `skill_name`       VARCHAR(120)  NOT NULL,
    `proficiency`      ENUM('beginner','intermediate','advanced','expert') NOT NULL DEFAULT 'intermediate',
    `years_of_exp`     TINYINT UNSIGNED NULL,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_cand` (`candidate_id`),
    KEY `idx_skill` (`skill_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. Ensure tenant_api_keys columns exist (idempotent) ───────────────────

ALTER TABLE `tenants`
    ADD COLUMN IF NOT EXISTS `openai_api_key`  VARCHAR(600) NULL COMMENT 'AES-256-CBC encrypted OpenAI key',
    ADD COLUMN IF NOT EXISTS `heygen_api_key`  VARCHAR(600) NULL COMMENT 'AES-256-CBC encrypted HeyGen key',
    ADD COLUMN IF NOT EXISTS `openai_model`    VARCHAR(100) NULL DEFAULT 'gpt-4o';
