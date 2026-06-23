# 04 — Entity Relationship Diagram

```mermaid
erDiagram
    users ||--o{ role_user : has
    roles ||--o{ role_user : assigned
    roles ||--o{ permission_role : grants
    permissions ||--o{ permission_role : in

    departments ||--o{ job_positions : contains
    departments ||--o{ interview_templates : scopes
    departments ||--o{ question_libraries : scopes
    users ||--o{ departments : manages

    hiring_pipelines ||--o{ pipeline_stages : has
    job_positions }o--|| hiring_pipelines : uses
    job_positions }o--o| interview_templates : default

    avatars ||--o{ interview_templates : voices
    interview_templates ||--o{ template_competencies : weights
    question_libraries ||--o{ questions : holds

    job_positions ||--o{ interview_invitations : opens
    interview_invitations }o--o| candidates : becomes
    candidates ||--o{ candidate_pipeline : tracked_in
    pipeline_stages ||--o{ candidate_pipeline : at

    candidates ||--o{ interviews : sits
    job_positions ||--o{ interviews : for
    interview_templates ||--o{ interviews : configured_by
    avatars ||--o{ interviews : conducted_by
    interview_invitations ||--o| interviews : produces

    interviews ||--o{ interview_messages : transcript
    interviews ||--o{ interview_events : timeline
    interviews ||--o{ recordings : captures
    interview_messages ||--o{ interview_events : anchors

    candidates ||--o{ cv_analyses : profiled
    interviews ||--o| cv_analyses : informs
    interviews ||--o{ competency_scores : scored
    interviews ||--o| behavioral_analyses : profiled
    interviews ||--o{ red_flags : flagged
    interviews ||--o| video_analyses : observed
    interviews ||--|| interview_reports : summarized

    interviews ||--o{ sheet_syncs : exported
    interviews ||--o{ notifications : triggers
    users ||--o{ audit_logs : performs
```

## Cardinality notes

- A **candidate** can have multiple **interviews** (re-screens, different positions), but each
  interview belongs to exactly one job position.
- **competency_scores**, **red_flags**, **recordings**, **interview_messages**,
  **interview_events** are 1‑to‑many off `interviews`.
- **interview_reports**, **behavioral_analyses**, **video_analyses** are 1‑to‑1(0) off
  `interviews` (one finalized artifact each).
- **cv_analyses** is keyed on `candidate_id` and optionally linked to the `interview` that used it.
- RBAC is many-to-many: `users` ↔ `roles` ↔ `permissions`.

See [`docs/03-database-schema.md`](03-database-schema.md) for full column definitions.
