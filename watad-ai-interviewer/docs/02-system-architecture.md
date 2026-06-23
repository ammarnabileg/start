# 02 — System Architecture

## Tech stack & rationale

| Layer | Choice | Why |
|---|---|---|
| Backend framework | **Laravel 11 (PHP 8.3)** | Mature queue/broadcasting/ORM, fits the requested stack, fast to build the full surface |
| Frontend | **Blade + TailwindCSS + Alpine.js** | Server-rendered, light client state; SaaS-grade UI without an SPA build burden |
| DB | **MySQL 8** | Relational integrity for pipelines/scores; JSON columns for flexible AI payloads |
| Cache / Queue / State | **Redis** | Async AI jobs, rate limiting, live interview session state, broadcasting backplane |
| Real-time | **Laravel Reverb** | First-party WebSocket server; powers the live interview stream + HR live updates |
| Object storage | **S3-compatible** | CVs, recordings, generated PDFs |
| LLM reasoning | **Claude** (`claude-opus-4-8`, `claude-sonnet-4-6`) via `anthropic-ai/sdk`; OpenAI pluggable | Strong reasoning, vision for CV PDFs, prompt caching, tool use, streaming |
| Voice | Web Speech API (baseline) → Deepgram/ElevenLabs adapters | Zero-cost baseline that runs in-browser; upgrade path without rearchitecting |
| Video avatar | Tavus / HeyGen / OpenAI Realtime + LiveKit (adapters) | Best-in-class interactive avatars; abstracted behind a provider interface |
| PDF | `barryvdh/laravel-dompdf` | Blade-to-PDF, no headless browser needed |
| Sheets | `google/apiclient` | Official Sheets v4 client |
| Containerization | Docker + Nginx + PHP-FPM | Reproducible deploy; matches requested stack |

## Why two Claude models

The interview has two very different LLM workloads:

| Workload | Latency need | Model (default) | Notes |
|---|---|---|---|
| **Real-time conversation turns** | Low (candidate is waiting) | `claude-sonnet-4-6` | Streamed, snappy; strong enough to run an adaptive interview |
| **Deep final analysis** (scoring, psychometrics, red flags, report) | Batch (async job) | `claude-opus-4-8` | Highest reasoning quality; runs in the queue, latency irrelevant |

Both are configurable in `config/watad.php`; set both to `claude-opus-4-8` if you prefer maximum
quality for live turns and can accept higher latency. Model IDs are never hard-coded in business
logic — they flow from config through the `LlmManager`. See
[`docs/06-ai-prompt-engineering.md`](06-ai-prompt-engineering.md).

## Component diagram

```mermaid
flowchart TB
    subgraph Client
      CAND["Candidate browser<br/>(intake, interview room,<br/>WebRTC / Web Speech)"]
      HR["HR browser<br/>(dashboard, reports, replay)"]
    end

    subgraph Edge
      NGINX[Nginx reverse proxy]
    end

    subgraph App["Laravel app (PHP-FPM)"]
      WEB[Web + API controllers]
      REVERB[Reverb WebSocket server]
      ENGINE[Interview Engine service]
      LLM[LLM Manager<br/>Claude / OpenAI providers]
      VIDEO[Avatar provider adapters]
    end

    subgraph Workers["Queue workers (Redis-backed)"]
      J1[AnalyzeCv]
      J2[FinalizeInterview<br/>scoring · psychometrics · red flags]
      J3[GenerateReport PDF]
      J4[PushToSheet / Excel]
      J5[SendNotification email·WhatsApp]
    end

    subgraph Data
      MYSQL[(MySQL 8)]
      REDIS[(Redis)]
      S3[(S3 storage)]
    end

    subgraph External
      ANTHROPIC[Anthropic API]
      OPENAI[OpenAI API]
      AVATAR[Tavus / HeyGen / LiveKit]
      GSHEET[Google Sheets API]
      MAIL[SMTP]
      WA[WhatsApp Cloud API]
    end

    CAND & HR --> NGINX --> WEB
    CAND <-->|WebSocket| REVERB
    HR  <-->|WebSocket| REVERB
    WEB --> ENGINE --> LLM --> ANTHROPIC
    LLM -.-> OPENAI
    ENGINE --> VIDEO --> AVATAR
    WEB --> MYSQL
    ENGINE --> REDIS
    WEB --> REDIS
    WEB -. dispatch .-> Workers
    J1 & J2 --> LLM
    J2 & J3 --> MYSQL
    J3 --> S3
    J4 --> GSHEET
    J5 --> MAIL & WA
    REVERB --- REDIS
    WEB --> S3
```

## Real-time interview sequence

```mermaid
sequenceDiagram
    participant C as Candidate
    participant W as Web/API
    participant E as Interview Engine
    participant R as Redis (session state)
    participant L as LLM (Claude Sonnet, streamed)
    participant WS as Reverb

    C->>W: POST /interview/{id}/answer (text or transcribed speech)
    W->>E: handleTurn(interviewId, answer)
    E->>R: load session state (phase, threads, coverage)
    E->>L: stream(system + cached job/CV context + transcript + answer + tools)
    L-->>WS: token deltas → broadcast to candidate channel
    L->>E: final message (next question / follow-up / tool calls)
    E->>R: update state (coverage, thread depth, flags)
    E->>W: persist interview_message rows, emit interview_events
    W-->>C: rendered next question (and TTS / avatar video in voice/video mode)
    Note over E,L: When coverage complete or time/limit reached → status=processing,<br/>dispatch FinalizeInterview job
```

## Async finalization pipeline

```mermaid
flowchart LR
    A[Interview completed] --> B[FinalizeInterview job]
    B --> C[ScoringService → competency_scores]
    B --> D[BehavioralAnalyzer → behavioral_analyses]
    B --> E[RedFlagDetector → red_flags]
    B --> F[VideoAnalysisService → video_analyses<br/>video mode only]
    C & D & E & F --> G[Compose overall score + recommendation]
    G --> H[GenerateReport → PDF to S3]
    G --> I[PushToSheet → Google Sheets row]
    G --> J[SendNotification → HR: new result / high potential]
    G --> K[Broadcast dashboard update via Reverb]
```

## Deployment topology

```mermaid
flowchart TB
    LB[Load balancer / TLS] --> N1[Nginx + PHP-FPM x N]
    N1 --> DB[(MySQL primary + replica)]
    N1 --> RD[(Redis)]
    N1 --> ST[(S3)]
    QW[Queue workers x M] --> RD
    QW --> DB
    RV[Reverb node] --- RD
    CRON[Scheduler<br/>reminders · GDPR purge · sheet retries] --> DB
```

See [`docs/18-deployment.md`](18-deployment.md) for the Docker/Nginx specifics and
[`docs/13-security-architecture.md`](13-security-architecture.md) for trust boundaries.
