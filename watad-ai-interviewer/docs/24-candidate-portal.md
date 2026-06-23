# 24 — Candidate Portal (Screen by Screen)

A separate candidate-facing app under `/portal/*`, authenticated by the **`candidate` guard**
(`candidate_users`, linked to `candidates`). Clean, mobile-first, bilingual (Arabic RTL + English),
dark-mode. Candidates only ever see their own data.

## Auth & onboarding

| Route | Screen |
|---|---|
| `/portal/register` | Sign up (name, email, password) → email verification |
| `/portal/login` | Login |
| `/portal/verify`, `/portal/forgot-password` | Email verification / reset |
| `/i/{token}` | Invitation link → if no account, register/claim → application created |

Applying: from the public job board (`/jobs`, `/jobs/{slug}`) → **Apply** → creates/links
`candidate_users` + `candidates` + `job_applications` (status `applied`).

---

## 1. Dashboard `/portal`

```
┌───────────────────────────────────────────────┐
│  Welcome back, Mona 👋                          │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌────────┐ │
│  │ Applied │ │ In      │ │ Upcoming│ │ Offers │ │
│  │   3     │ │ review 1│ │ IV  1   │ │   0    │ │
│  └─────────┘ └─────────┘ └─────────┘ └────────┘ │
│  Upcoming interviews ▸  (date, type, join link) │
│  Recent notifications ▸                         │
│  Continue your application ▸ (CTA)              │
└───────────────────────────────────────────────┘
```
Shows: applied jobs count, interview status, **upcoming interviews** (with join link/instructions),
latest notifications, pending actions (finish profile, start AI interview, sign offer).

---

## 2. My Applications `/portal/applications`

List of every application: Job title · Company/Dept · **Current status** · Applied date · next step.
Detail `/portal/applications/{id}`: **application timeline** (Applied → AI Screening → Qualified →
Interviews → Final Review → Offer), current stage highlighted, what happens next, any scheduled
interview, withdraw button. Statuses shown candidate-friendly (e.g., "Under review", "Interview
scheduled", "Offer extended"). Internal scores/notes are **never** exposed.

---

## 3. Interviews `/portal/interviews`

Sections: **AI Interviews** (start/resume the AI video interview — links to the interview room,
[`docs/07`](07-interview-engine-logic.md)), **Scheduled Interviews** (date/time, type, mode,
join link for Zoom/Meet/Teams or onsite address, add-to-calendar .ics), **Previous Interviews**
(completed, read-only), **Instructions** (device/mic/cam check, tips, what to expect, duration).
Reminders pushed via notifications + email/WhatsApp (T-24h, T-1h).

---

## 4. Profile `/portal/profile`

Editable: Personal information, **CV** (upload new version — kept as `candidate_documents`),
Portfolio links, Skills, Experience, Education, Certificates (upload), Languages, expected salary &
notice period, photo. Completeness meter. Changes append to the master profile (versioned); HR sees
the latest plus history.

---

## 5. Notifications `/portal/notifications`

Feed of application updates: received, advanced, interview scheduled/reminder, offer, decision.
Read/unread, mark all read, per-channel preferences (email/WhatsApp/in-app). Backed by
`notifications` (notifiable = candidate).

---

## 6. Offers `/portal/offers`

List of offers with status. Detail `/portal/offers/{id}`: offer letter (PDF preview), role, salary
(if shared), start date, expiry; actions **Accept** / **Decline**, and **e-sign** (digital
signature) on accept → application → `hired`. Countdown to expiry. Backed by `offers`.

---

## Candidate portal — permissions model

The candidate guard is **self-scoped** (no RBAC needed): every query is constrained to
`candidate_users.candidate_id`. Candidates can never read other candidates, internal notes, scores,
or HR data. Sensitive HR fields simply don't exist in portal responses.

## Notifications & automation surfaced here

Email + WhatsApp automation (templates in `message_templates`) drive: application received, AI
interview ready, interview scheduled + reminders, offer extended, decision. Calendar integration
emits `.ics` and (when connected) creates events on the candidate's calendar.

Portal wireframes are in [`docs/29-wireframes-v2.md`](29-wireframes-v2.md).
