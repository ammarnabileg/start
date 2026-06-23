# 09 — AI Video Interviewer Architecture

The video mode presents the AI interviewer as a **live virtual Watad HR representative**: an avatar
that speaks, lip-syncs, makes eye contact, listens, and interrupts naturally, in a real-time video
room.

> **Build status.** The questioning brain is the same `InterviewEngine` used in text/voice mode
> (already implemented). The avatar + transport layer is implemented as a **provider abstraction**
> (`AvatarProvider` interface + Tavus/HeyGen adapters) and specified end-to-end here. Running it
> live requires paid provider accounts (Tavus/HeyGen) and a LiveKit/WebRTC deployment, so it is not
> exercisable from a bare checkout.

## Provider options (pluggable)

| Provider | What it gives | Adapter |
|---|---|---|
| **Tavus** | Conversational Video Interface — real-time avatar with built-in turn-taking & interruptions | `TavusProvider` |
| **HeyGen Interactive Avatar** | Streaming avatar (LiveKit transport), send text → avatar speaks with lip-sync | `HeyGenProvider` |
| **OpenAI Realtime API + LiveKit** | Low-latency speech-to-speech brain + your own avatar renderer | `RealtimeProvider` (spec) |
| **Synthesia** | High-quality but render-based (async) → better for scripted, not live | `SynthesiaProvider` (spec) |
| **LiveKit** | WebRTC SFU transport used under Tavus/HeyGen/Realtime | transport layer |

`AvatarProvider` interface:

```
createSession(Interview, Avatar): AvatarSession   // returns room URL + token + provider session id
speak(session, text): void                        // push agent text → avatar speaks (lip-synced)
onCandidateUtterance(callback)                     // STT transcript chunks → engine turns
interrupt(session): void                           // barge-in support
endSession(session): RecordingHandle               // stop + return recording reference
```

## Live video sequence

```mermaid
sequenceDiagram
    participant C as Candidate (browser, WebRTC)
    participant FE as Interview room
    participant API as Laravel API
    participant AV as AvatarProvider (Tavus/HeyGen)
    participant LK as LiveKit room
    participant E as InterviewEngine
    participant L as Claude (Sonnet)

    C->>API: POST /interview/{id}/start (mode=video)
    API->>AV: createSession(interview, avatar)
    AV-->>API: {room_url, token, session_id}
    API-->>FE: join token
    FE->>LK: join room (publish cam+mic, subscribe avatar track)
    AV->>E: intro request
    E->>L: generate intro + first question
    E->>AV: speak(intro + question)
    AV-->>C: avatar speaks (lip-sync, eye contact)
    loop each answer
      C->>LK: speaks
      LK->>AV: audio
      AV->>API: candidate transcript chunk (STT)
      API->>E: handleTurn(text)
      E->>L: next question (streamed)
      E->>AV: speak(next question)
      AV-->>C: avatar responds (barge-in supported)
    end
    E->>AV: speak(closing); endSession
    AV-->>API: recording webhook → recordings row (status=ready)
```

## Avatar configuration (Watad cast)

Avatars live in the `avatars` table and are seeded for Watad:

| Name | Role | Style | Lang |
|---|---|---|---|
| Sara | HR Recruiter (Female) | friendly | en/ar |
| Khaled | HR Recruiter (Male) | formal | en/ar |
| Nour | Technical Interviewer | probing | en |
| Omar | Engineering Manager | socratic | en |
| Layla | Sales Director | rapid | en/ar |
| Hana | Customer Success Manager | friendly | en/ar |

Each has: name, role, personality (prompt fragment), questioning style, voice (provider+voice_id),
language, and provider replica/avatar id (`video_replica_id`). Personality flows into the
interviewer system prompt; voice/replica flow into the `AvatarProvider`.

## Realism features & how they're met

| Feature | Mechanism |
|---|---|
| Lip sync, facial expressions, eye contact | Provider avatar engine (Tavus/HeyGen) |
| Natural voice | Provider TTS or ElevenLabs voice bound to the avatar |
| Real-time conversation + interruptions (barge-in) | Provider VAD/turn-taking + `interrupt()`; engine accepts partial turns |
| Follow-up questions | Same adaptive `InterviewEngine` as text mode |
| Professional HR behavior | Avatar persona + interviewer system prompt + fairness guardrails |

## Recording & replay

- Full video + audio recorded by the provider/LiveKit egress → stored to S3 → `recordings` row.
- Transcript (`interview_messages`) and `interview_events` are timestamped with `ms_offset`,
  enabling synchronized replay.
- **Interview Replay Dashboard** (HR) shows, synchronized on one timeline: candidate video ·
  transcript · AI notes/observations · scores · recommendations. Clicking a timeline moment seeks
  the video (see [`docs/10`](10-video-behavioral-analysis.md) and
  [`docs/15`](15-wireframes-ui-ux.md)).

## Failure handling

- Avatar/transport failure mid-interview → automatic **fallback to voice mode** (Web Speech) so the
  interview continues; the drop is logged and the recording marked partial.
- Webhook signature verification on all provider callbacks; retries with idempotency keys.
