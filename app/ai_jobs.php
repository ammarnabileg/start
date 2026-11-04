<?php
/**
 * شغل الذكاء الاصطناعي في الخلفية: تصنيف المحادثات الجديدة، والتنبيهات الذكية اليومية.
 * بيشتغل من cron، أو بزرار "صنّف الآن". كله تحت حد الاستهلاك الشهري.
 */

/** تشغيل كود باسم شركة معينة (للـ cron) */
function ai_as_workspace(int $wid, callable $fn)
{
    $w = db()->prepare("SELECT * FROM workspaces WHERE id = ?");
    $w->execute([$wid]);
    $row = $w->fetch();
    if (!$row) return null;
    $row['my_role'] = 'owner';
    $prev = $GLOBALS['__ws_override'] ?? null;
    $GLOBALS['__ws_override'] = $row;
    try { return $fn(); } finally { if ($prev) $GLOBALS['__ws_override'] = $prev; else unset($GLOBALS['__ws_override']); }
}

function ai_auto_enabled(int $wid, string $what): bool
{
    return setting('ai_auto_' . $what . '_' . $wid, '0') === '1';
}

/** تصنيفات المحادثات المحفوظة: [conv_id => row] */
function ai_labels(int $wid): array
{
    $st = db()->prepare("SELECT * FROM ai_labels WHERE workspace_id = ?");
    $st->execute([$wid]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[$r['item_key']] = $r;
    return $out;
}

/**
 * بيصنف المحادثات اللي آخر رسالة فيها اتغيرت من آخر مرة. طلب واحد لحد 20 محادثة عشان التكلفة.
 * بيرجع عدد اللي اتصنف.
 */
function ai_classify_conversations(int $wid, int $max = 20): int
{
    if (!ai_connection()) return 0;
    $z = zernio_or_null();
    if (!$z) return 0;
    $conv = $z->conversations(['limit' => 40, 'profileId' => zernio_profile()])['data'] ?? [];
    $known = ai_labels($wid);
    $todo = [];
    foreach ($conv as $c) {
        $m = $c['lastMessage'] ?? '';
        $m = trim(is_array($m) ? (string)($m['text'] ?? $m['message'] ?? '') : (string)$m);
        $id = (string)($c['id'] ?? '');
        if ($id === '' || $m === '') continue;
        $h = md5($m . '|' . ($c['lastMessageAt'] ?? $c['updatedTime'] ?? ''));
        if (($known[$id]['src_hash'] ?? '') === $h) continue;
        $todo[] = ['id' => $id, 'hash' => $h, 'platform' => (string)($c['platform'] ?? ''), 'name' => (string)($c['participantName'] ?? ''), 'text' => mb_substr($m, 0, 400)];
        if (count($todo) >= $max) break;
    }
    if (!$todo) return 0;
    $lines = [];
    foreach ($todo as $i => $t) $lines[] = ($i + 1) . '. [' . $t['platform'] . '] ' . str_replace("\n", ' ', $t['text']);
    $sys = 'Classify each customer message. Return ONLY a JSON array, one object per message in the same order: '
        . '{"n":number,"category":"inquiry|complaint|purchase|support|spam|other","sentiment":"positive|neutral|upset|angry","urgency":1-3,"intent":true|false,"summary":"max 8 words in the message language"}. '
        . 'urgency 3 = angry, complaint about an order, or time-sensitive. intent = wants to buy or asks price/availability.';
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => implode("\n", $lines)]], 1200, 'classify');
    $json = $r['text'];
    if (preg_match('/\[.*\]/s', $json, $m)) $json = $m[0];
    $arr = json_decode($json, true);
    if (!is_array($arr)) return 0;
    $cats = ['inquiry', 'complaint', 'purchase', 'support', 'spam', 'other'];
    $sents = ['positive', 'neutral', 'upset', 'angry'];
    $st = db()->prepare("INSERT INTO ai_labels (workspace_id, item_key, category, sentiment, urgency, intent, summary, src_hash, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE category = VALUES(category), sentiment = VALUES(sentiment), urgency = VALUES(urgency), intent = VALUES(intent), summary = VALUES(summary), src_hash = VALUES(src_hash), updated_at = NOW()");
    $n = 0;
    foreach ($arr as $k => $o) {
        $idx = (int)($o['n'] ?? ($k + 1)) - 1;
        if (!isset($todo[$idx]) || !is_array($o)) continue;
        $t = $todo[$idx];
        $st->execute([$wid, $t['id'], in_array($o['category'] ?? '', $cats, true) ? $o['category'] : 'other', in_array($o['sentiment'] ?? '', $sents, true) ? $o['sentiment'] : 'neutral',
            max(1, min(3, (int)($o['urgency'] ?? 1))), !empty($o['intent']) ? 1 : 0, mb_substr((string)($o['summary'] ?? ''), 0, 250), $t['hash']]);
        $n++;
    }
    return $n;
}

/** تنبيهات ذكية: مرة في اليوم، بيدور على أي حاجة غير طبيعية في الأرقام ويحطها في التنبيهات */
function ai_smart_alerts(int $wid): int
{
    $key = 'ai_alerts_day_' . $wid;
    if (setting($key, '') === gmdate('Y-m-d') || !ai_connection()) return 0;
    save_setting($key, gmdate('Y-m-d'));
    $sys = 'You monitor social media metrics for a business. From the DATA, find real anomalies only (drops or spikes over ~30% vs the recent average, accounts needing reconnection, many unread messages). '
        . 'Return ONLY JSON: {"alerts":[{"title":"short title","detail":"one sentence with numbers","level":"warning|info"}]} — empty array if nothing unusual. Write in ' . (setting('default_lang', 'en') === 'ar' ? 'Arabic' : 'English') . '. Never invent numbers.';
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => "DATA:\n" . assistant_context(true)]], 700, 'analyze');
    $json = $r['text'];
    if (preg_match('/\{.*\}/s', $json, $m)) $json = $m[0];
    $alerts = json_decode($json, true)['alerts'] ?? [];
    $n = 0;
    $st = db()->prepare("INSERT IGNORE INTO alerts (workspace_id, rule, fingerprint, data) VALUES (?, 'ai_anomaly', ?, ?)");
    foreach (array_slice((array)$alerts, 0, 5) as $a) {
        if (!is_array($a) || trim((string)($a['title'] ?? '')) === '') continue;
        $st->execute([$wid, 'ai:' . gmdate('Y-m-d') . ':' . md5((string)$a['title']), json_encode(['title' => mb_substr((string)$a['title'], 0, 120), 'detail' => mb_substr((string)($a['detail'] ?? ''), 0, 400), 'level' => ($a['level'] ?? '') === 'info' ? 'info' : 'warning'], JSON_UNESCAPED_UNICODE)]);
        $n += $st->rowCount();
    }
    return $n;
}

/** ملخص المنصة لمدير النظام (الشركات، المستخدمين، الاستهلاك، النشاط) */
function ai_admin_context(): string
{
    $pdo = db();
    $month = gmdate('Y-m-01 00:00:00');
    $ws = $pdo->query("SELECT w.id, w.name, COALESCE(w.status,'active') status, DATE(w.created_at) created,
            (SELECT COUNT(*) FROM workspace_users u WHERE u.workspace_id = w.id AND u.status = 'active') members,
            (SELECT ROUND(COALESCE(SUM(cost),0),4) FROM ai_usage a WHERE a.workspace_id = w.id AND a.created_at >= '$month') ai_cost_month,
            (SELECT COUNT(*) FROM ai_usage a WHERE a.workspace_id = w.id AND a.created_at >= '$month') ai_calls_month,
            (SELECT MAX(created_at) FROM activity_log l WHERE l.workspace_id = w.id) last_activity,
            (SELECT GROUP_CONCAT(DISTINCT provider) FROM connectors c WHERE c.workspace_id = w.id) integrations
        FROM workspaces w ORDER BY w.id LIMIT 200")->fetchAll();
    $users = $pdo->query("SELECT name, email, role, COALESCE(status,'active') status, DATE(created_at) created, DATE(last_login_at) last_login FROM users ORDER BY last_login_at IS NULL, last_login_at DESC LIMIT 300")->fetchAll();
    return json_encode(['today' => gmdate('Y-m-d'), 'workspaces' => $ws, 'users' => $users], JSON_UNESCAPED_UNICODE);
}
