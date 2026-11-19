<?php
/* خطة المحتوى بالذكاء الاصطناعي: هدف + عدد + صور مرجعية ← (تحليل بصري ← مواضيع ← منشورات كاملة على دفعات)
   كل خطوة بتتحفظ في القاعدة، فلو النت فصل أو الجهاز قفل الشغل بيكمل من نفس النقطة (من الصفحة أو من الكرون).
   مابيولّدش صور ولا فيديو: بيجهز النصوص والبرومبتات بس. */

const CPL_BATCH = 2;   // منشورين كاملين في كل نداء (6 أقسام طويلة لكل واحد)
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
    $rows = array_map('cpl_item_row', $st->fetchAll()); $opts = []; $seen = [];
    foreach ($rows as &$r) {
        if (!isset($opts[$r['plan_id']])) { $pp = cpl_plan($r['plan_id']); $opts[$r['plan_id']] = $pp ? cpl_opts($pp) : []; }
        $r['qc'] = cpl_qc($r, $opts[$r['plan_id']]);
        foreach (['headline' => t('عنوان صورة مكرر مع المنشور %s'), 'title' => t('موضوع مكرر مع المنشور %s')] as $k => $msg) { // تكرار داخل نفس الخطة
            $v = $k === 'title' ? ($r['topic']['title'] ?? '') : (string)$r['headline']; $key = $r['plan_id'] . '|' . $k . '|' . mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $v));
            if ($v === '' || $r['linkedin'] === null) continue; if (isset($seen[$key])) $r['qc'][] = sprintf($msg, $seen[$key]); else $seen[$key] = $r['seq'];
        }
    } unset($r);
    return $rows;
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
        'video' => !empty($in['video']), 'carousel' => !isset($in['carousel']) || !empty($in['carousel']), 'humanize' => !isset($in['humanize']) || !empty($in['humanize'])];
    $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 150) ?: mb_substr(preg_replace('/\s+/u', ' ', $goal), 0, 60);
    db()->prepare('INSERT INTO cp_plans (workspace_id, user_id, name, goal, cnt, opts, refs, stage, status) VALUES (?,?,?,?,?,?,?,?, "generating")')
        ->execute([$wid, (int)(current_user()['id'] ?? 0), $name, $goal, $n, json_encode($opts, JSON_UNESCAPED_UNICODE), json_encode($refs), $refs ? 'visual' : 'outline']);
    return (int)db()->lastInsertId();
}

function cpl_opts(array $p): array { return (json_decode((string)$p['opts'], true) ?: []) + ['lang' => 'ar', 'dialect' => '', 'audience' => '', 'services' => [], 'tone' => '', 'cta' => '', 'avoid' => '', 'video' => true, 'carousel' => true, 'humanize' => true]; }

/** البرومبت الأساسي = برومبت المستخدم بالحرف (app/prompts/content_plan.md) + إعدادات الخطة. قسم HTML اتشال لأن السيستم هو اللي بيعرض ويصدّر. */
function cpl_master(array $p): string
{
    static $m = null; if ($m === null) $m = (string)@file_get_contents(__DIR__ . '/../prompts/content_plan.md');
    return str_replace('NUMBER_OF_POSTS', (string)(int)$p['cnt'], $m);
}

function cpl_rules(array $p): string
{
    $o = cpl_opts($p);
    $lang = ['ar' => 'Arabic' . ($o['dialect'] !== '' ? ' (' . $o['dialect'] . ')' : ' (clear Modern Standard Arabic unless the KB shows another brand voice)'), 'en' => 'English', 'both' => 'Arabic for LinkedIn and carousel copy, English for X'][$o['lang']];
    return cpl_master($p)
        . "\n\nPLAN SETTINGS\n- Copy language (LinkedIn, X, headline, on-slide copy, video voice-over dialogue): {$lang}. Image prompts and video directions: professional English.\n"
        . ($o['dialect'] !== '' ? "- Voice-over dialogue dialect: {$o['dialect']}.\n" : '')
        . ($o['tone'] !== '' ? "- Brand tone: {$o['tone']}\n" : '')
        . ($o['cta'] !== '' ? "- Preferred CTA: {$o['cta']}\n" : '')
        . ($o['avoid'] !== '' ? "- Avoid: {$o['avoid']}\n" : '')
        . (!$o['video'] ? "- The user turned off video for this plan: return video_prompt as an empty string.\n" : '')
        . (!$o['carousel'] ? "- The user turned off carousels for this plan: return carousel as an empty array.\n" : '')
        . "- The official logo is in the KB; refer to it as \"the official logo\" and state its exact placement. Never describe or invent its design.\n";
}

function cpl_brief(array $p): string
{
    $o = cpl_opts($p); $pal = function_exists('kb_logo_palette') ? kb_logo_palette((int)ws('id')) : [];
    return "Campaign goal (build the strategy around it): " . $p['goal']
        . ($o['audience'] !== '' ? "\nTarget audience / decision-makers: " . $o['audience'] : '')
        . ($o['services'] ? "\nFocus services: " . implode('; ', $o['services']) : '')
        . ($pal ? "\nLogo colors (hex): " . implode(', ', $pal) : '')
        . ((string)$p['visual'] !== '' ? "\nVisual identity extracted from the reference images (apply it in every image, video and carousel direction):\n" . $p['visual'] : "\nNo reference images were supplied: derive the visual identity from the KB and logo colors.");
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
    $h = db()->prepare('SELECT COUNT(*) FROM cp_items WHERE plan_id = ? AND humanized = 1'); $h->execute([(int)$p['id']]);
    return ['id' => (int)$p['id'], 'status' => $p['status'], 'stage' => $p['stage'], 'count' => (int)$p['cnt'], 'topics' => (int)$c['total'], 'ready' => (int)$c['ready'], 'human' => (int)$h->fetchColumn(), 'hz' => !empty(cpl_opts($p)['humanize']), 'error' => (string)($p['error'] ?? '')];
}

function cpl_do_visual(array $p): void
{
    $wid = (int)ws('id'); $parts = [['type' => 'text', 'text' => 'Visual references for the brand campaign.']];
    foreach (array_slice(json_decode((string)$p['refs'], true) ?: [], 0, 12) as $mid) {
        $m = media_get($wid, (int)$mid); if (!$m) continue;
        $f = media_file_path($wid, $m); if (!is_file($f) || filesize($f) > 4000000) $f = media_thumb_path($wid, $m); if (!is_file($f)) continue;
        $parts[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . (mime_content_type($f) ?: 'image/jpeg') . ';base64,' . base64_encode((string)file_get_contents($f))]];
    }
    $vis = '';
    if (count($parts) > 1) {
        $r = ai_chat([['role' => 'system', 'content' => "VISUAL_DNA. Act as a Commercial Art Director. Analyze the reference images to extract the visual identity: colors (hex values), typography (style, weight, case, Arabic/Latin pairing), lighting, composition, logo placement, and advertising style; also photography vs illustration vs 3D, textures, negative space, headline placement and mood. Write a reusable visual DNA in English, 150-250 words, as plain text grouped by those headings. Describe the style only; never copy brand names, text or specific people from the references."], ['role' => 'user', 'content' => $parts]], 900, 'analyze');
        $vis = trim((string)($r['text'] ?? ''));
    }
    db()->prepare('UPDATE cp_plans SET visual = ?, stage = "outline" WHERE id = ?')->execute([mb_substr($vis, 0, 4000), (int)$p['id']]);
}

/** تصنيفات المزيج الاستراتيجي من البرومبت (بالحرف) */
function cpl_categories(): array
{
    return ['pain_point' => 'Pain point', 'educational' => 'Educational insight', 'positioning' => 'Product positioning', 'thought_leadership' => 'Thought leadership', 'psychological' => 'Psychological selling', 'loss_aversion' => 'Loss aversion',
        'opportunity_cost' => 'Opportunity cost', 'differentiation' => 'Differentiation', 'objection' => 'Objection handling', 'authority' => 'Brand authority', 'trust' => 'Trust building', 'direct_sell' => 'Direct selling', 'indirect_sell' => 'Indirect selling', 'conversion' => 'Conversion'];
}

function cpl_do_outline(array $p): void
{
    $n = (int)$p['cnt'];
    $sys = "CONTENT_PLAN_OUTLINE_JSON\n" . cpl_rules($p) . "\n\nSTEP 1 OF 2: STRATEGY. Plan {$n} distinct posts (topics only, no copy yet). Cover the strategic mix across the plan and prioritize services by their commercial importance in the KB."
        . "\nReturn ONLY JSON: {\"topics\":[{\"title\":\"the strong, distinctive hook\",\"angle\":\"one sentence: the specific insight or argument of this post\",\"category\":\"" . implode('|', array_keys(cpl_categories())) . "\",\"service\":\"the relevant product/service exactly as named in the KB\",\"audience\":\"the specific decision-maker this post targets\",\"objective\":\"awareness|engagement|consideration|leads|conversion\"}]} with exactly {$n} items, ordered as a smart publishing sequence. No two topics may share the same hook or argument.";
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => cpl_brief($p)]], min(8000, 400 + $n * 110), 'compose');
    $j = kb_parse_json((string)($r['text'] ?? '')); $topics = array_values(array_filter((array)($j['topics'] ?? []), 'is_array'));
    if (!$topics) throw new RuntimeException(t('لم يُرجع الذكاء الاصطناعي مواضيع صالحة. حاول مرة أخرى.'));
    $topics = array_slice($topics, 0, $n);
    $ins = db()->prepare('INSERT INTO cp_items (plan_id, workspace_id, seq, topic, status) VALUES (?,?,?,?, "pending")');
    db()->prepare('DELETE FROM cp_items WHERE plan_id = ?')->execute([(int)$p['id']]);
    foreach ($topics as $i => $t) $ins->execute([(int)$p['id'], (int)ws('id'), $i + 1, json_encode(array_map(function ($v) { return is_string($v) ? mb_substr($v, 0, 300) : ''; }, array_intersect_key($t, array_flip(['title', 'angle', 'category', 'service', 'audience', 'objective']))), JSON_UNESCAPED_UNICODE)]);
    db()->prepare('UPDATE cp_plans SET stage = "posts", cnt = ? WHERE id = ?')->execute([count($topics), (int)$p['id']]);
}

/** مراجعة Humanizer: المهارة كاملة على نصوص المنشورات (لينكدإن، X، العنوان، الكاروسيل). البرومبتات الإنجليزي مابتتلمسش. */
function cpl_do_humanize(array $p, array $rows): void
{
    $list = []; foreach ($rows as $r) { $car = json_decode((string)$r['carousel'], true) ?: [];
        $list[] = ['n' => (int)$r['seq'], 'linkedin' => $r['linkedin'], 'x' => $r['x'], 'headline' => $r['headline'], 'carousel' => array_map(function ($s) { return ['title' => $s['title'] ?? '', 'body' => $s['body'] ?? '']; }, $car)]; }
    $sys = "CONTENT_HUMANIZE_JSON\nYou are the editor. Apply the Humanizer skill below to every text field, in EMBEDDED MODE (return only the final text). The copy is social media for a company; keep its language and dialect, keep hashtags at the end, keep the CTA, keep x within 270 characters, keep headline 2-6 words. Do not add or drop any fact, number, name, price or claim. Do not translate.\n"
        . "Return ONLY JSON with the same shape: {\"posts\":[{\"n\":1,\"linkedin\":\"...\",\"x\":\"...\",\"headline\":\"...\",\"carousel\":[{\"title\":\"...\",\"body\":\"...\"}]}]}\n\n=== HUMANIZER SKILL ===\n" . ai_humanizer_skill();
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => json_encode($list, JSON_UNESCAPED_UNICODE)]], 1300 * count($rows), 'compose');
    $j = kb_parse_json((string)($r['text'] ?? '')); $by = []; foreach ((array)($j['posts'] ?? []) as $x) if (is_array($x) && isset($x['n'])) $by[(int)$x['n']] = $x;
    $up = db()->prepare('UPDATE cp_items SET linkedin = ?, x = ?, headline = ?, carousel = ?, humanized = 1 WHERE id = ?'); $done = 0;
    foreach ($rows as $row) {
        $x = $by[(int)$row['seq']] ?? null; $car = json_decode((string)$row['carousel'], true) ?: [];
        $li = $x ? trim((string)($x['linkedin'] ?? '')) : '';
        if ($li === '' || mb_strlen($li) < mb_strlen((string)$row['linkedin']) * 0.4) { // رد ناقص: نطبق التنظيف الحتمي بس ومانضيعش النص
            if ($x) continue;
            $li = (string)$row['linkedin']; $x = ['x' => $row['x'], 'headline' => $row['headline'], 'carousel' => []];
        }
        foreach ($car as $i => $s) { $n = $x['carousel'][$i] ?? null; if (is_array($n)) { if (trim((string)($n['title'] ?? '')) !== '') $car[$i]['title'] = mb_substr(ai_strip_dashes(trim((string)$n['title'])), 0, 200); if (trim((string)($n['body'] ?? '')) !== '') $car[$i]['body'] = mb_substr(ai_strip_dashes(trim((string)$n['body'])), 0, 600); } }
        $xx = trim((string)($x['x'] ?? '')) ?: (string)$row['x']; $hh = trim((string)($x['headline'] ?? '')) ?: (string)$row['headline'];
        $up->execute([mb_substr(ai_strip_dashes($li), 0, 5000), mb_substr(ai_strip_dashes($xx), 0, 600), mb_substr(ai_strip_dashes($hh), 0, 120), json_encode($car, JSON_UNESCAPED_UNICODE), (int)$row['id']]); $done++;
    }
    if (!$done) throw new RuntimeException(t('مراجعة الأسلوب لم تكتمل. سنعيد المحاولة على المنشورات نفسها.'));
}

function cpl_do_posts(array $p): void
{
    if (cpl_opts($p)['humanize']) { // اكتب ← راجع بالـ Humanizer ← اكتب الدفعة اللي بعدها
        $h = db()->prepare('SELECT * FROM cp_items WHERE plan_id = ? AND linkedin IS NOT NULL AND humanized = 0 AND status = "draft" ORDER BY seq LIMIT ' . CPL_BATCH); $h->execute([(int)$p['id']]);
        if ($hr = $h->fetchAll()) { cpl_do_humanize($p, $hr); return; }
    }
    $st = db()->prepare('SELECT * FROM cp_items WHERE plan_id = ? AND linkedin IS NULL ORDER BY seq LIMIT ' . CPL_BATCH); $st->execute([(int)$p['id']]);
    $rows = $st->fetchAll();
    if (!$rows) { db()->prepare('UPDATE cp_plans SET status = "review", stage = "done" WHERE id = ?')->execute([(int)$p['id']]); return; }
    $list = []; foreach ($rows as $r) { $t = json_decode((string)$r['topic'], true) ?: []; $list[] = ['n' => (int)$r['seq']] + array_intersect_key($t, array_flip(['title', 'angle', 'category', 'service', 'audience', 'objective'])); }
    $st2 = db()->prepare('SELECT seq, headline FROM cp_items WHERE plan_id = ? AND linkedin IS NOT NULL ORDER BY seq'); $st2->execute([(int)$p['id']]); $used = array_filter(array_column($st2->fetchAll(), 'headline'));
    $sys = "CONTENT_PLAN_JSON\n" . cpl_rules($p) . "\n\nSTEP 2 OF 2: write the complete posts for the topics given, all six sections each, finished and usable."
        . "\nReturn ONLY JSON: {\"posts\":[{\"n\":1,\"linkedin\":\"130-220 words\",\"x\":\"max 280 characters incl. hashtags\",\"headline\":\"2-6 word poster headline\",\"image_prompt\":\"120-200 words English, covering concept, subject, art direction, composition, camera, lighting, brand identity, realism, the headline with its exact placement, output 4:5 1080x1350, and the avoid list\",\"video_prompt\":\"universal production-ready video prompt\",\"carousel\":[{\"role\":\"Hook|Problem|Insight|Solution|CTA\",\"title\":\"on-slide headline\",\"body\":\"on-slide supporting copy\",\"visual\":\"visual concept and composition\",\"layout\":\"layout instructions\"}]}]} one object per topic with the same n; carousel has exactly 5 slides in the order Hook, Problem, Insight, Solution, CTA."
        . ($used ? "\nHeadlines and visual concepts already used in this plan (do not repeat them): " . implode(' | ', array_slice($used, -40)) : '');
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => cpl_brief($p) . "\n\nTopics:\n" . json_encode($list, JSON_UNESCAPED_UNICODE)]], 3200 * count($rows), 'compose');
    $j = kb_parse_json((string)($r['text'] ?? '')); $by = [];
    foreach ((array)($j['posts'] ?? []) as $x) if (is_array($x) && isset($x['n'])) $by[(int)$x['n']] = $x;
    $up = db()->prepare('UPDATE cp_items SET linkedin = ?, x = ?, headline = ?, image_prompt = ?, video_prompt = ?, carousel = ?, status = "draft", humanized = 0 WHERE id = ?');
    $done = 0;
    foreach ($rows as $row) {
        $x = $by[(int)$row['seq']] ?? null; if (!$x || trim((string)($x['linkedin'] ?? '')) === '') continue;
        $car = array_values(array_map(function ($s) { return ['role' => mb_substr((string)($s['role'] ?? ''), 0, 30), 'title' => mb_substr((string)($s['title'] ?? ''), 0, 200), 'body' => mb_substr((string)($s['body'] ?? ''), 0, 800), 'visual' => mb_substr((string)($s['visual'] ?? ''), 0, 800), 'layout' => mb_substr((string)($s['layout'] ?? ''), 0, 600)]; }, array_filter((array)($x['carousel'] ?? []), 'is_array')));
        $up->execute([mb_substr(trim((string)$x['linkedin']), 0, 6000), mb_substr(trim((string)($x['x'] ?? '')), 0, 600), mb_substr(trim((string)($x['headline'] ?? '')), 0, 120), mb_substr(trim((string)($x['image_prompt'] ?? '')), 0, 6000), mb_substr(trim((string)($x['video_prompt'] ?? '')), 0, 8000), json_encode(array_slice($car, 0, 5), JSON_UNESCAPED_UNICODE), (int)$row['id']]);
        $done++;
    }
    if (!$done) throw new RuntimeException(t('رد الذكاء الاصطناعي ناقص. سنكمل من المنشور نفسه.'));
}

/** فحص الجودة (QUALITY CONTROL في البرومبت) لكل منشور: أرقام قابلة للقياس فقط، ومش بيحكم على الإبداع */
function cpl_qc(array $it, array $o = []): array
{
    if ($it['linkedin'] === null) return [];
    $w = function ($s) { return count(preg_split('/\s+/u', trim((string)$s), -1, PREG_SPLIT_NO_EMPTY)); }; $q = [];
    $lw = $w($it['linkedin']); if ($lw < 130 || $lw > 220) $q[] = t('لينكدإن %s كلمة (المطلوب 130–220)', $lw);
    if (mb_strlen((string)$it['x']) > 280) $q[] = t('X تجاوز 280 حرفًا'); if (trim((string)$it['x']) === '') $q[] = t('نص X فارغ');
    if (!preg_match('/#\S/u', (string)$it['linkedin'])) $q[] = t('لينكدإن بلا هاشتاجات');
    $iw = $w($it['image_prompt']); if ($iw < 110 || $iw > 220) $q[] = t('برومبت الصورة %s كلمة (المطلوب 120–200)', $iw);
    $hw = $w($it['headline']); if ($hw < 2 || $hw > 6) $q[] = t('عنوان الصورة %s كلمات (المطلوب 2–6)', $hw);
    if (($o['video'] ?? true) && $w($it['video_prompt']) < 60) $q[] = t('برومبت الفيديو قصير');
    if (($o['carousel'] ?? true) && count($it['carousel']) !== 5) $q[] = t('الكاروسيل %s شرائح (المطلوب 5)', count($it['carousel']));
    if (preg_match('/\[(?:[^\]]*)(?:placeholder|insert|company name|اسم الشركة|ضع هنا)[^\]]*\]|lorem|XXX/iu', $it['linkedin'] . $it['x'] . $it['image_prompt'])) $q[] = t('فيه نص مؤقت (placeholder)');
    return $q;
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
            if ($it['post_id']) { // إعادة جدولة أو نقل من الطابور: امسح القديم عشان مايتنشرش مرتين
                try { $z->deletePost((string)$it['post_id']); } catch (Throwable $e) {} } // إعادة جدولة: امسح القديم
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

/** Content Studio: ملف HTML مستقل حسب قسم HTML DELIVERY في برومبت المستخدم. كل الكروت مكتوبة في الـ HTML نفسه (ظاهرة من غير جافاسكربت)، والجافاسكربت تحسينات بس، ومفيش أي ملفات خارجية. */
function cpl_export_html(array $p, array $items): string
{
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $pal = function_exists('kb_logo_palette') ? kb_logo_palette((int)ws('id')) : []; $c1 = preg_match('/^#[0-9a-f]{6}$/i', (string)($pal[0] ?? '')) ? $pal[0] : '#0f766e'; $c2 = preg_match('/^#[0-9a-f]{6}$/i', (string)($pal[1] ?? '')) ? $pal[1] : '#1f2937';
    $cats = cpl_categories(); $svc = []; $cat = []; $obj = [];
    foreach ($items as $it) { $t = $it['topic']; if (!empty($t['service'])) $svc[$t['service']] = 1; if (!empty($t['category'])) $cat[$t['category']] = 1; if (!empty($t['objective'])) $obj[$t['objective']] = 1; }
    $opt = function (array $a, string $lab) use ($e) { $o = '<option value="">' . $e($lab) . '</option>'; foreach (array_keys($a) as $v) $o .= '<option>' . $e($v) . '</option>'; return $o; };
    $sec = function (string $id, string $label, string $text, bool $en = false) use ($e) { return '<section><div class="sh"><h3>' . $e($label) . '</h3><button type="button" class="cp" data-c="' . $id . '" hidden>Copy</button></div><pre id="' . $id . '"' . ($en ? ' dir="ltr"' : ' dir="auto"') . '>' . $e($text) . '</pre></section>'; };
    $cards = '';
    foreach (array_values($items) as $k => $it) {
        $t = $it['topic']; $id = 'p' . (int)$it['seq'];
        $car = ''; foreach ($it['carousel'] as $n => $sl) $car .= ($n + 1) . '. ' . $sl['role'] . "\n" . 'Copy: ' . $sl['title'] . ($sl['body'] !== '' ? ' — ' . $sl['body'] : '') . "\nVisual: " . $sl['visual'] . (!empty($sl['layout']) ? "\nLayout: " . $sl['layout'] : '') . "\n\n";
        $topic = trim(($t['title'] ?? '') . "\n" . implode(' · ', array_filter([$t['angle'] ?? '', $t['service'] ?? '', $t['audience'] ?? '', isset($t['category']) ? ($cats[$t['category']] ?? $t['category']) : '', $t['objective'] ?? ''])));
        $cards .= '<details open class="card" id="' . $id . '" data-s="' . $e($t['service'] ?? '') . '" data-c="' . $e($t['category'] ?? '') . '" data-o="' . $e($t['objective'] ?? '') . '">'
            . '<summary><span class="n">' . (int)$it['seq'] . '</span><span class="tt" dir="auto">' . $e($t['title'] ?? '') . '</span><span class="tags">' . $e(implode(' · ', array_filter([$t['service'] ?? '', isset($t['category']) ? ($cats[$t['category']] ?? $t['category']) : '', $t['objective'] ?? '']))) . '</span>'
            . '<label class="pub" hidden><input type="checkbox" data-pub="' . $id . '"> Published</label></summary>'
            . $sec($id . 't', '1. Topic', $topic) . $sec($id . 'l', '2. LinkedIn', (string)$it['linkedin']) . $sec($id . 'x', '3. X', (string)$it['x'])
            . $sec($id . 'i', '4. Image prompt · ' . $it['headline'], (string)$it['image_prompt'], true)
            . ((string)$it['video_prompt'] !== '' ? $sec($id . 'v', '5. Video prompt', (string)$it['video_prompt'], true) : '')
            . ($car !== '' ? $sec($id . 'c', '6. Carousel', trim($car)) : '')
            . '<button type="button" class="all" data-all="' . $id . '" hidden>Copy all</button></details>';
    }
    $title = $e($p['name']);
    $css = ':root{--b:' . $c1 . ';--d:' . $c2 . '}*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:#f5f6f8;color:#111827;line-height:1.7}'
        . 'header{background:linear-gradient(135deg,var(--d),var(--b));color:#fff;padding:28px 20px}header h1{margin:0 0 6px;font-size:22px}header p{margin:0;opacity:.9;font-size:14px}main{max-width:1100px;margin:0 auto;padding:16px}'
        . '.bar{position:sticky;top:0;z-index:5;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:-34px 0 16px;box-shadow:0 6px 20px rgba(0,0,0,.06)}'
        . '.bar input,.bar select{font:inherit;border:1px solid #d1d5db;border-radius:10px;padding:7px 10px;min-width:0;flex:1 1 140px;background:#fff}button{font:inherit;cursor:pointer;border:1px solid #d1d5db;background:#fff;border-radius:10px;padding:6px 12px}'
        . 'button.pri{background:var(--b);border-color:var(--b);color:#fff}.prog{flex:1 1 100%;height:6px;background:#e5e7eb;border-radius:9px;overflow:hidden}.prog i{display:block;height:100%;width:0;background:var(--b)}'
        . '.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;margin:12px 0;padding:0 16px 14px}.card summary{list-style:none;cursor:pointer;display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:14px 0}'
        . '.n{background:var(--b);color:#fff;border-radius:8px;min-width:30px;text-align:center;font-weight:700}.tt{font-weight:700;flex:1 1 280px}.tags{color:#6b7280;font-size:12px}.pub{font-size:13px;color:#374151}'
        . '.card.done{opacity:.6}section{border-top:1px solid #f0f1f3;padding-top:8px;margin-top:8px}.sh{display:flex;justify-content:space-between;align-items:center}h3{margin:0;font-size:12px;color:var(--b);text-transform:uppercase;letter-spacing:.04em}'
        . 'pre{white-space:pre-wrap;word-wrap:break-word;font:inherit;margin:4px 0 0;font-size:14px}pre[dir=ltr]{text-align:left}.all{margin-top:10px}.dna{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:12px 16px;margin:12px 0}'
        . '@media(max-width:600px){header h1{font-size:18px}.card{padding:0 12px 12px}}@media print{.bar,.cp,.all,.pub{display:none!important}.card{break-inside:avoid}}';
    $js = <<<'JS'
(function(){var K='cs_'+document.body.dataset.k,st={};try{st=JSON.parse(localStorage.getItem(K)||'{}')}catch(e){}
function $(s){return document.querySelector(s)}function $$(s){return [].slice.call(document.querySelectorAll(s))}
$$('[hidden]').forEach(function(x){x.hidden=false});
function copy(t,b){(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).catch(function(){var a=document.createElement('textarea');a.value=t;document.body.appendChild(a);a.select();document.execCommand('copy');a.remove()}).then(function(){var o=b.textContent;b.textContent='✓';setTimeout(function(){b.textContent=o},900)})}
document.addEventListener('click',function(e){var b=e.target.closest('[data-c]');if(b&&b.tagName==='BUTTON'){copy(document.getElementById(b.dataset.c).textContent,b)}
var a=e.target.closest('[data-all]');if(a){var c=document.getElementById(a.dataset.all);copy($$('#'+a.dataset.all+' section').map(function(s){return s.querySelector('h3').textContent+'\n'+s.querySelector('pre').textContent}).join('\n\n'),a)}});
function paint(){var n=0,t=$$('.card').length;$$('[data-pub]').forEach(function(c){c.checked=!!st[c.dataset.pub];c.closest('.card').classList.toggle('done',c.checked);if(c.checked)n++});$('#pc').textContent=n+' / '+t;$('.prog i').style.width=(t?100*n/t:0)+'%'}
document.addEventListener('change',function(e){var c=e.target.closest('[data-pub]');if(!c)return;st[c.dataset.pub]=c.checked;try{localStorage.setItem(K,JSON.stringify(st))}catch(x){}paint()});
function filt(){var q=$('#q').value.toLowerCase(),s=$('#fs').value,c=$('#fc').value,o=$('#fo').value,u=$('#fu').value;$$('.card').forEach(function(d){var p=!!st[d.id];d.style.display=(!q||d.textContent.toLowerCase().indexOf(q)>=0)&&(!s||d.dataset.s===s)&&(!c||d.dataset.c===c)&&(!o||d.dataset.o===o)&&(!u||(u==='1')===p)?'':'none'})}
['q','fs','fc','fo','fu'].forEach(function(i){$('#'+i).addEventListener('input',filt)});
$('#ex').onclick=function(){$$('.card').forEach(function(d){d.open=true})};$('#co').onclick=function(){$$('.card').forEach(function(d){d.open=false})};
$('#csv').onclick=function(){var r=[['#','Topic','LinkedIn','X','Image prompt','Video prompt','Carousel','Published']];$$('.card').forEach(function(d){var g=function(x){var e=document.getElementById(d.id+x);return e?e.textContent:''};r.push([d.querySelector('.n').textContent,g('t'),g('l'),g('x'),g('i'),g('v'),g('c'),st[d.id]?'yes':'no'])});
var csv='﻿'+r.map(function(x){return x.map(function(v){return '"'+String(v).replace(/"/g,'""')+'"'}).join(',')}).join('\r\n');var a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));a.download=document.title+'.csv';a.click()};
paint()})();
JS;
    return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . '</title><style>' . $css . '</style></head><body data-k="' . (int)$p['id'] . '">'
        . '<header><h1 dir="auto">' . $title . '</h1><p dir="auto">' . $e($p['goal']) . '</p></header><main>'
        . '<div class="bar"><input id="q" type="search" placeholder="Search / بحث" hidden><select id="fs" hidden>' . $opt($svc, 'Service') . '</select><select id="fc" hidden>' . $opt($cat, 'Category') . '</select><select id="fo" hidden>' . $opt($obj, 'Objective') . '</select>'
        . '<select id="fu" hidden><option value="">All</option><option value="0">Unpublished</option><option value="1">Published</option></select><button id="ex" type="button" hidden>Expand all</button><button id="co" type="button" hidden>Collapse all</button><button id="csv" type="button" class="pri" hidden>CSV</button>'
        . '<span id="pc" dir="ltr" hidden></span><div class="prog" hidden><i></i></div><noscript>' . count($items) . ' posts</noscript></div>'
        . ((string)$p['visual'] !== '' ? '<details class="dna" open><summary><b>Visual DNA</b></summary><pre dir="ltr">' . $e($p['visual']) . '</pre></details>' : '')
        . $cards . '</main><script>' . $js . '</script></body></html>';
}

/** أعمدة ملف Excel للخطة (نفس الترتيب في التصدير والاستيراد) */
function cpl_xlsx_cols(): array
{
    return ['id' => 'ID', 'seq' => '#', 'status' => t('الحالة'), 'topic' => t('الموضوع'), 'angle' => t('الزاوية'), 'service' => t('الخدمة'), 'audience' => t('الجمهور'), 'category' => t('التصنيف'), 'objective' => t('الهدف التسويقي'), 'qc' => t('فحص الجودة'),
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
        $v = ['id' => (int)$it['id'], 'seq' => (int)$it['seq'], 'status' => cpl_status_label((string)$it['status']), 'topic' => $t['title'] ?? '', 'angle' => $t['angle'] ?? '', 'service' => $t['service'] ?? '', 'audience' => $t['audience'] ?? '', 'category' => cpl_categories()[$t['category'] ?? ''] ?? ($t['category'] ?? ''), 'objective' => $t['objective'] ?? '', 'qc' => implode("\n", $it['qc'] ?? []),
            'linkedin' => $it['linkedin'], 'x' => $it['x'], 'headline' => $it['headline'], 'image_prompt' => $it['image_prompt'], 'video_prompt' => $it['video_prompt']];
        for ($s = 0; $s < 5; $s++) { $sl = $it['carousel'][$s] ?? null; $v['s' . ($s + 1)] = $sl ? trim($sl['title'] . "\n" . $sl['body'] . ($sl['visual'] !== '' ? "\n[" . $sl['visual'] . (!empty($sl['layout']) ? ' | ' . $sl['layout'] : '') . ']' : '')) : ''; }
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
    $stMap = ['approve' => 'approved', 'reject' => 'rejected', 'معتمده' => 'approved', 'اعتماد' => 'approved', 'رفض' => 'rejected', cpl_status_label('approved') => 'approved', cpl_status_label('rejected') => 'rejected', cpl_status_label('draft') => 'draft', 'approved' => 'approved', 'rejected' => 'rejected', 'draft' => 'draft', 'معتمد' => 'approved', 'مرفوض' => 'rejected', 'مسودة' => 'draft'];
    $up = 0; $skip = 0;
    foreach (array_slice($sh, 1) as $row) {
        $it = cpl_item((int)($row[$idx['id']] ?? 0)); if (!$it || $it['plan_id'] !== $planId || $it['linkedin'] === null || !in_array($it['status'], ['draft', 'approved', 'rejected'], true)) { $skip++; continue; }
        $g = function ($k) use ($row, $idx) { return isset($idx[$k]) ? (string)($row[$idx[$k]] ?? '') : null; };
        $f = []; foreach (['linkedin', 'x', 'headline', 'image_prompt', 'video_prompt'] as $k) if (($v = $g($k)) !== null && trim($v) !== '') $f[$k] = $v;
        cpl_save_item($it['id'], $f);
        $car = $it['carousel']; $ch = false;
        for ($s = 0; $s < 5; $s++) { $v = $g('s' . ($s + 1)); if ($v === null || trim($v) === '' || !isset($car[$s])) continue; $ln = preg_split('/\R/u', trim($v)); $vis = '';
            if (count($ln) > 1 && preg_match('/^\[(.*)\]$/u', trim(end($ln)), $m)) { $vis = $m[1]; array_pop($ln); }
            $car[$s] = ['role' => $car[$s]['role'], 'title' => mb_substr(trim((string)array_shift($ln)), 0, 200), 'body' => mb_substr(trim(implode("\n", $ln)), 0, 600), 'visual' => mb_substr(($vis !== '' ? trim(explode(' | ', $vis, 2)[0]) : $car[$s]['visual']), 0, 800), 'layout' => mb_substr(($vis !== '' && strpos($vis, ' | ') !== false ? trim(explode(' | ', $vis, 2)[1]) : ($car[$s]['layout'] ?? '')), 0, 600)]; $ch = true; }
        if ($ch) db()->prepare('UPDATE cp_items SET carousel = ? WHERE id = ?')->execute([json_encode($car, JSON_UNESCAPED_UNICODE), $it['id']]);
        $sv = trim((string)$g('status')); $st = $stMap[$sv] ?? $stMap[mb_strtolower($sv)] ?? null; if ($st && $st !== $it['status']) cpl_set_status([$it['id']], $st);
        $up++;
    }
    return [$up, $skip];
}
