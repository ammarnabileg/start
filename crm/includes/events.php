<?php
/**
 * Event + XP + Badges + Streaks engine.
 *
 * Call event_fire() from anywhere to record an action.
 * The engine awards XP, updates streaks/levels, and unlocks badges.
 */

require_once __DIR__ . '/db.php';

function xp_base_values(): array {
    return [
        'task.completed'      => 15,
        'task.completed_high' => 25,
        'deal.created'        => 5,
        'deal.advanced'       => 10,
        'deal.won'            => 100,
        'client.created'      => 10,
        'candidate.created'   => 8,
        'candidate.advanced'  => 15,
        'placement.placed'    => 200,
        'vacancy.opened'      => 12,
        'vacancy.closed'      => 30,
        'review.peer'         => 5,
        'login'               => 1,
    ];
}

function xp_for(string $type, array $metadata = []): int {
    $base = xp_base_values()[$type] ?? 0;
    if ($type === 'task.completed' && (($metadata['priority'] ?? 'medium') === 'high' || ($metadata['priority'] ?? '') === 'urgent')) {
        $base = xp_base_values()['task.completed_high'];
    }
    return (int)$base;
}

function xp_required_for_level(int $level): int {
    return (int) round(50 * pow($level, 1.6));
}

function level_for_xp(int $xp): int {
    $level = 1;
    while (xp_required_for_level($level + 1) <= $xp && $level < 100) $level++;
    return $level;
}

/**
 * Fire a domain event. Records it, computes XP, awards badges, updates streaks.
 * Returns the inserted event id.
 */
function event_fire(string $type, ?string $subjectType = null, ?int $subjectId = null, array $metadata = [], ?int $userId = null): ?int {
    $userId = $userId ?? (function_exists('auth_id') ? auth_id() : null);
    if (!$userId) return null;

    $xp = xp_for($type, $metadata);
    if (xp_daily_cap_exceeded($userId)) $xp = 0; // anti-farming

    try {
        $eventId = db_insert(tbl('events'), [
            'user_id'     => $userId,
            'type'        => $type,
            'subject_type'=> $subjectType,
            'subject_id'  => $subjectId,
            'metadata'    => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'xp_awarded'  => $xp,
        ]);
    } catch (Throwable $e) {
        return null;
    }

    if ($xp > 0) xp_grant($userId, $xp, 'event', (int)$eventId, $type);
    streak_touch($userId);
    badges_check($userId, $type, $metadata);

    return (int)$eventId;
}

function xp_daily_cap_exceeded(int $userId): bool {
    $today = (int)db_scalar(
        "SELECT COALESCE(SUM(delta),0) FROM " . tbl('xp_ledger') . "
         WHERE user_id = :u AND `at` >= CURDATE() AND delta > 0",
        ['u' => $userId]
    );
    return $today >= 500; // hard cap per day
}

function xp_grant(int $userId, int $delta, string $sourceType, ?int $sourceId, ?string $reason = null): void {
    db_insert(tbl('xp_ledger'), [
        'user_id'    => $userId,
        'delta'      => $delta,
        'source_type'=> $sourceType,
        'source_id'  => $sourceId,
        'reason'     => $reason,
    ]);

    user_stats_ensure($userId);
    $newTotal = (int)db_scalar('SELECT COALESCE(SUM(delta),0) FROM ' . tbl('xp_ledger') . ' WHERE user_id = :u', ['u' => $userId]);
    $oldLevel = (int)db_scalar('SELECT level FROM ' . tbl('user_stats') . ' WHERE user_id = :u', ['u' => $userId]);
    $newLevel = level_for_xp($newTotal);

    db_update(tbl('user_stats'),
        ['total_xp' => $newTotal, 'level' => $newLevel],
        'user_id = :u', ['u' => $userId]
    );

    if ($newLevel > $oldLevel) {
        notify($userId, 'level_up', "🎉 وصلت للمستوى $newLevel!", "تستحق التهنئة على هذا التقدم.", null, '🎉');
    }
}

function user_stats_ensure(int $userId): void {
    $exists = db_one('SELECT user_id FROM ' . tbl('user_stats') . ' WHERE user_id = :u', ['u' => $userId]);
    if (!$exists) {
        db_insert(tbl('user_stats'), ['user_id' => $userId]);
    }
}

function streak_touch(int $userId): void {
    user_stats_ensure($userId);
    $stats = db_one('SELECT * FROM ' . tbl('user_stats') . ' WHERE user_id = :u', ['u' => $userId]);
    if (!$stats) return;
    $today = date('Y-m-d');
    $last  = $stats['last_activity_date'];
    if ($last === $today) return;

    $current = (int)$stats['current_streak'];
    if ($last && (strtotime($today) - strtotime($last)) === 86400) {
        $current += 1;
    } else {
        $current = 1;
    }
    $longest = max((int)$stats['longest_streak'], $current);
    db_update(tbl('user_stats'),
        ['current_streak' => $current, 'longest_streak' => $longest, 'last_activity_date' => $today],
        'user_id = :u', ['u' => $userId]
    );
}

function badges_check(int $userId, string $eventType, array $metadata = []): void {
    $badges = db_all('SELECT * FROM ' . tbl('badges'));
    foreach ($badges as $b) {
        $criteria = json_decode($b['criteria'], true) ?: [];
        $owned = db_one('SELECT 1 FROM ' . tbl('user_badges') . ' WHERE user_id = :u AND badge_id = :b', ['u' => $userId, 'b' => $b['id']]);
        if ($owned) continue;
        if (badge_criteria_met($userId, $criteria, $eventType)) {
            badge_award($userId, (int)$b['id'], (int)$b['xp_reward'], $b['name'], $b['icon']);
        }
    }
}

function badge_criteria_met(int $userId, array $criteria, string $currentEvent): bool {
    if (!empty($criteria['event'])) {
        if ($criteria['event'] !== $currentEvent && empty($criteria['count'])) return false;
        $needed = (int)($criteria['count'] ?? 1);
        $have = (int)db_scalar(
            'SELECT COUNT(*) FROM ' . tbl('events') . ' WHERE user_id = :u AND type = :t',
            ['u' => $userId, 't' => $criteria['event']]
        );
        if ($have < $needed) return false;
    }
    if (!empty($criteria['streak_gte'])) {
        $cur = (int)db_scalar('SELECT current_streak FROM ' . tbl('user_stats') . ' WHERE user_id = :u', ['u' => $userId]);
        if ($cur < $criteria['streak_gte']) return false;
    }
    return true;
}

function badge_award(int $userId, int $badgeId, int $xpReward, string $name, string $icon): void {
    try {
        db_insert(tbl('user_badges'), ['user_id' => $userId, 'badge_id' => $badgeId]);
        if ($xpReward > 0) xp_grant($userId, $xpReward, 'badge', $badgeId, "Badge: $name");
        notify($userId, 'badge', "$icon فتحت شارة: $name", "حصلت على $xpReward XP إضافية", null, $icon);
    } catch (Throwable $e) { /* duplicate or error */ }
}

/**
 * Send a notification (used by the engine and modules).
 */
function notify(int $userId, string $kind, string $title, ?string $body = null, ?string $link = null, ?string $icon = null): void {
    try {
        db_insert(tbl('notifications'), [
            'user_id' => $userId,
            'kind'    => $kind,
            'title'   => $title,
            'body'    => $body,
            'link'    => $link,
            'icon'    => $icon ?? '🔔',
        ]);
    } catch (Throwable $e) { /* swallow */ }
}

/**
 * Compute performance scores from raw data (rolling 30 days).
 */
function compute_performance(int $userId): array {
    $params = ['u' => $userId];

    $tasks30        = (int)db_scalar('SELECT COUNT(*) FROM ' . tbl('tasks') . ' WHERE assignee_id = :u AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', $params);
    $done30         = (int)db_scalar('SELECT COUNT(*) FROM ' . tbl('tasks') . ' WHERE assignee_id = :u AND status = "done" AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', $params);
    $onTime30       = (int)db_scalar('SELECT COUNT(*) FROM ' . tbl('tasks') . ' WHERE assignee_id = :u AND status = "done" AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND (due_at IS NULL OR completed_at <= due_at)', $params);
    $overdueOpen    = (int)db_scalar('SELECT COUNT(*) FROM ' . tbl('tasks') . ' WHERE assignee_id = :u AND status IN ("open","in_progress") AND due_at < NOW()', $params);
    $dealsWon30     = (int)db_scalar('SELECT COUNT(*) FROM ' . tbl('deals') . ' WHERE owner_id = :u AND stage = "won" AND actual_close_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)', $params);
    $placements30   = (int)db_scalar('SELECT COUNT(*) FROM ' . tbl('placements') . " p JOIN " . tbl('candidates') . " c ON c.id = p.candidate_id WHERE c.owner_id = :u AND p.stage IN ('placed','probation_passed') AND p.placed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", $params);

    $completion = $tasks30 > 0 ? min(1, $done30 / max($tasks30, 1)) : 0.7;
    $onTimeRate = $done30  > 0 ? $onTime30 / $done30 : 0.7;
    $output     = min(1, ($done30 + $dealsWon30 * 5 + $placements30 * 10) / 50);
    $reliability = max(0, $onTimeRate - ($overdueOpen * 0.05));

    $performance = round(($completion * 0.40 + $output * 0.35 + $onTimeRate * 0.25) * 100, 2);
    $reliability = round(max(0, min(1, $reliability)) * 100, 2);

    user_stats_ensure($userId);
    db_update(tbl('user_stats'),
        ['performance_score' => $performance, 'reliability_score' => $reliability],
        'user_id = :u', ['u' => $userId]
    );

    return [
        'performance' => $performance,
        'reliability' => $reliability,
        'tasks_done_30d' => $done30,
        'tasks_total_30d' => $tasks30,
        'on_time_rate' => round($onTimeRate * 100, 1),
        'overdue_open' => $overdueOpen,
        'deals_won_30d' => $dealsWon30,
        'placements_30d' => $placements30,
    ];
}
