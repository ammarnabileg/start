# 17 — Development Roadmap

Phased delivery. Each phase is independently shippable and demoable. Estimates assume a small team
(2 backend, 1 frontend, 1 part-time ML/infra) and are indicative, not commitments.

## Phase 0 — Foundations (1–2 weeks)
- Laravel project, Docker, CI (lint, tests, `composer audit`), env/secrets wiring.
- DB schema migrations + models + seeders (roles/permissions, avatars, pipeline, competencies).
- Auth (HR login, Sanctum), RBAC middleware + policies, audit logging, security headers.
- **Exit**: HR can log in; schema migrates; CI green.

## Phase 1 — Core text interview MVP (2–3 weeks)
- Job CRUD, invitation links, candidate intake + CV upload to S3.
- `CvAnalyzer` (Claude, PDF vision).
- `InterviewEngine` text turn loop with tools + adaptive branching + Redis state + streaming.
- Candidate interview room (text), WebSocket token streaming via Reverb.
- `FinalizeInterview` → `ScoringService` → competency scores, overall, recommendation.
- HR dashboard (metrics + recent results), report screen (scores + summary).
- **Exit**: end-to-end text interview → scored report visible to HR. *(This is the slice the
  included scaffold targets.)*

## Phase 2 — Full analysis & delivery (2 weeks)
- `BehavioralAnalyzer` (DISC/Big-Five), `RedFlagDetector` (+ deterministic salary check).
- Recommendation override rules; moment timeline (`interview_events`).
- PDF report (`PdfReportService`) to S3 + signed download.
- Google Sheets push (`GoogleSheetsService`) + Excel export.
- Email notifications (invitation, reminder, completion, high-potential).
- **Exit**: finalized interview produces report PDF + sheet row + HR notification.

## Phase 3 — Voice mode + pipelines (2 weeks)
- Voice interview (Web Speech STT/TTS; per-turn audio capture), pluggable Deepgram/ElevenLabs.
- Hiring pipelines + stage moves + funnel analytics; charts (daily/weekly/monthly).
- Question libraries UI; templates UI (weights, toggles); avatar management UI.
- WhatsApp notifications (Cloud API).
- Dark mode, Arabic/RTL localization pass.
- **Exit**: voice interviews; full pipeline + analytics; bilingual UI.

## Phase 4 — Video avatar interviewer (3–4 weeks)
- `AvatarProvider` integration (Tavus or HeyGen), LiveKit room, barge-in.
- Recording egress → S3 → `recordings`; replay dashboard (synced video/transcript/notes/scores).
- Fallback-to-voice on provider failure.
- **Exit**: live AI avatar interview + replay.

## Phase 5 — Video behavioral analysis (3–4 weeks)
- Video-analysis worker (gaze/expression/prosody) or managed vision API; webhook ingestion.
- `video_analyses` + behavioral fusion into reports; timeline overlay.
- Liveness/anti-proxy checks.
- **Exit**: demeanor signals + authenticity in reports and replay.

## Phase 6 — Hardening & scale (ongoing)
- Load/perf testing; autoscale workers; cost dashboards (token usage per interview).
- GDPR tooling (export/erase/retention) verified; pen-test; adverse-impact audit.
- Calibration: compare AI recommendations vs. human outcomes; tune thresholds & prompts.
- Observability: tracing, alerting, SLOs.

## Cross-cutting workstreams
- **Prompt/version management & evals**: a golden-set of transcripts to regression-test scoring on
  every prompt change.
- **Fairness & compliance**: legal review of consent, bias controls, regional data residency.
- **Accessibility**: WCAG AA throughout.

## Risk register (top items)
| Risk | Mitigation |
|---|---|
| LLM latency hurts live UX | Sonnet for live turns, prompt caching, streaming, token guard |
| Scoring inconsistency | Structured outputs + evidence requirement + calibration eval set |
| Video provider cost/lock-in | `AvatarProvider` abstraction; voice fallback; start text/voice |
| Bias / legal exposure | Prompt guardrails, advisory video signals, audits, human-in-the-loop |
| Cost runaway | Per-interview token cap, async batch model only where needed, usage dashboards |
