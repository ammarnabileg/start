SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ─── TENANTS ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  status ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
  subscription_plan VARCHAR(60) NOT NULL DEFAULT 'trial',
  subscription_expires_at DATETIME NULL,
  max_jobs INT NOT NULL DEFAULT 10,
  max_users INT NOT NULL DEFAULT 5,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── USERS ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── ROLES & PERMISSIONS ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  description TEXT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(150) NOT NULL UNIQUE,
  module VARCHAR(80) NOT NULL,
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
  PRIMARY KEY (user_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── CANDIDATES ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS candidates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(30) NULL,
  years_experience DECIMAL(4,1) NULL DEFAULT 0,
  salary_expectation DECIMAL(12,2) NULL,
  salary_currency VARCHAR(10) NULL DEFAULT 'USD',
  cv_url VARCHAR(500) NULL,
  cv_text LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── JOBS ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  avatar_id BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  department VARCHAR(150) NULL,
  description LONGTEXT NULL,
  requirements LONGTEXT NULL,
  seniority ENUM('intern','junior','mid','senior','lead','manager','director','executive') NOT NULL DEFAULT 'mid',
  location VARCHAR(200) NULL,
  work_type ENUM('remote','hybrid','onsite') NOT NULL DEFAULT 'hybrid',
  salary_min DECIMAL(12,2) NULL,
  salary_max DECIMAL(12,2) NULL,
  salary_currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  status ENUM('active','draft','archived') NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── JOB CRITERIA ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS job_criteria (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id BIGINT UNSIGNED NOT NULL,
  criterion VARCHAR(255) NOT NULL,
  weight TINYINT NOT NULL DEFAULT 5 COMMENT '1-10 weight',
  description TEXT NULL,
  sort_order TINYINT NOT NULL DEFAULT 0,
  INDEX idx_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── JOB QUESTIONS ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS job_questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id BIGINT UNSIGNED NOT NULL,
  question TEXT NOT NULL,
  skill VARCHAR(100) NULL,
  difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
  sort_order TINYINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── AVATARS ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS avatars (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  gender ENUM('male','female','neutral') NOT NULL DEFAULT 'neutral',
  personality TEXT NULL COMMENT 'Description of personality/style',
  language ENUM('en','ar','both') NOT NULL DEFAULT 'both',
  image_url VARCHAR(500) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── APPLICATIONS ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  candidate_id BIGINT UNSIGNED NOT NULL,
  current_stage ENUM('applied','ai_screening','qualified','disqualified','tech_interview','manager_interview','final_review','offer','hired','rejected','withdrawn') NOT NULL DEFAULT 'applied',
  ai_score TINYINT UNSIGNED NULL COMMENT '0-100',
  ai_recommendation ENUM('strong_yes','yes','maybe','no') NULL,
  notes TEXT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_job_candidate (job_id, candidate_id),
  INDEX idx_tenant (tenant_id),
  INDEX idx_stage (current_stage),
  INDEX idx_candidate (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── INTERVIEW LINKS ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS interview_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(80) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token),
  INDEX idx_application (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── AI INTERVIEWS ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ai_interviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  link_id BIGINT UNSIGNED NULL,
  status ENUM('pending','in_progress','completed','evaluated') NOT NULL DEFAULT 'pending',
  transcript JSON NULL COMMENT 'Array of {role,content,timestamp}',
  ai_score TINYINT UNSIGNED NULL,
  ai_recommendation ENUM('strong_yes','yes','maybe','no') NULL,
  skills_scores JSON NULL COMMENT '{skill_name: score}',
  behavioral_analysis JSON NULL COMMENT '{disc, big_five, ...}',
  red_flags JSON NULL COMMENT '[{severity, description, evidence}]',
  cv_match_score TINYINT UNSIGNED NULL,
  cv_analysis JSON NULL,
  summary TEXT NULL,
  strengths JSON NULL,
  weaknesses JSON NULL,
  question_count TINYINT NOT NULL DEFAULT 0,
  duration_seconds INT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  evaluated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_application (application_id),
  INDEX idx_tenant (tenant_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── AI USAGE LOGS ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ai_usage_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  feature VARCHAR(80) NOT NULL COMMENT 'cv_analysis, interview, evaluation, copilot, etc.',
  model VARCHAR(80) NOT NULL DEFAULT 'gpt-4o-mini',
  prompt_tokens INT NOT NULL DEFAULT 0,
  completion_tokens INT NOT NULL DEFAULT 0,
  total_tokens INT NOT NULL DEFAULT 0,
  cost DECIMAL(10,6) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_feature (feature),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── HUMAN INTERVIEWS ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS human_interviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  interview_date DATETIME NOT NULL,
  meeting_link VARCHAR(500) NULL,
  notes TEXT NULL,
  status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_application (application_id),
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS human_interview_evaluators (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  interview_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  INDEX idx_interview (interview_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS human_interview_evaluations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  interview_id BIGINT UNSIGNED NOT NULL,
  evaluator_id BIGINT UNSIGNED NOT NULL,
  technical_depth TINYINT NULL COMMENT '1-5',
  problem_solving TINYINT NULL COMMENT '1-5',
  communication TINYINT NULL COMMENT '1-5',
  culture_fit TINYINT NULL COMMENT '1-5',
  takes_ownership TINYINT NULL COMMENT '1-5',
  seniority_fit TINYINT NULL COMMENT '1-5',
  strengths TEXT NULL,
  weaknesses TEXT NULL,
  overall_rating TINYINT NULL COMMENT '1-5',
  recommendation ENUM('strong_yes','yes','maybe','no') NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_interview_evaluator (interview_id, evaluator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── OFFERS ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS offers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  application_id BIGINT UNSIGNED NOT NULL,
  candidate_id BIGINT UNSIGNED NOT NULL,
  job_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  salary_amount DECIMAL(12,2) NULL,
  salary_currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  salary_type ENUM('monthly','annual','hourly') NOT NULL DEFAULT 'monthly',
  start_date DATE NULL,
  expiry_date DATE NULL,
  benefits TEXT NULL,
  conditions TEXT NULL,
  offer_letter LONGTEXT NULL,
  status ENUM('draft','sent','accepted','rejected','negotiating','withdrawn') NOT NULL DEFAULT 'draft',
  sent_at DATETIME NULL,
  responded_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_candidate (candidate_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── TALENT POOL ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS talent_pool_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS talent_pool_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id BIGINT UNSIGNED NOT NULL,
  candidate_id BIGINT UNSIGNED NOT NULL,
  notes TEXT NULL,
  added_by BIGINT UNSIGNED NULL,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_group_candidate (group_id, candidate_id),
  INDEX idx_group (group_id),
  INDEX idx_candidate (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── NOTIFICATIONS ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NULL,
  type VARCHAR(60) NOT NULL DEFAULT 'info',
  url VARCHAR(500) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_read (read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── SYSTEM SETTINGS ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  setting_key VARCHAR(120) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_key (tenant_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── INTERVIEW FEEDBACK ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS interview_feedback (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ai_interview_id BIGINT UNSIGNED NOT NULL UNIQUE,
  overall_experience TINYINT NULL COMMENT '1-5',
  question_quality TINYINT NULL COMMENT '1-5',
  ease_of_use TINYINT NULL COMMENT '1-5',
  comment TEXT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── AUDIT LOGS ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  resource_type VARCHAR(80) NOT NULL,
  resource_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  details JSON NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant (tenant_id),
  INDEX idx_resource (resource_type, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;

-- ─── SEED: PERMISSIONS ────────────────────────────────────────────────────────
INSERT IGNORE INTO permissions (name, slug, module) VALUES
('View Jobs','jobs.view','jobs'),
('Create Jobs','jobs.create','jobs'),
('Edit Jobs','jobs.edit','jobs'),
('Archive Jobs','jobs.archive','jobs'),
('View Candidates','candidates.view','candidates'),
('Edit Candidates','candidates.edit','candidates'),
('View Applications','applications.view','applications'),
('Manage Applications','applications.manage','applications'),
('Move Stage','applications.stage_change','applications'),
('View AI Interviews','interviews.view','interviews'),
('Manage AI Interviews','interviews.manage','interviews'),
('View Pipeline','pipeline.view','pipeline'),
('Manage Pipeline','pipeline.manage','pipeline'),
('Schedule Human Interviews','human_interviews.schedule','human_interviews'),
('Evaluate Interviews','interviews.evaluate','human_interviews'),
('View Offers','offers.view','offers'),
('Create Offers','offers.create','offers'),
('Send Offers','offers.send','offers'),
('Manage Offers','offers.manage','offers'),
('View Talent Pool','talent_pool.view','talent_pool'),
('Manage Talent Pool','talent_pool.manage','talent_pool'),
('View Users','users.view','users'),
('Create Users','users.create','users'),
('Edit Users','users.edit','users'),
('View Roles','roles.view','roles'),
('Manage Roles','roles.manage','roles'),
('View AI Analytics','ai.analytics','ai'),
('Use AI Features','ai.use','ai'),
('View Settings','settings.view','settings'),
('Manage Settings','settings.manage','settings'),
('Manage Avatars','avatars.manage','avatars'),
('View Reports','reports.view','reports'),
('Export Data','reports.export','reports');

-- ─── SEED: SYSTEM ROLES ───────────────────────────────────────────────────────
INSERT IGNORE INTO roles (id, tenant_id, name, slug, description, is_system) VALUES
(1, NULL, 'Super Admin',          'super_admin',          'Full system access', 1),
(2, NULL, 'Company Owner',        'company_owner',        'Full access within company', 1),
(3, NULL, 'HR Director',          'hr_director',          'Full HR access', 1),
(4, NULL, 'HR Manager',           'hr_manager',           'Manage hiring process', 1),
(5, NULL, 'Recruiter',            'recruiter',            'Source and screen candidates', 1),
(6, NULL, 'Technical Interviewer','technical_interviewer','Evaluate technical skills', 1),
(7, NULL, 'Department Manager',   'department_manager',   'View and evaluate for their dept', 1),
(8, NULL, 'Operations Manager',   'operations_manager',   'View pipeline and reports', 1),
(9, NULL, 'Executive Reviewer',   'executive_reviewer',   'Read-only executive view', 1),
(10,NULL, 'Viewer',               'viewer',               'Read-only access', 1),
(11,NULL, 'Candidate',            'candidate',            'Job applicant portal', 1);

-- ─── SEED: ROLE PERMISSIONS ───────────────────────────────────────────────────
-- company_owner: ALL permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions;

-- hr_director: all except settings.manage
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE slug != 'settings.manage';

-- hr_manager: broad access
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE slug IN (
  'jobs.view','jobs.create','jobs.edit','jobs.archive',
  'candidates.view','candidates.edit',
  'applications.view','applications.manage','applications.stage_change',
  'interviews.view','interviews.manage',
  'pipeline.view','pipeline.manage',
  'human_interviews.schedule','interviews.evaluate',
  'offers.view','offers.create','offers.send','offers.manage',
  'talent_pool.view','talent_pool.manage',
  'users.view','roles.view',
  'ai.analytics','ai.use',
  'settings.view','avatars.manage',
  'reports.view','reports.export'
);

-- recruiter
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE slug IN (
  'jobs.view','candidates.view','candidates.edit',
  'applications.view','applications.manage','applications.stage_change',
  'interviews.view','interviews.manage',
  'pipeline.view','pipeline.manage',
  'human_interviews.schedule',
  'talent_pool.view','offers.view',
  'ai.use','reports.view'
);

-- technical_interviewer
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE slug IN (
  'candidates.view','applications.view',
  'interviews.view','interviews.evaluate',
  'pipeline.view','ai.use'
);

-- department_manager
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions WHERE slug IN (
  'candidates.view','applications.view',
  'interviews.view','interviews.evaluate',
  'human_interviews.schedule',
  'pipeline.view','reports.view'
);

-- operations_manager
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions WHERE slug IN (
  'candidates.view','applications.view',
  'pipeline.view','offers.view',
  'talent_pool.view','ai.analytics','reports.view'
);

-- executive_reviewer + viewer: read-only
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug IN ('executive_reviewer','viewer')
  AND p.slug IN ('candidates.view','applications.view','pipeline.view','offers.view','reports.view');
