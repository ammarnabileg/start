# 23 — Admin Panel — Tabs (Screen by Screen)

Shell: icon rail + section panel + top bar (tab + utilities + user) — see
[`docs/15`](15-wireframes-ui-ux.md). Every screen is RTL-aware, dark-mode capable, mobile
responsive. Each tab lists: **purpose · screen · key fields/columns · filters · actions ·
permissions**.

---

## 1. Dashboard `/hr` — `reports.view`

Purpose: at-a-glance hiring health.
Metrics (stat cards): Total Candidates · Active Jobs · AI Interviews Today · Scheduled Interviews ·
Hired · Rejected · **Funnel Conversion %** · **Hiring Velocity** (avg days applied→hired).
Charts: Interviews over time (daily/weekly/monthly toggle), Hiring funnel, Score distribution,
Source breakdown. Widgets: Recent results, Today's interviews, Pending decisions queue, High-potential alerts.
Actions: date-range picker, drill-through to filtered lists, export.

---

## 2. Candidates `/hr/candidates` — `candidates.view`

List columns: Name · Job(s) · Stage · AI score · Recommendation · Tags · Last activity · Owner.
Filters (advanced, savable): stage, job, department, AI score range, recommendation, tags, source,
country, experience, date range, owner, has-offer. Search: name/email/phone/skills.
Bulk actions: tag, add to talent pool, move stage, send email/WhatsApp, export, reject.
Row → **Master Profile** (`/hr/candidates/{id}`, see [`docs/22`](22-candidate-master-profile.md)).
Actions: New candidate, Import (CSV), Compare (select 2-4 → comparison view), Saved filters.
Permissions: `candidates.view` (+ `.update`/`.delete`, `candidates.move_stage`, `tags`/`notes`).

---

## 3. Jobs `/hr/jobs` — `jobs.view`

List: Title · Department · Seniority · Status · Openings · Applicants · Created.
Detail / editor: title, department, seniority, employment type, location/remote, description,
responsibilities[], **skills requirements** (skill + weight + required), salary band + currency,
default AI template, **evaluation template** (Stage-2 form), **hiring team** assignment
(`interview_panelists` defaults), pipeline.
Actions: Create, Edit, Archive/Close, Duplicate, **Generate invitation link**, Assign hiring team,
Manage evaluation template, Publish to job board.
Permissions: `jobs.view`/`create`/`update`/`delete`, `invitations.create`, `templates.*`.

---

## 4. AI Interviews `/hr/ai-interviews` — `ai_interviews.view`

List: Candidate · Job · Status · Score · Recommendation · Duration · Date.
Detail = **Replay Dashboard**: synced video/audio · transcript · AI notes/observations · competency
scores · behavioral profile · red flags · timeline (jump-to moments) · report PDF.
Actions: Watch replay, Download report/recording (signed), Re-run analysis, Advance/Reject/Override
(decision), Compare. Live tab: monitor in-progress interviews (`ai_interviews.monitor`).
Analytics: avg score by job, score distribution, drop-off, cost/tokens per interview.

---

## 5. Human Interviews `/hr/interviews` — `human_interviews.view`

Views: List + **Calendar**. Columns: Candidate · Job · Type (technical/manager/department/panel) ·
Mode (onsite/online) · Panelists · When · Status · Avg rating.
Schedule form: candidate/application, type, mode, provider (Zoom/Meet/Teams/onsite), date/time/tz,
duration, panelists (any department), auto meeting link, invite message.
Detail: meeting link, candidate profile + AI notes, **dynamic evaluation form** (per job), each
panelist's evaluation, aggregate score, reschedule/cancel.
Permissions: `human_interviews.*`, `interviews.schedule`, `evaluations.create`.

---

## 6. Hiring Pipeline `/hr/pipeline` — `candidates.move_stage`

Visual **Kanban** with **drag & drop**. Columns = pipeline stages: Applied · AI Screening · Qualified
· Technical Interview · Manager Interview · Final Review · Offer · Hired · Rejected.
Card: candidate name, job, AI score chip, recommendation badge, tags, days-in-stage, owner avatar,
quick actions (open, schedule, reject). Drag a card → `hiring_decision` + `application_activities`.
Filters: job, department, owner, tags. Swimlanes by job (optional). WIP counts per column.

---

## 7. Departments `/hr/departments` — `departments.view`

List: Name · Manager · Open jobs · Team size. CRUD departments; assign manager; link default
pipeline/evaluation templates. Permissions: `departments.*`.

---

## 8. Team Members `/hr/users` — `users.view`

List: Name · Email · Roles · Department · Status · Last login. CRUD users; assign roles & department;
activate/deactivate; reset password; 2FA status. Tab: **Roles & Permissions** (`/hr/roles`) — the
CRUD matrix editor + **create custom role**. Permissions: `users.*`, `roles.*`.

---

## 9. Reports & Analytics `/hr/reports` — `reports.view`

Reports: Funnel & conversion, Time-to-hire / hiring velocity, Source quality, Interviewer activity &
calibration (AI vs human agreement), Score distribution & drift, Diversity/adverse-impact (where
lawful), Offer acceptance, Cost-per-hire. Each: filters, charts, table, **export** (`reports.export`).
Saved reports & scheduled email digests.

---

## 10. AI Configuration `/hr/ai-config` — `ai_config.view` (edit: `ai_config.update`)

Configure: **Interview Agent** (persona/avatars, models per role), **Scoring rules** (competency
weights, thresholds, recommendation bands), **Recommendation logic** (override rules, auto-advance
toggles), **Avatar settings** (provider, voice, replica), **Languages** (Arabic/English defaults +
mirroring), prompt versions. Backed by `avatars`, `interview_templates`, `ai_settings`.

---

## 11. Audit Logs `/hr/audit` — `audit.view`

Filterable, append-only log: actor, action, target (polymorphic), before/after diff, IP, UA, time.
Filters: user, action, module, date. Export. Highlights `ai_overridden` decisions and exports.

---

## 12. System Settings `/hr/settings` — `settings.view` (edit: `settings.update`)

Sections: Branding (logo, colors), Localization (default language, RTL, timezone, date format),
**Integrations** (Anthropic/OpenAI keys status, Google Sheets, Zoom, Google Meet, MS Teams, Google/
Microsoft Calendar, WhatsApp, SMTP — `integrations.manage`), **Message templates** (email/WhatsApp,
bilingual), GDPR/retention, Workflow/pipeline config (`workflows.manage`), API access & tokens
(`data.export`), Feature flags.

---

## Cross-cutting UI (every list/detail)

Saved filters · advanced search · bulk actions · column chooser · CSV/Excel export · activity
timeline · keyboard shortcuts · toasts · empty states · skeleton loaders · dark/light · EN/AR + RTL.

Wireframes for the most important screens are in [`docs/29-wireframes-v2.md`](29-wireframes-v2.md).
