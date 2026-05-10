-- =====================================================================
-- HalaOps CRM — Database Schema (PostgreSQL 16)
-- Multi-tenant, RLS-enforced, event-sourced for performance metrics.
-- This file is illustrative; production migrations will be Prisma-managed.
-- =====================================================================

CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "vector";

-- ---------------------------------------------------------------------
-- 1. Tenancy & Identity
-- ---------------------------------------------------------------------
CREATE TABLE tenants (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  slug            TEXT UNIQUE NOT NULL,
  name            TEXT NOT NULL,
  locale_default  TEXT NOT NULL DEFAULT 'ar',
  plan            TEXT NOT NULL DEFAULT 'starter',
  settings        JSONB NOT NULL DEFAULT '{}'::jsonb,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE users (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  email           TEXT NOT NULL,
  phone           TEXT,
  password_hash   TEXT,
  status          TEXT NOT NULL DEFAULT 'active',
  locale          TEXT NOT NULL DEFAULT 'ar',
  timezone        TEXT NOT NULL DEFAULT 'Asia/Riyadh',
  avatar_url      TEXT,
  last_seen_at    TIMESTAMPTZ,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (tenant_id, email)
);
CREATE INDEX users_tenant_idx ON users(tenant_id);

CREATE TABLE roles (
  id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id  UUID REFERENCES tenants(id) ON DELETE CASCADE,
  key        TEXT NOT NULL,
  name_ar    TEXT NOT NULL,
  name_en    TEXT NOT NULL,
  level      INT  NOT NULL DEFAULT 30,
  UNIQUE (tenant_id, key)
);

CREATE TABLE permissions (
  id      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  key     TEXT UNIQUE NOT NULL,
  scope   TEXT NOT NULL,        -- tenant | department | team | self
  action  TEXT NOT NULL         -- create | read | update | delete | execute
);

CREATE TABLE role_permissions (
  role_id        UUID REFERENCES roles(id) ON DELETE CASCADE,
  permission_id  UUID REFERENCES permissions(id) ON DELETE CASCADE,
  PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE user_roles (
  user_id     UUID REFERENCES users(id) ON DELETE CASCADE,
  role_id     UUID REFERENCES roles(id) ON DELETE CASCADE,
  scope_type  TEXT NOT NULL DEFAULT 'tenant',
  scope_id    UUID,
  PRIMARY KEY (user_id, role_id, scope_type, scope_id)
);

CREATE TABLE auth_sessions (
  id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  ip         INET,
  ua         TEXT,
  device_id  TEXT,
  expires_at TIMESTAMPTZ NOT NULL,
  revoked_at TIMESTAMPTZ
);

CREATE TABLE auth_2fa (
  user_id     UUID PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  type        TEXT NOT NULL,    -- totp | sms | webauthn
  secret      TEXT NOT NULL,
  verified_at TIMESTAMPTZ
);

-- ---------------------------------------------------------------------
-- 2. Org Structure
-- ---------------------------------------------------------------------
CREATE TABLE departments (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  name_ar     TEXT NOT NULL,
  name_en     TEXT NOT NULL,
  parent_id   UUID REFERENCES departments(id),
  manager_id  UUID REFERENCES users(id)
);

CREATE TABLE teams (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id      UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  department_id  UUID REFERENCES departments(id),
  name           TEXT NOT NULL,
  leader_id      UUID REFERENCES users(id),
  color          TEXT
);

CREATE TABLE team_members (
  team_id      UUID REFERENCES teams(id) ON DELETE CASCADE,
  user_id      UUID REFERENCES users(id) ON DELETE CASCADE,
  role_in_team TEXT,
  joined_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  left_at      TIMESTAMPTZ,
  PRIMARY KEY (team_id, user_id)
);

CREATE TABLE positions (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id   UUID NOT NULL REFERENCES tenants(id),
  title_ar    TEXT NOT NULL,
  title_en    TEXT NOT NULL,
  level       INT NOT NULL,
  salary_band JSONB
);

CREATE TABLE employees (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id         UUID UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  position_id     UUID REFERENCES positions(id),
  employment_type TEXT,
  hired_at        DATE,
  contract_end_at DATE,
  reports_to_id   UUID REFERENCES employees(id),
  work_hours      JSONB
);

-- ---------------------------------------------------------------------
-- 3. CRM (Hala Career-specific)
-- ---------------------------------------------------------------------
CREATE TYPE client_type AS ENUM ('company','candidate','partner');
CREATE TABLE clients (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id      UUID NOT NULL REFERENCES tenants(id),
  type           client_type NOT NULL,
  name           TEXT NOT NULL,
  industry       TEXT,
  size           TEXT,
  country        TEXT,
  owner_id       UUID REFERENCES users(id),
  stage          TEXT,
  value_score    NUMERIC(5,2),
  risk_score     NUMERIC(5,2),
  custom_fields  JSONB DEFAULT '{}'::jsonb,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX clients_tenant_idx ON clients(tenant_id);
CREATE INDEX clients_name_trgm ON clients USING gin (name gin_trgm_ops);

CREATE TABLE contacts (
  id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  client_id  UUID NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
  name       TEXT NOT NULL,
  role       TEXT,
  email      TEXT,
  phone      TEXT,
  locale     TEXT
);

CREATE TYPE service_category AS ENUM
  ('recruitment','training','consulting','community','partnership');
CREATE TABLE services (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id     UUID NOT NULL REFERENCES tenants(id),
  key           TEXT NOT NULL,
  name_ar       TEXT NOT NULL,
  name_en       TEXT NOT NULL,
  category      service_category NOT NULL,
  base_price    NUMERIC(12,2),
  duration_days INT,
  profit_margin NUMERIC(5,2)
);

CREATE TABLE deals (
  id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  client_id         UUID NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
  service_id        UUID REFERENCES services(id),
  stage             TEXT NOT NULL,
  amount            NUMERIC(14,2),
  currency          TEXT NOT NULL DEFAULT 'SAR',
  probability       NUMERIC(5,2),
  expected_close_at DATE,
  actual_close_at   DATE,
  owner_id          UUID REFERENCES users(id),
  lost_reason       TEXT,
  created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE candidates (
  id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  client_id           UUID REFERENCES clients(id),
  cv_url              TEXT,
  headline            TEXT,
  skills              JSONB,
  level               TEXT,
  availability        TEXT,
  salary_expectation  NUMERIC(12,2),
  status              TEXT
);

CREATE TABLE placements (
  id                 UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  candidate_id       UUID REFERENCES candidates(id),
  deal_id            UUID REFERENCES deals(id),
  employer_client_id UUID REFERENCES clients(id),
  placed_at          DATE,
  probation_end_at   DATE,
  status             TEXT
);

CREATE TABLE training_programs (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id   UUID NOT NULL REFERENCES tenants(id),
  service_id  UUID REFERENCES services(id),
  capacity    INT,
  schedule    JSONB
);

CREATE TABLE training_enrollments (
  program_id   UUID REFERENCES training_programs(id) ON DELETE CASCADE,
  candidate_id UUID REFERENCES candidates(id) ON DELETE CASCADE,
  progress     NUMERIC(5,2),
  score        NUMERIC(5,2),
  PRIMARY KEY (program_id, candidate_id)
);

-- ---------------------------------------------------------------------
-- 4. Tasks & Workflow
-- ---------------------------------------------------------------------
CREATE TABLE projects (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id        UUID NOT NULL REFERENCES tenants(id),
  client_id        UUID REFERENCES clients(id),
  name             TEXT NOT NULL,
  status           TEXT,
  owner_id         UUID REFERENCES users(id),
  sla_template_id  UUID
);

CREATE TABLE sla_templates (
  id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  name      TEXT NOT NULL,
  rules     JSONB NOT NULL
);

CREATE TABLE tasks (
  id                 UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id          UUID NOT NULL REFERENCES tenants(id),
  title              TEXT NOT NULL,
  description        TEXT,
  type               TEXT NOT NULL DEFAULT 'task',
  status             TEXT NOT NULL DEFAULT 'todo',
  priority           SMALLINT NOT NULL DEFAULT 2,    -- 0=P0 critical
  assignee_id        UUID REFERENCES users(id),
  reporter_id        UUID REFERENCES users(id),
  parent_task_id     UUID REFERENCES tasks(id),
  project_id         UUID REFERENCES projects(id),
  sla_minutes        INT,
  due_at             TIMESTAMPTZ,
  started_at         TIMESTAMPTZ,
  completed_at       TIMESTAMPTZ,
  reopened_count     INT NOT NULL DEFAULT 0,
  postponed_count    INT NOT NULL DEFAULT 0,
  estimated_minutes  INT,
  actual_minutes     INT,
  custom_fields      JSONB DEFAULT '{}'::jsonb,
  created_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX tasks_tenant_status_idx ON tasks(tenant_id, status);
CREATE INDEX tasks_assignee_due_idx  ON tasks(assignee_id, due_at);
CREATE INDEX tasks_project_idx       ON tasks(project_id);

CREATE TABLE task_dependencies (
  task_id            UUID REFERENCES tasks(id) ON DELETE CASCADE,
  depends_on_task_id UUID REFERENCES tasks(id) ON DELETE CASCADE,
  type               TEXT NOT NULL DEFAULT 'finish_to_start',
  PRIMARY KEY (task_id, depends_on_task_id)
);

CREATE TABLE task_watchers (
  task_id UUID REFERENCES tasks(id) ON DELETE CASCADE,
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  PRIMARY KEY (task_id, user_id)
);

CREATE TABLE task_comments (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  task_id       UUID NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
  user_id       UUID NOT NULL REFERENCES users(id),
  body          TEXT,
  body_voice_url TEXT,
  mentions      JSONB,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE task_attachments (
  id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  task_id   UUID NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
  file_url  TEXT NOT NULL,
  kind      TEXT,
  size      BIGINT
);

CREATE TABLE task_checklists (
  id      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  task_id UUID NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
  item    TEXT NOT NULL,
  done_by UUID REFERENCES users(id),
  done_at TIMESTAMPTZ
);

CREATE TYPE time_log_source AS ENUM ('manual','auto_focus','calendar');
CREATE TABLE task_time_logs (
  id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  task_id    UUID NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
  user_id    UUID NOT NULL REFERENCES users(id),
  started_at TIMESTAMPTZ NOT NULL,
  ended_at   TIMESTAMPTZ,
  source     time_log_source NOT NULL DEFAULT 'manual'
);

CREATE TABLE task_history (
  id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  task_id  UUID NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
  actor_id UUID REFERENCES users(id),
  change   JSONB NOT NULL,
  at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE recurring_tasks (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id    UUID NOT NULL REFERENCES tenants(id),
  template     JSONB NOT NULL,
  rrule        TEXT NOT NULL,
  next_run_at  TIMESTAMPTZ NOT NULL
);

CREATE TABLE approvals (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_type  TEXT NOT NULL,
  entity_id    UUID NOT NULL,
  requester_id UUID REFERENCES users(id),
  approver_id  UUID REFERENCES users(id),
  status       TEXT NOT NULL DEFAULT 'pending',
  decided_at   TIMESTAMPTZ,
  reason       TEXT
);

CREATE TABLE escalations (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_type   TEXT NOT NULL,
  entity_id     UUID NOT NULL,
  level         INT NOT NULL DEFAULT 1,
  triggered_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  resolved_at   TIMESTAMPTZ
);

-- ---------------------------------------------------------------------
-- 5. Performance Intelligence (event-sourced)
-- ---------------------------------------------------------------------
CREATE TABLE events (
  id            BIGSERIAL PRIMARY KEY,
  tenant_id     UUID NOT NULL REFERENCES tenants(id),
  actor_id      UUID REFERENCES users(id),
  type          TEXT NOT NULL,
  subject_type  TEXT,
  subject_id    UUID,
  metadata      JSONB,
  occurred_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX events_tenant_time_idx ON events(tenant_id, occurred_at DESC);
CREATE INDEX events_type_idx ON events(type);

CREATE TABLE performance_scores (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id      UUID NOT NULL REFERENCES users(id),
  period       DATERANGE NOT NULL,
  performance  NUMERIC(5,2),
  reliability  NUMERIC(5,2),
  leadership   NUMERIC(5,2),
  consistency  NUMERIC(5,2),
  growth       NUMERIC(5,2),
  computed_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE performance_components (
  score_id   UUID REFERENCES performance_scores(id) ON DELETE CASCADE,
  component  TEXT NOT NULL,
  value      NUMERIC(8,3),
  weight     NUMERIC(5,3),
  PRIMARY KEY (score_id, component)
);

CREATE TYPE review_type AS ENUM ('manager','peer','client','self','ai');
CREATE TABLE reviews (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  reviewee_id  UUID REFERENCES users(id),
  reviewer_id  UUID REFERENCES users(id),
  type         review_type NOT NULL,
  period       DATERANGE NOT NULL,
  scores       JSONB,
  notes        TEXT,
  submitted_at TIMESTAMPTZ
);

CREATE TABLE client_feedback (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  client_id    UUID REFERENCES clients(id),
  deal_id      UUID REFERENCES deals(id),
  employee_id  UUID REFERENCES users(id),
  csat         NUMERIC(3,1),
  nps          INT,
  comment      TEXT,
  submitted_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE attendance (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id      UUID NOT NULL REFERENCES users(id),
  check_in_at  TIMESTAMPTZ,
  check_out_at TIMESTAMPTZ,
  source       TEXT,
  location     JSONB
);

CREATE TABLE focus_sessions (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id     UUID NOT NULL REFERENCES users(id),
  started_at  TIMESTAMPTZ NOT NULL,
  ended_at    TIMESTAMPTZ,
  app_context JSONB
);

-- ---------------------------------------------------------------------
-- 6. Gamification
-- ---------------------------------------------------------------------
CREATE TABLE xp_ledger (
  id           BIGSERIAL PRIMARY KEY,
  user_id      UUID NOT NULL REFERENCES users(id),
  delta        INT NOT NULL,
  source_type  TEXT NOT NULL,
  source_id    UUID,
  reason       TEXT,
  at           TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX xp_ledger_user_time_idx ON xp_ledger(user_id, at DESC);

CREATE TABLE levels (
  level        INT PRIMARY KEY,
  xp_required  INT NOT NULL,
  perks        JSONB
);

CREATE TYPE badge_rarity AS ENUM ('common','rare','epic','legendary','mythic');
CREATE TABLE badges (
  id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  key       TEXT UNIQUE NOT NULL,
  name_ar   TEXT NOT NULL,
  name_en   TEXT NOT NULL,
  rarity    badge_rarity NOT NULL DEFAULT 'common',
  criteria  JSONB NOT NULL,
  icon_url  TEXT
);

CREATE TABLE user_badges (
  user_id    UUID REFERENCES users(id) ON DELETE CASCADE,
  badge_id   UUID REFERENCES badges(id) ON DELETE CASCADE,
  awarded_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  evidence   JSONB,
  PRIMARY KEY (user_id, badge_id)
);

CREATE TABLE streaks (
  user_id           UUID PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  kind              TEXT NOT NULL DEFAULT 'daily',
  current           INT NOT NULL DEFAULT 0,
  longest           INT NOT NULL DEFAULT 0,
  last_activity_at  TIMESTAMPTZ
);

CREATE TYPE mission_kind AS ENUM ('daily','weekly','seasonal','team');
CREATE TABLE missions (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id    UUID NOT NULL REFERENCES tenants(id),
  title        TEXT NOT NULL,
  kind         mission_kind NOT NULL,
  rules        JSONB NOT NULL,
  reward_xp    INT NOT NULL DEFAULT 0,
  reward_coins INT NOT NULL DEFAULT 0,
  starts_at    TIMESTAMPTZ NOT NULL,
  ends_at      TIMESTAMPTZ NOT NULL
);

CREATE TABLE mission_progress (
  mission_id   UUID REFERENCES missions(id) ON DELETE CASCADE,
  user_id      UUID REFERENCES users(id) ON DELETE CASCADE,
  progress     JSONB,
  completed_at TIMESTAMPTZ,
  PRIMARY KEY (mission_id, user_id)
);

CREATE TABLE leaderboards (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  scope       TEXT NOT NULL,            -- tenant | team | role
  period      TEXT NOT NULL,            -- daily | weekly | season
  snapshot    JSONB NOT NULL,
  computed_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE coins_ledger (
  id      BIGSERIAL PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES users(id),
  delta   INT NOT NULL,
  source  TEXT NOT NULL,
  at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE rewards (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name        TEXT NOT NULL,
  cost_coins  INT NOT NULL,
  stock       INT,
  kind        TEXT NOT NULL
);

CREATE TABLE reward_redemptions (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id       UUID NOT NULL REFERENCES users(id),
  reward_id     UUID NOT NULL REFERENCES rewards(id),
  status        TEXT NOT NULL DEFAULT 'pending',
  fulfilled_at  TIMESTAMPTZ
);

CREATE TABLE team_battles (
  id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  team_a_id UUID REFERENCES teams(id),
  team_b_id UUID REFERENCES teams(id),
  metric    TEXT NOT NULL,
  period    DATERANGE NOT NULL,
  score_a   NUMERIC(10,2),
  score_b   NUMERIC(10,2),
  winner_id UUID REFERENCES teams(id)
);

-- ---------------------------------------------------------------------
-- 7. AI / Insights
-- ---------------------------------------------------------------------
CREATE TABLE ai_insights (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id    UUID NOT NULL REFERENCES tenants(id),
  scope_type   TEXT NOT NULL,
  scope_id     UUID,
  kind         TEXT NOT NULL,
  severity     TEXT NOT NULL DEFAULT 'info',
  title        TEXT NOT NULL,
  body         TEXT,
  evidence     JSONB,
  status       TEXT NOT NULL DEFAULT 'open',
  created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE ai_suggestions (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id      UUID NOT NULL REFERENCES users(id),
  kind         TEXT NOT NULL,
  payload      JSONB NOT NULL,
  accepted     BOOLEAN,
  dismissed_at TIMESTAMPTZ,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE embeddings (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id   UUID NOT NULL REFERENCES tenants(id),
  owner_type  TEXT NOT NULL,
  owner_id    UUID NOT NULL,
  model       TEXT NOT NULL,
  vector      vector(1536) NOT NULL
);
CREATE INDEX embeddings_vec_idx
  ON embeddings USING ivfflat (vector vector_cosine_ops) WITH (lists = 100);

CREATE TABLE risk_alerts (
  id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id   UUID REFERENCES users(id),
  kind      TEXT NOT NULL,            -- burnout | churn | quiet_quitting
  score     NUMERIC(5,2),
  signals   JSONB,
  opened_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 8. System
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  id          BIGSERIAL PRIMARY KEY,
  tenant_id   UUID NOT NULL REFERENCES tenants(id),
  actor_id    UUID REFERENCES users(id),
  action      TEXT NOT NULL,
  entity_type TEXT,
  entity_id   UUID,
  before      JSONB,
  after       JSONB,
  ip          INET,
  ua          TEXT,
  prev_hash   TEXT,
  hash        TEXT NOT NULL,
  at          TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX audit_tenant_time_idx ON audit_logs(tenant_id, at DESC);

CREATE TABLE notifications (
  id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id    UUID NOT NULL REFERENCES users(id),
  kind       TEXT NOT NULL,
  title      TEXT NOT NULL,
  body       TEXT,
  link       TEXT,
  read_at    TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE notification_prefs (
  user_id UUID REFERENCES users(id) ON DELETE CASCADE,
  channel TEXT NOT NULL,        -- email | sms | push | in_app | whatsapp
  kind    TEXT NOT NULL,
  enabled BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (user_id, channel, kind)
);

CREATE TABLE files (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL REFERENCES tenants(id),
  owner_id        UUID REFERENCES users(id),
  url             TEXT NOT NULL,
  kind            TEXT,
  size            BIGINT,
  hash            TEXT,
  virus_scanned_at TIMESTAMPTZ
);

-- ---------------------------------------------------------------------
-- 9. Row-Level Security (illustrative; all tables enable on tenant_id)
-- ---------------------------------------------------------------------
ALTER TABLE clients ENABLE ROW LEVEL SECURITY;
CREATE POLICY clients_tenant_isolation ON clients
  USING (tenant_id = current_setting('app.tenant_id')::uuid);

ALTER TABLE tasks ENABLE ROW LEVEL SECURITY;
CREATE POLICY tasks_tenant_isolation ON tasks
  USING (tenant_id = current_setting('app.tenant_id')::uuid);

-- (Repeat similar policies for every tenant-scoped table.)
