-- ============================================================
-- AI RECRUITMENT SAAS PLATFORM - COMPLETE MySQL SCHEMA
-- Part 1: System/Multi-Tenant, Users & Auth, Onboarding
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ============================================================
-- SYSTEM / MULTI-TENANT
-- ============================================================

CREATE TABLE IF NOT EXISTS `tenants` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `domain` VARCHAR(255) DEFAULT NULL,
  `logo_url` TEXT DEFAULT NULL,
  `status` ENUM('active','suspended','trial') NOT NULL DEFAULT 'trial',
  `plan` ENUM('starter','pro','enterprise') NOT NULL DEFAULT 'starter',
  `owner_id` INT DEFAULT NULL,
  `settings` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenants_slug` (`slug`),
  KEY `idx_tenants_status` (`status`),
  KEY `idx_tenants_plan` (`plan`),
  KEY `idx_tenants_owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_settings_key` (`tenant_id`,`key`),
  CONSTRAINT `fk_tenant_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_ai_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
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
  UNIQUE KEY `uq_tenant_ai_settings_tenant` (`tenant_id`),
  CONSTRAINT `fk_tenant_ai_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `group_name` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_settings_key` (`key`),
  KEY `idx_system_settings_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USERS & AUTH
-- ============================================================

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `tenant_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`),
  KEY `idx_roles_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_roles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `group_name` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `idx_permissions_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_permissions` (`role_id`,`permission_id`),
  KEY `idx_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` TEXT DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `avatar_url` TEXT DEFAULT NULL,
  `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive','pending') NOT NULL DEFAULT 'pending',
  `department` VARCHAR(100) DEFAULT NULL,
  `job_title` VARCHAR(100) DEFAULT NULL,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `onboarding_completed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_tenant_id` (`tenant_id`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_is_super_admin` (`is_super_admin`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add owner_id FK to tenants after users table exists
ALTER TABLE `tenants` ADD CONSTRAINT `fk_tenants_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `role_id` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_roles` (`user_id`,`role_id`),
  KEY `idx_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  `granted` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_permissions` (`user_id`,`permission_id`),
  KEY `idx_user_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_user_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `token` TEXT NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_sessions_user_id` (`user_id`),
  KEY `idx_user_sessions_expires_at` (`expires_at`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_email` (`email`),
  KEY `idx_password_resets_token` (`token`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ONBOARDING
-- ============================================================

CREATE TABLE IF NOT EXISTS `onboarding_steps` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `step_order` INT NOT NULL DEFAULT 0,
  `user_type` ENUM('super_admin','company','candidate') NOT NULL,
  `icon` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_onboarding_steps_slug` (`slug`),
  KEY `idx_onboarding_steps_user_type` (`user_type`),
  KEY `idx_onboarding_steps_order` (`step_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_onboarding` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `step_slug` VARCHAR(100) NOT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `skipped_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_onboarding` (`user_id`,`step_slug`),
  CONSTRAINT `fk_user_onboarding_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ============================================================
-- Part 2: Departments, Teams, Avatars, Jobs, Career Page
-- ============================================================

CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `parent_id` INT DEFAULT NULL,
  `manager_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_departments_tenant_id` (`tenant_id`),
  KEY `idx_departments_parent_id` (`parent_id`),
  KEY `idx_departments_manager_id` (`manager_id`),
  CONSTRAINT `fk_departments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_departments_parent` FOREIGN KEY (`parent_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_departments_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teams` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `department_id` INT DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `lead_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_teams_tenant_id` (`tenant_id`),
  KEY `idx_teams_department_id` (`department_id`),
  KEY `idx_teams_lead_id` (`lead_id`),
  CONSTRAINT `fk_teams_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teams_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_teams_lead` FOREIGN KEY (`lead_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_members` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `team_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_team_members` (`team_id`,`user_id`),
  KEY `idx_team_members_user_id` (`user_id`),
  CONSTRAINT `fk_team_members_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_team_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AVATARS
-- ============================================================

CREATE TABLE IF NOT EXISTS `avatars` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
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
  KEY `idx_avatars_tenant_id` (`tenant_id`),
  KEY `idx_avatars_status` (`status`),
  CONSTRAINT `fk_avatars_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- JOBS
-- ============================================================

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) DEFAULT NULL,
  `department_id` INT DEFAULT NULL,
  `seniority` ENUM('intern','junior','mid','senior','lead','manager','director','executive') DEFAULT NULL,
  `employment_type` ENUM('full_time','part_time','contract','freelance','internship') NOT NULL DEFAULT 'full_time',
  `location` VARCHAR(255) DEFAULT NULL,
  `is_remote` TINYINT(1) NOT NULL DEFAULT 0,
  `salary_min` DECIMAL(12,2) DEFAULT NULL,
  `salary_max` DECIMAL(12,2) DEFAULT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `description` LONGTEXT DEFAULT NULL,
  `requirements` LONGTEXT DEFAULT NULL,
  `benefits` TEXT DEFAULT NULL,
  `avatar_id` INT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `status` ENUM('draft','active','paused','archived','closed') NOT NULL DEFAULT 'draft',
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `closed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jobs_tenant_id` (`tenant_id`),
  KEY `idx_jobs_status` (`status`),
  KEY `idx_jobs_department_id` (`department_id`),
  KEY `idx_jobs_avatar_id` (`avatar_id`),
  KEY `idx_jobs_created_by` (`created_by`),
  KEY `idx_jobs_slug` (`slug`(191)),
  CONSTRAINT `fk_jobs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jobs_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_jobs_avatar` FOREIGN KEY (`avatar_id`) REFERENCES `avatars` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_jobs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `job_id` INT NOT NULL,
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
  UNIQUE KEY `uq_job_settings_job` (`job_id`),
  CONSTRAINT `fk_job_settings_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_criteria` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `job_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `weight` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `max_score` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  `pass_score` DECIMAL(5,2) NOT NULL DEFAULT 3.00,
  `description` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_criteria_job_id` (`job_id`),
  KEY `idx_job_criteria_created_by` (`created_by`),
  CONSTRAINT `fk_job_criteria_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_criteria_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_criteria_dimensions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `criteria_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `weight` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_criteria_dimensions_criteria` (`criteria_id`),
  CONSTRAINT `fk_job_criteria_dimensions_criteria` FOREIGN KEY (`criteria_id`) REFERENCES `job_criteria` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_questions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `job_id` INT NOT NULL,
  `question` TEXT NOT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `difficulty` ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
  `language` ENUM('ar','en') NOT NULL DEFAULT 'en',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_questions_job_id` (`job_id`),
  KEY `idx_job_questions_created_by` (`created_by`),
  KEY `idx_job_questions_is_active` (`is_active`),
  CONSTRAINT `fk_job_questions_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_questions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CAREER PAGE
-- ============================================================

CREATE TABLE IF NOT EXISTS `career_page_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
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
  UNIQUE KEY `uq_career_page_settings_tenant` (`tenant_id`),
  CONSTRAINT `fk_career_page_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ============================================================
-- Part 3: Candidates, Applications, Interview Links
-- ============================================================

CREATE TABLE IF NOT EXISTS `candidate_profiles` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
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
  `years_experience` DECIMAL(4,1) NOT NULL DEFAULT 0,
  `notice_period_days` INT NOT NULL DEFAULT 0,
  `willing_to_relocate` TINYINT(1) NOT NULL DEFAULT 0,
  `willing_remote` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_candidate_profiles_user` (`user_id`),
  CONSTRAINT `fk_candidate_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_skills` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `skill_name` VARCHAR(100) NOT NULL,
  `proficiency` ENUM('beginner','intermediate','advanced','expert') DEFAULT NULL,
  `years` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_candidate_skills` (`user_id`,`skill_name`),
  CONSTRAINT `fk_candidate_skills_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_documents` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `type` ENUM('cv','cover_letter','certificate','other') NOT NULL DEFAULT 'cv',
  `filename` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_path` TEXT NOT NULL,
  `file_size` INT DEFAULT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_candidate_documents_user_id` (`user_id`),
  KEY `idx_candidate_documents_type` (`type`),
  CONSTRAINT `fk_candidate_documents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_experiences` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `company_name` VARCHAR(255) NOT NULL,
  `job_title` VARCHAR(255) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_candidate_experiences_user_id` (`user_id`),
  CONSTRAINT `fk_candidate_experiences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_education` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `institution` VARCHAR(255) NOT NULL,
  `degree` VARCHAR(255) DEFAULT NULL,
  `field_of_study` VARCHAR(255) DEFAULT NULL,
  `start_year` INT DEFAULT NULL,
  `end_year` INT DEFAULT NULL,
  `gpa` DECIMAL(4,2) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_candidate_education_user_id` (`user_id`),
  CONSTRAINT `fk_candidate_education_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- APPLICATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `job_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `status` ENUM('applied','ai_screening','qualified','disqualified','tech_interview','manager_interview','final_review','offer','hired','rejected','withdrawn') NOT NULL DEFAULT 'applied',
  `source` ENUM('direct','invite','career_page','linkedin','referral') NOT NULL DEFAULT 'direct',
  `cover_letter` TEXT DEFAULT NULL,
  `expected_salary` DECIMAL(12,2) DEFAULT NULL,
  `cv_document_id` INT DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_stage_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_applications_tenant_id` (`tenant_id`),
  KEY `idx_applications_job_id` (`job_id`),
  KEY `idx_applications_user_id` (`user_id`),
  KEY `idx_applications_status` (`status`),
  KEY `idx_applications_cv_document_id` (`cv_document_id`),
  CONSTRAINT `fk_applications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_applications_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_applications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_applications_cv_document` FOREIGN KEY (`cv_document_id`) REFERENCES `candidate_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_documents` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `application_id` INT NOT NULL,
  `document_id` INT NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_application_documents_application` (`application_id`),
  KEY `idx_application_documents_document` (`document_id`),
  CONSTRAINT `fk_application_documents_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_documents_document` FOREIGN KEY (`document_id`) REFERENCES `candidate_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_stage_history` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `application_id` INT NOT NULL,
  `from_status` VARCHAR(50) DEFAULT NULL,
  `to_status` VARCHAR(50) NOT NULL,
  `changed_by` INT NOT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_application_stage_history_application` (`application_id`),
  KEY `idx_application_stage_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_application_stage_history_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_stage_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_notes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `application_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `note` LONGTEXT NOT NULL,
  `is_private` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_application_notes_application` (`application_id`),
  KEY `idx_application_notes_user` (`user_id`),
  CONSTRAINT `fk_application_notes_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_labels` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `application_id` INT NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `color` VARCHAR(20) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_application_labels` (`application_id`,`label`),
  KEY `idx_application_labels_created_by` (`created_by`),
  CONSTRAINT `fk_application_labels_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_labels_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_form_fields` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `job_id` INT NOT NULL,
  `field_name` VARCHAR(100) NOT NULL,
  `field_type` ENUM('text','textarea','select','checkbox','number','date') NOT NULL DEFAULT 'text',
  `field_label` VARCHAR(255) NOT NULL,
  `options` JSON DEFAULT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `field_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_application_form_fields_job` (`job_id`),
  CONSTRAINT `fk_application_form_fields_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_form_responses` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `application_id` INT NOT NULL,
  `field_id` INT NOT NULL,
  `response` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_application_form_responses_application` (`application_id`),
  KEY `idx_application_form_responses_field` (`field_id`),
  CONSTRAINT `fk_application_form_responses_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_form_responses_field` FOREIGN KEY (`field_id`) REFERENCES `application_form_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INTERVIEW LINKS
-- ============================================================

CREATE TABLE IF NOT EXISTS `interview_links` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `job_id` INT NOT NULL,
  `application_id` INT DEFAULT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_interview_links_token` (`token`),
  KEY `idx_interview_links_tenant_id` (`tenant_id`),
  KEY `idx_interview_links_job_id` (`job_id`),
  KEY `idx_interview_links_application_id` (`application_id`),
  KEY `idx_interview_links_created_by` (`created_by`),
  CONSTRAINT `fk_interview_links_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_interview_links_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_interview_links_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_interview_links_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ============================================================
-- Part 4: AI Interviews, AI Analysis Results
-- ============================================================

CREATE TABLE IF NOT EXISTS `ai_interviews` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `application_id` INT NOT NULL,
  `link_id` INT DEFAULT NULL,
  `avatar_id` INT DEFAULT NULL,
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
  KEY `idx_ai_interviews_tenant_id` (`tenant_id`),
  KEY `idx_ai_interviews_application_id` (`application_id`),
  KEY `idx_ai_interviews_link_id` (`link_id`),
  KEY `idx_ai_interviews_avatar_id` (`avatar_id`),
  KEY `idx_ai_interviews_status` (`status`),
  CONSTRAINT `fk_ai_interviews_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_interviews_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_interviews_link` FOREIGN KEY (`link_id`) REFERENCES `interview_links` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ai_interviews_avatar` FOREIGN KEY (`avatar_id`) REFERENCES `avatars` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_interview_messages` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `interview_id` INT NOT NULL,
  `role` ENUM('ai','candidate') NOT NULL,
  `content` LONGTEXT NOT NULL,
  `question_number` INT DEFAULT NULL,
  `question_category` VARCHAR(100) DEFAULT NULL,
  `audio_url` TEXT DEFAULT NULL,
  `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_interview_messages_interview` (`interview_id`),
  KEY `idx_ai_interview_messages_role` (`role`),
  CONSTRAINT `fk_ai_interview_messages_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_interview_questions_used` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `interview_id` INT NOT NULL,
  `question_id` INT DEFAULT NULL,
  `question_text` LONGTEXT NOT NULL,
  `answer_text` LONGTEXT DEFAULT NULL,
  `follow_up_used` TINYINT(1) NOT NULL DEFAULT 0,
  `score` DECIMAL(5,2) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_interview_questions_used_interview` (`interview_id`),
  KEY `idx_ai_interview_questions_used_question` (`question_id`),
  CONSTRAINT `fk_ai_interview_questions_used_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_interview_questions_used_question` FOREIGN KEY (`question_id`) REFERENCES `job_questions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interview_feedback` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `interview_id` INT NOT NULL,
  `rating` INT DEFAULT NULL COMMENT '1-5',
  `experience_rating` INT DEFAULT NULL,
  `clarity_rating` INT DEFAULT NULL,
  `feedback_text` TEXT DEFAULT NULL,
  `submitted_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_interview_feedback_interview` (`interview_id`),
  CONSTRAINT `fk_interview_feedback_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AI ANALYSIS RESULTS
-- ============================================================

CREATE TABLE IF NOT EXISTS `ai_cv_analyses` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `application_id` INT NOT NULL,
  `document_id` INT DEFAULT NULL,
  `match_score` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `skills_extracted` JSON DEFAULT NULL,
  `companies_extracted` JSON DEFAULT NULL,
  `years_experience` DECIMAL(4,1) DEFAULT NULL,
  `education_level` VARCHAR(100) DEFAULT NULL,
  `strengths` JSON DEFAULT NULL,
  `weaknesses` JSON DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `raw_response` LONGTEXT DEFAULT NULL,
  `tokens_used` INT DEFAULT NULL,
  `analyzed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_cv_analyses_application` (`application_id`),
  KEY `idx_ai_cv_analyses_document` (`document_id`),
  CONSTRAINT `fk_ai_cv_analyses_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_cv_analyses_document` FOREIGN KEY (`document_id`) REFERENCES `candidate_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_skill_scores` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `interview_id` INT NOT NULL,
  `application_id` INT NOT NULL,
  `technical_competency` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `communication` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `problem_solving` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `critical_thinking` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `confidence` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `leadership` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `culture_fit` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `professionalism` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `ai_knowledge` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `english_proficiency` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `learning_ability` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `overall_score` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `confidence_level` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `tokens_used` INT DEFAULT NULL,
  `scored_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_skill_scores_interview` (`interview_id`),
  KEY `idx_ai_skill_scores_application` (`application_id`),
  CONSTRAINT `fk_ai_skill_scores_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_skill_scores_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_personality_analyses` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `interview_id` INT NOT NULL,
  `application_id` INT NOT NULL,
  `disc_d` DECIMAL(5,2) DEFAULT NULL,
  `disc_i` DECIMAL(5,2) DEFAULT NULL,
  `disc_s` DECIMAL(5,2) DEFAULT NULL,
  `disc_c` DECIMAL(5,2) DEFAULT NULL,
  `big5_openness` DECIMAL(5,2) DEFAULT NULL,
  `big5_conscientiousness` DECIMAL(5,2) DEFAULT NULL,
  `big5_extraversion` DECIMAL(5,2) DEFAULT NULL,
  `big5_agreeableness` DECIMAL(5,2) DEFAULT NULL,
  `big5_neuroticism` DECIMAL(5,2) DEFAULT NULL,
  `growth_score` DECIMAL(5,2) DEFAULT NULL,
  `pressure_score` DECIMAL(5,2) DEFAULT NULL,
  `leadership_style` VARCHAR(100) DEFAULT NULL,
  `summary` TEXT DEFAULT NULL,
  `tokens_used` INT DEFAULT NULL,
  `analyzed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_personality_analyses_interview` (`interview_id`),
  KEY `idx_ai_personality_analyses_application` (`application_id`),
  CONSTRAINT `fk_ai_personality_analyses_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_personality_analyses_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_red_flags` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `interview_id` INT NOT NULL,
  `application_id` INT NOT NULL,
  `severity` ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
  `category` VARCHAR(100) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `evidence` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_red_flags_interview` (`interview_id`),
  KEY `idx_ai_red_flags_application` (`application_id`),
  KEY `idx_ai_red_flags_severity` (`severity`),
  CONSTRAINT `fk_ai_red_flags_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_red_flags_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_recommendations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `interview_id` INT NOT NULL,
  `application_id` INT NOT NULL,
  `final_score` DECIMAL(5,2) DEFAULT NULL,
  `recommendation` ENUM('strong_yes','yes','maybe','no') NOT NULL DEFAULT 'maybe',
  `executive_summary` TEXT DEFAULT NULL,
  `strengths` JSON DEFAULT NULL,
  `weaknesses` JSON DEFAULT NULL,
  `hiring_risks` TEXT DEFAULT NULL,
  `generated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tokens_used` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_recommendations_interview` (`interview_id`),
  KEY `idx_ai_recommendations_application` (`application_id`),
  CONSTRAINT `fk_ai_recommendations_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_recommendations_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_criteria_scores` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `interview_id` INT NOT NULL,
  `criteria_id` INT NOT NULL,
  `score` DECIMAL(5,2) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_criteria_scores` (`interview_id`,`criteria_id`),
  KEY `idx_ai_criteria_scores_criteria` (`criteria_id`),
  CONSTRAINT `fk_ai_criteria_scores_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_criteria_scores_criteria` FOREIGN KEY (`criteria_id`) REFERENCES `job_criteria` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_interview_timeline` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `interview_id` INT NOT NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `occurred_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_interview_timeline_interview` (`interview_id`),
  KEY `idx_ai_interview_timeline_occurred_at` (`occurred_at`),
  CONSTRAINT `fk_ai_interview_timeline_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ============================================================
-- Part 5: Human Interviews, Offers, Talent Pool,
--         Notifications, AI Usage, Comparisons, Pipeline,
--         Audit, System Operations, Scoring, Exports,
--         AI Config, Tags, Subscriptions, Calendar, Slots,
--         Webhooks, Activity, Assessments, Additional Tables
-- ============================================================

-- ============================================================
-- HUMAN INTERVIEWS
-- ============================================================

CREATE TABLE IF NOT EXISTS `human_interviews` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `application_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `scheduled_at` TIMESTAMP NOT NULL,
  `duration_minutes` INT NOT NULL DEFAULT 60,
  `meeting_link` TEXT DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `type` ENUM('technical','manager','final','hr') NOT NULL DEFAULT 'technical',
  `status` ENUM('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_human_interviews_tenant_id` (`tenant_id`),
  KEY `idx_human_interviews_application_id` (`application_id`),
  KEY `idx_human_interviews_status` (`status`),
  KEY `idx_human_interviews_scheduled_at` (`scheduled_at`),
  KEY `idx_human_interviews_created_by` (`created_by`),
  CONSTRAINT `fk_human_interviews_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_human_interviews_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_human_interviews_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `human_interview_attendees` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `human_interview_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role` ENUM('interviewer','observer') NOT NULL DEFAULT 'interviewer',
  `confirmed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_human_interview_attendees` (`human_interview_id`,`user_id`),
  KEY `idx_human_interview_attendees_user` (`user_id`),
  CONSTRAINT `fk_human_interview_attendees_interview` FOREIGN KEY (`human_interview_id`) REFERENCES `human_interviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_human_interview_attendees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `human_interview_evaluations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `human_interview_id` INT NOT NULL,
  `evaluator_id` INT NOT NULL,
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
  UNIQUE KEY `uq_human_interview_evaluations` (`human_interview_id`,`evaluator_id`),
  KEY `idx_human_interview_evaluations_evaluator` (`evaluator_id`),
  CONSTRAINT `fk_human_interview_evaluations_interview` FOREIGN KEY (`human_interview_id`) REFERENCES `human_interviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_human_interview_evaluations_evaluator` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OFFERS
-- ============================================================

CREATE TABLE IF NOT EXISTS `offers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `application_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `base_salary` DECIMAL(12,2) DEFAULT NULL,
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
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_offers_tenant_id` (`tenant_id`),
  KEY `idx_offers_application_id` (`application_id`),
  KEY `idx_offers_status` (`status`),
  KEY `idx_offers_created_by` (`created_by`),
  CONSTRAINT `fk_offers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_offers_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_offers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `offer_benefits` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `offer_id` INT NOT NULL,
  `benefit_name` VARCHAR(255) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_offer_benefits_offer` (`offer_id`),
  CONSTRAINT `fk_offer_benefits_offer` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TALENT POOL
-- ============================================================

CREATE TABLE IF NOT EXISTS `talent_pool_groups` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#4f46e5',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_talent_pool_groups_tenant_id` (`tenant_id`),
  KEY `idx_talent_pool_groups_created_by` (`created_by`),
  CONSTRAINT `fk_talent_pool_groups_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_talent_pool_groups_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `talent_pool_members` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `group_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `application_id` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `added_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_talent_pool_members` (`group_id`,`user_id`),
  KEY `idx_talent_pool_members_user_id` (`user_id`),
  KEY `idx_talent_pool_members_application` (`application_id`),
  KEY `idx_talent_pool_members_added_by` (`added_by`),
  CONSTRAINT `fk_talent_pool_members_group` FOREIGN KEY (`group_id`) REFERENCES `talent_pool_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_talent_pool_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_talent_pool_members_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_talent_pool_members_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `tenant_id` INT DEFAULT NULL,
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
  KEY `idx_notifications_user_id` (`user_id`),
  KEY `idx_notifications_tenant_id` (`tenant_id`),
  KEY `idx_notifications_is_read` (`is_read`),
  KEY `idx_notifications_type` (`type`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_templates` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT DEFAULT NULL,
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
  UNIQUE KEY `uq_notification_templates` (`tenant_id`,`slug`),
  CONSTRAINT `fk_notification_templates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `notification_type` VARCHAR(100) NOT NULL,
  `channel` ENUM('email','system','both','none') NOT NULL DEFAULT 'both',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification_settings` (`user_id`,`notification_type`),
  CONSTRAINT `fk_notification_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AI USAGE ANALYTICS
-- ============================================================

CREATE TABLE IF NOT EXISTS `ai_usage_logs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `feature` VARCHAR(100) NOT NULL,
  `provider` ENUM('openai','heygen') NOT NULL DEFAULT 'openai',
  `model` VARCHAR(100) DEFAULT NULL,
  `prompt_tokens` INT NOT NULL DEFAULT 0,
  `completion_tokens` INT NOT NULL DEFAULT 0,
  `total_tokens` INT NOT NULL DEFAULT 0,
  `cost_usd` DECIMAL(10,6) NOT NULL DEFAULT 0,
  `status` ENUM('success','error') NOT NULL DEFAULT 'success',
  `error_message` TEXT DEFAULT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT DEFAULT NULL,
  `duration_ms` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_usage_logs_tenant_id` (`tenant_id`),
  KEY `idx_ai_usage_logs_user_id` (`user_id`),
  KEY `idx_ai_usage_logs_feature` (`feature`),
  KEY `idx_ai_usage_logs_provider` (`provider`),
  KEY `idx_ai_usage_logs_created_at` (`created_at`),
  CONSTRAINT `fk_ai_usage_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_usage_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_usage_daily` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `provider` VARCHAR(50) NOT NULL,
  `total_requests` INT NOT NULL DEFAULT 0,
  `total_tokens` INT NOT NULL DEFAULT 0,
  `total_cost_usd` DECIMAL(10,4) NOT NULL DEFAULT 0,
  `successful_requests` INT NOT NULL DEFAULT 0,
  `failed_requests` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_usage_daily` (`tenant_id`,`date`,`provider`),
  CONSTRAINT `fk_ai_usage_daily_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- COMPARISONS
-- ============================================================

CREATE TABLE IF NOT EXISTS `candidate_comparisons` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_by` INT NOT NULL,
  `job_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_candidate_comparisons_tenant_id` (`tenant_id`),
  KEY `idx_candidate_comparisons_created_by` (`created_by`),
  KEY `idx_candidate_comparisons_job_id` (`job_id`),
  CONSTRAINT `fk_candidate_comparisons_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_candidate_comparisons_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_candidate_comparisons_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_comparison_items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `comparison_id` INT NOT NULL,
  `application_id` INT NOT NULL,
  `position` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_candidate_comparison_items` (`comparison_id`,`application_id`),
  KEY `idx_candidate_comparison_items_application` (`application_id`),
  CONSTRAINT `fk_candidate_comparison_items_comparison` FOREIGN KEY (`comparison_id`) REFERENCES `candidate_comparisons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_candidate_comparison_items_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PIPELINE
-- ============================================================

CREATE TABLE IF NOT EXISTS `pipeline_stage_transitions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `application_id` INT NOT NULL,
  `from_status` VARCHAR(50) DEFAULT NULL,
  `to_status` VARCHAR(50) NOT NULL,
  `triggered_by` ENUM('ai','manual','system') NOT NULL DEFAULT 'manual',
  `changed_by` INT DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pipeline_stage_transitions_tenant` (`tenant_id`),
  KEY `idx_pipeline_stage_transitions_application` (`application_id`),
  KEY `idx_pipeline_stage_transitions_changed_by` (`changed_by`),
  CONSTRAINT `fk_pipeline_stage_transitions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pipeline_stage_transitions_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pipeline_stage_transitions_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDIT
-- ============================================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(100) DEFAULT NULL,
  `entity_id` INT DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_tenant_id` (`tenant_id`),
  KEY `idx_audit_logs_user_id` (`user_id`),
  KEY `idx_audit_logs_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_logs_action` (`action`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SYSTEM OPERATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS `setup_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `step` VARCHAR(100) NOT NULL,
  `status` ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_setup_log_step` (`step`),
  KEY `idx_setup_log_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT DEFAULT NULL,
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
  KEY `idx_email_queue_tenant_id` (`tenant_id`),
  KEY `idx_email_queue_status` (`status`),
  KEY `idx_email_queue_scheduled_at` (`scheduled_at`),
  CONSTRAINT `fk_email_queue_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `file_uploads` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `file_path` TEXT NOT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `size_bytes` INT DEFAULT NULL,
  `disk` ENUM('local','s3') NOT NULL DEFAULT 'local',
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_file_uploads_tenant_id` (`tenant_id`),
  KEY `idx_file_uploads_user_id` (`user_id`),
  KEY `idx_file_uploads_reference` (`reference_type`,`reference_id`),
  CONSTRAINT `fk_file_uploads_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_file_uploads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SCORING
-- ============================================================

CREATE TABLE IF NOT EXISTS `scoring_rubrics` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `job_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scoring_rubrics_job_id` (`job_id`),
  KEY `idx_scoring_rubrics_created_by` (`created_by`),
  CONSTRAINT `fk_scoring_rubrics_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scoring_rubrics_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rubric_levels` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `rubric_id` INT NOT NULL,
  `score` INT NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rubric_levels_rubric_id` (`rubric_id`),
  CONSTRAINT `fk_rubric_levels_rubric` FOREIGN KEY (`rubric_id`) REFERENCES `scoring_rubrics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EXPORT / REPORTS
-- ============================================================

CREATE TABLE IF NOT EXISTS `export_jobs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `type` VARCHAR(100) NOT NULL,
  `filters` JSON DEFAULT NULL,
  `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `file_path` TEXT DEFAULT NULL,
  `row_count` INT DEFAULT NULL,
  `error` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_export_jobs_tenant_id` (`tenant_id`),
  KEY `idx_export_jobs_user_id` (`user_id`),
  KEY `idx_export_jobs_status` (`status`),
  CONSTRAINT `fk_export_jobs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_export_jobs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `saved_reports` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `type` VARCHAR(100) NOT NULL,
  `config` JSON DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_saved_reports_tenant_id` (`tenant_id`),
  KEY `idx_saved_reports_created_by` (`created_by`),
  CONSTRAINT `fk_saved_reports_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_saved_reports_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_schedules` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `report_id` INT NOT NULL,
  `frequency` ENUM('daily','weekly','monthly') NOT NULL,
  `next_run_at` TIMESTAMP NOT NULL,
  `last_run_at` TIMESTAMP NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_schedules_report_id` (`report_id`),
  KEY `idx_report_schedules_next_run_at` (`next_run_at`),
  CONSTRAINT `fk_report_schedules_report` FOREIGN KEY (`report_id`) REFERENCES `saved_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AI CONFIG
-- ============================================================

CREATE TABLE IF NOT EXISTS `ai_prompt_templates` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT DEFAULT NULL,
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
  UNIQUE KEY `uq_ai_prompt_templates` (`tenant_id`,`slug`),
  CONSTRAINT `fk_ai_prompt_templates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TAGS
-- ============================================================

CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#6b7280',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags` (`tenant_id`,`name`),
  KEY `idx_tags_created_by` (`created_by`),
  CONSTRAINT `fk_tags_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tags_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `taggables` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tag_id` INT NOT NULL,
  `taggable_type` VARCHAR(50) NOT NULL,
  `taggable_id` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_taggables` (`tag_id`,`taggable_type`,`taggable_id`),
  KEY `idx_taggables_polymorphic` (`taggable_type`,`taggable_id`),
  CONSTRAINT `fk_taggables_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TENANT SUBSCRIPTIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS `tenant_subscriptions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
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
  CONSTRAINT `fk_tenant_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_usage_stats` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `month_year` VARCHAR(10) NOT NULL,
  `total_jobs` INT NOT NULL DEFAULT 0,
  `total_applications` INT NOT NULL DEFAULT 0,
  `total_ai_interviews` INT NOT NULL DEFAULT 0,
  `total_hired` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_usage_stats` (`tenant_id`,`month_year`),
  CONSTRAINT `fk_tenant_usage_stats_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CALENDAR
-- ============================================================

CREATE TABLE IF NOT EXISTS `scheduled_events` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `type` ENUM('human_interview','meeting','deadline','other') NOT NULL DEFAULT 'meeting',
  `reference_id` INT DEFAULT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `start_at` TIMESTAMP NOT NULL,
  `end_at` TIMESTAMP NOT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scheduled_events_tenant_id` (`tenant_id`),
  KEY `idx_scheduled_events_start_at` (`start_at`),
  KEY `idx_scheduled_events_created_by` (`created_by`),
  KEY `idx_scheduled_events_reference` (`reference_type`,`reference_id`),
  CONSTRAINT `fk_scheduled_events_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scheduled_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INTERVIEW SLOTS
-- ============================================================

CREATE TABLE IF NOT EXISTS `interview_slots` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `start_at` TIMESTAMP NOT NULL,
  `end_at` TIMESTAMP NOT NULL,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_interview_slots_tenant_id` (`tenant_id`),
  KEY `idx_interview_slots_user_id` (`user_id`),
  KEY `idx_interview_slots_start_at` (`start_at`),
  KEY `idx_interview_slots_is_available` (`is_available`),
  CONSTRAINT `fk_interview_slots_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_interview_slots_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WEBHOOKS
-- ============================================================

CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `url` TEXT NOT NULL,
  `events` JSON DEFAULT NULL,
  `secret` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_triggered_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_webhooks_tenant_id` (`tenant_id`),
  KEY `idx_webhooks_is_active` (`is_active`),
  CONSTRAINT `fk_webhooks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_logs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `webhook_id` INT NOT NULL,
  `event` VARCHAR(100) NOT NULL,
  `payload` JSON DEFAULT NULL,
  `response_status` INT DEFAULT NULL,
  `response_body` TEXT DEFAULT NULL,
  `delivered_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_logs_webhook_id` (`webhook_id`),
  KEY `idx_webhook_logs_event` (`event`),
  CONSTRAINT `fk_webhook_logs_webhook` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ACTIVITY
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_activities` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `tenant_id` INT DEFAULT NULL,
  `activity_type` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_activities_user_id` (`user_id`),
  KEY `idx_user_activities_tenant_id` (`tenant_id`),
  KEY `idx_user_activities_type` (`activity_type`),
  KEY `idx_user_activities_created_at` (`created_at`),
  CONSTRAINT `fk_user_activities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_activities_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ASSESSMENT
-- ============================================================

CREATE TABLE IF NOT EXISTS `assessment_templates` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `questions` JSON DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assessment_templates_tenant_id` (`tenant_id`),
  KEY `idx_assessment_templates_created_by` (`created_by`),
  CONSTRAINT `fk_assessment_templates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assessment_templates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ADDITIONAL TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `job_question_imports` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `job_id` INT NOT NULL,
  `source_job_id` INT NOT NULL,
  `imported_count` INT NOT NULL DEFAULT 0,
  `imported_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_question_imports_job_id` (`job_id`),
  KEY `idx_job_question_imports_source_job` (`source_job_id`),
  KEY `idx_job_question_imports_imported_by` (`imported_by`),
  CONSTRAINT `fk_job_question_imports_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_question_imports_source_job` FOREIGN KEY (`source_job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_question_imports_imported_by` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_interview_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `interview_id` INT NOT NULL,
  `session_data` JSON DEFAULT NULL,
  `last_active_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_interview_sessions_interview` (`interview_id`),
  CONSTRAINT `fk_ai_interview_sessions_interview` FOREIGN KEY (`interview_id`) REFERENCES `ai_interviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `application_bulk_actions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `action_type` VARCHAR(100) NOT NULL,
  `application_ids` JSON NOT NULL,
  `performed_by` INT NOT NULL,
  `result` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_application_bulk_actions_tenant_id` (`tenant_id`),
  KEY `idx_application_bulk_actions_performed_by` (`performed_by`),
  CONSTRAINT `fk_application_bulk_actions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_application_bulk_actions_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_application_counts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `job_id` INT NOT NULL,
  `total` INT NOT NULL DEFAULT 0,
  `this_week` INT NOT NULL DEFAULT 0,
  `last_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_application_counts_job` (`job_id`),
  CONSTRAINT `fk_job_application_counts_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `candidate_match_scores` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `application_id` INT NOT NULL,
  `job_id` INT NOT NULL,
  `cv_match_score` DECIMAL(5,2) DEFAULT NULL,
  `ai_match_score` DECIMAL(5,2) DEFAULT NULL,
  `overall_match` DECIMAL(5,2) DEFAULT NULL,
  `computed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_candidate_match_scores_application` (`application_id`),
  KEY `idx_candidate_match_scores_job_id` (`job_id`),
  CONSTRAINT `fk_candidate_match_scores_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_candidate_match_scores_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_notifications` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT DEFAULT NULL,
  `target_type` ENUM('all','super_admin','tenant','user') NOT NULL DEFAULT 'all',
  `target_id` INT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_system_notifications_type` (`type`),
  KEY `idx_system_notifications_target` (`target_type`,`target_id`),
  KEY `idx_system_notifications_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interview_link_guest_info` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `link_id` INT NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `years_experience` DECIMAL(4,1) DEFAULT NULL,
  `expected_salary` DECIMAL(12,2) DEFAULT NULL,
  `cv_path` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_interview_link_guest_info_link` (`link_id`),
  CONSTRAINT `fk_interview_link_guest_info_link` FOREIGN KEY (`link_id`) REFERENCES `interview_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- Part 6: Initial Data Inserts
-- ============================================================

-- ============================================================
-- SYSTEM ROLES
-- ============================================================

INSERT INTO `roles` (`name`, `slug`, `description`, `is_system`, `tenant_id`) VALUES
('Super Admin',            'super_admin',           'Full platform access, manages all tenants and system configuration.',         1, NULL),
('HR Director',            'hr_director',           'Oversees all recruitment activities for a tenant.',                           1, NULL),
('HR Manager',             'hr_manager',            'Manages job postings, applications, and recruiters.',                         1, NULL),
('Recruiter',              'recruiter',             'Creates jobs, reviews applications, and schedules interviews.',               1, NULL),
('Technical Interviewer',  'technical_interviewer', 'Conducts technical evaluations and submits structured feedback.',             1, NULL),
('Department Manager',     'department_manager',    'Reviews candidates for their department and provides hiring recommendations.', 1, NULL),
('Operations Manager',     'operations_manager',    'Manages operational workflows and team coordination.',                        1, NULL),
('Executive Reviewer',     'executive_reviewer',    'Read-only view of pipeline and reports for executive oversight.',             1, NULL),
('Viewer',                 'viewer',                'Read-only access to assigned jobs and candidates.',                           1, NULL),
('Candidate',              'candidate',             'External applicant who applies for jobs and completes AI interviews.',        1, NULL);

-- ============================================================
-- PERMISSIONS (50+)
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `group_name`, `description`) VALUES
-- Tenant Management
('View Tenants',                'tenants.view',                  'tenants',      'View list and details of all tenants'),
('Create Tenants',              'tenants.create',                'tenants',      'Create new tenant accounts'),
('Update Tenants',              'tenants.update',                'tenants',      'Edit tenant details and settings'),
('Delete Tenants',              'tenants.delete',                'tenants',      'Delete or deactivate tenants'),
('Suspend Tenants',             'tenants.suspend',               'tenants',      'Suspend or reactivate tenant accounts'),
-- User Management
('View Users',                  'users.view',                    'users',        'View user list and profiles'),
('Create Users',                'users.create',                  'users',        'Invite or create new users'),
('Update Users',                'users.update',                  'users',        'Edit user profiles and settings'),
('Delete Users',                'users.delete',                  'users',        'Remove users from the system'),
('Manage User Roles',           'users.roles.manage',            'users',        'Assign or revoke roles for users'),
('Manage User Permissions',     'users.permissions.manage',      'users',        'Grant or revoke individual permissions'),
-- Roles & Permissions
('View Roles',                  'roles.view',                    'roles',        'View role definitions'),
('Manage Roles',                'roles.manage',                  'roles',        'Create, update, delete roles'),
('View Permissions',            'permissions.view',              'roles',        'View permission definitions'),
-- Jobs
('View Jobs',                   'jobs.view',                     'jobs',         'View job listings'),
('Create Jobs',                 'jobs.create',                   'jobs',         'Create new job postings'),
('Update Jobs',                 'jobs.update',                   'jobs',         'Edit existing job postings'),
('Delete Jobs',                 'jobs.delete',                   'jobs',         'Archive or delete job postings'),
('Publish Jobs',                'jobs.publish',                  'jobs',         'Publish jobs to career page'),
('Manage Job Settings',         'jobs.settings.manage',          'jobs',         'Configure interview mode, scoring, and criteria for jobs'),
('Manage Job Questions',        'jobs.questions.manage',         'jobs',         'Add, edit, delete interview questions for jobs'),
-- Applications
('View Applications',           'applications.view',             'applications', 'View all applications'),
('View Own Applications',       'applications.view.own',         'applications', 'View applications assigned to self'),
('Update Application Status',   'applications.status.update',    'applications', 'Move applications through pipeline stages'),
('Add Application Notes',       'applications.notes.add',        'applications', 'Add notes to applications'),
('View Private Notes',          'applications.notes.private',    'applications', 'View private recruiter notes'),
('Manage Application Labels',   'applications.labels.manage',    'applications', 'Add or remove labels on applications'),
('Bulk Action Applications',    'applications.bulk',             'applications', 'Perform bulk actions on applications'),
('Delete Applications',         'applications.delete',           'applications', 'Remove applications from the system'),
-- AI Interviews
('View AI Interviews',          'ai_interviews.view',            'interviews',   'View AI interview results and transcripts'),
('Send Interview Links',        'ai_interviews.links.send',      'interviews',   'Generate and send interview invitation links'),
('Review AI Analysis',          'ai_interviews.analysis.view',   'interviews',   'Access AI-generated scores and analysis reports'),
('Override AI Score',           'ai_interviews.score.override',  'interviews',   'Manually override AI-assigned scores'),
-- Human Interviews
('View Human Interviews',       'human_interviews.view',         'interviews',   'View scheduled human interviews'),
('Schedule Human Interviews',   'human_interviews.schedule',     'interviews',   'Schedule human interview sessions'),
('Submit Evaluation',           'human_interviews.evaluate',     'interviews',   'Submit evaluation forms after interviews'),
('View All Evaluations',        'human_interviews.eval.view_all','interviews',   'View evaluations submitted by other interviewers'),
-- Offers
('View Offers',                 'offers.view',                   'offers',       'View offer letters'),
('Create Offers',               'offers.create',                 'offers',       'Draft and create offer letters'),
('Send Offers',                 'offers.send',                   'offers',       'Send offer letters to candidates'),
('Revoke Offers',               'offers.revoke',                 'offers',       'Withdraw or revoke sent offers'),
-- Talent Pool
('View Talent Pool',            'talent_pool.view',              'talent_pool',  'View talent pool groups and members'),
('Manage Talent Pool',          'talent_pool.manage',            'talent_pool',  'Add, remove, and organize talent pool groups'),
-- Departments & Teams
('View Departments',            'departments.view',              'org',          'View department and team structure'),
('Manage Departments',          'departments.manage',            'org',          'Create, update, delete departments and teams'),
-- Reports & Analytics
('View Reports',                'reports.view',                  'analytics',    'View recruitment reports and dashboards'),
('Export Data',                 'reports.export',                'analytics',    'Export reports and data to CSV/Excel'),
('View AI Usage',               'analytics.ai_usage.view',       'analytics',    'View AI token and cost usage analytics'),
('Manage Report Schedules',     'reports.schedules.manage',      'analytics',    'Create and manage scheduled reports'),
-- AI & Platform Settings
('Manage AI Settings',          'settings.ai.manage',            'settings',     'Configure OpenAI and HeyGen API keys and models'),
('Manage Tenant Settings',      'settings.tenant.manage',        'settings',     'Update tenant general settings'),
('Manage Career Page',          'settings.career_page.manage',   'settings',     'Edit career page content and branding'),
('Manage Avatars',              'avatars.manage',                'settings',     'Create and configure interview avatars'),
('Manage Webhooks',             'webhooks.manage',               'settings',     'Configure webhook endpoints'),
('Manage Notification Settings','notifications.settings.manage', 'settings',     'Configure system notifications and templates'),
-- Audit
('View Audit Logs',             'audit.view',                    'audit',        'Access audit log records'),
-- Comparisons
('Compare Candidates',          'comparisons.manage',            'applications', 'Create and manage candidate comparison boards'),
-- System
('View System Settings',        'system.settings.view',          'system',       'View system-wide configuration'),
('Manage System Settings',      'system.settings.manage',        'system',       'Update system-wide configuration'),
('View Setup Log',              'system.setup_log.view',         'system',       'View system setup and operation logs'),
('Manage Prompt Templates',     'system.prompts.manage',         'system',       'Edit AI prompt templates');

-- ============================================================
-- ONBOARDING STEPS
-- ============================================================

INSERT INTO `onboarding_steps` (`slug`, `title`, `description`, `step_order`, `user_type`, `icon`) VALUES
-- Super Admin steps
('sa_welcome',            'Welcome to the Platform',      'Learn about your super admin capabilities and dashboard overview.',      1, 'super_admin', 'star'),
('sa_system_settings',    'Configure System Settings',    'Set up platform-wide defaults including email, storage, and AI limits.',  2, 'super_admin', 'settings'),
('sa_create_tenant',      'Create Your First Tenant',     'Onboard a company by creating their tenant account.',                    3, 'super_admin', 'building'),
('sa_manage_roles',       'Review Roles & Permissions',   'Understand built-in roles and how to manage access control.',            4, 'super_admin', 'shield'),
('sa_explore_analytics',  'Explore Analytics',            'Review platform-level usage, AI costs, and tenant health metrics.',      5, 'super_admin', 'chart'),
-- Company (HR/Recruiter) steps
('co_company_profile',    'Complete Company Profile',     'Add your company logo, description, and branding details.',              1, 'company',     'building'),
('co_ai_settings',        'Connect AI Services',          'Add your OpenAI and HeyGen API keys to enable AI-powered interviews.',   2, 'company',     'cpu'),
('co_career_page',        'Set Up Career Page',           'Customize your public career page with branding and job listings.',      3, 'company',     'layout'),
('co_invite_team',        'Invite Team Members',          'Add recruiters, managers, and interviewers to your workspace.',          4, 'company',     'users'),
('co_create_avatar',      'Create Interview Avatar',      'Configure an AI avatar to conduct video or voice interviews.',           5, 'company',     'user-circle'),
('co_post_first_job',     'Post Your First Job',          'Create a job listing and configure the AI interview settings.',          6, 'company',     'briefcase'),
('co_send_interview',     'Send Interview Invitations',   'Generate interview links and send them to your first candidates.',       7, 'company',     'send'),
('co_review_results',     'Review AI Interview Results',  'Explore scores, analysis, personality insights, and recommendations.',   8, 'company',     'clipboard-check'),
-- Candidate steps
('ca_profile',            'Complete Your Profile',        'Add your personal details, headline, and bio.',                         1, 'candidate',   'user'),
('ca_upload_cv',          'Upload Your CV',               'Upload your latest CV to enable AI-assisted screening.',                 2, 'candidate',   'upload'),
('ca_skills',             'Add Your Skills',              'List your skills and proficiency levels for better job matching.',       3, 'candidate',   'list'),
('ca_experience',         'Add Work Experience',          'Enter your professional background and achievements.',                   4, 'candidate',   'briefcase'),
('ca_education',          'Add Education',                'Add your academic qualifications and certifications.',                   5, 'candidate',   'academic-cap'),
('ca_preferences',        'Set Job Preferences',          'Configure salary expectations, location, and remote work preferences.',  6, 'candidate',   'sliders');

-- ============================================================
-- DEFAULT AI PROMPT TEMPLATES (tenant_id NULL = global defaults)
-- ============================================================

INSERT INTO `ai_prompt_templates` (`tenant_id`, `slug`, `name`, `system_prompt`, `user_prompt_template`, `model`, `max_tokens`, `temperature`, `is_active`) VALUES

-- 1. CV Analysis
(NULL, 'cv_analysis', 'CV Analysis',
'You are an expert HR analyst and talent acquisition specialist. Your task is to analyze a candidate\'s CV and evaluate it against a specific job description. Be objective, thorough, and structured in your analysis. Always respond with valid JSON only — no additional text, no markdown fences.',
'Analyze the following CV against the job description and return a JSON object with this exact structure:
{
  "match_score": <number 0-100>,
  "years_experience": <decimal number>,
  "education_level": "<highest qualification>",
  "skills_extracted": ["skill1", "skill2", ...],
  "companies_extracted": ["company1", "company2", ...],
  "strengths": ["strength1", "strength2", ...],
  "weaknesses": ["weakness1", "weakness2", ...],
  "notes": "<concise recruiter-facing summary>"
}

JOB TITLE: {{job_title}}
JOB REQUIREMENTS: {{job_requirements}}
JOB DESCRIPTION: {{job_description}}

CANDIDATE CV:
{{cv_text}}',
'gpt-4o', 1500, 0.30, 1),

-- 2. Interview Conductor
(NULL, 'interview_conductor', 'Interview Conductor',
'You are {{avatar_name}}, a professional AI interviewer conducting a structured job interview. Your tone is {{avatar_style}}. You are interviewing the candidate for the role of {{job_title}}. Ask one question at a time. Listen carefully to answers and ask relevant follow-up questions when appropriate. Keep the conversation professional, respectful, and focused. Do not reveal internal scores or assessment criteria. Conduct the interview in {{language}}.',
'The candidate has just responded with the following message. Determine whether you should ask a follow-up question to probe deeper, or move to the next interview question. Maintain conversational flow.

INTERVIEW CONTEXT:
- Job Title: {{job_title}}
- Question Number: {{question_number}} of {{max_questions}}
- Category: {{current_category}}
- Time Remaining: {{time_remaining}} minutes

CONVERSATION HISTORY:
{{conversation_history}}

CANDIDATE RESPONSE: {{candidate_message}}

Respond naturally as the interviewer. If this is the final question, thank the candidate professionally and close the interview.',
'gpt-4o', 500, 0.70, 1),

-- 3. Skill Scorer
(NULL, 'skill_scorer', 'Skill Scorer',
'You are an expert HR evaluation AI. Analyze interview transcripts and score candidates across defined competency dimensions. Be objective and evidence-based. Base every score strictly on what the candidate demonstrated in the interview. Always respond with valid JSON only.',
'Analyze the interview transcript below and return a JSON object with scores for each competency (scale 0.00 to 100.00):
{
  "technical_competency": <score>,
  "communication": <score>,
  "problem_solving": <score>,
  "critical_thinking": <score>,
  "confidence": <score>,
  "leadership": <score>,
  "culture_fit": <score>,
  "professionalism": <score>,
  "ai_knowledge": <score>,
  "english_proficiency": <score>,
  "learning_ability": <score>,
  "overall_score": <weighted average>,
  "confidence_level": <how confident the AI is in these scores 0-100>
}

JOB TITLE: {{job_title}}
JOB REQUIREMENTS: {{job_requirements}}

INTERVIEW TRANSCRIPT:
{{interview_transcript}}',
'gpt-4o', 1000, 0.20, 1),

-- 4. Personality Analyst
(NULL, 'personality_analyst', 'Personality Analyst',
'You are an organizational psychologist AI specializing in behavioral analysis and personality assessment. Analyze interview transcripts to identify personality traits using established frameworks. Be evidence-based and nuanced. Respond with valid JSON only.',
'Analyze the following interview transcript and return a JSON object with personality scores:
{
  "disc_d": <Dominance score 0-100>,
  "disc_i": <Influence score 0-100>,
  "disc_s": <Steadiness score 0-100>,
  "disc_c": <Conscientiousness score 0-100>,
  "big5_openness": <score 0-100>,
  "big5_conscientiousness": <score 0-100>,
  "big5_extraversion": <score 0-100>,
  "big5_agreeableness": <score 0-100>,
  "big5_neuroticism": <score 0-100>,
  "growth_score": <growth mindset indicator 0-100>,
  "pressure_score": <performance under pressure score 0-100>,
  "leadership_style": "<autocratic|democratic|laissez_faire|transformational|servant|coaching>",
  "summary": "<2-3 sentence personality summary suitable for a hiring manager>"
}

JOB TITLE: {{job_title}}

INTERVIEW TRANSCRIPT:
{{interview_transcript}}',
'gpt-4o', 1200, 0.30, 1),

-- 5. Red Flag Detector
(NULL, 'red_flag_detector', 'Red Flag Detector',
'You are a senior HR risk assessment AI. Your role is to identify potential concerns or red flags in candidate interviews. Be fair, evidence-based, and avoid bias. Only flag genuine concerns backed by specific evidence from the transcript. Respond with valid JSON only.',
'Review the following interview transcript and identify any red flags. Return a JSON array:
[
  {
    "severity": "<high|medium|low>",
    "category": "<honesty|attitude|professionalism|competency|stability|culture|communication|ethics>",
    "description": "<clear description of the concern>",
    "evidence": "<direct quote or specific reference from the transcript>"
  }
]

Return an empty array [] if no red flags are detected.

JOB TITLE: {{job_title}}
SENIORITY LEVEL: {{seniority}}

INTERVIEW TRANSCRIPT:
{{interview_transcript}}',
'gpt-4o', 1000, 0.20, 1),

-- 6. Recommendation Generator
(NULL, 'recommendation_generator', 'Recommendation Generator',
'You are a senior talent acquisition AI advisor. Your role is to synthesize all available candidate data — CV analysis, interview performance, skill scores, personality profile, and red flags — into a final hiring recommendation. Be balanced, evidence-based, and actionable. Respond with valid JSON only.',
'Based on all the data below, generate a final hiring recommendation as a JSON object:
{
  "final_score": <weighted overall score 0-100>,
  "recommendation": "<strong_yes|yes|maybe|no>",
  "executive_summary": "<3-5 sentence executive summary for the hiring manager>",
  "strengths": ["strength1", "strength2", "strength3"],
  "weaknesses": ["weakness1", "weakness2"],
  "hiring_risks": "<paragraph describing key risks if hired, or null if none>"
}

JOB TITLE: {{job_title}}
SENIORITY: {{seniority}}
PASSING SCORE: {{passing_score}}
AUTO QUALIFY SCORE: {{auto_qualify_score}}

CV MATCH SCORE: {{cv_match_score}}
OVERALL INTERVIEW SCORE: {{overall_score}}
RECOMMENDATION FROM SCORING: {{skill_recommendation}}

SKILL SCORES SUMMARY:
{{skill_scores_json}}

PERSONALITY SUMMARY:
{{personality_summary}}

RED FLAGS:
{{red_flags_json}}

KEY STRENGTHS FROM CV:
{{cv_strengths}}',
'gpt-4o', 1500, 0.40, 1);

-- ============================================================
-- DEFAULT SYSTEM SETTINGS
-- ============================================================

INSERT INTO `system_settings` (`key`, `value`, `group_name`) VALUES
-- General
('app_name',                        'AI Recruit',           'general'),
('app_url',                         'https://app.airecruit.io', 'general'),
('app_logo_url',                    '',                     'general'),
('app_timezone',                    'UTC',                  'general'),
('app_default_language',            'en',                   'general'),
('app_supported_languages',         '["en","ar"]',          'general'),
('maintenance_mode',                '0',                    'general'),
('maintenance_message',             'The platform is currently under scheduled maintenance. We will be back shortly.', 'general'),
-- Email
('mail_driver',                     'smtp',                 'email'),
('mail_host',                       'smtp.mailgun.org',     'email'),
('mail_port',                       '587',                  'email'),
('mail_encryption',                 'tls',                  'email'),
('mail_from_address',               'no-reply@airecruit.io','email'),
('mail_from_name',                  'AI Recruit',           'email'),
-- Security
('session_lifetime_minutes',        '120',                  'security'),
('max_login_attempts',              '5',                    'security'),
('login_lockout_minutes',           '15',                   'security'),
('password_min_length',             '8',                    'security'),
('password_require_uppercase',      '1',                    'security'),
('password_require_number',         '1',                    'security'),
('password_require_symbol',         '0',                    'security'),
('password_reset_expiry_minutes',   '60',                   'security'),
-- File Upload
('max_file_size_mb',                '20',                   'uploads'),
('allowed_cv_types',                '["pdf","doc","docx"]', 'uploads'),
('allowed_image_types',             '["jpg","jpeg","png","webp"]', 'uploads'),
('default_storage_disk',            'local',                'uploads'),
-- AI Platform Defaults
('default_ai_model',                'gpt-4o',               'ai'),
('default_interview_mode',          'text',                 'ai'),
('default_max_questions',           '12',                   'ai'),
('default_time_limit_minutes',      '20',                   'ai'),
('default_passing_score',           '68',                   'ai'),
('default_auto_qualify_score',      '82',                   'ai'),
('default_auto_disqualify_score',   '50',                   'ai'),
('default_link_expiry_days',        '14',                   'ai'),
('ai_cost_alert_threshold_usd',     '100',                  'ai'),
-- Subscription Defaults
('starter_max_jobs',                '5',                    'subscriptions'),
('starter_max_users',               '3',                    'subscriptions'),
('starter_max_ai_interviews',       '100',                  'subscriptions'),
('pro_max_jobs',                    '50',                   'subscriptions'),
('pro_max_users',                   '15',                   'subscriptions'),
('pro_max_ai_interviews',           '1000',                 'subscriptions'),
('enterprise_max_jobs',             '-1',                   'subscriptions'),
('enterprise_max_users',            '-1',                   'subscriptions'),
('enterprise_max_ai_interviews',    '-1',                   'subscriptions'),
-- Registration
('allow_public_registration',       '0',                    'registration'),
('require_email_verification',      '1',                    'registration'),
('default_trial_days',              '14',                   'registration'),
-- Career Page
('career_page_enabled',             '1',                    'career_page'),
('career_page_base_url',            'https://careers.airecruit.io', 'career_page'),
-- Notifications
('enable_email_notifications',      '1',                    'notifications'),
('enable_system_notifications',     '1',                    'notifications'),
-- Versioning
('schema_version',                  '1.0.0',                'system'),
('last_migration_at',               NOW(),                  'system');
