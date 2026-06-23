# 18 — Deployment (Docker + Nginx)

## Containers (`docker-compose.yml`)

| Service | Image / role |
|---|---|
| `app` | PHP 8.3-FPM + app code (composer install at build) |
| `nginx` | Reverse proxy / static / FastCGI to `app` |
| `mysql` | MySQL 8 |
| `redis` | Redis 7 (cache, queue, session, broadcast backplane) |
| `queue` | `php artisan queue:work` (AI analysis jobs) — scaled to N |
| `reverb` | `php artisan reverb:start` (WebSockets) |
| `scheduler` | `php artisan schedule:work` (reminders, GDPR purge, sheet retries) |

For production, run `app`+`nginx` behind a load balancer (multiple replicas), MySQL with a replica,
managed Redis, and S3. Queue and Reverb scale independently.

## Request paths

- HTTP/HTTPS → Nginx → PHP-FPM (`app`).
- WebSocket (`/app/*`) → Nginx `proxy_pass` → Reverb.
- Static assets served by Nginx; uploads/recordings/reports live in S3 (never local web root).

## Environment

Key variables (see `.env.example`): `APP_KEY`, `DB_*`, `REDIS_*`, `ANTHROPIC_API_KEY`,
`OPENAI_API_KEY` (optional), `WATAD_AI_*` (models), `AWS_*` / S3, `GOOGLE_APPLICATION_CREDENTIALS`,
`WATAD_SHEETS_*`, `REVERB_*`, mail + WhatsApp creds, `WATAD_GDPR_RETENTION_DAYS`.

## Deploy steps

```bash
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=RolePermissionSeeder --force
docker compose exec app php artisan db:seed --class=AvatarSeeder --force
docker compose exec app php artisan config:cache route:cache view:cache
docker compose exec app php artisan storage:link
```

Zero-downtime: build new image, run migrations (backward-compatible), roll app replicas, then
restart queue/reverb. Long-running queue jobs drain gracefully (`queue:work --stop-when-empty` on
deploy of new workers).

## Scheduler (cron entries via `schedule:work`)
- `interviews:remind` — send reminders for `pending`/`opened` invitations nearing expiry.
- `sheets:retry` — drain `sheet_syncs` where `status=failed` with backoff.
- `gdpr:purge` — `PurgeExpiredCandidateData`.
- `interviews:reap-abandoned` — finalize/abandon stale `in_progress` interviews past grace.

## Health, logs, backups
- `/health` (app + DB + Redis ping) for the LB.
- JSON logs to stdout → shipped to a log store; no PII in logs.
- Nightly MySQL dump + S3 versioning; documented restore runbook.
- Alerts on failed jobs and `interviews.status=error`.

## Scaling notes
- **Workers** scale with interview volume (the analysis fan-out is the heavy path). Tune
  `queue` replicas + Redis.
- **Reverb** scales horizontally with Redis as the backplane.
- **LLM** is the cost/latency driver: prompt caching + Sonnet for live turns + per-interview token
  cap keep it bounded; watch `interviews.llm_*_tokens` dashboards.
- Pin DB + S3 region for data residency (see [`docs/13`](13-security-architecture.md)).

Concrete `Dockerfile`, `docker-compose.yml`, `docker/nginx/default.conf`, and supervisor configs
are included in the repo.
