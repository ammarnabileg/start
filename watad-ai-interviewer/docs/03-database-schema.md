# 03 — Database Schema

MySQL 8 / InnoDB / `utf8mb4_unicode_ci`. All tables carry `created_at` / `updated_at` unless
noted. Soft deletes (`deleted_at`) on candidate-facing and PII tables for GDPR-controlled erasure.
Money stored as `DECIMAL`, currency as ISO‑4217 char(3). All foreign keys are indexed.

> Naming note: the open-position table is `job_positions` (not `jobs`) to avoid colliding with
> Laravel's queue `jobs` table. The Eloquent model is `JobPosition`.

## Table groups

1. Identity & RBAC — `users`, `roles`, `permissions`, `permission_role`, `role_user`
2. Org & hiring — `departments`, `job_positions`, `hiring_pipelines`, `pipeline_stages`
3. Interview config — `avatars`, `interview_templates`, `template_competencies`, `question_libraries`, `questions`
4. Candidate & invitation — `candidates`, `candidate_pipeline`, `interview_invitations`
5. Interview runtime — `interviews`, `interview_messages`, `interview_events`, `recordings`
6. AI outputs — `cv_analyses`, `competency_scores`, `behavioral_analyses`, `red_flags`, `video_analyses`, `interview_reports`
7. Integrations & ops — `notifications`, `sheet_syncs`, `audit_logs`
8. Framework — `sessions`, `cache`, `jobs`, `failed_jobs`, `job_batches`, `personal_access_tokens`, `password_reset_tokens`

---

## 1. Identity & RBAC

```sql
CREATE TABLE users (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name            VARCHAR(150) NOT NULL,
  email           VARCHAR(190) NOT NULL UNIQUE,
  email_verified_at TIMESTAMP NULL,
  password        VARCHAR(255) NOT NULL,
  phone           VARCHAR(30) NULL,
  avatar_url      VARCHAR(512) NULL,
  locale          CHAR(2) NOT NULL DEFAULT 'en',          -- en | ar
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at   TIMESTAMP NULL,
  two_factor_secret      TEXT NULL,
  two_factor_recovery_codes TEXT NULL,
  remember_token  VARCHAR(100) NULL,
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  deleted_at      TIMESTAMP NULL
);

CREATE TABLE roles (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  slug        VARCHAR(64) NOT NULL UNIQUE,   -- super_admin | hr_manager | recruiter | dept_manager | viewer
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL
);

CREATE TABLE permissions (
  id    BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  slug  VARCHAR(100) NOT NULL UNIQUE,        -- e.g. job.create, interview.view, report.export, settings.manage
  name  VARCHAR(150) NOT NULL,
  group VARCHAR(64) NOT NULL,                -- jobs | candidates | interviews | reports | users | settings
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

CREATE TABLE permission_role (
  permission_id BIGINT UNSIGNED NOT NULL,
  role_id       BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (permission_id, role_id),
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE
);

CREATE TABLE role_user (
  role_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, user_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

See [`docs/14-rbac.md`](14-rbac.md) for the seeded roles and the full permission matrix.

---

## 2. Org & hiring

```sql
CREATE TABLE departments (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name        VARCHAR(150) NOT NULL,
  slug        VARCHAR(160) NOT NULL UNIQUE,
  manager_id  BIGINT UNSIGNED NULL,
  description TEXT NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE job_positions (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  department_id   BIGINT UNSIGNED NULL,
  created_by      BIGINT UNSIGNED NULL,
  title           VARCHAR(200) NOT NULL,
  slug            VARCHAR(220) NOT NULL UNIQUE,
  seniority       ENUM('intern','junior','mid','senior','lead','manager','director','executive') NOT NULL,
  employment_type ENUM('full_time','part_time','contract','internship') NOT NULL DEFAULT 'full_time',
  location        VARCHAR(150) NULL,
  is_remote       TINYINT(1) NOT NULL DEFAULT 0,
  description     MEDIUMTEXT NULL,
  responsibilities JSON NULL,                  -- array of strings
  requirements    JSON NULL,                   -- array of {skill, weight, required}
  salary_min      DECIMAL(12,2) NULL,
  salary_max      DECIMAL(12,2) NULL,
  currency        CHAR(3) NOT NULL DEFAULT 'EGP',
  default_template_id BIGINT UNSIGNED NULL,     -- FK added after interview_templates
  pipeline_id     BIGINT UNSIGNED NULL,
  status          ENUM('draft','open','paused','closed') NOT NULL DEFAULT 'draft',
  openings        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  deleted_at      TIMESTAMP NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by)    REFERENCES users(id)       ON DELETE SET NULL,
  INDEX idx_jobpos_status (status),
  INDEX idx_jobpos_dept (department_id)
);

CREATE TABLE hiring_pipelines (
  id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(150) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

CREATE TABLE pipeline_stages (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  pipeline_id BIGINT UNSIGNED NOT NULL,
  name        VARCHAR(100) NOT NULL,           -- Applied | AI Screening | Shortlisted | Human Interview | Offer | Hired | Rejected
  slug        VARCHAR(110) NOT NULL,
  position    SMALLINT UNSIGNED NOT NULL,
  is_terminal TINYINT(1) NOT NULL DEFAULT 0,   -- Hired / Rejected
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  FOREIGN KEY (pipeline_id) REFERENCES hiring_pipelines(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_stage (pipeline_id, slug)
);
```

---

## 3. Interview configuration

```sql
CREATE TABLE avatars (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name            VARCHAR(120) NOT NULL,        -- "Sara", "Khaled"
  role_label      VARCHAR(120) NOT NULL,        -- HR Recruiter | Technical Interviewer | Engineering Manager | Sales Director | CS Manager
  gender          ENUM('female','male','neutral') NOT NULL DEFAULT 'neutral',
  personality     TEXT NOT NULL,                -- persona prompt fragment
  questioning_style ENUM('friendly','formal','probing','rapid','socratic') NOT NULL DEFAULT 'friendly',
  language        CHAR(2) NOT NULL DEFAULT 'en',
  voice_provider  VARCHAR(40) NULL,             -- web_speech | elevenlabs | deepgram | heygen | tavus
  voice_id        VARCHAR(120) NULL,
  video_provider  VARCHAR(40) NULL,             -- tavus | heygen | none
  video_replica_id VARCHAR(120) NULL,           -- provider-side avatar/replica id
  avatar_image_url VARCHAR(512) NULL,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL
);

CREATE TABLE interview_templates (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name          VARCHAR(150) NOT NULL,
  department_id BIGINT UNSIGNED NULL,
  avatar_id     BIGINT UNSIGNED NULL,
  mode          ENUM('text','voice','video') NOT NULL DEFAULT 'text',
  language      CHAR(2) NOT NULL DEFAULT 'en',  -- en | ar
  intro_script  TEXT NULL,                      -- optional opening override
  min_questions SMALLINT UNSIGNED NOT NULL DEFAULT 6,
  max_questions SMALLINT UNSIGNED NOT NULL DEFAULT 14,
  max_duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 25,
  difficulty    ENUM('adaptive','easy','standard','hard') NOT NULL DEFAULT 'adaptive',
  follow_up_depth TINYINT UNSIGNED NOT NULL DEFAULT 2,   -- max follow-ups per thread
  config        JSON NULL,                      -- toggles: detect_contradictions, measure_confidence, english_eval...
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (avatar_id)     REFERENCES avatars(id)     ON DELETE SET NULL
);

-- Which competencies a template scores, and their weights (sum need not be 100; normalized at scoring time)
CREATE TABLE template_competencies (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_id  BIGINT UNSIGNED NOT NULL,
  competency   VARCHAR(40) NOT NULL,            -- enum mirror: technical, communication, confidence, leadership,
                                                -- problem_solving, critical_thinking, ai_knowledge, culture_fit,
                                                -- professionalism, english_fluency, learning_ability
  weight       DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  is_enabled   TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (template_id) REFERENCES interview_templates(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_tpl_comp (template_id, competency)
);

CREATE TABLE question_libraries (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name          VARCHAR(150) NOT NULL,
  department_id BIGINT UNSIGNED NULL,
  description   VARCHAR(255) NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE questions (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  library_id   BIGINT UNSIGNED NOT NULL,
  competency   VARCHAR(40) NOT NULL,
  seniority    VARCHAR(20) NULL,                -- optional targeting
  text         TEXT NOT NULL,
  text_ar      TEXT NULL,
  expected_signals TEXT NULL,                   -- what a strong answer demonstrates (fed to scorer)
  difficulty   ENUM('easy','standard','hard') NOT NULL DEFAULT 'standard',
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NULL,
  updated_at   TIMESTAMP NULL,
  FOREIGN KEY (library_id) REFERENCES question_libraries(id) ON DELETE CASCADE,
  INDEX idx_q_comp (competency)
);
```

> The question bank is **optional seed material** for the AI engine, not a rigid script. In
> adaptive mode the engine generates and branches questions dynamically (see
> [`docs/07`](docs/07-interview-engine-logic.md)); seeded questions are used as anchors / few-shot.

---

## 4. Candidate & invitation

```sql
CREATE TABLE candidates (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  full_name        VARCHAR(190) NOT NULL,
  email            VARCHAR(190) NOT NULL,
  phone            VARCHAR(40) NULL,
  linkedin_url     VARCHAR(512) NULL,
  country          VARCHAR(80) NULL,
  years_experience DECIMAL(4,1) NULL,
  expected_salary  DECIMAL(12,2) NULL,
  salary_currency  CHAR(3) NULL,
  notice_period    VARCHAR(60) NULL,            -- "Immediate", "1 month", "3 months"
  cv_path          VARCHAR(512) NULL,           -- S3 key
  cv_original_name VARCHAR(255) NULL,
  cv_text          MEDIUMTEXT NULL,             -- extracted text cache
  source           VARCHAR(60) NULL,            -- link | referral | linkedin
  consent_at       TIMESTAMP NULL,              -- GDPR processing consent
  created_at       TIMESTAMP NULL,
  updated_at       TIMESTAMP NULL,
  deleted_at       TIMESTAMP NULL,
  INDEX idx_cand_email (email)
);

CREATE TABLE candidate_pipeline (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  candidate_id  BIGINT UNSIGNED NOT NULL,
  job_position_id BIGINT UNSIGNED NOT NULL,
  stage_id      BIGINT UNSIGNED NOT NULL,
  moved_by      BIGINT UNSIGNED NULL,
  moved_at      TIMESTAMP NULL,
  note          VARCHAR(255) NULL,
  FOREIGN KEY (candidate_id)    REFERENCES candidates(id)     ON DELETE CASCADE,
  FOREIGN KEY (job_position_id) REFERENCES job_positions(id)  ON DELETE CASCADE,
  FOREIGN KEY (stage_id)        REFERENCES pipeline_stages(id) ON DELETE CASCADE,
  FOREIGN KEY (moved_by)        REFERENCES users(id)          ON DELETE SET NULL,
  UNIQUE KEY uniq_cand_job (candidate_id, job_position_id)
);

CREATE TABLE interview_invitations (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  job_position_id BIGINT UNSIGNED NOT NULL,
  template_id     BIGINT UNSIGNED NULL,
  avatar_id       BIGINT UNSIGNED NULL,
  created_by      BIGINT UNSIGNED NULL,
  token           CHAR(40) NOT NULL UNIQUE,     -- public URL token
  email           VARCHAR(190) NULL,            -- optional pre-fill / send-to
  candidate_id    BIGINT UNSIGNED NULL,         -- set once intake completes
  status          ENUM('pending','opened','started','completed','expired','cancelled') NOT NULL DEFAULT 'pending',
  expires_at      TIMESTAMP NULL,
  opened_at       TIMESTAMP NULL,
  reminded_at     TIMESTAMP NULL,
  completed_at    TIMESTAMP NULL,
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  FOREIGN KEY (job_position_id) REFERENCES job_positions(id)       ON DELETE CASCADE,
  FOREIGN KEY (template_id)     REFERENCES interview_templates(id) ON DELETE SET NULL,
  FOREIGN KEY (avatar_id)       REFERENCES avatars(id)             ON DELETE SET NULL,
  FOREIGN KEY (candidate_id)    REFERENCES candidates(id)          ON DELETE SET NULL,
  FOREIGN KEY (created_by)      REFERENCES users(id)               ON DELETE SET NULL,
  INDEX idx_inv_status (status)
);
```

---

## 5. Interview runtime

```sql
CREATE TABLE interviews (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  public_id       CHAR(26) NOT NULL UNIQUE,     -- ULID for URLs / sheet "Candidate ID"
  candidate_id    BIGINT UNSIGNED NOT NULL,
  job_position_id BIGINT UNSIGNED NOT NULL,
  template_id     BIGINT UNSIGNED NULL,
  avatar_id       BIGINT UNSIGNED NULL,
  invitation_id   BIGINT UNSIGNED NULL,
  mode            ENUM('text','voice','video') NOT NULL DEFAULT 'text',
  language        CHAR(2) NOT NULL DEFAULT 'en',
  status          ENUM('scheduled','in_progress','processing','completed','abandoned','error') NOT NULL DEFAULT 'scheduled',
  -- denormalized result fields for fast dashboard/sheet reads:
  overall_score   DECIMAL(5,2) NULL,
  recommendation  ENUM('strong_hire','hire','maybe','reject') NULL,
  question_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  duration_seconds INT UNSIGNED NULL,
  llm_input_tokens  INT UNSIGNED NOT NULL DEFAULT 0,
  llm_output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  started_at      TIMESTAMP NULL,
  completed_at    TIMESTAMP NULL,
  state           JSON NULL,                    -- engine state snapshot (phase, threads, covered competencies)
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  deleted_at      TIMESTAMP NULL,
  FOREIGN KEY (candidate_id)    REFERENCES candidates(id)          ON DELETE CASCADE,
  FOREIGN KEY (job_position_id) REFERENCES job_positions(id)       ON DELETE CASCADE,
  FOREIGN KEY (template_id)     REFERENCES interview_templates(id) ON DELETE SET NULL,
  FOREIGN KEY (avatar_id)       REFERENCES avatars(id)             ON DELETE SET NULL,
  FOREIGN KEY (invitation_id)   REFERENCES interview_invitations(id) ON DELETE SET NULL,
  INDEX idx_int_status (status),
  INDEX idx_int_reco (recommendation),
  INDEX idx_int_job (job_position_id),
  INDEX idx_int_created (created_at)
);

CREATE TABLE interview_messages (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  interview_id  BIGINT UNSIGNED NOT NULL,
  seq           INT UNSIGNED NOT NULL,          -- 1-based ordering
  role          ENUM('agent','candidate','system') NOT NULL,
  content       MEDIUMTEXT NOT NULL,
  audio_path    VARCHAR(512) NULL,              -- per-turn audio (voice mode)
  competency    VARCHAR(40) NULL,               -- competency this agent question targets
  thread_key    VARCHAR(60) NULL,               -- follow-up thread grouping
  is_follow_up  TINYINT(1) NOT NULL DEFAULT 0,
  ms_offset     INT UNSIGNED NULL,              -- offset from interview start (for replay sync)
  tokens        INT UNSIGNED NULL,
  meta          JSON NULL,                      -- {confidence_hint, flagged, latency_ms}
  created_at    TIMESTAMP NULL,
  FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_msg_seq (interview_id, seq),
  INDEX idx_msg_interview (interview_id)
);

-- Post-interview moment timeline (jump-to points in the replay dashboard)
CREATE TABLE interview_events (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  interview_id BIGINT UNSIGNED NOT NULL,
  ms_offset    INT UNSIGNED NOT NULL,           -- 00:03 → 3000
  type         VARCHAR(50) NOT NULL,            -- introduction | confidence_up | confidence_down | strong_answer |
                                                -- inconsistency | leadership_example | weak_communication | red_flag | wrap_up
  severity     ENUM('info','positive','warning','critical') NOT NULL DEFAULT 'info',
  label        VARCHAR(190) NOT NULL,
  message_id   BIGINT UNSIGNED NULL,            -- anchor to a transcript turn
  meta         JSON NULL,
  created_at   TIMESTAMP NULL,
  FOREIGN KEY (interview_id) REFERENCES interviews(id)        ON DELETE CASCADE,
  FOREIGN KEY (message_id)   REFERENCES interview_messages(id) ON DELETE SET NULL,
  INDEX idx_evt_interview (interview_id, ms_offset)
);

CREATE TABLE recordings (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  interview_id BIGINT UNSIGNED NOT NULL,
  kind         ENUM('video','audio','screen','transcript') NOT NULL,
  provider     VARCHAR(40) NULL,                -- livekit | tavus | heygen | local
  url          VARCHAR(512) NULL,               -- S3 key
  duration_seconds INT UNSIGNED NULL,
  size_bytes   BIGINT UNSIGNED NULL,
  status       ENUM('pending','processing','ready','failed') NOT NULL DEFAULT 'pending',
  meta         JSON NULL,
  created_at   TIMESTAMP NULL,
  updated_at   TIMESTAMP NULL,
  FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE,
  INDEX idx_rec_interview (interview_id)
);
```

---

## 6. AI outputs

```sql
CREATE TABLE cv_analyses (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  candidate_id  BIGINT UNSIGNED NOT NULL,
  interview_id  BIGINT UNSIGNED NULL,
  summary       TEXT NULL,
  extracted     JSON NULL,                      -- {skills[], roles[], companies[], education[], total_years}
  highlights    JSON NULL,                      -- array of strings
  gaps          JSON NULL,                      -- employment gaps / concerns
  jd_match_score DECIMAL(5,2) NULL,             -- CV ↔ job requirements fit (0-100)
  topics_to_probe JSON NULL,                    -- feeds the interview engine's opening plan
  model         VARCHAR(60) NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE SET NULL
);

-- One row per scored competency (long form → easy aggregation, charting, filtering)
CREATE TABLE competency_scores (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  interview_id BIGINT UNSIGNED NOT NULL,
  competency   VARCHAR(40) NOT NULL,            -- technical | communication | confidence | leadership |
                                                -- problem_solving | critical_thinking | ai_knowledge |
                                                -- culture_fit | professionalism | english_fluency | learning_ability
  score        DECIMAL(5,2) NOT NULL,           -- 0-100
  weight       DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  confidence   DECIMAL(4,2) NULL,               -- model's self-rated confidence in this score 0-1
  rationale    TEXT NULL,
  evidence     JSON NULL,                       -- message seq refs supporting the score
  created_at   TIMESTAMP NULL,
  FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_score (interview_id, competency)
);

CREATE TABLE behavioral_analyses (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  interview_id  BIGINT UNSIGNED NOT NULL,
  personality_type VARCHAR(40) NULL,            -- e.g. "Analytical-Driver"
  disc          JSON NULL,                      -- {D, I, S, C} 0-100 each (approximation)
  big_five      JSON NULL,                      -- {openness, conscientiousness, extraversion, agreeableness, neuroticism}
  leadership_tendency TEXT NULL,
  growth_mindset_score DECIMAL(5,2) NULL,
  stress_handling_score DECIMAL(5,2) NULL,
  risk_indicators  JSON NULL,                   -- array of {label, severity, note}
  integrity_indicators JSON NULL,
  observations  TEXT NULL,
  model         VARCHAR(60) NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE
);

CREATE TABLE red_flags (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  interview_id BIGINT UNSIGNED NOT NULL,
  type         VARCHAR(50) NOT NULL,            -- inconsistent_answer | suspicious_claim | salary_mismatch |
                                                -- fake_experience | lack_of_ownership | poor_communication |
                                                -- aggressive_behavior | evasive_answer
  severity     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  description  TEXT NOT NULL,
  evidence     JSON NULL,                       -- message seq refs / quotes
  created_at   TIMESTAMP NULL,
  FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE,
  INDEX idx_flag_interview (interview_id),
  INDEX idx_flag_type (type)
);

CREATE TABLE video_analyses (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  interview_id  BIGINT UNSIGNED NOT NULL,
  eye_contact_score      DECIMAL(5,2) NULL,
  facial_expression      JSON NULL,             -- distribution {neutral, happy, tense, ...}
  engagement_score       DECIMAL(5,2) NULL,
  confidence_score       DECIMAL(5,2) NULL,
  nervousness_score      DECIMAL(5,2) NULL,
  energy_score           DECIMAL(5,2) NULL,
  attention_score        DECIMAL(5,2) NULL,
  professional_appearance_score DECIMAL(5,2) NULL,
  speaking_pace_wpm      SMALLINT UNSIGNED NULL,
  body_language          JSON NULL,             -- observations
  authenticity_score     DECIMAL(5,2) NULL,
  timeline               JSON NULL,             -- [{ms_offset, signal, value}]
  provider               VARCHAR(40) NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE
);

CREATE TABLE interview_reports (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  interview_id  BIGINT UNSIGNED NOT NULL UNIQUE,
  overall_score DECIMAL(5,2) NULL,
  recommendation ENUM('strong_hire','hire','maybe','reject') NULL,
  resume_summary TEXT NULL,
  interview_summary TEXT NULL,
  strengths     JSON NULL,                      -- array
  weaknesses    JSON NULL,                      -- array
  technical_assessment TEXT NULL,
  behavioral_assessment TEXT NULL,
  ai_analysis   TEXT NULL,
  hiring_recommendation TEXT NULL,              -- narrative justification
  pdf_path      VARCHAR(512) NULL,              -- S3 key
  generated_at  TIMESTAMP NULL,
  model         VARCHAR(60) NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE
);
```

---

## 7. Integrations & ops

```sql
CREATE TABLE notifications (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  channel      ENUM('email','whatsapp','inapp') NOT NULL,
  event        VARCHAR(60) NOT NULL,            -- invitation | reminder | completion | new_candidate |
                                                -- interview_completed | high_potential
  recipient    VARCHAR(190) NOT NULL,           -- email / phone / user_id
  notifiable_type VARCHAR(120) NULL,            -- polymorphic (candidate / interview / user)
  notifiable_id   BIGINT UNSIGNED NULL,
  payload      JSON NULL,
  status       ENUM('queued','sent','failed','delivered','read') NOT NULL DEFAULT 'queued',
  error        VARCHAR(512) NULL,
  sent_at      TIMESTAMP NULL,
  created_at   TIMESTAMP NULL,
  updated_at   TIMESTAMP NULL,
  INDEX idx_notif_status (status),
  INDEX idx_notif_notifiable (notifiable_type, notifiable_id)
);

CREATE TABLE sheet_syncs (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  interview_id   BIGINT UNSIGNED NOT NULL,
  spreadsheet_id VARCHAR(120) NOT NULL,
  sheet_tab      VARCHAR(120) NOT NULL DEFAULT 'Candidates',
  row_number     INT UNSIGNED NULL,
  status         ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
  error          VARCHAR(512) NULL,
  synced_at      TIMESTAMP NULL,
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE,
  INDEX idx_sync_status (status)
);

CREATE TABLE audit_logs (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NULL,
  action         VARCHAR(80) NOT NULL,          -- created | updated | deleted | viewed | exported | login | gdpr_erase
  auditable_type VARCHAR(120) NULL,
  auditable_id   BIGINT UNSIGNED NULL,
  changes        JSON NULL,                     -- {before, after}
  ip_address     VARCHAR(45) NULL,
  user_agent     VARCHAR(512) NULL,
  created_at     TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_actor (user_id),
  INDEX idx_audit_target (auditable_type, auditable_id),
  INDEX idx_audit_created (created_at)
);
```

`job_positions.default_template_id` FK is added in a follow-up migration once
`interview_templates` exists:

```sql
ALTER TABLE job_positions
  ADD CONSTRAINT fk_jobpos_template
  FOREIGN KEY (default_template_id) REFERENCES interview_templates(id) ON DELETE SET NULL;
ALTER TABLE job_positions
  ADD CONSTRAINT fk_jobpos_pipeline
  FOREIGN KEY (pipeline_id) REFERENCES hiring_pipelines(id) ON DELETE SET NULL;
```

---

## 8. Framework tables

Standard Laravel tables created by framework migrations: `sessions`, `cache`, `cache_locks`,
`jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`, `personal_access_tokens`
(Sanctum for API auth). Not redefined here.

---

## Retention & GDPR

- PII tables (`candidates`, `interviews`, `interview_messages`, `recordings`, `cv_analyses`,
  `video_analyses`) use soft deletes; a scheduled `PurgeExpiredCandidateData` job hard-deletes
  rows past the configured retention window (`config('watad.gdpr.retention_days')`, default 365)
  and removes S3 objects.
- A candidate "right to erasure" request triggers `GdprEraseCandidate`, which deletes the
  candidate, cascades to interviews/messages/recordings, removes S3 objects, and writes an
  `audit_logs` entry (`action = gdpr_erase`).

See [`docs/13-security-architecture.md`](13-security-architecture.md).
