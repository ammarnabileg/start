<?php
/* خطة المحتوى بالذكاء الاصطناعي: هدف + عدد + صور مرجعية ← (تحليل بصري ← مواضيع ← منشورات كاملة على دفعات)
   كل خطوة بتتحفظ في القاعدة، فلو النت فصل أو الجهاز قفل الشغل بيكمل من نفس النقطة (من الصفحة أو من الكرون).
   مابيولّدش صور ولا فيديو: بيجهز النصوص والبرومبتات بس. */

const CPL_BATCH = 3;   // منشورات كاملة في كل نداء
const CPL_MAX = 60;

function cpl_plan(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM cp_plans WHERE id = ? AND workspace_id = ?'); $st->execute([$id, (int)ws('id')]);
    return $st->fetch() ?: null;
}

function cpl_plans(): array
{
    $st = db()->prepare('SELECT p.*, (SELECT COUNT(*) FROM cp_items i WHERE i.plan_id = p.id AND i.linkedin IS NOT NULL) AS ready,
        (SELECT COUNT(*) FROM cp_items i WHERE i.plan_id = p.id AND i.status IN ("approved","scheduled","queued","published")) AS approved
        FROM cp_plans p WHERE p.workspace_id = ? ORDER BY p.id DESC LIMIT 100');
    $st->execute([(int)ws('id')]); return $st->fetchAll();
}

function cpl_items(int $planId = 0, array $status = []): array
{
    $sql = 'SELECT * FROM cp_items WHERE workspace_id = ?'; $a = [(int)ws('id')];
    if ($planId) { $sql .= ' AND plan_id = ?'; $a[] = $planId; }
    if ($status) { $sql .= ' AND status IN (' . implode(',', array_fill(0, count($status), '?')) . ')'; $a = array_merge($a, $status); }
    $st = db()->prepare($sql . ' ORDER BY plan_id DESC, seq ASC LIMIT 2000'); $st->execute($a);
    return array_map('cpl_item_row', $st->fetchAll());
}

function cpl_item(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM cp_items WHERE id = ? AND workspace_id = ?'); $st->execute([$id, (int)ws('id')]);
    $r = $st->fetch(); return $r ? cpl_item_row($r) : null;
}

function cpl_item_row(array $r): array
{
    $r['topic'] = json_decode((string)$r['topic'], true) ?: [];
    $r['carousel'] = json_decode((string)($r['carousel'] ?? ''), true) ?: [];
    $r['id'] = (int)$r['id']; $r['plan_id'] = (int)$r['plan_id']; $r['seq'] = (int)$r['seq']; $r['media_id'] = $r['media_id'] !== null ? (int)$r['media_id'] : null;
    return $r;
}

/** إنشاء خطة جديدة (الحالة generating، ومرحلة البداية حسب وجود صور مرجعية) */
function cpl_create(array $in): int
{
    $wid = (int)ws('id');
    $n = max(1, min(CPL_MAX, (int)($in['count'] ?? 10)));
    $refs = array_values(array_filter(array_map('intval', (array)($in['refs'] ?? []))));
    $refs = array_values(array_filter($refs, function ($id) use ($wid) { $m = media_get($wid, $id); return $m && empty($m['deleted_at']) && media_kind((string)$m['mime']) === 'image'; }));
    $goal = trim(mb_substr((string)($in['goal'] ?? ''), 0, 3000));
    if ($goal === '') throw new RuntimeException(t('اكتب هدف الحملة.'));
    $opts = ['lang' => in_array($in['lang'] ?? '', ['ar', 'en', 'both'], true) ? $in['lang'] : 'ar', 'dialect' => mb_substr(trim((string)($in['dialect'] ?? '')), 0, 60),
        'audience' => mb_substr(trim((string)($in['audience'] ?? '')), 0, 600), 'services' => array_slice(array_map(function ($s) { return mb_substr((string)$s, 0, 150); }, (array)($in['services'] ?? [])), 0, 20),
        'tone' => mb_substr(trim((string)($in['tone'] ?? '')), 0, 200), 'cta' => mb_substr(trim((string)($in['cta'] ?? '')), 0, 300), 'avoid' => mb_substr(trim((string)($in['avoid'] ?? '')), 0, 600),
        'video' => !empty($in['video']), 'carousel' => !isset($in['carousel']) || !empty($in['carousel'])];
    $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 150) ?: mb_substr(preg_replace('/\s+/u', ' ', $goal), 0, 60);
    db()->prepare('INSERT INTO cp_plans (workspace_id, user_id, name, goal, cnt, opts, refs, stage, status) VALUES (?,?,?,?,?,?,?,?, "generating")')
        ->execute([$wid, (int)(current_user()['id'] ?? 0), $name, $goal, $n, json_encode($opts, JSON_UNESCAPED_UNICODE), json_encode($refs), $refs ? 'visual' : 'outline']);
    return (int)db()->lastInsertId();
}

function cpl_opts(array $p): array { return (json_decode((string)$p['opts'], true) ?: []) + ['lang' => 'ar', 'dialect' => '', 'audience' => '', 'services' => [], 'tone' => '', 'cta' => '', 'avoid' => '', 'video' => true, 'carousel' => true]; }

/** قواعد الكتابة الثابتة (من برومبت المستخدم، مختصرة) */
function cpl_rules(array $p): string
{
    $o = cpl_opts($p);
    $lang = ['ar' => 'Write LinkedIn, X and on-slide copy in Arabic' . ($o['dialect'] !== '' ? ' (' . $o['dialect'] . ')' : ' (clear Modern Standard Arabic)'), 'en' => 'Write LinkedIn, X and on-slide copy in English', 'both' => 'Write LinkedIn copy in Arabic and X copy in English; on-slide copy in Arabic'][$o['lang']];
    return "You are a senior content strategist, B2B copywriter and art director.\n"
        . "Ground every claim ONLY in the company knowledge base you were given. NEVER invent statistics, client names, awards, certifications, prices, years or results. If a fact is missing, write around it.\n"
        . "Each post must have a distinct angle (no repeated topics), mix categories: educational, problem/solution, service spotlight, myth vs fact, checklist, behind the scenes, social proof only if in the KB, offer/CTA.\n"
        . $lang . ". Image and video prompts are ALWAYS in English.\n"
        . "linkedin: 130-220 words, strong first line hook, short paragraphs, one clear CTA, 3-5 relevant hashtags at the end.\n"
        . "x: max 270 characters including hashtags, punchy, standalone.\n"
        . "image_prompt: 120-200 words, premium editorial look, 4:5 portrait 1080x1350, describe subject, composition, lighting, palette, typography area; include the brand logo placement (small, a corner) and the exact headline text and where it sits. No fake UI text, no gibberish text, no stock-photo cliches.\n"
        . "headline: 2-6 words that appear on the image.\n"
        . ($o['video'] ? "video_prompt: model-agnostic AI video prompt (8-12 seconds, 9:16 or 4:5): scenes with timing, camera moves, motion, lighting, mood, on-screen text, sound design.\n" : "video_prompt: empty string.\n")
        . ($o['carousel'] ? "carousel: exactly 5 slides in this order: Hook, Problem, Insight, Solution, CTA; each {role, title (max 8 words), body (max 30 words), visual (short English art note)}.\n" : "carousel: empty array.\n")
        . ($o['tone'] !== '' ? 'Tone: ' . $o['tone'] . "\n" : '')
        . ($o['cta'] !== '' ? 'Preferred CTA: ' . $o['cta'] . "\n" : '')
        . ($o['avoid'] !== '' ? 'Avoid: ' . $o['avoid'] . "\n" : '');
}

function cpl_brief(array $p): string
{
    $o = cpl_opts($p);
    return "Campaign goal: " . $p['goal']
        . ($o['audience'] !== '' ? "\nTarget audience: " . $o['audience'] : '')
        . ($o['services'] ? "\nFocus services: " . implode('; ', $o['services']) : '')
        . ((string)$p['visual'] !== '' ? "\nVisual DNA (from the reference designs, follow it in every image prompt):\n" . $p['visual'] : '');
}

/** خطوة واحدة من التوليد. ترجع حالة الخطة بعدها. آمنة لو اتنادت من أكتر من مكان (قفل). */
function cpl_step(int $id): array
{
    $p = cpl_plan($id); if (!$p) throw new RuntimeException(t('الخطة غير موجودة.'));
    if ($p['status'] !== 'generating') return cpl_status($p);
    $lk = 'cpl_' . $id; $g = db()->query('SELECT GET_LOCK(' . db()->quote($lk) . ', 0)')->fetchColumn();
    if (!$g) return cpl_status($p) + ['busy' => true];
    try {
        @set_time_limit(300);
        if ($p['stage'] === 'visual') cpl_do_visual($p);
        elseif ($p['stage'] === 'outline') cpl_do_outline($p);
        else cpl_do_posts($p);
        db()->prepare('UPDATE cp_plans SET error = NULL, fails = 0 WHERE id = ?')->execute([$id]);
    } catch (Throwable $e) {
        $f = (int)$p['fails'] + 1;
        // 3 محاولات فاشلة ورا بعض = نوقف ونستنى المستخدم (مش نصرف رصيد على الفاضي)
        db()->prepare('UPDATE cp_plans SET error = ?, fails = ?, status = IF(? >= 3, "paused", status) WHERE id = ?')->execute([mb_substr($e->getMessage(), 0, 500), $f, $f, $id]);
    } finally { db()->query('SELECT RELEASE_LOCK(' . db()->quote($lk) . ')'); }
    return cpl_status(cpl_plan($id));
}

function cpl_status(array $p): array
{
    $st = db()->prepare('SELECT COUNT(*) total, SUM(linkedin IS NOT NULL) ready FROM cp_items WHERE plan_id = ?'); $st->execute([(int)$p['id']]); $c = $st->fetch();
    return ['id' => (int)$p['id'], 'status' => $p['status'], 'stage' => $p['stage'], 'count' => (int)$p['cnt'], 'topics' => (int)$c['total'], 'ready' => (int)$c['ready'], 'error' => (string)($p['error'] ?? '')];
}

function cpl_do_visual(array $p): void
{
    $wid = (int)ws('id'); $parts = [['type' => 'text', 'text' => 'Reference designs for the brand campaign. Analyse them.']];
    foreach (array_slice(json_decode((string)$p['refs'], true) ?: [], 0, 8) as $mid) {
        $m = media_get($wid, (int)$mid); if (!$m) continue;
        $f = media_thumb_path($wid, $m); if (!is_file($f)) $f = media_file_path($wid, $m); if (!is_file($f) || filesize($f) > 6000000) continue;
        $parts[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . (mime_content_type($f) ?: 'image/jpeg') . ';base64,' . base64_encode((string)file_get_contents($f))]];
    }
    $vis = '';
    if (count($parts) > 1) {
        $r = ai_chat([['role' => 'system', 'content' => "VISUAL_DNA. You are an art director. From the reference images extract a reusable visual DNA for image prompts, in English, 120-180 words: layout patterns, typography style, color palette (hex if clear), lighting, photography vs illustration vs 3D, textures, negative space, headline placement, mood. Describe style only, never copy brand names or text from the references. Plain text, no markdown."], ['role' => 'user', 'content' => $parts]], 700, 'analyze');
        $vis = trim((string)($r['text'] ?? ''));
    }
    db()->prepare('UPDATE cp_plans SET visual = ?, stage = "outline" WHERE id = ?')->execute([mb_substr($vis, 0, 3000), (int)$p['id']]);
}

function cpl_do_outline(array $p): void
{
    $n = (int)$p['cnt'];
    $sys = "CONTENT_PLAN_OUTLINE_JSON\n" . cpl_rules($p) . "\nNow plan {$n} distinct posts for this campaign. Return ONLY JSON: {\"topics\":[{\"title\":\"short topic\",\"angle\":\"the specific angle\",\"category\":\"educational|problem_solution|service|myth_fact|checklist|behind_scenes|proof|offer\",\"service\":\"related service from the KB or empty\",\"objective\":\"awareness|engagement|leads\"}]} with exactly {$n} items, ordered as a smart publishing sequence.";
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => cpl_brief($p)]], min(4000, 300 + $n * 70), 'compose');
    $j = kb_parse_json((string)($r['text'] ?? '')); $topics = array_values(array_filter((array)($j['topics'] ?? []), 'is_array'));
    if (!$topics) throw new RuntimeException(t('لم يُرجع الذكاء الاصطناعي مواضيع صالحة. حاول مرة أخرى.'));
    $topics = array_slice($topics, 0, $n);
    $ins = db()->prepare('INSERT INTO cp_items (plan_id, workspace_id, seq, topic, status) VALUES (?,?,?,?, "pending")');
    db()->prepare('DELETE FROM cp_items WHERE plan_id = ?')->execute([(int)$p['id']]);
    foreach ($topics as $i => $t) $ins->execute([(int)$p['id'], (int)ws('id'), $i + 1, json_encode(array_map(function ($v) { return is_string($v) ? mb_substr($v, 0, 300) : ''; }, array_intersect_key($t, array_flip(['title', 'angle', 'category', 'service', 'objective']))), JSON_UNESCAPED_UNICODE)]);
    db()->prepare('UPDATE cp_plans SET stage = "posts", cnt = ? WHERE id = ?')->execute([count($topics), (int)$p['id']]);
}

function cpl_do_posts(array $p): void
{
    $st = db()->prepare('SELECT * FROM cp_items WHERE plan_id = ? AND linkedin IS NULL ORDER BY seq LIMIT ' . CPL_BATCH); $st->execute([(int)$p['id']]);
    $rows = $st->fetchAll();
    if (!$rows) { db()->prepare('UPDATE cp_plans SET status = "review", stage = "done" WHERE id = ?')->execute([(int)$p['id']]); return; }
    $list = []; foreach ($rows as $r) { $t = json_decode((string)$r['topic'], true) ?: []; $list[] = ['n' => (int)$r['seq'], 'title' => $t['title'] ?? '', 'angle' => $t['angle'] ?? '', 'category' => $t['category'] ?? '', 'service' => $t['service'] ?? '']; }
    $sys = "CONTENT_PLAN_JSON\n" . cpl_rules($p) . "\nWrite the full posts for the topics given. Return ONLY JSON: {\"posts\":[{\"n\":1,\"linkedin\":\"...\",\"x\":\"...\",\"headline\":\"...\",\"image_prompt\":\"...\",\"video_prompt\":\"...\",\"carousel\":[{\"role\":\"Hook\",\"title\":\"...\",\"body\":\"...\",\"visual\":\"...\"}]}] one object per topic, same n.";
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => cpl_brief($p) . "\n\nTopics:\n" . json_encode($list, JSON_UNESCAPED_UNICODE)]], 1600 * count($rows), 'compose');
    $j = kb_parse_json((string)($r['text'] ?? '')); $by = [];
    foreach ((array)($j['posts'] ?? []) as $x) if (is_array($x) && isset($x['n'])) $by[(int)$x['n']] = $x;
    $up = db()->prepare('UPDATE cp_items SET linkedin = ?, x = ?, headline = ?, image_prompt = ?, video_prompt = ?, carousel = ?, status = "draft" WHERE id = ?');
    $done = 0;
    foreach ($rows as $row) {
        $x = $by[(int)$row['seq']] ?? null; if (!$x || trim((string)($x['linkedin'] ?? '')) === '') continue;
        $car = array_values(array_map(function ($s) { return ['role' => mb_substr((string)($s['role'] ?? ''), 0, 30), 'title' => mb_substr((string)($s['title'] ?? ''), 0, 200), 'body' => mb_substr((string)($s['body'] ?? ''), 0, 600), 'visual' => mb_substr((string)($s['visual'] ?? ''), 0, 400)]; }, array_filter((array)($x['carousel'] ?? []), 'is_array')));
        $up->execute([mb_substr(trim((string)$x['linkedin']), 0, 5000), mb_substr(trim((string)($x['x'] ?? '')), 0, 600), mb_substr(trim((string)($x['headline'] ?? '')), 0, 120), mb_substr(trim((string)($x['image_prompt'] ?? '')), 0, 4000), mb_substr(trim((string)($x['video_prompt'] ?? '')), 0, 4000), json_encode(array_slice($car, 0, 5), JSON_UNESCAPED_UNICODE), (int)$row['id']]);
        $done++;
    }
    if (!$done) throw new RuntimeException(t('رد الذكاء الاصطناعي ناقص. سنكمل من المنشور نفسه.'));
}

/** الكرون: كمّل الخطط اللي شغالة حتى لو المتصفح مقفول */
function cpl_tick(int $wid, int $budget = 4): int
{
    $st = db()->prepare('SELECT id FROM cp_plans WHERE workspace_id = ? AND status = "generating" ORDER BY id LIMIT 3'); $st->execute([$wid]); $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) for ($i = 0; $i < $budget; $i++) { $s = cpl_step((int)$id); $n++; if ($s['status'] !== 'generating' || !empty($s['busy']) || $s['error'] !== '') break; }
    return $n;
}

function cpl_set_status(array $ids, string $status): int
{
    if (!in_array($status, ['approved', 'rejected', 'draft'], true) || !$ids) return 0;
    $ids = array_map('intval', $ids);
    $st = db()->prepare('UPDATE cp_items SET status = ? WHERE workspace_id = ? AND linkedin IS NOT NULL AND status IN ("draft","approved","rejected") AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute(array_merge([$status, (int)ws('id')], $ids)); return $st->rowCount();
}

function cpl_save_item(int $id, array $f): void
{
    $it = cpl_item($id); if (!$it) throw new RuntimeException(t('العنصر غير موجود.'));
    $cols = ['linkedin' => 5000, 'x' => 600, 'headline' => 120, 'image_prompt' => 4000, 'video_prompt' => 4000]; $set = []; $a = [];
    foreach ($cols as $c => $max) if (array_key_exists($c, $f)) { $set[] = "$c = ?"; $a[] = mb_substr(trim((string)$f[$c]), 0, $max); }
    if (array_key_exists('media_id', $f)) { $mid = (int)$f['media_id']; if ($mid && !media_get((int)ws('id'), $mid)) throw new RuntimeException(t('الملف مش موجود')); $set[] = 'media_id = ?'; $a[] = $mid ?: null; }
    if (!$set) return; $a[] = $id; $a[] = (int)ws('id');
    db()->prepare('UPDATE cp_items SET ' . implode(', ', $set) . ' WHERE id = ? AND workspace_id = ?')->execute($a);
}

/** يبني طلب زيرنيو لعنصر: لينكدإن (وأي منصة غير X) بنص لينكدإن، وX بنصه الخاص */
function cpl_payload(Zernio $z, array $it, array $accounts, array $mode): array
{
    $platforms = [];
    foreach ($accounts as $a) {
        $pf = (string)$a['platform']; $item = ['platform' => $pf, 'accountId' => (string)$a['_id']];
        if ($pf === 'twitter' && trim((string)$it['x']) !== '') $item['customContent'] = (string)$it['x'];
        $platforms[] = $item;
    }
    $p = ['content' => (string)$it['linkedin'], 'platforms' => $platforms, 'timezone' => site_timezone()];
    if ($it['media_id']) $p['mediaItems'] = [['type' => media_kind((string)(media_get((int)ws('id'), (int)$it['media_id'])['mime'] ?? '')) === 'video' ? 'video' : 'image', 'url' => media_public_url($z, (int)ws('id'), (int)$it['media_id'], 'content_bank', (string)$it['id'])]];
    if ($mode['type'] === 'now') $p['publishNow'] = true;
    elseif ($mode['type'] === 'queue') $p['queuedFromProfile'] = current_profile_id($z);
    else $p['scheduledFor'] = $mode['at'];
    return $p;
}

/** ينشر/يجدول/يطابر مجموعة عناصر. at: لكل عنصر وقت محلي Y-m-d\TH:i:s لو جدولة */
function cpl_publish(Zernio $z, array $ids, array $accIds, string $type, array $times = []): array
{
    $byId = []; foreach (acc_filter($z->accounts(zernio_profile())) as $a) $byId[(string)$a['_id']] = $a;
    $accs = array_values(array_filter(array_map(function ($id) use ($byId) { return $byId[$id] ?? null; }, $accIds)));
    if (!$accs) throw new RuntimeException(t('اختر حسابًا واحدًا على الأقل.'));
    $out = []; $up = db()->prepare('UPDATE cp_items SET status = ?, post_id = ?, scheduled_at = ?, pub_error = NULL WHERE id = ?');
    $err = db()->prepare('UPDATE cp_items SET pub_error = ? WHERE id = ?');
    foreach (array_values($ids) as $i => $id) {
        $it = cpl_item((int)$id); if (!$it || !in_array($it['status'], ['approved', 'scheduled', 'queued'], true)) { $out[] = ['id' => (int)$id, 'ok' => false, 'error' => t('العنصر غير معتمد.')]; continue; }
        try {
            $at = $type === 'schedule' ? (string)($times[$i] ?? '') : '';
            if ($type === 'schedule' && !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d$/', $at)) throw new RuntimeException(t('الموعد غير صحيح.'));
            if ($it['post_id'] && $it['status'] !== 'queued') { try { $z->deletePost((string)$it['post_id']); } catch (Throwable $e) {} } // إعادة جدولة: امسح القديم
            $c = $z->createPost(cpl_payload($z, $it, $accs, ['type' => $type, 'at' => $at]));
            $st = ['now' => 'published', 'queue' => 'queued', 'schedule' => 'scheduled'][$type];
            $up->execute([$st, (string)($c['post']['_id'] ?? ''), $at !== '' ? str_replace('T', ' ', $at) : null, (int)$id]);
            $out[] = ['id' => (int)$id, 'ok' => true, 'status' => $st];
        } catch (Throwable $e) { $err->execute([mb_substr($e->getMessage(), 0, 300), (int)$id]); $out[] = ['id' => (int)$id, 'ok' => false, 'error' => $e->getMessage()]; }
    }
    return $out;
}

/** مواعيد موزعة: من بداية، كل X ساعة، في ساعات مسموحة بس */
function cpl_spread(string $start, int $everyH, int $n, int $fromH = 9, int $toH = 21): array
{
    $tz = new DateTimeZone(site_timezone());
    $d = DateTime::createFromFormat('Y-m-d\TH:i', substr($start, 0, 16), $tz); if (!$d) throw new RuntimeException(t('الموعد غير صحيح.'));
    if ($d < new DateTime('+5 minutes', $tz)) throw new RuntimeException(t('اختر موعدًا في المستقبل.'));
    $everyH = max(1, min(24 * 14, $everyH)); $out = [];
    for ($i = 0; $i < $n; $i++) {
        $guard = 0; while (((int)$d->format('G') < $fromH || (int)$d->format('G') >= $toH) && $guard++ < 48) $d->modify('+1 hour');
        $out[] = $d->format('Y-m-d\TH:i:s'); $d->modify('+' . $everyH . ' hours');
    }
    return $out;
}

/** ملف HTML مستقل (المحتوى ظاهر من غير جافاسكربت) */
function cpl_export_html(array $p, array $items): string
{
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $h = '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($p['name']) . '</title><style>'
        . 'body{font-family:system-ui,"Segoe UI",Tahoma,sans-serif;max-width:980px;margin:0 auto;padding:24px;background:#f7f7f8;color:#111;line-height:1.7}h1{margin:0 0 4px}article{background:#fff;border:1px solid #e5e5e5;border-radius:14px;padding:18px 20px;margin:18px 0}'
        . 'h2{font-size:18px;margin:0 0 6px}h3{font-size:13px;color:#666;margin:14px 0 4px;text-transform:uppercase}pre{white-space:pre-wrap;background:#fafafa;border:1px solid #eee;border-radius:10px;padding:10px;margin:0;font:inherit}'
        . '.en{direction:ltr;text-align:left}.meta{color:#666;font-size:13px}ol{margin:0;padding-inline-start:20px}@media print{article{break-inside:avoid}}</style></head><body>'
        . '<h1>' . $e($p['name']) . '</h1><p class="meta">' . $e($p['goal']) . '</p>';
    if ((string)$p['visual'] !== '') $h .= '<article><h2>Visual DNA</h2><pre class="en">' . $e($p['visual']) . '</pre></article>';
    foreach ($items as $it) {
        $t = $it['topic'];
        $h .= '<article><h2>' . (int)$it['seq'] . '. ' . $e($t['title'] ?? '') . '</h2><p class="meta">' . $e(implode(' · ', array_filter([$t['angle'] ?? '', $t['category'] ?? '', $t['service'] ?? '']))) . '</p>'
            . '<h3>LinkedIn</h3><pre>' . $e($it['linkedin']) . '</pre><h3>X</h3><pre>' . $e($it['x']) . '</pre>'
            . '<h3>Image prompt — ' . $e($it['headline']) . '</h3><pre class="en">' . $e($it['image_prompt']) . '</pre>'
            . ((string)$it['video_prompt'] !== '' ? '<h3>Video prompt</h3><pre class="en">' . $e($it['video_prompt']) . '</pre>' : '');
        if ($it['carousel']) { $h .= '<h3>Carousel</h3><ol>'; foreach ($it['carousel'] as $s) $h .= '<li><b>' . $e($s['role']) . ':</b> ' . $e($s['title']) . ' — ' . $e($s['body']) . ' <span class="en meta">(' . $e($s['visual']) . ')</span></li>'; $h .= '</ol>'; }
        $h .= '</article>';
    }
    return $h . '</body></html>';
}

/** أعمدة ملف Excel للخطة (نفس الترتيب في التصدير والاستيراد) */
function cpl_xlsx_cols(): array
{
    return ['id' => 'ID', 'seq' => '#', 'status' => t('الحالة'), 'topic' => t('الموضوع'), 'angle' => t('الزاوية'), 'category' => t('التصنيف'),
        'linkedin' => 'LinkedIn', 'x' => 'X', 'headline' => t('عنوان الصورة'), 'image_prompt' => 'Image prompt', 'video_prompt' => 'Video prompt',
        's1' => 'Slide 1 Hook', 's2' => 'Slide 2 Problem', 's3' => 'Slide 3 Insight', 's4' => 'Slide 4 Solution', 's5' => 'Slide 5 CTA'];
}

function cpl_status_label(string $s): string { return ['pending' => t('قيد الكتابة'), 'draft' => t('مسودة'), 'approved' => t('معتمد'), 'rejected' => t('مرفوض'), 'scheduled' => t('مجدول'), 'queued' => t('في الطابور'), 'published' => t('منشور')][$s] ?? $s; }

/** ملف xlsx حقيقي بدون مكتبات: شيت للمنشورات (من اليمين لليسار، عناوين مثبتة، فلتر، التفاف نص) وشيت للهدف والأسلوب البصري */
function cpl_export_xlsx(array $p, array $items): string
{
    $x = function ($s) { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$s), ENT_QUOTES | ENT_XML1, 'UTF-8'); };
    $cl = function (int $i) { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; };
    $cell = function (int $c, int $r, $v, int $st = 1) use ($x, $cl) { return is_int($v) ? '<c r="' . $cl($c) . $r . '" s="' . $st . '"><v>' . $v . '</v></c>' : '<c r="' . $cl($c) . $r . '" s="' . $st . '" t="inlineStr"><is><t xml:space="preserve">' . $x($v) . '</t></is></c>'; };
    $cols = cpl_xlsx_cols(); $keys = array_keys($cols);
    $rows = '<row r="1">'; foreach (array_values($cols) as $i => $h) $rows .= $cell($i, 1, $h, 2); $rows .= '</row>';
    foreach (array_values($items) as $k => $it) {
        $r = $k + 2; $t = $it['topic'];
        $v = ['id' => (int)$it['id'], 'seq' => (int)$it['seq'], 'status' => cpl_status_label((string)$it['status']), 'topic' => $t['title'] ?? '', 'angle' => $t['angle'] ?? '', 'category' => $t['category'] ?? '',
            'linkedin' => $it['linkedin'], 'x' => $it['x'], 'headline' => $it['headline'], 'image_prompt' => $it['image_prompt'], 'video_prompt' => $it['video_prompt']];
        for ($s = 0; $s < 5; $s++) { $sl = $it['carousel'][$s] ?? null; $v['s' . ($s + 1)] = $sl ? trim($sl['title'] . "\n" . $sl['body'] . ($sl['visual'] !== '' ? "\n[" . $sl['visual'] . ']' : '')) : ''; }
        $rows .= '<row r="' . $r . '">'; foreach ($keys as $i => $key) $rows .= $cell($i, $r, $v[$key] ?? '', in_array($key, ['image_prompt', 'video_prompt'], true) ? 3 : 1); $rows .= '</row>';
    }
    $w = ['id' => 7, 'seq' => 5, 'status' => 11, 'topic' => 24, 'angle' => 28, 'category' => 14, 'linkedin' => 60, 'x' => 34, 'headline' => 20, 'image_prompt' => 60, 'video_prompt' => 50];
    $colsXml = '<cols>'; foreach ($keys as $i => $key) $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . ($w[$key] ?? 32) . '" customWidth="1"/>'; $colsXml .= '</cols>';
    $last = $cl(count($keys) - 1) . (count($items) + 1);
    $sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView rightToLeft="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . $colsXml . '<sheetData>' . $rows . '</sheetData><autoFilter ref="A1:' . $last . '"/></worksheet>';
    $info = [[t('اسم الخطة'), (string)$p['name']], [t('هدف الحملة'), (string)$p['goal']], ['Visual DNA', (string)$p['visual']], [t('ملاحظة'), t('عدّل النصوص أو عمود الحالة (معتمد / مرفوض / مسودة) ثم ارفع الملف في صفحة الخطة. لا تغيّر عمود ID.')]];
    $r2 = ''; foreach ($info as $k => $row) $r2 .= '<row r="' . ($k + 1) . '">' . $cell(0, $k + 1, $row[0], 2) . $cell(1, $k + 1, $row[1], $k === 2 ? 3 : 1) . '</row>';
    $sheet2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews><cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="100" customWidth="1"/></cols><sheetData>' . $r2 . '</sheetData></worksheet>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F2937"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
        . '<cellXfs count="4"><xf/><xf applyAlignment="1"><alignment wrapText="1" vertical="top" readingOrder="2"/></xf><xf fontId="1" fillId="2" applyFont="1" applyFill="1" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf><xf applyAlignment="1"><alignment wrapText="1" vertical="top" horizontal="left" readingOrder="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    $tmp = tempnam(sys_get_temp_dir(), 'cpx'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Posts" sheetId="1" r:id="rId1"/><sheet name="Brief" sheetId="2" r:id="rId2"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">Posts!$A$1:$' . preg_replace('/\d+/', '', $last) . '$' . (count($items) + 1) . '</definedName></definedNames></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $z->addFromString('xl/worksheets/sheet1.xml', $sheet1); $z->addFromString('xl/worksheets/sheet2.xml', $sheet2); $z->addFromString('xl/styles.xml', $styles); $z->close();
    $b = (string)file_get_contents($tmp); @unlink($tmp); return $b;
}

/** رفع ملف Excel المعدّل: يحدّث النصوص والحالة بالـ ID. يرجع [updated, skipped] */
function cpl_import_xlsx(int $planId, string $path): array
{
    $sh = xlsx_sheets($path, 200)[0]['rows'] ?? []; if (count($sh) < 2) throw new RuntimeException(t('الملف فارغ.'));
    $head = array_map('trim', $sh[0]); $cols = cpl_xlsx_cols(); $idx = [];
    foreach ($cols as $k => $h) { $i = array_search($h, $head, true); if ($i !== false) $idx[$k] = $i; }
    if (!isset($idx['id'])) throw new RuntimeException(t('عمود ID غير موجود. استخدم الملف الذي نزّلته من هنا.'));
    $stMap = [cpl_status_label('approved') => 'approved', cpl_status_label('rejected') => 'rejected', cpl_status_label('draft') => 'draft', 'approved' => 'approved', 'rejected' => 'rejected', 'draft' => 'draft', 'معتمد' => 'approved', 'مرفوض' => 'rejected', 'مسودة' => 'draft'];
    $up = 0; $skip = 0;
    foreach (array_slice($sh, 1) as $row) {
        $it = cpl_item((int)($row[$idx['id']] ?? 0)); if (!$it || $it['plan_id'] !== $planId || $it['linkedin'] === null || !in_array($it['status'], ['draft', 'approved', 'rejected'], true)) { $skip++; continue; }
        $g = function ($k) use ($row, $idx) { return isset($idx[$k]) ? (string)($row[$idx[$k]] ?? '') : null; };
        $f = []; foreach (['linkedin', 'x', 'headline', 'image_prompt', 'video_prompt'] as $k) if (($v = $g($k)) !== null && trim($v) !== '') $f[$k] = $v;
        cpl_save_item($it['id'], $f);
        $car = $it['carousel']; $ch = false;
        for ($s = 0; $s < 5; $s++) { $v = $g('s' . ($s + 1)); if ($v === null || trim($v) === '' || !isset($car[$s])) continue; $ln = preg_split('/\R/u', trim($v)); $vis = '';
            if (count($ln) > 1 && preg_match('/^\[(.*)\]$/u', trim(end($ln)), $m)) { $vis = $m[1]; array_pop($ln); }
            $car[$s] = ['role' => $car[$s]['role'], 'title' => mb_substr(trim((string)array_shift($ln)), 0, 200), 'body' => mb_substr(trim(implode("\n", $ln)), 0, 600), 'visual' => mb_substr($vis !== '' ? $vis : $car[$s]['visual'], 0, 400)]; $ch = true; }
        if ($ch) db()->prepare('UPDATE cp_items SET carousel = ? WHERE id = ?')->execute([json_encode($car, JSON_UNESCAPED_UNICODE), $it['id']]);
        $st = $stMap[trim((string)$g('status'))] ?? null; if ($st && $st !== $it['status']) cpl_set_status([$it['id']], $st);
        $up++;
    }
    return [$up, $skip];
}
