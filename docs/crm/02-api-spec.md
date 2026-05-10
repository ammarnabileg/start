# HalaOps CRM — API Specification (V1)

> Style: REST + tRPC, JSON, cursor pagination, idempotency, ETag, i18n via `Accept-Language`.

## Conventions
- **Base URL:** `https://api.halaops.com/v1`
- **Auth:** `Authorization: Bearer <jwt>` + `X-Tenant: <slug>`
- **Idempotency:** `Idempotency-Key: <uuid>` على كل POST
- **Optimistic concurrency:** `If-Match: <etag>` على PATCH
- **Localization:** `Accept-Language: ar` يُعيد `name_ar` كحقل `name`
- **Pagination:** `?cursor=<opaque>&limit=50` → response `{ data, next_cursor }`
- **Errors:** RFC 7807 problem+json
- **Rate limits:** 600/min/user عام، 60/min على `/ai/*`
- **Tracing:** `traceparent` header passthrough

## Auth
| Method | Path | Notes |
|--------|------|-------|
| POST | `/auth/login` | email + password → tokens + 2FA challenge |
| POST | `/auth/2fa/verify` | totp code |
| POST | `/auth/refresh` | rotate refresh |
| POST | `/auth/logout` | revoke session |
| POST | `/auth/sso/start` | WorkOS SAML/OIDC |
| GET  | `/auth/me` | current user + roles + perms |

## Tenants & Users
| Method | Path |
|--------|------|
| GET    | `/tenant/settings` |
| PATCH  | `/tenant/settings` |
| GET    | `/users?team=&role=&q=` |
| POST   | `/users/invite` |
| PATCH  | `/users/:id` |
| POST   | `/users/:id/deactivate` |

## Org
| Method | Path |
|--------|------|
| GET/POST/PATCH/DELETE | `/departments[/:id]` |
| GET/POST/PATCH/DELETE | `/teams[/:id]` |
| POST   | `/teams/:id/members` |
| DELETE | `/teams/:id/members/:userId` |
| GET    | `/org/chart` |

## Clients & CRM
| Method | Path |
|--------|------|
| GET    | `/clients?type=&stage=&q=&owner=` |
| POST   | `/clients` |
| GET    | `/clients/:id` |
| PATCH  | `/clients/:id` |
| GET    | `/clients/:id/timeline` |
| GET    | `/clients/:id/health` (AI risk + drivers) |
| POST   | `/clients/:id/playbook/:key` (run automation) |
| GET    | `/contacts?clientId=` |
| POST   | `/contacts` |
| GET    | `/deals?stage=&owner=` |
| POST   | `/deals` |
| POST   | `/deals/:id/transitions` (state machine) |
| GET    | `/deals/:id/win-prediction` |

## Recruitment
| Method | Path |
|--------|------|
| GET/POST/PATCH | `/candidates[/:id]` |
| POST   | `/candidates/:id/parse-cv` (AI) |
| GET    | `/vacancies` |
| POST   | `/match` body: `{vacancyId, candidateIds}` → scores + bias signals |
| POST   | `/placements` |
| GET    | `/placements?status=` |

## Training
| Method | Path |
|--------|------|
| GET/POST/PATCH | `/training/programs[/:id]` |
| POST   | `/training/programs/:id/enroll` |
| GET    | `/training/programs/:id/progress` |

## Tasks
| Method | Path |
|--------|------|
| GET    | `/tasks?status=&assignee=me&due_before=&q=` |
| POST   | `/tasks` |
| GET    | `/tasks/:id` |
| PATCH  | `/tasks/:id` |
| POST   | `/tasks/:id/transitions` body: `{action: "start"\|"complete"\|"reopen"\|"block"}` |
| POST   | `/tasks/:id/comments` |
| POST   | `/tasks/:id/attachments` (multipart) |
| POST   | `/tasks/:id/checklist` |
| POST   | `/tasks/:id/dependencies` |
| POST   | `/tasks/:id/time-logs/start` |
| POST   | `/tasks/:id/time-logs/stop` |
| GET    | `/tasks/:id/history` |
| POST   | `/tasks/bulk` (assign, transition) |
| GET    | `/tasks/suggestions/assign?taskId=` (AI) |
| POST   | `/tasks/from-voice` body: audio → structured task (AI) |

## Approvals & Escalations
| Method | Path |
|--------|------|
| GET    | `/approvals?status=pending` |
| POST   | `/approvals/:id/decide` body: `{decision, reason}` |
| GET    | `/escalations?open=true` |

## Performance
| Method | Path |
|--------|------|
| GET    | `/performance/me?period=2026-Q2` |
| GET    | `/performance/users/:id?period=` |
| GET    | `/performance/teams/:id?period=` |
| GET    | `/performance/truth-index?userId=&period=` |
| POST   | `/reviews` (peer/manager) |
| GET    | `/reviews?revieweeId=&period=` |
| POST   | `/feedback/clients` (CSAT/NPS submit) |

## Insights
| Method | Path |
|--------|------|
| GET    | `/insights/decisions` (CEO top-3 cards) |
| GET    | `/insights/risks` |
| GET    | `/insights/heatmap?scope=tenant` |
| GET    | `/insights/forecasts/:metric` |

## AI Copilot
| Method | Path |
|--------|------|
| POST   | `/ai/chat` (SSE stream) body: `{messages, tools[]}` |
| POST   | `/ai/voice-to-action` (multipart audio) |
| POST   | `/ai/summarize/meeting` body: `{transcript}` → action items |
| POST   | `/ai/draft/review` body: `{userId, period}` |
| POST   | `/ai/automation/parse` body: `{naturalLanguage}` → rule DSL |

## Arena (Gamification)
| Method | Path |
|--------|------|
| GET    | `/arena/me` (level, xp, streak, league) |
| GET    | `/arena/leaderboard?scope=team&period=season` |
| GET    | `/arena/missions/active` |
| POST   | `/arena/missions/:id/claim` |
| GET    | `/arena/badges` |
| POST   | `/arena/coins/redeem` |
| GET    | `/arena/battles/active` |

## Notifications
| Method | Path |
|--------|------|
| GET    | `/notifications?unread=true` |
| POST   | `/notifications/:id/read` |
| POST   | `/notifications/read-all` |
| GET/PATCH | `/notification-prefs` |

## Client Portal (separate JWT scope)
| Method | Path |
|--------|------|
| GET    | `/portal/me` |
| GET    | `/portal/projects` |
| GET    | `/portal/projects/:id/timeline` |
| POST   | `/portal/messages` |
| POST   | `/portal/feedback` |

## Admin & Audit
| Method | Path |
|--------|------|
| GET    | `/admin/audit?from=&to=&actor=&entity=` |
| GET    | `/admin/audit/verify` (hash chain integrity) |
| GET    | `/admin/billing` |
| GET    | `/admin/feature-flags` |

## WebSocket Channels
```
ws://api.halaops.com/ws  (Bearer auth)
events:
  user:{id}                presence, notif, xp, badges
  team:{id}                tasks, presence
  task:{id}                comments, status, watchers
  tenant:{id}:leaderboard  live ranking ticks
  client:{id}              CSM updates
```

## State Machines (selected)
**Task:** `todo → in_progress → review → done` (+ `blocked`, `reopened`)
**Deal:** `lead → qualified → proposal → negotiation → won|lost`
**Placement:** `submitted → interview → offer → placed → probation_passed|failed`

## Error Codes (selected)
- `403 PERMISSION_DENIED` — RBAC/ABAC fail
- `409 CONFLICT_ETAG` — stale write
- `422 VALIDATION_FAILED`
- `429 RATE_LIMITED`
- `451 LEGAL_HOLD` — record under audit/legal hold
- `498 MFA_REQUIRED` — step-up needed for sensitive op
