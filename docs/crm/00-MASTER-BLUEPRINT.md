# Hala Career — Operational Intelligence CRM
## Master Blueprint (Vision → Architecture → Roadmap)

> **Tagline:** “The CRM that runs the company while management sleeps.”
> **Codename:** `HalaOps`
> **Positioning:** Operational Intelligence System — وليس CRM مبيعات.
> **Inspiration mix:** Monday × ClickUp × HubSpot × Linear × Notion × Duolingo × AI Copilot.

---

## فهرس المخطط

1. [Product Vision](#1-product-vision)
2. [Full System Architecture](#2-full-system-architecture)
3. [Database Design](#3-database-design)
4. [UX Flow & User Journeys](#4-ux-flow--user-journeys)
5. [Feature Breakdown — 14 Modules](#5-feature-breakdown--14-modules)
6. [Gamification Mechanics](#6-gamification-mechanics)
7. [KPI & Performance Intelligence](#7-kpi--performance-intelligence)
8. [AI Logic & Copilot](#8-ai-logic--copilot)
9. [Dashboard Structure](#9-dashboard-structure)
10. [API Structure](#10-api-structure)
11. [Permission System](#11-permission-system)
12. [Tech Stack](#12-tech-stack)
13. [Deployment Architecture](#13-deployment-architecture)
14. [Scalability Plan](#14-scalability-plan)
15. [Monetization Ideas](#15-monetization-ideas)
16. [Automation Ideas](#16-automation-ideas)
17. [Anti-Cheat & Anti-Manipulation Layer](#17-anti-cheat--anti-manipulation-layer)
18. [Future Expansion](#18-future-expansion)
19. [Competitive Advantages](#19-competitive-advantages)
20. [Roadmap — MVP → V2 → V3 → Enterprise](#20-roadmap)

---

## 1. Product Vision

### 1.1 المشكلة الجوهرية
شركات التوظيف والتشغيل والتطوير المهني (مثل هلا كارير) تعاني من:
- **عدم رؤية حقيقية** لمن يعمل ومن يتظاهر.
- **اعتماد على تقارير الموظفين أنفسهم** → بيانات ملوّثة.
- **فقدان السياق** بين Sales / Recruitment / Operations / Training.
- **بيروقراطية متابعة** تستهلك وقت المديرين.
- **انسحاب صامت Quiet Quitting** بدون أي إشارات.

### 1.2 الرؤية في جملة واحدة
> **HalaOps** هو الجهاز العصبي للشركة: يرى كل مهمة، يقيس كل دقيقة، يكتشف الأنماط، ويحوّل الموظفين إلى لاعبين مدمنين على الإنجاز — دون كذب إداري ولا تلاعب بالأرقام.

### 1.3 المبادئ الخمسة (Design Principles)
1. **Truth by Default** — لا تُسجَّل بيانات يكتبها الموظف فقط؛ كل بيانات الأداء مشتقة آليًا من أحداث حقيقية (events).
2. **Calm Software** — تشتيت أقل، تركيز أكثر. الإشعار يجب أن يستحق المقاطعة.
3. **Make Work Feel Like a Game** — لكن بدون تحويل الشركة إلى Casino.
4. **Insights > Reports** — لا نعرض جداول، نعرض قرارات.
5. **Bilingual-Native (AR/EN)** — RTL/LTR من اليوم الأول، ليس Translation Layer.

### 1.4 النجاح كيف يُقاس (North Star)
- **Active Truth Index (ATI)** = نسبة الموظفين الذين أحداثهم الفعلية تطابق ما يدّعونه ≥ 90%.
- **Time to Insight** ≤ 3 ثوانٍ من فتح الـ Dashboard لرؤية أهم 3 قرارات اليوم.
- **Engagement Streak** = متوسط أيام الـ streak اليومي للموظفين ≥ 12 يومًا.

---

## 2. Full System Architecture

### 2.1 نمط معماري عالي المستوى
**Modular Monolith أولًا → Microservices لاحقًا**, مبني على:
- **Event-Driven Core** (كل شيء حدث Event ينشر على Event Bus).
- **CQRS-Lite**: فصل المسارات الكتابية (commands) عن القراءة (queries) في الوحدات الثقيلة (Tasks, Analytics).
- **Read Models** مخصصة لكل Dashboard (مادية، مفهرسة، سريعة).

### 2.2 الطبقات
```
┌────────────────────────────────────────────────────────────────┐
│                      Client Layer                               │
│  Next.js (Web)  │  React Native (Mobile)  │  Client Portal     │
└─────────────────┬──────────────────┬───────────────────────────┘
                  │                  │
            REST + tRPC         WebSocket (Realtime)
                  │                  │
┌─────────────────▼──────────────────▼───────────────────────────┐
│                   API Gateway (NestJS)                          │
│  Auth │ Rate Limit │ i18n │ Audit │ Tenant Resolver            │
└─────────────────┬──────────────────────────────────────────────┘
                  │
   ┌──────────────┼──────────────────────────────┐
   │              │                              │
┌──▼──────┐  ┌────▼─────┐  ┌─────────┐  ┌────────▼────────┐
│ Core    │  │ Analytics│  │  AI     │  │  Notification   │
│ Services│  │  Service │  │ Service │  │     Service     │
│(Modular │  │(ClickHouse│  │(Claude  │  │ (FCM/WebSocket/ │
│Monolith)│  │ + Cube.js)│  │ + RAG)  │  │  Email/SMS)     │
└──┬──────┘  └────┬─────┘  └────┬────┘  └────────┬────────┘
   │              │             │                │
   └──────────────┴─────────────┴────────────────┘
                  │
        Event Bus (Redis Streams / Kafka في V2)
                  │
   ┌──────────────┼──────────────┐
   │              │              │
┌──▼─────┐  ┌─────▼────┐  ┌──────▼───────┐
│Postgres│  │ClickHouse│  │ Elasticsearch│
│(OLTP)  │  │(OLAP/    │  │(Search/Logs) │
│        │  │ Events)  │  │              │
└────────┘  └──────────┘  └──────────────┘
                  │
              ┌───▼───┐
              │ Redis │  (Cache + Pub/Sub + Queue)
              └───────┘
```

### 2.3 المكونات الأساسية
| المكون | التقنية | الدور |
|--------|---------|------|
| **API Gateway** | NestJS + Fastify | REST + tRPC + WS, Auth, i18n, Audit |
| **Realtime** | Socket.io + Redis Adapter | Live tasks, presence, leaderboards |
| **Workers** | BullMQ (Redis) | Recurring tasks, AI jobs, exports |
| **Search** | Elasticsearch | Tasks, employees, clients, knowledge |
| **OLAP** | ClickHouse | Events, analytics, time-series |
| **Vector DB** | pgvector (V1) → Qdrant (V3) | RAG, semantic similarity |
| **Storage** | S3-compatible (R2/MinIO) | Files, voice notes, exports |
| **Queue** | BullMQ → NATS JetStream (V2) | Async + Event Bus |
| **Cache** | Redis | Session, hot reads, rate-limit |
| **Secrets** | HashiCorp Vault | Keys, API tokens |

### 2.4 لماذا Modular Monolith أولًا؟
- شركة في طور النمو لا تحتاج 12 microservice من اليوم الأول.
- كل **Module** له `domain/`, `application/`, `infrastructure/`, `interface/` (Hexagonal).
- عند الحاجة، أي module يُستخرج كـ microservice دون إعادة كتابة (نفس الـ contracts).

---

## 3. Database Design

### 3.1 المخطط العام (PostgreSQL — OLTP)
> **Multi-tenant** عبر `tenant_id` + Row-Level Security (RLS).

#### Core Identity
```sql
tenants (id, slug, name, locale_default, plan, settings JSONB)
users (id, tenant_id, email, phone, password_hash, status, locale,
       timezone, avatar_url, created_at, last_seen_at)
roles (id, tenant_id, key, name_ar, name_en, level INT)
permissions (id, key, scope, action)
role_permissions (role_id, permission_id)
user_roles (user_id, role_id, scope_type, scope_id) -- e.g. role on a team
auth_sessions (id, user_id, ip, ua, device_id, expires_at, revoked_at)
auth_2fa (user_id, type, secret, verified_at)
```

#### Org Structure
```sql
departments (id, tenant_id, name_ar, name_en, parent_id, manager_id)
teams (id, tenant_id, department_id, name, leader_id, color)
team_members (team_id, user_id, role_in_team, joined_at, left_at)
positions (id, tenant_id, title_ar, title_en, level, salary_band)
employees (id, user_id, position_id, employment_type, hired_at,
           contract_end_at, reports_to_id, work_hours JSONB)
```

#### CRM Domain (Hala Career–Specific)
```sql
clients (id, tenant_id, type ENUM('company','candidate','partner'),
         name, industry, size, country, owner_id, stage, value_score,
         risk_score, custom_fields JSONB)
contacts (id, client_id, name, role, email, phone, locale)
deals (id, client_id, service_id, stage, amount, currency, probability,
       expected_close_at, actual_close_at, owner_id, lost_reason)
services (id, tenant_id, key, name_ar, name_en, category
          ENUM('recruitment','training','consulting','community','partnership'),
          base_price, duration_days, profit_margin)
candidates (id, client_id, cv_url, headline, skills JSONB, level,
            availability, salary_expectation, status)
placements (id, candidate_id, deal_id, employer_client_id,
            placed_at, probation_end_at, status)
training_programs (id, tenant_id, service_id, capacity, schedule JSONB)
training_enrollments (program_id, candidate_id, progress, score)
```

#### Tasks & Workflow
```sql
tasks (id, tenant_id, title, description, type, status, priority,
       assignee_id, reporter_id, parent_task_id, project_id,
       sla_minutes, due_at, started_at, completed_at, reopened_count,
       postponed_count, estimated_minutes, actual_minutes,
       custom_fields JSONB)
task_dependencies (task_id, depends_on_task_id, type)
task_watchers (task_id, user_id)
task_comments (id, task_id, user_id, body, body_voice_url, mentions JSONB)
task_attachments (id, task_id, file_url, kind, size)
task_checklists (id, task_id, item, done_by, done_at)
task_time_logs (id, task_id, user_id, started_at, ended_at,
                source ENUM('manual','auto_focus','calendar'))
task_history (id, task_id, actor_id, change JSONB, at) -- audit trail
projects (id, tenant_id, client_id, name, status, owner_id, sla_template_id)
sla_templates (id, tenant_id, name, rules JSONB)
recurring_tasks (id, tenant_id, template JSONB, rrule, next_run_at)
approvals (id, entity_type, entity_id, requester_id, approver_id,
           status, decided_at, reason)
escalations (id, entity_type, entity_id, level, triggered_at, resolved_at)
```

#### Performance Intelligence
```sql
events (id, tenant_id, actor_id, type, subject_type, subject_id,
        metadata JSONB, occurred_at)  -- mirrored to ClickHouse
performance_scores (id, user_id, period, performance, reliability,
                    leadership, consistency, growth, computed_at)
performance_components (score_id, component, value, weight)
reviews (id, reviewee_id, reviewer_id, type
         ENUM('manager','peer','client','self','ai'),
         period, scores JSONB, notes, submitted_at)
client_feedback (id, client_id, deal_id, employee_id, csat, nps,
                 comment, submitted_at)
attendance (id, user_id, check_in_at, check_out_at, source, location)
focus_sessions (id, user_id, started_at, ended_at, app_context)
```

#### Gamification
```sql
xp_ledger (id, user_id, delta, source_type, source_id, reason, at)
levels (id, level, xp_required, perks JSONB)
badges (id, key, name_ar, name_en, rarity, criteria JSONB, icon_url)
user_badges (user_id, badge_id, awarded_at, evidence JSONB)
streaks (user_id, kind, current, longest, last_activity_at)
missions (id, tenant_id, title, kind ENUM('daily','weekly','seasonal','team'),
          rules JSONB, reward_xp, reward_coins, starts_at, ends_at)
mission_progress (mission_id, user_id, progress JSONB, completed_at)
leaderboards (id, scope, period, snapshot JSONB, computed_at)
coins_ledger (id, user_id, delta, source, at)
rewards (id, name, cost_coins, stock, kind)
reward_redemptions (id, user_id, reward_id, status, fulfilled_at)
team_battles (id, team_a_id, team_b_id, metric, period,
              score_a, score_b, winner_id)
```

#### AI / Insights
```sql
ai_insights (id, tenant_id, scope_type, scope_id, kind, severity,
             title, body, evidence JSONB, status, created_at)
ai_suggestions (id, user_id, kind, payload JSONB, accepted, dismissed_at)
embeddings (id, owner_type, owner_id, model, vector vector(1536))
risk_alerts (id, user_id, kind, score, signals JSONB, opened_at)
```

#### System
```sql
audit_logs (id, tenant_id, actor_id, action, entity_type, entity_id,
            before JSONB, after JSONB, ip, ua, at)
notifications (id, user_id, kind, title, body, link, read_at, created_at)
notification_prefs (user_id, channel, kind, enabled)
files (id, tenant_id, owner_id, url, kind, size, hash, virus_scanned_at)
```

### 3.2 OLAP Schema (ClickHouse)
كل `events` تُكرَّر في ClickHouse مع partitioning شهري:
```sql
CREATE TABLE events_oltp (
  tenant_id UUID, occurred_at DateTime64(3), type LowCardinality(String),
  actor_id UUID, subject_type LowCardinality(String), subject_id UUID,
  metadata String  -- JSON
) ENGINE = MergeTree
  PARTITION BY toYYYYMM(occurred_at)
  ORDER BY (tenant_id, type, occurred_at);
```
ثم Materialized Views للـ:
- `daily_user_productivity`
- `task_lifecycle_metrics`
- `client_health_signals`
- `service_profitability_rollup`

### 3.3 لماذا هذا التصميم؟
- **events** مصدر الحقيقة الوحيد للأداء → يستحيل تزوير الـ KPIs بتعديل سجل واحد.
- **JSONB** للحقول المخصصة → مرونة بدون migrations لكل عميل enterprise.
- **RLS** على tenant_id يمنع تسرب بيانات بين شركات حتى لو كان هناك bug في الكود.

---

## 4. UX Flow & User Journeys

### 4.1 المبادئ
- **3-second rule**: من فتح الصفحة لاتخاذ القرار ≤ 3 ثوانٍ.
- **One-Hand Mobile**: العمليات الشائعة تُنجز بإصبع واحد.
- **Command Palette** (`⌘K`) كبديل للقوائم.
- **No empty states** — دائمًا اقتراح ذكي مكان الفراغ.

### 4.2 Personas الرئيسية
| Persona | Goal | Pain | Magic Moment |
|---------|------|------|--------------|
| **CEO / Founder** | يرى الشركة لحظيًا | يدفن في تقارير | فتح Pulse Dashboard ويرى 3 قرارات الآن |
| **HR Manager** | يتابع 40 موظف | تقارير متأخرة | Risk Radar يكشف موظف على وشك الاستقالة |
| **Team Leader** | يوزّع شغل عادل | Workload أعمى | Smart Assignment يقترح أفضل person لكل task |
| **Recruiter** | يضع 10 مرشحين/شهر | تتبع متعدد | Pipeline Kanban + AI Match Score |
| **Sales** | يقفل صفقات | Follow-up مفقود | AI Next-Best-Action لكل deal |
| **Employee** | يعرف أولوياته | تشتت | Today View: 3 things only |
| **Client** | يتابع تقدم خدمته | لا visibility | Client Portal بـ progress live |

### 4.3 رحلة الموظف اليومية (Magic Loop)
```
صباحًا  →  Daily Brief (AI):
           "اليوم 3 مهام، 1 منها SLA ينتهي 12pm، فرصة streak +1"
           [Start Focus] ──► Focus Mode (60min Pomodoro + auto time log)
                            └─ إشعارات تتوقف، Slack صامت
بعد كل مهمة → XP animation + لو حقّق milestone → Badge unlock
ظهرًا   →  Mission Check: "أكمل 5 مهام اليوم لتفوز Team Battle"
مساءً   →  Day Recap: "أنجزت 4/5، Performance Score 87, Streak 9 days"
           يطلب من الـ AI ملخص لما تعلّمه (Growth Score input)
```

### 4.4 رحلة المدير (Operational Awareness Loop)
```
فتح Pulse Dashboard:
  ├─ Top 3 Decisions Now (AI-curated)
  │    1. Approve 2 escalations
  │    2. Reassign Khaled's overload (15 tasks)
  │    3. Reach out to client X (NPS dropped)
  ├─ Heatmap: من شغّال فعلًا الآن
  ├─ Risk Radar: 1 employee burnout, 1 client churn
  └─ Revenue Pulse: this week vs forecast
```

### 4.5 رحلة العميل (Client Portal)
```
Login → Service Timeline (مثل Domino's Tracker للتوظيف):
  [Brief] → [Sourcing] → [Shortlist] → [Interviews] → [Offer] → [Placement]
  + Live messages with account manager
  + NPS prompt every milestone
```

### 4.6 Information Architecture (Top Nav)
```
Home (Today)  •  Tasks  •  Pipeline (CRM)  •  People  •  Insights  •
Learn (Training)  •  Arena (Gamification)  •  Inbox  •  ⌘K
```

---

## 5. Feature Breakdown — 14 Modules

> كل module مصمم بحدود واضحة، API مستقل، إمكانية إيقافه/تشغيله per tenant.

| # | Module | الوصف المختصر | Killer Feature |
|---|--------|---------------|----------------|
| 1 | **Identity & Access** | Auth, Roles, 2FA, SSO | Just-in-Time Access (طلب صلاحية للحظة) |
| 2 | **Org & People** | الهيكل، الفرق، الموظفين | Org Chart حي يتغيّر تلقائيًا |
| 3 | **Tasks & Workflow** | مهام، subtasks، SLA | **SLA Auto-Escalation Trees** |
| 4 | **CRM (Clients/Deals)** | Pipeline، شركات، عملاء | Deal DNA: AI يكتشف الصفقات الشبيهة الناجحة |
| 5 | **Recruitment** | مرشحين، vacancies، placements | **AI Match Score + Bias Audit** |
| 6 | **Training & Programs** | برامج، تسجيلات، تقدّم | Learning XP يدخل في Gamification |
| 7 | **Performance Intelligence** | Scores، Reviews، Feedback | **Truth Index** ضد التزوير |
| 8 | **Gamification (Arena)** | XP، Levels، Battles | Seasonal Leagues مثل Apex/Duolingo |
| 9 | **Insights & Analytics** | Dashboards، Reports | **Decision Cards** بدل التقارير |
| 10 | **AI Copilot** | Assistant، Recommendations | RAG على بيانات الشركة |
| 11 | **Communication** | Comments، Mentions، Voice | **Voice-to-Action**: ملاحظة صوتية تتحول لـ task |
| 12 | **Notifications** | Channels، Prefs، Digest | **Calm Mode**: دفعة واحدة كل ساعتين |
| 13 | **Client Portal** | بوابة العميل | Domino-style live tracker |
| 14 | **Admin & Audit** | Settings، Logs، Billing | Full event replay (time travel) |

### 5.1 Killer Features (تفصيل)
- **SLA Auto-Escalation Trees**: لو SLA اقترب من النفاد → reassign اقتراحي → لو تجاوز → notify TL → لو فات → notify منtt CEO + خصم XP من المسؤول.
- **AI Match Score**: لكل candidate-vacancy: درجة + شرح "لماذا" + إشارات Bias (gender/age) قبل الإرسال للعميل.
- **Truth Index**: مقارنة بين ما يقوله الموظف (مهمة "اكتملت") والإشارات الفعلية (focus minutes، commits، client confirm، file changes).
- **Voice-to-Action**: مدير يسجل voice note → AI يحوّلها لـ task مع assignee + due + priority.
- **Decision Cards**: بدل تقرير 20 صفحة، 5 بطاقات: "افعل هذا الآن لأن…".

---

## 6. Gamification Mechanics

### 6.1 طبقات النظام
1. **XP & Levels** — تقدّم خطي طويل المدى.
2. **Streaks** — التزام يومي.
3. **Missions** — أهداف قصيرة المدى (يومية/أسبوعية/موسمية).
4. **Badges** — تكريم للسلوك النوعي (نادر).
5. **Coins & Rewards** — اقتصاد قابل للصرف (حقيقي).
6. **Leaderboards** — منافسة (محدودة لتجنب السمية).
7. **Leagues** — Bronze→Silver→Gold→Platinum→Diamond (موسمية، تنزل وتطلع).
8. **Team Battles** — قسم ضد قسم على KPI محدد.

### 6.2 XP Formula (مقاوم للـ Farming)
```
xp(event) = base[event] × quality × difficulty × novelty × anti_spam

quality      = (csat or peer_review or sla_hit_ratio) ∈ [0.5, 1.5]
difficulty   = log(1 + estimated_minutes) × priority_weight
novelty      = 1 / (1 + repeats_in_window)   // يقل لو نفس النوع متكرر
anti_spam    = 0 لو الـ event حدث في < 60s من السابق المماثل
```
**ملاحظة:** XP لا يُمنح إلا بعد:
- Confirm من النظام (event حقيقي).
- لو المهمة تُعاد فتحها (reopened) → XP يُسحب.
- Cap يومي = 1.5× المتوسط الأسبوعي للمستخدم نفسه (يمنع marathon farming).

### 6.3 Badges (أمثلة)
| Rarity | Badge | الشرط |
|--------|-------|------|
| Common | First Blood | أول مهمة منجزة |
| Rare | Night Owl | 5 مهام بعد 10pm بجودة ≥ 4/5 |
| Epic | The Closer | 10 صفقات/شهر بقيمة فوق المتوسط |
| Legendary | Phoenix | تحويل عميل خاسر إلى مربح |
| Mythic | The Architect | تصميم workflow اعتُمد على مستوى الشركة |

### 6.4 Seasonal Engine (Duolingo-style)
- موسم = 4 أسابيع.
- كل لاعب في League حسب XP الموسم السابق.
- آخر 20% ينزلون. أول 20% يصعدون.
- جوائز موسمية حقيقية (يوم إجازة، voucher، شهادة).

### 6.5 Anti-Toxicity Guardrails
- Leaderboards خاصة بالـ peer group (نفس الدور) لا "الكل ضد الكل".
- لاعب جديد محمي 30 يومًا (لا يقع في bottom 20%).
- Manager يستطيع إيقاف Battle لو رصد سلوك سام.
- لا يوجد "خسارة XP" بسبب لاعب آخر — فقط بسبب عملك أنت.

---

## 7. KPI & Performance Intelligence

### 7.1 الـ Five Pillars (تظهر للموظف وللمدير)
| Pillar | يقيس | مكوّناته |
|--------|------|----------|
| **Performance** | الإنتاجية | tasks/day, SLA hit %, output quality |
| **Reliability** | الاعتماد عليه | on-time %, response time, attendance variance |
| **Leadership** | الأثر على الفريق | mentions positives, peer reviews, knowledge shared |
| **Consistency** | الاستقرار | std dev of daily output, streak length |
| **Growth** | التطوّر | skills acquired, training, improving trend |

### 7.2 صيغة Performance Score (مثال)
```
P = 0.35 × completion_rate
  + 0.25 × sla_hit_rate
  + 0.20 × quality_score
  + 0.10 × peer_score
  + 0.10 × client_csat
clipped to [0, 100]
```
كل مكوّن يأتي من **events** فقط — لا إدخال يدوي.

### 7.3 Source Signals (لمنع التزوير)
| Signal | المصدر | لا يمكن تزويره لأن… |
|--------|--------|---------------------|
| Task completed | event من النظام | يحتاج state transition + إشارات مساندة |
| SLA hit | محسوب آليًا | due_at مسجل عند الإنشاء فقط |
| Quality | client_feedback / peer_review | يأتي من طرف ثالث |
| Focus minutes | focus_sessions | يحتاج activity signals |
| Reopen penalty | task.reopened_count | يُخصم من المسؤول عن إغلاقها |

### 7.4 Reliability Score Example
```
R = 0.40 × on_time_completion
  + 0.25 × response_time_score
  + 0.20 × attendance_score
  + 0.15 × commitment_kept_ratio
```

### 7.5 Manager-Facing Insights
- **Bottleneck Map**: أين تتوقف المهام أكثر.
- **Burnout Radar**: focus_minutes ↑ لكن quality ↓ + after-hours ↑.
- **Promotion Candidates**: Growth ↑ + Leadership ↑ + 90+ ل 3 شهور.
- **Quiet Quitting Alert**: streaks تنكسر + reviews تتأخر + comments يقلّ.

---

## 8. AI Logic & Copilot

### 8.1 طبقات الذكاء
1. **Reactive AI** — يستجيب لطلب (chat, summarize).
2. **Proactive AI** — يولّد insights/suggestions تلقائيًا.
3. **Embedded AI** — مدمج في كل field (smart fill, classify, route).
4. **Predictive AI** — تنبؤات (deal close prob, churn, burnout).

### 8.2 الميزات الفعلية (ليست شكلية)
| الميزة | المدخل | النموذج | الإخراج |
|--------|--------|---------|---------|
| Smart Task Assignment | task + workload + skills | rules + ML ranking | top 3 assignees + reason |
| Priority Detection | task title/desc + context | LLM classification | P0–P3 + due suggestion |
| Meeting Summary | transcript | LLM summarization | action items as tasks |
| AI Performance Review | events for period | LLM + structured data | draft 360° review |
| Burnout Detection | focus, after-hours, sentiment | anomaly detection | risk_alert |
| Deal Win Prediction | deal history + signals | gradient boosting | probability % + drivers |
| Candidate Match | vacancy + cv + history | embeddings + LLM | score + bias check |
| Workflow Automation | natural language rule | LLM → rule DSL | published automation |
| Internal Chat Assistant | any question | RAG over org data | answer + sources |
| Voice-to-Action | voice note | STT + LLM | structured task |

### 8.3 RAG Architecture
```
User question
   │
   ▼
Query rewriter (LLM)  ──►  Hybrid search:
                           ├─ pgvector (semantic)
                           └─ Elasticsearch (keyword)
                                   │
                                   ▼
                           Permission filter (RLS-aware)
                                   │
                                   ▼
                           Re-ranker (cross-encoder)
                                   │
                                   ▼
                           LLM with cited context
                                   │
                                   ▼
                          Answer + source links + confidence
```

### 8.4 Model Strategy
- **Primary LLM**: Claude (Sonnet لأكثر المهام، Opus للـ deep reviews، Haiku للـ classification).
- **Embeddings**: voyage-3 / OpenAI text-embedding-3-large.
- **STT**: Whisper (Arabic + English).
- **Tabular ML**: LightGBM in-house لـ predictions.
- **Cost control**: prompt caching + small-first routing + batch jobs at night.

### 8.5 Safety & Hallucination Control
- كل AI insight يحمل `evidence JSONB` بمصادر فعلية من الـ DB.
- AI لا يعدّل بيانات بدون موافقة بشر (suggestion، ليس action).
- Red-team prompts تُختبر CI.
- Confidence threshold: لو < 70% → "أحتاج توضيح".

---

## 9. Dashboard Structure

### 9.1 ثلاثة Dashboards مختلفة جذريًا

#### A) **CEO Pulse** (تحكم تنفيذي)
```
┌────────────────────────────────────────────────────┐
│  Top 3 Decisions Today        │  Revenue Pulse     │
│  (AI-curated, 1-tap action)   │  (week vs forecast)│
├───────────────────────────────┼────────────────────┤
│  Company Heatmap              │  Risk Radar        │
│  (who's working — live)       │  (people + clients)│
├───────────────────────────────┼────────────────────┤
│  Service Profitability        │  Pipeline Velocity │
│  (which earns most/least)     │  (avg days/stage)  │
├───────────────────────────────┴────────────────────┤
│  AI Weekly Briefing (audio + text)                  │
└────────────────────────────────────────────────────┘
```

#### B) **Team Leader Cockpit**
```
- Workload Balance (per member, color-coded)
- Tasks at Risk (SLA close)
- Top Performers / At-Risk this week
- Suggested Reassignments
- Team Battle status
- Pending approvals
```

#### C) **Employee Today**
```
- 3 things to do today (max)
- Streak + XP progress to next level
- Today's mission
- Recent achievements
- Calm focus button
```

### 9.2 مبادئ التصميم
- لا أكثر من **6 widgets** per dashboard.
- كل widget له **1 action** واضح.
- **Drill-down** بنقرة (لا navigation معقد).
- **Real-time** عبر WebSocket.

---

## 10. API Structure

### 10.1 الأنماط
- **REST** للموارد العامة، **tRPC** للـ type-safe Next.js calls.
- **GraphQL** غير مطلوب MVP (تعقيد > فائدة هنا).
- **Webhooks** للـ outbound events.
- **WebSocket** للـ realtime (presence, tasks, leaderboards).

### 10.2 Resource Naming
```
GET    /api/v1/tasks?status=in_progress&assignee=me
POST   /api/v1/tasks
GET    /api/v1/tasks/:id
PATCH  /api/v1/tasks/:id
POST   /api/v1/tasks/:id/transitions   // state machine action
POST   /api/v1/tasks/:id/comments
POST   /api/v1/tasks/:id/time-logs/start
POST   /api/v1/tasks/:id/time-logs/stop

GET    /api/v1/clients  /  /deals  /  /candidates
GET    /api/v1/dashboards/ceo|leader|me
GET    /api/v1/insights?scope=user&id=:id
POST   /api/v1/ai/copilot/chat       (SSE stream)
POST   /api/v1/ai/voice-to-action

GET    /api/v1/arena/leaderboard?period=season
GET    /api/v1/arena/missions/active
POST   /api/v1/arena/missions/:id/claim
```

### 10.3 Conventions
- **Idempotency-Key** header on POST.
- **ETag** + `If-Match` على PATCH.
- **Cursor pagination** فقط (لا offset).
- **PartialResponse**: `?fields=id,title,assignee.name`.
- **Localization**: `Accept-Language: ar` يُعيد الـ name_ar.
- **Rate limit**: 600/min/user; 60/min على AI endpoints.

### 10.4 WebSocket Channels
```
user:{id}              → personal notifications, XP, badges
team:{id}              → tasks, presence
tenant:{id}:leaderboard → live ranking
task:{id}              → comments, status, watchers
```

---

## 11. Permission System

### 11.1 النموذج
**RBAC + ABAC Hybrid**:
- **RBAC** للأدوار العامة (CEO, HR…).
- **ABAC** للقواعد المرنة (`user.team_id = resource.team_id`).
- **Scopes**: role قد يُمنح على tenant / department / team / resource.

### 11.2 Roles المعرفة مسبقًا
| Role | Level | الصلاحيات |
|------|-------|-----------|
| Super Admin | 100 | كل شيء + billing |
| CEO | 90 | كل شيء عدا billing |
| HR | 70 | people, performance, attendance |
| Operations | 70 | tasks, projects, clients |
| Team Leader | 60 | على فريقه فقط |
| Recruiter | 50 | candidates, placements |
| Sales | 50 | clients, deals |
| Trainer | 50 | training programs |
| Employee | 30 | الذاتي + ما خُصّص له |
| Client Portal | 10 | بوابة محدودة |

### 11.3 Permissions Examples
```
tasks.create / read.team / read.tenant / update.assigned / delete.any
clients.export
employees.terminate    (CEO + HR only, with approval)
salary.read            (CEO + HR only)
ai.run_copilot         (everyone, but rate-limited)
arena.admin            (CEO + HR)
```

### 11.4 Just-in-Time Access
موظف يحتاج وصول مؤقت لـ resource → يطلب → manager يوافق → permission تُمنح لمدة محددة → تنتهي تلقائيًا → كل شيء logged.

### 11.5 Security Layers
- 2FA إجباري للأدوار ≥ 60.
- Session bound to device fingerprint.
- IP allowlist per role (optional).
- All `salary.*`, `performance.read.others`, `audit.read` → MFA-step-up.
- **Audit Logs** على كل تعديل sensitive، immutable (append-only + hash chain).

---

## 12. Tech Stack

### 12.1 الاختيارات النهائية
| الطبقة | التقنية | السبب |
|--------|---------|------|
| **Frontend Web** | Next.js 15 (App Router) + React 19 | SSR/Streaming, RSC, i18n native |
| **UI** | TailwindCSS + shadcn/ui + Radix | سرعة، accessible، RTL سهل |
| **Charts** | ECharts + visx | rich + custom |
| **State** | Zustand + TanStack Query + tRPC | بسيط، typed |
| **Forms** | React Hook Form + Zod | validation موحدة |
| **Mobile** | React Native (Expo) | code reuse |
| **Backend** | NestJS (Fastify) + TypeScript | modular, opinionated |
| **ORM** | Prisma + Kysely (للـ heavy queries) | DX + perf |
| **DB OLTP** | PostgreSQL 16 + RLS + pgvector | ACID + vectors |
| **DB OLAP** | ClickHouse | events at scale |
| **Search** | Elasticsearch / OpenSearch | full-text AR/EN |
| **Cache/Queue** | Redis 7 + BullMQ | بسيط |
| **Realtime** | Socket.io + Redis adapter | mature |
| **Storage** | Cloudflare R2 / S3 | cheap egress |
| **AI** | Claude (Anthropic) + Whisper + LightGBM | quality |
| **Auth** | Auth.js + WorkOS (SSO) | enterprise-ready |
| **i18n** | next-intl (web), i18next (RN) | RTL/LTR native |
| **Email** | Resend / SES | dev-friendly |
| **SMS/WA** | Twilio / WhatsApp Cloud API | regional |
| **Observability** | OpenTelemetry + Grafana + Loki + Tempo | open + powerful |
| **Errors** | Sentry | standard |
| **Feature Flags** | Unleash / GrowthBook | gradual rollout |
| **CI/CD** | GitHub Actions + Argo CD | gitops |
| **IaC** | Terraform + Helm | repeatable |
| **Containers** | Docker → Kubernetes (V2) | maturity path |
| **Secrets** | Vault | rotation |
| **Testing** | Vitest, Playwright, k6, Pact | full pyramid |

### 12.2 لماذا ليس Laravel/PHP رغم الموقع الحالي PHP؟
- الموقع التسويقي يبقى PHP (لا حاجة لإعادة كتابته).
- HalaOps منتج SaaS مستقل، يحتاج realtime + queues + AI integration → Node/NestJS أفضل.
- PHP backend ممكن لكن النظام البيئي للـ realtime + queues + AI tooling أنضج في Node.

---

## 13. Deployment Architecture

### 13.1 البيئات
- **dev** (developer laptop, docker-compose)
- **staging** (يطابق prod 1:1، بيانات synthetic)
- **prod** (multi-AZ)
- **canary** (5% traffic للـ releases)

### 13.2 Topology (V1)
```
                         ┌─────────────┐
                         │  Cloudflare │  (DNS + WAF + CDN + R2)
                         └──────┬──────┘
                                │
                         ┌──────▼──────┐
                         │  Load Bal.  │
                         └──────┬──────┘
                                │
              ┌─────────────────┼─────────────────┐
              ▼                 ▼                 ▼
        ┌──────────┐      ┌──────────┐      ┌──────────┐
        │ Next.js  │      │ Next.js  │      │ Next.js  │
        │ (Vercel/ │      │  (Edge)  │      │          │
        │  k8s)    │      │          │      │          │
        └────┬─────┘      └────┬─────┘      └────┬─────┘
             └─────────────────┼─────────────────┘
                               ▼
                        ┌─────────────┐
                        │ NestJS API  │ (3+ replicas, HPA)
                        └──────┬──────┘
                               │
       ┌───────────┬───────────┼───────────┬───────────┐
       ▼           ▼           ▼           ▼           ▼
   Postgres   ClickHouse   Elasticsearch  Redis     S3/R2
   (primary  (replicated)              (cluster)
    + 2 RR)
       │
       ▼
   PgBouncer
```

### 13.3 CI/CD
1. PR → test (unit + integration + e2e Playwright on key flows).
2. Merge to `main` → build images → push to registry.
3. Argo CD detects → deploys to staging → runs smoke + load (k6).
4. Manual gate → canary 5% → metrics ok → full rollout.
5. Rollback = git revert (gitops).

### 13.4 Data Strategy
- **Backups**: Postgres PITR every 5 min (WAL to R2), 30-day retention.
- **DR**: secondary region warm standby, RPO 5 min, RTO 30 min.
- **Migrations**: blue/green schema for breaking changes (expand → migrate → contract).

---

## 14. Scalability Plan

### 14.1 محاور النمو
| محور | استراتيجية |
|------|-----------|
| **Tenants** | Pooled DB حتى 500 tenant، ثم sharded by `tenant_id` |
| **Reads** | Read replicas + Redis cache + CDN |
| **Writes** | Event sourcing لقطاعات thrash (tasks, events) |
| **Search** | ES sharded by tenant_id |
| **Realtime** | Socket.io horizontal مع Redis adapter |
| **Analytics** | ClickHouse cluster (sharded + replicated) |
| **AI** | request-level cache + batch nightly jobs |

### 14.2 من Modular Monolith إلى Microservices (متى؟)
- **استخراج Notifications** أولًا (heavy fan-out, isolated).
- ثم **Analytics/AI** (heavy compute).
- ثم **Gamification** (heavy events).
- Core (Tasks/CRM) يبقى monolith أطول فترة.

### 14.3 حدود معروفة (سنُخطّط لها مسبقًا)
- Postgres write 5k tps per shard → بعدها sharding.
- ClickHouse 100k events/sec single node → cluster بسهولة.
- Redis 100k ops/sec → cluster mode.

---

## 15. Monetization Ideas

### 15.1 Tiers (SaaS)
| Plan | Target | Price (USD/user/mo) | Highlights |
|------|--------|---------------------|------------|
| **Starter** | < 15 user | $9 | Tasks + CRM + basic gamification |
| **Pro** | 15–100 | $19 | + AI Copilot + Insights + Client Portal |
| **Business** | 100–500 | $39 | + SSO + advanced analytics + automations |
| **Enterprise** | 500+ | Custom | + SLA + dedicated + on-prem option |

### 15.2 Add-ons
- **AI Credits** (per 1M tokens) — لمن يكثر استخدام Copilot.
- **Recruitment Module** — مدفوع منفصل (لشركات لا تحتاجه).
- **Training/LMS Module** — منفصل.
- **WhatsApp Channel** — منفصل (تكلفة API).
- **Premium Themes / White Label** — للـ Business+.

### 15.3 Revenue Levers
- **Marketplace**: شركاء يبيعون workflows جاهزة.
- **Certifications**: شهادات هلا كارير المعتمدة (paid exam).
- **Recruitment Success Fee**: في وحدة التوظيف، % من placement.
- **Data Insights as a Service**: تقارير سوق عمل (anonymized) للشركات.

### 15.4 Anti-Churn
- Annual discount 20%.
- "Pause subscription" بدلًا من cancel (يحفظ البيانات 90 يومًا).
- Health score يُنبّه CSM قبل churn.

---

## 16. Automation Ideas

### 16.1 No-Code Automation Builder
```
WHEN  task.priority = P0 AND task.assignee.workload > 8
THEN  notify team_leader
      AND suggest reassignment to top 3 alternatives
```

### 16.2 أمثلة جاهزة (Templates)
- **Stale Deal Reviver**: deal لم يُلمس 7 أيام → AI يقترح next-best-action → task يُنشأ.
- **Onboarding Auto**: موظف جديد → checklist 30/60/90 + buddy assignment.
- **Client Pulse**: NPS < 7 → escalation to account manager + recovery playbook.
- **Burnout Cooler**: focus_minutes > X لـ 5 أيام → suggest day off.
- **Recruitment Pipeline**: candidate stage = interview → reminder 24h قبل + AI prep brief.
- **Birthday/Anniversary**: تلقائي للموظفين والعملاء.
- **Weekly Pulse Email**: ملخص لكل manager صباح الإثنين.

### 16.3 Triggers الأساسية
- Time-based (cron).
- Event-based (event bus).
- Threshold-based (metric crossed).
- AI-based (anomaly detected).
- Webhook-based (external).

---

## 17. Anti-Cheat & Anti-Manipulation Layer

### 17.1 المخاطر المعروفة
| الخطر | المثال | المضاد |
|------|-------|--------|
| Task farming | إنشاء وإغلاق مهام وهمية | XP يحتاج صلة بـ event خارجي (client confirm، peer review، file change) |
| SLA gaming | تعديل due_at قبل النفاد | due_at مغلق عند الإنشاء، تعديله يحتاج approval + سجل |
| Self-review inflation | تقييمات ذاتية مرتفعة | self review وزنه 5%، الباقي peer/client/AI |
| Buddy reviews | شخصان يبادلان تقييمات عالية | تحليل شبكة Reviewers (graph anomaly) |
| Reopen dump | إغلاق سريع ثم reopen ينقص XP المسؤول | reopened_count يخصم |
| Shadow work | خصم من زميل بسبب reassignment | XP يُحسب على last_assignee فقط لو مرّ عليه > 20% من الوقت |
| Burnout to win | ساعات إضافية لتسلق leaderboard | cap يومي على XP + burnout alert يقلّل النقاط |

### 17.2 Truth Index
لكل user/period:
```
TI = corroborated_signals / claimed_signals
   corroborated = task مغلقة + (client_confirm OR peer_review OR file_change OR commit OR meeting attended)
   claimed = task مغلقة فقط
```
TI < 0.6 → flag للـ HR.

### 17.3 Tamper-Evident Audit
- audit_logs مع hash chain (كل سجل يحوي hash السابق).
- export موقّع تشفيريًا.

---

## 18. Future Expansion

### 18.1 Modules المستقبلية
- **OKR Module** (Quarterly objectives مرتبطة بـ tasks).
- **1:1 Meetings Hub** (template + history + AI summary).
- **Compensation Planning** (budgets, raises, equity).
- **Vendor & Procurement**.
- **Finance Lite** (invoices, expenses, links to deals).
- **Knowledge Base** (Notion-like + AI search).
- **Community Module** (لمجتمعات هلا كارير المهنية).
- **Job Board Public** (white-labeled per tenant).
- **Mobile-only Field Ops App** (للزيارات الميدانية).
- **HRIS Full** (payroll, leaves) — V3.

### 18.2 Platformization
- **Plugin SDK** (TypeScript) — أطراف ثالثة تبني modules.
- **Open API** + **Webhooks** + **Zapier/Make**.
- **AI Agent SDK**: عملاء يبنون agents مخصصة فوق بياناتهم.

### 18.3 Geographic Expansion
- AR-first → EN → FR (شمال أفريقيا) → ES (لاتين/إسبانيا).
- Region-aware data residency (KSA, EU).

---

## 19. Competitive Advantages

### 19.1 لماذا HalaOps يفوز
1. **Bilingual-Native**: ليس ترجمة، بل تصميم RTL/LTR من اليوم 1.
2. **Operational Truth**: KPIs مشتقة من events، يصعب تزويرها.
3. **Gamification الحقيقية**: ليست شارات شكلية، بل اقتصاد متكامل بضوابط anti-cheat.
4. **AI Embedded**: ليس chatbot جانبي، بل في كل field/decision.
5. **Hala Career–Domain Fit**: Recruitment + Training + Community في core (لا add-ons).
6. **Premium Calm UX**: Linear-grade سرعة + Notion-grade مرونة.
7. **Decision Cards بدلاً من تقارير**: اختصار فجوة insight→action.
8. **Truth Index**: ميزة فريدة، تكسر الكذب الإداري.
9. **Fair Competition**: Leagues محمية ضد السمية.
10. **Modular**: شركة تشتري ما تحتاج فقط.

### 19.2 المقارنة المختصرة
| منافس | يفوز في | نتفوّق في |
|------|---------|----------|
| Monday | Visual workflows | AI embedded, gamification, AR-native |
| ClickUp | Feature-richness | UX hygiene, truth-based KPIs |
| HubSpot | Marketing CRM | Operations + Recruitment + Gamification |
| Linear | Speed/UX | Domain fit (HR/Recruitment) |
| Notion | Flexibility | Operational metrics |
| Duolingo | Gamification | Real-work integration |

---

## 20. Roadmap

### 20.1 Phase 0 — Foundations (Weeks 1–2)
- Monorepo (pnpm + Turborepo).
- Auth + Tenancy + RLS + i18n skeleton.
- Design system (shadcn + RTL).
- Observability baseline.

### 20.2 MVP — Phase 1 (Weeks 3–10)
**Goal:** فريق هلا كارير الداخلي يستخدمه يوميًا.
- Identity & Access (basic 2FA).
- Org & People (departments, teams).
- Tasks (basic + comments + attachments + SLA).
- CRM Lite (clients, deals, pipeline kanban).
- Today View + Team Cockpit.
- Basic gamification (XP, levels, streaks, daily missions).
- Notifications (in-app + email).
- Audit logs.

### 20.3 V2 — Phase 2 (Weeks 11–20)
**Goal:** Insight-Driven.
- Performance Intelligence (5 pillars + Truth Index).
- AI Copilot (chat + suggestions + voice-to-action).
- Recruitment module (candidates, vacancies, AI match).
- Insights/Decision Cards.
- Client Portal.
- Recurring tasks + Approvals + Escalations.
- Calm Mode notifications + digest.
- Search (Elasticsearch).

### 20.4 V3 — Phase 3 (Weeks 21–32)
**Goal:** Premium SaaS.
- Full Gamification (badges, leagues, team battles, rewards store).
- Advanced AI (predictions, RAG, automation builder).
- Training/LMS module.
- Community module.
- Mobile apps (iOS/Android).
- SSO (WorkOS) + advanced security.
- Marketplace MVP.

### 20.5 Enterprise — Phase 4 (Weeks 33+)
**Goal:** بيع لشركات 500+.
- Microservices extraction (Notifications, Analytics, AI).
- Multi-region + data residency.
- On-prem option (Helm chart + air-gapped AI fallback).
- Compliance (ISO 27001, SOC 2 readiness).
- Custom SLAs + dedicated CSM.
- White-label.

### 20.6 Build Order Logic
- نبني أصغر شيء يولّد بيانات حقيقية → يغذّي AI → يدعم decisions → يفتح gamification.
- **events** أولًا قبل أي feature تعتمد عليها.
- Truth Index قبل توسيع gamification (وإلا سنبني cheat-friendly system).

### 20.7 Risk Register (مختصر)
| خطر | احتمال | أثر | تخفيف |
|----|------|-----|------|
| Over-scoping MVP | عالي | عالي | حدود صلبة، scope freeze قبل كل phase |
| تعقيد i18n RTL | متوسط | متوسط | Component library RTL-tested من البداية |
| AI cost runaway | متوسط | عالي | caching + small-first + budgets per tenant |
| Adoption resistance | عالي | عالي | Onboarding playful + champion-per-team |
| Data privacy (KSA/EU) | متوسط | عالي | residency من V1، DPA جاهز |

---

## ملحق A — أمثلة Decision Cards

```
🟥 [Action Required]
Khaled has 3 SLAs at risk in next 4 hours.
Reason: workload jumped 60% after Sara left team.
Suggestion: reassign 2 tasks to Mona (capacity 70%, skill match 92%).
[1-tap Reassign]   [Open details]
```
```
🟧 [Watch]
Client "ACME Corp" CSAT dropped 28→7 over 2 weeks.
Drivers: response time +3.2x, 2 missed deadlines.
Suggestion: trigger Recovery Playbook (call + apology brief + senior assignment).
[Run Playbook]   [Snooze 24h]
```

## ملحق B — صيغة XP موسّعة (مرجع تنفيذي)
```ts
function computeXp(event: TaskCompletedEvent, ctx: Ctx): number {
  const base = BASE_XP[event.type] ?? 10;
  const quality = clamp(event.quality ?? ctx.peerScoreAvg ?? 1.0, 0.5, 1.5);
  const difficulty = Math.log1p(event.estimatedMinutes) * PRIORITY_W[event.priority];
  const novelty = 1 / (1 + ctx.repeatsInWindow(event.type, 24 * 60));
  if (ctx.isSpammyDuplicate(event)) return 0;
  if (ctx.dailyXpExceeded(event.userId)) return 0;
  return Math.round(base * quality * difficulty * novelty);
}
```

## ملحق C — قائمة التحقق قبل الإطلاق (Launch Readiness)
- [ ] RLS مُفعّل وموثّق + تجارب إثبات عزل tenants.
- [ ] Audit logs immutable + hash chain يعمل.
- [ ] 2FA إجباري للأدوار ≥ 60.
- [ ] Backups + PITR مُختبَرة.
- [ ] DR drill ناجح (RTO 30m).
- [ ] AR/EN لكل user-facing string، RTL pixel-perfect.
- [ ] Performance: P95 < 250ms للـ dashboards.
- [ ] AI: prompt-cache hit > 60%، budgets per tenant.
- [ ] A11y AA (WCAG).
- [ ] Onboarding: time-to-first-value < 10 دقائق.

---

> **خلاصة:** ابدأ صغيرًا (MVP يعمل في 8 أسابيع لفريق واحد)، ابنِ event-first، أَدخِل AI بعد توفر البيانات، احرس البيانات بـ Truth Index، وشغّل Gamification بضوابط — وستحصل على CRM يجعل الإدارة "ترى الشركة كلها لحظيًا" دون كذب.
