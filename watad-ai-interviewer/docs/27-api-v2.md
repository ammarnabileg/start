# 27 — API Endpoints v2

Extends [`docs/05`](05-api-structure.md). Two guards: **admin** (`web`/Sanctum, RBAC) and
**candidate** (`candidate`). JSON; RBAC via `can:{ability}`.

## Applications (pipeline spine) — admin

| Method | Path | Permission |
|---|---|---|
| GET | `/api/applications` | `applications.view` |
| GET | `/api/applications/{id}` | `applications.view` |
| POST | `/api/applications` | `applications.create` |
| PATCH | `/api/applications/{id}` | `applications.update` |
| POST | `/api/applications/{id}/move-stage` | `candidates.move_stage` |
| POST | `/api/applications/{id}/decision` | `decisions.advance`/`reject`/`approve`/`make_offer` |
| POST | `/api/applications/{id}/override-ai` | `decisions.override_ai` (body: `decision`, `reason`) |
| GET | `/api/applications/{id}/timeline` | `applications.view` |
| POST | `/api/applications/{id}/withdraw` | `applications.update` |

## Human interviews & evaluations — admin

| Method | Path | Permission |
|---|---|---|
| GET | `/api/human-interviews?from=&to=&type=` | `human_interviews.view` |
| POST | `/api/human-interviews` (schedule + auto meeting link) | `interviews.schedule` |
| GET/PATCH | `/api/human-interviews/{id}` | `human_interviews.view`/`update` |
| POST | `/api/human-interviews/{id}/reschedule` · `/cancel` | `human_interviews.update` |
| POST | `/api/human-interviews/{id}/panelists` | `human_interviews.update` |
| GET | `/api/human-interviews/{id}/evaluation-form` (resolves template by job) | `evaluations.view` |
| POST | `/api/human-interviews/{id}/evaluations` (submit) | `evaluations.create` |
| GET | `/api/evaluation-templates`, POST/PUT `/api/evaluation-templates/{id}` | `templates.*` |

## Decisions & offers — admin

| Method | Path | Permission |
|---|---|---|
| GET | `/api/offers` | `offers.view` |
| POST | `/api/offers` (generate letter PDF) | `decisions.make_offer` / `offers.create` |
| POST | `/api/offers/{id}/send` | `offers.update` |
| POST | `/api/offers/{id}/withdraw` | `offers.update` |
| GET | `/api/offers/{id}/letter.pdf` | `offers.view` |

## Candidates / profile — admin

| Method | Path | Permission |
|---|---|---|
| GET | `/api/candidates?filters…` (advanced search, saved filters) | `candidates.view` |
| POST | `/api/candidates/compare` (2–4 ids) | `candidates.view` |
| GET/POST | `/api/candidates/{id}/documents` | `documents.view`/`create` |
| GET/POST | `/api/candidates/{id}/notes` | `notes.view`/`create` |
| POST/DELETE | `/api/candidates/{id}/tags` | `tags.create`/`delete` |
| POST | `/api/candidates/{id}/talent-pool` | `talent_pool.create` |
| POST | `/api/candidates/{id}/erase` (GDPR) | `candidates.erase` |
| POST | `/api/bulk/candidates/{action}` (tag/move/email/reject/export) | per action |

## Config / ops — admin

| Method | Path | Permission |
|---|---|---|
| GET/PUT | `/api/ai-config` (models, weights, bands, avatars, languages) | `ai_config.view`/`update` |
| GET/PUT | `/api/settings`, `/api/message-templates` | `settings.view`/`update` |
| GET/POST | `/api/integrations/{provider}/connect` (OAuth) | `integrations.manage` |
| GET/POST | `/api/saved-filters` | (owner) |
| GET | `/api/audit-logs` | `audit.view` |
| GET | `/api/export/{resource}.xlsx` | `reports.export` / `data.export` |
| GET/POST/PUT | `/api/roles`, `/api/roles/{id}` (create custom role, edit matrix) | `roles.*` |

## Candidate Portal API — `candidate` guard

| Method | Path |
|---|---|
| POST | `/portal/api/register`, `/login`, `/logout`, `/verify`, `/forgot-password` |
| GET | `/portal/api/dashboard` |
| GET | `/portal/api/applications`, `/applications/{id}` |
| POST | `/portal/api/jobs/{slug}/apply` |
| POST | `/portal/api/applications/{id}/withdraw` |
| GET | `/portal/api/interviews` (AI + scheduled + previous + instructions) |
| GET/PUT | `/portal/api/profile` |
| POST | `/portal/api/profile/documents` (upload CV version / certificate) |
| GET | `/portal/api/notifications`, POST `/notifications/read` |
| GET | `/portal/api/offers`, `/offers/{id}` |
| POST | `/portal/api/offers/{id}/accept` (e-sign) · `/decline` |

## Webhooks (inbound)

| Path | Source |
|---|---|
| `/api/webhooks/calendar/{provider}` | Google/Microsoft calendar event updates |
| `/api/webhooks/meeting/{provider}` | Zoom/Meet/Teams recording-ready, attendance |
| `/api/webhooks/whatsapp` | delivery/read receipts |
| `/api/webhooks/esign` | signature completion → mark offer accepted |
| `/api/webhooks/avatar/{provider}`, `/api/webhooks/video-analysis` | (v1) AI video |

All webhooks HMAC-verified; all mutating endpoints rate-limited and audited.
