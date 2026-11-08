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
function ai_classify_conversations(int $wid, int $max = 20, ?array $onlyIds = null, bool $force = false): int
{
    if (!ai_connection()) return 0;
    $z = zernio_or_null();
    if (!$z) return 0;
    $conv = $z->conversations(['limit' => $onlyIds ? 100 : 40, 'profileId' => zernio_profile()])['data'] ?? [];
    if ($onlyIds !== null) { $onlyIds = array_map('strval', $onlyIds); $conv = array_values(array_filter($conv, function ($c) use ($onlyIds) { return in_array((string)($c['id'] ?? ''), $onlyIds, true); })); }
    $known = $force ? [] : ai_labels($wid);
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

/** نص المكالمة: من Zernio لو موجود، وإلا تفريغ التسجيل عن طريق OpenAI (بيتحفظ) */
function ai_call_transcript(Zernio $z, string $id): string
{
    $wid = (int)ws('id');
    $key = 'ai_tr_' . $wid . '_' . md5($id);
    $saved = (string)setting($key, '');
    if ($saved !== '') return $saved;
    $c = ph_call($z, $id);
    $lines = [];
    foreach ((array)($c['transcript'] ?? []) as $seg) {
        $sp = $seg['speaker'] ?? $seg['role'] ?? $seg['track'] ?? '';
        $tx = trim((string)($seg['text'] ?? ''));
        if ($tx !== '') $lines[] = ($sp !== '' ? $sp . ': ' : '') . $tx;
    }
    $text = implode("\n", $lines);
    if ($text === '') {
        $oa = connector('openai');
        if (!$oa || !empty($oa['settings']['disabled'])) throw new AiException(t('لا يوجد نص لهذه المكالمة. اربط OpenAI لتفريغ التسجيل صوتيًا.'));
        $url = ph_call_recording($z, $id);
        if ($url === '') throw new AiException(t('هذه المكالمة غير مسجلة.'));
        // safe_fetch: عناوين عامة بس وكل تحويل بيتفحص، وحد 25 ميجا
        $r = safe_fetch($url, ['file' => true, 'max' => 24 * 1048576, 'timeout' => 120, 'headers' => []]);
        $ok = empty($r['err']); $tmp = $ok ? $r['file'] : tempnam(sys_get_temp_dir(), 'call');
        if (!$ok || filesize($tmp) > 24 * 1048576) { @unlink($tmp); throw new AiException(t('تعذر تحميل التسجيل، أو حجمه أكبر من 25 ميجا.')); }
        $mime = (string)mime_content_type($tmp) ?: 'audio/mpeg';
        $ch = curl_init(ai_base('openai') . '/audio/transcriptions');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 180,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $oa['credentials']['api_key']],
            CURLOPT_POSTFIELDS => ['model' => 'gpt-4o-mini-transcribe', 'file' => new CURLFile($tmp, $mime, 'call.' . (explode('/', $mime)[1] ?? 'mp3'))]]);
        $raw = curl_exec($ch); $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); @unlink($tmp);
        $d = json_decode((string)$raw, true) ?: [];
        if ($st >= 400 || empty($d['text'])) throw new AiException(t('فشل التفريغ الصوتي: %s', (string)($d['error']['message'] ?? ('HTTP ' . $st))));
        $text = trim((string)$d['text']);
        // سعر التفريغ: gpt-4o-mini-transcribe حوالي 0.003$ للدقيقة
        $mins = max(1, (int)ceil(((int)($c['durationSeconds'] ?? 60)) / 60));
        try { db()->prepare("INSERT INTO ai_usage (workspace_id, user_id, task, provider, model, cost) VALUES (?, ?, 'calls', 'openai', 'gpt-4o-mini-transcribe', ?)")->execute([$wid, (int)(current_user()['id'] ?? 0) ?: null, $mins * 0.003]); } catch (Throwable $e) {}
    }
    save_setting($key, mb_substr($text, 0, 60000));
    return $text;
}

/** إعدادات الرد التلقائي خارج المواعيد */
function ai_reply_cfg(int $wid): array
{
    $c = json_decode((string)setting('ai_reply_cfg_' . $wid, ''), true) ?: [];
    $pl = array_values(array_filter(array_map('strval', (array)($c['platforms'] ?? []))));
    $sv = (array)($c['save'] ?? []);
    return ['save' => ['greet' => ($sv['greet'] ?? 1) ? 1 : 0, 'faq' => ($sv['faq'] ?? 1) ? 1 : 0, 'cache' => ($sv['cache'] ?? 1) ? 1 : 0, 'route' => ($sv['route'] ?? 1) ? 1 : 0, 'batch' => ($sv['batch'] ?? 1) ? 1 : 0, 'greetText' => mb_substr((string)($sv['greetText'] ?? ''), 0, 200)], 'platforms' => $pl, 'len' => in_array($c['len'] ?? '', ['short', 'medium', 'detailed'], true) ? $c['len'] : 'short', 'tone' => in_array($c['tone'] ?? '', ['friendly', 'formal', 'brand'], true) ? $c['tone'] : 'brand',
        'sig' => mb_substr((string)($c['sig'] ?? ''), 0, 80), 'when' => 'always', 'comments' => !empty($c['comments']), 'pause' => max(5, min(240, (int)($c['pause'] ?? 30))),
        'mode' => ($c['mode'] ?? '') === 'send' ? 'send' : 'draft', 'from' => preg_match('/^\d\d:\d\d$/', $c['from'] ?? '') ? $c['from'] : '09:00',
        'to' => preg_match('/^\d\d:\d\d$/', $c['to'] ?? '') ? $c['to'] : '21:00', 'days' => array_values(array_map('intval', (array)($c['days'] ?? [0, 1, 2, 3, 4, 6])))];
}

/** دلوقتي مواعيد عمل؟ (بتوقيت الشركة) */
function ai_in_hours(int $wid): bool
{
    $c = ai_reply_cfg($wid);
    $now = new DateTime('now', new DateTimeZone(site_timezone()));
    if (!in_array((int)$now->format('w'), $c['days'], true)) return false;
    $hm = $now->format('H:i');
    return $c['from'] <= $c['to'] ? ($hm >= $c['from'] && $hm < $c['to']) : ($hm >= $c['from'] || $hm < $c['to']);
}

/** الردود المقترحة المحفوظة: [conv_id => ['text' => ..., 't' => ...]] */
function ai_drafts(int $wid): array
{
    $d = json_decode((string)setting('ai_drafts_' . $wid, ''), true);
    return is_array($d) ? array_filter($d, function ($x) { return (int)($x['t'] ?? 0) > time() - 7 * 86400; }) : [];
}

/** الردود اللي الإيجنت بعتها (بصمة النص) عشان نفرّق بينها وبين رد البشر */
/* ---------- توفير الرصيد: إجابات من غير ذكاء اصطناعي ---------- */
function ai_norm_q(string $t): string
{
    $t = mb_strtolower(trim($t));
    $t = strtr($t, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي', 'ـ' => '']);
    $t = preg_replace('/[\x{064B}-\x{0652}]/u', '', $t);
    $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);
    return trim(preg_replace('/\s+/u', ' ', $t));
}
function ai_words(string $n): array
{
    static $stop = ['في', 'من', 'علي', 'على', 'عن', 'الى', 'الي', 'هل', 'ما', 'ماذا', 'كم', 'هو', 'هي', 'انا', 'انت', 'عندكم', 'لو', 'يا', 'و', 'او', 'the', 'a', 'an', 'is', 'are', 'do', 'you', 'to', 'of', 'for', 'and', 'in', 'what', 'how'];
    $w = []; foreach (explode(' ', $n) as $x) { $x = preg_replace('/^(ال|وال|بال|لل)/u', '', $x); if (mb_strlen($x) >= 2 && !in_array($x, $stop, true)) $w[$x] = 1; }
    return array_keys($w);
}
/** إحصائيات التوفير لليوم */
function ai_save_stat(int $wid, string $k): void
{
    $key = 'ai_save_' . $wid; $d = json_decode((string)setting($key, ''), true) ?: []; $day = date('Y-m-d');
    if (($d['day'] ?? '') !== $day) $d = ['day' => $day];
    $d[$k] = (int)($d[$k] ?? 0) + 1; save_setting($key, json_encode($d));
}
/** إجابة ببلاش لو ينفع: تحية/شكر/إيموجي، سؤال شائع، أو سؤال اتجاوب قبل كده. null = لازم الذكاء الاصطناعي */
function ai_cheap_answer(int $wid, array $cfg, string $msg, string $channel): ?array
{
    $sv = $cfg['save']; $n = ai_norm_q($msg); $ar = (bool)preg_match('/\p{Arabic}/u', $msg) || (!preg_match('/[a-z]/i', $msg) && lang() === 'ar');
    // إيموجي/ملصق/علامات بس: مفيش رد
    if ($n === '') return ['action' => 'reply', 'reply' => '', 'reason' => t('رسالة بلا نص'), 'src' => 'skip'];
    if ($channel === 'comment') {
        // منشن لصاحب بس (@sara @ali): الكلام مش لينا
        if (preg_match('/^(\s*@[\p{L}\p{N}._]+)+\s*$/u', trim($msg))) return ['action' => 'reply', 'reply' => '', 'reason' => t('منشن لأشخاص فقط'), 'src' => 'skip'];
        // مدح قصير من غير سؤال: شكر جاهز
        if ($sv['greet'] && !preg_match('/[?؟]/u', $msg) && preg_match('/^((ما ?شاء ?الله|تبارك الله|جميل|جميله|روعه|رائع|تحفه|حلو|حلوه|احسنتم|ابداع|مبدعين|عظيم|فخم|wow|nice|amazing|beautiful|great|love it|perfect|awesome)\s*){1,3}$/u', $n))
            return ['action' => 'reply', 'reply' => $ar ? 'شكرًا لك، كلامك يسعدنا 🤍' : 'Thank you so much! 🤍', 'reason' => t('مدح'), 'src' => 'greet'];
    }
    if ($sv['greet']) {
        $greet = '/^(السلام عليكم( ورحمه الله( وبركاته)?)?|سلام|مرحبا|مرحب|اهلا|اهلين|هلا|هاي|هلو|صباح الخير|مساء الخير|hi|hello|hey|good (morning|evening))( \S+)?$/u';
        $thanks = '/^(شكرا( جزيلا)?( لك| لكم)?|مشكور|مشكورين|تسلم|يعطيك العافيه|جزاك الله خير|ممتاز|تمام|اوكي|اوك|ok|okay|thanks|thank you|thx|👍)$/u';
        if (preg_match($greet, $n)) return ['action' => 'reply', 'reply' => $sv['greetText'] !== '' ? $sv['greetText'] : ($ar ? 'أهلًا وسهلًا بك 👋 كيف نقدر نساعدك اليوم؟' : 'Hi there 👋 How can we help you today?'), 'reason' => t('تحية'), 'src' => 'greet'];
        if (preg_match($thanks, $n)) return ['action' => 'reply', 'reply' => $channel === 'comment' ? '' : ($ar ? 'العفو، في خدمتك دائمًا 🤍' : 'You’re welcome! 🤍'), 'reason' => t('شكر'), 'src' => 'greet'];
    }
    $qw = ai_words($n);
    if ($sv['faq'] && count($qw) >= 2 && function_exists('kb_get')) {
        $best = null; $bs = 0;
        foreach ((array)(kb_get($wid)['faq']['rows'] ?? []) as $r) { $q = trim((string)($r['q'] ?? '')); $a = trim((string)($r['a'] ?? '')); if ($q === '' || $a === '') continue;
            $fw = ai_words(ai_norm_q($q)); if (!$fw) continue; $in = count(array_intersect($qw, $fw)); $sc = $in >= 2 ? $in / max(count($fw), count($qw)) : 0;
            if ($sc > $bs) { $bs = $sc; $best = $a; } }
        if ($best !== null && $bs >= 0.7) return ['action' => 'reply', 'reply' => $best, 'reason' => t('من الأسئلة الشائعة'), 'src' => 'faq'];
    }
    if ($sv['cache'] && count($qw) >= 2) {
        $c = json_decode((string)setting('ai_cache_' . $wid, ''), true) ?: []; $k = $channel . ':' . implode(' ', $qw);
        if (isset($c[$k]) && (int)$c[$k]['t'] > time() - 14 * 86400) return ['action' => 'reply', 'reply' => (string)$c[$k]['r'], 'reason' => t('سؤال متكرر'), 'src' => 'cache'];
    }
    return null;
}
/** قرار الرد: الأرخص الأول، وبعدين الذكاء الاصطناعي بسياق أقصر، وحفظ الإجابة للأسئلة المتكررة */
function ai_decide(int $wid, array $cfg, string $msg, string $channel, string $history = ''): array
{
    $c = ai_cheap_answer($wid, $cfg, $msg, $channel);
    if ($c !== null) { ai_save_stat($wid, $c['src']); return $c + ['confidence' => 1]; }
    $fast = $cfg['save']['route'] && ai_is_simple($msg);
    $d = kb_decide($wid, $msg, $channel, mb_substr($history, $fast ? -500 : -1200), ai_reply_style($cfg), $fast ? 'reply_fast' : 'reply');
    ai_save_stat($wid, $fast ? 'fast' : 'ai');
    // بنحفظ بس لو مفيش سياق قبلها: «وكام سعرها؟» إجابتها بتعتمد على المحادثة ومينفعش تتكرر لعميل تاني
    if ($cfg['save']['cache'] && trim($history) === '' && $d['action'] === 'reply' && trim((string)$d['reply']) !== '') {
        $qw = ai_words(ai_norm_q($msg));
        // بنحفظ الأسئلة العامة بس (مش لو المحادثة فيها سياق خاص)
        if (count($qw) >= 2 && count($qw) <= 12) { $cc = json_decode((string)setting('ai_cache_' . $wid, ''), true) ?: []; $cc[$channel . ':' . implode(' ', $qw)] = ['r' => trim((string)$d['reply'], " \n\"'«»"), 't' => time()]; save_setting('ai_cache_' . $wid, json_encode(array_slice($cc, -400, null, true), JSON_UNESCAPED_UNICODE)); }
    }
    return $d + ['src' => 'ai'];
}

/** سؤال قصير واضح (سعر/مواعيد/مكان...) يكفيه موديل أرخص؛ الشكوى أو الطلب المركّب يذهب للموديل الأقوى */
function ai_is_simple(string $m): bool
{
    $m = trim($m); $n = mb_strlen($m);
    if ($n === 0 || $n > 90 || substr_count($m, '?') + substr_count($m, '؟') > 1 || preg_match('/\n.*\n/u', $m)) return false;
    return !preg_match('/(شكو|مشكل|زعلان|سيء|سيئ|استرجاع|استرداد|ارجاع|إرجاع|تعويض|غلط|خطأ|متأخر|اتأخر|ما وصل|موصلش|لم يصل|refund|complain|problem|issue|broken|wrong|late|cancel|الغاء|إلغاء|لغي|عقد|مقارن|افرق|الفرق|compare)/iu', $m);
}

/** تعليمات الأسلوب للموديل */
function ai_reply_style(array $c): string
{
    $l = ['short' => 'Keep it to 1-2 short sentences.', 'medium' => 'Keep it to 2-4 sentences.', 'detailed' => 'Give a complete but focused answer (up to 6 sentences).'][$c['len']];
    $t = ['friendly' => 'Warm, friendly and simple.', 'formal' => 'Polite and formal.', 'brand' => 'Follow the TONE section of the knowledge base.'][$c['tone']];
    return $l . ' ' . $t . ' Do not add a signature.';
}
/** المنصة دي مسموح للرد الذكي يرد عليها؟ (فاضي = كل المنصات) */
function ai_platform_on(array $c, string $platform): bool
{
    return !$c['platforms'] || in_array($platform, $c['platforms'], true);
}
function ai_sign(array $c, string $text): string
{
    return $c['sig'] !== '' && $text !== '' ? $text . "\n— " . $c['sig'] : $text;
}
function ai_sent_mark(int $wid, string $text): void
{
    $l = json_decode((string)setting('ai_sent_' . $wid, ''), true) ?: []; $l[] = md5(trim($text)); save_setting('ai_sent_' . $wid, json_encode(array_slice(array_values(array_unique($l)), -500)));
}
function ai_sent_is(int $wid, string $text): bool
{
    static $c = []; if (!isset($c[$wid])) $c[$wid] = array_flip(json_decode((string)setting('ai_sent_' . $wid, ''), true) ?: []);
    return isset($c[$wid][md5(trim($text))]);
}
/** رد بشري من جوه المنصة (صندوق الرسائل/التعليقات): الإيجنت يسكت على المحادثة دي */
function ai_human_mark(int $wid, string $key): void
{
    $h = json_decode((string)setting('ai_human_' . $wid, ''), true) ?: []; $h[$key] = time();
    $h = array_filter($h, function ($t) { return $t > time() - 86400; }); save_setting('ai_human_' . $wid, json_encode($h));
}
function ai_human_recent(int $wid, string $key, int $mins): bool
{
    $h = json_decode((string)setting('ai_human_' . $wid, ''), true) ?: [];
    return (int)($h[$key] ?? 0) > time() - $mins * 60;
}
function ai_ts($v): int { if (is_numeric($v)) return (int)$v > 2000000000 ? (int)((int)$v / 1000) : (int)$v; $t = strtotime((string)$v); return $t ?: 0; }

/**
 * الإيجنت: بيرد على الرسايل والتعليقات من قاعدة المعرفة بس (kb_decide)، ولو الإجابة مش فيها بيحوّل للفريق.
 * بيشتغل خارج المواعيد أو طول الوقت حسب الإعداد. لو حد من الفريق رد بإيده (من هنا أو من التطبيق نفسه)
 * الإيجنت بيسكت على المحادثة/المنشور ده لحد ما تعدي مدة الإيقاف (افتراضي 30 دقيقة) من آخر رد بشري.
 */
function ai_auto_reply(int $wid, bool $force = false, ?array $hookMsg = null, ?array $hookCmt = null): int
{
    $cfg = ai_reply_cfg($wid);
    if (!ai_connection() || ai_kb($wid) === '') return 0;
    save_setting('ai_agent_last_' . $wid, (string)time());
    if (!function_exists('ai_agent_log_add')) require_once __DIR__ . '/views/ai_agent.php';
    $z = zernio_or_null();
    if (!$z) return 0;
    $pause = $cfg['pause'] * 60; $now = time();
    $done = json_decode((string)setting('ai_replied_' . $wid, ''), true) ?: [];
    $drafts = ai_drafts($wid);
    $n = 0;
    // ---------- الرسايل ----------
    // من الإشعار الفوري: نرد على الرسالة اللي في الإشعار نفسه (قائمة المحادثات عند Zernio بتتأخر دقايق)
    $fresh = $hookMsg !== null;
    $conv = $fresh ? [$hookMsg] : ($hookCmt !== null ? [] : ($z->conversations(['limit' => 30, 'profileId' => zernio_profile()])['data'] ?? []));
    foreach ($conv as $c) {
        if ($n >= 10) break;
        $id = (string)($c['id'] ?? ''); $acc = (string)($c['accountId'] ?? '');
        $m = $c['lastMessage'] ?? ''; $m = trim(is_array($m) ? (string)($m['text'] ?? $m['message'] ?? '') : (string)$m);
        // جديدة: مش مقروءة، أو آخر رسالة من أقل من ساعتين (حتى لو حد فتح المحادثة من غير ما يرد)
        $lt = ai_ts($c['lastMessageAt'] ?? $c['updatedTime'] ?? null);
        if ($id === '' || $acc === '' || $m === '' || ((int)($c['unreadCount'] ?? 0) < 1 && $lt < time() - 7200)) continue;
        if (!ai_platform_on($cfg, (string)($c['platform'] ?? ''))) continue;
        $h = md5($m . '|' . ($c['lastMessageAt'] ?? $c['updatedTime'] ?? ''));
        if (!$fresh && ($done[$id] ?? '') === $h) continue;
        $who = (string)($c['participantName'] ?? ''); $plat = (string)($c['platform'] ?? ''); $res = ai_agent_resumed($wid, 'c:' . $id);
        if (ai_human_recent($wid, 'c:' . $id, $cfg['pause']) && $res < time() - 5) { $hh = json_decode((string)setting('ai_human_' . $wid, ''), true) ?: []; ai_agent_pause_set($wid, 'c:' . $id, (int)($hh['c:' . $id] ?? time()) + $pause, ['name' => $who, 'platform' => $plat, 'kind' => 'msg']); continue; } // رد بشري من جوه المنصة
        try {
            // سجل المحادثة: للسياق، ولمعرفة لو حد من الفريق رد من التطبيق نفسه
            $ms = []; try { $mr = $z->messages($id, $acc); $ms = $mr['messages'] ?? ($mr['data'] ?? []); } catch (Throwable $e) {}
            $human = 0; $hist = [];
            foreach ((array)$ms as $x) { $tx = trim((string)($x['message'] ?? $x['text'] ?? '')); $out = ($x['direction'] ?? '') === 'outgoing'; $ts = ai_ts($x['createdAt'] ?? null);
                if ($out && $tx !== '' && !ai_sent_is($wid, $tx)) $human = max($human, $ts);
                if ($tx !== '') $hist[] = ($out ? 'Us: ' : 'Customer: ') . mb_substr($tx, 0, 400); }
            // آخر رسالة في المحادثة لازم تكون من العميل (لو إحنا اللي ردينا آخر حاجة، مفيش رد)
            $lastDir = ''; foreach ((array)$ms as $x) { if (trim((string)($x['message'] ?? $x['text'] ?? '')) !== '') $lastDir = (string)($x['direction'] ?? ''); }
            // قائمة الرسايل ممكن تكون متأخرة: في الإشعار الفوري الرسالة الجديدة مضمونة، فبنضيفها للسياق ومانقفش
            if ($fresh) { $hist[] = 'Customer: ' . mb_substr($m, 0, 400); $evTs = ai_ts($c['lastMessageAt'] ?? null) ?: time(); if ($human && $human > $evTs) continue; }
            elseif ($ms && $lastDir === 'outgoing') continue;
            if ($human && $human > $now - $pause && $human > $res) { ai_agent_pause_set($wid, 'c:' . $id, $human + $pause, ['name' => $who, 'platform' => $plat, 'kind' => 'msg']); continue; } // البشري رد من أقل من مدة الإيقاف
            $d = ai_decide($wid, $cfg, $m, 'message', implode("\n", array_slice($hist, -7, 6)));
            $text = ai_sign($cfg, trim($d['reply'], " \n\"'«»"));
            if ($d['action'] === 'handoff') {
                kb_handoff_email($wid, (string)($c['participantName'] ?? ''), (string)($c['platform'] ?? ''), $m, $d['reason'], base_url() . '/' . url('inbox', ['c' => $id, 'a' => $acc]));
                log_activity('ai.handoff', $id . ' ' . $d['reason']);
            }
            if ($text === '') { $done[$id] = $h; if ($d['action'] === 'handoff') ai_agent_log_add($wid, ['kind' => 'msg', 'who' => $who, 'platform' => $plat, 'in' => mb_substr($m, 0, 300), 'out' => '', 'handoff' => true, 'reason' => (string)$d['reason'], 'link' => url('inbox', ['c' => $id, 'a' => $acc])]); continue; }
            if ($cfg['mode'] === 'send') { $z->sendMessage($id, $acc, $text); ai_sent_mark($wid, $text); log_activity('ai.auto_reply', $id); }
            else $drafts[$id] = ['text' => $text, 't' => time(), 'handoff' => $d['action'] === 'handoff', 'reason' => $d['reason'], 'acc' => $acc, 'who' => $who, 'platform' => $plat, 'in' => mb_substr($m, 0, 400)];
            ai_agent_log_add($wid, ['kind' => 'msg', 'who' => $who, 'platform' => $plat, 'in' => mb_substr($m, 0, 300), 'out' => mb_substr($text, 0, 400), 'handoff' => $d['action'] === 'handoff', 'draft' => $cfg['mode'] !== 'send', 'reason' => (string)$d['reason'], 'link' => url('inbox', ['c' => $id, 'a' => $acc])]);
            $done[$id] = $h; $n++;
        } catch (Throwable $e) {
            ai_agent_log_add($wid, ['kind' => 'msg', 'who' => $who ?? '', 'platform' => $plat ?? '', 'in' => mb_substr($m, 0, 300), 'out' => '', 'reason' => 'خطأ: ' . mb_substr($e->getMessage(), 0, 160)]);
            break; // حد الاستهلاك أو مشكلة في المزود: نقف
        }
    }
    if ($hookCmt !== null) {
        $pid = (string)$hookCmt['postId']; $acc = (string)$hookCmt['accountId']; $cid = (string)$hookCmt['id']; $tx = trim((string)$hookCmt['text']); $k = 'cm:' . $cid;
        if ($cfg['comments'] && $cfg['mode'] === 'send' && $pid !== '' && $acc !== '' && $cid !== '' && $tx !== '' && !isset($done[$k]) && ai_platform_on($cfg, (string)$hookCmt['platform'])
            && !(ai_human_recent($wid, 'p:' . $pid, $cfg['pause']) && ai_agent_resumed($wid, 'p:' . $pid) < time() - 5)) {
            try {
                $d = ai_decide($wid, $cfg, $tx, 'comment'); $rt = ai_sign($cfg, trim($d['reply'], " \n\"'«»"));
                if ($d['action'] === 'handoff') { kb_handoff_email($wid, (string)$hookCmt['name'], (string)$hookCmt['platform'], $tx, $d['reason'], base_url() . '/' . url('comments', ['post' => $pid])); }
                if ($rt !== '') { $z->replyComment($pid, $acc, $rt, $cid); ai_sent_mark($wid, $rt); log_activity('ai.auto_comment', $cid); $n++; }
                ai_agent_log_add($wid, ['kind' => 'comment', 'who' => (string)$hookCmt['name'], 'platform' => (string)$hookCmt['platform'], 'in' => mb_substr($tx, 0, 300), 'out' => mb_substr($rt, 0, 400), 'handoff' => $d['action'] === 'handoff', 'reason' => (string)$d['reason'], 'link' => url('comments', ['post' => $pid])]);
                $done[$k] = 1;
            } catch (Throwable $e) { ai_agent_log_add($wid, ['kind' => 'comment', 'who' => (string)$hookCmt['name'], 'platform' => (string)$hookCmt['platform'], 'in' => mb_substr($tx, 0, 300), 'out' => '', 'reason' => 'خطأ: ' . mb_substr($e->getMessage(), 0, 160)]); }
        }
        save_setting('ai_replied_' . $wid, json_encode(array_slice($done, -600, null, true)));
        return $n;
    }
    // ---------- التعليقات (رد عام على التعليق، من قاعدة المعرفة بس) ----------
    if (!$fresh && $cfg['comments'] && $cfg['mode'] === 'send' && $n < 10) {
        $posts = []; try { $posts = $z->commentedPosts(['limit' => 8, 'profileId' => zernio_profile()])['data'] ?? []; } catch (Throwable $e) {}
        foreach ($posts as $p) {
            if ($n >= 10) break;
            $pid = (string)($p['id'] ?? ''); $acc = (string)($p['accountId'] ?? ''); if ($pid === '' || $acc === '') continue; if (!ai_platform_on($cfg, (string)($p['platform'] ?? ''))) continue;
            $resP = ai_agent_resumed($wid, 'p:' . $pid);
            if (ai_human_recent($wid, 'p:' . $pid, $cfg['pause']) && $resP < time() - 5) { ai_agent_pause_set($wid, 'p:' . $pid, time() + $pause, ['name' => t('منشور'), 'platform' => (string)($p['platform'] ?? ''), 'kind' => 'comment']); continue; }
            try { $list = $z->comments($pid, $acc); } catch (Throwable $e) { continue; }
            $all = (array)($list['comments'] ?? $list['data'] ?? []);
            // رد بشري على المنشور ده (من التطبيق نفسه) خلال مدة الإيقاف = نسكت على المنشور كله
            $human = 0; $walk = function ($l) use (&$walk, &$human, $wid) { foreach ((array)$l as $c) { $tx = trim((string)($c['message'] ?? ''));
                if (!empty($c['from']['isOwner']) && $tx !== '' && !ai_sent_is($wid, $tx)) $human = max($human, ai_ts($c['createdTime'] ?? $c['createdAt'] ?? null)); $walk($c['replies'] ?? []); } };
            $walk($all);
            if ($human && $human > $now - $pause && $human > $resP) { ai_agent_pause_set($wid, 'p:' . $pid, $human + $pause, ['name' => t('منشور'), 'platform' => (string)($p['platform'] ?? ''), 'kind' => 'comment']); continue; }
            foreach ($all as $c) {
                if ($n >= 10) break;
                $cid = (string)($c['id'] ?? ''); $tx = trim((string)($c['message'] ?? ''));
                if ($cid === '' || $tx === '' || !empty($c['from']['isOwner']) || !($c['canReply'] ?? true)) continue;
                if (array_filter((array)($c['replies'] ?? []), function ($r) { return !empty($r['from']['isOwner']); })) continue; // اترد عليه
                $k = 'cm:' . $cid; if (isset($done[$k])) continue;
                if (ai_ts($c['createdTime'] ?? $c['createdAt'] ?? null) < $now - 3 * 86400) { $done[$k] = 1; continue; } // قديم: مانردش
                try {
                    $d = ai_decide($wid, $cfg, $tx, 'comment');
                    $rt = ai_sign($cfg, trim($d['reply'], " \n\"'«»"));
                    if ($d['action'] === 'handoff') { kb_handoff_email($wid, (string)($c['from']['name'] ?? ''), (string)($p['platform'] ?? ''), $tx, $d['reason'], base_url() . '/' . url('comments', ['post' => $pid])); log_activity('ai.handoff', 'comment ' . $cid . ' ' . $d['reason']); }
                    if ($rt !== '') { $z->replyComment($pid, $acc, $rt, $cid); ai_sent_mark($wid, $rt); log_activity('ai.auto_comment', $cid); $n++; }
                    if ($rt !== '' || $d['action'] === 'handoff') ai_agent_log_add($wid, ['kind' => 'comment', 'who' => (string)($c['from']['name'] ?? ''), 'platform' => (string)($p['platform'] ?? ''), 'in' => mb_substr($tx, 0, 300), 'out' => mb_substr($rt, 0, 400), 'handoff' => $d['action'] === 'handoff', 'reason' => (string)$d['reason'], 'link' => url('comments', ['post' => $pid])]);
                    $done[$k] = 1;
                } catch (Throwable $e) { break 2; }
            }
        }
    }
    save_setting('ai_replied_' . $wid, json_encode(array_slice($done, -600, null, true)));
    save_setting('ai_drafts_' . $wid, json_encode($drafts, JSON_UNESCAPED_UNICODE));
    return $n;
}
