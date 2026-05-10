# HalaOps — Gamification Mechanics & KPI Engine (Reference)

> Companion to `00-MASTER-BLUEPRINT.md`. Concrete formulas, anti-cheat rules,
> and event mappings ready for implementation.

## 1. Event Catalog (source of truth)
كل XP / KPI يأتي من event واحد على الأقل من هذه القائمة:

| Event Type | المصدر | حقول metadata | يؤثر على |
|-----------|--------|--------------|---------|
| `task.created` | system | `priority, project_id` | activity |
| `task.started` | user click + first time-log | `task_id` | engagement |
| `task.completed` | state transition + checks | `quality_signals[]` | XP, performance |
| `task.reopened` | reviewer action | `previous_assignee` | XP penalty |
| `task.sla_hit` | scheduler | `delta_minutes` | reliability |
| `task.sla_missed` | scheduler | `delta_minutes` | reliability ↓ |
| `comment.posted` | user | `mentions, length` | leadership |
| `review.peer.submitted` | user | `score` | leadership/quality |
| `review.client.received` | webhook/portal | `csat,nps` | quality |
| `deal.advanced` | state transition | `stage_from→to` | performance |
| `deal.won` | state transition | `amount` | performance |
| `placement.confirmed` | employer confirm | `probation_pass` | performance |
| `focus.session.completed` | client app | `minutes` | reliability |
| `attendance.checked_in` | system | `delta_to_schedule` | reliability |
| `training.module.passed` | LMS | `score` | growth |
| `automation.published` | user | `complexity` | leadership |
| `mention.appreciated` | reaction | `from_user_id` | leadership |
| `risk.alert.opened` | AI | `kind,score` | (HR view only) |

## 2. XP Base Values
```ts
const BASE_XP = {
  'task.completed': 20,
  'task.sla_hit': 10,
  'review.peer.submitted': 5,
  'review.client.received_high': 30,    // csat ≥ 4.5
  'deal.won_small': 50,
  'deal.won_large': 150,
  'placement.confirmed': 200,
  'training.module.passed': 25,
  'automation.published': 60,
  'mention.appreciated': 3,
  'focus.session.completed_25min': 4,
};
const PRIORITY_W = { 0: 1.6, 1: 1.3, 2: 1.0, 3: 0.7 };
```

## 3. XP Computation
```ts
function computeXp(event: Event, ctx: UserCtx): number {
  if (ctx.isSpammyDuplicate(event)) return 0;          // anti-farm
  if (ctx.dailyXpExceeded()) return 0;                  // daily cap
  if (!ctx.hasCorroboratingSignal(event)) return 0;     // truth-only

  const base = BASE_XP[event.type] ?? 0;
  const quality = clamp(event.quality ?? 1.0, 0.5, 1.5);
  const difficulty = Math.log1p(event.estimatedMinutes ?? 30)
                   * (PRIORITY_W[event.priority] ?? 1);
  const novelty = 1 / (1 + ctx.repeatsInWindow(event.type, 24*60));
  const xp = Math.round(base * quality * difficulty * novelty);

  return ctx.applyAfterHoursDamper(xp);  // discourage burnout
}
```

### Daily Cap
```
dailyCap(user) = max(80, 1.5 × movingAvg7d(user))
```

### After-Hours Damper
- بعد 9pm محلي → ×0.5 على XP.
- > 10 ساعات نشاط في اليوم → ×0.3 + open burnout flag.

## 4. Levels Curve
```
xpRequired(level) = round(50 * level^1.6)
// level 1 →  50, level 5 → 663, level 10 → 1995, level 25 → 9192
```
Perks unlock at: 5 (theme), 10 (custom avatar frame), 15 (early access flag),
20 (mentor badge), 30 (legend wall).

## 5. Streak Rules
- Daily streak: ينمو لو حقّق ≥ 1 task completion + ≥ 1 corroborated event.
- يُكسر إذا غاب يومًا كاملًا.
- **Freeze tokens**: يربح 1 كل 14 يومًا (max 3) — يحمي من الكسر يوم واحد.
- يوم الإجازة المعتمدة لا يكسر الـ streak.

## 6. Missions (templates)
```yaml
- key: "daily_3_tasks"
  kind: daily
  rule: { event: task.completed, count_gte: 3, quality_gte: 0.8 }
  reward: { xp: 30, coins: 10 }

- key: "weekly_no_sla_miss"
  kind: weekly
  rule: { aggregate: sla_hit_ratio, gte: 0.95 }
  reward: { xp: 120, coins: 40 }

- key: "team_battle_csat"
  kind: team
  metric: avg_client_csat
  duration_days: 14
  reward: { xp_split: 300, badge: "Crowd Favorite" }
```

## 7. Badges (full set examples)
| Key | Rarity | Trigger |
|-----|--------|---------|
| `first_blood` | common | First completed task |
| `streak_7` / `streak_30` / `streak_100` | rare/epic/legendary | Streak length |
| `night_owl` | rare | 5 high-quality after-9pm tasks (with manager opt-in) |
| `the_closer` | epic | 10 deals won/month above team avg |
| `phoenix` | legendary | Recover NPS from <6 to ≥8 |
| `architect` | mythic | Authored automation adopted org-wide |
| `mentor` | epic | 20 peer reviews with helpful score ≥4 |
| `clean_sheet` | epic | A week with zero reopens |

## 8. Leaderboards & Leagues
- Period: weekly + seasonal (4 weeks).
- League ladder: Bronze → Silver → Gold → Platinum → Diamond.
- Top 20% promote, bottom 20% demote (new users protected 30 days).
- Tie-break: fewer reopens → higher CSAT → fewer SLA misses.

## 9. Coins Economy
- Coins ≠ XP. Coins are spendable; XP is identity/progress.
- Earn rate: ~1 coin per 5 XP.
- Sinks: rewards store, mission boosts, mission rerolls.
- **Inflation guard:** monthly faucet/sink ratio reviewed; cosmetic items keep ≥70% of redemptions.

## 10. KPI Formulas (Five Pillars)
```
Performance  = 0.35*completion_rate + 0.25*sla_hit + 0.20*quality
             + 0.10*peer + 0.10*csat
Reliability  = 0.40*on_time + 0.25*response_time + 0.20*attendance
             + 0.15*commitments_kept
Leadership   = 0.40*peer_score + 0.30*mentions_helpful
             + 0.20*automations_authored + 0.10*mentor_reviews
Consistency  = 0.50*streak_factor + 0.30*low_variance_factor + 0.20*on_calendar
Growth       = 0.40*trend(performance) + 0.30*training_completed
             + 0.20*new_skills + 0.10*responsibility_added
```
كل factor مُعيَّر [0..1] على فترة rolling 30 days.

## 11. Truth Index (Anti-Manipulation)
```
TI = corroborated_completions / claimed_completions
corroborated:
  - client_feedback.received  (any csat for the deal)
  - peer_review.exists        (within ±3 days)
  - file_uploaded             (output artifact)
  - external_confirm          (employer/client portal)
  - meeting_attended_with_signal
```
Bands:
- TI ≥ 0.85 → trusted (XP regular)
- 0.60 ≤ TI < 0.85 → soft cap (×0.7)
- TI < 0.60 → flag to HR + suggest 1:1 + freeze leaderboard

## 12. Bottleneck & Burnout Detection
**Bottleneck Map (org-level):**
- For each task, `time_in_status_p90` per status → identify slowest stages.
- Highlight users with > 2σ time spent in `review`/`blocked`.

**Burnout Radar (per user):**
Signals (rolling 14d):
- after_hours_minutes ↑
- focus_minutes ↑ but quality ↓
- sentiment of own comments ↓ (LLM sentiment)
- streak protected by freezes (forcing it)
- attendance anomalies

Score = weighted sum, > threshold → open `risk_alerts(kind='burnout')`,
notify manager privately + suggest day off + reduce auto-assignments.

## 13. Quiet Quitting Detection
Pattern (rolling 21d):
- streak breaks ↑
- comment volume ↓ ≥ 50%
- response_time ↑ ≥ 2x baseline
- mission claims ↓
- 1:1 cancellations ↑
→ open private alert to HR; never visible to peers.

## 14. Promotion Candidate Detection
- Performance ≥ 85 for 3 consecutive months
- Leadership ≥ 75
- Growth trend ≥ +10%
- No truth flags
- 3+ legendary/epic badges OR mentor activity
→ surface in HR dashboard with evidence.

## 15. Anti-Cheat Rule Set (summary)
1. XP requires corroborating signal.
2. Daily cap on XP per user.
3. After-hours damper.
4. Reopen penalty (refund XP from previous closer).
5. Reviewer pair anomalies (graph analysis on peer reviews).
6. SLA edits require approval after creation.
7. Audit chain (hashed) on every score-affecting record.
8. Self-review weight ≤ 5%.
9. Manager cannot directly add XP — only via approved rewards.
10. AI insights tagged with evidence; insights without evidence are blocked.

## 16. Implementation Notes
- All XP/Score writes happen in **`worker/xp-engine`** consuming `events` from Redis Streams.
- Idempotent by `(event_id, rule_version)`.
- Rule versions stored — recomputation possible for the last 90 days.
- Leaderboards are materialized snapshots (not live aggregates) to prevent gaming via timing.
