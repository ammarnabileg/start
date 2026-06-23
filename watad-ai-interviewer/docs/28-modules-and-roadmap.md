# 28 — Laravel Module Structure & Development Roadmap

## Module / folder structure (v2 additions in **bold**)

```
app/
├── Enums/
│   ├── ApplicationStatus.php**, HumanInterviewType.php**, HumanInterviewStatus.php**,
│   ├── DecisionType.php**, OfferStatus.php**, EvaluationRecommendation.php**
│   ├── Competency.php, InterviewMode.php, InterviewStatus.php, Recommendation.php,
│   └── RedFlagType.php, RoleSlug.php (extended)
├── Models/
│   ├── (v1) User, Role, Permission, Department, JobPosition, Candidate, Interview, ...
│   └── **CandidateUser, JobApplication, CandidateActivity, CandidateDocument, CandidateNote,**
│       **Tag, TalentPool, EvaluationTemplate, EvaluationCriterion, HumanInterview,**
│       **InterviewPanelist, InterviewEvaluation, HiringDecision, Offer, Setting,**
│       **MessageTemplate, UserIntegration, SavedFilter**
├── Http/Controllers/
│   ├── Hr/   (Dashboard, Candidate, Job, Interview[AI], **HumanInterviewController,**
│   │          **ApplicationController, PipelineController, OfferController,**
│   │          **TalentPoolController, EvaluationTemplateController, AiConfigController,**
│   │          Template, Avatar, Question, User, Role, Settings, Audit)
│   ├── **Portal/** (Auth, Dashboard, Application, Interview, Profile, Notification, Offer)
│   └── Api/  (Interview, Export, Webhook, **ApplicationApi, OfferApi, IntegrationApi**)
├── Services/
│   ├── AI/ (LlmManager, InterviewEngine, CvAnalyzer, ScoringService, ...)
│   ├── Video/ (AvatarProvider adapters, VideoAnalysisService)
│   ├── **Hiring/** (ApplicationWorkflow, DecisionService, EvaluationService, PipelineService)
│   ├── **Scheduling/** (CalendarService, MeetingLinkService — Zoom/Meet/Teams adapters)
│   ├── **Offers/** (OfferLetterService [PDF], ESignatureService)
│   ├── **Messaging/** (EmailService, WhatsAppService, TemplateRenderer)
│   ├── Sheets/, Export/, Reports/
├── Policies/ (Job, Candidate, Interview, **Application, HumanInterview, Offer**)
├── Scopes/ (DepartmentScope, **AssignedInterviewScope**, **OwnCandidateScope**[portal])
└── Support/ (Permissions [extended])
config/  watad.php (+ scheduling, offers, messaging, integrations groups)
routes/  web.php, **portal.php**, api.php, channels.php, console.php
resources/views/ hr/* (+ applications, human-interviews, pipeline, offers, talent-pool,
                 ai-config, candidates/profile), **portal/***, components/*
database/ migrations (2024_02_01_* v2), seeders (RolePermission [extended], EvaluationTemplate,
          MessageTemplate, Settings)
```

Guards: `config/auth.php` adds a **`candidate`** guard + `candidate_users` provider. Portal routes in
`routes/portal.php` use `auth:candidate`.

## Development roadmap (continues [`docs/17`](17-development-roadmap.md))

| Phase | Scope | Exit criteria |
|---|---|---|
| **P7 — Application spine** | `job_applications` as the pipeline spine; migrate from `candidate_pipeline`; `ApplicationWorkflow` + statuses; `candidate_activities` timeline; decisions + **AI override** | Apply → AI screen → human advance/override → status moves; timeline populated |
| **P8 — Master Profile** | Candidate profile screen (Overview/Applications/AI/Human/Documents/Notes/Timeline/Offers); CV **versions**, notes, tags, documents | Full profile renders; data accumulates across stages |
| **P9 — Human interviews** | Scheduling + Calendar + meeting-link adapters (Zoom/Meet/Teams); panelists; **dynamic evaluation forms** per job; aggregate scoring | Schedule → invite + link → panelists submit job-specific eval → status advances |
| **P10 — Final approval & Offers** | Stage-3 decisions; **Offer Letter Generator** (PDF) + send + **e-signature**; accept → hired | Director approves → offer → candidate e-signs → hired |
| **P11 — Candidate Portal** | `candidate` guard; portal Dashboard/Applications/Interviews/Profile/Notifications/Offers; public job board + apply | Candidate self-serves end-to-end |
| **P12 — Roles & dynamic permissions** | Extended resource permissions; **custom roles** (create in UI); matrix editor; field masking (financial/sensitive); scopes (assigned/dept) | Admin builds a custom role + scoped access works |
| **P13 — Enterprise features** | Talent Pool, tagging, **comparison & ranking**, Question Bank, Scorecards, **Email/WhatsApp automation**, saved filters, bulk actions, advanced search, **data export / API** | Each feature usable from the admin UI |
| **P14 — Integrations** | Google/Microsoft Calendar, Zoom/Meet/Teams, WhatsApp Cloud API, e-sign provider, SMTP; OAuth + webhooks | Real meetings/calendars/notifications fire |
| **P15 — Analytics & calibration** | Reports tab (funnel, velocity, source quality, interviewer calibration AI-vs-human, diversity), scheduled digests | Dashboards + exports + calibration loop live |
| **P16 — Hardening** | RBAC pen-test, GDPR tooling, load/scale, observability, prompt eval harness | Production sign-off |

Cross-cutting throughout: Arabic RTL + English, dark mode, mobile responsive, audit logging,
accessibility (WCAG AA).

## Status / ownership of this design vs current code

| Area | State |
|---|---|
| v2 schema (these tables) | **Migrations + models shipped** (`2024_02_01_*`) |
| Extended permissions + custom roles + roles matrix UI | **Shipped** |
| v1 AI interview → scoring → report → sheet → dashboard, HR console, voice/video wiring | **Shipped** (prior phases) |
| Application workflow services, human-interview/offer/portal controllers & views, integrations | **Specified here** (P7–P16) — build per roadmap |
