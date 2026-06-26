-- ============================================================
-- AI Recruitment SaaS Platform — Full Database Schema
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── SYSTEM / MULTI-TENANT ────────────────────────────────────

CREATE TABLE IF NOT EXISTS `tenants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `domain` VARCHAR(255) DEFAULT NULL,
  `logo_url` TEXT DEFAULT NULL,
  `status` ENUM('active','suspended','trial') NOT NULL DEFAULT 'trial',
  `plan` ENUM('starter','pro','enterprise') NOT NULL DEFAULT 'starter',
  `owner_id` INT UNSIGNED DEFAULT NULL,
  `settings` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_settings_tenant_key` (`tenant_id`,`key`),
  CONSTRAINT `fk_tenant_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_ai_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `openai_api_key` TEXT DEFAULT NULL,
  `heygen_api_key` TEXT DEFAULT NULL,
  `openai_model` VARCHAR(50) NOT NULL DEFAULT 'gpt-4o',
  `enable_video_interviews` TINYINT(1) NOT NULL DEFAULT 0,
  `enable_voice_interviews` TINYINT(1) NOT NULL DEFAULT 1,
  `enable_text_interviews` TINYINT(1) NOT NULL DEFAULT 1,
  `openai_connected_at` TIMESTAMP NULL DEFAULT NULL,
  `heygen_connected_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_ai_settings_tenant_unique` (`tenant_id`),
  CONSTRAINT `fk_tenant_ai_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_subscriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `plan` ENUM('starter','pro','enterprise') NOT NULL DEFAULT 'starter',
  `status` ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `max_jobs` INT NOT NULL DEFAULT 5,
  `max_users` INT NOT NULL DEFAULT 3,
  `max_ai_interviews_per_month` INT NOT NULL DEFAULT 100,
  `current_period_start` DATE DEFAULT NULL,
  `current_period_end` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_subscriptions_tenant` (`tenant_id`),
  CONSTRAINT `fk_tenant_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `group_name` VARCHAR(50) DEFAULT 'general',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ROLES & PERMISSIONS ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`),
  CONSTRAINT `fk_roles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `group_name` VARCHAR(50) DEFAULT 'general',
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permissions_unique` (`role_id`,`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── USERS ────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL DEFAULT '',
  `email` VARCHAR(255) NOT NULL,
  `password_hash` TEXT NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `avatar_url` TEXT DEFAULT NULL,
  `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive','pending') NOT NULL DEFAULT 'active',
  `department` VARCHAR(100) DEFAULT NULL,
  `job_title` VARCHAR(100) DEFAULT NULL,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `onboarding_completed` TINYINT(1) NOT NULL DEFAULT 0,
  `remember_token` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_tenant_idx` (`tenant_id`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_roles_unique` (`user_id`,`role_id`),
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  `granted` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_permissions_unique` (`user_id`,`permission_id`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` TEXT NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_sessions_user_idx` (`user_id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `password_resets_email_idx` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ONBOARDING ───────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `onboarding_steps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `step_order` INT NOT NULL DEFAULT 0,
  `user_type` ENUM('super_admin','company','candidate') NOT NULL DEFAULT 'company',
  `icon` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onboarding_steps_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_onboarding` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `step_slug` VARCHAR(100) NOT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `skipped_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_onboarding_unique` (`user_id`,`step_slug`),
  CONSTRAINT `fk_uo_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── DEPARTMENTS & TEAMS ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `manager_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_dept_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teams` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `lead_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_teams_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teams_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_members_unique` (`team_id`,`user_id`),
  CONSTRAINT `fk_tm_team` FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── AVATARS ──────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `avatars` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `gender` ENUM('male','female','neutral') NOT NULL DEFAULT 'neutral',
  `language` ENUM('ar','en','both') NOT NULL DEFAULT 'both',
  `style` ENUM('formal','friendly','technical','casual') NOT NULL DEFAULT 'formal',
  `personality_prompt` TEXT DEFAULT NULL,
  `heygen_avatar_id` VARCHAR(255) DEFAULT NULL,
  `photo_url` TEXT DEFAULT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_avatars_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── JOBS ─────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(300) DEFAULT NULL,
  `department_id` INT UNSIGNED DEFAULT NULL,
  `seniority` ENUM('intern','junior','mid','senior','lead','manager','director','executive') NOT NULL DEFAULT 'mid',
  `employment_type` ENUM('full_time','part_time','contract','freelance','internship') NOT NULL DEFAULT 'full_time',
  `location` VARCHAR(255) DEFAULT NULL,
  `is_remote` TINYINT(1) NOT NULL DEFAULT 0,
  `salary_min` DECIMAL(12,2) DEFAULT NULL,
  `salary_max` DECIMAL(12,2) DEFAULT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `description` LONGTEXT DEFAULT NULL,
  `requirements` LONGTEXT DEFAULT NULL,
  `benefits` TEXT DEFAULT NULL,
  `avatar_id` INT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('draft','active','paused','archived','closed') NOT NULL DEFAULT 'draft',
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `closed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jobs_tenant_idx` (`tenant_id`),
  KEY `jobs_status_idx` (`status`),
  CONSTRAINT `fk_jobs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jobs_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_jobs_avatar` FOREIGN KEY (`avatar_id`) REFERENCES `avatars`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `interview_mode` ENUM('text','voice','video') NOT NULL DEFAULT 'text',
  `interview_language` ENUM('ar','en','auto') NOT NULL DEFAULT 'auto',
  `max_questions` INT NOT NULL DEFAULT 12,
  `time_limit_minutes` INT NOT NULL DEFAULT 20,
  `passing_score` DECIMAL(5,2) NOT NULL DEFAULT 68.00,
  `auto_qualify_score` DECIMAL(5,2) NOT NULL DEFAULT 82.00,
  `auto_disqualify_score` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  `cv_screening_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `link_expiry_days` INT NOT NULL DEFAULT 14,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_settings_job_unique` (`job_id`),
  CONSTRAINT `fk_js_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_criteria` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `weight` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `max_score` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  `pass_score` DECIMAL(5,2) NOT NULL DEFAULT 3.00,
  `description` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_jc_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_criteria_dimensions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `criteria_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `weight` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_jcd_criteria` FOREIGN KEY (`criteria_id`) REFERENCES `job_criteria`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_questions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `question` TEXT NOT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `difficulty` ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
  `language` ENUM('ar','en') NOT NULL DEFAULT 'en',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `job_questions_job_idx` (`job_id`),
  CONSTRAINT `fk_jq_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_question_imports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `source_job_id` INT UNSIGNED NOT NULL,
  `imported_count` INT NOT NULL DEFAULT 0,
  `imported_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_jqi_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jqi_source` FOREIGN KEY (`source_job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CAREER PAGE ──────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `career_page_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `headline` TEXT DEFAULT NULL,
  `description` LONGTEXT DEFAULT NULL,
  `logo_url` TEXT DEFAULT NULL,
  `banner_url` TEXT DEFAULT NULL,
  `primary_color` VARCHAR(20) NOT NULL DEFAULT '#4f46e5',
  `secondary_color` VARCHAR(20) NOT NULL DEFAULT '#7c3aed',
  `custom_domain` VARCHAR(255) DEFAULT NULL,
  `is_public` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `career_page_tenant_unique` (`tenant_id`),
  CONSTRAINT `fk_cp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CANDIDATE PROFILES ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS `candidate_profiles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `headline` VARCHAR(255) DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `nationality` VARCHAR(100) DEFAULT NULL,
  `current_location` VARCHAR(255) DEFAULT NULL,
  `linkedin_url` TEXT DEFAULT NULL,
  `portfolio_url` TEXT DEFAULT NULL,
  `expected_salary_min` DECIMAL(12,2) DEFAULT NULL,
  `expected_salary_max` DECIMAL(12,2) DEFAULT NULL,
  `salary_currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `years_experience` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  `notice_period_days` INT NOT NULL DEFAULT 0,
  `willing_to_relocate` TINYINT(1) NOT NULL DEFAULT 0,
  `willing_remote` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `candidate_profiles_user_unique` (`user_id`),
  CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_skills` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `skill_name` VARCHAR(100) NOT NULL,
  `proficiency` ENUM('beginner','intermediate','advanced','expert') NOT NULL DEFAULT 'intermediate',
  `years` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `candidate_skills_unique` (`user_id`,`skill_name`),
  CONSTRAINT `fk_cs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` ENUM('cv','cover_letter','certificate','other') NOT NULL DEFAULT 'cv',
  `filename` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_path` TEXT NOT NULL,
  `file_size` INT NOT NULL DEFAULT 0,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `candidate_docs_user_idx` (`user_id`),
  CONSTRAINT `fk_cd_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_experiences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `company_name` VARCHAR(255) NOT NULL,
  `job_title` VARCHAR(255) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ce_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_education` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `institution` VARCHAR(255) NOT NULL,
  `degree` VARCHAR(255) DEFAULT NULL,
  `field_of_study` VARCHAR(255) DEFAULT NULL,
  `start_year` INT DEFAULT NULL,
  `end_year` INT DEFAULT NULL,
  `gpa` DECIMAL(4,2) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_cedu_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── APPLICATIONS ─────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `job_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('applied','screening','ai_interview','technical_test','human_interview','shortlisted','reference_check','offer_extended','offer_accepted','offer_declined','hired','rejected','withdrawn') NOT NULL DEFAULT 'applied',
  `source` ENUM('direct','invite','career_page','linkedin','referral') NOT NULL DEFAULT 'direct',
  `cover_letter` TEXT DEFAULT NULL,
  `expected_salary` DECIMAL(12,2) DEFAULT NULL,
  `cv_document_id` INT UNSIGNED DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_stage_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `applications_tenant_idx` (`tenant_id`),
  KEY `applications_job_idx` (`job_id`),
  KEY `applications_user_idx` (`user_id`),
  KEY `applications_status_idx` (`status`),
  CONSTRAINT `fk_apps_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_apps_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_apps_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_apps_cv` FOREIGN KEY (`cv_document_id`) REFERENCES `candidate_documents`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `document_id` INT UNSIGNED NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ad_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ad_doc` FOREIGN KEY (`document_id`) REFERENCES `candidate_documents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_stage_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `from_status` VARCHAR(50) DEFAULT NULL,
  `to_status` VARCHAR(50) NOT NULL,
  `changed_by` INT UNSIGNED DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ash_app_idx` (`application_id`),
  CONSTRAINT `fk_ash_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `note` LONGTEXT NOT NULL,
  `is_private` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_an_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_labels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#6b7280',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_labels_unique` (`application_id`,`label`),
  CONSTRAINT `fk_al_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_form_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `field_name` VARCHAR(100) NOT NULL,
  `field_type` ENUM('text','textarea','select','checkbox','number','date') NOT NULL DEFAULT 'text',
  `field_label` VARCHAR(255) NOT NULL,
  `options` JSON DEFAULT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `field_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_aff_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_form_responses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `field_id` INT UNSIGNED NOT NULL,
  `response` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_afr_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_afr_field` FOREIGN KEY (`field_id`) REFERENCES `application_form_fields`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_bulk_actions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `action_type` VARCHAR(100) NOT NULL,
  `application_ids` JSON NOT NULL,
  `performed_by` INT UNSIGNED DEFAULT NULL,
  `result` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── INTERVIEW LINKS ──────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `interview_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `job_id` INT UNSIGNED NOT NULL,
  `application_id` INT UNSIGNED DEFAULT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `interview_links_token_unique` (`token`),
  CONSTRAINT `fk_il_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_il_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_il_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interview_link_guest_info` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `link_id` INT UNSIGNED NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `years_experience` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  `expected_salary` DECIMAL(12,2) DEFAULT NULL,
  `cv_path` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ilgi_link_unique` (`link_id`),
  CONSTRAINT `fk_ilgi_link` FOREIGN KEY (`link_id`) REFERENCES `interview_links`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── AI INTERVIEWS ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_interviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `application_id` INT UNSIGNED NOT NULL,
  `link_id` INT UNSIGNED DEFAULT NULL,
  `avatar_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','in_progress','completed','abandoned','expired') NOT NULL DEFAULT 'pending',
  `mode` ENUM('text','voice','video') NOT NULL DEFAULT 'text',
  `language` ENUM('ar','en','auto') NOT NULL DEFAULT 'auto',
  `started_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `duration_seconds` INT DEFAULT NULL,
  `questions_asked` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ai_interviews_app_idx` (`application_id`),
  CONSTRAINT `fk_aii_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aii_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aii_link` FOREIGN KEY (`link_id`) REFERENCES `interview_links`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aii_avatar` FOREIGN KEY (`avatar_id`) REFERENCES `avatars`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_interview_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id` INT UNSIGNED NOT NULL,
  `role` ENUM('ai','candidate') NOT NULL,
  `content` LONGTEXT NOT NULL,
  `question_number` INT DEFAULT NULL,
  `question_category` VARCHAR(100) DEFAULT NULL,
  `audio_url` TEXT DEFAULT NULL,
  `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aim_interview_idx` (`interview_id`),
  CONSTRAINT `fk_aim_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_interview_questions_used` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED DEFAULT NULL,
  `question_text` LONGTEXT NOT NULL,
  `answer_text` LONGTEXT DEFAULT NULL,
  `follow_up_used` TINYINT(1) NOT NULL DEFAULT 0,
  `score` DECIMAL(5,2) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_aiqu_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aiqu_question` FOREIGN KEY (`question_id`) REFERENCES `job_questions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_interview_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id` INT UNSIGNED NOT NULL,
  `session_data` JSON DEFAULT NULL,
  `last_active_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_interview_sessions_unique` (`interview_id`),
  CONSTRAINT `fk_ais_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interview_feedback` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id` INT UNSIGNED NOT NULL,
  `rating` INT DEFAULT NULL COMMENT '1-5',
  `experience_rating` INT DEFAULT NULL,
  `clarity_rating` INT DEFAULT NULL,
  `feedback_text` TEXT DEFAULT NULL,
  `submitted_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_if_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── AI ANALYSIS RESULTS ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_cv_analyses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `document_id` INT UNSIGNED DEFAULT NULL,
  `match_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `skills_extracted` JSON DEFAULT NULL,
  `companies_extracted` JSON DEFAULT NULL,
  `years_experience` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  `education_level` VARCHAR(100) DEFAULT NULL,
  `strengths` JSON DEFAULT NULL,
  `weaknesses` JSON DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `raw_response` LONGTEXT DEFAULT NULL,
  `tokens_used` INT NOT NULL DEFAULT 0,
  `analyzed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ai_cv_app_idx` (`application_id`),
  CONSTRAINT `fk_acva_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acva_doc` FOREIGN KEY (`document_id`) REFERENCES `candidate_documents`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_skill_scores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id` INT UNSIGNED NOT NULL,
  `application_id` INT UNSIGNED NOT NULL,
  `technical_competency` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `communication` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `problem_solving` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `critical_thinking` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `confidence` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `leadership` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `culture_fit` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `professionalism` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `ai_knowledge` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `english_proficiency` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `learning_ability` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `overall_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `confidence_level` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `tokens_used` INT NOT NULL DEFAULT 0,
  `scored_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_skill_scores_interview_unique` (`interview_id`),
  CONSTRAINT `fk_ass_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ass_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_personality_analyses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id` INT UNSIGNED NOT NULL,
  `application_id` INT UNSIGNED NOT NULL,
  `disc_d` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `disc_i` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `disc_s` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `disc_c` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `big5_openness` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `big5_conscientiousness` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `big5_extraversion` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `big5_agreeableness` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `big5_neuroticism` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `growth_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `pressure_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `leadership_style` VARCHAR(100) DEFAULT NULL,
  `summary` TEXT DEFAULT NULL,
  `tokens_used` INT NOT NULL DEFAULT 0,
  `analyzed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_personality_interview_unique` (`interview_id`),
  CONSTRAINT `fk_apa_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_apa_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_red_flags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id` INT UNSIGNED NOT NULL,
  `application_id` INT UNSIGNED NOT NULL,
  `severity` ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
  `category` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `evidence` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ai_rf_app_idx` (`application_id`),
  CONSTRAINT `fk_arf_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_arf_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_recommendations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id` INT UNSIGNED NOT NULL,
  `application_id` INT UNSIGNED NOT NULL,
  `final_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `recommendation` ENUM('strong_yes','yes','maybe','no') NOT NULL DEFAULT 'maybe',
  `executive_summary` TEXT DEFAULT NULL,
  `strengths` JSON DEFAULT NULL,
  `weaknesses` JSON DEFAULT NULL,
  `hiring_risks` TEXT DEFAULT NULL,
  `tokens_used` INT NOT NULL DEFAULT 0,
  `generated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_rec_interview_unique` (`interview_id`),
  CONSTRAINT `fk_ar_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ar_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_criteria_scores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id` INT UNSIGNED NOT NULL,
  `criteria_id` INT UNSIGNED NOT NULL,
  `score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_cs_unique` (`interview_id`,`criteria_id`),
  CONSTRAINT `fk_acs_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acs_criteria` FOREIGN KEY (`criteria_id`) REFERENCES `job_criteria`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_interview_timeline` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interview_id` INT UNSIGNED NOT NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `occurred_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ait_interview_idx` (`interview_id`),
  CONSTRAINT `fk_ait_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_match_scores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `job_id` INT UNSIGNED NOT NULL,
  `cv_match_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `ai_match_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `overall_match` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `computed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cms_app_unique` (`application_id`),
  CONSTRAINT `fk_cms_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── HUMAN INTERVIEWS ─────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `human_interviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `application_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `scheduled_at` TIMESTAMP NOT NULL,
  `duration_minutes` INT NOT NULL DEFAULT 60,
  `meeting_link` TEXT DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `type` ENUM('technical','manager','final','hr') NOT NULL DEFAULT 'technical',
  `status` ENUM('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hi_app_idx` (`application_id`),
  CONSTRAINT `fk_hi_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hi_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `human_interview_attendees` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `human_interview_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('interviewer','observer') NOT NULL DEFAULT 'interviewer',
  `confirmed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hia_unique` (`human_interview_id`,`user_id`),
  CONSTRAINT `fk_hia_hi` FOREIGN KEY (`human_interview_id`) REFERENCES `human_interviews`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hia_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `human_interview_evaluations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `human_interview_id` INT UNSIGNED NOT NULL,
  `evaluator_id` INT UNSIGNED NOT NULL,
  `technical_depth` INT DEFAULT NULL COMMENT '1-5',
  `problem_solving` INT DEFAULT NULL COMMENT '1-5',
  `communication` INT DEFAULT NULL COMMENT '1-5',
  `culture_fit` INT DEFAULT NULL COMMENT '1-5',
  `takes_ownership` INT DEFAULT NULL COMMENT '1-5',
  `seniority_fit` INT DEFAULT NULL COMMENT '1-5',
  `overall_rating` DECIMAL(3,1) DEFAULT NULL,
  `recommendation` ENUM('strong_yes','yes','maybe','no') DEFAULT NULL,
  `strengths` TEXT DEFAULT NULL,
  `weaknesses` TEXT DEFAULT NULL,
  `notes` LONGTEXT DEFAULT NULL,
  `submitted_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hie_unique` (`human_interview_id`,`evaluator_id`),
  CONSTRAINT `fk_hie_hi` FOREIGN KEY (`human_interview_id`) REFERENCES `human_interviews`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hie_eval` FOREIGN KEY (`evaluator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── OFFERS ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `offers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `application_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `base_salary` DECIMAL(12,2) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `start_date` DATE DEFAULT NULL,
  `benefits` TEXT DEFAULT NULL,
  `additional_terms` LONGTEXT DEFAULT NULL,
  `status` ENUM('draft','sent','accepted','rejected','expired','withdrawn') NOT NULL DEFAULT 'draft',
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `accepted_at` TIMESTAMP NULL DEFAULT NULL,
  `rejected_at` TIMESTAMP NULL DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `offers_app_idx` (`application_id`),
  CONSTRAINT `fk_offers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_offers_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `offer_benefits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `offer_id` INT UNSIGNED NOT NULL,
  `benefit_name` VARCHAR(255) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ob_offer` FOREIGN KEY (`offer_id`) REFERENCES `offers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TALENT POOL ──────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `talent_pool_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#4f46e5',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_tpg_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `talent_pool_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `application_id` INT UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `added_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tpm_unique` (`group_id`,`user_id`),
  CONSTRAINT `fk_tpm_group` FOREIGN KEY (`group_id`) REFERENCES `talent_pool_groups`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tpm_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tpm_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── NOTIFICATIONS ────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `type` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT DEFAULT NULL,
  `data` JSON DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  `action_url` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notif_user_idx` (`user_id`),
  KEY `notif_read_idx` (`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) DEFAULT NULL,
  `body_html` LONGTEXT DEFAULT NULL,
  `body_text` TEXT DEFAULT NULL,
  `variables` JSON DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notif_tpl_unique` (`tenant_id`,`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `notification_type` VARCHAR(100) NOT NULL,
  `channel` ENUM('email','system','both','none') NOT NULL DEFAULT 'both',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ns_unique` (`user_id`,`notification_type`),
  CONSTRAINT `fk_ns_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── AI USAGE ANALYTICS ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_usage_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `feature` VARCHAR(100) NOT NULL,
  `provider` ENUM('openai','heygen') NOT NULL DEFAULT 'openai',
  `model` VARCHAR(100) DEFAULT NULL,
  `prompt_tokens` INT NOT NULL DEFAULT 0,
  `completion_tokens` INT NOT NULL DEFAULT 0,
  `total_tokens` INT NOT NULL DEFAULT 0,
  `cost_usd` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `status` ENUM('success','error') NOT NULL DEFAULT 'success',
  `error_message` TEXT DEFAULT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT DEFAULT NULL,
  `duration_ms` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `aul_tenant_idx` (`tenant_id`),
  KEY `aul_created_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_usage_daily` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `provider` VARCHAR(50) NOT NULL DEFAULT 'openai',
  `total_requests` INT NOT NULL DEFAULT 0,
  `total_tokens` INT NOT NULL DEFAULT 0,
  `total_cost_usd` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `successful_requests` INT NOT NULL DEFAULT 0,
  `failed_requests` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `aud_unique` (`tenant_id`,`date`,`provider`),
  CONSTRAINT `fk_aud_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── COMPARISONS ──────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `candidate_comparisons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `job_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_cc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_comparison_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `comparison_id` INT UNSIGNED NOT NULL,
  `application_id` INT UNSIGNED NOT NULL,
  `position` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cci_unique` (`comparison_id`,`application_id`),
  CONSTRAINT `fk_cci_comp` FOREIGN KEY (`comparison_id`) REFERENCES `candidate_comparisons`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cci_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PIPELINE ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `pipeline_stage_transitions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `application_id` INT UNSIGNED NOT NULL,
  `from_status` VARCHAR(50) DEFAULT NULL,
  `to_status` VARCHAR(50) NOT NULL,
  `triggered_by` ENUM('ai','manual','system') NOT NULL DEFAULT 'manual',
  `changed_by` INT UNSIGNED DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pst_app_idx` (`application_id`),
  CONSTRAINT `fk_pst_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pst_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── AUDIT ────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(100) DEFAULT NULL,
  `entity_id` INT DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `al_tenant_idx` (`tenant_id`),
  KEY `al_created_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SYSTEM OPERATIONS ────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `setup_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `step` VARCHAR(100) NOT NULL,
  `status` ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `to_email` VARCHAR(255) NOT NULL,
  `to_name` VARCHAR(255) DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_html` LONGTEXT DEFAULT NULL,
  `body_text` TEXT DEFAULT NULL,
  `template_slug` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` INT NOT NULL DEFAULT 0,
  `scheduled_at` TIMESTAMP NULL DEFAULT NULL,
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  `error` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `eq_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `file_uploads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `file_path` TEXT NOT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `size_bytes` INT NOT NULL DEFAULT 0,
  `disk` ENUM('local','s3') NOT NULL DEFAULT 'local',
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SCORING & EXPORT ─────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `scoring_rubrics` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_sr_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rubric_levels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rubric_id` INT UNSIGNED NOT NULL,
  `score` INT NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_rl_rubric` FOREIGN KEY (`rubric_id`) REFERENCES `scoring_rubrics`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `export_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `type` VARCHAR(100) NOT NULL,
  `filters` JSON DEFAULT NULL,
  `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `file_path` TEXT DEFAULT NULL,
  `row_count` INT DEFAULT NULL,
  `error` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `saved_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(100) NOT NULL,
  `config` JSON DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_schedules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `frequency` ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
  `next_run_at` TIMESTAMP NOT NULL,
  `last_run_at` TIMESTAMP NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_rs_report` FOREIGN KEY (`report_id`) REFERENCES `saved_reports`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── AI CONFIG ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `ai_prompt_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `system_prompt` LONGTEXT DEFAULT NULL,
  `user_prompt_template` LONGTEXT DEFAULT NULL,
  `model` VARCHAR(100) NOT NULL DEFAULT 'gpt-4o',
  `max_tokens` INT NOT NULL DEFAULT 2000,
  `temperature` DECIMAL(3,2) NOT NULL DEFAULT 0.70,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `apt_unique` (`tenant_id`,`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TAGS ─────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#6b7280',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_unique` (`tenant_id`,`name`),
  CONSTRAINT `fk_tags_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `taggables` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag_id` INT UNSIGNED NOT NULL,
  `taggable_type` VARCHAR(50) NOT NULL,
  `taggable_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `taggables_unique` (`tag_id`,`taggable_type`,`taggable_id`),
  CONSTRAINT `fk_taggables_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TENANT SUBSCRIPTIONS ─────────────────────────────────────

CREATE TABLE IF NOT EXISTS `tenant_subscriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `plan` ENUM('starter','pro','enterprise') NOT NULL DEFAULT 'starter',
  `status` ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `max_jobs` INT NOT NULL DEFAULT 5,
  `max_users` INT NOT NULL DEFAULT 3,
  `max_ai_interviews_per_month` INT NOT NULL DEFAULT 100,
  `current_period_start` DATE NOT NULL,
  `current_period_end` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ts_tenant_unique` (`tenant_id`),
  CONSTRAINT `fk_ts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_usage_stats` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `month_year` VARCHAR(10) NOT NULL,
  `total_jobs` INT NOT NULL DEFAULT 0,
  `total_applications` INT NOT NULL DEFAULT 0,
  `total_ai_interviews` INT NOT NULL DEFAULT 0,
  `total_hired` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tus_unique` (`tenant_id`,`month_year`),
  CONSTRAINT `fk_tus_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── CALENDAR ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `scheduled_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `type` ENUM('human_interview','meeting','deadline','other') NOT NULL DEFAULT 'meeting',
  `reference_id` INT DEFAULT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `start_at` TIMESTAMP NOT NULL,
  `end_at` TIMESTAMP NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_se_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interview_slots` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `start_at` TIMESTAMP NOT NULL,
  `end_at` TIMESTAMP NOT NULL,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_is_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_is_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── WEBHOOKS ─────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `url` TEXT NOT NULL,
  `events` JSON NOT NULL,
  `secret` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_triggered_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_wh_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `webhook_id` INT UNSIGNED NOT NULL,
  `event` VARCHAR(100) NOT NULL,
  `payload` JSON DEFAULT NULL,
  `response_status` INT DEFAULT NULL,
  `response_body` TEXT DEFAULT NULL,
  `delivered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_whl_wh` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ACTIVITY & MISC ──────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `user_activities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `activity_type` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ua_user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assessment_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `questions` JSON DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT DEFAULT NULL,
  `target_type` ENUM('all','super_admin','tenant','user') NOT NULL DEFAULT 'all',
  `target_id` INT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_application_counts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id` INT UNSIGNED NOT NULL,
  `total` INT NOT NULL DEFAULT 0,
  `this_week` INT NOT NULL DEFAULT 0,
  `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jac_job_unique` (`job_id`),
  CONSTRAINT `fk_jac_job` FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- System Roles
INSERT IGNORE INTO `roles` (`name`, `slug`, `description`, `is_system`, `tenant_id`) VALUES
('Super Admin',          'super_admin',          'Full platform access',                          1, NULL),
('HR Director',          'hr_director',          'Full HR access for the company',               1, NULL),
('HR Manager',           'hr_manager',           'Manage jobs and candidates',                   1, NULL),
('Recruiter',            'recruiter',            'Source and screen candidates',                 1, NULL),
('Technical Interviewer','technical_interviewer','Conduct technical interviews',                 1, NULL),
('Department Manager',   'department_manager',   'View and comment on candidates for dept',     1, NULL),
('Operations Manager',   'operations_manager',   'Operations and reports access',               1, NULL),
('Executive Reviewer',   'executive_reviewer',   'Read-only executive dashboard',               1, NULL),
('Viewer',               'viewer',               'Read-only access',                            1, NULL),
('Candidate',            'candidate',            'Job applicant account',                       1, NULL);

-- Permissions
INSERT IGNORE INTO `permissions` (`name`, `slug`, `group_name`) VALUES
-- Jobs
('View Jobs',            'jobs.view',             'jobs'),
('Create Jobs',          'jobs.create',           'jobs'),
('Edit Jobs',            'jobs.edit',             'jobs'),
('Delete Jobs',          'jobs.delete',           'jobs'),
('Archive Jobs',         'jobs.archive',          'jobs'),
('Generate Interview Links','jobs.generate_link', 'jobs'),
-- Candidates
('View Candidates',      'candidates.view',       'candidates'),
('Manage Candidates',    'candidates.manage',     'candidates'),
('Export Candidates',    'candidates.export',     'candidates'),
('Delete Candidates',    'candidates.delete',     'candidates'),
-- Applications
('View Applications',    'applications.view',     'applications'),
('Change App Status',    'applications.status',   'applications'),
('Add App Notes',        'applications.notes',    'applications'),
('View App Reports',     'applications.reports',  'applications'),
-- AI Interviews
('View AI Interviews',   'ai_interviews.view',    'ai_interviews'),
('Manage AI Interviews', 'ai_interviews.manage',  'ai_interviews'),
-- Human Interviews
('Schedule Interviews',  'human_interviews.schedule','interviews'),
('Evaluate Interviews',  'human_interviews.evaluate','interviews'),
-- Offers
('View Offers',          'offers.view',           'offers'),
('Create Offers',        'offers.create',         'offers'),
('Send Offers',          'offers.send',           'offers'),
('Withdraw Offers',      'offers.withdraw',       'offers'),
-- Talent Pool
('View Talent Pool',     'talent_pool.view',      'talent_pool'),
('Manage Talent Pool',   'talent_pool.manage',    'talent_pool'),
-- Users
('View Users',           'users.view',            'users'),
('Create Users',         'users.create',          'users'),
('Edit Users',           'users.edit',            'users'),
('Delete Users',         'users.delete',          'users'),
-- Roles
('View Roles',           'roles.view',            'roles'),
('Manage Roles',         'roles.manage',          'roles'),
-- Avatars
('View Avatars',         'avatars.view',          'avatars'),
('Manage Avatars',       'avatars.manage',        'avatars'),
-- Settings
('View Settings',        'settings.view',         'settings'),
('Manage Settings',      'settings.manage',       'settings'),
('Manage AI Settings',   'settings.ai',           'settings'),
-- Pipeline
('View Pipeline',        'pipeline.view',         'pipeline'),
('Manage Pipeline',      'pipeline.manage',       'pipeline'),
-- Reports
('View Reports',         'reports.view',          'reports'),
('Export Reports',       'reports.export',        'reports'),
-- Dashboard
('View Dashboard',       'dashboard.view',        'dashboard'),
('View Analytics',       'analytics.view',        'analytics'),
-- Super Admin
('Manage Tenants',       'tenants.manage',        'super_admin'),
('View All Tenants',     'tenants.view',          'super_admin'),
('Platform Settings',    'platform.settings',     'super_admin'),
-- Comparisons
('View Comparisons',     'comparisons.view',      'comparisons'),
('Manage Comparisons',   'comparisons.manage',    'comparisons'),
-- Criteria
('Manage Criteria',      'criteria.manage',       'jobs'),
('View Criteria',        'criteria.view',         'jobs');

-- Assign all permissions to super_admin
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.slug = 'super_admin';

-- HR Director permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'hr_director'
AND p.slug IN ('jobs.view','jobs.create','jobs.edit','jobs.archive','jobs.generate_link',
               'candidates.view','candidates.manage','candidates.export',
               'applications.view','applications.status','applications.notes','applications.reports',
               'ai_interviews.view','ai_interviews.manage',
               'human_interviews.schedule','human_interviews.evaluate',
               'offers.view','offers.create','offers.send','offers.withdraw',
               'talent_pool.view','talent_pool.manage',
               'users.view','users.create','users.edit',
               'roles.view','avatars.view','avatars.manage',
               'settings.view','settings.manage','settings.ai',
               'pipeline.view','pipeline.manage',
               'reports.view','reports.export','dashboard.view','analytics.view',
               'comparisons.view','comparisons.manage','criteria.manage','criteria.view');

-- HR Manager permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'hr_manager'
AND p.slug IN ('jobs.view','jobs.create','jobs.edit','jobs.archive','jobs.generate_link',
               'candidates.view','candidates.manage','candidates.export',
               'applications.view','applications.status','applications.notes','applications.reports',
               'ai_interviews.view','ai_interviews.manage',
               'human_interviews.schedule','human_interviews.evaluate',
               'offers.view','offers.create','offers.send',
               'talent_pool.view','talent_pool.manage',
               'users.view','avatars.view',
               'settings.view','pipeline.view','pipeline.manage',
               'reports.view','dashboard.view','analytics.view',
               'comparisons.view','comparisons.manage','criteria.manage','criteria.view');

-- Recruiter permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'recruiter'
AND p.slug IN ('jobs.view','jobs.generate_link',
               'candidates.view','candidates.manage',
               'applications.view','applications.status','applications.notes',
               'ai_interviews.view','human_interviews.schedule',
               'offers.view','talent_pool.view','talent_pool.manage',
               'pipeline.view','pipeline.manage','dashboard.view',
               'comparisons.view','comparisons.manage','criteria.view');

-- Technical Interviewer permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'technical_interviewer'
AND p.slug IN ('jobs.view','candidates.view','applications.view','applications.notes',
               'ai_interviews.view','human_interviews.evaluate',
               'pipeline.view','dashboard.view','criteria.view');

-- Department Manager permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'department_manager'
AND p.slug IN ('jobs.view','candidates.view','applications.view','applications.notes',
               'ai_interviews.view','human_interviews.evaluate',
               'pipeline.view','dashboard.view','criteria.view','criteria.manage');

-- Viewer permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug IN ('viewer','executive_reviewer','operations_manager')
AND p.slug IN ('jobs.view','candidates.view','applications.view','ai_interviews.view',
               'pipeline.view','reports.view','dashboard.view','analytics.view');

-- Onboarding Steps
INSERT IGNORE INTO `onboarding_steps` (`slug`, `title`, `description`, `step_order`, `user_type`, `icon`) VALUES
-- Super Admin
('sa_platform_setup',   'Platform Setup',      'Configure your platform settings',              1, 'super_admin', 'settings'),
('sa_create_company',   'Create First Company','Add your first company/tenant',                 2, 'super_admin', 'building'),
('sa_create_admin',     'Create Admin User',   'Create the first HR admin for the company',    3, 'super_admin', 'user-plus'),
('sa_review_dashboard', 'Explore Dashboard',   'Review the super admin dashboard',             4, 'super_admin', 'chart-bar'),
-- Company/HR
('co_ai_settings',      'Connect AI',          'Add your OpenAI API key to enable AI features',1, 'company', 'cpu'),
('co_create_job',       'Create First Job',    'Create your first job opening',                2, 'company', 'briefcase'),
('co_create_avatar',    'Setup AI Interviewer','Create your AI interviewer avatar',            3, 'company', 'user-circle'),
('co_invite_team',      'Invite Team Members', 'Invite your HR team to the platform',          4, 'company', 'users'),
('co_generate_link',    'Generate Interview Link','Generate your first AI interview link',     5, 'company', 'link'),
-- Candidate
('ca_complete_profile', 'Complete Your Profile','Add your details and experience',             1, 'candidate', 'user'),
('ca_upload_cv',        'Upload Your CV',       'Upload your resume/CV',                       2, 'candidate', 'upload'),
('ca_browse_jobs',      'Browse Jobs',          'Explore available positions',                 3, 'candidate', 'search'),
('ca_apply_job',        'Apply for a Job',      'Submit your first job application',           4, 'candidate', 'send');

-- Default AI Prompt Templates
INSERT IGNORE INTO `ai_prompt_templates` (`tenant_id`, `slug`, `name`, `system_prompt`, `user_prompt_template`, `model`, `max_tokens`, `temperature`) VALUES
(NULL, 'cv_analysis', 'CV Analysis',
'You are an expert HR recruiter and CV analyzer. Analyze the provided CV against the job requirements and return a detailed JSON analysis. Be objective, thorough, and focus on factual information from the CV.',
'Analyze this CV for the position of {{job_title}} ({{seniority}} level).\n\nJob Description: {{job_description}}\nJob Requirements: {{job_requirements}}\n\nCV Content:\n{{cv_text}}\n\nReturn a JSON object with: match_score (0-100), skills_extracted (array), companies_extracted (array), years_experience (number), education_level (string), strengths (array of strings), weaknesses (array of strings), notes (string with overall assessment).',
'gpt-4o', 2000, 0.30),

(NULL, 'interview_conductor', 'Interview Conductor',
'You are {{avatar_name}}, a professional AI recruiter conducting a {{style}} job interview for {{company_name}}. You are interviewing for the position of {{job_title}}.\n\nInterview Guidelines:\n- Ask one question at a time\n- Follow up on interesting answers\n- Keep a {{language}} conversation (detect candidate language automatically)\n- Be professional but approachable\n- Do not reveal scoring or evaluation criteria\n- After {{max_questions}} questions, wrap up the interview professionally\n- Respond in the same language the candidate uses (Arabic or English)',
'Continue the interview. Previous conversation:\n{{conversation_history}}\n\nCandidate just answered: {{candidate_answer}}\n\nQuestion count: {{question_count}}/{{max_questions}}\n\nJob criteria to explore: {{criteria_list}}\nQuestion bank hints: {{question_hints}}\n\nRespond naturally as the interviewer. If this is the last question, thank the candidate and close the interview.',
'gpt-4o', 500, 0.80),

(NULL, 'skill_scorer', 'Skill Scorer',
'You are an expert HR analyst. Based on an interview transcript, score the candidate on 11 key skills. Be objective and base all scores on actual evidence from the transcript.',
'Score this candidate based on their interview for {{job_title}}.\n\nInterview Transcript:\n{{transcript}}\n\nCV Summary: {{cv_summary}}\n\nScore each skill from 0-100 and return as JSON:\n{\n  "technical_competency": 0-100 (weight 18%),\n  "communication": 0-100 (weight 12%),\n  "problem_solving": 0-100 (weight 12%),\n  "critical_thinking": 0-100 (weight 10%),\n  "confidence": 0-100 (weight 8%),\n  "leadership": 0-100 (weight 8%),\n  "culture_fit": 0-100 (weight 8%),\n  "professionalism": 0-100 (weight 8%),\n  "ai_knowledge": 0-100 (weight 6%),\n  "english_proficiency": 0-100 (weight 6%),\n  "learning_ability": 0-100 (weight 4%),\n  "overall_score": weighted average,\n  "confidence_level": 0-100 (how confident are you in this scoring)\n}',
'gpt-4o', 1000, 0.20),

(NULL, 'personality_analyst', 'Personality Analyst',
'You are an expert organizational psychologist. Analyze interview transcripts to provide personality insights using DISC and Big Five frameworks. Base all assessments on behavioral evidence from the interview.',
'Analyze this candidate personality based on their interview.\n\nTranscript: {{transcript}}\n\nReturn JSON:\n{\n  "disc_d": 0-100,\n  "disc_i": 0-100,\n  "disc_s": 0-100,\n  "disc_c": 0-100,\n  "big5_openness": 0-100,\n  "big5_conscientiousness": 0-100,\n  "big5_extraversion": 0-100,\n  "big5_agreeableness": 0-100,\n  "big5_neuroticism": 0-100,\n  "growth_score": 0-100,\n  "pressure_score": 0-100,\n  "leadership_style": "string",\n  "summary": "3-4 sentence behavioral summary"\n}',
'gpt-4o', 1000, 0.30),

(NULL, 'red_flag_detector', 'Red Flag Detector',
'You are a thorough HR auditor looking for potential risks and inconsistencies in candidate profiles. Be objective but thorough — only flag genuine concerns with evidence.',
'Review this candidate for potential red flags.\n\nCV Content: {{cv_text}}\nInterview Transcript: {{transcript}}\nExpected Salary Range: {{salary_range}}\nJob Level: {{seniority}}\n\nReturn JSON array of red flags:\n[{"severity": "high|medium|low", "category": "string", "description": "string", "evidence": "quote from transcript or CV"}]',
'gpt-4o', 1000, 0.20),

(NULL, 'recommendation_generator', 'Recommendation Generator',
'You are a senior HR advisor generating final hiring recommendations. Be objective, balanced, and base all recommendations on the provided data.',
'Generate a final hiring recommendation for this candidate.\n\nJob: {{job_title}} ({{seniority}})\nSkill Scores: {{skill_scores}}\nCV Match: {{cv_match_score}}%\nRed Flags: {{red_flags}}\nPersonality Summary: {{personality_summary}}\nInterview Summary: {{interview_summary}}\n\nScoring: 82+ = strong_yes, 68-81 = yes, 50-67 = maybe, <50 = no\n\nReturn JSON:\n{\n  "final_score": 0-100,\n  "recommendation": "strong_yes|yes|maybe|no",\n  "executive_summary": "3-5 sentences",\n  "strengths": ["array of top strengths"],\n  "weaknesses": ["array of key weaknesses"],\n  "hiring_risks": "paragraph about risks"\n}',
'gpt-4o', 1500, 0.30);

-- System Settings
INSERT IGNORE INTO `system_settings` (`key`, `value`, `group_name`) VALUES
('app_name',           'AI Recruitment Platform', 'general'),
('app_version',        '1.0.0',                  'general'),
('maintenance_mode',   '0',                       'general'),
('allow_registration', '1',                       'general'),
('default_language',   'en',                      'general'),
('max_upload_size',    '10',                      'uploads'),
('allowed_cv_types',   'pdf,doc,docx',            'uploads'),
('smtp_configured',    '0',                       'email'),
('platform_email',     '',                        'email');
