<?php
$err = null;
$z = zernio_or_null($err);
$fxd0 = getenv('UI_FIXTURES');
if ($fxd0 && is_file($fxd0 . '/posts_fake_z.php')) $z = require $fxd0 . '/posts_fake_z.php'; // اختبارات بس: الكتابة بتتسجل بدل ما تتبعت

/** ميعاد جديد لمنشور مجدول/مسودة.
 *  Zernio ممكن يرد 200 من غير ما يغير الميعاد، فبنتأكد بقراءة المنشور تاني. الميعاد بيتبعت UTC صريح (Z) عشان مايتفهمش بتوقيت تاني. */
function ps_same_time(?string $got, DateTime $want): bool
{
    if (!$got) return false;
    try { return abs((new DateTime($got))->getTimestamp() - $want->getTimestamp()) < 90; } catch (Throwable $e) { return false; }
}
/** سجل تشخيص لمحاولات تغيير الميعاد (آخر 10) — عشان نعرف Zernio بيرد بإيه فعلًا */
function ps_diag_add(array $d): void
{
    $k = 'ps_diag_' . (int)ws('id');
    $all = (array)json_decode((string)setting($k, '[]'), true);
    array_unshift($all, $d + ['at' => gmdate('Y-m-d H:i:s') . 'Z']);
    save_setting($k, json_encode(array_slice($all, 0, 10), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function ps_diag_post(array $p): array
{
    $pl = array_map(function ($x) { return ['pf' => $x['platform'] ?? '', 'st' => $x['status'] ?? '', 'sch' => $x['scheduledFor'] ?? null]; }, (array)($p['platforms'] ?? []));
    $keys = array_values(array_intersect(array_keys($p), ['queuedFromProfile', 'queueId', 'queueSlot', 'isDraft', 'recycling', 'timezone', 'requiresApproval', 'approvalStatus']));
    $extra = []; foreach ($keys as $k) $extra[$k] = is_scalar($p[$k]) || $p[$k] === null ? $p[$k] : '…';
    return ['status' => $p['status'] ?? null, 'scheduledFor' => $p['scheduledFor'] ?? null, 'tz' => $p['timezone'] ?? null, 'platforms' => $pl] + $extra;
}
function ps_reschedule(Zernio $z, string $id, DateTime $dt): void
{
    $p = pub_get_post($z, $id);
    if (in_array((string)($p['status'] ?? ''), ['published', 'publishing'], true)) throw new ZernioException(t('المنشور اتنشر خلاص، مينفعش تغير ميعاده.'));
    $diag = ['post' => $id, 'want' => ps_utc($dt), 'before' => ps_diag_post($p), 'tries' => []];
    $tries = [
        'full' => function () use ($p, $dt) { $x = ['content' => (string)($p['content'] ?? ''), 'platforms' => ps_platforms($p), 'timezone' => site_timezone(), 'scheduledFor' => ps_utc($dt)]; if ($m = ps_media($p)) $x['mediaItems'] = $m; return $x; },
        'time_only' => function () use ($dt) { return ['scheduledFor' => ps_utc($dt)]; },
        'time_local_tz' => function () use ($dt) { $l = clone $dt; $l->setTimezone(new DateTimeZone(site_timezone())); return ['scheduledFor' => $l->format('Y-m-d\TH:i:s'), 'timezone' => site_timezone()]; },
    ];
    // لو المنشور متربط بطابور، Zernio ممكن يرجّعه لميعاد الطابور — بنفصله عن الطابور في المحاولة الأخيرة
    if (!empty($p['queuedFromProfile']) || !empty($p['queueId'])) $tries['unqueue'] = function () use ($dt) { return ['scheduledFor' => ps_utc($dt), 'queuedFromProfile' => null, 'queueId' => null]; };
    foreach ($tries as $name => $mk) {
        $body = $mk(); $t = ['try' => $name, 'sent' => array_intersect_key($body, array_flip(['scheduledFor', 'timezone', 'queuedFromProfile', 'queueId'])) + ['fields' => array_keys($body)]];
        try {
            $r = pub_update_post($z, $id, $body);
            $t['resp'] = ['message' => $r['message'] ?? null, 'warnings' => $r['warnings'] ?? null, 'scheduledFor' => $r['post']['scheduledFor'] ?? null, 'status' => $r['post']['status'] ?? null];
        } catch (ZernioException $ex) { $t['error'] = mb_substr($ex->getMessage(), 0, 300); $diag['tries'][] = $t; continue; }
        // التأكيد دايمًا بقراءة المنشور من جديد، مش من رد التعديل
        $after = pub_get_post($z, $id); $t['after'] = ps_diag_post($after);
        $diag['tries'][] = $t;
        if (ps_same_time((string)($after['scheduledFor'] ?? ''), $dt)) { $diag['ok'] = $name; ps_diag_add($diag); return; }
    }
    $diag['ok'] = false; ps_diag_add($diag);
    $got = (string)($diag['tries'] ? (end($diag['tries'])['after']['scheduledFor'] ?? '') : '');
    $tz = new DateTimeZone(site_timezone()); $now = '—';
    if ($got) { try { $g = new DateTime($got); $g->setTimezone($tz); $now = $g->format('Y-m-d H:i'); } catch (Throwable $e) {} }
    $err = ''; foreach ($diag['tries'] as $t) if (!empty($t['error'])) { $err = $t['error']; break; }
    log_activity('post.reschedule_failed', $id);
    throw new ZernioException($err !== '' ? t('Zernio رفض تغيير الميعاد: %s', $err) : t('Zernio رد إن التعديل تم، بس الميعاد مااتغيرش (لسه %s). افتح "تشخيص تغيير المواعيد" تحت الصفحة وابعتلي اللي فيه.', $now));
}
/** ملاحظات الفريق الداخلية (مابتتنشرش) */
function ps_notes_ok(): bool { static $ok = null; if ($ok === null) { try { db()->query('SELECT 1 FROM post_notes LIMIT 1'); $ok = true; } catch (Throwable $e) { $ok = false; } } return $ok; }
function ps_notes(array $ids): array
{
    if (!$ids || !ps_notes_ok()) return [];
    $st = db()->prepare('SELECT n.post_id, n.body, n.created_at, u.name FROM post_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.workspace_id = ? AND n.post_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY n.id');
    $st->execute(array_merge([(int)ws('id')], $ids));
    $out = []; foreach ($st->fetchAll() as $r) $out[$r['post_id']][] = ['b' => $r['body'], 'at' => $r['created_at'], 'by' => (string)($r['name'] ?? '')];
    return $out;
}
/** نسخة مجدولة من منشور (إعادة نشر) */
function ps_repost(Zernio $z, string $id, DateTime $dt): string
{
    $p = pub_get_post($z, $id);
    $copy = ['content' => (string)($p['content'] ?? ''), 'timezone' => site_timezone(), 'scheduledFor' => ps_utc($dt)];
    if ($m = ps_media($p)) $copy['mediaItems'] = $m;
    $pl = ps_platforms($p); if (!$pl) throw new ZernioException(t('المنشور ده مالوش حسابات.'));
    $copy['platforms'] = $pl;
    $r = $z->createPost($copy);
    return (string)($r['post']['_id'] ?? '');
}
function ps_duplicate(Zernio $z, string $id): string
{
    $p = pub_get_post($z, $id);
    $copy = ['content' => (string)($p['content'] ?? ''), 'isDraft' => true, 'timezone' => site_timezone()];
    if ($m = ps_media($p)) $copy['mediaItems'] = $m;
    if ($pl = ps_platforms($p)) $copy['platforms'] = $pl;
    $r = $z->createPost($copy);
    return (string)($r['post']['_id'] ?? '');
}


/** عنصر التقويم */
function ps_cal_item(array $p): ?array
{
    $w = $p['scheduledFor'] ?? $p['publishedAt'] ?? $p['createdAt'] ?? null; if (!$w) return null;
    return ['id' => $p['_id'], 'at' => (string)$w, 'st' => (string)($p['status'] ?? ''), 'mv' => in_array((string)($p['status'] ?? ''), ['scheduled', 'draft'], true) && !empty($p['scheduledFor']),
        'txt' => mb_substr((string)($p['content'] ?? ''), 0, 120), 'pf' => array_values(array_unique(array_map(function ($pl) { return (string)($pl['platform'] ?? ''); }, $p['platforms'] ?? []))),
        'ac' => array_values(array_unique(array_filter(array_map(function ($pl) { return (string)pub_acc_id($pl['accountId'] ?? ''); }, $p['platforms'] ?? [])))),
        'tg' => (function ($id) { static $m = null; if ($m === null) $m = (function_exists('an_tags_available') && an_tags_available()) ? an_tags_map() : []; return $m[$id] ?? []; })((string)($p['_id'] ?? '')),
        'eg' => (function ($id) { static $e = null; if ($e === null) { $e = []; try { $s = db()->prepare('SELECT post_id, every_days FROM post_evergreen WHERE workspace_id = ?'); $s->execute([(int)ws('id')]); foreach ($s->fetchAll() as $r) $e[$r['post_id']] = (int)$r['every_days']; } catch (Throwable $x) {} } return $e[$id] ?? 0; })((string)($p['_id'] ?? '')),
        'img' => (($p['mediaItems'][0]['type'] ?? '') === 'image') ? ($p['mediaItems'][0]['url'] ?? '') : ''];
}
/** كل المنشورات المجدولة في فترة (مش أول 100 بس): صفحات مترتبة بالميعاد لحد ما نعدّي آخر الفترة */
function ps_cal_scheduled(Zernio $z, int $from, int $to, ?array $fixture = null): array
{
    $in = function ($p) use ($from, $to) { $t = strtotime((string)($p['scheduledFor'] ?? '')); return $t && $t >= $from && $t <= $to; };
    if ($fixture !== null) return array_values(array_filter($fixture, function ($p) use ($in) { return ($p['status'] ?? '') === 'scheduled' && $in($p); }));
    $out = [];
    for ($pg = 1; $pg <= 30; $pg++) {
        $r = $z->posts(['status' => 'scheduled', 'limit' => 100, 'page' => $pg, 'sortBy' => 'scheduled-asc', 'profileId' => zernio_profile()]);
        $list = (array)($r['posts'] ?? []);
        foreach ($list as $p) if ($in($p)) $out[] = $p;
        $last = $list ? strtotime((string)(end($list)['scheduledFor'] ?? '')) : 0;
        if (count($list) < 100 || ($last && $last > $to) || $pg >= (int)($r['pagination']['pages'] ?? $pg)) break;
    }
    return $out;
}
if ($z && ($_GET['ajax'] ?? '') === 'cal') {
    header('Content-Type: application/json; charset=utf-8');
    $tz = new DateTimeZone(site_timezone());
    $f = DateTime::createFromFormat('!Y-m-d', (string)($_GET['from'] ?? ''), $tz); $t = DateTime::createFromFormat('!Y-m-d', (string)($_GET['to'] ?? ''), $tz);
    if (!$f || !$t || $t < $f) exit(json_encode(['ok' => false]));
    if ($t->getTimestamp() - $f->getTimestamp() > 120 * 86400) $t = (clone $f)->modify('+120 day');
    $t->setTime(23, 59, 59);
    $fxp = getenv('POSTS_FIXTURE') ?: (getenv('UI_FIXTURES') ? getenv('UI_FIXTURES') . '/posts.json' : '');
    $fx = ($fxp && is_file($fxp)) ? (array)(json_decode((string)file_get_contents($fxp), true)['posts'] ?? []) : null;
    try {
        $items = array_values(array_filter(array_map('ps_cal_item', ps_cal_scheduled($z, $f->getTimestamp(), $t->getTimestamp(), $fx))));
        exit(json_encode(['ok' => true, 'items' => $items, 'from' => $f->format('Y-m-d'), 'to' => $t->format('Y-m-d')], JSON_UNESCAPED_UNICODE));
    } catch (ZernioException $ex) { exit(json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE)); }
}

if ($z && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'vary') {
    header('Content-Type: application/json; charset=utf-8');
    if (($_POST['mode'] ?? '') !== 'fast' && !can('ai.use')) exit(json_encode(['ok' => false, 'error' => t('مالكش صلاحية استخدام الذكاء الاصطناعي.')], JSON_UNESCAPED_UNICODE));
    $ids = array_slice(array_values(array_filter(array_map('strval', (array)($_POST['ids'] ?? [])), function ($x) { return preg_match('/^[\w-]{1,64}$/', $x); })), 0, 12);
    if (count($ids) < 2) exit(json_encode(['ok' => false, 'error' => t('محتاج منشورين على الأقل.')], JSON_UNESCAPED_UNICODE));
    try {
        $base = (string)(pub_get_post($z, $ids[0])['content'] ?? '');
        if (trim($base) === '') throw new RuntimeException(t('النص مينفعش يكون فاضي.'));
        $n = count($ids);
        if (($_POST['mode'] ?? '') === 'fast') {
            // صياغات بالقواعد (من غير AI ولا كريديت): نفس المعلومات بترتيب وافتتاحية وختام مختلفين
            $out = []; foreach ($ids as $i => $id) $out[] = ['id' => $id, 'text' => ps_vary_text($base, $i)];
            echo json_encode(['ok' => true, 'items' => $out, 'base' => $base, 'fast' => true], JSON_UNESCAPED_UNICODE); exit;
        }
        $sys = 'POST_VARIANTS_JSON. أنت كاتب محتوى سوشيال ميديا. هيتنشر نفس المحتوى ' . $n . ' مرات في أيام مختلفة، والمنصات بتعاقب النص المتكرر. اكتب ' . $n . ' صياغات مختلفة بنفس المعنى والمعلومات بالظبط (نفس الأسعار والأرقام والروابط والهاشتاجات المهمة)، '
            . 'كل واحدة بافتتاحية وترتيب مختلف، وبنفس اللغة واللهجة والطول تقريبًا. متضيفش معلومات جديدة. أعد JSON فقط بالشكل {"variants":["...","..."]}.';
        $r = ai_chat([['role' => 'system', 'content' => $sys . ai_country_note()], ['role' => 'user', 'content' => $base]], min(3800, 300 + $n * 220), 'compose');
        // بنقبل أي شكل: {"variants":[..]} أو [..] أو مفتاح تاني، ولو الرد اتقطع بناخد اللي كامل منه
        $txt = trim((string)$r['text']); $txt = preg_replace('/^```(?:json)?|```$/m', '', $txt);
        $j = json_decode($txt, true);
        if (!is_array($j) && preg_match('/[\[{].*[\]}]/s', $txt, $m)) $j = json_decode($m[0], true);
        $list = is_array($j) ? ($j['variants'] ?? $j['items'] ?? $j['texts'] ?? (array_keys($j) === range(0, count($j) - 1) ? $j : (array)reset($j))) : [];
        if (!$list && preg_match_all('/"((?:[^"\\\\]|\\\\.){20,})"/u', $txt, $mm)) $list = array_map(function ($x) { return json_decode('"' . $x . '"') ?? $x; }, $mm[1]);
        $v = array_values(array_filter(array_map(function ($x) { return trim(is_array($x) ? (string)($x['text'] ?? reset($x)) : (string)$x); }, (array)$list), 'strlen'));
        // اللي ناقص بيتكمّل بالقواعد بدل ما نرفض الكل
        for ($k = count($v); $k < $n; $k++) $v[$k] = ps_vary_text($base, $k);
        log_activity('ai.post_variants', $n . ' posts');
        $out = []; foreach ($ids as $i => $id) $out[] = ['id' => $id, 'text' => mb_substr($v[$i], 0, 5000)];
        echo json_encode(['ok' => true, 'items' => $out, 'base' => $base], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// #19 Evergreen: منشور ناجح بيتعاد كل X يوم بصياغة مختلفة (بالقواعد، من غير AI) — #20 أوقات ممنوعة للنشر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['evergreen', 'blackout', 'goals'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    if (!can('posts.manage')) exit(json_encode(['ok' => false, 'error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], JSON_UNESCAPED_UNICODE));
    $wid = (int)ws('id');
    if ($_POST['action'] === 'goals') {
        if (!can('posts.manage')) { http_response_code(403); exit(json_encode(['error' => t('مالكش صلاحية.')], JSON_UNESCAPED_UNICODE)); }
        $g = []; foreach ((array)json_decode((string)($_POST['goals'] ?? '{}'), true) as $k => $n) if (preg_match('/^[\w-]{1,64}$/', (string)$k) && (int)$n > 0) $g[(string)$k] = min(100, (int)$n);
        save_setting('post_goals_' . $wid, json_encode($g)); log_activity('post.goals', (string)count($g));
        exit(json_encode(['ok' => true, 'goals' => $g]));
    }
    if ($_POST['action'] === 'blackout') {
        $rules = [];
        foreach ((array)json_decode((string)($_POST['rules'] ?? '[]'), true) as $r) {
            $d = array_values(array_intersect(array_map('intval', (array)($r['days'] ?? [])), range(0, 6)));
            $f = preg_match('/^\d{2}:\d{2}$/', (string)($r['from'] ?? '')) ? $r['from'] : ''; $t2 = preg_match('/^\d{2}:\d{2}$/', (string)($r['to'] ?? '')) ? $r['to'] : '';
            if ($d && $f !== '' && $t2 !== '') $rules[] = ['days' => $d, 'from' => $f, 'to' => $t2];
        }
        save_setting('ps_blackout_' . $wid, json_encode(array_slice($rules, 0, 10)));
        exit(json_encode(['ok' => true, 'rules' => $rules], JSON_UNESCAPED_UNICODE));
    }
    $id = preg_replace('/[^\w-]/', '', (string)($_POST['id'] ?? '')); $every = max(0, min(365, (int)($_POST['every'] ?? 0)));
    if ($id === '') exit(json_encode(['ok' => false], JSON_UNESCAPED_UNICODE));
    if (!$every) db()->prepare('DELETE FROM post_evergreen WHERE workspace_id = ? AND post_id = ?')->execute([$wid, $id]);
    else db()->prepare('INSERT INTO post_evergreen (workspace_id, post_id, every_days, next_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE every_days = VALUES(every_days), next_at = VALUES(next_at), last_error = NULL')
        ->execute([$wid, $id, $every, date('Y-m-d H:i:s', time() + $every * 86400)]);
    log_activity('post.evergreen', $id . ' ' . $every);
    exit(json_encode(['ok' => true, 'every' => $every], JSON_UNESCAPED_UNICODE));
}

// #15 أفكار الأسبوع (AI، بيصرف كريديت بس لما تدوس): 7 أفكار من بيانات الشركة في قاعدة المعرفة + المواسم الجاية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'week_ideas') {
    header('Content-Type: application/json; charset=utf-8');
    if (!can('ai.use') || !ai_connection()) exit(json_encode(['ok' => false, 'error' => t('مالكش صلاحية استخدام الذكاء الاصطناعي.')], JSON_UNESCAPED_UNICODE));
    try {
        $kb = function_exists('kb_compile') ? mb_substr(kb_compile((int)ws('id'), 'خدمات منتجات عروض جمهور'), 0, 3500) : '';
        $se = function_exists('ps_seasons') ? implode('، ', array_map(function ($x) { return $x['n'] . ' ' . $x['d']; }, ps_seasons(date('Y-m-d'), date('Y-m-d', strtotime('+14 days'))))) : '';
        $sys = 'WEEK_IDEAS_JSON. أنت مخطط محتوى سوشيال ميديا. اكتب 7 أفكار منشورات لأسبوع واحد (فكرة لكل يوم) لنفس الشركة، متنوعة: تعليمي، عرض، رأي عميل، سؤال للتفاعل، خلف الكواليس، نصيحة، دعوة للتواصل. استخدم معلومات الشركة بس ومتخترعش أسعار. '
            . 'لكل فكرة: عنوان قصير ومسودة منشور 2-4 سطور بنفس لغة ولهجة الشركة. أعد JSON فقط: {"ideas":[{"t":"...","b":"..."}]}' . ai_country_note();
        $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => "معلومات الشركة:\n" . ($kb ?: '(مفيش)') . ($se ? "\nمناسبات قريبة: " . $se : '')]], 1800, 'compose');
        $txt = (string)$r['text']; if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
        $ideas = array_values(array_filter(array_map(function ($x) { return is_array($x) ? ['t' => mb_substr(trim((string)($x['t'] ?? '')), 0, 120), 'b' => mb_substr(trim((string)($x['b'] ?? '')), 0, 1500)] : null; }, (array)(json_decode($txt, true)['ideas'] ?? [])), function ($x) { return $x && $x['b'] !== ''; }));
        if (!$ideas) throw new RuntimeException(t('الرد مكانش بالشكل المتوقع، جرّب تاني.'));
        log_activity('ai.week_ideas', count($ideas) . '');
        echo json_encode(['ok' => true, 'ideas' => array_slice($ideas, 0, 7), 'kb' => $kb !== ''], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) { echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE); }
    exit;
}

// إجراءات سريعة من الصفحة (JSON): ميعاد جديد، إعادة محاولة، نسخة، تأجيل جماعي
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && ($_POST['action'] ?? '') === 'quick') {
    header('Content-Type: application/json; charset=utf-8');
    $op = (string)($_POST['op'] ?? '');
    if ($op === 'note') {
        if (!ps_notes_ok()) exit(json_encode(['error' => t('شغّل setup.php الأول عشان الملاحظات تشتغل.')], JSON_UNESCAPED_UNICODE));
        $pid = (string)($_POST['ids'][0] ?? ''); $body = trim(mb_substr((string)($_POST['body'] ?? ''), 0, 1000));
        if (!preg_match('/^[\w-]{1,64}$/', $pid) || $body === '') exit(json_encode(['error' => t('اكتب الملاحظة الأول.')], JSON_UNESCAPED_UNICODE));
        db()->prepare('INSERT INTO post_notes (workspace_id, post_id, user_id, body) VALUES (?, ?, ?, ?)')->execute([(int)ws('id'), $pid, (int)(current_user()['id'] ?? 0) ?: null, $body]);
        log_activity('post.note', $pid);
        exit(json_encode(['ok' => [$pid], 'failed' => [], 'notes' => ps_notes([$pid])[$pid] ?? []], JSON_UNESCAPED_UNICODE));
    }
    if ($op === 'tags' || $op === 'tag_add') {
        if (!function_exists('an_tags_available') || !an_tags_available()) exit(json_encode(['error' => t('شغّل setup.php الأول عشان التاجات تشتغل.')], JSON_UNESCAPED_UNICODE));
        if (!can('posts.create')) { http_response_code(403); exit(json_encode(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], JSON_UNESCAPED_UNICODE)); }
        $ids = array_slice(array_values(array_filter(array_map('strval', (array)($_POST['ids'] ?? [])), function ($x) { return preg_match('/^[\w-]{1,64}$/', $x); })), 0, 100);
        $map = an_tags_map(); $out = [];
        $new = array_filter(array_map('trim', preg_split('/[,،]+/u', (string)($_POST['tags'] ?? ''))), 'strlen');
        foreach ($ids as $id) {
            $tags = $op === 'tags' ? $new : array_merge($map[$id] ?? [], $new);
            an_set_tags($id, $tags);
            $out[$id] = array_values(an_tags_map()[$id] ?? []);
        }
        log_activity('post.tags', count($ids) . ' ' . implode(',', $new));
        exit(json_encode(['ok' => array_keys($out), 'tags' => $out, 'failed' => []], JSON_UNESCAPED_UNICODE));
    }
    $need = in_array($op, ['dup', 'repost', 'note', 'copyshift'], true) ? 'posts.create' : 'posts.manage';
    if (!can($need)) { http_response_code(403); exit(json_encode(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], JSON_UNESCAPED_UNICODE)); }
    $ids = array_slice(array_values(array_filter(array_map('strval', (array)($_POST['ids'] ?? [])), function ($x) { return preg_match('/^[\w-]{1,64}$/', $x); })), 0, 50);
    $tz = new DateTimeZone(site_timezone());
    $ok = []; $fail = [];
    @set_time_limit(30 + 3 * count($ids));
    foreach ($ids as $id) {
        try {
            if ($op === 'resched') {
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', (string)($_POST['when'] ?? ''), $tz);
                if (!$dt || $dt < new DateTime('now', $tz)) throw new ZernioException(t('وقت النشر لازم يكون في المستقبل.'));
                ps_reschedule($z, $id, $dt); log_activity('post.reschedule', $id);
            } elseif ($op === 'shift') {
                $n = max(-365, min(365, (int)($_POST['n'] ?? 1))); $u = ($_POST['unit'] ?? 'd') === 'h' ? 'hour' : 'day';
                $p = pub_get_post($z, $id);
                if (empty($p['scheduledFor'])) throw new ZernioException(t('المنشور ده مش مجدول.'));
                $dt = new DateTime((string)$p['scheduledFor']); $dt->setTimezone($tz); $dt->modify(($n >= 0 ? '+' : '') . $n . ' ' . $u);
                if ($dt < new DateTime('now', $tz)) throw new ZernioException(t('الميعاد الجديد هيبقى فات.'));
                ps_reschedule($z, $id, $dt); log_activity('post.shift', $id . ' ' . $n . $u);
            } elseif ($op === 'replace') {
                $find = (string)($_POST['find'] ?? ''); $repl = (string)($_POST['repl'] ?? '');
                if (trim($find) === '') throw new ZernioException(t('اكتب الكلمة اللي هتدور عليها.'));
                $p = pub_get_post($z, $id);
                if (in_array((string)($p['status'] ?? ''), ['published', 'publishing'], true)) throw new ZernioException(t('المنشور اتنشر خلاص. عدّله من صفحة التعديل على كل منصة.'));
                $old = (string)($p['content'] ?? ''); $n = 0;
                $new = str_replace($find, $repl, $old, $n);
                $pls = ps_platforms($p);
                foreach ($pls as &$pl) { if (!empty($pl['customContent'])) { $c = 0; $pl['customContent'] = str_replace($find, $repl, (string)$pl['customContent'], $c); $n += $c; } }
                unset($pl);
                if (!$n) throw new ZernioException(t('الكلمة مش موجودة في المنشور ده.'));
                if (trim($new) === '') throw new ZernioException(t('النص مينفعش يكون فاضي.'));
                $payload = ['content' => $new, 'platforms' => $pls, 'timezone' => site_timezone()];
                if (!empty($p['scheduledFor'])) { $payload['scheduledFor'] = ps_utc(new DateTime((string)$p['scheduledFor'])); } // نفس الميعاد بالظبط (UTC)
                if ($m = ps_media($p)) $payload['mediaItems'] = $m;
                pub_update_post($z, $id, $payload); log_activity('post.replace', $id . ' x' . $n);
            } elseif ($op === 'text') {
                $txt = trim((string)($_POST['content'] ?? ''));
                if ($txt === '') throw new ZernioException(t('النص مينفعش يكون فاضي.'));
                $p = pub_get_post($z, $id);
                if (in_array((string)($p['status'] ?? ''), ['published', 'publishing'], true)) throw new ZernioException(t('المنشور اتنشر خلاص. عدّله من صفحة التعديل على كل منصة.'));
                $payload = ['content' => mb_substr($txt, 0, 10000), 'platforms' => ps_platforms($p), 'timezone' => site_timezone()];
                if (!empty($p['scheduledFor'])) { $payload['scheduledFor'] = ps_utc(new DateTime((string)$p['scheduledFor'])); } // نفس الميعاد بالظبط (UTC)
                if ($m = ps_media($p)) $payload['mediaItems'] = $m;
                pub_update_post($z, $id, $payload); log_activity('post.update', $id);
            } elseif ($op === 'repost') {
                if (!can('posts.create')) throw new ZernioException(t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.'));
                $base = DateTime::createFromFormat('Y-m-d\TH:i', (string)($_POST['when'] ?? ''), $tz);
                if (!$base) throw new ZernioException(t('اختار تاريخ ووقت النشر.'));
                $gap = max(0, min(60, (int)($_POST['gap'] ?? 0)));
                $dt = (clone $base)->modify('+' . ($gap * array_search($id, $ids, true)) . ' day');
                if ($dt < new DateTime('now', $tz)) throw new ZernioException(t('وقت النشر لازم يكون في المستقبل.'));
                ps_repost($z, $id, $dt); log_activity('post.repost', $id . ' ' . $dt->format('Y-m-d H:i'));
            } elseif ($op === 'retry') {
                pub_retry_post($z, $id); log_activity('post.retry', $id);
            } elseif ($op === 'dup') {
                ps_duplicate($z, $id); log_activity('post.duplicate', $id);
            } elseif ($op === 'copyshift') { // #22 نسخ الأسبوع: نسخة من المنشور بعد N يوم في نفس الساعة
                if (!can('posts.create')) throw new ZernioException(t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.'));
                $p = pub_get_post($z, $id); if (empty($p['scheduledFor'])) throw new ZernioException(t('المنشور ده مش مجدول.'));
                $dt = new DateTime((string)$p['scheduledFor']); $dt->setTimezone($tz); $dt->modify('+' . max(1, min(60, (int)($_POST['n'] ?? 7))) . ' day');
                ps_repost($z, $id, $dt); log_activity('post.copy_week', $id);
            } elseif ($op === 'from_draft') { // #17 املا الفراغات: المسودة تتجدول في ميعاد فاضي (نسخة مجدولة والمسودة تتمسح)
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', (string)($_POST['when'] ?? ''), $tz);
                if (!$dt || $dt < new DateTime('now', $tz)) throw new ZernioException(t('وقت النشر لازم يكون في المستقبل.'));
                $nid = ps_repost($z, $id, $dt); if ($nid !== '') { try { $z->deletePost($id); } catch (ZernioException $e) {} }
                log_activity('post.fill_gap', $id . ' ' . $dt->format('Y-m-d H:i'));
            } else {
                throw new ZernioException('bad op');
            }
            $ok[] = $id;
        } catch (ZernioException $ex) { $fail[$id] = $ex->getMessage(); }
    }
    exit(json_encode(['ok' => $ok, 'failed' => $fail], JSON_UNESCAPED_UNICODE));
}

// حذف من غير تحميل الصفحة (JSON)، أو بالطريقة العادية لو الجافاسكربت مش شغال
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && in_array($_POST['action'] ?? '', ['delete', 'delete_json'], true)) {
    $json = ($_POST['action'] ?? '') === 'delete_json';
    $ids = $json ? array_slice(array_filter(array_map('strval', (array)($_POST['ids'] ?? []))), 0, 50) : [(string)($_POST['id'] ?? '')];
    if (!can('posts.manage')) {
        if ($json) { header('Content-Type: application/json'); http_response_code(403); exit(json_encode(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], JSON_UNESCAPED_UNICODE)); }
        redirect(url('posts'));
    }
    $ok = []; $fail = [];
    foreach ($ids as $id) {
        if (!preg_match('/^[\w-]{1,64}$/', $id)) continue;
        try { $z->deletePost($id); log_activity('post.delete', $id); $ok[] = $id; } catch (ZernioException $ex) { $fail[$id] = $ex->getMessage(); }
    }
    if ($json) { header('Content-Type: application/json; charset=utf-8'); exit(json_encode(['ok' => $ok, 'failed' => $fail], JSON_UNESCAPED_UNICODE)); }
    $fail ? flash('error', reset($fail)) : flash('success', t('تم حذف المنشور.'));
    redirect(url('posts', ['status' => $_GET['status'] ?? '']));
}

$status = (string)($_GET['status'] ?? '');
$validStatus = ['', 'scheduled', 'published', 'draft', 'failed', 'partial'];
if (!in_array($status, $validStatus, true)) $status = '';
$view = ($_GET['view'] ?? '') === 'cal' ? 'cal' : 'list';
$pageNo = max(1, (int)($_GET['n'] ?? 1));
$posts = [];
$pages = 1;
$total = null;
$apiError = null;
$accounts = [];

$fixture = getenv('POSTS_FIXTURE') ?: (getenv('UI_FIXTURES') ? getenv('UI_FIXTURES') . '/posts.json' : ''); // بيئة الاختبار فقط
if ($z && $fixture && is_file($fixture)) {
    $fx = json_decode((string)file_get_contents($fixture), true) ?: [];
    foreach ($fx['posts'] ?? [] as $p) if ($status === '' || ($p['status'] ?? '') === $status) $posts[] = $p;
    foreach ($fx['accounts'] ?? [] as $acc) $accounts[$acc['_id']] = ['name' => $acc['displayName'] ?? '', 'pic' => '', 'plat' => $acc['platform'] ?? ''];
    $total = count($posts);
    if ($view === 'list' && ($per = (int)($fx['per_page'] ?? 0)) > 0) { $pages = (int)ceil($total / $per); $posts = array_slice($posts, ($pageNo - 1) * $per, $per); } // اختبار الصفحات
} elseif ($z) {
    try {
        if ($view === 'cal') {
            // التقويم: المجدول (الجاي) + آخر المنشور، لحد 100 من كل نوع
            $tzc = new DateTimeZone(site_timezone());
            $calFrom = (new DateTime('first day of this month', $tzc))->setTime(0, 0)->modify('-7 day');
            $calTo = (new DateTime('last day of next month', $tzc))->setTime(23, 59, 59)->modify('+7 day');
            $a = ps_cal_scheduled($z, $calFrom->getTimestamp(), $calTo->getTimestamp());
            $b = $status === 'scheduled' ? [] : ($z->posts(['status' => $status ?: null, 'limit' => 100, 'sortBy' => 'created-desc', 'profileId' => zernio_profile()])['posts'] ?? []);
            $seen = [];
            foreach (array_merge($a, $b) as $p) { $id = (string)($p['_id'] ?? ''); if ($id === '' || isset($seen[$id])) continue; $seen[$id] = 1; if ($status === '' || ($p['status'] ?? '') === $status) $posts[] = $p; }
        } else {
            $r = $z->posts(['status' => $status ?: null, 'page' => $pageNo, 'limit' => 24, 'sortBy' => $status === 'scheduled' ? 'scheduled-asc' : 'created-desc', 'profileId' => zernio_profile()]);
            $posts = $r['posts'] ?? [];
            $pages = max(1, (int)($r['pagination']['pages'] ?? 1));
            $total = isset($r['pagination']['total']) ? (int)$r['pagination']['total'] : null;
        }
        foreach ($z->accounts(zernio_profile()) as $acc) $accounts[$acc['_id']] = ['name' => $acc['displayName'] ?? $acc['username'] ?? '', 'pic' => $acc['profilePicture'] ?? '', 'plat' => $acc['platform'] ?? ''];
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

// أرقام التفاعل للمنشورات اللي اتنشرت (من التحليلات، قراءة بس، متخزنة 10 دقايق)
$metrics = [];
$fxa = getenv('UI_FIXTURES') ? getenv('UI_FIXTURES') . '/analytics_posts.json' : '';
$apiRows = [];
if ($fxa && is_file($fxa)) $apiRows = (array)(json_decode((string)file_get_contents($fxa), true)['posts'] ?? []);
elseif ($z && !$apiError && module_enabled('analytics') && can('analytics.view') && function_exists('an_posts')) {
    $ck = 'ps_metrics_' . (int)ws('id');
    if (isset($_SESSION[$ck]) && $_SESSION[$ck]['t'] > time() - 600 && !isset($_GET['refresh'])) $apiRows = $_SESSION[$ck]['d'];
    else {
        try { $apiRows = (array)(an_posts($z, ['limit' => 100, 'page' => 1, 'sortBy' => 'date', 'order' => 'desc', 'profileId' => zernio_profile()])['posts'] ?? []); } catch (Throwable $e) { $apiRows = []; }
        $_SESSION[$ck] = ['t' => time(), 'd' => array_map(function ($r) { return ['_id' => $r['_id'] ?? '', 'platforms' => array_map(function ($pl) { return ['platformPostId' => $pl['platformPostId'] ?? '']; }, (array)($r['platforms'] ?? [])), 'platformPostId' => $r['platformPostId'] ?? '', 'analytics' => (array)($r['analytics'] ?? [])]; }, $apiRows)];
    }
}
foreach ($apiRows as $r) {
    $m = (array)($r['analytics'] ?? []); if (!$m) continue;
    $row = ['imp' => (int)($m['impressions'] ?? $m['views'] ?? 0), 'reach' => (int)($m['reach'] ?? 0), 'likes' => (int)($m['likes'] ?? 0), 'comments' => (int)($m['comments'] ?? 0), 'shares' => (int)($m['shares'] ?? 0), 'saves' => (int)($m['saves'] ?? 0)];
    $row['eng'] = $row['likes'] + $row['comments'] + $row['shares'] + $row['saves'];
    $row['rate'] = $row['imp'] > 0 ? round($row['eng'] / $row['imp'] * 100, 1) : null;
    $keys = array_filter(array_merge([(string)($r['_id'] ?? ''), (string)($r['platformPostId'] ?? '')], array_map(function ($pl) { return (string)($pl['platformPostId'] ?? ''); }, (array)($r['platforms'] ?? []))));
    foreach ($keys as $k) $metrics[$k] = $row;
}

$tagsMap = (function_exists('an_tags_available') && an_tags_available()) ? an_tags_map() : [];
$egMap = []; try { $egq = db()->prepare('SELECT post_id, every_days FROM post_evergreen WHERE workspace_id = ?'); $egq->execute([(int)ws('id')]); foreach ($egq->fetchAll() as $r) $egMap[$r['post_id']] = (int)$r['every_days']; } catch (Throwable $e) {}
$counts = [];
if ($z && $fixture && is_file($fixture)) {
    $counts = ['scheduled' => 0, 'failed' => 0, 'draft' => 0, 'published' => 0];
    foreach ((array)(json_decode((string)file_get_contents($fixture), true)['posts'] ?? []) as $p) { $k = (string)($p['status'] ?? ''); $counts[$k] = ($counts[$k] ?? 0) + 1; }
} elseif ($z && !$apiError) {
    $ck = 'ps_counts_' . (int)ws('id');
    if (isset($_SESSION[$ck]) && $_SESSION[$ck]['t'] > time() - 120 && !isset($_GET['refresh'])) $counts = $_SESSION[$ck]['d'];
    else {
        foreach (['scheduled', 'failed', 'draft', 'published'] as $k) {
            try { $r = $z->posts(['status' => $k, 'limit' => 1, 'page' => 1, 'profileId' => zernio_profile()]); $counts[$k] = (int)($r['pagination']['total'] ?? count($r['posts'] ?? [])); } catch (ZernioException $ex) { /* اختياري */ }
        }
        $_SESSION[$ck] = ['t' => time(), 'd' => $counts];
    }
}

render($view === 'cal' ? t('التقويم') : t('المنشورات'), $view === 'cal' ? 'calendar' : 'posts', function () use ($z, $posts, $pages, $pageNo, $status, $view, $total, $apiError, $accounts, $counts, $metrics, $tagsMap) {
    if (!$z) { no_api_box(); return; }
    $tabs = ['' => t('الكل'), 'scheduled' => t('مجدول'), 'published' => t('تم النشر'), 'draft' => t('مسودات'), 'failed' => t('فشل')];
    $stCls = ['scheduled' => 'bg-brand-50 text-brand-700', 'published' => 'bg-lime-100 text-lime-700', 'draft' => 'bg-ink-100 text-ink-600', 'failed' => 'bg-red-100 text-red-700', 'partial' => 'bg-amber-100 text-amber-800', 'publishing' => 'bg-cyan-100 text-cyan-700', 'pending_approval' => 'bg-amber-100 text-amber-800', 'cancelled' => 'bg-ink-100 text-ink-500'];
    $plats = []; foreach ($posts as $p) foreach ($p['platforms'] ?? [] as $pl) { $k = (string)($pl['platform'] ?? ''); if ($k !== '') $plats[$k] = ($plats[$k] ?? 0) + 1; }
    $data = [];
    $pdata = [];
    ?>
    <?php // شريط الحالات: العدد + الفلتر في نفس المكان (بدل الكروت والتابات والتنبيه المكرر)
      $stMeta = ['' => [t('الكل'), 'layers', 'text-ink-900'], 'scheduled' => [t('مجدول'), 'calendar', 'text-brand-700'], 'published' => [t('تم النشر'), 'check', 'text-lime-700'], 'draft' => [t('مسودات'), 'file', 'text-ink-700'], 'failed' => [t('فشل'), 'alert', 'text-red-600']];
      $allN = $counts ? array_sum(array_map('intval', $counts)) : null; ?>
    <div class="flex gap-1.5 mb-3 overflow-x-auto pb-1 -mx-1 px-1" role="tablist" aria-label="<?= e(t('الحالة')) ?>">
      <?php foreach ($stMeta as $k => [$lbl, $ic, $col]): $n = $k === '' ? $allN : ($counts[$k] ?? null); $on = $status === $k; $bad = $k === 'failed' && (int)$n > 0; ?>
        <a href="<?= e(url('posts', array_filter(['status' => $k, 'view' => $view === 'cal' ? 'cal' : '']))) ?>" role="tab" aria-selected="<?= $on ? 'true' : 'false' ?>"
           class="group flex-1 min-w-[120px] rounded-2xl border px-3.5 py-2.5 transition hover:-translate-y-0.5 hover:shadow-md <?= $on ? 'border-brand-400 bg-brand-50 ring-2 ring-brand-500/15' : ($bad ? 'border-red-200 bg-red-50/70' : 'border-ink-200 bg-white') ?>">
          <span class="flex items-center gap-1.5 text-xs <?= $on ? 'text-brand-700 font-semibold' : 'text-ink-500' ?>"><?= ui_icon($ic, 'w-4 h-4') ?><?= e($lbl) ?></span>
          <span class="flex items-baseline gap-2 mt-0.5"><b class="text-xl tabular-nums <?= $bad ? 'text-red-600' : $col ?>"><?= $n === null ? '—' : (int)$n ?></b><?php if ($bad && !$on): ?><span class="text-[11px] font-semibold text-red-700"><?= e(t('اعرضها وأصلحها')) ?></span><?php endif; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <div class="relative flex-1 min-w-[12rem]">
        <span class="absolute inset-y-0 start-3 grid place-items-center text-ink-400 pointer-events-none"><?= ui_icon('search', 'w-4 h-4') ?></span>
        <input type="search" id="ps-q" class="input !mt-0 !h-10 !py-0 ps-9 text-sm" placeholder="<?= e(t('ابحث في المنشورات...')) ?> (/)" dir="auto">
      </div>
      <div class="tb p-0.5 gap-0.5" id="ps-view">
        <a href="<?= e(url('posts', array_filter(['status' => $status]))) ?>" class="h-9 px-2.5 rounded-lg flex items-center gap-1.5 text-xs <?= $view === 'list' ? 'bg-white shadow-sm text-ink-900 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>" title="V"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 5h16v6H4zM4 15h16v4H4z"/></svg><?= e(t('قائمة')) ?></a>
        <a href="<?= e(url('posts', array_filter(['status' => $status, 'view' => 'cal']))) ?>" class="h-9 px-2.5 rounded-lg flex items-center gap-1.5 text-xs <?= $view === 'cal' ? 'bg-white shadow-sm text-ink-900 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>" title="V"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 6h16v14H4zM4 10h16M9 3v4M15 3v4"/></svg><?= e(t('تقويم')) ?></a>
      </div>
 <?php if ($view === 'list'): ?>
      <div class="tb p-0.5 gap-0.5" role="group" aria-label="<?= e(t('طريقة العرض')) ?>">
        <button type="button" data-dens="cards" class="ps-dens h-9 px-2.5 rounded-lg text-xs" title="<?= e(t('كروت')) ?>"><?= ui_icon('grid', 'w-4 h-4') ?></button>
        <button type="button" data-dens="rows" class="ps-dens h-9 px-2.5 rounded-lg text-xs" title="<?= e(t('مضغوط')) ?>"><?= ui_icon('list', 'w-4 h-4') ?></button>
      </div>
 <select id="ps-sort" class="tb !h-10 px-2 text-xs" aria-label="<?= e(t('ترتيب')) ?>">
        <option value=""><?= e(t('الترتيب العادي')) ?></option>
        <option value="new"><?= e(t('الأحدث')) ?></option>
        <option value="old"><?= e(t('الأقدم')) ?></option>
        <?php if ($metrics): ?><option value="eng"><?= e(t('الأعلى تفاعل')) ?></option><?php endif; ?>
      </select>
 <?php endif; ?>
      <button type="button" popovertarget="ps-more" class="tb h-10 px-3 gap-1.5 text-xs" title="<?= e(t('أدوات أكتر')) ?>"><?= ui_icon('dots', 'w-4 h-4') ?><span class="hidden sm:inline"><?= e(t('أدوات')) ?></span></button>
      <div id="ps-more" popover class="m-0 w-64 rounded-2xl border border-ink-200 bg-white p-1.5 shadow-2xl text-sm">
        <a href="<?= e(url('bulk')) ?>" data-modal="<?= e(url('bulk')) ?>" data-modal-reload class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-ink-50"><?= ui_icon('upload', 'w-4 h-4 text-ink-500') ?><span><?= e(t('رفع منشورات من ملف CSV')) ?></span></a>
        <a href="<?= e(url('queue')) ?>" data-modal="<?= e(url('queue')) ?>" class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-ink-50"><?= ui_icon('clock', 'w-4 h-4 text-ink-500') ?><span><?= e(t('مواعيد الطابور')) ?></span></a>
        <?php if ($view === 'list'): ?><button type="button" id="ps-csv" class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-ink-50 text-start"><?= ui_icon('download', 'w-4 h-4 text-ink-500') ?><span><?= e(t('نزّل المنشورات اللي ظاهرة CSV')) ?></span></button><?php endif; ?>
        <div class="my-1 border-t border-ink-100"></div>
        <button type="button" id="ps-help" class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-ink-50 text-start"><span class="w-4 text-center font-semibold text-ink-500">?</span><span><?= e(t('اختصارات الكيبورد')) ?></span></button>
      </div>
      <a href="<?= e(url('compose')) ?>" class="btn-primary"><?= ui_icon('plus', 'w-4 h-4') ?><?= e(t('منشور جديد')) ?></a>
    </div>
    <?php if (count($plats) > 1): ?>
      <div class="flex flex-wrap items-center gap-1.5 mb-4" id="ps-pf">
        <?php foreach ($plats as $pk => $pn): ?><button type="button" data-pf="<?= e($pk) ?>" class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-600 hover:border-brand-300 transition"><?= platform_icon($pk, 'w-3.5 h-3.5') ?><?= e(platform_label($pk)) ?> <span class="tabular-nums text-ink-400"><?= $pn ?></span></button><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php
      $accSeen = []; foreach ($posts as $p0) foreach ((array)($p0['platforms'] ?? []) as $pl0) { $a0 = pub_acc_id($pl0['accountId'] ?? ''); if ($a0 !== '' && isset($accounts[$a0])) $accSeen[$a0] = ($accSeen[$a0] ?? 0) + 1; }
      $tagSeen = []; foreach ($tagsMap as $tgs) foreach ($tgs as $tg) $tagSeen[$tg] = ($tagSeen[$tg] ?? 0) + 1; // كل منشورات الشركة، مش الصفحة دي بس
      arsort($tagSeen);
    ?>
    <?php if ($view === 'list' && (count($accSeen) > 1 || $tagSeen)): ?>
      <div class="flex flex-wrap items-center gap-1.5 mb-4 -mt-2" id="ps-af">
        <?php if (count($accSeen) > 1): foreach ($accSeen as $aid => $n): $ac = $accounts[$aid]; ?>
          <button type="button" data-acc="<?= e($aid) ?>" class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-600 hover:border-brand-300 transition"><?= platform_icon((string)$ac['plat'], 'w-3.5 h-3.5') ?><?= e($ac['name'] ?: '—') ?> <span class="tabular-nums text-ink-400"><?= $n ?></span></button>
        <?php endforeach; endif; ?>
        <?php foreach (array_slice($tagSeen, 0, 15, true) as $tg => $n): ?>
          <button type="button" data-tag="<?= e($tg) ?>" class="inline-flex items-center gap-1 rounded-full border border-violet-200 bg-violet-50/50 px-2.5 py-1 text-xs text-violet-700 hover:border-violet-400 transition">#<?= e($tg) ?> <span class="tabular-nums text-violet-400"><?= $n ?></span></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($apiError) { error_box($apiError); return; } ?>

    <?php if (!$posts): ?>
      <div class="card p-12 text-center">
        <div class="w-16 h-16 mx-auto mb-4 rounded-3xl bg-gradient-to-br from-brand-600/10 to-cyan-500/10 text-brand-600 flex items-center justify-center"><svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg></div>
        <?php $em = ['failed' => [t('مفيش منشورات فشلت 🎉'), t('كل منشوراتك اتنشرت من غير مشاكل.')], 'scheduled' => [t('مفيش منشورات مجدولة'), t('جدول منشور، أو ارفع كمية مرة واحدة من ملف.')], 'draft' => [t('مفيش مسودات'), t('أي منشور تحفظه كمسودة هيظهر هنا.')]][$status] ?? [t('مفيش منشورات هنا.'), '']; ?>
        <p class="font-medium text-ink-900"><?= e($em[0]) ?></p>
        <?php if ($em[1]): ?><p class="text-sm text-ink-500 mt-1"><?= e($em[1]) ?></p><?php endif; ?>
        <div class="flex flex-wrap justify-center gap-2 mt-4">
          <a href="<?= e(url('compose')) ?>" class="btn-primary"><?= e(t('منشور جديد')) ?></a>
          <a href="<?= e(url('bulk')) ?>" class="btn-ghost"><?= e(t('رفع بالجملة')) ?></a>
        </div>
      </div>
    <?php else: ?>

    <?php if ($view === 'list'): ?>
      <div id="ps-bar" class="hidden sticky top-20 z-20 mb-3 flex flex-wrap items-center gap-2 rounded-2xl border border-brand-200 bg-white/95 backdrop-blur shadow-lg px-4 py-2.5">
        <b class="text-sm text-ink-900" id="ps-n">0</b><span class="text-xs text-ink-500"><?= e(t('محدد')) ?></span>
        <label class="text-xs text-ink-600 flex items-center gap-1 cursor-pointer"><input type="checkbox" id="ps-all"> <?= e(t('الكل')) ?></label>
        <?php if (can('posts.manage')): ?>
          <span class="h-5 w-px bg-ink-200"></span>
          <span class="text-xs text-ink-500"><?= e(t('أجّل')) ?></span>
          <input type="number" id="ps-sh-n" value="1" min="-365" max="365" class="input !mt-0 !h-8 !py-0 text-xs !w-16">
          <select id="ps-sh-u" class="input !mt-0 !h-8 !py-0 text-xs !w-auto"><option value="d"><?= e(t('يوم')) ?></option><option value="h"><?= e(t('ساعة')) ?></option></select>
          <button type="button" id="ps-shift" class="btn-ghost !py-1.5 !text-xs"><?= e(t('طبّق')) ?></button>
          <span class="h-5 w-px bg-ink-200"></span>
          <button type="button" id="ps-bretry" class="btn-ghost !py-1.5 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4"/></svg> <?= e(t('أعد محاولة الفاشل')) ?></button>
        <?php endif; ?>
 <?php if (can('posts.create') && $tagsMap !== null && function_exists('an_tags_available') && an_tags_available()): ?>
          <span class="h-5 w-px bg-ink-200"></span>
          <input id="ps-btag" class="input !mt-0 !h-8 !py-0 text-xs !w-28" placeholder="<?= e(t('تاج…')) ?>" list="ps-taglist">
          <button type="button" id="ps-btag-go" class="btn-ghost !py-1.5 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12V4h8l10 10-8 8zM7.5 8.5h.01"/></svg> <?= e(t('ضيف تاج')) ?></button>
          <datalist id="ps-taglist"><?php foreach (array_keys($tagSeen) as $tg): ?><option value="<?= e($tg) ?>"><?php endforeach; ?></datalist>
        <?php endif; ?>
        <?php if (can('posts.create')): ?>
          <span class="h-5 w-px bg-ink-200"></span>
          <span class="text-xs text-ink-500"><?= e(t('انشرهم تاني من')) ?></span>
          <input type="datetime-local" id="ps-rp-at" class="input !mt-0 !h-8 !py-0 text-xs !w-auto" dir="ltr">
          <span class="text-xs text-ink-500"><?= e(t('كل')) ?></span>
          <input type="number" id="ps-rp-gap" value="2" min="0" max="60" class="input !mt-0 !h-8 !py-0 text-xs !w-14">
          <span class="text-xs text-ink-500"><?= e(t('يوم')) ?></span>
          <button type="button" id="ps-rp-go" class="btn-ghost !py-1.5 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4"/></svg> <?= e(t('جدول')) ?></button>
        <?php endif; ?>
        <?php if (can('posts.create')): ?><button type="button" id="ps-bdup" class="btn-ghost !py-1.5 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8h11v12H8zM5 16V4h11"/></svg> <?= e(t('نسخ كمسودات')) ?></button><?php endif; ?>
        <?php if (can('posts.manage')): ?><button type="button" id="ps-brep" class="btn-ghost !py-1.5 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4"/></svg> <?= e(t('استبدال')) ?></button><?php endif; ?>
        <?php if (can('posts.manage')): ?><button type="button" id="ps-bulkdel" class="btn-ghost !py-1.5 !text-xs text-red-600"><?= e(t('حذف المحدد')) ?></button><?php endif; ?>
        <button type="button" id="ps-clear" class="ms-auto link text-xs !text-ink-500"><?= e(t('إلغاء التحديد')) ?></button>
      </div>
      <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4" id="ps-grid">
        <?php
        $pm = function ($p) use ($metrics) {
            if (isset($metrics[$p['_id']])) return $metrics[$p['_id']];
            foreach ((array)($p['platforms'] ?? []) as $pl) if (!empty($pl['platformPostId']) && isset($metrics[$pl['platformPostId']])) return $metrics[$pl['platformPostId']];
            return null;
        };
        $engs = []; foreach ($posts as $p0) { $m0 = $pm($p0); if ($m0 && $m0['eng'] > 0) $engs[] = $m0['eng']; }
        rsort($engs); $topCut = $engs ? $engs[max(0, (int)ceil(count($engs) * 0.2) - 1)] : PHP_INT_MAX;
        ?>
        <?php $notesMap = ps_notes(array_values(array_filter(array_map(function ($x) { return (string)($x['_id'] ?? ''); }, $posts)))); ?>
        <?php $lastDay = null; $tzx = new DateTimeZone(site_timezone()); $today = (new DateTime('now', $tzx))->format('Y-m-d');
        foreach ($posts as $p):
            $st = (string)($p['status'] ?? ''); [$label] = status_label($st);
            $w0 = $p['scheduledFor'] ?? $p['publishedAt'] ?? $p['createdAt'] ?? null; $dk = '';
            if ($w0) { try { $dd = new DateTime((string)$w0); $dd->setTimezone($tzx); $dk = $dd->format('Y-m-d'); } catch (Throwable $e) { $dk = ''; } }
            // التجميع بالأيام بس في التابات المترتبة بالتاريخ
            if (in_array($status, ['scheduled', 'published'], true) && $dk !== '' && $dk !== $lastDay) { $lastDay = $dk;
                $diff = (int)round((strtotime($dk) - strtotime($today)) / 86400);
                $dl = [0 => t('النهارده'), 1 => t('بكرة'), -1 => t('امبارح')][$diff] ?? weekday_name((int)date('w', strtotime($dk)));
                echo '<div class="ps-day col-span-full flex items-center gap-2 pt-2 text-xs font-semibold text-ink-500"><span class="' . ($diff === 0 ? 'text-brand-700' : '') . '">' . e($dl) . '</span><span class="text-ink-300 font-normal" dir="ltr">' . e(date('d/m/Y', strtotime($dk))) . '</span><span class="flex-1 h-px bg-ink-200"></span></div>';
            }
            $m = $p['mediaItems'][0] ?? null; $mt = $m['type'] ?? '';
            $when = $p['scheduledFor'] ?? $p['publishedAt'] ?? $p['createdAt'] ?? null;
            $pls = $p['platforms'] ?? []; $accIds = []; $errs = [];
            foreach ($pls as $pl) { $aid = is_array($pl['accountId'] ?? null) ? ($pl['accountId']['_id'] ?? '') : ($pl['accountId'] ?? ''); $accIds[] = $aid; if (($pl['status'] ?? '') === 'failed' && !empty($pl['errorMessage'])) $errs[] = platform_label($pl['platform'] ?? '') . ': ' . $pl['errorMessage']; }
            $pf = implode(' ', array_unique(array_map(function ($pl) { return (string)($pl['platform'] ?? ''); }, $pls)));
            ?>
          <?php
            $plv = [];
            foreach ($pls as $pl) $plv[] = ['pf' => (string)($pl['platform'] ?? ''), 'st' => (string)($pl['status'] ?? ''), 'url' => (string)($pl['platformPostUrl'] ?? ''), 'err' => (string)($pl['errorMessage'] ?? ''), 'hint' => !empty($pl['errorMessage']) ? ps_fail_hint((string)$pl['errorMessage']) : '',
                'acc' => $accounts[pub_acc_id($pl['accountId'] ?? '')]['name'] ?? ''];
            $pdata[$p['_id']] = ['txt' => (string)($p['content'] ?? ''), 'st' => $st, 'stl' => $label, 'at' => (string)$when, 'sched' => (string)($p['scheduledFor'] ?? ''),
                'media' => array_values(array_filter(array_map(function ($m) { return ['t' => (string)($m['type'] ?? ''), 'u' => (string)($m['url'] ?? '')]; }, (array)($p['mediaItems'] ?? [])), function ($m) { return $m['u'] !== ''; })),
                'pl' => $plv, 'dup' => url('compose', ['dup_text' => mb_substr((string)($p['content'] ?? ''), 0, 4000), 'dup_acc' => implode(',', array_filter($accIds))])];
          ?>
          <?php $mx = $pm($p); if ($mx) $pdata[$p['_id']]['m'] = $mx; ?>
          <?php $pnotes = $notesMap[$p['_id']] ?? []; $pdata[$p['_id']]['notes'] = $pnotes; ?>
          <?php $ptags = $tagsMap[$p['_id']] ?? []; $pdata[$p['_id']]['tags'] = $ptags; $pdata[$p['_id']]['eg'] = $egMap[$p['_id']] ?? 0; $pdata[$p['_id']]['acc'] = array_values(array_filter($accIds)); ?>
          <article data-tags="<?= e(implode('|', $ptags)) ?>" data-acc="<?= e(implode(' ', array_filter($accIds))) ?>" data-eng="<?= $mx ? (int)$mx['eng'] : -1 ?>" data-t="<?= e((string)($when ?? '')) ?>" class="ps-card group relative card p-0 overflow-hidden flex flex-col transition hover:shadow-lg hover:-translate-y-0.5" data-id="<?= e($p['_id']) ?>" data-pf="<?= e($pf) ?>" data-q="<?= e(mb_strtolower(mb_substr((string)($p['content'] ?? ''), 0, 1000))) ?>">
            <?php if ($m && !empty($m['url'])): ?>
              <a href="<?= e(url('post_edit', ['id' => $p['_id']])) ?>" class="ps-media relative block aspect-[16/9] bg-ink-100 overflow-hidden">
                <?php if ($mt === 'video'): ?><video src="<?= e($m['url']) ?>" class="w-full h-full object-cover" muted preload="metadata"></video><span class="absolute inset-0 flex items-center justify-center"><span class="w-10 h-10 rounded-full bg-ink-900/60 text-[#fff] flex items-center justify-center"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4l13 8-13 8z"/></svg></span></span>
                <?php elseif ($mt === 'document'): ?><span class="w-full h-full flex items-center justify-center text-sm text-ink-500"><?= e(t('ملف')) ?></span>
                <?php else: ?><img src="<?= e($m['url']) ?>" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" alt="" referrerpolicy="no-referrer"><?php endif; ?>
                <?php if (count($p['mediaItems']) > 1): ?><span class="absolute bottom-2 end-2 rounded-md bg-ink-900/70 text-[#fff] text-[10px] px-1.5 py-0.5">+<?= count($p['mediaItems']) - 1 ?></span><?php endif; ?>
              </a>
            <?php endif; ?>
            <div class="p-4 flex-1 flex flex-col">
              <div class="flex items-center gap-2">
                <label class="shrink-0 cursor-pointer flex"><input type="checkbox" class="ps-sel w-4 h-4 rounded opacity-40 group-hover:opacity-100 checked:opacity-100 focus:opacity-100 transition" aria-label="<?= e(t('تحديد')) ?>"></label>
                <span class="rounded-md px-1.5 py-0.5 text-[10px] font-medium <?= $stCls[$st] ?? 'bg-ink-100 text-ink-600' ?>"><?= e($label) ?></span>
                <span class="ps-nc text-[10px] rounded-full bg-amber-100 text-amber-800 px-1.5 py-0.5 <?= $pnotes ? '' : 'hidden' ?>" title="<?= e(t('ملاحظات الفريق')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6"/></svg> <b><?= count($pnotes) ?></b></span>
                <span class="ps-when text-[11px] text-ink-500 tabular-nums" data-at="<?= e((string)$when) ?>" data-st="<?= e($st) ?>" title="<?= e(fmt_date($when)) ?>"><?= e(fmt_date($when)) ?></span>
                <div class="ms-auto flex -space-x-1 rtl:space-x-reverse">
                  <?php foreach ($pls as $pl): $u = $pl['platformPostUrl'] ?? null; $ic = platform_icon((string)($pl['platform'] ?? ''), 'w-[18px] h-[18px]', 'badge'); ?>
                    <?php if ($u): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener" class="rounded-full ring-2 ring-white hover:scale-110 transition" title="<?= e(t('فتح على %s', platform_label((string)($pl['platform'] ?? '')))) ?>"><?= $ic ?></a>
                    <?php else: ?><span class="rounded-full ring-2 ring-white <?= ($pl['status'] ?? '') === 'failed' ? 'opacity-40' : '' ?>" title="<?= e(platform_label((string)($pl['platform'] ?? ''))) ?>"><?= $ic ?></span><?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>
              <a href="<?= e(url('post_edit', ['id' => $p['_id']])) ?>" data-open="<?= e($p['_id']) ?>" class="ps-open mt-2.5 block flex-1"><p class="ps-txt text-sm text-ink-800 whitespace-pre-line line-clamp-4" dir="auto"><?= ($p['content'] ?? '') !== '' ? e(mb_substr((string)$p['content'], 0, 600)) : '<span class="text-ink-400 italic">' . e(t('(بدون نص)')) . '</span>' ?></p></a>
              <div class="ps-tags mt-2 flex flex-wrap gap-1 <?= $ptags ? '' : 'hidden' ?>"><?php foreach ($ptags as $tg): ?><span class="rounded-full bg-violet-50 text-violet-700 border border-violet-200 text-[10px] px-2 py-0.5">#<?= e($tg) ?></span><?php endforeach; ?></div>
              <?php if ($mx): $nf = function ($n) { return $n >= 1000 ? round($n / 1000, $n >= 10000 ? 0 : 1) . 'K' : (string)$n; }; ?>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-ink-500 tabular-nums">
                  <?php if ($mx['eng'] >= $topCut && $mx['eng'] > 0): ?><span class="rounded-md bg-orange-100 text-orange-800 px-1.5 py-0.5 font-medium"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a7 7 0 007-7c0-4-3-6-4-9-1 2-2 3-3 3 0-2-1-4-3-5 0 4-4 6-4 11a7 7 0 007 7zM12 21a3 3 0 003-3c0-2-1.5-3-3-5-1.5 2-3 3-3 5a3 3 0 003 3z"/></svg> <?= e(t('الأعلى تفاعل')) ?></span><?php endif; ?>
                  <?php if ($mx['imp']): ?><span title="<?= e(t('مرات الظهور')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z"/></svg> <?= e($nf($mx['imp'])) ?></span><?php endif; ?>
                  <span title="<?= e(t('إعجاب')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-8-5-8-11a4.5 4.5 0 018-2.8A4.5 4.5 0 0120 9c0 6-8 11-8 11z"/></svg> <?= e($nf($mx['likes'])) ?></span>
                  <span title="<?= e(t('تعليق')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg> <?= e($nf($mx['comments'])) ?></span>
                  <?php if ($mx['shares']): ?><span title="<?= e(t('مشاركة')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8M18 14v5H5V6h5"/></svg> <?= e($nf($mx['shares'])) ?></span><?php endif; ?>
                  <?php if ($mx['rate'] !== null): ?><span class="ms-auto font-medium <?= $mx['rate'] >= 3 ? 'text-lime-700' : 'text-ink-600' ?>" title="<?= e(t('نسبة التفاعل')) ?>"><?= e($mx['rate']) ?>%</span><?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ($errs): ?><details class="mt-2 rounded-xl bg-red-50 border border-red-200 px-3 py-2"><summary class="cursor-pointer text-[11px] font-medium text-red-700"><?= e(t('سبب الفشل')) ?></summary><?php foreach ($errs as $er): ?><p class="text-[11px] text-red-700 mt-1 break-words"><?= e(mb_substr($er, 0, 300)) ?></p><?php endforeach; ?></details><?php endif; ?>
              <div class="flex items-center gap-1 mt-3 pt-3 border-t border-ink-100 text-xs">
                <a href="<?= e(url('post_edit', ['id' => $p['_id']])) ?>" class="rounded-lg px-2 py-1 text-brand-700 hover:bg-brand-50 font-medium"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4"/></svg> <?= e(t('تعديل')) ?></a>
                <?php if (in_array($st, ['failed', 'partial'], true) && can('posts.manage')): ?><button type="button" class="ps-retry rounded-lg px-2 py-1 text-red-700 bg-red-50 hover:bg-red-100 font-medium"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4"/></svg> <?= e(t('أعد المحاولة')) ?></button><?php endif; ?>
                <?php foreach ($pls as $pl) { $bu = ($pl['status'] ?? '') === 'published' ? ads_boost_url((string)($pl['platform'] ?? ''), (string)(is_array($pl['accountId'] ?? null) ? ($pl['accountId']['_id'] ?? '') : ($pl['accountId'] ?? '')), (string)($pl['platformPostId'] ?? '')) : null; if ($bu) { ?><a href="<?= e($bu) ?>" class="rounded-lg px-2 py-1 text-amber-800 hover:bg-amber-50 font-medium inline-flex items-center gap-1" title="<?= e(t('روّجه كإعلان على %s', platform_label((string)$pl['platform']))) ?>"><?= ui_icon('zap', 'w-3.5 h-3.5') ?><?= e(t('روّج')) ?></a><?php break; } } ?>
                <a href="<?= e(url('compose', ['dup_text' => mb_substr((string)($p['content'] ?? ''), 0, 4000), 'dup_acc' => implode(',', array_filter($accIds))])) ?>" class="w-8 h-8 grid place-items-center rounded-lg text-ink-500 hover:bg-ink-100 hover:text-ink-900" title="<?= e(t('منشور جديد بنفس النص والحسابات')) ?>" aria-label="<?= e(t('تكرار')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l9 5-9 5-9-5zM3 13l9 5 9-5M3 17l9 5 9-5"/></svg></a>
                <button type="button" class="ps-copy w-8 h-8 grid place-items-center rounded-lg text-ink-500 hover:bg-ink-100 hover:text-ink-900" title="<?= e(t('نسخ النص')) ?>" aria-label="<?= e(t('نسخ النص')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8h11v12H8zM5 16V4h11"/></svg></button>
                <?php if ($st !== 'publishing' && can('posts.manage')): ?><button type="button" class="ps-del ms-auto w-8 h-8 grid place-items-center rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600" title="<?= e(t('حذف')) ?>" aria-label="<?= e(t('حذف')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/></svg></button><?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div id="ps-empty" class="hidden card p-8 text-center text-sm text-ink-500"><?= e(t('مفيش نتائج.')) ?></div>
      <div id="ps-rep" class="fixed inset-0 z-[60] hidden grid place-items-center p-4" aria-hidden="true">
        <div class="absolute inset-0 bg-ink-900/40" data-close></div>
        <div class="relative card p-5 w-full max-w-md space-y-3" role="dialog" aria-modal="true" aria-labelledby="ps-rep-h">
          <h3 id="ps-rep-h" class="font-bold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4"/></svg> <?= e(t('ابحث واستبدل في المنشورات المحددة')) ?></h3>
          <label class="block"><span class="label"><?= e(t('دوّر على')) ?></span><input id="ps-rep-f" class="input" dir="auto" placeholder="01001234567"></label>
          <label class="block"><span class="label"><?= e(t('استبدلها بـ')) ?></span><input id="ps-rep-r" class="input" dir="auto" placeholder="01009876543"></label>
          <div id="ps-rep-info" class="text-xs rounded-lg bg-ink-50 border border-ink-200 p-2 text-ink-600"></div>
          <p class="text-[11px] text-ink-500"><?= e(t('بيشتغل على المجدول والمسودات بس، والنسخ الخاصة بكل منصة كمان. الحروف لازم تبقى زي ما هي بالظبط.')) ?></p>
          <div class="flex gap-2"><button type="button" id="ps-rep-go" class="btn-primary flex-1"><?= e(t('استبدل')) ?></button><button type="button" class="btn-ghost" data-close><?= e(t('إلغاء')) ?></button></div>
        </div>
      </div>
      <div id="ps-dr" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-ink-900/40" data-close></div>
        <div class="absolute top-0 bottom-0 end-0 w-full sm:w-[480px] bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="ps-dr-h">
          <div class="flex items-center gap-2 p-4 border-b border-ink-100">
            <h3 id="ps-dr-h" class="font-semibold text-ink-900 flex-1"><?= e(t('تفاصيل المنشور')) ?></h3>
            <button type="button" id="ps-dr-prev" class="btn-ghost !px-2 !py-1 text-xs" aria-label="prev"><?= is_rtl() ? '→' : '←' ?></button>
            <button type="button" id="ps-dr-next" class="btn-ghost !px-2 !py-1 text-xs" aria-label="next"><?= is_rtl() ? '←' : '→' ?></button>
            <button type="button" class="btn-ghost !px-2 !py-1" data-close aria-label="<?= e(t('اقفل')) ?>">✕</button>
          </div>
          <div id="ps-dr-body" class="flex-1 overflow-y-auto p-4 space-y-4"></div>
          <div id="ps-dr-acts" class="p-3 border-t border-ink-100 flex flex-wrap items-center gap-2"></div>
        </div>
      </div>
      <?php if ($pageNo < $pages): ?>
        <div class="text-center mt-6"><button type="button" id="ps-more" data-next="<?= e(url('posts', array_filter(['status' => $status, 'n' => $pageNo + 1]))) ?>" data-pages="<?= (int)$pages ?>" data-page="<?= (int)$pageNo ?>" class="btn-ghost"><?= e(t('اعرض أكتر')) ?> <span class="text-ink-400 text-xs"><?= e(t('(صفحة %s من %s)', $pageNo + 1, $pages)) ?></span></button></div>
      <?php endif; ?>
      <?php if ($pages > 1): ?>
        <div class="ps-pager flex items-center justify-center gap-1 mt-6">
          <?php if ($pageNo > 1): ?><a class="tb px-3 text-sm" href="<?= e(url('posts', array_filter(['status' => $status, 'n' => $pageNo - 1]))) ?>">‹ <?= e(t('السابق')) ?></a><?php endif; ?>
          <?php for ($i = max(1, $pageNo - 2); $i <= min($pages, $pageNo + 2); $i++): ?><a class="tb w-9 justify-center text-sm <?= $i === $pageNo ? '!bg-brand-600 !text-[#fff]' : '' ?>" href="<?= e(url('posts', array_filter(['status' => $status, 'n' => $i]))) ?>"><?= $i ?></a><?php endfor; ?>
          <?php if ($pageNo < $pages): ?><a class="tb px-3 text-sm" href="<?= e(url('posts', array_filter(['status' => $status, 'n' => $pageNo + 1]))) ?>"><?= e(t('التالي')) ?> ›</a><?php endif; ?>
        </div>
      <?php endif; ?>

    <?php else: // التقويم
        foreach ($posts as $p) { $it = ps_cal_item($p); if ($it) $data[] = $it; }
        ?>
      <div class="card p-0 overflow-hidden" id="ps-cal">
        <div class="flex items-center gap-2 px-4 h-14 border-b border-ink-200">
          <button type="button" data-cal="prev" class="tb w-9 justify-center"><svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button>
          <b class="text-base text-ink-900 min-w-[10rem] text-center" id="cal-title"></b>
          <button type="button" data-cal="next" class="tb w-9 justify-center"><svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button>
          <button type="button" data-cal="today" class="tb px-3 text-xs"><?= e(t('النهارده')) ?></button>
          <div class="tb p-0.5 gap-0.5" role="group" aria-label="<?= e(t('طريقة العرض')) ?>">
            <button type="button" data-calv="month" class="cal-v h-8 px-2.5 rounded-lg text-xs"><?= e(t('شهر')) ?></button>
            <button type="button" data-calv="week" class="cal-v h-8 px-2.5 rounded-lg text-xs"><?= e(t('أسبوع')) ?></button>
          </div>
          <select id="cal-acc" class="input !mt-0 !h-8 !py-0 !w-auto text-xs"><option value=""><?= e(t('كل الحسابات')) ?></option><?php foreach (($accounts ?? []) as $aid => $a): ?><option value="<?= e($aid) ?>"><?= e($a['name']) ?><?= $a['plat'] ? ' · ' . e($a['plat']) : '' ?></option><?php endforeach; ?></select>
          <?php if (can('posts.manage')): ?>
          <details class="relative" id="cal-tools"><summary class="tb px-3 text-xs list-none cursor-pointer"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15a3 3 0 100-6 3 3 0 000 6zM19 12a7 7 0 00-.1-1.2l2-1.5-2-3.4-2.3.9a7 7 0 00-2-1.2L14 3h-4l-.6 2.6a7 7 0 00-2 1.2l-2.3-.9-2 3.4 2 1.5A7 7 0 005 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-.9a7 7 0 002 1.2L10 21h4l.6-2.6a7 7 0 002-1.2l2.3.9 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z"/></svg> <?= e(t('أدوات')) ?></summary>
            <div class="absolute start-0 mt-1 z-40 w-52 rounded-xl border border-ink-200 bg-white shadow-lg p-1 flex flex-col text-sm">
              <button type="button" data-ct="gaps" class="text-start px-3 py-2 rounded-lg hover:bg-ink-50"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 14h6v6h-6zM10 7h4a3 3 0 013 3v4M7 10v4a3 3 0 003 3h4"/></svg> <?= e(t('املا الفراغات')) ?></button>
              <button type="button" data-ct="copyw" class="text-start px-3 py-2 rounded-lg hover:bg-ink-50"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 8h11v12H8zM5 16V4h11"/></svg> <?= e(t('انسخ الأسبوع للجاي')) ?></button>
              <button type="button" data-ct="pause" class="text-start px-3 py-2 rounded-lg hover:bg-ink-50"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14M16 5v14"/></svg> <?= e(t('أجّل فترة')) ?></button>
              <?php if (can('posts.manage')): ?><button type="button" data-ct="goals" class="text-start px-3 py-2 rounded-lg hover:bg-ink-50"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z"/></svg> <?= e(t('أهداف أسبوعية')) ?></button><?php endif; ?>
              <button type="button" data-ct="black" class="text-start px-3 py-2 rounded-lg hover:bg-ink-50"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM5.6 5.6l12.8 12.8"/></svg> <?= e(t('أوقات ممنوعة')) ?></button>
            </div></details>
          <?php endif; ?>
          <div class="ms-auto flex flex-wrap gap-3 text-[11px] text-ink-500">
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-brand-500"></span><?= e(t('مجدول')) ?></span>
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-lime-500"></span><?= e(t('تم النشر')) ?></span>
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span><?= e(t('فشل')) ?></span>
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-ink-400"></span><?= e(t('مسودة')) ?></span>
          </div>
        </div>
        <div id="cal-seasons" class="hidden flex flex-wrap items-center gap-2 px-4 py-2 border-b border-ink-100 text-xs"></div>
        <div id="cal-health" class="hidden flex flex-wrap items-center gap-2 px-4 py-2 border-b border-ink-100 text-xs"></div>
        <div class="grid grid-cols-7 border-b border-ink-100 bg-ink-50 text-[11px] font-medium text-ink-500" id="cal-dow"></div>
        <div class="grid grid-cols-7" id="cal-grid"></div>
        <div id="cal-week" class="hidden overflow-y-auto max-h-[38rem]" dir="ltr"></div>
      </div>
      <div id="cal-dups" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-ink-900/40" data-close></div>
        <div class="absolute top-0 bottom-0 end-0 w-full sm:w-[520px] bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="cal-dups-h">
          <div class="flex items-center gap-2 p-4 border-b border-ink-100"><h3 id="cal-dups-h" class="font-semibold text-ink-900 flex-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4"/></svg> <?= e(t('منشورات بنفس النص')) ?></h3><button type="button" class="btn-ghost !px-2 !py-1" data-close aria-label="<?= e(t('اقفل')) ?>">✕</button></div>
          <p class="px-4 pt-3 text-xs text-ink-500"><?= e(t('المنصات (زي لينكدإن وX) ممكن ترفض أو تقلل وصول المنشور المكرر. خلّي كل نسخة بصياغة مختلفة.')) ?></p>
          <div id="cal-dups-body" class="flex-1 overflow-y-auto p-4 space-y-4"></div>
        </div>
      </div>
      <p class="text-[11px] text-ink-400 mt-2"><?= e(t('التقويم بيجيب كل المنشورات المجدولة في الفترة اللي قدامك، ولما تقلّب بيجيب الباقي. اضغط على يوم فاضي في المستقبل عشان تعمل منشور فيه.')) ?><?php if (can('posts.manage')): ?> <?= e(t('اسحب أي منشور مجدول ليوم تاني عشان تغير ميعاده (نفس الساعة).')) ?><?php endif; ?></p>
    <?php endif; ?>
    <?php endif; ?>
    <?php $diagRows = (array)json_decode((string)setting('ps_diag_' . (int)ws('id'), '[]'), true); if ($diagRows && can('posts.manage')): ?>
      <?php if (is_admin()): ?><details class="card p-4 mt-6 text-sm" id="ps-diag">
        <summary class="cursor-pointer font-semibold text-ink-800">🩺 <?= e(t('تشخيص تغيير المواعيد')) ?> <span class="text-xs text-ink-500 font-normal">(<?= e(t('آخر %s محاولة', count($diagRows))) ?>: <?= e(t('%s نجحت', count(array_filter($diagRows, function ($d) { return !empty($d['ok']); })))) ?>)</span></summary>
        <p class="text-xs text-ink-500 mt-2"><?= e(t('لو تغيير الميعاد مش بيتحفظ، انسخ اللي تحت وابعته للدعم. مفيهوش مفاتيح ولا بيانات سرية.')) ?></p>
        <textarea readonly id="ps-diag-txt" class="input font-mono text-[11px] mt-2" rows="8" dir="ltr"><?= e(json_encode($diagRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></textarea>
        <button type="button" class="btn-ghost !text-xs mt-2" onclick="var t=document.getElementById('ps-diag-txt');t.select();navigator.clipboard&&navigator.clipboard.writeText(t.value);this.textContent='✓'"><?= e(t('انسخ')) ?></button>
      </details><?php endif; ?>
    <?php endif; ?>
    <?php
      $tzc2 = new DateTimeZone(site_timezone());
      $calRange = $view === 'cal' ? [(new DateTime('first day of this month', $tzc2))->modify('-7 day')->format('Y-m-d'), (new DateTime('last day of next month', $tzc2))->modify('+7 day')->format('Y-m-d')] : null;
      if ($view === 'cal' && getenv('UI_FIXTURES')) $calRange = ['2000-01-01', '2000-01-01']; // اختبار: نجبر التحميل بالفترات
    ?>
    <script>window.PSX = <?= json_encode(['calRange' => $calRange, 'calUrl' => url('posts', ['ajax' => 'cal']), 'view' => $view, 'weekStart' => in_array('sun', (array)cx('weekend'), true) ? 1 : 6, 'data' => $data, 'tz' => site_timezone(), 'icons' => array_combine(array_keys($plats) ?: [], array_map(function ($p) { return platform_icon($p, 'w-3 h-3'); }, array_keys($plats))) ?: new stdClass(),
      'compose' => url('compose'), 'edit' => url('post_edit', ['id' => '__ID__']), 'canDel' => can('posts.manage'), 'canDup' => can('posts.create'), 'url' => url('posts'), 'csrf' => csrf_token(),
      'posts' => $pdata ?: new stdClass(), 'plab' => array_combine(array_keys($plats) ?: [], array_map('platform_label', array_keys($plats))) ?: new stdClass(),
      'q' => ['canEg' => can('posts.manage'), 'open' => t('افتح على المنصة'), 'edit' => t('تعديل كامل'), 'retry' => t('أعد المحاولة'), 'dup' => t('نسخة كمسودة'), 'del' => t('حذف'), 'resched' => t('غيّر الميعاد'), 'save' => t('احفظ'), 'editT' => t('عدّل النص'), 'moved' => t('اتنقل لـ %s'), 'moving' => t('بننقله…'), 'pastDay' => t('مينفعش تنقله ليوم فات'),
        'csvH' => ['id', 'status', 'time', 'platforms', 'text', 'links', 'errors', 'impressions', 'likes', 'comments', 'shares', 'engagement_rate'],
        'mImp' => t('مرات الظهور'), 'mLikes' => t('إعجاب'), 'mCom' => t('تعليق'), 'mSh' => t('مشاركة'), 'mRate' => t('نسبة التفاعل'), 'perf' => t('الأداء'),
        'notesL' => t('ملاحظات الفريق'), 'notePh' => t('ملاحظة داخلية للفريق (مش هتتنشر)…'), 'noteAdd' => t('ضيف'), 'canNote' => ps_notes_ok() && can('posts.create'),
        'repost' => t('انشره تاني'), 'egT' => t('♻️ Evergreen: بيتعاد لوحده بصياغة جديدة'), 'egEvery' => t('كل كام يوم'), 'egOn2' => t('شغّل'), 'egOff' => t('وقّف'), 'egSaved' => t('اتحفظ ✓'), 'repostAt' => t('في ميعاد'), 'reposted' => t('اتعمل %s نسخة مجدولة'), 'rpQ' => t('هيتعمل %s نسخة مجدولة من المنشورات المحددة، أول واحدة %s وكل نسخة بعد %s يوم. تكمل؟'), 'pickT' => t('اختار ميعاد'),
        'tagsL' => t('التاجات'), 'tagsPh' => t('مثلاً: رمضان، عروض، فرع الرياض'), 'tagged' => t('اتحط التاج على %s منشور'), 'tagSaved' => t('اتحفظت التاجات'), 'canTag' => function_exists('an_tags_available') && an_tags_available() && can('posts.create'),
        'loading' => t('بنحمّل…'), 'pageOf' => t('(صفحة %s من %s)'), 'keysExtra' => [['j / k', t('المنشور اللي بعده / قبله')], ['Enter', t('افتح المنشور')], ['x', t('حدّد المنشور')]],
        'repInfo' => t('%s من %s منشور محدد فيهم الكلمة (المنشور اللي اتنشر مش هيتغير).'), 'repNone' => t('ولا منشور من المحددين فيه الكلمة دي.'), 'repQ' => t('استبدال في %s منشور؟'), 'replaced' => t('اتعدّل %s منشور'),
        'weekOf' => t('أسبوع %s'), 'calV' => ['month' => t('شهر'), 'week' => t('أسبوع')],
        'calLoading' => t('بنجيب منشورات الفترة دي…'),
        'seasons' => function_exists('ps_seasons') ? ps_seasons(date('Y-m-d', strtotime('-40 days')), date('Y-m-d', strtotime('+400 days'))) : [], 'compose' => url('compose'),
'noKb' => t('الأفكار هتبقى أدق لو ملّيت قاعدة المعرفة (خدماتك وأسعارك وجمهورك).'), 'mkPost' => t('اعمل منشور'), 'ideasT' => t('أفكار الأسبوع'), 'thinking' => t('بنفكّر…'),
        'blackout' => json_decode((string)setting('ps_blackout_' . (int)ws('id'), '[]'), true) ?: [], 'canManage' => can('posts.manage'), 'bulkUrl' => can('posts.create') ? url('bulk', ['drop' => 1]) : '',
        'goals' => function_exists('post_goals') ? (object)post_goals() : (object)[], 'gT' => t('أهداف النشر الأسبوعية'), 'gHint' => t('كام منشور في الأسبوع (من السبت للجمعة) لكل حساب. بنعدّ المنشور والمجدول.'), 'gLbl' => t('🎯 الأسبوع ده'), 'gSaved' => t('اتحفظت الأهداف ✓'),
        'accs' => array_map(function ($a) { return $a['name'] . ($a['plat'] ? ' · ' . $a['plat'] : ''); }, $accounts ?? []),
        'tPause' => t('أجّل فترة'), 'tCopyW' => t('انسخ الأسبوع للجاي'), 'tGaps' => t('املا الفراغات'), 'tBlack' => t('أوقات ممنوعة'), 'tAllAcc' => t('كل الحسابات'),
        'pFrom' => t('من يوم (YYYY-MM-DD):'), 'pTo' => t('لحد يوم (YYYY-MM-DD):'), 'pDays' => t('أجّلهم كام يوم؟'), 'pDone' => t('اتأجل %s منشور، وفشل %s'), 'pNone' => t('مفيش منشورات مجدولة في الفترة دي.'),
        'cwQ' => t('هيتعمل نسخة من %s منشور في الأسبوع ده للأسبوع الجاي في نفس المواعيد. تكمل؟'), 'cwDone' => t('اتنسخ %s منشور للأسبوع الجاي ✓'), 'cwNone' => t('مفيش منشورات مجدولة في الأسبوع ده.'),
        'gNone' => t('مفيش مسودات تتجدول، أو مفيش أيام فاضية في الأسبوعين الجايين.'), 'gQ' => t('هتتجدول %s مسودة في الأيام الفاضية:'), 'gDone' => t('اتجدول %s مسودة ✓'), 'gHour' => (int)(function_exists('best_slots_local') && $z ? ((best_slots_local($z, 1)[0]['hour'] ?? 11)) : 11),
        'bT' => t('أوقات ممنوعة للنشر'), 'bHint' => t('مثلاً صلاة الجمعة أو بعد نص الليل. صفحة الإنشاء بتنبهك لو اخترت وقت منهم، و"املا الفراغات" بيتجنبها.'), 'bAdd' => t('+ قاعدة'), 'bSaved' => t('اتحفظت ✓'), 'bWarn' => t('الوقت ده من الأوقات الممنوعة للنشر'),
        'dows' => [t('الأحد'), t('الاتنين'), t('التلات'), t('الأربع'), t('الخميس'), t('الجمعة'), t('السبت')], 'dropT' => t('سيب ملف Excel/CSV هنا عشان يترفع جماعي'), 'egOn' => t('بيتعاد كل %s يوم'),
        'sPrep' => t('حضّر منشور'), 'sSoon' => t('مواسم جاية'), 'sApprox' => t('التاريخ تقريبي'), 'ideas' => t('أفكار الأسبوع ✦'),
        'hDup' => t('%s منشور نصّه متكرر (%s مجموعة)'), 'hBusy' => t('%s يوم زحمة (أكتر من %s منشور على نفس الحساب)'), 'hLimit' => t('حد الزحمة لكل حساب في اليوم'), 'hOk' => t('الجدول سليم: مفيش نصوص متكررة ولا أيام زحمة'), 'hShow' => t('اعرضهم'), 'hTitle' => t('صحة الجدول'),
        'gN' => t('%s نسخة'), 'vary' => t('اكتبهم بصياغات مختلفة ✦'), 'fast' => t('صياغات سريعة'), 'fastT' => t('بالقواعد من غير ذكاء اصطناعي ولا كريديت: بنغير الافتتاحية وترتيب السطور والختام'), 'varying' => t('بنكتب…'), 'applyV' => t('طبّق الصياغات'), 'varied' => t('اتغيّرت صياغة %s منشور'), 'canAi' => can('ai.use'), 'busyN' => 6,
        'varyNote' => t('راجع كل صياغة وعدّلها لو عاوز. مفيش حاجة هتتغير غير لما تدوس "طبّق".'), 'vurl' => url('posts'),
        'gaps' => t('%s يوم فاضي في الأسبوعين الجايين'), 'noGaps' => t('كل الأيام الجاية فيها منشورات ✓'), 'gapDay' => t('فاضي'),
        'done' => t('تم ✓'), 'some' => t('%s تم · %s فشل'), 'retried' => t('اتعملت إعادة محاولة. الحالة هتتحدّث بعد شوية.'), 'duped' => t('اتعمل %s نسخة كمسودة.'), 'shifted' => t('اتأجّل %s منشور.'),
        'noSel' => t('حدد منشورات الأول'), 'shiftQ' => t('تأجيل %s منشور بـ %s؟ (المنشورات اللي مش مجدولة هتتجاهل)'), 'retryQ' => t('إعادة محاولة نشر %s منشور؟'), 'whenL' => t('الميعاد'), 'pfL' => t('المنصات'), 'reason' => t('السبب'), 'media' => t('الوسائط'), 'reload' => t('بنحدّث الصفحة…'),
        'st' => ['published' => t('اتنشر'), 'failed' => t('فشل'), 'scheduled' => t('مجدول'), 'pending' => t('مستني'), 'publishing' => t('بيتنشر')]],
      'past' => t('من %s'), 'future' => t('بعد %s'), 'min' => t('%s دقيقة'), 'hr' => t('%s ساعة'), 'day' => t('%s يوم'), 'now' => t('دلوقتي'),
      'del' => t('متأكد إنك عاوز تحذف المنشور ده؟'), 'delN' => t('حذف %s منشور؟ مش هتقدر ترجعهم.'), 'deleted' => t('تم حذف المنشور.'), 'copied' => t('اتنسخ'), 'err' => t('حصلت مشكلة في الاتصال. جرب تاني.'),
      'more' => t('+%s كمان'), 'newHere' => t('منشور جديد في اليوم ده'), 'keysTitle' => t('اختصارات الكيبورد'),
      'keys' => [['n', t('منشور جديد')], ['/', t('البحث')], ['v', t('قائمة / تقويم')], ['← →', t('الشهر السابق / التالي (في التقويم)')], ['j / k', t('المنشور اللي بعده / قبله')], ['Enter', t('افتح المنشور')], ['x', t('حدّد المنشور')], ['?', t('الاختصارات')]]], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/posts.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
