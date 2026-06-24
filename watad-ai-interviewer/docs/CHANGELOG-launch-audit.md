# Watad AI Interviewer — Launch Readiness Audit & Changelog

A phased audit + rebuild toward commercial launch. Each phase is shippable on its own.

---

## Phase 1 — Core interview loop & analysis engine (CRITICAL)

### Root cause found
The dashboard widgets (Scores, Behavioral, Red Flags, Competencies) were empty **not because the UI
is weak, but because no data is ever produced**:

1. **Interviews never concluded.** The interview only ended when the LLM voluntarily called the
   `conclude_interview` tool. With OpenAI/gpt-4o that tool call is unreliable, so interviews ran
   forever and never reached the `Processing/Completed` state — so the analysis pipeline never fired.
2. **The analysis pipeline never ran.** `FinalizeInterview` (scoring → behavioral → red flags →
   report) is a queued job. On Plesk shared hosting there is **no queue worker**, so the jobs sat in
   the `jobs` table forever → "analysis pending" permanently.

### Changes

| # | Change | Why | Affected |
|---|--------|-----|----------|
| 1.1 | **Hard stop on max-questions AND time deadline.** `handleTurn()` now forces a clean conclusion once `asked_count >= max_questions` or the `deadline_at` timestamp passes — regardless of what the model does. | Interview must always finalize so scoring runs (Completion rules A + B). | `app/Services/AI/InterviewEngine.php` |
| 1.2 | **Bilingual closing message.** `defaultClosing()` now returns Arabic or English based on the interview language. | The AI agent should always close naturally in the candidate's language. | `app/Services/AI/InterviewEngine.php` |
| 1.3 | **Candidate "End interview" button + auto-conclude.** Manual end button, plus a client countdown timer that auto-concludes at the max duration. The agent still produces the closing. | Completion rules C (press end) + B (timer). | `resources/views/candidate/room.blade.php` |
| 1.4 | **Abandon sweep now concludes + scores.** Scheduled reaper: an in-progress interview past the grace window with at least one candidate answer is **concluded by the AI** (closing message) and finalized; an interview with no answers is just marked `abandoned`. | Completion rules D/E/F (closed tab, lost connection, inactivity) — never an abrupt termination. | `routes/console.php` |
| 1.5 | **Queue draining on shared hosting.** Scheduler runs `queue:work --stop-when-empty` every minute, so analysis/report/notification jobs actually execute without a daemon. | Makes the analysis pipeline run on Plesk. | `routes/console.php` |
| 1.6 | **AI auto-advance (never reject).** After scoring, `aiScreeningOutcome()` advances StrongHire/Hire → `Qualified` (Approved); everyone else is **held for human review** (stays `ai_screening`); a critical (high-severity, fatal-type) red flag routes to "HR attention required". The AI never sets `Rejected`. | Automated pipeline decision — final rejection always requires a human. | `app/Services/Hiring/ApplicationWorkflow.php`, `app/Jobs/FinalizeInterview.php` |
| 1.7 | **Progress display.** Header shows `Question X / max`, a progress bar, and a live `mm:ss` countdown that turns red in the last 2 minutes. | Question engine display requirement (X/14 + %). | `resources/views/candidate/room.blade.php` |
| 1.8 | **Completion screen.** On conclusion the room switches to a dedicated screen: "Interview Completed Successfully", review message, `Submitted` status, 100% bar. | Required completion screen. | `resources/views/candidate/room.blade.php` |
| 1.9 | **CV upload UX + validation.** Client-side type/size guard with inline ✓ filename / error messages and an "Uploading…" button state; friendly server validation messages. | File-upload bug: clear states + clearer failures. | `resources/views/candidate/intake.blade.php`, `app/Http/Requests/IntakeRequest.php` |
| 1.10 | **OpenAI provider uses native cURL.** Replaced the Guzzle/Http facade call (which forced TLS 1.2 and failed on this server's libcurl) with native cURL. | Fixed the actual blocker behind every "AI service unavailable". | `app/Services/AI/Providers/OpenAiProvider.php` |
| 1.11 | **`avatars.blade.php` syntax fix.** Inline `@php()` with multiple statements → `@php … @endphp` block. | 500 error on the HR Avatars page. | `resources/views/hr/avatars.blade.php` |
| 1.12 | **Jobs edit + archive.** Added update + status-change (archive → closed / re-open) with an inline edit form. | Jobs were create-only. | `app/Http/Controllers/Hr/JobController.php`, `routes/web.php`, `resources/views/hr/jobs.blade.php` |

### Deployment (required)
1. Deploy the changed files.
2. Clear caches: `php artisan config:clear && php artisan route:clear && php artisan view:clear`
3. **Set up the scheduler cron in Plesk → Scheduled Tasks (every 1 minute):**
   `/opt/plesk/php/8.3/bin/php /var/www/vhosts/<domain>/httpdocs/artisan schedule:run`
   This single cron drives queue draining, the abandon sweep, invitation expiry and the GDPR purge.
4. **Bump PHP upload limits** in Plesk → PHP Settings: `upload_max_filesize = 16M`, `post_max_size = 16M`.

---

## Phase 2 — HR Decision Center (DONE)
Rebuilt `resources/views/hr/report.blade.php` from a 5-tab stub into a decision center.
`InterviewController@show` now also passes the linked `JobApplication`.

| Canvas | Contents |
|--------|----------|
| Executive Summary | Score ring, recommendation, AI outcome chip (auto-advanced / pending / HR attention), JD-match, question count, red-flag count, strongest/weakest competency, summary, strengths, concerns. |
| Competencies | Per-competency bar + **confidence %** + rationale + **evidence quotes** pulled from the transcript by `evidence` seq. |
| Behavioral | DISC bars, Big Five bars, growth-mindset, stress-handling, personality type, leadership tendency, observations. |
| Risk Analysis | Red flags sorted by severity, color-coded, with evidence quotes. |
| Resume | CV summary, JD-match score, years, skills, companies, highlights, gaps, suggested focus. |
| Transcript | Chat-styled, seq-numbered. |
| Timeline | Event stream with severity dots. |
| **Final Decision bar** | Advance / Hold / Reject (with reason), permission-gated, wired to `applications.decision`. |

**Affected:** `hr/report.blade.php`, `InterviewController.php`. **Data:** competency_scores, behavioral_analyses, red_flags, interview_reports, cv_analyses, interview_messages, interview_events, job_applications.

## Phase 3 — Pipeline redesign (DONE)
Rebuilt `resources/views/hr/pipeline.blade.php` + `PipelineController@index`.
- **Before:** static columns, a per-card status `<select>` (one POST per move, full reload).
- **After:** reactive Alpine board — **drag & drop** between columns (optimistic, posts to `applications.move_stage`), live **search**, **job** + **recommendation** filters, AI **score + recommendation badges**, interview-status dot, stale-activity (**health**) indicator, multi-select with a floating **bulk move** bar.

## Phase 4 — HR dashboard (DONE)
`DashboardController@index` + `hr/dashboard.blade.php`: added **Active jobs** and **Pending review** metrics, an **Active jobs** panel (open roles + screening volume) and a **Needs attention** panel (completed interviews carrying a high-severity red flag) deep-linking to the Decision Center. Existing funnel + volume chart retained.

## Phase 5 — Edit / Archive + audit (DONE)
**Edit + archive** now available on the create-heavy setup entities:
- **Jobs** — full edit form + Archive/Re-open (Phase 1).
- **Templates** — full edit (name, mode, language, avatar, Q-counts, duration, follow-up, weights) + Archive/Restore via `is_active`. (`TemplateController@update`, `hr/templates.blade.php`)
- **Avatars** — inline edit + active toggle (already present; verified).

### Security audit (findings)
| Area | State | Note |
|------|-------|------|
| CSRF | ✅ | All web forms use `@csrf`; `SecurityHeaders` middleware appended to the web group. |
| Webhook HMAC | ✅ | Avatar + video-analysis webhooks validate HMAC (`routes/api.php`). |
| RBAC | ✅ | Every HR route gated by `can:` abilities; decision actions check `decisions.*`. |
| Rate limiting | ✅ | `answer` 30/min, `audio` 60/min throttles. |
| Upload security | ✅ now | mimes + size validation; client guard added; recommend ClamAV (`watad.uploads.av_scan`) in prod. |
| Sessions | ✅ | Candidate room is session-bound (`session('interview_id')`), 403 otherwise. |
| Data retention | ✅ | `watad:gdpr-purge` scheduled daily (now actually runs via the cron). |

### Integrations status
OpenAI ✅ (native cURL, TLS fix) · Claude ✅ · Tavus/HeyGen ✅ (graceful fallback when disabled/no credits) ·
Google Sheets ✅ (config-gated) · WhatsApp/Email ✅ (notification jobs) · PDF/Excel export ✅.

### Remaining recommendations (not yet implemented)
- Edit/archive UI for **Questions** and **Users** (lower-frequency entities).
- Candidate **portal** polish (the live interview room was redesigned in Phase 1; the post-application portal pages are functional but could get the same progress/status treatment).
- **Resume Intelligence on OpenAI**: `CvAnalyzer` reads PDFs via Anthropic document blocks; when the provider is OpenAI, PDF vision parsing needs an OpenAI-native path (falls back to extracted text today).
- Move queue processing to a real worker (Supervisor) if traffic grows beyond what a 1-min cron drain handles.
