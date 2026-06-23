# 15 — Wireframes, Dashboard & UI/UX

## Design language

Clean, enterprise SaaS — Linear / Notion / Stripe Dashboard influence.

- **Layout**: left sidebar nav + top bar (search, notifications, user). Content in cards on a
  neutral canvas. Generous whitespace, 8px spacing grid.
- **Type**: Inter (LTR) / Cairo (RTL). Tight, legible hierarchy.
- **Color**: neutral grays; a single brand accent (Watad). Semantic colors for recommendation
  badges (green/teal/amber/red).
- **Modes**: light + dark (Tailwind `dark:` + a persisted toggle). **Full RTL** support
  (`dir="rtl"`, logical properties) for Arabic; language switch (en/ar) in the top bar.
- **Components**: Alpine.js for interactivity (dropdowns, tabs, live regions, the interview room);
  no heavy SPA framework.
- **Accessibility**: WCAG AA contrast, keyboard nav, ARIA on the live transcript region.

## HR Dashboard

```
┌───────────────────────────────────────────────────────────────────────────┐
│ Watad AI Interviewer        🔍 search            🌐 EN/AR  🌗  🔔  ▢ Ammar ▾ │
├──────────┬────────────────────────────────────────────────────────────────┤
│ Dashboard│  Overview                                          [ This week ▾]│
│ Jobs     │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌────────────┐  │
│ Candidates│ │ Total   │ │ Today   │ │ Hired   │ │ Rejected│ │ Avg score  │  │
│ Interviews│ │  1,284  │ │   37    │ │   92    │ │  610    │ │   71.4     │  │
│ Reports  │  └─────────┘ └─────────┘ └─────────┘ └─────────┘ └────────────┘  │
│ Templates│  ┌───────────────────────────┐  ┌───────────────────────────┐    │
│ Avatars  │  │ Interviews / day (chart)  │  │ Hiring funnel             │    │
│ Settings │  │  ▁▂▅▇▆▃▂  daily|weekly|mo │  │ Applied ███████ 1284      │    │
│ Audit    │  └───────────────────────────┘  │ AI Screened ████ 980      │    │
│          │  ┌──────────────────────────────│ Shortlisted ██ 240        │    │
│          │  │ Recent results (table)       │ Human IV █ 110            │    │
│          │  │ Name · Job · Score · Reco · ⏱│ Offer ▏ 40 · Hired ▏ 92   │    │
│          │  └──────────────────────────────┴───────────────────────────┘    │
└──────────┴────────────────────────────────────────────────────────────────┘
```

Metrics: Total Candidates · Interviews Today · Hired · Rejected · **Conversion rate** · **Avg
interview score**. Charts: interviews over time (daily/weekly/monthly toggle) + hiring funnel.
Recent results table with score + recommendation badges; click → report.

## Candidate intake screen

```
┌──────────────────────────── Watad ────────────────────────────┐
│  You're invited to interview for: Senior Backend Engineer       │
│  Estimated time: ~20 min · Conducted by Sara (AI HR)            │
│                                                                 │
│  Full name [_______]      Email [_______]                       │
│  Mobile    [_______]      LinkedIn [_______]                    │
│  Country   [____ ▾]       Years of experience [__]              │
│  Expected salary [____]   Notice period [____ ▾]                │
│  Upload CV  [ Drop PDF/DOCX or browse ]                         │
│                                                                 │
│  ☑ I consent to processing & recording of this interview        │
│            [ Check mic & camera ]   [ Start interview → ]       │
└─────────────────────────────────────────────────────────────────┘
```

## Interview room (candidate)

```
┌──────────────────────────────────────────────────────────────────┐
│  Watad · Senior Backend Engineer        ● Recording   ⏱ 12:04     │
│                                                                    │
│   text/voice mode:                video mode:                      │
│   ┌──────────────────────────┐    ┌───────────────┬─────────────┐ │
│   │  Sara (AI HR)            │    │  [AI avatar]  │ [self cam]  │ │
│   │  "Tell me about a time…" │    │   speaking    │             │ │
│   │  ……streaming tokens……    │    └───────────────┴─────────────┘ │
│   │                          │     transcript caption overlay     │
│   │  [ your transcript ]     │                                    │
│   └──────────────────────────┘                                    │
│   [ 🎤 hold to speak ]  or  [ type your answer…           ] [Send] │
│   Progress: ●●●●●○○ (5/—)  phase: probing                          │
└──────────────────────────────────────────────────────────────────┘
```

Live agent text streams in (token deltas via WebSocket). Voice mode adds push-to-talk (Web Speech
STT) + TTS playback. Video mode embeds the avatar + self-view (LiveKit), with caption overlay.

## Report screen (HR)

```
┌──────────────────────────────────────────────────────────────────┐
│  ‹ Back   Mona Adel · Senior Backend Engineer    [⬇ PDF] [Move ▾]  │
│  Overall  78  ████████░░   Recommendation:  ◍ Hire                 │
├───────────────┬────────────────────────────────────────────────────┤
│ Scores        │ Competency bars: technical 82 ▇▇▇▇  comm 74 ▇▇▇    │
│ Behavioral    │ DISC: D70 I40 S55 C80   Big-Five: …                │
│ Red flags (1) │ ⚠ salary_mismatch (medium): expects 2× band        │
│ Transcript    │ Strengths / Weaknesses · Technical & Behavioral    │
│ Replay        │ AI analysis · Hiring recommendation narrative      │
└───────────────┴────────────────────────────────────────────────────┘
```

## Interview Replay Dashboard (HR, video mode)

```
┌──────────────────────────────────────────────────────────────────┐
│  Replay · Mona Adel                                                │
│  ┌───────────────────────┐   Transcript (synced) ▶                │
│  │  [candidate video]    │   00:03 Sara: Welcome to Watad…        │
│  │       ▶  ───●────────  │   00:21 Mona: Thanks, I'm a…           │
│  └───────────────────────┘   …auto-scrolls with playhead…         │
│  Timeline:  ●00:03 intro  ●02:11 confidence↑  ●05:22 strong tech  │
│             ●08:14 inconsistency  ●11:47 leadership  ●16:02 weak   │
│  AI notes • Scores • Recommendation  (tabs, all synced to playhead)│
└──────────────────────────────────────────────────────────────────┘
```

Clicking any timeline moment (`interview_events`) seeks the video and scrolls the transcript.
Video · transcript · AI notes · scores · recommendation are synchronized in one interface.

## Admin screens

- **Jobs**: list (status chips, dept, openings) → create/edit (requirements builder, salary band,
  pipeline, default template) → "Generate invitation link" modal.
- **Templates**: mode, language, min/max questions, duration, competency weights (sliders),
  toggles (detect contradictions, measure confidence, English eval), avatar pick.
- **Avatars**: cast cards (name, role, style, language, provider) → edit persona/voice/replica.
- **Question libraries**: per-department banks, CRUD, competency tags, bilingual text.
- **Users & roles**: invite, assign roles, department scope; permission matrix view.
- **Settings**: integrations (Anthropic/OpenAI keys status, Google Sheets connect, WhatsApp),
  retention/GDPR, branding.
- **Audit**: filterable log table.

## States & feedback

- Skeleton loaders, empty states with primary CTA, toast notifications, optimistic stage moves.
- Live regions announce streamed agent text for screen readers.
- Error/abandon states on the candidate side are friendly and recoverable (rejoin within grace).
