# 16 — Folder & Module Structure

Standard Laravel 11 layout, organized by feature. The repo ships the application code under
`watad-ai-interviewer/` (kept separate from the legacy root CMS).

```
watad-ai-interviewer/
├── app/
│   ├── Enums/
│   │   ├── Competency.php            # the 11 competencies
│   │   ├── InterviewMode.php         # text | voice | video
│   │   ├── InterviewStatus.php
│   │   ├── Recommendation.php        # strong_hire | hire | maybe | reject
│   │   ├── RedFlagType.php
│   │   └── RoleSlug.php
│   ├── Events/
│   │   ├── AgentMessageStreamed.php   # broadcast token deltas
│   │   └── InterviewCompleted.php     # broadcast to hr.dashboard
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Hr/                    # DashboardController, JobController, CandidateController,
│   │   │   │                          # InterviewController, ReportController, TemplateController,
│   │   │   │                          # AvatarController, UserController, AuditController
│   │   │   ├── Api/                   # InterviewApiController, DashboardApiController,
│   │   │   │                          # ExportController, WebhookController
│   │   │   └── Candidate/             # InvitationController, IntakeController, InterviewRoomController
│   │   ├── Middleware/                # EnsureRole, VerifyWebhookSignature, SecurityHeaders
│   │   └── Requests/                  # IntakeRequest, AnswerRequest, JobRequest, ...
│   ├── Jobs/
│   │   ├── AnalyzeCv.php
│   │   ├── FinalizeInterview.php      # orchestrates the analysis fan-out
│   │   ├── GenerateReport.php
│   │   ├── PushToSheet.php
│   │   ├── SendNotification.php
│   │   ├── PurgeExpiredCandidateData.php
│   │   └── GdprEraseCandidate.php
│   ├── Models/                        # one per table (JobPosition, Candidate, Interview, ...)
│   ├── Policies/                      # InterviewPolicy, JobPositionPolicy, CandidatePolicy, ...
│   ├── Scopes/                        # DepartmentScope
│   └── Services/
│       ├── AI/
│       │   ├── Contracts/LlmProvider.php
│       │   ├── Providers/{ClaudeProvider,OpenAiProvider}.php
│       │   ├── LlmManager.php          # picks provider+model by role (conversation|analysis)
│       │   ├── InterviewEngine.php     # the turn loop / state machine
│       │   ├── CvAnalyzer.php
│       │   ├── ScoringService.php
│       │   ├── BehavioralAnalyzer.php
│       │   ├── RedFlagDetector.php
│       │   └── Prompts/PromptLibrary.php
│       ├── Video/
│       │   ├── Contracts/AvatarProvider.php
│       │   ├── Providers/{TavusProvider,HeyGenProvider}.php
│       │   └── VideoAnalysisService.php
│       ├── Sheets/GoogleSheetsService.php
│       ├── Export/ExcelExportService.php
│       └── Reports/PdfReportService.php
├── config/
│   ├── watad.php                       # AI models, avatars, scoring, sheets, gdpr, interview budgets
│   └── (app, queue, broadcasting, filesystems, sanctum ...)
├── database/
│   ├── migrations/                     # the schema from docs/03
│   └── seeders/                        # RolePermissionSeeder, AvatarSeeder, PipelineSeeder,
│                                        # CompetencySeeder, DemoSeeder
├── resources/
│   ├── views/
│   │   ├── layouts/{app,candidate}.blade.php
│   │   ├── hr/{dashboard,jobs,candidates,interview,report,replay}.blade.php
│   │   ├── candidate/{intake,room,complete}.blade.php
│   │   ├── reports/interview.blade.php  # the PDF template
│   │   └── components/                  # score-bar, badge, stat-card, ...
│   ├── js/ (Alpine entry, interview-room.js, charts.js)
│   └── css/ (tailwind)
├── routes/
│   ├── web.php          # HR UI + candidate public pages
│   ├── api.php          # JSON API + webhooks
│   ├── channels.php     # Reverb channel auth
│   └── console.php      # scheduled commands (reminders, purge, sheet retries)
├── docker/
│   ├── nginx/default.conf
│   ├── php/php.ini
│   └── supervisor/      # queue worker + reverb process configs
├── docker-compose.yml
├── Dockerfile
├── composer.json
└── docs/                # the 18 specification documents
```

## Conventions

- **Services own logic; controllers stay thin.** Controllers validate (Form Requests), call a
  service/job, return a view/JSON.
- **Async by default for AI.** Anything that calls the LLM for analysis runs in a queued Job, so
  HTTP requests stay fast and retries are free. The live interview turn is the one synchronous LLM
  path (streamed), because the candidate is waiting.
- **Enums everywhere** for closed sets (competencies, statuses, recommendations) — DB stores the
  backing string; PHP works with the enum.
- **Provider interfaces** (`LlmProvider`, `AvatarProvider`) keep vendor SDKs at the edges; swapping
  Claude↔OpenAI or Tavus↔HeyGen is a config change, not a refactor.
- **Config-driven**: models, weights, thresholds, avatars, budgets all live in `config/watad.php`.
