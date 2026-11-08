<?php
/**
 * شاشة الإيجنت: تشغيل/إيقاف، الإعدادات بتتحفظ لوحدها، حالته دلوقتي، المحادثات الواقف فيها (مع استئناف)،
 * آخر الردود اللي عملها، وتجربة رد من قاعدة المعرفة من غير إرسال.
 */

/** سجل نشاط الإيجنت (آخر 60) */
function ai_agent_log(int $wid): array
{
    $l = json_decode((string)setting('ai_agent_log_' . $wid, ''), true);
    return is_array($l) ? $l : [];
}
function ai_agent_log_add(int $wid, array $row): void
{
    $l = ai_agent_log($wid); array_unshift($l, $row + ['t' => time(), 'id' => substr(md5(uniqid('', true)), 0, 10)]);
    save_setting('ai_agent_log_' . $wid, json_encode(array_slice($l, 0, 60), JSON_UNESCAPED_UNICODE));
}
/** المحادثات/المنشورات اللي الإيجنت ساكت فيها عشان رد بشري */
function ai_agent_paused(int $wid): array
{
    $p = json_decode((string)setting('ai_paused_' . $wid, ''), true); $p = is_array($p) ? $p : [];
    return array_filter($p, function ($x) { return (int)($x['until'] ?? 0) > time(); });
}
function ai_agent_pause_set(int $wid, string $key, int $until, array $info): void
{
    $p = ai_agent_paused($wid); $p[$key] = $info + ['until' => $until];
    save_setting('ai_paused_' . $wid, json_encode(array_slice($p, -80, null, true), JSON_UNESCAPED_UNICODE));
}
/** استئناف: الإيجنت يتجاهل أي رد بشري قبل اللحظة دي على المحادثة دي */
function ai_agent_resumed(int $wid, string $key): int
{
    $r = json_decode((string)setting('ai_resume_' . $wid, ''), true) ?: [];
    return (int)($r[$key] ?? 0);
}

/** الرد الفوري: يسجّل إشعار Zernio (رسالة/تعليق جديد) على رابط المنصة، أو يقفله */
function ai_hook_sync(int $wid, bool $on): array
{
    $cfg = json_decode((string)setting('ai_hook_' . $wid, ''), true) ?: [];
    $z = zernio_or_null($err); if (!$z) return ['ok' => false, 'error' => $err ?: t('لا توجد حسابات تواصل مربوطة.')];
    if (empty($cfg['token'])) $cfg['token'] = bin2hex(random_bytes(16));
    if (empty($cfg['secret'])) $cfg['secret'] = bin2hex(random_bytes(24));
    $url = rtrim(base_url(), '/') . '/index.php?p=zernio_hook&w=' . $wid . '&k=' . $cfg['token'];
    $clients = $z instanceof ZernioMulti ? array_map(function ($id) use ($z) { return [$id, $z->client($id), $z->profileOf($id)]; }, $z->connectionIds()) : [[0, $z, function_exists('zernio_profile') ? zernio_profile() : null]];
    $ids = (array)($cfg['ids'] ?? []); $errs = [];
    foreach ($clients as [$cid, $c, $prof]) {
        try {
            $wh = (string)($ids[$cid] ?? '');
            if (!$on) { if ($wh !== '') { $c->request('PUT', '/v1/webhooks/settings', [], ['webhookId' => $wh, 'isActive' => false]); } continue; }
            $body = ['name' => 'Smart Reply · ' . mb_substr((string)ws('name'), 0, 30), 'url' => $url, 'secret' => $cfg['secret'], 'events' => ['message.received', 'comment.received'], 'isActive' => true] + ($prof ? ['profileIds' => [$prof]] : []);
            if ($wh !== '') { try { $c->request('PUT', '/v1/webhooks/settings', [], ['webhookId' => $wh] + $body); continue; } catch (Throwable $e) { /* اتمسح من هناك: نعمله من جديد */ } }
            $r = $c->request('POST', '/v1/webhooks/settings', [], $body);
            $nid = (string)($r['webhook']['_id'] ?? $r['webhook']['id'] ?? $r['webhook']['webhookId'] ?? '');
            if ($nid !== '') $ids[$cid] = $nid;
        } catch (Throwable $e) { $errs[] = $e->getMessage(); }
    }
    $cfg['ids'] = $ids; $cfg['on'] = $on && !$errs; $cfg['at'] = time(); $cfg['err'] = $errs ? mb_substr(implode(' · ', $errs), 0, 300) : '';
    save_setting('ai_hook_' . $wid, json_encode($cfg));
    return ['ok' => !$errs, 'error' => $cfg['err']];
}

/** طلبات الشاشة (JSON) */
function ai_agent_ajax(string $a): void
{
    $wid = (int)ws('id'); $J = function ($d, int $c = 200) { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; };
    if (!is_ws_owner() && $a !== 'agent_test' && $a !== 'agent_state') $J(['ok' => false, 'error' => t('هذا الإعداد متاح لمالك الشركة فقط.')], 403);
    if ($a === 'agent_save') {
        $c = ai_reply_cfg($wid); $in = $_POST;
        $c['mode'] = ($in['mode'] ?? $c['mode']) === 'send' ? 'send' : 'draft';
        $c['when'] = 'always';
        if (isset($in['comments'])) $c['comments'] = $in['comments'] === '1';
        if (isset($in['pause'])) $c['pause'] = max(5, min(240, (int)$in['pause']));
        if (isset($in['platforms'])) $c['platforms'] = array_values(array_intersect(array_filter(explode(',', (string)$in['platforms'])), ai_agent_platforms()));
        if (isset($in['len']) && in_array($in['len'], ['short', 'medium', 'detailed'], true)) $c['len'] = $in['len'];
        if (isset($in['tone']) && in_array($in['tone'], ['friendly', 'formal', 'brand'], true)) $c['tone'] = $in['tone'];
        if (isset($in['sig'])) $c['sig'] = mb_substr(trim((string)$in['sig']), 0, 80);
        foreach (['greet', 'faq', 'cache', 'route', 'batch'] as $sk) if (isset($in['save_' . $sk])) $c['save'][$sk] = $in['save_' . $sk] === '1' ? 1 : 0;
        if (isset($in['cap'])) $c['save']['cap'] = max(0, min(5000, (int)$in['cap']));
        if (isset($in['greetText'])) $c['save']['greetText'] = mb_substr(trim((string)$in['greetText']), 0, 200);
        if (isset($in['clearCache'])) save_setting('ai_cache_' . $wid, '');
        if (isset($in['from']) && preg_match('/^\d\d:\d\d$/', $in['from'])) $c['from'] = $in['from'];
        if (isset($in['to']) && preg_match('/^\d\d:\d\d$/', $in['to'])) $c['to'] = $in['to'];
        if (isset($in['days'])) $c['days'] = array_values(array_intersect(array_map('intval', array_filter(explode(',', (string)$in['days']), 'strlen')), range(0, 6)));
        save_setting('ai_reply_cfg_' . $wid, json_encode(['when' => $c['when'], 'comments' => $c['comments'] ? 1 : 0, 'pause' => $c['pause'], 'mode' => $c['mode'], 'from' => $c['from'], 'to' => $c['to'], 'days' => $c['days'], 'platforms' => $c['platforms'], 'len' => $c['len'], 'tone' => $c['tone'], 'sig' => $c['sig'], 'save' => $c['save']], JSON_UNESCAPED_UNICODE));
        if (isset($in['on'])) save_setting('ai_auto_reply_' . $wid, $in['on'] === '1' ? '1' : '0');
        if (isset($in['on'])) ai_hook_sync($wid, $in['on'] === '1');
        log_activity('ai.agent_cfg', json_encode($c));
        $J(['ok' => true] + ai_agent_state($wid));
    }
    if ($a === 'agent_resume') {
        $k = (string)($_POST['key'] ?? ''); $r = json_decode((string)setting('ai_resume_' . $wid, ''), true) ?: []; $r[$k] = time();
        save_setting('ai_resume_' . $wid, json_encode(array_slice($r, -200, null, true)));
        $p = ai_agent_paused($wid); unset($p[$k]); save_setting('ai_paused_' . $wid, json_encode($p, JSON_UNESCAPED_UNICODE));
        $h = json_decode((string)setting('ai_human_' . $wid, ''), true) ?: []; unset($h[$k]); save_setting('ai_human_' . $wid, json_encode($h));
        $J(['ok' => true] + ai_agent_state($wid));
    }
    if ($a === 'agent_run') {
        if (!ai_connection()) $J(['ok' => false, 'error' => t('اربط مزود ذكاء اصطناعي أولًا.')]);
        try { $n = ai_auto_reply($wid, true); } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()]); }
        $J(['ok' => true, 'n' => $n] + ai_agent_state($wid));
    }
    if ($a === 'agent_test') {
        $m = trim((string)($_POST['m'] ?? '')); if ($m === '') $J(['ok' => false, 'error' => t('اكتب رسالة للتجربة.')]);
        if (!ai_connection()) $J(['ok' => false, 'error' => t('اربط مزود ذكاء اصطناعي أولًا.')]);
        if (ai_kb($wid) === '') $J(['ok' => false, 'error' => t('قاعدة المعرفة فارغة: لن يتمكن الرد الذكي من الرد.')]);
        try { $cf = ai_reply_cfg($wid); $d = ai_decide($wid, $cf, mb_substr($m, 0, 1500), ($_POST['ch'] ?? '') === 'comment' ? 'comment' : 'message'); $d['reply'] = ai_sign($cf, trim((string)$d['reply'], " \n\"'«»")); }
        catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()]); }
        $J(['ok' => true, 'action' => $d['action'], 'reply' => trim((string)$d['reply'], " \n\"'«»"), 'reason' => (string)($d['reason'] ?? ''), 'src' => (string)($d['src'] ?? 'ai')]);
    }
    if ($a === 'agent_draft') {
        $id = (string)($_POST['id'] ?? ''); $do = (string)($_POST['do'] ?? ''); $dr = ai_drafts($wid); $x = $dr[$id] ?? null;
        if (!$x) $J(['ok' => false, 'error' => t('المسودة غير موجودة.')]);
        if ($do === 'send') {
            $text = trim((string)($_POST['text'] ?? $x['text'])); if ($text === '') $J(['ok' => false, 'error' => t('الرد فارغ.')]);
            if (empty($x['acc'])) $J(['ok' => false, 'error' => t('افتح المحادثة وأرسلها من هناك.')]);
            $z = zernio_or_null($err); if (!$z) $J(['ok' => false, 'error' => $err]);
            try { $z->sendMessage($id, (string)$x['acc'], $text); } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()]); }
            ai_sent_mark($wid, $text); log_activity('ai.draft_sent', $id);
            ai_agent_log_add($wid, ['kind' => 'msg', 'who' => (string)($x['who'] ?? ''), 'platform' => (string)($x['platform'] ?? ''), 'in' => (string)($x['in'] ?? ''), 'out' => $text, 'edited' => $text !== $x['text'], 'link' => url('inbox', ['c' => $id, 'a' => $x['acc']])]);
        }
        unset($dr[$id]); save_setting('ai_drafts_' . $wid, json_encode($dr, JSON_UNESCAPED_UNICODE));
        $J(['ok' => true] + ai_agent_state($wid));
    }
    if ($a === 'agent_rate') {
        $id = (string)($_POST['id'] ?? ''); $v = (string)($_POST['v'] ?? ''); $fix = mb_substr(trim((string)($_POST['fix'] ?? '')), 0, 1000);
        $l = ai_agent_log($wid); $row = null;
        foreach ($l as &$x) if (($x['id'] ?? '') === $id) { $x['rate'] = $v === 'up' ? 'up' : 'down'; if ($fix !== '') $x['fix'] = $fix; $row = $x; } unset($x);
        if (!$row) $J(['ok' => false, 'error' => t('غير موجود.')]);
        save_setting('ai_agent_log_' . $wid, json_encode($l, JSON_UNESCAPED_UNICODE));
        if ($v !== 'up' && $fix !== '' && trim((string)($row['in'] ?? '')) !== '') { $sg = ai_agent_suggest($wid); $sg[$id] = ['q' => (string)$row['in'], 'a' => $fix, 't' => time()]; save_setting('ai_kb_suggest_' . $wid, json_encode(array_slice($sg, -40, null, true), JSON_UNESCAPED_UNICODE)); }
        $J(['ok' => true] + ai_agent_state($wid));
    }
    if ($a === 'agent_report') $J(['ok' => true, 'r' => ai_agent_report($wid, (int)($_POST['days'] ?? $_GET['days'] ?? 7))]);
    if ($a === 'agent_suggest') {
        $id = (string)($_POST['id'] ?? ''); $sg = ai_agent_suggest($wid); $x = $sg[$id] ?? null; if (!$x) $J(['ok' => false, 'error' => t('غير موجود.')]);
        if (($_POST['do'] ?? '') === 'add') {
            $kb = kb_get($wid); $rows = (array)($kb['faq']['rows'] ?? []);
            $rows[] = ['q' => mb_substr(trim((string)($_POST['q'] ?? $x['q'])), 0, 300), 'a' => mb_substr(trim((string)($_POST['a'] ?? $x['a'])), 0, 1500)];
            kb_save($wid, 'faq', ['rows' => $rows] + (array)($kb['faq'] ?? [])); log_activity('kb.faq_from_agent', $id);
        }
        unset($sg[$id]); save_setting('ai_kb_suggest_' . $wid, json_encode($sg, JSON_UNESCAPED_UNICODE));
        $J(['ok' => true] + ai_agent_state($wid));
    }
    if ($a === 'agent_hook') { $r = ai_hook_sync($wid, true); $J(['ok' => $r['ok'], 'error' => $r['error']] + ai_agent_state($wid)); }
    if ($a === 'agent_state') $J(['ok' => true] + ai_agent_state($wid));
    $J(['ok' => false], 400);
}

function ai_agent_platforms(): array { return ['whatsapp', 'facebook', 'instagram', 'twitter', 'telegram', 'tiktok', 'linkedin', 'youtube', 'threads']; }
/** تقرير الرصيد: الردود ببلاش مقابل المدفوعة، التكلفة الفعلية من سجل الاستهلاك، والتوصيات */
function ai_agent_report(int $wid, int $days): array
{
    $days = in_array($days, [7, 30], true) ? $days : 7; $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $h = json_decode((string)setting('ai_save_hist_' . $wid, ''), true) ?: []; $n = [];
    foreach ($h as $d => $x) if ($d >= $from) foreach ($x as $k => $v) $n[$k] = ($n[$k] ?? 0) + (int)$v;
    $free = ($n['greet'] ?? 0) + ($n['faq'] ?? 0) + ($n['cache'] ?? 0) + ($n['skip'] ?? 0);
    $models = []; $cost = 0.0; $calls = 0; $tin = 0;
    try {
        $st = db()->prepare("SELECT task, model, COUNT(*) n, COALESCE(SUM(cost),0) c, AVG(tokens_in) ti, AVG(tokens_out) tt, SUM(cost IS NULL) unk FROM ai_usage WHERE workspace_id = ? AND task IN ('reply','reply_fast') AND created_at >= ? GROUP BY task, model ORDER BY c DESC");
        $st->execute([$wid, $from . ' 00:00:00']);
        foreach ($st->fetchAll() as $r) { $models[] = ['task' => $r['task'] === 'reply_fast' ? t('بسيطة') : t('عادية'), 'fast' => $r['task'] === 'reply_fast', 'model' => (string)$r['model'], 'n' => (int)$r['n'], 'cost' => round((float)$r['c'], 4), 'avg' => (int)$r['n'] ? round((float)$r['c'] / (int)$r['n'], 5) : 0, 'tin' => (int)$r['ti'], 'tout' => (int)$r['tt'], 'unk' => (int)$r['unk']];
            $cost += (float)$r['c']; $calls += (int)$r['n']; $tin += (int)$r['ti'] * (int)$r['n']; }
    } catch (Throwable $e) {}
    $avg = $calls ? $cost / $calls : 0;
    // نسبة المجاني بتتحسب من يوم ما العدّاد اشتغل بس (عشان متطلعش أقل من الحقيقة)
    $since = max($from, $h ? (string)array_key_first($h) : date('Y-m-d')); $callsS = $calls;
    if ($since > $from) { try { $q = db()->prepare("SELECT COUNT(*) FROM ai_usage WHERE workspace_id = ? AND task IN ('reply','reply_fast') AND created_at >= ?"); $q->execute([$wid, $since . ' 00:00:00']); $callsS = (int)$q->fetchColumn(); } catch (Throwable $e) {} }
    $tot = $free + $callsS;
    $tips = [];
    $byTask = []; foreach ($models as $m) $byTask[$m['fast'] ? 'f' : 'r'][$m['model']] = 1;
    if (!empty($byTask['f']) && !empty($byTask['r']) && !array_diff_key($byTask['f'], $byTask['r'])) $tips[] = t('الأسئلة البسيطة تعمل بنفس موديل الردود العادية، فلا يوجد توفير منها. اختر لمهمة «الردود البسيطة» موديلًا أرخص من صفحة المساعد الذكي.');
    if ($tot >= 20 && $free / $tot < 0.25) $tips[] = t('%s%% فقط من الردود كانت بدون رصيد. أضف الأسئلة المتكررة إلى الأسئلة الشائعة لتُجاب مجانًا.', round($free / $tot * 100));
    if (($sg = count(ai_agent_suggest($wid))) > 0) $tips[] = t('لديك %s اقتراح بانتظار الاعتماد. كل اقتراح تعتمده يحوّل سؤالًا متكررًا إلى إجابة مجانية.', $sg);
    if ($calls && $tin / $calls > 2500) $tips[] = t('متوسط مدخلات الرد الواحد كبير (%s توكن). راجع ملفات قاعدة المعرفة واحذف المكرر أو غير المفيد للعملاء.', number_format($tin / $calls));
    if (($n['cap'] ?? 0) > 0) $tips[] = t('تحوّلت %s رسالة للفريق بسبب الحد اليومي. ارفع الحد لو كان الفريق لا يلحق بالرد.', $n['cap']);
    if (!$tips && $tot) $tips[] = t('لا توجد مشكلة واضحة في الاستهلاك خلال هذه الفترة.');
    return ['days' => $days, 'free' => $free, 'byFree' => ['greet' => $n['greet'] ?? 0, 'faq' => $n['faq'] ?? 0, 'cache' => $n['cache'] ?? 0, 'skip' => $n['skip'] ?? 0], 'ai' => $calls, 'fast' => $n['fast'] ?? 0, 'cap' => $n['cap'] ?? 0,
        'cost' => round($cost, 4), 'avg' => round($avg, 5), 'saved' => round($free * $avg, 4), 'month' => round($cost / $days * 30, 2), 'freePct' => $tot ? round($free / $tot * 100) : 0, 'models' => $models, 'tips' => $tips, 'histStart' => $h ? array_key_first($h) : ''];
}

function ai_agent_suggest(int $wid): array { $s = json_decode((string)setting('ai_kb_suggest_' . $wid, ''), true); return is_array($s) ? $s : []; }

function ai_agent_state(int $wid): array
{
    $c = ai_reply_cfg($wid); $log = ai_agent_log($wid); $today = strtotime('today');
    $tz = new DateTimeZone(site_timezone());
    $fmt = function ($t) use ($tz) { return (new DateTime('@' . (int)$t))->setTimezone($tz)->format('d/m H:i'); };
    $issues = [];
    if (!ai_connection()) $issues[] = t('لا يوجد مزود ذكاء اصطناعي مربوط.');
    if (ai_kb($wid) === '') $issues[] = t('قاعدة المعرفة فارغة.');
    if (!ai_feature_on('auto_reply', $wid)) $issues[] = t('ميزة الرد التلقائي متوقفة من تبويب الميزات.');
    if (!zernio_or_null()) $issues[] = t('لا توجد حسابات تواصل مربوطة.');
    $on = ai_auto_enabled($wid, 'reply');
    $active = $on && !$issues;
    return ['cfg' => $c, 'on' => $on, 'active' => $active, 'inHours' => ai_in_hours($wid), 'issues' => $issues,
        'last' => ($lr = (int)setting('ai_agent_last_' . $wid, '0')) ? $fmt($lr) : '',
        'saved' => (function () use ($wid) { $d = json_decode((string)setting('ai_save_' . $wid, ''), true) ?: []; if (($d['day'] ?? '') !== date('Y-m-d')) $d = []; $f = (int)($d['greet'] ?? 0) + (int)($d['faq'] ?? 0) + (int)($d['cache'] ?? 0) + (int)($d['skip'] ?? 0); return ['free' => $f, 'ai' => (int)($d['ai'] ?? 0) + (int)($d['fast'] ?? 0), 'fast' => (int)($d['fast'] ?? 0), 'greet' => (int)($d['greet'] ?? 0), 'faq' => (int)($d['faq'] ?? 0), 'cache' => (int)($d['cache'] ?? 0), 'cached' => count(json_decode((string)setting('ai_cache_' . $wid, ''), true) ?: [])]; })(),
        'model' => (function () use ($wid) { $o = []; foreach ((function_exists('ai_picks') ? ai_picks($wid) : []) as $k => $tasks) if (in_array('reply', (array)$tasks, true)) $o[] = explode('|', $k, 2)[1] ?? $k; return $o ? implode('، ', array_slice($o, 0, 3)) : t('الافتراضي'); })(),
        'hook' => (function () use ($wid, $fmt) { $h = json_decode((string)setting('ai_hook_' . $wid, ''), true) ?: []; $l = json_decode((string)setting('ai_hook_last_' . $wid, ''), true) ?: []; return ['on' => !empty($h['on']), 'err' => (string)($h['err'] ?? ''), 'last' => !empty($l['t']) ? $fmt($l['t']) : '']; })(),
        'today' => ['replies' => count(array_filter($log, function ($x) use ($today) { return $x['t'] >= $today && in_array($x['kind'], ['msg', 'comment'], true); })),
            'handoffs' => count(array_filter($log, function ($x) use ($today) { return $x['t'] >= $today && !empty($x['handoff']); })),
            'drafts' => count(ai_drafts($wid))],
        'paused' => array_map(function ($k, $x) use ($fmt) { return ['key' => $k, 'name' => (string)($x['name'] ?? ''), 'platform' => (string)($x['platform'] ?? ''), 'kind' => (string)($x['kind'] ?? ''), 'until' => $fmt($x['until']), 'mins' => max(1, (int)ceil(($x['until'] - time()) / 60))]; }, array_keys(ai_agent_paused($wid)), ai_agent_paused($wid)),
        'log' => array_map(function ($x) use ($fmt) { return $x + ['at' => $fmt($x['t'])]; }, array_slice($log, 0, 25)),
        'drafts' => array_map(function ($k, $x) use ($fmt) { return ['id' => (string)$k, 'text' => (string)$x['text'], 'who' => (string)($x['who'] ?? ''), 'platform' => (string)($x['platform'] ?? ''), 'in' => (string)($x['in'] ?? ''), 'handoff' => !empty($x['handoff']), 'reason' => (string)($x['reason'] ?? ''), 'canSend' => !empty($x['acc']), 'at' => $fmt($x['t']), 'link' => url('inbox', ['c' => $k, 'a' => (string)($x['acc'] ?? '')])]; }, array_keys(ai_drafts($wid)), ai_drafts($wid)),
        'suggest' => array_map(function ($k, $x) { return ['id' => (string)$k, 'q' => (string)$x['q'], 'a' => (string)$x['a'], 'human' => ($x['src'] ?? '') === 'human']; }, array_keys(ai_agent_suggest($wid)), ai_agent_suggest($wid)),
        'rated' => ['up' => count(array_filter($log, function ($x) { return ($x['rate'] ?? '') === 'up'; })), 'down' => count(array_filter($log, function ($x) { return ($x['rate'] ?? '') === 'down'; }))]];
}

function ai_agent_render(): void
{
    $wid = (int)ws('id'); $owner = is_ws_owner(); $st = ai_agent_state($wid);
        ?>
    <div id="ag" class="ag">
      <section class="ag-hero card">
        <div class="ag-hd">
          <span class="ag-ic"><?= ui_icon('spark', 'w-6 h-6') ?></span>
          <div class="min-w-0 flex-1"><b class="ag-t"><?= e(t('الرد الذكي')) ?></b>
            <p class="ag-s" data-status></p></div>
          <button type="button" class="ag-sw" data-on role="switch" aria-label="<?= e(t('تشغيل الرد الذكي')) ?>" <?= $owner ? '' : 'disabled' ?>><i></i></button>
        </div>
        <ul class="ag-issues" data-issues></ul>
        <div class="ag-kpis">
          <div><small><?= e(t('ردود اليوم')) ?></small><b data-k="replies">0</b></div>
          <div><small><?= e(t('تحويلات للفريق')) ?></small><b data-k="handoffs">0</b></div>
          <div><small><?= e(t('مسودات بانتظار المراجعة')) ?></small><b data-k="drafts">0</b></div>
          <div><small><?= e(t('متوقف فيها')) ?></small><b data-k="paused">0</b></div>
          <div><small><?= e(t('آخر تشغيل')) ?></small><b data-k="last">—</b></div>
        </div>
        <div class="ag-hook" data-hook></div>
        <?php if ($owner): ?><div class="ag-run"><button type="button" class="btn-ghost !py-1.5 !text-xs" data-run><?= ui_icon('refresh', 'w-4 h-4') ?><?= e(t('تشغيل الآن')) ?></button><span class="text-[11px] text-ink-400"><?= e(t('يعمل تلقائيًا مع التشغيل الدوري، وهذا الزر يشغّله فورًا.')) ?></span></div><?php endif; ?>
      </section>

      <div class="ag-grid">
        <section class="card ag-card">
          <h3><?= ui_icon('sliders', 'w-4 h-4') ?><?= e(t('طريقة العمل')) ?><i class="ag-saved" data-saved><?= e(t('تم الحفظ ✓')) ?></i></h3>
          <div class="ag-seg" data-seg="mode"><button type="button" data-v="draft"><b><?= e(t('مسودة للمراجعة')) ?></b><small><?= e(t('يجهّز الرد وأنت ترسله')) ?></small></button><button type="button" data-v="send"><b><?= e(t('إرسال مباشر')) ?></b><small><?= e(t('يرد تلقائيًا دون مراجعة')) ?></small></button></div>
          <label class="ag-row"><span><b><?= e(t('الرد على التعليقات')) ?></b><small><?= e(t('رد علني قصير، مع الإرسال المباشر فقط، للتعليقات الأحدث من 3 أيام')) ?></small></span><button type="button" class="ag-sw sm" data-f="comments"><i></i></button></label>
          <div class="ag-row"><span><b><?= e(t('يتوقف بعد رد بشري')) ?></b><small><?= e(t('من آخر رد يدوي على نفس المحادثة أو المنشور')) ?></small></span>
            <div class="ag-pause"><?php foreach ([15, 30, 60, 120] as $m): ?><button type="button" data-pause="<?= $m ?>"><?= $m < 60 ? e(t('%s د', $m)) : e(t('%s س', $m / 60)) ?></button><?php endforeach; ?></div></div>
        </section>

        <section class="card ag-card">
          <h3><?= ui_icon('layers', 'w-4 h-4') ?><?= e(t('المنصات والأسلوب')) ?></h3>
          <div class="ag-lbl"><?= e(t('يرد على')) ?> <small><?= e(t('(بدون اختيار = كل المنصات)')) ?></small></div>
          <div class="ag-plats"><?php foreach (ai_agent_platforms() as $pl): ?><button type="button" data-pl="<?= e($pl) ?>"><?= function_exists('platform_icon') ? platform_icon($pl, 'w-4 h-4') : '' ?><?= e(platform_label($pl)) ?></button><?php endforeach; ?></div>
          <div class="ag-lbl"><?= e(t('طول الرد')) ?></div>
          <div class="ag-pause" data-opt="len"><button type="button" data-v="short"><?= e(t('قصير')) ?></button><button type="button" data-v="medium"><?= e(t('متوسط')) ?></button><button type="button" data-v="detailed"><?= e(t('مفصّل')) ?></button></div>
          <div class="ag-lbl"><?= e(t('النبرة')) ?></div>
          <div class="ag-pause" data-opt="tone"><button type="button" data-v="brand"><?= e(t('حسب قاعدة المعرفة')) ?></button><button type="button" data-v="friendly"><?= e(t('ودودة')) ?></button><button type="button" data-v="formal"><?= e(t('رسمية')) ?></button></div>
          <div class="ag-lbl"><?= e(t('توقيع في آخر الرد (اختياري)')) ?></div>
          <input class="ag-sig" data-sig maxlength="80" dir="auto" placeholder="<?= e(t('مثلًا: فريق خدمة العملاء')) ?>">
        </section>
      </div>

      <section class="card ag-card">
        <h3><?= ui_icon('wallet', 'w-4 h-4') ?><?= e(t('توفير الرصيد')) ?><span class="ag-free" data-free></span></h3>
        <p class="ag-hint"><?= e(t('الردود البسيطة والمتكررة تُرسل بدون استدعاء الذكاء الاصطناعي، والأسئلة الجديدة فقط تستهلك رصيدًا.')) ?></p>
        <label class="ag-row"><span><b><?= e(t('التحية والشكر بدون رصيد')) ?></b><small><?= e(t('«السلام عليكم» و«شكرًا» تُرد برسالة جاهزة، والإيموجي وحده لا يُرد عليه')) ?></small></span><button type="button" class="ag-sw sm" data-save="greet"><i></i></button></label>
        <input class="ag-sig" data-greet maxlength="200" dir="auto" placeholder="<?= e(t('رسالة الترحيب (اختياري) — مثلًا: أهلًا بك في [اسم الشركة]! كيف نخدمك؟')) ?>">
        <label class="ag-row"><span><b><?= e(t('الإجابة من الأسئلة الشائعة مباشرة')) ?></b><small><?= e(t('لو السؤال يطابق سؤالًا في قاعدة المعرفة، تُرسل الإجابة المعتمدة كما هي')) ?></small></span><button type="button" class="ag-sw sm" data-save="faq"><i></i></button></label>
        <label class="ag-row"><span><b><?= e(t('إعادة استخدام الإجابات المتكررة')) ?></b><small data-cachen></small></span><button type="button" class="ag-sw sm" data-save="cache"><i></i></button></label>
        <label class="ag-row"><span><b><?= e(t('موديل أرخص للأسئلة البسيطة')) ?></b><small><?= e(t('السؤال القصير الواضح يذهب لموديل «الردود البسيطة»، والشكاوى والأسئلة المركبة لموديل الردود الأقوى')) ?></small></span><button type="button" class="ag-sw sm" data-save="route"><i></i></button></label>
        <label class="ag-row"><span><b><?= e(t('تجميع الرسائل المتتالية')) ?></b><small><?= e(t('لو أرسل العميل عدة رسائل وراء بعض، ينتظر ثواني ويرد ردًا واحدًا عليها كلها')) ?></small></span><button type="button" class="ag-sw sm" data-save="batch"><i></i></button></label>
        <label class="ag-row"><span><b><?= e(t('حد يومي لردود الذكاء الاصطناعي')) ?></b><small><?= e(t('بعده تتحول الرسائل الجديدة للفريق بدل استهلاك الرصيد. 0 = بلا حد')) ?></small></span><input type="number" min="0" max="5000" class="ag-sig !w-24 !mt-0" data-cap></label>
        <div class="ag-model"><span><?= e(t('موديل الردود الحالي:')) ?> <b data-model></b></span><a class="link" href="<?= e(url('assistant')) ?>"><?= e(t('اختر موديلًا أرخص')) ?> ←</a><?php if ($owner): ?><button type="button" class="link" data-clearcache><?= e(t('مسح الإجابات المحفوظة')) ?></button><?php endif; ?></div>
      <p class="ag-hint !mt-2 !mb-0" dir="ltr" style="text-align:end;opacity:.6">v<?= e(APP_VERSION) ?></p>
      </section>

      <?php if ($owner): ?>
      <section class="card ag-card" data-rep-box>
        <h3><?= ui_icon('chart', 'w-4 h-4') ?><?= e(t('تقرير الرصيد')) ?><span class="ag-rseg"><button type="button" data-rdays="7" class="on"><?= e(t('7 أيام')) ?></button><button type="button" data-rdays="30"><?= e(t('30 يومًا')) ?></button></span></h3>
        <div data-rep><p class="ag-hint"><?= e(t('جارٍ التحميل…')) ?></p></div>
      </section>
      <?php endif; ?>

      <section class="card ag-card" data-drafts-box>
        <h3><?= ui_icon('pencil', 'w-4 h-4') ?><?= e(t('مسودات بانتظار المراجعة')) ?><span class="ag-n" data-dn></span></h3>
        <div class="ag-drafts" data-drafts></div>
      </section>

      <div class="ag-grid">
        <section class="card ag-card">
          <h3><?= ui_icon('chat', 'w-4 h-4') ?><?= e(t('تجربة الرد الذكي')) ?></h3>
          <p class="ag-hint"><?= e(t('اكتب رسالة كما سيكتبها العميل، وشاهد كيف سيرد الرد الذكي من قاعدة المعرفة. لن يُرسل شيء.')) ?></p>
          <form class="ag-test" data-test data-no-ajax>
            <div class="ag-ch"><button type="button" data-ch="message" class="on"><?= e(t('رسالة')) ?></button><button type="button" data-ch="comment"><?= e(t('تعليق')) ?></button></div>
            <textarea name="m" rows="2" dir="auto" placeholder="<?= e(t('مثلًا: كم سعر الباقة؟ وأين توصلون؟')) ?>"></textarea>
            <div class="ag-sug"><?php foreach ([t('كم الأسعار؟'), t('ما مواعيد العمل؟'), t('أريد التحدث مع الإدارة'), t('وصل المنتج تالفًا')] as $x): ?><button type="button" data-sug><?= e($x) ?></button><?php endforeach; ?></div>
            <button class="btn-primary !py-1.5 !text-sm"><?= ui_icon('spark', 'w-4 h-4') ?><?= e(t('تجربة')) ?></button>
          </form>
          <div class="ag-out hidden" data-out></div>
          <a class="ag-kb" href="<?= e(url('knowledge')) ?>"><?= e(t('عدّل قاعدة المعرفة')) ?> ←</a>
        </section>
      </div>

      <section class="card ag-card">
          <h3><?= ui_icon('bulb', 'w-4 h-4') ?><?= e(t('اقتراحات لقاعدة المعرفة')) ?><span class="ag-n" data-sn></span></h3>
          <p class="ag-hint"><?= e(t('يظهر هنا ردّ موظفيك على الأسئلة التي حوّلها الرد الذكي، وتصحيحاتك للردود المقيّمة بـ 👎. اعتمد الاقتراح فيُجاب السؤال بعد ذلك تلقائيًا وبدون رصيد.')) ?></p>
          <div class="ag-sugs" data-sugs></div>
          <div class="ag-rated" data-rated></div>
        </section>

      <section class="card ag-card">
        <h3><?= ui_icon('pause', 'w-4 h-4') ?><?= e(t('متوقف فيها لأن الفريق رد')) ?><span class="ag-n" data-pn></span></h3>
        <div class="ag-paused" data-paused></div>
      </section>

      <section class="card ag-card">
        <h3><?= ui_icon('clock', 'w-4 h-4') ?><?= e(t('آخر ما قام به')) ?></h3>
        <div class="ag-filter"><button type="button" class="on" data-lf=""><?= e(t('الكل')) ?></button><button type="button" data-lf="msg"><?= e(t('رسائل')) ?></button><button type="button" data-lf="comment"><?= e(t('تعليقات')) ?></button><button type="button" data-lf="handoff"><?= e(t('تحويلات')) ?></button><button type="button" data-lf="draft"><?= e(t('مسودات')) ?></button></div>
        <div class="ag-log" data-log></div>
      </section>
    </div>
    <script>
    (function () {
      var C = <?= json_encode(['csrf' => csrf_token(), 'url' => url('assistant', ['tab' => 'agent']), 'inbox' => url('inbox'), 'comments' => url('comments'), 'owner' => $owner, 'st' => $st,
        'T' => ['rFree' => t('ردود بدون رصيد'), 'rAi' => t('ردود بالذكاء الاصطناعي'), 'rFast' => t('منها %s بالموديل الأرخص'), 'rCost' => t('التكلفة الفعلية'), 'rAvg' => t('متوسط الرد %s'), 'rMonth' => t('المتوقع شهريًا'), 'rSaved' => t('وفّرت حوالي %s'), 'rBreak' => t('بدون رصيد: تحية وشكر %1 · أسئلة شائعة %2 · إجابات متكررة %3 · تجاهل %4'), 'rModel' => t('الموديل'), 'rType' => t('النوع'), 'rN' => t('عدد'), 'rTok' => t('توكن داخل/خارج'), 'rCostH' => t('التكلفة'), 'rNone' => t('لا توجد ردود بالذكاء الاصطناعي في هذه الفترة.'), 'rSince' => t('عدّاد الردود المجانية يعمل منذ %s'), 'rCopy' => t('نسخ التقرير'), 'rCopied' => t('تم نسخ التقرير'), 'fromStaff' => t('من رد الموظف — اعتمده ليُجاب تلقائيًا بعد ذلك'), 'on' => t('يعمل 24 ساعة: يرد على الرسائل من قاعدة المعرفة.'), 'off' => t('متوقف: لا يرد على أحد.'), 'blocked' => t('مفعّل لكنه لن يعمل:'),
          'resume' => t('استئناف الآن'), 'left' => t('متبقٍ %s د'), 'noPaused' => t('لا توجد محادثات متوقف فيها حاليًا.'), 'noLog' => t('لم يقم بأي شيء بعد.'), 'reply' => t('رد'), 'handoff' => t('تحويل للفريق'), 'draft' => t('مسودة'),
          'msg' => t('رسالة'), 'comment' => t('تعليق'), 'testing' => t('جارٍ التفكير…'), 'willReply' => t('سيرد هكذا'), 'willHand' => t('سيحوّل للفريق'), 'why' => t('السبب'), 'err' => t('حدثت مشكلة.'), 'ran' => t('تم التشغيل: %s رد'),
          'sendWarn' => t('الإرسال المباشر سيرسل ردودًا للعملاء دون مراجعة. هل أنت متأكد؟'), 'running' => t('جارٍ التشغيل…'), 'freeToday' => t('اليوم: %s رد بدون رصيد · %s بالذكاء الاصطناعي'), 'cacheN' => t('%s إجابة محفوظة، تُستخدم لنفس السؤال لمدة 14 يومًا'), 'clearQ' => t('مسح كل الإجابات المحفوظة؟'), 'free' => t('بدون رصيد'), 'hookOn' => t('الرد الفوري مفعّل: يرد خلال ثوانٍ من وصول الرسالة، حتى واللوحة مغلقة.'), 'hookLast' => t('آخر إشعار: %s'), 'hookOff' => t('الرد الفوري غير مفعّل، فيعمل الرد الذكي فقط أثناء فتح اللوحة.'), 'hookFix' => t('تفعيل الرد الفوري'), 'open' => t('فتح'), 'send' => t('إرسال'), 'discard' => t('تجاهل'), 'noDrafts' => t('لا توجد مسودات.'), 'handDraft' => t('يحتاج تدخل الفريق'), 'openToSend' => t('افتح المحادثة'), 'sent' => t('تم الإرسال ✓'), 'good' => t('رد جيد'), 'bad' => t('رد غير مناسب'), 'fixPh' => t('اكتب الرد الصحيح (سيُقترح إضافته لقاعدة المعرفة)'), 'saveFix' => t('حفظ'), 'thanks' => t('شكرًا، تم التقييم'), 'add' => t('أضف للأسئلة الشائعة'), 'noSug' => t('لا توجد اقتراحات.'), 'rated' => t('التقييمات: 👍 %s · 👎 %s'), 'added' => t('أضيف لقاعدة المعرفة ✓'), 'edited' => t('معدّل')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
      var root = document.getElementById('ag'), T = C.T, st = C.st, $ = function (s) { return root.querySelector(s); }, $$ = function (s) { return root.querySelectorAll(s); };
      function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
      function post(d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }); }
      function toast(m, t) { if (window.fxToast) fxToast(m, t); }
      var lf = '';
      function paint() { var c = st.cfg;
        $('[data-on]').classList.toggle('on', st.on);
        var s = $('[data-status]'); s.className = 'ag-s ' + (st.active ? 'ok' : st.on ? 'warn' : '');
        s.textContent = !st.on ? T.off : st.issues.length ? T.blocked : T.on;
        $('[data-issues]').innerHTML = st.on ? st.issues.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') : '';
        $$('[data-seg]').forEach(function (g) { g.querySelectorAll('[data-v]').forEach(function (b) { b.classList.toggle('on', b.dataset.v === c[g.dataset.seg]); }); });
        $('[data-f=comments]').classList.toggle('on', !!c.comments); $('[data-f=comments]').disabled = c.mode !== 'send' || !C.owner;
        $$('[data-pause]').forEach(function (b) { b.classList.toggle('on', +b.dataset.pause === +c.pause); });
        ['replies', 'handoffs', 'drafts'].forEach(function (k) { $('[data-k=' + k + ']').textContent = st.today[k]; }); $('[data-k=paused]').textContent = st.paused.length; $('[data-k=last]').textContent = st.last || '—';
        $$('[data-pl]').forEach(function (b) { b.classList.toggle('on', c.platforms.indexOf(b.dataset.pl) > -1); });
        $$('[data-opt]').forEach(function (g) { g.querySelectorAll('[data-v]').forEach(function (b) { b.classList.toggle('on', b.dataset.v === c[g.dataset.opt]); }); });
        var sg = $('[data-sig]'); if (document.activeElement !== sg) sg.value = c.sig || '';
        $$('[data-save]').forEach(function (b) { b.classList.toggle('on', !!c.save[b.dataset.save]); b.disabled = !C.owner; });
        var cp = $('[data-cap]'); if (document.activeElement !== cp) cp.value = c.save.cap || 0; cp.disabled = !C.owner;
        var gt = $('[data-greet]'); if (document.activeElement !== gt) gt.value = c.save.greetText || ''; gt.classList.toggle('hidden', !c.save.greet);
        $('[data-free]').textContent = T.freeToday.replace('%s', st.saved.free).replace('%s', st.saved.ai);
        $('[data-cachen]').textContent = T.cacheN.replace('%s', st.saved.cached); $('[data-model]').textContent = st.model || '—';
        $('[data-dn]').textContent = st.drafts.length || ''; $('[data-drafts-box]').classList.toggle('hidden', c.mode !== 'draft' && !st.drafts.length);
        $('[data-drafts]').innerHTML = st.drafts.length ? st.drafts.map(function (d) { return '<div class="ag-dr" data-did="' + esc(d.id) + '"><div class="ag-lh"><b>' + esc(d.who || '') + '</b><small>' + esc([d.platform, d.at].filter(Boolean).join(' · ')) + '</small>' + (d.handoff ? '<span class="ag-tag hand">' + esc(T.handDraft) + '</span>' : '') + '<a href="' + esc(d.link) + '" class="ms-auto link text-xs">' + esc(T.open) + '</a></div>' +
          (d.in ? '<p class="ag-in" dir="auto">' + esc(d.in) + '</p>' : '') + '<textarea dir="auto" rows="2">' + esc(d.text) + '</textarea><div class="ag-dra">' + (d.canSend && C.owner ? '<button type="button" class="btn-primary !py-1 !text-xs" data-dsend>' + esc(T.send) + '</button>' : '<a class="btn-ghost !py-1 !text-xs" href="' + esc(d.link) + '">' + esc(T.openToSend) + '</a>') + (C.owner ? '<button type="button" class="btn-ghost !py-1 !text-xs" data-ddrop>' + esc(T.discard) + '</button>' : '') + '</div></div>'; }).join('') : '<p class="ag-empty">' + esc(T.noDrafts) + '</p>';
        $('[data-sn]').textContent = st.suggest.length || '';
        $('[data-sugs]').innerHTML = st.suggest.length ? st.suggest.map(function (x) { return '<div class="ag-sgi" data-sid="' + esc(x.id) + '">' + (x.human ? '<small class="ag-src">' + esc(T.fromStaff) + '</small>' : '') + '<input dir="auto" data-q value="' + esc(x.q) + '"><textarea dir="auto" rows="2" data-a>' + esc(x.a) + '</textarea><div class="ag-dra"><button type="button" class="btn-primary !py-1 !text-xs" data-sadd>' + esc(T.add) + '</button><button type="button" class="btn-ghost !py-1 !text-xs" data-sdrop>' + esc(T.discard) + '</button></div></div>'; }).join('') : '<p class="ag-empty">' + esc(T.noSug) + '</p>';
        $('[data-rated]').textContent = T.rated.replace('%s', st.rated.up).replace('%s', st.rated.down);
        var hk = $('[data-hook]'); hk.className = 'ag-hook ' + (st.hook.on ? 'ok' : st.on ? 'warn' : '');
        hk.innerHTML = st.hook.on ? '⚡ ' + esc(T.hookOn) + (st.hook.last ? ' · ' + esc(T.hookLast.replace('%s', st.hook.last)) : '') : (st.on ? '⚠️ ' + esc(st.hook.err || T.hookOff) + (C.owner ? ' <button type="button" class="link" data-hookfix>' + esc(T.hookFix) + '</button>' : '') : '');
        $('[data-pn]').textContent = st.paused.length || '';
        $('[data-paused]').innerHTML = st.paused.length ? st.paused.map(function (p) { return '<div class="ag-pi"><span class="ag-pic">' + (p.kind === 'comment' ? '💬' : '✉️') + '</span><div class="min-w-0 flex-1"><b>' + esc(p.name || p.key) + '</b><small>' + esc([p.platform, T.left.replace('%s', p.mins)].filter(Boolean).join(' · ')) + '</small></div>' + (C.owner ? '<button type="button" class="btn-ghost !py-1 !text-xs" data-resume="' + esc(p.key) + '">' + esc(T.resume) + '</button>' : '') + '</div>'; }).join('') : '<p class="ag-empty">' + esc(T.noPaused) + '</p>';
        var L = st.log.filter(function (x) { return !lf || (lf === 'handoff' ? x.handoff : lf === 'draft' ? x.draft : x.kind === lf); });
        $('[data-log]').innerHTML = L.length ? L.map(function (x) { var tag = x.handoff ? ['hand', T.handoff] : x.draft ? ['draft', T.draft] : ['ok', T.reply];
          return '<div class="ag-li"><div class="ag-lh"><span class="ag-tag ' + tag[0] + '">' + esc(tag[1]) + '</span><b>' + esc(x.who || '') + '</b><small>' + esc([x.kind === 'comment' ? T.comment : T.msg, x.platform, x.at].filter(Boolean).join(' · ')) + '</small>' + (x.link ? '<a href="' + esc(x.link) + '" class="ms-auto link text-xs">' + esc(T.open) + '</a>' : '') + '</div>' +
            '<p class="ag-in" dir="auto">' + esc(x.in || '') + '</p>' + (x.out ? '<p class="ag-o" dir="auto">' + esc(x.out) + (x.edited ? ' <i class="ag-ed">' + esc(T.edited) + '</i>' : '') + '</p>' : '') + (x.reason ? '<small class="ag-why">' + esc(T.why) + ': ' + esc(x.reason) + '</small>' : '') +
            (x.out && x.id ? '<div class="ag-rate" data-lid="' + esc(x.id) + '"><button type="button" data-rate="up" class="' + (x.rate === 'up' ? 'on' : '') + '" title="' + esc(T.good) + '">👍</button><button type="button" data-rate="down" class="' + (x.rate === 'down' ? 'on' : '') + '" title="' + esc(T.bad) + '">👎</button>' + (x.fix ? '<small class="ag-fixd">✓ ' + esc(x.fix) + '</small>' : '') + '</div>' : '') + '</div>'; }).join('') : '<p class="ag-empty">' + esc(T.noLog) + '</p>'; }
      paint();
      var savedT; function save(d) { if (!C.owner) return; post(Object.assign({ action: 'agent_save' }, d)).then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); st = j; paint(); var s = $('[data-saved]'); s.classList.add('show'); clearTimeout(savedT); savedT = setTimeout(function () { s.classList.remove('show'); }, 1400); }).catch(function () { toast(T.err, 'error'); }); }
      root.addEventListener('click', function (e) { var b;
        if ((b = e.target.closest('[data-on]'))) { save({ on: st.on ? '0' : '1' }); return; }
        if ((b = e.target.closest('[data-seg] [data-v]'))) { var g = b.closest('[data-seg]').dataset.seg; if (g === 'mode' && b.dataset.v === 'send' && st.cfg.mode !== 'send' && !confirm(T.sendWarn)) return; var d = {}; d[g] = b.dataset.v; save(d); return; }
        if ((b = e.target.closest('[data-f=comments]'))) { save({ comments: st.cfg.comments ? '0' : '1' }); return; }
        if ((b = e.target.closest('[data-pause]'))) { save({ pause: b.dataset.pause }); return; }
        if ((b = e.target.closest('[data-pl]'))) { var ps = st.cfg.platforms.slice(), ix = ps.indexOf(b.dataset.pl); if (ix > -1) ps.splice(ix, 1); else ps.push(b.dataset.pl); save({ platforms: ps.join(',') }); return; }
        if ((b = e.target.closest('[data-opt] [data-v]'))) { var o2 = {}; o2[b.closest('[data-opt]').dataset.opt] = b.dataset.v; save(o2); return; }
        if ((b = e.target.closest('[data-dsend],[data-ddrop]'))) { var dr = b.closest('[data-did]'), snd = b.hasAttribute('data-dsend'); b.disabled = true;
          post({ action: 'agent_draft', id: dr.dataset.did, do: snd ? 'send' : 'drop', text: dr.querySelector('textarea').value }).then(function (j) { if (!j.ok) { b.disabled = false; return toast(j.error || T.err, 'error'); } st = j; paint(); if (snd) toast(T.sent); }).catch(function () { b.disabled = false; toast(T.err, 'error'); }); return; }
        if ((b = e.target.closest('[data-rate]'))) { var rw = b.closest('[data-lid]'), v = b.dataset.rate;
          if (v === 'up') { post({ action: 'agent_rate', id: rw.dataset.lid, v: 'up' }).then(function (j) { if (j.ok) { st = j; paint(); toast(T.thanks); } }); return; }
          if (rw.querySelector('.ag-fix')) return; var fx = document.createElement('div'); fx.className = 'ag-fix'; fx.innerHTML = '<textarea dir="auto" rows="2" placeholder="' + esc(T.fixPh) + '"></textarea><button type="button" class="btn-primary !py-1 !text-xs">' + esc(T.saveFix) + '</button>'; rw.after(fx); fx.querySelector('textarea').focus();
          fx.querySelector('button').addEventListener('click', function () { post({ action: 'agent_rate', id: rw.dataset.lid, v: 'down', fix: fx.querySelector('textarea').value }).then(function (j) { if (j.ok) { st = j; paint(); toast(T.thanks); } }); }); return; }
        if ((b = e.target.closest('[data-sadd],[data-sdrop]'))) { var si = b.closest('[data-sid]'), add = b.hasAttribute('data-sadd'); b.disabled = true;
          post({ action: 'agent_suggest', id: si.dataset.sid, do: add ? 'add' : 'drop', q: si.querySelector('[data-q]').value, a: si.querySelector('[data-a]').value }).then(function (j) { if (!j.ok) { b.disabled = false; return toast(j.error || T.err, 'error'); } st = j; paint(); if (add) toast(T.added); }); return; }
        if ((b = e.target.closest('[data-save]'))) { var o3 = {}; o3['save_' + b.dataset.save] = st.cfg.save[b.dataset.save] ? '0' : '1'; save(o3); return; }
        if ((b = e.target.closest('[data-clearcache]'))) { if (!confirm(T.clearQ)) return; save({ clearCache: '1' }); return; }
        if ((b = e.target.closest('[data-hookfix]'))) { b.disabled = true; post({ action: 'agent_hook' }).then(function (j) { st = j; paint(); if (!j.ok) toast(j.error || T.err, 'error'); }); return; }
        if ((b = e.target.closest('[data-resume]'))) { b.disabled = true; post({ action: 'agent_resume', key: b.dataset.resume }).then(function (j) { if (j.ok) { st = j; paint(); } }); return; }
        if ((b = e.target.closest('[data-lf]'))) { lf = b.dataset.lf; $$('[data-lf]').forEach(function (x) { x.classList.toggle('on', x === b); }); paint(); return; }
        if ((b = e.target.closest('[data-run]'))) { b.disabled = true; var o = b.innerHTML; b.textContent = T.running; post({ action: 'agent_run' }).then(function (j) { b.disabled = false; b.innerHTML = o; if (!j.ok) return toast(j.error || T.err, 'error'); st = j; paint(); toast(T.ran.replace('%s', j.n)); }).catch(function () { b.disabled = false; b.innerHTML = o; toast(T.err, 'error'); }); return; }
        if ((b = e.target.closest('[data-ch]'))) { $$('[data-ch]').forEach(function (x) { x.classList.toggle('on', x === b); }); return; }
        if ((b = e.target.closest('[data-sug]'))) { var ta = $('[data-test] textarea'); ta.value = b.textContent; ta.focus(); return; }
      });
      var rDays = 7;
      function money(v) { return '$' + (v < 0.01 && v > 0 ? v.toFixed(4) : v.toFixed(2)); }
      function rep() { var box = $('[data-rep]'); if (!box) return; post({ action: 'agent_report', days: rDays }).then(function (j) { if (!j.ok) return; var r = j.r;
        var tile = function (l, v, s) { return '<div class="ag-rt"><small>' + esc(l) + '</small><b>' + v + '</b>' + (s ? '<i>' + esc(s) + '</i>' : '') + '</div>'; };
        var h = '<div class="ag-rts">' + tile(T.rFree, r.free, r.freePct + '%') + tile(T.rAi, r.ai, r.fast ? T.rFast.replace('%s', r.fast) : '') + tile(T.rCost, money(r.cost), T.rAvg.replace('%s', money(r.avg))) + tile(T.rMonth, money(r.month), T.rSaved.replace('%s', money(r.saved))) + '</div>';
        h += '<p class="ag-rbr">' + esc(T.rBreak.replace('%1', r.byFree.greet).replace('%2', r.byFree.faq).replace('%3', r.byFree.cache).replace('%4', r.byFree.skip)) + '</p>';
        if (r.models.length) h += '<div class="ag-rtb"><table><thead><tr><th>' + esc(T.rModel) + '</th><th>' + esc(T.rType) + '</th><th>' + esc(T.rN) + '</th><th>' + esc(T.rTok) + '</th><th>' + esc(T.rCostH) + '</th></tr></thead><tbody>' + r.models.map(function (m) { return '<tr><td dir="ltr">' + esc(m.model) + '</td><td>' + esc(m.task) + '</td><td>' + m.n + '</td><td dir="ltr">' + m.tin + ' / ' + m.tout + '</td><td dir="ltr">' + money(m.cost) + (m.unk ? ' *' : '') + '</td></tr>'; }).join('') + '</tbody></table></div>';
        else h += '<p class="ag-hint">' + esc(T.rNone) + '</p>';
        h += '<ul class="ag-tips">' + r.tips.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>';
        if (r.histStart) h += '<p class="ag-hint">' + esc(T.rSince.replace('%s', r.histStart)) + '</p>';
        h += '<button type="button" class="btn-ghost !py-1.5 !text-xs border border-ink-200" data-rcopy>' + esc(T.rCopy) + '</button>';
        box.innerHTML = h; box._r = r; }); }
      root.addEventListener('click', function (e) { var b = e.target.closest('[data-rdays]'); if (b) { rDays = +b.dataset.rdays; $$('[data-rdays]').forEach(function (x) { x.classList.toggle('on', x === b); }); rep(); return; }
        if (e.target.closest('[data-rcopy]')) { var r = $('[data-rep]')._r; if (!r) return; var t = 'Smart Reply report (' + r.days + 'd)\nfree: ' + r.free + ' (' + r.freePct + '%) greet ' + r.byFree.greet + ' faq ' + r.byFree.faq + ' cache ' + r.byFree.cache + ' skip ' + r.byFree.skip + '\nAI: ' + r.ai + ' (fast ' + r.fast + ') cap: ' + r.cap + '\ncost: ' + money(r.cost) + ' avg ' + money(r.avg) + ' month~' + money(r.month) + '\n' + r.models.map(function (m) { return m.model + ' [' + (m.fast ? 'fast' : 'reply') + '] n=' + m.n + ' tok=' + m.tin + '/' + m.tout + ' ' + money(m.cost); }).join('\n');
          (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { toast(T.rCopied); }, function () { window.prompt('', t); }); } });
      rep();
      var cpT; $('[data-cap]').addEventListener('input', function () { var v = this.value; clearTimeout(cpT); cpT = setTimeout(function () { save({ cap: v }); }, 700); });
      var grT; $('[data-greet]').addEventListener('input', function () { var v = this.value; clearTimeout(grT); grT = setTimeout(function () { save({ greetText: v }); }, 700); });
      var sgT; $('[data-sig]').addEventListener('input', function () { var v = this.value; clearTimeout(sgT); sgT = setTimeout(function () { save({ sig: v }); }, 700); });
      $('[data-test]').addEventListener('submit', function (e) { e.preventDefault(); var f = this, m = f.m.value.trim(), out = $('[data-out]'), btn = f.querySelector('.btn-primary'); if (!m) return f.m.focus();
        btn.disabled = true; out.classList.remove('hidden'); out.className = 'ag-out'; out.innerHTML = '<span class="ag-dots">' + esc(T.testing) + '</span>';
        post({ action: 'agent_test', m: m, ch: (root.querySelector('[data-ch].on') || {}).dataset.ch || 'message' }).then(function (j) { btn.disabled = false;
          if (!j.ok) { out.className = 'ag-out err'; out.textContent = j.error || T.err; return; }
          out.className = 'ag-out ' + (j.action === 'handoff' ? 'hand' : 'ok'); out.innerHTML = '<b>' + esc(j.action === 'handoff' ? T.willHand : T.willReply) + (j.src && j.src !== 'ai' ? ' <i class="ag-freebadge">' + esc(T.free) + '</i>' : '') + '</b>' + (j.reply ? '<p dir="auto">' + esc(j.reply) + '</p>' : '') + (j.reason ? '<small>' + esc(T.why) + ': ' + esc(j.reason) + '</small>' : ''); })
          .catch(function () { btn.disabled = false; out.className = 'ag-out err'; out.textContent = T.err; }); });
      // تحديث الحالة كل دقيقة
      setInterval(function () { if (document.hidden) return; fetch(C.url + '&ajax=agent_state', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (j.ok) { st = j; paint(); } }).catch(function () {}); }, 60000);
    })();
    </script>
    <?php
}
