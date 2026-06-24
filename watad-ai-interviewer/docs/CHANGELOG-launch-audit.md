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

## Phase 2 — HR Decision Center (planned)
Rebuild `hr/report.blade.php` into a multi-canvas decision center: Executive Summary, Recommendation,
Competency breakdown (score + confidence + evidence quotes), Behavioral/Personality (DISC + Big Five),
Risk analysis, Resume analysis, Interview Evidence Explorer, Timeline, Recruiter notes, Final Decision.

## Phase 3 — Pipeline redesign (planned)
Drag & drop board, fast search, filters, bulk actions, AI recommendation badges, candidate health.

## Phase 4 — Candidate portal + HR dashboard redesign (planned)

## Phase 5 — Security & integrations audit (planned)
CSRF, webhook HMAC, RBAC, audit logs, rate limiting, upload security, retention; OpenAI/Claude/Tavus/
HeyGen/WhatsApp/Sheets/Email/PDF/Excel verification.
