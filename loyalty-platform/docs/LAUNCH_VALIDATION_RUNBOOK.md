# 🚀 Final Launch Validation Runbook — Hatchy Loyalty Platform

**Audience:** release engineer / QA lead running launch‑week sign‑off.
**Scope:** Supabase (Postgres + Edge Functions + Storage + Realtime), Customer app, Merchant app.
**Source of truth:** `sql/schema.sql`, `supabase/functions/*`, `apps/customer_app`, `apps/merchant_app`.
**Rule:** every item below is **blocking** unless explicitly marked *(non‑blocking)*. Do not publish until the GO/NO‑GO checklist at the end is 100% green.

**Environments:** run §1–§12 against a **staging** Supabase project that mirrors prod. Run §13–§15 with release artifacts. Only after staging is green do you apply the same migration to **prod** and re‑run the smoke subset (§ marked 🔁).

**Legend:** ✅ pass · ❌ fail · 🔁 re‑run on prod after cutover.

---

## How to read each test
Every test has: **Objective · Preconditions · Steps · Expected · Failure indicators.** A test passes only when the Expected result is observed *and* none of the Failure indicators appear.

---

# 1. Database Validation

### 1.1 — Migration applies cleanly from zero
- **Objective:** `sql/schema.sql` builds the entire schema on an empty database without error.
- **Preconditions:** fresh Postgres 16 / empty Supabase project; `psql` access.
- **Steps:**
  1. `psql "$DB_URL" -v ON_ERROR_STOP=1 -f sql/schema.sql`
  2. Re‑run the same command a second time (idempotency check).
- **Expected:** first run completes with `0` errors; second run is also clean (script uses `if not exists` / `create or replace`).
- **Failure indicators:** any `ERROR:`; `relation already exists`; partial apply leaving missing objects.

### 1.2 — All 37 tables present
- **Objective:** every expected table exists in `public`.
- **Steps:** `select count(*) from information_schema.tables where table_schema='public';` and diff the names against the list below.
- **Expected:** all present — `audit_log, branches, coupon_redemptions, coupons, device_tokens, entity_branches, idempotency_keys, loyalty_levels, lucky_wheels, merchant_limits, merchant_questions, merchant_roles, merchant_settings, merchant_staff, merchants, notification_campaigns, notifications, platform_settings, points_transactions, pos_api_keys, proximity_notifications_log, question_options, question_responses, rate_limits, referrals, reports, reward_redemptions, rewards, subscriptions, super_admins, user_prizes, user_stores, user_visits, users, visit_campaigns, wheel_segments, question_responses`.
- **Failure indicators:** any missing table.

### 1.3 — All 37 RPCs present and callable
- **Objective:** every function exists with the expected signature.
- **Steps:** `select proname from pg_proc p join pg_namespace n on n.oid=p.pronamespace where n.nspname='public' order by 1;`
- **Expected:** includes the money/critical RPCs: **`wallet_apply`**, **`idem_claim`**, **`get_or_create_wallet`**, **`register_merchant`**, **`merchant_entitled`**, **`rate_limit_hit`**, **`current_staff_can`**, **`staff_can`**, **`process_referral_on_visit`**, **`decrement_stock`**, plus the scheduled jobs `expire_subscriptions`, `expire_coupons`, `grant_birthday_rewards`, `purge_idempotency`, `purge_rate_limits`, `refresh_analytics`.
- **Failure indicators:** any missing; signature mismatch vs. how edge functions call it.

### 1.4 — RLS enabled on every table (36 policies)
- **Objective:** no table is silently world‑readable.
- **Steps:** `select relname from pg_class c join pg_namespace n on n.oid=c.relnamespace where n.nspname='public' and c.relkind='r' and c.relrowsecurity=false;`
- **Expected:** the query returns **only** intentionally‑public/config tables (verify each one in the result is deliberate). 36 tables have RLS enabled.
- **Failure indicators:** any user‑data table (`users`, `points_transactions`, `wallets/subscriptions`, `notifications`, `device_tokens`, `reports`, `referrals`) appears with RLS **off**.

### 1.5 — Critical RLS behaviour (cross‑tenant isolation)
- **Objective:** a customer cannot read another customer's rows; a merchant staffer cannot read another merchant's data.
- **Preconditions:** two seeded customers (A, B) and two merchants (M1, M2) with staff.
- **Steps:** using A's JWT, `select * from points_transactions where user_id = <B.id>`; using M1 staff JWT, `select * from merchant_customers(<M2.id>)` and `select * from reports where merchant_id=<M2.id>`.
- **Expected:** 0 rows in every cross‑tenant query.
- **Failure indicators:** any row from the other tenant returned; error revealing existence of the row.

### 1.6 — `super_admins` gate
- **Objective:** platform‑admin RPCs (`platform_overview`, `admin-merchant`) are reachable only by super admins.
- **Steps:** call `is_super_admin()` as a normal user (expect `false`); call `platform_overview()` as a normal user.
- **Expected:** `false`; platform RPC denied / empty.
- **Failure indicators:** non‑admin gets platform data.

### 1.7 — Indexes (38) and FK coverage
- **Objective:** all foreign keys used in hot queries are indexed (no seq‑scan under load).
- **Steps:** `select count(*) from pg_indexes where schemaname='public';` (expect ≥ 38). Spot‑check `explain (analyze)` on: customer wallet read, `merchant_customers`, `points_transactions` by `user_id`, `notifications` by `user_id`, `device_tokens` by `user_id`.
- **Expected:** index scans (not seq scans) on the FK columns.
- **Failure indicators:** seq scan on a large table in any hot path.

### 1.8 — Constraints & data integrity
- **Objective:** money/identity invariants are enforced at the DB.
- **Steps:** attempt to (a) insert a `points_transactions` row that would drive a wallet negative via direct insert; (b) insert duplicate `idempotency_keys` (same key); (c) insert `merchant_staff` with duplicate (merchant, user).
- **Expected:** (a) blocked by `wallet_apply` non‑negative logic / check; (b) unique‑violation; (c) unique‑violation.
- **Failure indicators:** negative balance possible; duplicate idempotency key accepted; duplicate staff row.

---

# 2. Edge Function Validation

**Shared invariants (apply to all 20 functions):**
- **Auth:** every customer/merchant function requires a valid Supabase JWT (`_shared/auth.ts`); missing/expired token → `401`.
- **CORS:** preflight `OPTIONS` returns the `_shared/cors.ts` headers.
- **Rate limiting:** money/abuse‑prone functions call `rate_limit_hit` (`_shared/ratelimit.ts`) → `429` when exceeded.
- **Idempotency:** money mutations take `idempotency_key` and use `idem_claim` (`_shared/idempotency.ts`) → identical replay returns the original result, never double‑applies.
- **Balance:** all balance changes go through `wallet_apply` (atomic, non‑negative).

| # | Function | Expected input | Expected output | Success case | Failure cases |
|---|---|---|---|---|---|
| 1 | **verify-qr** | `{ payload }` (signed, version‑bound) | `{ wallet, user, ... }` | valid current‑version token → wallet/identity resolved | bad signature → `401`; stale version → reject; tampered payload → reject |
| 2 | **add-points** | `{ user_id/qr, amount, idempotency_key }` | updated balance | staff earns points for customer via `wallet_apply` | unauthorized staff → `403`; replay key → same result; negative/zero amount → `400` |
| 3 | **redeem-reward** | `{ reward_id, idempotency_key }` | redemption (`status: pending`/applied) | sufficient balance → reward reserved, points debited | insufficient balance → `400`; replay → original; out‑of‑stock → `400` |
| 4 | **confirm-redemption** | `{ redemption_id / code }` | confirmed redemption | staff confirms pending redemption | already confirmed → idempotent; wrong merchant → `403` |
| 5 | **staff-redeem** | `{ code, idempotency_key }` | redemption result | staff‑initiated redeem at POS | invalid code → `400`; cross‑merchant → `403` |
| 6 | **redeem-prize** | `{ prize_id, idempotency_key }` | prize claim | valid prize → claimed, stock decremented | no stock → `400`; replay → original |
| 7 | **confirm-prize** | `{ user_prize_id }` | confirmed | staff confirms prize handover | wrong merchant → `403`; double confirm → idempotent |
| 8 | **spin-wheel** | `{ wheel_id, idempotency_key }` | winning segment | eligible spin → segment + reward applied atomically | not entitled / no spins → `400`; replay → original |
| 9 | **answer-question** | `{ question_id, option_id }` | reward/points | valid answer recorded once | duplicate answer → blocked; closed question → `400` |
| 10 | **apply-coupon** | `{ code, idempotency_key }` | discount/points | valid, unexpired coupon → applied | expired → `400`; already used → `400`; replay → original |
| 11 | **record-visit** | `{ merchant_id / branch, qr }` | visit + referral side‑effects | visit logged; `process_referral_on_visit` fires once | duplicate within window → deduped |
| 12 | **proximity-hit** | `{ branch_id, location }` | proximity notification | within radius + quota → push logged | outside radius → no push; over quota → suppressed |
| 13 | **claim-staff** | `{ claim_secret }` | staff membership | valid secret → user joined as staff | wrong/used secret → `403`; expired → `403` |
| 14 | **register-merchant** (via **admin-merchant**/`register_merchant`) | merchant + owner payload | merchant + trial subscription | new merchant created with trial | duplicate merchant → `409`; invalid payload → `400` |
| 15 | **admin-merchant** | admin action payload | merchant mutation | super admin approves/suspends merchant | non‑admin → `403` |
| 16 | **send-announcement** | `{ title, body, audience }` | campaign + push fan‑out | within notification quota → queued/sent | over `merchant_notification_quota` → `429`/`400`; not entitled → `403` |
| 17 | **submit-report** | `{ type, payload }` (validated) | report row | valid report stored | invalid/oversized payload → `400`; rate‑limited → `429` |
| 18 | **pos-keys** | key mgmt payload | api key (once) | owner creates/rotates POS key | non‑owner → `403` |
| 19 | **pos-api** | POS request + API key | points/redeem result | valid key → POS operation | bad/revoked key → `401`; rate‑limited → `429` |
| 20 | **delete-account / export-data** | auth only | deletion / data export (GDPR) | user exports then deletes own data | unauthenticated → `401`; cannot touch another user |

### 2.A — Per‑function smoke (run for all 20)
- **Objective:** each function is deployed, reachable, and enforces auth + (where applicable) rate‑limit + idempotency.
- **Steps:** for each function: (1) `OPTIONS` → CORS 200; (2) call with **no JWT** → `401`; (3) call valid happy path → expected output; (4) for money paths, **replay the same `idempotency_key`** → identical result and **balance unchanged on replay**; (5) exceed the rate limit → `429`.
- **Expected:** all five behaviours as specified per row above.
- **Failure indicators:** function 404 (not deployed); `500` on happy path; replay double‑applies; missing auth check; CORS missing.

### 2.B — `wallet_apply` is the only balance mutator
- **Objective:** no edge function writes balances directly.
- **Steps:** `grep -rn "points_transactions" supabase/functions` and confirm every balance change routes through `wallet_apply`/`get_or_create_wallet`.
- **Expected:** no direct balance arithmetic outside `wallet_apply`.
- **Failure indicators:** any function inserting/adjusting balance without `wallet_apply`.

---

# 3. Customer App — E2E

> Build: `cd apps/customer_app && flutter run` against staging (`Env.supabaseUrl`).

### 3.1 — Onboarding & auth
- **Objective:** phone‑OTP sign‑up/login works; session persists.
- **Steps:** launch → enter phone → OTP → land on home. Kill & relaunch.
- **Expected:** session restored; home shows wallet.
- **Failure indicators:** OTP fails; session lost on relaunch.

### 3.2 — My QR identity (version‑bound, rotating)
- **Objective:** customer QR resolves at merchant and rotates.
- **Steps:** open "رمزي" tab; have merchant scan via `verify-qr`; wait for rotation interval; scan again.
- **Expected:** both scans resolve to the same customer; an **old/expired token is rejected** by `verify-qr`.
- **Failure indicators:** stale token still accepted; QR fails to resolve.

### 3.3 — Earn points
- **Objective:** points credited and reflected live.
- **Steps:** merchant runs `add-points`; watch customer home.
- **Expected:** balance increases in real time (Realtime), transaction appears in history.
- **Failure indicators:** balance stale until manual refresh; double credit.

### 3.4 — Redeem reward / prize / spin / coupon / question
- **Objective:** each earn‑burn mechanic works and is idempotent.
- **Steps:** perform one of each: redeem reward, claim prize, spin wheel, apply coupon, answer question. Force a network retry on one redeem.
- **Expected:** balance debited exactly once; reward/prize shows as pending→confirmed; retry does **not** double‑spend.
- **Failure indicators:** double debit; negative balance; UI shows success but no DB change (or vice‑versa).

### 3.5 — Stores list & store detail (realtime)
- **Objective:** "متاجري" and store detail update live.
- **Steps:** open a store; have merchant change a reward/level; observe.
- **Expected:** store detail reflects change without leaving the screen.
- **Failure indicators:** stale content; missing realtime update.

### 3.6 — Notifications (realtime + unread badge)
- **Objective:** new notification appears live; bottom‑nav badge shows unread count; opening clears it.
- **Steps:** trigger a notification (earn/announcement); observe nav badge; open Notifications; reopen app.
- **Expected:** badge increments live; list refreshes live; opening marks all read → badge clears; count is correct.
- **Failure indicators:** badge never appears/clears; list requires manual refresh; wrong count.

### 3.7 — Empty / loading / error states
- **Objective:** no raw errors or blank screens.
- **Steps:** new account (empty wallet/stores/notifications); turn off network mid‑load.
- **Expected:** friendly empty states; loading spinners; graceful error with retry. **No empty‑name crash** (guard in place).
- **Failure indicators:** red error screen; infinite spinner; RangeError on empty data.

### 3.8 — GDPR (export & delete)
- **Objective:** customer can export and delete their own account.
- **Steps:** Settings → export data; then delete account.
- **Expected:** export returns the user's data; delete removes account & sessions.
- **Failure indicators:** export includes other users; delete leaves residual data.

---

# 4. Merchant App — E2E

> Build: `cd apps/merchant_app && flutter run` against staging.

### 4.1 — Owner onboarding / registration
- **Objective:** new merchant registers and gets a trial.
- **Steps:** register flow → `register_merchant`.
- **Expected:** merchant created in `merchants`, `subscriptions` row with **trial**; dashboard loads.
- **Failure indicators:** no trial row; merchant entitled = false on day 1.

### 4.2 — Staff claim (RBAC)
- **Objective:** staff joins via `claim-staff` with correct role.
- **Steps:** owner generates claim secret; staff device claims it.
- **Expected:** `merchant_staff` row with role; permissions enforced by `current_staff_can`.
- **Failure indicators:** wrong/used secret accepted; staff gets owner powers.

### 4.3 — Scanner → earn / redeem
- **Objective:** scan customer QR and run earn/redeem at POS.
- **Steps:** Scanner tab → scan customer → add points; then confirm a redemption (`confirm-redemption`/`staff-redeem`).
- **Expected:** customer balance updates; redemption confirmed; audit row written.
- **Failure indicators:** scan of another merchant's identity succeeds where it shouldn't; double apply.

### 4.4 — Management hub (rewards / levels / coupons / questions / wheel)
- **Objective:** CRUD on loyalty entities respects branch scoping & permissions.
- **Steps:** create a reward, a level, a coupon, a question, a wheel segment; scope one to a branch.
- **Expected:** entities saved; branch scoping via `entity_branches` honored; customers in scope see them.
- **Failure indicators:** permission bypass; entity visible to wrong branch.

### 4.5 — Announcements (quota)
- **Objective:** send‑announcement respects the notification quota.
- **Steps:** send announcements until `merchant_notification_quota` exceeded.
- **Expected:** within quota → delivered; over quota → blocked with clear message.
- **Failure indicators:** unlimited sends; quota miscounted.

### 4.6 — Dashboard & analytics
- **Objective:** `dashboard_summary` / `analytics_summary` show correct, tenant‑scoped numbers.
- **Steps:** open dashboard after seeded activity.
- **Expected:** counts match seeded data for this merchant only.
- **Failure indicators:** other merchants' data bleeds in; numbers wrong.

### 4.7 — Suspended / unavailable gate
- **Objective:** suspended or unentitled merchant sees the "غير متاح" screen, not the shell.
- **Steps:** super admin suspends merchant; reopen app.
- **Expected:** `merchant_entitled` = false → unavailable screen; no scanning/earning possible.
- **Failure indicators:** suspended merchant still operates.

---

# 5. Cross‑App E2E

### 5.1 — Full loyalty loop
- **Objective:** merchant action reflects on customer instantly and durably.
- **Steps:** merchant earns 50 pts for customer → customer redeems a 50‑pt reward → merchant confirms.
- **Expected:** customer ends at correct balance; both apps consistent; one `points_transactions` debit + credit; audit trail complete.
- **Failure indicators:** balance mismatch between apps; missing audit.

### 5.2 — Referral end‑to‑end
- **Objective:** referral grants reward on first qualifying visit.
- **Steps:** customer A refers B; B visits merchant (`record-visit` → `process_referral_on_visit`).
- **Expected:** referral marked complete **once**; reward granted per rules.
- **Failure indicators:** double‑grant; grant without a real visit.

### 5.3 — POS API parity
- **Objective:** POS (`pos-api`) earn/redeem matches in‑app behaviour.
- **Steps:** with a POS key, earn and redeem; compare to app result.
- **Expected:** identical balance effects + idempotency.
- **Failure indicators:** POS path bypasses `wallet_apply`/idempotency.

---

# 6. Subscription Enforcement

### 6.1 — Active subscription allows operations
- **Objective:** entitled merchant can scan/earn/redeem/announce.
- **Steps:** ensure active `subscriptions` row; perform core ops.
- **Expected:** `merchant_entitled` = true; all ops succeed.
- **Failure:** entitled merchant blocked.

### 6.2 — Expired subscription blocks operations
- **Objective:** expiry disables the merchant.
- **Steps:** set subscription end in the past; run `expire_subscriptions`; reopen merchant app and call a money path.
- **Expected:** unavailable screen; money edge functions reject (entitlement check); customers cannot earn at this merchant.
- **Failure:** expired merchant still earns/redeems.

### 6.3 — Server‑side enforcement (not just UI)
- **Objective:** entitlement is enforced in edge functions, not only the app.
- **Steps:** with an expired merchant, call `add-points`/`send-announcement` directly via HTTP.
- **Expected:** rejected server‑side.
- **Failure:** server accepts despite expiry.

---

# 7. Trial Enforcement

### 7.1 — Trial active
- **Objective:** new merchant operates during trial.
- **Steps:** fresh registration; perform core ops within trial window.
- **Expected:** entitled; ops succeed; dashboard shows trial state.
- **Failure:** trial merchant blocked.

### 7.2 — Trial expiry → conversion gate
- **Objective:** trial end transitions to unentitled until paid.
- **Steps:** set trial end in past; run `expire_subscriptions`; reopen.
- **Expected:** unavailable screen until a paid subscription exists.
- **Failure:** expired trial keeps full access.

### 7.3 — No trial stacking
- **Objective:** a merchant cannot re‑trigger a second trial.
- **Steps:** attempt re‑registration / re‑trial for same owner/merchant.
- **Expected:** blocked; single trial honored.
- **Failure:** repeated trials granted.

---

# 8. Realtime Sync

### 8.1 — Wallet balance live
- **Objective:** balance changes propagate without refresh.
- **Steps:** merchant earns/customer redeems; watch the other side.
- **Expected:** update < 2 s, no manual refresh.
- **Failure:** stale until reopen.

### 8.2 — Notifications live + badge (see 3.6)
- **Expected:** new notification + unread badge update live; clears on open.

### 8.3 — Store detail live (see 3.5)
- **Expected:** reward/level edits reflect live in customer store detail.

### 8.4 — Reconnect behaviour
- **Objective:** realtime recovers after network drop.
- **Steps:** drop network 30 s during a session; restore.
- **Expected:** channels resubscribe; missed state reconciles on next read.
- **Failure:** permanently stale until app restart.

---

# 9. Push Notifications

> Requires Firebase config (`google-services.json`) present and the `gms` Gradle plugin enabled. Code is wired (`PushService` in both apps).

### 9.1 — Token registration (both apps)
- **Objective:** FCM token is stored in `device_tokens` after auth.
- **Steps:** log in (customer, then merchant staff); inspect `device_tokens` for the user with correct `platform`.
- **Expected:** one row per (user, token); refresh updates it (`onTokenRefresh`).
- **Failure:** no token row when Firebase configured; wrong platform.

### 9.2 — Delivery (foreground/background)
- **Objective:** announcement/earn push is delivered.
- **Steps:** trigger `send-announcement`; observe device in foreground and background.
- **Expected:** customer sees local notification (foreground via `flutter_local_notifications`) and system push (background).
- **Failure:** no delivery with Firebase configured.

### 9.3 — Safe without Firebase *(non‑blocking for code, blocking for store if you intend push at launch)*
- **Objective:** app still runs if Firebase config absent.
- **Steps:** build without `google-services.json` in a dev flavor.
- **Expected:** `PushService.init()` no‑ops; app launches normally.
- **Failure:** crash on startup when config missing.

---

# 10. Crashlytics Verification

> Wired in both apps via `PushService` (`FlutterError.onError` + `PlatformDispatcher.onError`). Requires Firebase config to report.

### 10.1 — Fatal & non‑fatal capture
- **Objective:** crashes/errors reach the Crashlytics console.
- **Steps:** in a staging build, force a test crash and a caught error; wait; check console.
- **Expected:** both appear with stack traces and app version.
- **Failure:** nothing in console with Firebase configured.

### 10.2 — No PII in logs
- **Objective:** crash payloads don't leak phone numbers / tokens.
- **Steps:** inspect a captured report.
- **Expected:** no customer PII / secrets.
- **Failure:** PII present.

---

# 11. Security Verification

### 11.1 — QR tamper / replay resistance
- **Objective:** signed, version‑bound QR cannot be forged or replayed.
- **Steps:** capture a `verify-qr` payload; (a) flip a byte; (b) replay an old‑version token; (c) replay after rotation.
- **Expected:** all rejected (`401`/reject).
- **Failure:** any accepted.

### 11.2 — Authorization matrix (RBAC)
- **Objective:** each role can do only what it should.
- **Steps:** as customer, staff (limited), owner, super admin, call a representative protected action of each higher tier.
- **Expected:** lower tiers `403`; `current_staff_can`/`is_super_admin` enforced.
- **Failure:** privilege escalation.

### 11.3 — IDOR / cross‑tenant (see 1.5)
- **Expected:** cannot read/write another user's or merchant's rows via API or RLS.

### 11.4 — Idempotency & double‑spend (atomic)
- **Objective:** concurrent identical money requests apply once.
- **Steps:** see §12.1.
- **Expected:** single application via `idem_claim` + `wallet_apply`.

### 11.5 — Rate limiting
- **Objective:** abuse‑prone endpoints throttle.
- **Steps:** burst each rate‑limited function past its limit.
- **Expected:** `429` after threshold; `purge_rate_limits` housekeeping works.
- **Failure:** unlimited calls.

### 11.6 — Secrets hygiene
- **Objective:** no secrets in the repo or client bundle.
- **Steps:** `mcp__github__run_secret_scanning` (or `gitleaks`); confirm anon key only in client, service‑role key only server‑side.
- **Expected:** clean scan; service‑role key never shipped to the app.
- **Failure:** any secret committed; service key in client.

### 11.7 — POS API key lifecycle
- **Objective:** POS keys are shown once, hashed at rest, revocable.
- **Steps:** create key (shown once), use it, revoke it, reuse.
- **Expected:** revoked key → `401`; key stored hashed.
- **Failure:** plaintext key at rest; revoked key still works.

---

# 12. Load & Concurrency

### 12.1 — Double‑spend race (the critical one)
- **Objective:** two simultaneous redeems of the same reward debit only once.
- **Preconditions:** customer with exactly enough points for one redemption.
- **Steps:** fire 2 concurrent `redeem-reward` with the **same** `idempotency_key`; then a separate test with **different** keys racing the same balance.
- **Expected:** same key → one applied, one returns the original; different keys racing a single‑redeem balance → exactly one succeeds, the other gets insufficient‑balance. **Balance never negative; never double‑debited.**
- **Failure:** balance negative; two successful debits; deadlock/500.

### 12.2 — Concurrent earn
- **Objective:** parallel `add-points` sum correctly.
- **Steps:** 50 concurrent earns of 1 pt.
- **Expected:** final balance = sum; no lost updates (row‑level lock in `wallet_apply`).
- **Failure:** lost updates; total < expected.

### 12.3 — Throughput / latency
- **Objective:** core endpoints hold under expected launch load.
- **Steps:** load test `verify-qr`, `add-points`, `redeem-reward` at target RPS for 5 min.
- **Expected:** p95 within target; error rate ~0; no DB connection exhaustion.
- **Failure:** rising error rate; p95 blowup; pool exhaustion.

### 12.4 — Scheduled jobs under data volume
- **Objective:** cron RPCs complete on realistic data.
- **Steps:** run `expire_subscriptions`, `expire_coupons`, `grant_birthday_rewards`, `refresh_analytics`, `purge_idempotency`, `purge_rate_limits` on a seeded dataset.
- **Expected:** complete in time budget; correct rows affected; idempotent on re‑run.
- **Failure:** timeout; wrong rows; double‑grant of birthday rewards.

---

# 13. Release Build Validation

### 13.1 — Static analysis & tests
- **Steps:** `flutter analyze` in `packages/loyalty_core`, `apps/customer_app`, `apps/merchant_app`; `flutter test` in each (screenshots auto‑skip).
- **Expected:** **No issues found** ×3; all tests green.
- **Failure:** any analyzer error; failing test.

### 13.2 — Release build, both apps
- **Steps:** `flutter build appbundle --release` for customer and merchant; (iOS) `flutter build ipa --release`.
- **Expected:** signed artifacts produced (keystore + `key.properties` present); no missing‑config errors.
- **Failure:** unsigned build; Gradle/Firebase plugin failure; missing env (`SUPABASE_URL`/`SUPABASE_ANON_KEY`).

### 13.3 — Flavor / env correctness
- **Objective:** release points at **prod** Supabase, not staging.
- **Steps:** inspect built `Env` values.
- **Expected:** prod URL + prod anon key; debug banners off.
- **Failure:** staging endpoints in a prod build.

### 13.4 — CI green
- **Steps:** confirm CI workflow passes with the required GitHub secrets set.
- **Expected:** build + analyze + test stages green.
- **Failure:** missing secrets; red pipeline.

---

# 14. Play Store Readiness

- [ ] **Signed AAB** with the production keystore (not debug).
- [ ] **`applicationId`** final and unique; **versionCode/versionName** bumped.
- [ ] **`google-services.json`** included; `gms` plugin enabled; push verified (§9).
- [ ] **Permissions** minimal & justified (camera for QR, notifications, location for proximity) with rationale strings.
- [ ] **Data safety form** matches reality (collects phone, location for proximity; deletion available via in‑app **delete‑account**).
- [ ] **Privacy policy URL** live and covers loyalty data, location, notifications, deletion/export.
- [ ] **Target SDK** meets current Play requirement; 64‑bit; R8/shrinking OK.
- [ ] **Store listing**: AR/EN copy, screenshots (real screens), icon, feature graphic.
- [ ] **Account deletion** discoverable (Play policy) — present in customer app.
- [ ] **Pre‑launch report** (Play Console) shows no crashes on test devices.

---

# 15. App Store Readiness

- [ ] **Signed IPA** with distribution profile; bundle id final; build/version bumped.
- [ ] **`GoogleService-Info.plist`** included; push entitlement (APNs) configured; push verified.
- [ ] **Privacy nutrition labels** match data use (phone, location, notifications).
- [ ] **`NS*UsageDescription`** strings: camera, location (when‑in‑use for proximity), notifications — clear AR/EN.
- [ ] **Account deletion** in‑app (App Store guideline 5.1.1(v)) — present.
- [ ] **ATT** not required unless tracking; confirm no cross‑app tracking SDKs.
- [ ] **Privacy policy URL** + support URL live.
- [ ] **Screenshots** per required device sizes; AR localization correct (RTL).
- [ ] **TestFlight** internal pass with no crashes (Crashlytics clean).

---

# ✅ FINAL GO / NO‑GO CHECKLIST

Publish to real customers only when **every** box is checked. 🔁 = also re‑verify on prod after the migration cutover.

### A. Database & backend
- [ ] 🔁 `schema.sql` applies cleanly on prod (0 errors), re‑runnable (§1.1)
- [ ] 37 tables, 37 RPCs, 38 indexes present (§1.2–1.3, 1.7)
- [ ] RLS enabled on all 36 user/tenant tables; cross‑tenant isolation proven (§1.4–1.5)
- [ ] `super_admins` gate enforced (§1.6)
- [ ] All 20 edge functions deployed; auth + CORS on each (§2.A) 🔁
- [ ] Every balance change goes through `wallet_apply`; idempotency via `idem_claim` everywhere money moves (§2.B, §11.4)

### B. Money safety & concurrency
- [ ] Double‑spend race applies once; balance never negative (§12.1) 🔁
- [ ] Concurrent earn sums correctly (§12.2)
- [ ] Idempotent replays return original result, no double‑apply (§2.A, §11.4)
- [ ] Rate limits enforced on abuse‑prone functions (§11.5)

### C. Subscription & trial
- [ ] Active sub allows ops; expired sub blocks ops **server‑side** (§6) 🔁
- [ ] Trial active works; trial expiry gates access; no trial stacking (§7)

### D. Realtime, push, crashlytics
- [ ] Wallet, notifications (+unread badge), store detail update live; reconnect recovers (§8)
- [ ] FCM token registered in `device_tokens`; push delivered both apps (§9) — *requires Firebase config*
- [ ] Crashlytics captures fatal+non‑fatal, no PII (§10) — *requires Firebase config*

### E. Security
- [ ] QR tamper/replay/version rejected (§11.1) 🔁
- [ ] RBAC matrix enforced; no privilege escalation; no IDOR (§11.2–11.3)
- [ ] Secret scan clean; service‑role key never in client (§11.6)
- [ ] POS keys hashed, shown once, revocable (§11.7)

### F. Apps & E2E
- [ ] Customer E2E pass incl. empty/error states, GDPR export+delete (§3)
- [ ] Merchant E2E pass incl. suspended gate, quotas, RBAC (§4)
- [ ] Cross‑app loop, referral once, POS parity (§5)

### G. Release artifacts
- [ ] `flutter analyze` clean ×3; all tests green (§13.1)
- [ ] Signed release builds, prod env, CI green (§13.2–13.4)
- [ ] Play Store checklist complete (§14)
- [ ] App Store checklist complete (§15)

### H. Operational pre‑reqs (the current known blockers)
- [ ] 🔁 Migration applied to **prod** Supabase
- [ ] Edge functions smoke‑tested on **staging** (Deno runtime) and deployed to prod
- [ ] **Firebase config** (`google-services.json` / `GoogleService-Info.plist`) + `gms` plugin added → push & Crashlytics live
- [ ] **Android keystore** + `key.properties` present (signing) ; **GitHub secrets** `SUPABASE_URL` / `SUPABASE_ANON_KEY` set
- [ ] Scheduled jobs (cron) installed for `expire_subscriptions`, `expire_coupons`, `grant_birthday_rewards`, `refresh_analytics`, `purge_idempotency`, `purge_rate_limits`

---

## Decision rule
**GO** only when sections A–H are fully checked on the **prod** target (with staging having passed §1–§12 first).
**NO‑GO** if any box in A–E (correctness/money/security) or H (operational pre‑reqs) is unchecked — these are hard blockers. Unchecked items in F/G must be resolved or explicitly risk‑accepted by the release owner with a documented reason.

> Code state at runbook creation: all code‑level defects remediated; analyzers clean; tests green. The remaining blockers are operational (apply migration, staging smoke‑test of edge functions, Firebase config, signing keystore, CI secrets, cron install) — this runbook is the procedure to close them.
