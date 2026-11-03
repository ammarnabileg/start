<?php
/**
 * تحليلات الفريق وخدمة العملا (جزء من صفحة تحليلات الرسائل)
 */

/** ردود الأعضاء من سجل النشاط في الفترة: [rows => [[user_id, name, convs, replies]], recent => [[conv_id, user_id, ts]]] */
function ia2_member_replies(int $days): array
{
    $w = current_workspace();
    if (!$w) return ['rows' => [], 'recent' => []];
    $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);
    $st = db()->prepare("SELECT a.user_id, u.name, COUNT(DISTINCT a.details) AS convs, COUNT(*) AS replies
        FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
        WHERE a.workspace_id = ? AND a.action = 'inbox.reply' AND a.created_at >= ? AND a.user_id IS NOT NULL
        GROUP BY a.user_id, u.name ORDER BY convs DESC, replies DESC");
    $st->execute([$w['id'], $since]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    // كل رد بتوقيته (آخر 500) عشان نقارن بعينة المحادثات
    $st = db()->prepare("SELECT user_id, details AS conv_id, UNIX_TIMESTAMP(created_at) AS ts FROM activity_log
        WHERE workspace_id = ? AND action = 'inbox.reply' AND created_at >= ? AND user_id IS NOT NULL
        ORDER BY id DESC LIMIT 500");
    $st->execute([$w['id'], $since]);
    return ['rows' => $rows, 'recent' => $st->fetchAll(PDO::FETCH_ASSOC)];
}

function ia2_ts($iso): ?int
{
    if (!$iso) return null;
    $t = strtotime((string)$iso);
    return $t === false ? null : $t;
}

/**
 * زمن الرد لكل عضو من عينة: لكل رد مسجل، نشوف آخر رسالة واردة من العميل قبله في نفس المحادثة.
 * $messagesByConv = [convId => [messages...]]
 * يرجع [byUser => [uid => [sum, n, within1h]], samples => n, within1h => n, incoming => [texts]]
 */
function ia2_response_sample(array $recent, array $messagesByConv): array
{
    $byUser = []; $n = 0; $fast = 0;
    foreach ($recent as $r) {
        $msgs = $messagesByConv[$r['conv_id']] ?? null;
        if (!$msgs) continue;
        $replyTs = (int)$r['ts'];
        $lastIn = null; $prevOut = null;
        foreach ($msgs as $m) {
            $t = ia2_ts($m['createdAt'] ?? null);
            if ($t === null || $t > $replyTs + 60) continue;
            if (($m['direction'] ?? '') === 'incoming') {
                if ($lastIn === null || $t > $lastIn) $lastIn = $t;
            }
        }
        if ($lastIn === null) continue;
        // لو فيه رد طالع بين رسالة العميل والرد ده (ومش هو نفسه) يبقى الرد ده مش أول رد
        foreach ($msgs as $m) {
            $t = ia2_ts($m['createdAt'] ?? null);
            if (($m['direction'] ?? '') === 'outgoing' && $t !== null && $t > $lastIn && $t < $replyTs - 120) { $prevOut = $t; break; }
        }
        if ($prevOut !== null) continue;
        $sec = max(0, $replyTs - $lastIn);
        $uid = (int)$r['user_id'];
        $byUser[$uid] = $byUser[$uid] ?? ['sum' => 0, 'n' => 0, 'fast' => 0];
        $byUser[$uid]['sum'] += $sec;
        $byUser[$uid]['n']++;
        if ($sec <= 3600) { $byUser[$uid]['fast']++; $fast++; }
        $n++;
    }
    return ['byUser' => $byUser, 'samples' => $n, 'within1h' => $fast];
}

/** نسبة الردود خلال ساعة من الـ histogram (لو الـ buckets موجودة) */
function ia2_within_hour_from_histogram(array $hist): ?array
{
    $tot = 0; $fast = 0;
    foreach ($hist as $h) {
        $c = (int)($h['count'] ?? 0);
        $tot += $c;
        $up = $h['upperSeconds'] ?? null;
        if ($up !== null && (float)$up <= 3600) $fast += $c;
    }
    return $tot > 0 ? ['total' => $tot, 'fast' => $fast, 'pct' => $fast / $tot * 100] : null;
}

function ia2_stopwords(): array
{
    static $s = null;
    if ($s !== null) return $s;
    $ar = 'في من على الى إلى عن مع هو هي انا أنا انت إنت انتي احنا إحنا هم ده دي دا اللي الذي التي ان أن إن كان كانت يكون مش مو لا لأ ما لم لن و او أو ثم بس كده كدا ايه إيه ازاي إزاي فين امتى ليه عشان علشان لو بعد قبل كل ممكن عايز عاوز عايزة محتاج يا ياريت هل هذا هذه ذلك تلك هنا هناك بتاع بتاعت بتاعي عند عندي عندك لي ليا ليك له لها لكم لنا منه منها فيه فيها عليه عليها اي أي اية حاجة حد طيب تمام اه أه ايوه آه شكرا شكراً السلام عليكم وعليكم مساء صباح الخير النور اهلا أهلا مرحبا والله لسه برضه برده كمان جدا جداً اوي قوي يعني';
    $en = 'a an the and or but if of to in on at for from with by is are was were be been am i you he she it we they me my your our their this that these those there here what which who how why when where do does did not no yes so can could would should will just please hi hello hey thanks thank ok okay im its dont ive about as have has had get got want need any some all more very also than then too';
    $s = array_flip(array_merge(preg_split('/\s+/u', $ar), preg_split('/\s+/u', $en)));
    return $s;
}

/** تطبيع عربي بسيط: يشيل التشكيل والتطويل ويوحد الألف والياء والتاء المربوطة */
function ia2_norm(string $w): string
{
    $w = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $w);
    $w = strtr($w, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);
    return mb_strtolower($w, 'UTF-8');
}

/** أكتر الكلمات وعبارات من كلمتين تكرارًا. يرجع [[word, count], ...] */
function ia2_top_words(array $texts, int $limit = 20): array
{
    $stop = [];
    foreach (ia2_stopwords() as $k => $_) $stop[ia2_norm($k)] = true;
    $words = []; $bi = [];
    foreach ($texts as $txt) {
        $txt = preg_replace('~https?://\S+~u', ' ', (string)$txt);
        $toks = preg_split('/[^\p{Arabic}\p{L}\p{N}]+/u', $txt, -1, PREG_SPLIT_NO_EMPTY);
        $prev = null;
        foreach ($toks as $t) {
            $t = ia2_norm($t);
            if (preg_match('/^\p{N}+$/u', $t)) { $prev = null; continue; }
            // "ال" التعريف: نشيلها لو الكلمة طويلة كفاية
            $base = (mb_strlen($t) > 4 && mb_substr($t, 0, 2) === 'ال') ? mb_substr($t, 2) : $t;
            if (mb_strlen($base) < 2 || isset($stop[$t]) || isset($stop[$base])) { $prev = null; continue; }
            $words[$base] = ($words[$base] ?? 0) + 1;
            if ($prev !== null) { $k = $prev . ' ' . $base; $bi[$k] = ($bi[$k] ?? 0) + 1; }
            $prev = $base;
        }
    }
    $all = $words;
    foreach ($bi as $k => $c) if ($c >= 2) $all[$k] = $c;
    arsort($all);
    $out = [];
    foreach (array_slice($all, 0, $limit, true) as $w => $c) $out[] = [(string)$w, (int)$c];
    return $out;
}

/** المحادثات اللي جت من إعلانات */
function ia2_ad_conversations(array $convs, int $fromTs): array
{
    $out = [];
    foreach ($convs as $c) {
        $ad = $c['adAttribution'] ?? null;
        $meta = $c['metadata'] ?? null;
        $isAd = is_array($ad) || (is_array($meta) && (isset($meta['meta_ad_id']) || isset($meta['ctwa_source_id']) || isset($meta['ctwa_clid']) || isset($meta['meta_ad_ref'])));
        if (!$isAd) continue;
        $t = ia2_ts($c['updatedTime'] ?? null);
        if ($t !== null && $t < $fromTs) continue;
        $name = is_array($ad) ? ($ad['name'] ?? null) : null;
        if (!$name && is_array($meta)) $name = $meta['ctwa_headline'] ?? $meta['meta_ad_title'] ?? null;
        $out[] = ['conv' => $c, 'adName' => $name, 'campaign' => is_array($ad) ? ($ad['campaignName'] ?? null) : null];
    }
    return $out;
}
