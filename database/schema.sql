-- AI Recruitment SaaS Platform - MySQL 8 Schema
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS tenants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  subdomain VARCHAR(120) NULL,
  domain VARCHAR(255) NULL,
  plan VARCHAR(50) NOT NULL DEFAULT 'starter',
  status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  -- Per-tenant API keys (AES-256-CBC encrypted at rest via ApiKeyManager)
  openai_api_key VARCHAR(600) NULL COMMENT 'Encrypted OpenAI API key for this company',
  heygen_api_key VARCHAR(600) NULL COMMENT 'Encrypted HeyGen API key for this company',
  openai_model VARCHAR(100) NULL DEFAULT 'gpt-4o' COMMENT 'OpenAI model preference per company',
  settings JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tenants_slug (slug),
  KEY idx_tenants_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(120) NULL,
  last_name VARCHAR(120) NULL,
  full_name VARCHAR(241) GENERATED ALWAYS AS (TRIM(CONCAT(COALESCE(first_name,''), IF(first_name IS NOT NULL AND last_name IS NOT NULL,' ',''), COALESCE(last_name,'')))) VIRTUAL,
  avatar_url VARCHAR(500) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
  email_verified_at TIMESTAMP NULL,
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_email_tenant (tenant_id, email),
  KEY idx_users_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  display_name VARCHAR(180) NULL,
  description TEXT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_roles_tenant (tenant_id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  slug VARCHAR(150) NOT NULL UNIQUE,
  display_name VARCHAR(180) NULL,
  module VARCHAR(120) NULL,
  description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description LONGTEXT NULL,
  requirements LONGTEXT NULL,
  department VARCHAR(150) NULL,
  location VARCHAR(180) NULL,
  job_type ENUM('full-time','part-time','contract','remote','internship') NOT NULL DEFAULT 'full-time',
  salary_min DECIMAL(12,2) NULL,
  salary_max DECIMAL(12,2) NULL,
  currency VARCHAR(10) DEFAULT 'USD',
  status ENUM('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
  experience_level VARCHAR(60) NULL,
  salary_currency VARCHAR(10) DEFAULT 'USD',
  show_salary TINYINT(1) NOT NULL DEFAULT 1,
  benefits JSON NULL,
  interview_type VARCHAR(60) DEFAULT 'ai_text',
  interview_process VARCHAR(60) DEFAULT 'ai_text',
  interview_duration INT NULL,
  max_questions INT NULL DEFAULT 10,
  time_limit_minutes INT NULL DEFAULT 30,
  application_deadline DATE NULL,
  max_applications INT NULL,
  require_cv TINYINT(1) NOT NULL DEFAULT 1,
  require_cover_letter TINYINT(1) NOT NULL DEFAULT 0,
  auto_reject_threshold INT NOT NULL DEFAULT 0,
  ai_criteria JSON NULL,
  question_bank JSON NULL,
  avatar_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  published_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_jobs_tenant (tenant_id),
  KEY idx_jobs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS job_criteria (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id BIGINT UNSIGNED NOT NULL,
  criterion_name VARCHAR(180) NOT NULL,
  weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  description TEXT NULL,
  KEY idx_jobcrit_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS avatars (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  heygen_avatar_id VARCHAR(180) NOT NULL,
  name VARCHAR(180) NULL,
  preview_url VARCHAR(500) NULL,
  voice_id VARCHAR(180) NULL,
  language VARCHAR(40) DEFAULT 'en',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_avatars_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  first_name VARCHAR(120) NULL,
  last_name VARCHAR(120) NULL,
  full_name VARCHAR(241) GENERATED ALWAYS AS (TRIM(CONCAT(COALESCE(first_name,''), IF(first_name IS NOT NULL AND last_name IS NOT NULL,' ',''), COALESCE(last_name,'')))) VIRTUAL,
  phone VARCHAR(60) NULL,
  location VARCHAR(180) NULL,
  years_experience TINYINT UNSIGNED NULL,
  expected_salary DECIMAL(12,2) NULL,
  salary_currency VARCHAR(10) DEFAULT 'USD',
  skills JSON NULL,
  avg_skill_score DECIMAL(5,2) NULL,
  avg_match_score DECIMAL(5,2) NULL,
  cv_url VARCHAR(500) NULL,
  cv_text LONGTEXT NULL,
  linkedin_url VARCHAR(500) NULL,
  status VARCHAR(60) NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_candidates_tenant (tenant_id),
  KEY idx_candidates_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  candidate_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(60) NOT NULL DEFAULT 'applied',
  stage VARCHAR(60) NOT NULL DEFAULT 'applied',
  current_stage VARCHAR(60) NOT NULL DEFAULT 'applied',
  pipeline_stage VARCHAR(60) NOT NULL DEFAULT 'applied',
  ai_match_score DECIMAL(5,2) NULL,
  final_score DECIMAL(5,2) NULL,
  ai_recommendation VARCHAR(60) NULL,
  cv_match_score DECIMAL(5,2) NULL,
  cv_analysis LONGTEXT NULL,
  hr_decision VARCHAR(60) NULL,
  hr_notes TEXT NULL,
  interview_link_token VARCHAR(80) NULL,
  interview_link_expires_at TIMESTAMP NULL,
  interview_id BIGINT UNSIGNED NULL,
  interview_link_used TINYINT(1) NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_app_tenant (tenant_id),
  KEY idx_app_job (job_id),
  KEY idx_app_candidate (candidate_id),
  KEY idx_app_stage (pipeline_stage),
  KEY idx_app_current_stage (current_stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS interviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL DEFAULT 'ai_text',
  status ENUM('pending','in_progress','completed','expired') NOT NULL DEFAULT 'pending',
  token VARCHAR(80) NOT NULL UNIQUE,
  questions_count INT NOT NULL DEFAULT 0,
  language_detected VARCHAR(20) NULL,
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  duration_seconds INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_interview_app (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS interview_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  interview_id BIGINT UNSIGNED NOT NULL,
  role ENUM('ai','candidate') NOT NULL,
  content LONGTEXT NOT NULL,
  message_index INT NOT NULL DEFAULT 0,
  is_question TINYINT(1) NOT NULL DEFAULT 0,
  is_followup TINYINT(1) NOT NULL DEFAULT 0,
  skill_assessed VARCHAR(120) NULL,
  audio_url VARCHAR(500) NULL,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_msg_interview (interview_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS interview_evaluations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  interview_id BIGINT UNSIGNED NOT NULL,
  application_id BIGINT UNSIGNED NULL,
  overall_score DECIMAL(5,2) NULL,
  recommendation ENUM('hire','maybe','reject') NULL,
  summary LONGTEXT NULL,
  executive_summary LONGTEXT NULL,
  strengths JSON NULL,
  weaknesses JSON NULL,
  skills_analysis JSON NULL,
  personality_analysis JSON NULL,
  disc_profile JSON NULL,
  big_five JSON NULL,
  red_flags JSON NULL,
  cv_analysis JSON NULL,
  criteria_scores JSON NULL,
  language_proficiency JSON NULL,
  ai_tokens_used INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_eval_interview (interview_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS skill_scores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  evaluation_id BIGINT UNSIGNED NOT NULL,
  skill_name VARCHAR(120) NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  notes TEXT NULL,
  KEY idx_skill_eval (evaluation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS personality_analysis (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  evaluation_id BIGINT UNSIGNED NOT NULL,
  disc_d DECIMAL(5,2) NULL, disc_i DECIMAL(5,2) NULL, disc_s DECIMAL(5,2) NULL, disc_c DECIMAL(5,2) NULL,
  big5_openness DECIMAL(5,2) NULL, big5_conscientiousness DECIMAL(5,2) NULL, big5_extraversion DECIMAL(5,2) NULL,
  big5_agreeableness DECIMAL(5,2) NULL, big5_neuroticism DECIMAL(5,2) NULL,
  analysis_notes TEXT NULL,
  KEY idx_pers_eval (evaluation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS red_flags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  evaluation_id BIGINT UNSIGNED NOT NULL,
  flag_type VARCHAR(120) NOT NULL,
  description TEXT NULL,
  severity ENUM('low','medium','high') NOT NULL DEFAULT 'low',
  KEY idx_flag_eval (evaluation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS human_interviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  application_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(60) NULL DEFAULT 'technical',
  scheduled_at TIMESTAMP NULL,
  duration_minutes INT NULL DEFAULT 60,
  location VARCHAR(255) NULL,
  meeting_link VARCHAR(500) NULL,
  meeting_platform VARCHAR(60) NULL,
  notes TEXT NULL,
  status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  created_by BIGINT UNSIGNED NULL,
  KEY idx_hint_app (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS human_interview_evaluators (
  human_interview_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (human_interview_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS offers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  salary DECIMAL(12,2) NULL,
  currency VARCHAR(10) DEFAULT 'USD',
  start_date DATE NULL,
  expiry_date DATE NULL,
  status ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
  token VARCHAR(80) NULL UNIQUE,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_offer_app (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS talent_pools (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pool_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS talent_pool_candidates (
  pool_id BIGINT UNSIGNED NOT NULL,
  candidate_id BIGINT UNSIGNED NOT NULL,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (pool_id, candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_usage_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  feature VARCHAR(120) NULL,
  model VARCHAR(120) NULL,
  tokens_used INT NOT NULL DEFAULT 0,
  prompt_tokens INT NOT NULL DEFAULT 0,
  completion_tokens INT NOT NULL DEFAULT 0,
  total_tokens INT NOT NULL DEFAULT 0,
  cost DECIMAL(12,6) NOT NULL DEFAULT 0,
  cost_usd DECIMAL(12,6) NOT NULL DEFAULT 0,
  reference_type VARCHAR(80) NULL,
  reference_id VARCHAR(120) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_usage_tenant (tenant_id),
  KEY idx_usage_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(150) NULL,
  resource_type VARCHAR(120) NULL,
  resource_id VARCHAR(120) NULL,
  entity_type VARCHAR(120) NULL,
  entity_id VARCHAR(120) NULL,
  details JSON NULL,
  meta JSON NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(80) NULL,
  title VARCHAR(255) NULL,
  message TEXT NULL,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS interview_feedback (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  interview_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT NULL,
  comments TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_feedback_interview (interview_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS career_page_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL UNIQUE,
  company_name VARCHAR(255) NULL,
  logo_url VARCHAR(500) NULL,
  banner_url VARCHAR(500) NULL,
  primary_color VARCHAR(20) DEFAULT '#7C3AED',
  description LONGTEXT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  setting_key VARCHAR(180) NOT NULL,
  setting_value LONGTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_settings_key_tenant (tenant_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidate_cvs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id BIGINT UNSIGNED NOT NULL,
  file_url VARCHAR(500) NULL,
  file_name VARCHAR(255) NULL,
  extracted_text LONGTEXT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cv_candidate (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
