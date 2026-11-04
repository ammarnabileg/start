<?php
/** ملخص بيانات الشركة اللي الذكاء الاصطناعي بيرد منه (بيتحفظ 10 دقايق في الجلسة) */
function assistant_context(bool $extended = false): string
{
    $wid = (int)ws('id');
    $ck = $wid . ($extended ? 'x' : '');
    $cache = $_SESSION['ai_ctx'][$ck] ?? null;
    if ($cache && $cache['t'] > time() - 600) return $cache['v'];

    $z = zernio_or_null($err);
    $pid = zernio_profile();
    $ctx = ['company' => ws('name'), 'today' => (new DateTime('now', new DateTimeZone(site_timezone())))->format('Y-m-d l'), 'timezone' => site_timezone()];
    if ($z) {
        $g = function (callable $f) { try { return $f(); } catch (Throwable $t) { return null; } };
        $acc = $g(function () use ($z, $pid) { return $z->allAccounts($pid); }) ?: [];
        $ctx['accounts'] = array_map(function ($a) {
            return ['platform' => $a['platform'] ?? '', 'name' => trim($a['displayName'] ?? $a['username'] ?? ''), 'followers' => $a['followersCount'] ?? null,
                'needs_reconnect' => !empty($a['needsReconnection'])];
        }, $acc);
        $from = gmdate('Y-m-d', time() - 29 * 86400);
        $to = gmdate('Y-m-d');
        $daily = $g(function () use ($z, $pid, $from, $to) { return an_daily_metrics($z, ['profileId' => $pid, 'fromDate' => $from, 'toDate' => $to]); }) ?: [];
        $sum = function ($rows) {
            $t = [];
            foreach ($rows as $r) foreach ((array)($r['metrics'] ?? []) as $k => $v) if (is_numeric($v)) $t[$k] = ($t[$k] ?? 0) + $v;
            return $t;
        };
        usort($daily, function ($a, $b) { return strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? '')); });
        $ctx['last_30_days_totals'] = $sum($daily);
        $ctx['last_7_days_totals'] = $sum(array_slice($daily, -7));
        $ctx['previous_7_days_totals'] = $sum(array_slice($daily, -14, 7));
        $ctx['daily_impressions_last_14'] = array_map(function ($r) { return [$r['date'] ?? '', (int)($r['metrics']['impressions'] ?? 0)]; }, array_slice($daily, -14));
        $posts = $g(function () use ($z, $pid, $from, $to) { return an_posts($z, ['profileId' => $pid, 'fromDate' => $from, 'toDate' => $to, 'limit' => 8, 'sortBy' => 'engagement', 'order' => 'desc'])['posts'] ?? []; }) ?: [];
        $ctx['top_posts_last_30_days'] = array_map(function ($p) {
            return ['platform' => $p['platform'] ?? '', 'date' => substr((string)($p['publishedAt'] ?? ''), 0, 10), 'text' => mb_substr(trim((string)($p['content'] ?? '')), 0, 120),
                'metrics' => array_intersect_key((array)($p['analytics'] ?? $p['metrics'] ?? []), array_flip(['impressions', 'reach', 'likes', 'comments', 'shares', 'clicks', 'engagementRate']))];
        }, $posts);
        $best = $g(function () use ($z, $pid) { return an_best_time($z, ['profileId' => $pid]); }) ?: [];
        $ctx['best_times_utc'] = array_slice($best, 0, 6);
        $sched = $g(function () use ($z, $pid) { return $z->posts(['status' => 'scheduled', 'limit' => 1, 'profileId' => $pid]); });
        $ctx['scheduled_posts'] = (int)($sched['pagination']['total'] ?? 0);
        $conv = $g(function () use ($z, $pid) { return $z->conversations(['limit' => 30, 'profileId' => $pid])['data'] ?? []; }) ?: [];
        $ctx['inbox'] = ['conversations_loaded' => count($conv), 'unread_messages' => array_sum(array_map(function ($c) { return (int)($c['unreadCount'] ?? 0); }, $conv)),
            'by_platform' => array_count_values(array_map(function ($c) { return (string)($c['platform'] ?? ''); }, $conv))];
        if ($extended) {
            // بيانات زيادة للتحليلات: أضعف المنشورات، المجدول، وآخر رسائل العملاء
            $mapPost = function ($p) {
                return ['platform' => $p['platform'] ?? '', 'date' => substr((string)($p['publishedAt'] ?? ''), 0, 10), 'weekday' => !empty($p['publishedAt']) ? gmdate('l', strtotime((string)$p['publishedAt'])) : '',
                    'hour_utc' => !empty($p['publishedAt']) ? (int)gmdate('G', strtotime((string)$p['publishedAt'])) : null,
                    'media' => !empty($p['mediaItems']) ? (string)($p['mediaItems'][0]['type'] ?? 'media') : 'text', 'text' => mb_substr(trim((string)($p['content'] ?? '')), 0, 120),
                    'metrics' => array_intersect_key((array)($p['analytics'] ?? $p['metrics'] ?? []), array_flip(['impressions', 'reach', 'likes', 'comments', 'shares', 'clicks', 'engagementRate']))];
            };
            $ctx['top_posts_last_30_days'] = array_map($mapPost, $posts);
            $worst = $g(function () use ($z, $pid, $from, $to) { return an_posts($z, ['profileId' => $pid, 'fromDate' => $from, 'toDate' => $to, 'limit' => 8, 'sortBy' => 'engagement', 'order' => 'asc'])['posts'] ?? []; }) ?: [];
            $ctx['weakest_posts_last_30_days'] = array_map($mapPost, $worst);
            $up = $g(function () use ($z, $pid) { return $z->posts(['status' => 'scheduled', 'limit' => 40, 'profileId' => $pid])['posts'] ?? []; }) ?: [];
            $ctx['upcoming_scheduled_posts'] = array_map(function ($p) {
                return ['when' => (string)($p['scheduledFor'] ?? ''), 'platforms' => array_values(array_filter(array_map(function ($x) { return is_array($x) ? ($x['platform'] ?? '') : (string)$x; }, (array)($p['platforms'] ?? [])))), 'text' => mb_substr(trim((string)($p['content'] ?? '')), 0, 100)];
            }, $up);
            $ctx['recent_customer_messages'] = array_values(array_filter(array_map(function ($c) {
                $m = $c['lastMessage'] ?? '';
                $m = is_array($m) ? (string)($m['text'] ?? $m['message'] ?? '') : (string)$m;
                return $m !== '' ? ['platform' => $c['platform'] ?? '', 'text' => mb_substr($m, 0, 140)] : null;
            }, $conv)));
            $ctx['daily_metrics_last_30'] = array_map(function ($r) { return array_merge(['date' => $r['date'] ?? ''], array_intersect_key((array)($r['metrics'] ?? []), array_flip(['impressions', 'reach', 'likes', 'comments', 'shares', 'clicks', 'followers']))); }, $daily);
        }
    }
    $v = json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $_SESSION['ai_ctx'][$ck] = ['t' => time(), 'v' => $v];
    return $v;
}

