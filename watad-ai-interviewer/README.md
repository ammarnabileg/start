# Watad AI Interviewer

**An enterprise-grade, autonomous AI HR interview platform** that replaces the first HR
screening round. Candidates complete a 1‑on‑1 interview with an AI agent (text, voice, or
video avatar). The platform analyzes the CV, runs an adaptive real‑time interview, scores the
candidate across 10+ competencies, produces a psychological/behavioral profile, detects red
flags, generates a PDF report, and pushes results to the HR dashboard, Google Sheets, and Excel.

Built for **Watad**.

---

## 1. What this repository contains

This repo is delivered in two layers:

| Layer | Status | Where |
|---|---|---|
| **Production specification** — DB schema, ERD, API spec, AI prompt architecture, interview-engine logic, scoring, video architecture, security, RBAC, roadmap, deployment | Complete, implementation-ready | [`docs/`](docs/) |
| **Runnable Laravel foundation** — migrations, models, AI interview engine (Claude PHP SDK), scoring, Google Sheets + Excel export, PDF report, REST API, full HR console + candidate interview UI, Docker | Text **and voice** interview flow runnable; **bilingual (Arabic + English)**; video‑avatar layer wired end‑to‑end in code | `app/`, `database/`, `routes/`, `resources/`, `config/`, `docker/` |

> **Honest scope note.** The interview → AI scoring → report → sheet flow is real, wired code you
> can run after `composer install` and supplying an API key.
> - **Text + Voice modes** run from a bare checkout (voice uses the browser Web Speech API for
>   speech‑to‑text + text‑to‑speech, plus per‑turn audio capture; no paid service required).
> - The agent is **fully bilingual** — it starts in the template language and mirrors the
>   candidate's Arabic (incl. dialect) or English, with RTL support throughout the candidate UI.
> - The **video‑avatar interviewer** (Tavus / HeyGen / LiveKit) is now **wired end‑to‑end in code**
>   (the engine provisions the avatar room, speaks each turn, and ends the session), but running it
>   live requires paid provider accounts + a WebRTC deployment, so it is not exercisable in a bare
>   checkout. **Real‑time video behavioral analysis** ([`docs/10`](docs/10-video-behavioral-analysis.md))
>   needs a GPU/vision worker.
>
> **HR console** includes Dashboard (with charts), Jobs + invitations, Interviews, Report/replay,
> Pipeline board, Templates (competency weights), Avatars, Question libraries, Users & roles, and
> Settings — all RBAC‑gated.

---

## 2. Documentation index (the 17 deliverables)

| # | Document | Covers deliverable(s) |
|---|---|---|
| 01 | [Product Overview](docs/01-product-overview.md) | Vision, personas, core business flow, user flows |
| 02 | [System Architecture](docs/02-system-architecture.md) | Full system architecture, component & sequence diagrams, tech stack |
| 03 | [Database Schema](docs/03-database-schema.md) | Complete schema — every table, field, type, index, FK |
| 04 | [ERD](docs/04-erd.md) | Entity-relationship diagram (Mermaid) |
| 05 | [API Structure](docs/05-api-structure.md) | Full REST + WebSocket API spec |
| 06 | [AI Prompt Engineering](docs/06-ai-prompt-engineering.md) | Prompt architecture, system prompts, tool defs, caching |
| 07 | [Interview Engine Logic](docs/07-interview-engine-logic.md) | State machine, adaptive branching, real-time loop |
| 08 | [Scoring & Analysis](docs/08-scoring-and-analysis.md) | Scoring rubric, psychological/DISC/Big-Five, red flags |
| 09 | [Video Interview Architecture](docs/09-video-interview-architecture.md) | AI avatar, Tavus/HeyGen/LiveKit/WebRTC, recording, replay |
| 10 | [Video Behavioral Analysis](docs/10-video-behavioral-analysis.md) | Video/audio analysis engine, multi-agent behavioral pipeline |
| 11 | [Google Sheets Integration](docs/11-google-sheets-integration.md) | Sheets flow + Excel export |
| 12 | [PDF Report Structure](docs/12-pdf-report-structure.md) | Report sections and layout |
| 13 | [Security Architecture](docs/13-security-architecture.md) | Audit logs, GDPR, sessions, API security, rate limiting |
| 14 | [RBAC](docs/14-rbac.md) | Roles, permission matrix, multi-user system |
| 15 | [Wireframes & UI/UX](docs/15-wireframes-ui-ux.md) | Wireframes, dashboard, admin & candidate screens, design system |
| 16 | [Folder & Module Structure](docs/16-folder-structure.md) | Laravel module/folder structure |
| 17 | [Development Roadmap](docs/17-development-roadmap.md) | Phased delivery roadmap |
| 18 | [Deployment](docs/18-deployment.md) | Docker + Nginx + queue/websocket deployment |
| 19 | [Additional Ideas](docs/19-additional-ideas.md) | Prioritized high-impact feature backlog |
| 20 | [System Blueprint & Sitemap](docs/20-system-blueprint.md) | Admin + portal sitemap, modules, status vocabulary |
| 21 | [Hiring Workflow (3 stages)](docs/21-hiring-workflow.md) | AI screening → human interviews → final approval, AI override, user journeys |
| 22 | [Candidate Master Profile](docs/22-candidate-master-profile.md) | Unified profile, sub-tabs, accumulation, timeline |
| 23 | [Admin Panel Tabs](docs/23-admin-tabs.md) | 12 tabs screen-by-screen (fields, actions, permissions) |
| 24 | [Candidate Portal](docs/24-candidate-portal.md) | 6 candidate-facing tabs |
| 25 | [Permissions Matrix](docs/25-permissions-matrix.md) | Dynamic CRUD + abilities × roles |
| 26 | [Database Schema v2](docs/26-schema-v2.md) | New tables + ERD (applications, human interviews, offers, portal…) |
| 27 | [API Endpoints v2](docs/27-api-v2.md) | Admin + portal + webhook endpoints |
| 28 | [Modules & Roadmap](docs/28-modules-and-roadmap.md) | Laravel module structure + phased roadmap (P7–P16) |
| 29 | [Wireframes v2](docs/29-wireframes-v2.md) | Screen-by-screen wireframes for the new flows |

---

## 3. Tech stack

| Concern | Choice |
|---|---|
| Backend | PHP 8.3+ / Laravel 11 |
| Frontend | Blade + TailwindCSS + Alpine.js |
| Database | MySQL 8 |
| Cache / Queue | Redis (queues, rate limiting, interview session state) |
| Real-time | Laravel Reverb (WebSockets) |
| Object storage | S3-compatible (recordings, CVs, reports) |
| AI — reasoning | **Claude** (`claude-opus-4-8` deep analysis, `claude-sonnet-4-6` real-time turns) via `anthropic-ai/sdk`; OpenAI provider as a pluggable alternative |
| AI — voice | Browser Web Speech API (STT/TTS) baseline; pluggable Deepgram/ElevenLabs adapters |
| AI — video avatar | Tavus / HeyGen Interactive Avatar / OpenAI Realtime + LiveKit (provider adapters) |
| PDF | `barryvdh/laravel-dompdf` |
| Sheets | `google/apiclient` (Sheets v4) |
| Deployment | Docker + Nginx |

See [`docs/02`](docs/02-system-architecture.md) for why each was chosen.

---

## 4. Quickstart (local, Docker)

```bash
cd watad-ai-interviewer
cp .env.example .env

# Set at minimum:
#   ANTHROPIC_API_KEY=sk-ant-...
#   APP_KEY=                      (php artisan key:generate fills this)
#   DB_*, REDIS_*                 (defaults match docker-compose)

docker compose up -d --build      # app, nginx, mysql, redis, queue worker, reverb
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
# App:        http://localhost:8080
# HR login:   admin@watad.com / password  (from DemoSeeder)
```

**Or use the web installer** (no terminal setup): after `composer install` and a running MySQL,
open **`http://localhost:8080/install.php`** and follow the wizard — it checks requirements,
writes `.env`, runs migrations, seeds roles/permissions/avatars, and creates your **Super Admin**
account (full control). Delete `public/install.php` afterwards. See [`docs/14`](docs/14-rbac.md).

Without Docker (requires local PHP 8.3+, MySQL, Redis):

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan reverb:start &        # websockets
php artisan queue:work &          # async AI analysis jobs
php artisan serve
```

> This directory is a self-contained Laravel application module. It is intentionally kept
> separate from the legacy PHP CMS in the repository root.

---

## 5. The core flow in one picture

```
HR creates Job ──▶ Candidate gets link ──▶ Candidate intake form + CV upload
                                                     │
                                          AI analyzes CV (Claude, PDF vision)
                                                     │
                                    Real-time adaptive interview (AI agent)
                                       text / voice / video avatar
                                                     │
                       Transcript + recording + (video signals) captured
                                                     │
        Multi-agent analysis: scoring · psychometrics · red flags · summary
                                                     │
        ┌────────────────────────┬─────────────────────────┬──────────────┐
   HR Dashboard            PDF report (S3)          Google Sheet row    Excel export
```

Full detail: [`docs/07`](docs/07-interview-engine-logic.md) and [`docs/01`](docs/01-product-overview.md).
