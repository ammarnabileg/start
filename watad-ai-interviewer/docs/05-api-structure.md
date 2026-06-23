# 05 — API Structure

Two surfaces:

- **Web routes** (`routes/web.php`) — session-authenticated HR UI + the public candidate flow.
- **API routes** (`routes/api.php`) — token-authenticated (Sanctum) JSON API for the SPA-ish
  interview room, integrations, and future mobile clients.
- **WebSocket channels** (`routes/channels.php`) — Reverb broadcast channels.

All API responses are JSON; errors use RFC-7807-ish envelopes:
`{ "message": "...", "errors": { field: [..] } }` with appropriate HTTP status.

## Auth

| Surface | Mechanism |
|---|---|
| HR UI | Laravel session + CSRF; optional 2FA |
| HR API | Sanctum bearer token (`/api/auth/token`) |
| Candidate interview | Short-lived signed interview token bound to `interviews.public_id` (no account) |
| Webhooks (avatar/video providers) | HMAC signature header verification |

## Public candidate endpoints (no login)

| Method | Path | Purpose |
|---|---|---|
| GET | `/i/{token}` | Resolve invitation; show intake or expired page |
| POST | `/i/{token}/intake` | Submit intake form + CV upload → creates candidate + interview, dispatches `AnalyzeCv` |
| GET | `/interview/{public_id}` | Interview room (after intake) |
| POST | `/api/interview/{public_id}/start` | Begin interview; returns agent intro + first question |
| POST | `/api/interview/{public_id}/answer` | Submit a turn `{text, client_token, audio?}`; returns next question or `concluded` |
| POST | `/api/interview/{public_id}/event` | Client telemetry (focus lost, device, mic level) |
| GET | `/api/interview/{public_id}/state` | Resume: current phase, last question, asked_count |
| POST | `/api/interview/{public_id}/complete` | Candidate-initiated finish |

### Example — submit answer

```http
POST /api/interview/01J.../answer
Authorization: Bearer <interview-token>
Content-Type: application/json

{ "text": "I led a team of 10 engineers across two squads.", "client_token": "a1b2c3" }
```

```json
{
  "status": "in_progress",
  "agent": {
    "text": "How were those two squads structured, and what KPIs did you own?",
    "is_follow_up": true,
    "seq": 14
  },
  "progress": { "asked": 7, "min": 6, "max": 14, "phase": "probing" }
}
```

On termination:

```json
{ "status": "concluded", "agent": { "text": "Thanks — that's everything from my side..." } }
```

## HR API (Sanctum)

### Jobs & config
| Method | Path | Permission |
|---|---|---|
| GET/POST | `/api/jobs` | `job.view` / `job.create` |
| GET/PUT/DELETE | `/api/jobs/{id}` | `job.*` |
| POST | `/api/jobs/{id}/invitations` | `invitation.create` → returns public link |
| GET/POST | `/api/departments` | `settings.manage` |
| GET/POST | `/api/templates` | `template.manage` |
| GET/POST | `/api/avatars` | `avatar.manage` |
| GET/POST | `/api/question-libraries`, `/api/questions` | `question.manage` |
| GET/POST | `/api/pipelines` | `settings.manage` |

### Candidates & interviews
| Method | Path | Purpose |
|---|---|---|
| GET | `/api/candidates` | List/filter/search candidates |
| GET | `/api/candidates/{id}` | Candidate detail + interviews |
| POST | `/api/candidates/{id}/erase` | GDPR erasure (`gdpr.erase`) |
| GET | `/api/interviews` | List/filter (status, reco, job, score range, date) |
| GET | `/api/interviews/{id}` | Full interview detail |
| GET | `/api/interviews/{id}/transcript` | Ordered messages |
| GET | `/api/interviews/{id}/timeline` | `interview_events` (jump-to points) |
| GET | `/api/interviews/{id}/scores` | competency_scores + overall + reco |
| GET | `/api/interviews/{id}/report` | Report JSON |
| GET | `/api/interviews/{id}/report.pdf` | Signed PDF download |
| GET | `/api/interviews/{id}/recording` | Signed recording URL |
| POST | `/api/interviews/{id}/move-stage` | Advance/reject in pipeline |
| POST | `/api/interviews/{id}/resync-sheet` | Retry Sheets push |

### Dashboard & export
| Method | Path | Purpose |
|---|---|---|
| GET | `/api/dashboard/metrics` | Totals, today, hired/rejected, conversion, avg score |
| GET | `/api/dashboard/funnel` | Hiring funnel counts per stage |
| GET | `/api/dashboard/charts?range=daily\|weekly\|monthly` | Time-series |
| GET | `/api/export/interviews.xlsx?filters...` | Excel export |
| POST | `/api/integrations/sheets/connect` | OAuth/service-account setup |

### Users & RBAC
| Method | Path | Permission |
|---|---|---|
| GET/POST | `/api/users` | `user.manage` |
| PUT | `/api/users/{id}/roles` | `user.manage` |
| GET | `/api/roles`, `/api/permissions` | `user.manage` |
| GET | `/api/audit-logs` | `audit.view` |

## Webhooks (inbound)

| Method | Path | Source |
|---|---|---|
| POST | `/api/webhooks/avatar/{provider}` | Tavus/HeyGen session + recording events |
| POST | `/api/webhooks/video-analysis` | Async video-analysis results |
| POST | `/api/webhooks/whatsapp` | Delivery/read receipts |

All verified by provider HMAC; unverified payloads are rejected and audited.

## WebSocket channels (Reverb)

| Channel | Type | Events |
|---|---|---|
| `interview.{public_id}` | private (interview token) | `agent.delta`, `agent.message`, `agent.concluded`, `state.update` |
| `hr.dashboard` | private (auth user) | `interview.completed`, `candidate.high_potential`, `metrics.update` |
| `hr.interview.{id}` | private (auth + permission) | live monitoring (optional) |

## Rate limiting

| Group | Limit |
|---|---|
| Candidate answer endpoint | 30/min per interview token |
| HR API (general) | 120/min per user |
| Auth/login | 5/min per IP + lockout |
| Webhooks | 600/min per provider IP allowlist |

Implemented with Laravel rate limiters backed by Redis. See
[`docs/13-security-architecture.md`](13-security-architecture.md).
