# 11 — Google Sheets Integration & Excel Export

## Goal

When an interview is finalized, append a row to a configured Google Sheet and keep an Excel export
available on demand — so HR can work in their existing spreadsheets without logging in.

## Auth model

A **Google service account** (recommended for server-to-server, no per-user OAuth):

1. Create a service account in Google Cloud; enable the **Google Sheets API**.
2. Download the JSON key → mount it; set `GOOGLE_APPLICATION_CREDENTIALS=/path/key.json`.
3. Share the target spreadsheet with the service-account email (Editor).
4. Set `WATAD_SHEETS_SPREADSHEET_ID` and `WATAD_SHEETS_TAB` (default `Candidates`).

(OAuth user-consent flow is also supported for HRs who prefer their own Drive; the
`GoogleSheetsService` accepts either credential source.)

## Column layout (exact, in order)

| Col | Header | Source |
|---|---|---|
| A | Candidate ID | `interviews.public_id` |
| B | Date | `interviews.completed_at` (ISO) |
| C | Position | `job_positions.title` |
| D | Name | `candidates.full_name` |
| E | Email | `candidates.email` |
| F | Phone | `candidates.phone` |
| G | Country | `candidates.country` |
| H | Experience (yrs) | `candidates.years_experience` |
| I | Interview Score | `interviews.overall_score` |
| J | Technical Score | `competency_scores[technical]` |
| K | Soft Skills Score | avg(`communication`,`confidence`,`culture_fit`,`professionalism`) |
| L | Recommendation | `interviews.recommendation` |
| M | Status | pipeline stage / `interviews.status` |
| N | Report | signed PDF URL |

The header row is ensured once (created if the tab is empty).

## Flow

```mermaid
sequenceDiagram
    participant F as FinalizeInterview
    participant P as PushToSheet job
    participant G as Google Sheets API
    participant DB as sheet_syncs

    F->>DB: create sheet_syncs (status=pending)
    F->>P: dispatch PushToSheet(interviewId)
    P->>G: spreadsheets.values.get (ensure header)
    P->>G: spreadsheets.values.append (one row, USER_ENTERED)
    G-->>P: updatedRange (e.g. Candidates!A42)
    P->>DB: status=synced, row_number=42, synced_at=now
    Note over P,DB: on API error → status=failed + error; retried by scheduler (backoff)
```

- **Idempotency**: `sheet_syncs` has one row per interview; a successful append records
  `row_number`. Re-runs **update** the existing row (via `row_number`) instead of appending a
  duplicate. `POST /api/interviews/{id}/resync-sheet` forces a retry.
- **Batching**: high-volume periods append via `values.batchUpdate` to respect Sheets quotas; the
  scheduler drains `sheet_syncs` where `status=failed` with exponential backoff.
- **Quota/resilience**: 429/5xx from Sheets are retried; the interview is never blocked on Sheets —
  it's a side effect of finalization, not part of it.

## Excel export

On-demand, no external dependency, via `ExcelExportService` (`openspout/openspout` streaming
writer for large datasets):

- `GET /api/export/interviews.xlsx?status=&recommendation=&job=&from=&to=` streams an `.xlsx` with
  the same columns as the sheet plus per-competency score columns.
- Streamed write keeps memory flat for tens of thousands of rows.
- Each export writes an `audit_logs` entry (`action=exported`).

## Configuration (`config/watad.php`)

```php
'sheets' => [
    'enabled'        => env('WATAD_SHEETS_ENABLED', false),
    'spreadsheet_id' => env('WATAD_SHEETS_SPREADSHEET_ID'),
    'tab'            => env('WATAD_SHEETS_TAB', 'Candidates'),
    'credentials'    => env('GOOGLE_APPLICATION_CREDENTIALS'),
],
```
