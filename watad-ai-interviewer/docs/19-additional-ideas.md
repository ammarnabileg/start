# 19 — Additional Ideas (High-Impact Enhancements)

A curated, prioritized backlog of features that would take Watad AI Interviewer from a strong
screening tool to a category-leading HR-tech product. Each item notes **why it matters** and
**how it plugs into the existing architecture**.

## ⭐ Build-next shortlist (highest impact / reasonable effort)

1. **Calibration loop** — record the human decision (hired/performed well?) against the AI
   recommendation, and surface agreement rate + false-reject audits. *Plugs into:* a
   `decision_outcomes` table + a dashboard panel; feeds prompt/threshold tuning. This is the single
   most valuable thing for trust and continuous improvement.
2. **Bias & adverse-impact dashboard** — track recommendation/score distributions across cohorts
   (where lawful to measure) to catch disparate impact early. *Plugs into:* analytics queries over
   `interviews` + `competency_scores`; ties to `docs/13` fairness controls.
3. **Anti-cheat / integrity signals** — paste detection, tab-switch counts (telemetry already
   exists via `/event`), answer-latency + "too-polished / LLM-assisted" heuristics, and (video)
   liveness. *Plugs into:* `interview_events` + a `risk_score`; augments `RedFlagDetector`.
4. **Candidate comparison & ranking view** — select N candidates for a role and compare scorecards
   side-by-side, auto-ranked. *Plugs into:* a new HR screen over existing `competency_scores`.
5. **WhatsApp/email scheduling + reminders + resumable interviews** — invitation reminders,
   "continue where you left off" (state already in Redis/`interviews.state`). *Plugs into:*
   `SendNotification` + the scheduler (`routes/console.php`).
6. **JD → auto competency weights & question pack** — paste a job description, AI proposes the
   template weights and a tailored question library. *Plugs into:* a new AI agent + `templates`.

## AI depth

- **Role-specific assessment modules**: live coding sandbox, case studies, sales role-play,
  writing samples — scored as additional competencies.
- **Structured per-question rubrics** so scoring cites which rubric criteria were met.
- **Model ensemble / second opinion** on borderline candidates (run the scorer twice or on two
  models; flag disagreement for human review).
- **Confidence-gated human review**: auto-route low-confidence or borderline results to a human
  instead of auto-deciding (policy toggle: "human required before any reject").
- **Semantic talent search**: embed CVs + transcripts; "find me candidates like X" / re-surface
  past applicants for new roles (talent pool re-engagement).
- **Automated reference checks** and **CV ↔ LinkedIn consistency** verification.
- **Adaptive difficulty**: ramp question difficulty based on live performance (template already has
  a `difficulty` field — wire it into the engine).

## Candidate experience

- **Practice/warm-up mode** (ungraded) to reduce anxiety and tech issues.
- **Accessibility**: captions, screen-reader-tuned live region (partly done), adjustable pace,
  keyboard-only.
- **More languages** beyond Arabic/English (the engine mirrors language already; extend STT/TTS).
- **Candidate-facing feedback** (optional, configurable): a short, kind summary of strengths.
- **Mobile-first / PWA** and a native wrapper for low-bandwidth regions.
- **Device pre-check** wizard (mic/cam/network) before video interviews.

## Recruiter productivity & integrations

- **ATS integrations**: Greenhouse, Lever, Workable, SmartRecruiters (push results + pull jobs).
- **Calendar handoff**: auto-schedule the human round (Google/Microsoft) for strong-hire results.
- **Slack/Teams notifications** for high-potential candidates and daily digests.
- **Bulk invitations** (CSV upload) and branded invitation emails per job.
- **Saved views & smart filters**; **duplicate-candidate detection** across jobs.
- **Comments & collaborative ratings** so humans can annotate the AI report.

## Analytics & insights

- **Hiring funnel analytics**: time-to-shortlist, source quality, drop-off by stage.
- **Cost-per-screen dashboard** (token usage already tracked on `interviews.llm_*_tokens`).
- **Avatar / template A/B testing**: which persona or question set yields better-calibrated
  outcomes.
- **Score-drift monitoring**: alert if average scores shift after a prompt/model change (pairs with
  the eval harness below).

## Trust, compliance & governance

- **Prompt versioning + eval harness**: a golden set of labeled transcripts; every prompt/model
  change runs regression scoring before deploy (referenced in `docs/17`). Critical for safe
  iteration.
- **Explainability statements** on every report ("this recommendation is based on…") and a
  **candidate appeal/recourse** path.
- **Region-aware retention & data residency** (per-tenant retention windows; EU/KSA/EG residency).
- **Consent & policy management** with versioned consent text and audit.
- **Compliance presets**: GDPR, KSA PDPL, EEOC-aware configuration.

## Platform & SaaS

- **Multi-tenant / white-label** for agencies (org isolation, per-org branding, themes).
- **SSO / SAML / SCIM** for enterprise identity.
- **Usage metering & billing** (per-interview or seat-based plans).
- **Feature flags** and per-org cost guardrails (max tokens/interviews per period).
- **Observability**: per-interview trace (each LLM call, latency, tokens, tool calls) for debugging
  and cost attribution.

## Voice & video (now wired — next steps)

- Swap the browser Web Speech baseline for **Deepgram/ElevenLabs** (better accuracy + Arabic
  dialects) behind the existing voice adapter seam.
- Wire **LiveKit egress** to capture and store full video for replay (recordings table is ready).
- Turn on the **video behavioral analysis worker** (`docs/10`) for engagement/authenticity signals.

---

*All of the above are additive to the current architecture — no rework of the schema, engine, or
provider abstractions is required to start any one of them.*
