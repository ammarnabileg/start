<?php
/* خطة المحتوى بالذكاء الاصطناعي: هدف + عدد + صور مرجعية ← (تحليل بصري ← مواضيع ← منشورات كاملة على دفعات)
   كل خطوة بتتحفظ في القاعدة، فلو النت فصل أو الجهاز قفل الشغل بيكمل من نفس النقطة (من الصفحة أو من الكرون).
   مابيولّدش صور ولا فيديو: بيجهز النصوص والبرومبتات بس. */

const CPL_BATCH = 1;   // منشور واحد كامل في كل نداء: البرومبت الكامل بيطلع ~3000 كلمة للمنشور، واتنين كانوا بيعدّوا مهلة الموديل
const CPL_MAX = 1000;  // سقف أمان فقط (مش حد ظاهر للمستخدم) عشان السيرفر ما يقعش

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
    $r['media_raw'] = array_values(array_filter(array_map('intval', (array)json_decode((string)($r['media_raw'] ?? ''), true))));
    $r['logo'] = json_decode((string)($r['logo'] ?? ''), true) ?: null;
    $r['txt_opts'] = json_decode((string)($r['txt_opts'] ?? ''), true) ?: null;
    $mids = array_values(array_filter(array_map('intval', (array)json_decode((string)($r['media_ids'] ?? ''), true)))); if (!$mids && !empty($r['media_id'])) $mids = [(int)$r['media_id']]; $r['media_ids'] = $mids;
    $r['id'] = (int)$r['id']; $r['plan_id'] = (int)$r['plan_id']; $r['seq'] = (int)$r['seq']; $r['media_id'] = $r['media_id'] !== null ? (int)$r['media_id'] : null;
    // مراجع خاصة بالمنشور (إن وُجدت) مع روابط مصغّرات لعرضها/تعديلها
    $r['ref_ov'] = null;
    $ro = json_decode((string)($r['refs'] ?? ''), true);
    if (is_array($ro)) {
        $wid = (int)($r['workspace_id'] ?? ws('id'));
        $mk = function ($id) use ($wid) { $id = (int)$id; return ['id' => $id, 'thumb' => url('media', ['file' => $id, 'thumb' => 1]), 'full' => url('media', ['file' => $id])]; };
        $r['ref_ov'] = [
            'refs' => array_map($mk, array_values(array_filter(array_map('intval', (array)($ro['refs'] ?? []))))),
            'products' => array_map($mk, array_values(array_filter(array_map('intval', (array)($ro['products'] ?? []))))),
            'presets' => array_values(array_filter(array_map('strval', (array)($ro['presets'] ?? [])), function ($k) { return in_array($k, ['1', '2', '3', '4', '5'], true); })),
        ];
    }
    unset($r['refs']);
    $r['has_doc'] = !empty($r['design_doc']) ? 1 : 0; unset($r['design_doc']); // لا نرسل المستند الكامل في القائمة (ثقيل) — فقط وجوده
    $hh = json_decode((string)($r['design_doc_hist'] ?? ''), true); $r['n_vers'] = is_array($hh) ? count($hh) : 0; unset($r['design_doc_hist']);
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
    $L = function ($k, $ok = ['ar', 'en', 'both']) use ($in) { return in_array($in[$k] ?? '', $ok, true) ? $in[$k] : 'ar'; };
    $dk = ['msa' => 'Modern Standard Arabic', 'saudi' => 'Saudi Arabic (light Najdi/Hijazi)', 'gulf' => 'Gulf Arabic', 'emirati' => 'Emirati Arabic', 'kuwaiti' => 'Kuwaiti Arabic', 'egyptian' => 'Egyptian Arabic', 'levantine' => 'Levantine Arabic', 'maghrebi' => 'simplified Maghrebi Arabic', 'white' => 'neutral "white" Arabic understood across the Arab world'];
    $dialect = ($in['dialect_k'] ?? '') === 'other' ? mb_substr(trim((string)($in['dialect_other'] ?? '')), 0, 120) : ($dk[$in['dialect_k'] ?? ''] ?? mb_substr(trim((string)($in['dialect'] ?? '')), 0, 60));
    $tone = implode('، ', array_filter(array_merge(array_map('strval', (array)($in['tone_k'] ?? [])), [trim((string)($in['tone'] ?? ''))])));
    $cta = trim((string)($in['cta'] ?? '')) !== '' ? (string)$in['cta'] : (string)($in['cta_k'] ?? '');
    $in['tone'] = $tone; $in['cta'] = $cta;
    $L4 = function ($k) use ($in) { return in_array($in[$k] ?? '', ['ar', 'en', 'ar_en', 'en_ar'], true) ? $in[$k] : 'ar'; };
    $lli = isset($in['lang_li']) ? $L4('lang_li') : 'ar';
    $opts = ['lang' => $lli, 'lang_li' => $lli, 'lang_x' => isset($in['lang_x']) ? $L4('lang_x') : $lli, 'lang_img' => isset($in['lang_img']) ? $L('lang_img', ['ar', 'en', 'ar_en', 'en_ar', 'none']) : $lli, 'lang_vo' => isset($in['lang_vo']) ? $L4('lang_vo') : $lli,
        'dialect' => $dialect,
        'audience' => mb_substr(trim((string)($in['audience'] ?? '')), 0, 800), 'about' => mb_substr(trim((string)($in['about'] ?? '')), 0, 1500), 'services' => array_slice(array_map(function ($s) { return mb_substr((string)$s, 0, 150); }, (array)($in['services'] ?? [])), 0, 20),
        'tone' => mb_substr(trim((string)($in['tone'] ?? '')), 0, 200), 'cta' => mb_substr(trim((string)($in['cta'] ?? '')), 0, 300), 'avoid' => mb_substr(trim((string)($in['avoid'] ?? '')), 0, 600),
        'video' => !empty($in['video']), 'carousel' => !isset($in['carousel']) || !empty($in['carousel']), 'humanize' => !isset($in['humanize']) || !empty($in['humanize']), 'qc' => !isset($in['qc']) || !empty($in['qc']),
        'logo_x' => max(0, min(100, (int)($in['logo_x'] ?? 86))), 'logo_y' => max(0, min(100, (int)($in['logo_y'] ?? 9))), 'logo_w' => max(8, min(35, (int)($in['logo_w'] ?? 18))), 'logo_op' => max(30, min(100, (int)($in['logo_op'] ?? 100))),
        'font' => preg_match('/^[\w .-]{1,40}$/', (string)($in['font'] ?? 'auto')) ? (string)$in['font'] : 'auto',
        'img_q' => in_array($in['img_q'] ?? '', ['medium', 'high'], true) ? (string)$in['img_q'] : 'high',
        'style' => array_key_exists((string)($in['style'] ?? 'auto'), cpl_styles()) ? (string)$in['style'] : 'auto',
        'aspect' => in_array($in['aspect'] ?? '', ['4:5', '1:1', '9:16', '16:9'], true) ? (string)$in['aspect'] : '4:5',
        'design_mode' => in_array($in['design_mode'] ?? '', ['photo', 'layout', 'ai'], true) ? (string)$in['design_mode'] : 'ai',
        'layout' => array_key_exists((string)($in['layout'] ?? 'full'), cpl_layouts()) ? (string)$in['layout'] : 'full',
        'topics_first' => !isset($in['topics_first']) || !empty($in['topics_first']),
        'txt_pos' => in_array($in['txt_pos'] ?? '', ['auto', 'top', 'bottom'], true) ? (string)$in['txt_pos'] : 'auto',
        'txt_size' => in_array($in['txt_size'] ?? '', ['s', 'm', 'l'], true) ? (string)$in['txt_size'] : 'm',
        'txt_scale' => isset($in['txt_scale']) ? max(50, min(180, (int)$in['txt_scale'])) : 100,
        'txt_align' => in_array($in['txt_align'] ?? '', ['auto', 'right', 'center', 'left'], true) ? (string)$in['txt_align'] : 'auto',
        'txt_y' => (isset($in['txt_y']) && is_numeric($in['txt_y'])) ? max(2, min(92, (int)$in['txt_y'])) : null,
        'txt_variant' => in_array($in['txt_variant'] ?? '', ['auto', 'box', 'underline', 'color'], true) ? (string)$in['txt_variant'] : 'auto',
        'txt_effect' => in_array($in['txt_effect'] ?? '', ['none', 'shadow', 'outline', 'sticker', '3d', 'gold', 'glow'], true) ? (string)$in['txt_effect'] : 'none',
        'txt_accent' => (preg_match('/^#?[0-9a-fA-F]{6}$/', (string)($in['txt_accent'] ?? '')) && strtolower(ltrim((string)$in['txt_accent'], '#')) !== '10b981') ? ('#' . ltrim((string)$in['txt_accent'], '#')) : '',
        'products' => array_slice(array_values(array_filter(array_map('intval', (array)($in['products'] ?? [])), function ($id) use ($wid) { $m = media_get($wid, $id); return $id > 0 && $m && empty($m['deleted_at']) && media_kind((string)$m['mime']) === 'image'; })), 0, 6),
        'ref_presets' => array_values(array_unique(array_filter(array_map('strval', (array)($in['ref_presets'] ?? [])), function ($k) { return in_array($k, ['1', '2', '3', '4', '5'], true); }))),
        'logo_tint' => in_array($in['logo_tint'] ?? '', ['orig', 'white', 'black'], true) ? $in['logo_tint'] : 'orig', 'logo_plate' => in_array($in['logo_plate'] ?? '', ['none', 'light', 'dark'], true) ? $in['logo_plate'] : 'none', 'logo_on' => !isset($in['logo_on']) || !empty($in['logo_on'])];
    $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 150) ?: mb_substr(preg_replace('/\s+/u', ' ', $goal), 0, 60);
    $hasLogo = function_exists('kb_logo') && kb_logo($wid);
    db()->prepare('INSERT INTO cp_plans (workspace_id, user_id, name, goal, cnt, opts, refs, stage, status) VALUES (?,?,?,?,?,?,?,?, "generating")')
        ->execute([$wid, (int)(current_user()['id'] ?? 0), $name, $goal, $n, json_encode($opts, JSON_UNESCAPED_UNICODE), json_encode($refs), ($refs || $hasLogo || !empty($opts['products']) || !empty($opts['ref_presets'])) ? 'visual' : 'outline']);
    $nid = (int)db()->lastInsertId(); cpl_log($nid, t('اتعملت الخطة: %s منشور، %s صورة مرجعية%s.', $n, count($refs), $hasLogo ? t('، والشعار من ملف الشركة') : '')); return $nid;
}

function cpl_opts(array $p): array { return (json_decode((string)$p['opts'], true) ?: []) + ['lang' => 'ar', 'dialect' => '', 'audience' => '', 'services' => [], 'tone' => '', 'cta' => '', 'avoid' => '', 'about' => '', 'font' => 'auto', 'video' => true, 'carousel' => true, 'humanize' => true, 'qc' => true, 'img_q' => 'high', 'style' => 'auto', 'products' => [], 'ref_presets' => [], 'aspect' => '4:5',
    'txt_pos' => 'auto', 'txt_size' => 'm', 'txt_variant' => 'auto', 'txt_effect' => 'none', 'txt_accent' => '', 'txt_scale' => 100, 'txt_align' => 'auto', 'txt_y' => null,
    'design_mode' => 'ai', 'layout' => 'full', 'topics_first' => true]; }

/** نسبة/حجم الصورة المختارة للخطة (4:5 طولي افتراضيًا). */
function cpl_aspect(array $p): string { $a = (string)(cpl_opts($p)['aspect'] ?? '4:5'); return in_array($a, ['4:5', '1:1', '9:16', '16:9'], true) ? $a : '4:5'; }

/** ستايلات تصميم جاهزة: المستخدم يختار واحد، أو يرفع صورة ريفرنس (refs)، أو يسيبها تلقائي. */
function cpl_styles(): array
{
    return [
        'auto' => [t('تلقائي / من المرجع'), ''],
        'cinematic' => [t('سينمائي غامق'), 'ART-DIRECTION STYLE: cinematic dark premium scene, moody low-key lighting, strong brand-colour rim/accent glow, volumetric light, reflections, shallow depth of field, photoreal, award-winning advertising look.'],
        'neon_sport' => [t('نيون رياضي'), 'ART-DIRECTION STYLE: vibrant energetic sports aesthetic, dynamic sense of motion, glowing neon brand-colour light-trails and energy streaks, high contrast, bold and punchy.'],
        'luxury_gold' => [t('فخامة ذهبية'), 'ART-DIRECTION STYLE: luxury premium aesthetic, deep dark background, elegant gold and brand-colour accents, soft specular reflections, refined high-end mood.'],
        'clean_product' => [t('منتج نظيف'), 'ART-DIRECTION STYLE: clean product-hero studio shot, soft bright pastel or brand-colour seamless background, gentle soft shadows, crisp minimal premium e-commerce composition.'],
        'vibrant_pop' => [t('بوب زاهي'), 'ART-DIRECTION STYLE: playful vibrant pop aesthetic, bright saturated brand colours, bold geometric shapes and fun props, cheerful and scroll-stopping.'],
        'corporate_3d' => [t('كوربوريت ثري دي'), 'ART-DIRECTION STYLE: modern corporate scene with sleek 3D elements, app/device mockups, clean depth, smooth brand-colour gradients, professional tech feel.'],
        'nature_fresh' => [t('طبيعي منعش'), 'ART-DIRECTION STYLE: fresh natural daylight scene with water, greenery or fruit elements, airy clean composition, vivid and refreshing.'],
    ];
}

/** معرّفات صور المنتج اللي رفعها المستخدم (اختياري) — تُرسل لموديل الصور ليظهر المنتج نفسه في التصميم. */
function cpl_prod_ids(array $p): array { return array_values(array_filter(array_map('intval', (array)(cpl_opts($p)['products'] ?? [])))); }

/** مراجع خاصة بمنشور واحد (تتجاوز مراجع الخطة). ترجّع null لو لا توجد (نستخدم مراجع الخطة). */
function cpl_item_ref_override(array $it): ?array
{
    $raw = trim((string)($it['refs'] ?? '')); if ($raw === '') return null;
    $d = json_decode($raw, true); if (!is_array($d)) return null;
    return [
        'refs' => array_values(array_filter(array_map('intval', (array)($d['refs'] ?? [])))),
        'products' => array_values(array_filter(array_map('intval', (array)($d['products'] ?? [])))),
        'presets' => array_values(array_filter(array_map('strval', (array)($d['presets'] ?? [])), function ($k) { return in_array($k, ['1', '2', '3', '4', '5'], true); })),
    ];
}

/** يحضّر ملفات مرجعية لموديل الصور: صور المنتج أولًا (تُعاد بأمانة) ثم تصميمات الستايل، مع توجيه نصّي مميّز لكل نوع.
 *  يرجّع [$files, $hint]. المتصل مسؤول عن حذف الملفات المؤقتة بعد الاستخدام. */
function cpl_ref_images(array $p, int $wid, int $max = 4, ?array $ov = null): array
{
    $files = []; $prod = 0; $style = 0;
    $load = function ($rid) use ($wid, &$files) {
        $rm = media_get($wid, (int)$rid);
        if ($rm && ($rd = cpl_small_image(media_file_path($wid, $rm), 1024))) {
            $tf = tempnam(sys_get_temp_dir(), 'cpr') . '.jpg';
            file_put_contents($tf, base64_decode(substr($rd, strpos($rd, ',') + 1))); $files[] = $tf; return true;
        }
        return false;
    };
    // عند وجود مراجع خاصة بالمنشور ($ov) تحلّ محل مراجع الخطة بالكامل
    $prodIds = $ov !== null ? array_values(array_filter(array_map('intval', (array)($ov['products'] ?? [])))) : cpl_prod_ids($p);
    $styleIds = $ov !== null ? array_values(array_filter(array_map('intval', (array)($ov['refs'] ?? [])))) : (json_decode((string)$p['refs'], true) ?: []);
    $presetKeys = $ov !== null ? (array)($ov['presets'] ?? []) : (array)(cpl_opts($p)['ref_presets'] ?? []);
    foreach ($prodIds as $pid) { if (count($files) >= $max) break; if ($load($pid)) $prod++; }
    foreach (array_slice($styleIds, 0, $max) as $rid) { if (count($files) >= $max) break; if ($load($rid)) $style++; }
    // نماذج مرجعية جاهزة: تُقرأ مباشرة من القرص (لا تُخزَّن في مكتبة الوسائط)
    foreach ($presetKeys as $pk) {
        if (count($files) >= $max) break;
        $pf = dirname(__DIR__, 2) . '/assets/ref-presets/ref' . (int)$pk . '.webp';
        if (is_file($pf) && ($rd = cpl_small_image($pf, 1024))) {
            $tf = tempnam(sys_get_temp_dir(), 'cpr') . '.jpg';
            file_put_contents($tf, base64_decode(substr($rd, strpos($rd, ',') + 1))); $files[] = $tf; $style++;
        }
    }
    $hint = '';
    if ($prod) $hint .= "\nThe first {$prod} attached image(s) show the EXACT real PRODUCT the user chose to feature — it MUST appear in the design as the clear hero. Reproduce it faithfully: real shape, colour, packaging, proportions and its real label/logo kept intact and legible. Integrate it naturally into the scene with correct perspective, lighting, shadow and reflections. Do NOT redesign the product, do NOT invent text on it, and do NOT omit it.";
    if ($style) $hint .= "\nThe " . ($prod ? "remaining {$style}" : (string)$style) . " attached image(s) are STYLE REFERENCES — infer their shared visual system (colour palette, lighting mood, contrast, composition and polish) and apply it. Do not copy any subject, text, person or logo from them.";
    return [$files, $hint];
}

/** جودة توليد صور الخطة (high افتراضيًا للتصميمات بمستوى الوكالات؛ يقدر صاحب الخطة يخفّضها لتوفير الكريدت). */
function cpl_img_quality(array $p): string { $q = (string)(cpl_opts($p)['img_q'] ?? 'high'); return in_array($q, ['low', 'medium', 'high'], true) ? $q : 'high'; }
/** تكلفة الصورة الواحدة المقدّرة بالدولار — فقط عندما يكون السعر معروفًا (OpenAI). غير ذلك null (لا نخترع أرقامًا). */
function cpl_img_unit_cost(array $p): ?float
{
    if (!function_exists('img_provider') || img_provider() !== 'openai' || !function_exists('img_openai_cost')) return null;
    $size = cpl_aspect($p) === '1:1' ? '1024x1024' : '1024x1536';
    return round(img_openai_cost(cpl_img_quality($p), $size), 4);
}

/** البرومبت الأساسي = برومبت المستخدم بالحرف (app/prompts/content_plan.md) + إعدادات الخطة. قسم HTML اتشال لأن السيستم هو اللي بيعرض ويصدّر. */
function cpl_master(array $p): string
{
    static $m = null; if ($m === null) $m = (string)@file_get_contents(__DIR__ . '/../prompts/content_plan.md');
    return str_replace('NUMBER_OF_POSTS', (string)(int)$p['cnt'], $m);
}

function cpl_rules(array $p): string
{
    $o = cpl_opts($p);
    // خطة المحتوى تحقن أسلوب الكتابة بنفسها حسب خيار الخطة؛ نوقف الحقن العام (ai_with_style) لتجنّب التكرار
    $GLOBALS['ai_no_humanize_style'] = true;
    $GLOBALS['ai_usage_ref'] = 'plan:' . (int)$p['id']; // لنسب تكلفة الذكاء لهذه الحملة
    $ar = 'Arabic' . ($o['dialect'] !== '' ? ' (' . $o['dialect'] . ' dialect — use this dialect, NOT Modern Standard Arabic)' : ' (clear Modern Standard Arabic unless the KB shows another brand voice)');
    $nm = function ($k) use ($o, $ar) { $v = $o[$k] ?? $o['lang']; return [
        'ar' => 'ONLY ' . $ar . ' (no English words except unavoidable technical/brand terms)',
        'en' => 'ONLY English (no Arabic at all)',
        'both' => 'BILINGUAL — include BOTH: ' . $ar . ' first, then the English version right after (both must appear)',
        'ar_en' => 'BILINGUAL — include BOTH: ' . $ar . ' as the primary text, then the English version right after it, shorter/secondary (both must appear)',
        'en_ar' => 'BILINGUAL — include BOTH: English as the primary text, then the ' . $ar . ' version right after it, shorter/secondary (both must appear)',
        'none' => 'NO text on the image or slide at all (purely visual; put the message in the caption)'][$v] ?? $ar; };
    $lang = "LinkedIn caption: " . $nm('lang_li') . ". | X post: " . $nm('lang_x') . ". | headline and on-slide/image copy: " . $nm('lang_img') . ". | video voice-over dialogue: " . $nm('lang_vo');
    static $craft = null; if ($craft === null) $craft = (string)@file_get_contents(__DIR__ . '/../prompts/image_craft.md');
    return cpl_master($p) . "\n\n" . $craft
        . "\n\nPLAN SETTINGS\n"
        . "*** LANGUAGE — MANDATORY, OBEY EXACTLY FOR EVERY FIELD ***\nThe user explicitly chose the languages below. Follow them to the letter. When a field says BILINGUAL you MUST output BOTH languages in that field (never only one). When it says ONLY one language, use just that one. Never silently switch to Arabic-only or English-only.\n- {$lang}.\n- The image-generation prompts and video directions you write (the instructions to the image/video model) are themselves in professional English, but the visible on-image text must follow the headline/on-slide language above.\n"
        . ($o['dialect'] !== '' ? "- Any Arabic you write uses the {$o['dialect']} dialect (not MSA), including voice-over.\n" : '')
        . ($o['tone'] !== '' ? "- Brand tone: {$o['tone']}\n" : '')
        . ($o['cta'] !== '' ? "- Preferred CTA: {$o['cta']}\n" : '')
        . ($o['avoid'] !== '' ? "- Avoid: {$o['avoid']}\n" : '')
        . (!$o['video'] ? "- The user turned off video for this plan: return video_prompt as an empty string.\n" : '')
        . (!$o['carousel'] ? "- The user turned off carousels for this plan: return carousel as an empty array.\n" : '')
        . "- LOGO: never draw, write or invent any logo, brand name, wordmark or watermark in images, slides or video. The system places the real logo afterwards in the " . cpl_logo_corner($p) . " corner: keep that corner area (about 260x160px) clean and calm.\n"
        . "- Safe area: keep the headline, any text and every key element at least 100px (about 9%) inside each edge of the 1080x1350 frame; nothing touches or is cropped by an edge; generous padding around the headline; headline at most 3 short lines in a clean bold sans-serif, spelled exactly, high contrast.\n"
        . ($o['humanize'] ? "\n=== WRITING STYLE (write the final human version directly, never a draft to fix later; never change the JSON format):\n" . ai_humanize_rules() . "\n" : '');
}

function cpl_brief(array $p): string
{
    $o = cpl_opts($p); $wid = (int)ws('id'); $pal = function_exists('kb_logo_palette') ? kb_logo_palette($wid) : [];
    $cb = function_exists('cpl_company_brief') ? cpl_company_brief($wid) : '';
    // الذكاء الموسّع: نضيف قاعدة المعرفة كاملة فوق الموجز؛ البسيط يكتفي بالموجز المثبّت
    $full = (function_exists('ai_kb_mode') && ai_kb_mode($wid) === 'extended' && function_exists('kb_compile')) ? trim(kb_compile($wid, '', false)) : '';
    return ($cb !== '' ? "COMPANY BRIEF (think like this company; use ONLY facts/prices/claims found here or in the KB, never invent):\n" . $cb . "\n\n" : "")
        . ($full !== '' ? "FULL KNOWLEDGE BASE (detailed source — use it for depth, specifics and exact prices):\n" . $full . "\n\n" : "")
        . "Campaign goal (build the strategy around it): " . $p['goal']
        . ($o['about'] !== '' ? "\nAbout the company (context): " . $o['about'] : '')
        . ($o['audience'] !== '' ? "\nTarget audience / decision-makers: " . $o['audience'] : '')
        . ($o['services'] ? "\nFocus services: " . implode('; ', $o['services']) : '')
        . ($pal ? "\nLogo colors (hex): " . implode(', ', $pal) : '')
        . ((string)$p['visual'] !== '' ? "\nVisual identity extracted from the reference DESIGNS the user supplied — these reference designs ARE the target look: every image_prompt you write MUST follow this identity (its palette, typography feel, composition, full-canvas design language and mood), not a generic or minimalist default:\n" . $p['visual'] : "\nNo reference images were supplied: derive the visual identity from the KB and logo colors.")
        . (cpl_prod_ids($p) ? "\n\nPRODUCT TO FEATURE: the user attached a real PRODUCT image for this campaign. In EVERY image_prompt you write, make that product the clear HERO of the design — describe it being featured prominently and faithfully (real shape, packaging, label), integrated into the scene. Never write an image_prompt that omits the product or replaces it with an unrelated object; build the creative concept AROUND showcasing this product." : '');
}

/** خطوة واحدة من التوليد. ترجع حالة الخطة بعدها. آمنة لو اتنادت من أكتر من مكان (قفل). */
function cpl_step(int $id): array
{
    $p = cpl_plan($id); if (!$p) throw new RuntimeException(t('الخطة غير موجودة.'));
    if ($p['status'] !== 'generating') return cpl_status($p);
    $lk = 'cpl_' . $id; $g = db()->query('SELECT GET_LOCK(' . db()->quote($lk) . ', 0)')->fetchColumn();
    if (!$g) return cpl_status($p) + ['busy' => true];
    $done = false;
    register_shutdown_function(function () use (&$done, $id, $lk) { if ($done) return; $e = error_get_last();
        cpl_log($id, t('انتهت مهلة السيرفر أثناء الخطوة. سنعيد المحاولة.') . ($e ? ' (' . mb_substr((string)$e['message'], 0, 120) . ')' : ''), 'err');
        try { db()->prepare('UPDATE cp_plans SET error = ?, fails = fails + 1, status = IF(fails + 1 >= 3, "paused", status) WHERE id = ?')->execute([t('انتهت مهلة السيرفر أثناء هذه الخطوة. سنعيد المحاولة تلقائيًا.') . ($e ? ' (' . mb_substr((string)$e['message'], 0, 120) . ')' : ''), $id]); db()->query('SELECT RELEASE_LOCK(' . db()->quote($lk) . ')'); } catch (Throwable $x) {} });
    $t0 = microtime(true);
    try {
        @set_time_limit(420); $GLOBALS['ai_timeout'] = 320; // نداء واحد ممكن ياخد دقيقتين مع موديل بطيء
        $GLOBALS['__kb_skip'] = true; // الملخص المضغوط بيتبعت في البريف؛ ما نحقنش كل قاعدة المعرفة (توفير تكلفة)
        if ($p['stage'] === 'visual') cpl_do_visual($p);
        elseif ($p['stage'] === 'outline') cpl_do_outline($p);
        else cpl_do_posts($p);
        db()->prepare('UPDATE cp_plans SET error = NULL, fails = 0 WHERE id = ?')->execute([$id]);
    } catch (Throwable $e) {
        $f = (int)$p['fails'] + 1;
        cpl_log($id, t('فشلت الخطوة بعد %s ثانية (محاولة %s من 3): %s', round(microtime(true) - $t0), $f, $e->getMessage()), 'err');
        if ($f >= 3) cpl_log($id, t('توقف التوليد بعد 3 محاولات فاشلة. اضغط «أكمل من حيث توقف».'), 'err');
        // 3 محاولات فاشلة ورا بعض = نوقف ونستنى المستخدم (مش نصرف رصيد على الفاضي)
        db()->prepare('UPDATE cp_plans SET error = ?, fails = ?, status = IF(? >= 3, "paused", status) WHERE id = ?')->execute([mb_substr($e->getMessage(), 0, 500), $f, $f, $id]);
    } finally { $done = true; unset($GLOBALS['__kb_skip']); db()->query('SELECT RELEASE_LOCK(' . db()->quote($lk) . ')'); }
    return cpl_status(cpl_plan($id));
}

function cpl_status(array $p): array
{
    $st = db()->prepare('SELECT COUNT(*) total, SUM(linkedin IS NOT NULL) ready FROM cp_items WHERE plan_id = ?'); $st->execute([(int)$p['id']]); $c = $st->fetch();
    $h = db()->prepare('SELECT COUNT(*) FROM cp_items WHERE plan_id = ? AND humanized = 1'); $h->execute([(int)$p['id']]);
    return ['id' => (int)$p['id'], 'status' => $p['status'], 'stage' => $p['stage'], 'count' => (int)$p['cnt'], 'topics' => (int)$c['total'], 'ready' => (int)$c['ready'], 'human' => (int)$h->fetchColumn(), 'hz' => !empty(cpl_opts($p)['humanize']), 'error' => (string)($p['error'] ?? ''), 'log' => json_decode((string)($p['log'] ?? ''), true) ?: []];
}

/** تكلفة الذكاء الفعلية لهذه الحملة من بيانات ai_usage الحقيقية (موسومة بـ plan:ID). لا أرقام مخترعة. */
function cpl_cost(int $planId): array
{
    $wid = (int)ws('id');
    $st = db()->prepare("SELECT task, provider, COUNT(*) n, COALESCE(SUM(cost),0) c, COALESCE(SUM(tokens_in),0) ti, COALESCE(SUM(tokens_out),0) too FROM ai_usage WHERE workspace_id = ? AND ref = ? GROUP BY task, provider ORDER BY c DESC");
    $st->execute([$wid, 'plan:' . $planId]); $rows = $st->fetchAll();
    $total = 0.0; $byTask = []; $text = 0.0; $media = 0.0;
    foreach ($rows as $r) { $c = (float)$r['c']; $total += $c;
        $isMedia = stripos($r['task'], 'image') !== false || stripos($r['task'], 'video') !== false || stripos($r['task'], 'img') !== false;
        if ($isMedia) $media += $c; else $text += $c;
        $byTask[] = ['task' => (string)$r['task'], 'provider' => (string)$r['provider'], 'n' => (int)$r['n'], 'cost' => round($c, 4)]; }
    $ap = (int)db()->query('SELECT COUNT(*) FROM cp_items WHERE plan_id = ' . (int)$planId . " AND status IN ('approved','scheduled','queued','published')")->fetchColumn();
    return ['has_data' => !empty($rows), 'total' => round($total, 4), 'text' => round($text, 4), 'media' => round($media, 4),
        'approved' => $ap, 'per_approved' => $ap ? round($total / $ap, 4) : null, 'by' => $byTask, 'currency' => 'USD'];
}

function cpl_do_visual(array $p): void
{
    $wid = (int)ws('id'); $parts = [['type' => 'text', 'text' => 'Visual references for the brand campaign.']];
    foreach (array_slice(json_decode((string)$p['refs'], true) ?: [], 0, 12) as $mid) {
        $m = media_get($wid, (int)$mid); if (!$m) continue;
        $u = cpl_small_image(media_file_path($wid, $m)) ?: cpl_small_image(media_thumb_path($wid, $m)); if ($u) $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $u]];
    }
    $lg = function_exists('kb_logo') ? kb_logo($wid) : null; // اللوجو من ملف الشركة: عشان مكانه وألوانه تبقى حقيقية
    if ($lg && ($lu = cpl_small_image(media_file_path($wid, $lg), 512))) { $parts[] = ['type' => 'text', 'text' => 'The last image is the official company logo (not a reference design). Use it for logo colors and recommended logo placement.']; $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $lu]]; }
    $vis = ''; $nImg = count(array_filter($parts, function ($x) { return $x['type'] === 'image_url'; }));
    if (count($parts) > 1) {
        if ((int)$p['fails'] >= 2) { cpl_log((int)$p['id'], t('تخطّينا تحليل الصور بعد محاولتين فاشلتين، ونكمل من ملف الشركة وألوان الشعار.'), 'warn'); db()->prepare('UPDATE cp_plans SET visual = "", stage = "outline", error = ? WHERE id = ?')->execute([t('تعذر تحليل الصور المرجعية؛ أكملنا من ملف الشركة وألوان الشعار.'), (int)$p['id']]); return; }
        cpl_log((int)$p['id'], t('إرسال %s صورة (المرجعية + الشعار) للذكاء الاصطناعي لاستخراج الهوية البصرية…', $nImg)); $tv = microtime(true);
        $r = ai_chat([['role' => 'system', 'content' => "VISUAL_DNA. Act as a Commercial Art Director. Analyze the reference images to extract the visual identity: colors (hex values), typography (style, weight, case, Arabic/Latin pairing), lighting, composition, logo placement, and advertising style; also photography vs illustration vs 3D, textures, negative space, headline placement and mood. Write a reusable visual DNA in English, 150-250 words, as plain text grouped by those headings. Describe the style only; never copy brand names, text or specific people from the references."], ['role' => 'user', 'content' => $parts]], 900, 'analyze');
        $vis = trim((string)($r['text'] ?? ''));
    }
    if (isset($tv)) cpl_log((int)$p['id'], t('الهوية البصرية جاهزة في %s ثانية: %s', round(microtime(true) - $tv), mb_substr($vis, 0, 160) . '…'), 'ok');
    db()->prepare('UPDATE cp_plans SET visual = ?, stage = "outline" WHERE id = ?')->execute([mb_substr($vis, 0, 4000), (int)$p['id']]);
}

/** سجل حي لكل خطوة (آخر 200 سطر): المستخدم بيشوف كل اللي بيحصل */
function cpl_log(int $id, string $msg, string $kind = 'info'): void
{
    try { $st = db()->prepare('SELECT log FROM cp_plans WHERE id = ?'); $st->execute([$id]); $l = json_decode((string)$st->fetchColumn(), true) ?: [];
        $l[] = ['t' => date('H:i:s'), 'm' => mb_substr($msg, 0, 400), 'k' => $kind, 'src' => PHP_SAPI === 'cli' || !empty($GLOBALS['cpl_cron']) ? 'bg' : 'page'];
        db()->prepare('UPDATE cp_plans SET log = ? WHERE id = ?')->execute([json_encode(array_slice($l, -200), JSON_UNESCAPED_UNICODE), $id]); } catch (Throwable $e) {}
}

/** صورة مصغرة data URI (JPEG، أطول ضلع $max) عشان طلب الرؤية يفضل صغير */
function cpl_small_image(string $f, int $max = 1024): string
{
    if (!is_file($f) || !function_exists('imagecreatefromstring')) return '';
    $im = @imagecreatefromstring((string)file_get_contents($f)); if (!$im) return '';
    $w = imagesx($im); $h = imagesy($im); $k = min(1, $max / max($w, $h)); $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
    $o = imagecreatetruecolor($nw, $nh); imagefill($o, 0, 0, imagecolorallocate($o, 255, 255, 255)); imagecopyresampled($o, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start(); imagejpeg($o, null, 82); $b = (string)ob_get_clean(); imagedestroy($im); imagedestroy($o);
    return 'data:image/jpeg;base64,' . base64_encode($b);
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
    cpl_log((int)$p['id'], t('تخطيط %s موضوع بالاستراتيجية من ملف الشركة والهدف… (قد يستغرق دقيقة أو أكثر)', $n)); $to = microtime(true);
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => cpl_brief($p)]], min(8000, 400 + $n * 110), 'cp_plan');
    $j = kb_parse_json((string)($r['text'] ?? '')); $topics = array_values(array_filter((array)($j['topics'] ?? []), 'is_array'));
    if (!$topics) throw new RuntimeException(t('لم يُرجع الذكاء الاصطناعي مواضيع صالحة. حاول مرة أخرى.'));
    $topics = array_slice($topics, 0, $n);
    $ins = db()->prepare('INSERT INTO cp_items (plan_id, workspace_id, seq, topic, status) VALUES (?,?,?,?, "pending")');
    db()->prepare('DELETE FROM cp_items WHERE plan_id = ?')->execute([(int)$p['id']]);
    foreach ($topics as $i => $t) $ins->execute([(int)$p['id'], (int)ws('id'), $i + 1, json_encode(array_map(function ($v) { return is_string($v) ? mb_substr($v, 0, 300) : ''; }, array_intersect_key($t, array_flip(['title', 'angle', 'category', 'service', 'audience', 'objective']))), JSON_UNESCAPED_UNICODE)]);
    // مسار ذكي: نتوقف لمراجعة الأفكار قبل كتابة النصوص الكاملة — نولّد نصوص المعتمدة فقط (توفير كريدت على المرفوض)
    $tf = !empty(cpl_opts($p)['topics_first']);
    db()->prepare('UPDATE cp_plans SET stage = "posts", status = ?, cnt = ? WHERE id = ?')->execute([$tf ? 'topics' : 'generating', count($topics), (int)$p['id']]);
    cpl_log((int)$p['id'], $tf
        ? t('%s فكرة جاهزة في %s ثانية. راجعها واحذف ما لا تريده، ثم ولّد النصوص للأفكار المعتمدة فقط.', count($topics), round(microtime(true) - $to))
        : t('%s موضوع جاهز في %s ثانية. أول موضوع: %s', count($topics), round(microtime(true) - $to), (string)($topics[0]['title'] ?? '')), 'ok');
}

/** مراجعة Humanizer: المهارة كاملة على نصوص المنشورات (لينكدإن، X، العنوان، الكاروسيل). البرومبتات الإنجليزي مابتتلمسش. */
function cpl_do_humanize(array $p, array $rows): void
{
    $list = []; foreach ($rows as $r) { $car = json_decode((string)$r['carousel'], true) ?: [];
        $list[] = ['n' => (int)$r['seq'], 'linkedin' => $r['linkedin'], 'x' => $r['x'], 'headline' => $r['headline'], 'carousel' => array_map(function ($s) { return ['title' => $s['title'] ?? '', 'body' => $s['body'] ?? '']; }, $car)]; }
    $sys = "CONTENT_HUMANIZE_JSON\nYou are the editor. Apply the Humanizer skill below to every text field, in EMBEDDED MODE (return only the final text). The copy is social media for a company; keep its language and dialect, keep hashtags at the end, keep the CTA, keep x within 270 characters, keep headline 2-6 words, and keep the «guillemets» around the one highlighted word in the headline exactly as they are. Do not add or drop any fact, number, name, price or claim. Do not translate.\n"
        . "Return ONLY JSON with the same shape: {\"posts\":[{\"n\":1,\"linkedin\":\"...\",\"x\":\"...\",\"headline\":\"...\",\"carousel\":[{\"title\":\"...\",\"body\":\"...\"}]}]}\n\n=== HUMANIZER SKILL ===\n" . ai_humanizer_skill();
    cpl_log((int)$p['id'], t('مراجعة الأسلوب (Humanizer) للمنشور %s…', implode(' و', array_column($list, 'n')))); $th = microtime(true);
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
    cpl_log((int)$p['id'], t('روجع %s منشور في %s ثانية.', $done, round(microtime(true) - $th)), 'ok');
}

function cpl_do_posts(array $p): void
{
    // اكتب ← فحص الجودة ← تصليح تلقائي للي فيه مشاكل (لحد محاولتين لكل منشور) ← المنشور اللي بعده
    // لو المستخدم قافل فحص الجودة، بنعدّي التصليح التلقائي خالص (بيوفّر استهلاك الكريدت)
    if (cpl_opts($p)['qc'] ?? true) foreach (cpl_items((int)$p['id']) as $it) if ($it['status'] === 'draft' && $it['qc'] && (int)$it['fix_tries'] < 2) { cpl_do_fix($p, $it); return; }
    $st = db()->prepare('SELECT * FROM cp_items WHERE plan_id = ? AND linkedin IS NULL ORDER BY seq LIMIT ' . CPL_BATCH); $st->execute([(int)$p['id']]);
    $rows = $st->fetchAll();
    if (!$rows) { db()->prepare('UPDATE cp_plans SET status = "review", stage = "done" WHERE id = ?')->execute([(int)$p['id']]); cpl_log((int)$p['id'], t('اكتملت الخطة. جاهزة للمراجعة والموافقة.'), 'ok'); return; }
    $list = []; foreach ($rows as $r) { $t = json_decode((string)$r['topic'], true) ?: []; $list[] = ['n' => (int)$r['seq']] + array_intersect_key($t, array_flip(['title', 'angle', 'category', 'service', 'audience', 'objective'])); }
    $st2 = db()->prepare('SELECT seq, headline FROM cp_items WHERE plan_id = ? AND linkedin IS NOT NULL ORDER BY seq'); $st2->execute([(int)$p['id']]); $used = array_filter(array_column($st2->fetchAll(), 'headline'));
    // بناء المخطط حسب المطلوب فقط: لا نطلب من الموديل video_prompt أو carousel لو المستخدم مش عايزهم (توفير توكنز فعلي)
    $o = cpl_opts($p);
    $fld = [];
    $fld[] = '"linkedin":"130-220 words"';
    $fld[] = '"x":"max 280 characters incl. hashtags"';
    $fld[] = '"headline":"2-6 word poster headline that is GRAMMATICALLY CORRECT, natural and fluent in the headline language — read it aloud; it must sound like a real professional ad line a native copywriter wrote, never an awkward literal or machine-translated phrasing (e.g. write «راقب شبكتك قبل أن تتوقف» not «راقب الشبكة قبل لا يوقف الخط»). Wrap the SINGLE most important word (one word only, the punchy keyword) in «» guillemets — this word will be coloured as an accent, so pick the word that carries the message"';
    if (($o['design_mode'] ?? 'ai') === 'ai') {
        // وضع الذكاء: الموديل يرسم التصميم كاملًا، فالبرومبت يُكتب كبريف تصميم جرافيك مش كوصف تصوير
        $fld[] = '"image_prompt":"150-220 words English describing a COMPLETE, FINISHED social-media DESIGN that fills the whole 1080x1350 canvas edge-to-edge (NOT a bare photograph, NOT a photo inside a frame) — a polished creative-agency post a senior designer would build in a design tool. Describe it as a DESIGN: the overall layout and grid; brand-coloured shapes, panels, blocks or geometric accents; how the hero imagery (a real person in a decisive moment, or one striking object/3D element) is integrated full-bleed or as a large composed block; clear typographic hierarchy and WHERE the headline sits as part of the composition; generous spacing; the exact brand palette and accent colour. Give concrete art direction (lighting/mood/materials of the imagery) but frame everything as parts of one designed poster, not a camera shot. Avoid: generic stock-photo look, fake AI faces, distorted hands, holograms, glowing rings/halos, floating icons, clutter, invented logos, watermarks, gibberish text. Output 4:5 1080x1350."';
    } else {
        $fld[] = '"image_prompt":"150-220 words English for a HIGH-END ADVERTISING / CREATIVE-AGENCY poster (think award-winning social campaign, not a plain stock photo). Describe: a single powerful hero subject (a real expressive person in a candid decisive moment, OR one striking hero object / premium 3D element) as the clear focal point; a premium scene with depth in the BRAND\'s own palette and brightness (light, clean, colourful or dark — whatever matches the brand, never forced dark), strong directional key light plus a coloured rim/accent glow in the brand colour, volumetric light, reflections and premium materials; professional composition with the subject offset and deliberate EMPTY NEGATIVE SPACE in the upper area kept calm and darker for the headline to be added later; shallow depth of field, sharp focus on the subject, cinematic colour grade, fine film grain, photoreal and tactile — never flat, generic, bright-and-empty or AI-plastic. State the exact brand accent colour and mood. Output 4:5 1080x1350. End with the avoid list."';
    }
    if (!empty($o['video'])) $fld[] = '"video_prompt":"an 8-second vertical 9:16 cinematic video prompt in professional English: ONE clear simple visual idea with smooth natural camera motion and believable real action that shows the post\'s concept. NO on-screen text, NO captions or subtitles, NO titles or headlines, NO UI/dashboard/chart text, NO readable screens, NO logos, NO brand names, NO watermarks, NO call-to-action text and NO end card — any text the video model draws comes out as broken gibberish, so keep every screen and surface abstract and out of focus. Photoreal, realistic humans with correct hands, natural lighting; end on a clean calm frame with empty space"';
    if (!empty($o['carousel'])) $fld[] = '"carousel":[{"role":"Hook|Problem|Insight|Solution|CTA","title":"on-slide headline","body":"on-slide supporting copy","visual":"visual concept and composition","layout":"layout instructions"}]';
    $schema = 'Return ONLY JSON: {"posts":[{"n":1,' . implode(',', $fld) . '}]} one object per topic with the same n'
        . (!empty($o['carousel']) ? '; carousel has exactly 5 slides in the order Hook, Problem, Insight, Solution, CTA' : '; do NOT include video_prompt or carousel — they were not requested') . '.';
    $sys = "CONTENT_PLAN_JSON\n" . cpl_rules($p) . "\n\nSTEP 2 OF 2: write the complete posts for the topics given, every requested section finished and usable.\n" . $schema
        . ($used ? "\nHeadlines and visual concepts already used in this plan (do not repeat them): " . implode(' | ', array_slice($used, -40)) : '');
    $seqs = array_column($list, 'n'); cpl_log((int)$p['id'], t('كتابة المنشور %s بالأقسام الستة…', implode(' و', $seqs))); $tw = microtime(true);
    // ميزانية توكنز سخية: موديلات التفكير بتستهلك جزء كبير على التفكير قبل إخراج الـ JSON،
    // فلو الحد ضيّق الرد بيطلع مقطوع/فاضي ("رد ناقص"). بندفع على المُستخدَم فعليًا بس، فالحد العالي آمن.
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => cpl_brief($p) . "\n\nTopics:\n" . json_encode($list, JSON_UNESCAPED_UNICODE)]], 6000 * count($rows), 'compose');
    $j = kb_parse_json((string)($r['text'] ?? '')); $by = [];
    foreach ((array)($j['posts'] ?? []) as $x) if (is_array($x) && isset($x['n'])) $by[(int)$x['n']] = $x;
    $up = db()->prepare('UPDATE cp_items SET linkedin = ?, x = ?, headline = ?, image_prompt = ?, video_prompt = ?, carousel = ?, status = "draft", humanized = ' . (cpl_opts($p)['humanize'] ? 1 : 0) . ', fix_tries = 0 WHERE id = ?');
    $done = 0;
    foreach ($rows as $row) {
        $x = $by[(int)$row['seq']] ?? null; if (!$x || trim((string)($x['linkedin'] ?? '')) === '') continue;
        $car = array_values(array_map(function ($s) { return ['role' => mb_substr((string)($s['role'] ?? ''), 0, 30), 'title' => mb_substr((string)($s['title'] ?? ''), 0, 200), 'body' => mb_substr((string)($s['body'] ?? ''), 0, 800), 'visual' => mb_substr((string)($s['visual'] ?? ''), 0, 800), 'layout' => mb_substr((string)($s['layout'] ?? ''), 0, 600)]; }, array_filter((array)($x['carousel'] ?? []), 'is_array')));
        $up->execute([mb_substr(ai_strip_dashes(trim((string)$x['linkedin'])), 0, 6000), mb_substr(ai_strip_dashes(trim((string)($x['x'] ?? ''))), 0, 600), mb_substr(ai_strip_dashes(trim((string)($x['headline'] ?? ''))), 0, 120), mb_substr(trim((string)($x['image_prompt'] ?? '')), 0, 6000), mb_substr(trim((string)($x['video_prompt'] ?? '')), 0, 8000), json_encode(array_slice($car, 0, 5), JSON_UNESCAPED_UNICODE), (int)$row['id']]);
        $done++;
    }
    if (!$done) throw new RuntimeException(($r['finish'] ?? '') === 'length'
        ? t('رد الذكاء الاصطناعي اتقطع قبل ما يكمل (الموديل بيفكّر كتير). جرّب موديل أسرع للكتابة من صفحة التكاملات، أو أعد المحاولة.')
        : t('رد الذكاء الاصطناعي ناقص. سنكمل من المنشور نفسه.'));
    cpl_log((int)$p['id'], t('كُتب %s منشور في %s ثانية (%s).', $done, round(microtime(true) - $tw), implode('، ', $seqs)), 'ok');
}

/** تصليح تلقائي: يبعت المنشور ومشاكل الفحص بالظبط للموديل، ويرجع الأقسام المتصلحة بس. بعدها الفحص بيتعاد على النتيجة. */
function cpl_do_fix(array $p, array $it): void
{
    $id = (int)$p['id']; $o = cpl_opts($p);
    db()->prepare('UPDATE cp_items SET fix_tries = fix_tries + 1 WHERE id = ?')->execute([$it['id']]);
    cpl_log($id, t('فحص الجودة وجد في المنشور %s: %s. جارٍ التصحيح تلقائيًا (محاولة %s من 2)…', $it['seq'], implode(' · ', $it['qc']), (int)$it['fix_tries'] + 1), 'warn'); $tf = microtime(true);
    $cur = ['topic' => $it['topic'], 'linkedin' => $it['linkedin'], 'x' => $it['x'], 'headline' => $it['headline'], 'image_prompt' => $it['image_prompt'], 'video_prompt' => $it['video_prompt'], 'carousel' => $it['carousel']];
    $sys = "CONTENT_FIX_JSON\n" . cpl_rules($p) . "\n\nQUALITY FIX: the post below failed these measurable checks: " . implode('; ', $it['qc'])
        . ".\nRewrite ONLY the sections needed so every check passes (LinkedIn 130-220 words with hashtags, X max 280 characters with hashtags, headline 2-6 words, image prompt 120-200 words, video prompt complete, carousel exactly 5 slides Hook/Problem/Insight/Solution/CTA, no placeholders; for a duplicate topic or headline, choose a clearly different angle, headline and visual concept). Keep everything that already passes, keep the facts, never invent new ones."
        . "\nReturn ONLY JSON with the corrected fields among: {\"linkedin\",\"x\",\"headline\",\"image_prompt\",\"video_prompt\",\"carousel\":[{\"role\",\"title\",\"body\",\"visual\",\"layout\"}],\"title\",\"angle\"}.";
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => cpl_brief($p) . "\n\nPost to fix:\n" . json_encode($cur, JSON_UNESCAPED_UNICODE)]], 6000, 'cp_edit');
    $j = kb_parse_json((string)($r['text'] ?? '')); if (!$j) throw new RuntimeException(t('رد التصحيح غير صالح. سنعيد المحاولة.'));
    $f = []; foreach (['linkedin' => 6000, 'x' => 600, 'headline' => 120, 'image_prompt' => 6000, 'video_prompt' => 8000] as $k => $mx) if (trim((string)($j[$k] ?? '')) !== '') $f[$k] = mb_substr(in_array($k, ['linkedin', 'x', 'headline'], true) ? ai_strip_dashes(trim((string)$j[$k])) : trim((string)$j[$k]), 0, $mx);
    if ($f) { $set = []; $a = []; foreach ($f as $k => $v) { $set[] = "$k = ?"; $a[] = $v; } $a[] = $it['id']; db()->prepare('UPDATE cp_items SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($a); }
    if (!empty($j['carousel']) && is_array($j['carousel'])) { $car = array_values(array_map(function ($s) { return ['role' => mb_substr((string)($s['role'] ?? ''), 0, 30), 'title' => mb_substr(ai_strip_dashes((string)($s['title'] ?? '')), 0, 200), 'body' => mb_substr(ai_strip_dashes((string)($s['body'] ?? '')), 0, 800), 'visual' => mb_substr((string)($s['visual'] ?? ''), 0, 800), 'layout' => mb_substr((string)($s['layout'] ?? ''), 0, 600)]; }, array_filter($j['carousel'], 'is_array'))); db()->prepare('UPDATE cp_items SET carousel = ? WHERE id = ?')->execute([json_encode(array_slice($car, 0, 5), JSON_UNESCAPED_UNICODE), $it['id']]); }
    if (!empty($j['title']) || !empty($j['angle'])) { $t = $it['topic']; if (!empty($j['title'])) $t['title'] = mb_substr((string)$j['title'], 0, 300); if (!empty($j['angle'])) $t['angle'] = mb_substr((string)$j['angle'], 0, 300); db()->prepare('UPDATE cp_items SET topic = ? WHERE id = ?')->execute([json_encode($t, JSON_UNESCAPED_UNICODE), $it['id']]); }
    $after = null; foreach (cpl_items($id) as $x) if ($x['id'] === $it['id']) $after = $x;
    $left = $after ? $after['qc'] : [];
    cpl_log($id, $left ? t('بعد التصحيح (%s ثانية) ما زال في المنشور %s: %s', round(microtime(true) - $tf), $it['seq'], implode(' · ', $left)) : t('تم تصحيح المنشور %s في %s ثانية، واجتاز فحص الجودة.', $it['seq'], round(microtime(true) - $tf)), $left ? 'warn' : 'ok');
    if ($left && (int)$it['fix_tries'] + 1 >= 2) cpl_log($id, t('توقفنا عن تصحيح المنشور %s بعد محاولتين؛ راجعه يدويًا (الملاحظات ظاهرة على الكارت).', $it['seq']), 'warn');
}

/** إعادة توليد حقل واحد فقط (عنوان/LinkedIn/X/برومبت صورة) دون المساس ببقية المنشور — توفير استهلاك. */
function cpl_regen_field(int $itemId, string $field): ?array
{
    $allow = ['headline' => 120, 'linkedin' => 6000, 'x' => 600, 'image_prompt' => 6000];
    if (!isset($allow[$field])) return null;
    $it = cpl_item($itemId); if (!$it) return null; $p = cpl_plan($it['plan_id']); if (!$p) return null;
    $label = ['headline' => 'the poster headline (2-6 words)', 'linkedin' => 'the LinkedIn caption (130-220 words with hashtags)', 'x' => 'the X/Twitter post (max 280 chars incl. hashtags)', 'image_prompt' => 'the English image prompt (150-220 words)'][$field];
    $ctx = ['topic' => $it['topic'], 'current_headline' => $it['headline'], 'current_linkedin' => $it['linkedin'], 'current_x' => $it['x']];
    $sys = "CONTENT_FIELD_JSON\n" . cpl_rules($p) . "\n\nRegenerate ONLY {$label} for this post — a fresh, clearly different, better version. Keep it consistent with the topic, audience, language and brand. Do NOT change anything else."
        . "\nReturn ONLY JSON: {\"" . $field . "\":\"...\"}.";
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => cpl_brief($p) . "\n\nPost context:\n" . json_encode($ctx, JSON_UNESCAPED_UNICODE)]], 1500, 'cp_edit');
    $j = kb_parse_json((string)($r['text'] ?? '')); $v = trim((string)($j[$field] ?? '')); if ($v === '') return null;
    if (in_array($field, ['headline', 'linkedin', 'x'], true)) $v = ai_strip_dashes($v);
    $v = mb_substr($v, 0, $allow[$field]);
    db()->prepare("UPDATE cp_items SET $field = ? WHERE id = ? AND workspace_id = ?")->execute([$v, $itemId, (int)ws('id')]);
    // لو العنوان اتغيّر وفي صورة بلا تعديل يدوي مقفول، نعيد تركيبها لتعكس العنوان الجديد
    if ($field === 'headline' && (string)$it['fmt'] === 'image') cpl_recompose($itemId);
    return cpl_item($itemId);
}

/** فحص الجودة (QUALITY CONTROL في البرومبت) لكل منشور: أرقام قابلة للقياس فقط، ومش بيحكم على الإبداع */
function cpl_qc(array $it, array $o = []): array
{
    if ($it['linkedin'] === null) return [];
    $w = function ($s) { return count(preg_split('/\s+/u', trim((string)$s), -1, PREG_SPLIT_NO_EMPTY)); }; $q = [];
    $lw = $w($it['linkedin']); if ($lw < 130 || $lw > 220) $q[] = t('النص الطويل %s كلمة (المطلوب 130–220)', $lw);
    if (mb_strlen((string)$it['x']) > 280) $q[] = t('النص القصير تجاوز 280 حرفًا'); if (trim((string)$it['x']) === '') $q[] = t('النص القصير فارغ');
    if (!preg_match('/#\S/u', (string)$it['linkedin'])) $q[] = t('النص الطويل بلا هاشتاجات');
    // فحوص حتمية (بنيوية) فقط — لا نحكم على الجودة الإبداعية بوجود كلمات مثل 35mm أو 4000K أو hex (ذلك لا يُثبت جودة)
    $iw = $w($it['image_prompt']); if ($iw < 90) $q[] = t('برومبت الصورة قصير جدًا (%s كلمة)', $iw);
    $ip = (string)$it['image_prompt'];
    if (preg_match('/\b(place|add|include|put|draw|render|write)\b[^.]{0,40}\b(logo|wordmark|brand name)\b/i', $ip)) $q[] = t('برومبت الصورة يطلب رسم لوجو/اسم (السيستم يضيفه لاحقًا)');
    $hw = $w($it['headline']); if ($hw < 2 || $hw > 6) $q[] = t('عنوان الصورة %s كلمات (المطلوب 2–6)', $hw);
    if (($o['video'] ?? true) && $w($it['video_prompt']) < 60) $q[] = t('برومبت الفيديو قصير');
    if (($o['carousel'] ?? true) && count($it['carousel']) !== 5) $q[] = t('الكاروسيل %s شرائح (المطلوب 5)', count($it['carousel']));
    if (preg_match('/\[(?:[^\]]*)(?:placeholder|insert|company name|اسم الشركة|ضع هنا)[^\]]*\]|lorem|XXX/iu', $it['linkedin'] . $it['x'] . $it['image_prompt'])) $q[] = t('فيه نص مؤقت (placeholder)');
    return $q;
}

/** الكرون: كمّل الخطط اللي شغالة حتى لو المتصفح مقفول */
function cpl_tick(int $wid, int $budget = 4): int
{
    $GLOBALS['cpl_cron'] = true;
    $t0 = microtime(true); $n = 0;
    // ميزانية وقت لكل شركة في التشغيلة: نعالج أكبر كمية ممكنة بدل عدد ثابت صغير (تسريع التوليد الكبير)
    $cap = 70;
    if (isset($GLOBALS['cron_t0'])) { $rem = 560 - (microtime(true) - (float)$GLOBALS['cron_t0']); $cap = max(8, min($cap, $rem)); }
    while (microtime(true) - $t0 < $cap) {
        $did = 0;
        // مهام الصور/الفيديو — بالتوازي (دفعة صور في نفس الوقت)
        $jq = db()->prepare('SELECT DISTINCT plan_id FROM cp_jobs WHERE workspace_id = ? AND status IN ("queued","running") LIMIT 4'); $jq->execute([$wid]);
        foreach ($jq->fetchAll(PDO::FETCH_COLUMN) as $pid) { $m = function_exists('cpl_jobs_burst') ? cpl_jobs_burst((int)$pid, 5) : (cpl_jobs_tick((int)$pid) ? 1 : 0); if ($m > 0) { $n += $m; $did += $m; } elseif (cpl_jobs_tick((int)$pid)) { $n++; $did++; } if (microtime(true) - $t0 >= $cap) break 2; }
        // خطوات المخطط/النصوص
        $st = db()->prepare('SELECT id FROM cp_plans WHERE workspace_id = ? AND status = "generating" ORDER BY id LIMIT 4'); $st->execute([$wid]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) { $s = cpl_step((int)$id); $n++; $did++; if (!empty($s['busy']) || $s['error'] !== '') break; if (microtime(true) - $t0 >= $cap) break 2; }
        if ($did === 0) break; // مفيش شغل متبقّي
    }
    return $n;
}

/** هل لسه فيه توليد محتوى شغّال على مستوى المنصة (لتكملة الكرون نفسه فورًا بدل انتظار ساعة). */
function cpl_pending_any(): bool
{
    try {
        $a = (int)db()->query('SELECT COUNT(*) FROM cp_plans WHERE status = "generating"')->fetchColumn();
        $b = (int)db()->query('SELECT COUNT(*) FROM cp_jobs WHERE status IN ("queued","running")')->fetchColumn();
        return ($a + $b) > 0;
    } catch (Throwable $e) { return false; }
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
    if (array_key_exists('carousel', $f) && is_array($f['carousel'])) {
        $old = (array)($it['carousel'] ?: []); $car = [];
        foreach (array_slice(array_values($f['carousel']), 0, 10) as $i => $s) {
            $s = (array)$s; $base = (array)($old[$i] ?? []);
            $car[] = array_merge($base, ['title' => mb_substr(trim((string)($s['title'] ?? '')), 0, 300), 'body' => mb_substr(trim((string)($s['body'] ?? '')), 0, 2000)]);
        }
        $set[] = 'carousel = ?'; $a[] = json_encode($car, JSON_UNESCAPED_UNICODE);
    }
    if (array_key_exists('media_ids', $f)) { $ms = array_slice(array_values(array_unique(array_filter(array_map('intval', (array)$f['media_ids'])))), 0, 10); foreach ($ms as $mid) if (!media_get((int)ws('id'), $mid)) throw new RuntimeException(t('الملف مش موجود')); $set[] = 'media_ids = ?'; $a[] = json_encode($ms); $set[] = 'media_id = ?'; $a[] = $ms[0] ?? null; }
    if (!$set) return; $a[] = $id; $a[] = (int)ws('id');
    db()->prepare('UPDATE cp_items SET ' . implode(', ', $set) . ' WHERE id = ? AND workspace_id = ?')->execute($a);
}

/** يبني طلب زيرنيو لعنصر: لينكدإن (وأي منصة غير X) بنص لينكدإن، وX بنصه الخاص */
function cpl_payload(Zernio $z, array $it, array $accounts, array $mode): array
{
    // المنصات ذات الحد القصير (زي X) تاخد النص القصير؛ الباقي ياخد النص الطويل (لينكدإن)
    $shortPlatforms = ['twitter', 'x', 'bluesky', 'mastodon', 'threads'];
    $platforms = [];
    foreach ($accounts as $a) {
        $pf = (string)$a['platform']; $item = ['platform' => $pf, 'accountId' => (string)$a['_id']];
        if (in_array(strtolower($pf), $shortPlatforms, true) && trim((string)$it['x']) !== '') $item['customContent'] = (string)$it['x'];
        $platforms[] = $item;
    }
    $p = ['content' => (string)$it['linkedin'], 'platforms' => $platforms, 'timezone' => site_timezone()];
    foreach ($it['media_ids'] as $mid) $p['mediaItems'][] = ['type' => media_kind((string)(media_get((int)ws('id'), $mid)['mime'] ?? '')) === 'video' ? 'video' : 'image', 'url' => media_public_url($z, (int)ws('id'), $mid, 'content_bank', (string)$it['id'])];
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
        'linkedin' => t('النص الطويل'), 'x' => t('النص القصير'), 'headline' => t('عنوان الصورة'), 'image_prompt' => 'Image prompt', 'video_prompt' => 'Video prompt',
        's1' => 'Slide 1 Hook', 's2' => 'Slide 2 Problem', 's3' => 'Slide 3 Insight', 's4' => 'Slide 4 Solution', 's5' => 'Slide 5 CTA'];
}

function cpl_status_label(string $s): string { return ['pending' => t('قيد الكتابة'), 'draft' => t('مسودة'), 'approved' => t('معتمد'), 'rejected' => t('مرفوض'), 'scheduled' => t('مجدول'), 'queued' => t('في الطابور'), 'published' => t('تم النشر')][$s] ?? $s; }

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

/* ===================== الخطوة الثانية: توليد الصور/الفيديو/الكاروسيل للمنشورات المختارة ===================== */
/* كل طلب توليد = مهمة محفوظة في cp_jobs، بتتنفذ واحدة واحدة من الصفحة أو من الكرون، فلو النت فصل بتكمل. */

/** يضيف مهام توليد لمنشورات (الكاروسيل = 5 مهام، واحدة لكل شريحة). يرجع عدد المهام */
function cpl_jobs_add(int $planId, array $ids, string $kind): int
{
    if (!in_array($kind, ['image', 'video', 'carousel'], true)) throw new RuntimeException(t('طلب غير صحيح.'));
    if ($kind === 'video' && (!function_exists('vid_provider') || vid_provider() !== 'openrouter')) throw new RuntimeException(t('توليد الفيديو هنا يحتاج ربط OpenRouter بموديل فيديو من صفحة التكاملات.'));
    if ($kind !== 'video' && (!function_exists('img_provider') || img_provider() === '')) throw new RuntimeException(t('اربط OpenAI أو OpenRouter من التكاملات لتوليد الصور.'));
    $n = 0; $ins = db()->prepare('INSERT INTO cp_jobs (plan_id, item_id, workspace_id, kind, slide, status) VALUES (?,?,?,?,?, "queued")');
    $has = db()->prepare('SELECT COUNT(*) FROM cp_jobs WHERE item_id = ? AND kind = ? AND status IN ("queued","running")');
    foreach (array_map('intval', $ids) as $id) {
        $it = cpl_item($id); if (!$it || $it['plan_id'] !== $planId || $it['linkedin'] === null) continue;
        $has->execute([$id, $kind]); if ((int)$has->fetchColumn()) continue;
        if ($kind === 'carousel' && count($it['carousel']) < 2) continue;
        foreach ($kind === 'carousel' ? array_keys(array_slice($it['carousel'], 0, 5)) : [null] as $sl) { $ins->execute([$planId, $id, (int)ws('id'), $kind, $sl]); $n++; }
    }
    if ($n) cpl_log($planId, t('أُضيفت %s مهمة توليد (%s) لـ %s منشور.', $n, ['image' => t('صورة'), 'video' => t('فيديو'), 'carousel' => t('كاروسيل')][$kind], count($ids)));
    return $n;
}

function cpl_jobs(int $planId): array
{
    $st = db()->prepare('SELECT * FROM cp_jobs WHERE plan_id = ? ORDER BY id'); $st->execute([$planId]); return $st->fetchAll();
}

/** أرقام ملفات المكتبة لأسماء نتايج التوليد (بعد مزامنة مجلد AI Generated) */
function cpl_media_for_names(array $names): array
{
    if (!$names) return []; $wid = (int)ws('id'); $names = array_map(function ($n) { return 'ai-' . basename($n); }, $names); // media_sync_ai بيحفظها باسم ai-<الملف> if (function_exists('media_sync_ai')) { try { media_sync_ai($wid); } catch (Throwable $e) {} }
    $q = db()->prepare('SELECT id, name FROM media_files WHERE workspace_id = ? AND deleted_at IS NULL AND name IN (' . implode(',', array_fill(0, count($names), '?')) . ')'); $q->execute(array_merge([$wid], $names));
    $by = []; foreach ($q->fetchAll() as $r) $by[$r['name']] = (int)$r['id'];
    $sh = db()->prepare('SELECT id FROM media_files WHERE workspace_id = ? AND sha1 = ? AND deleted_at IS NULL LIMIT 1'); // نفس المحتوى اتحفظ قبل كده (المكتبة بتمنع التكرار)
    return array_values(array_filter(array_map(function ($n) use ($by, $sh, $wid) { if (isset($by[$n])) return $by[$n]; $f = hf_lib_dir($wid) . '/' . substr($n, 3); if (!is_file($f)) return 0; $sh->execute([$wid, sha1_file($f)]); return (int)$sh->fetchColumn(); }, $names)));
}

/** لما كل مهام نوع معين لمنشور تخلص: الوسائط بتتحط على المنشور بالترتيب ونوعه بيتحدد (صورة/فيديو/كاروسيل) */
function cpl_jobs_finalize(int $itemId, string $kind): void
{
    $st = db()->prepare('SELECT * FROM cp_jobs WHERE item_id = ? AND kind = ? ORDER BY id DESC'); $st->execute([$itemId, $kind]); $jobs = $st->fetchAll();
    $latest = []; foreach ($jobs as $j) { $k = (string)$j['slide']; if (!isset($latest[$k])) $latest[$k] = $j; }
    foreach ($latest as $j) if (in_array($j['status'], ['queued', 'running'], true)) return;
    ksort($latest); $raw = []; foreach ($latest as $j) if ($j['status'] === 'done') $raw = array_merge($raw, array_map('intval', (array)json_decode((string)$j['result'], true)));
    if (!$raw) return;
    $it = cpl_item($itemId); $p = $it ? cpl_plan($it['plan_id']) : null; if (!$p) return;
    $lopts = $it['logo'] ?: null; // إعداد لوجو خاص بالمنشور لو موجود
    $tov = $it['txt_opts'] ?: []; // تعديلات النص الخاصة بالمنشور
    $heads = cpl_media_headlines($it, $kind, count($raw)); // نطبع العنوان بنفسنا (عربي سليم)
    if ($kind === 'image' && !empty($tov['headline'])) $heads[0] = (string)$tov['headline']; // عنوان معدّل يدويًا
    $raw = array_values($raw); $stamped = []; foreach ($raw as $i => $m) $stamped[] = cpl_compose_media($p, $m, (string)($heads[$i] ?? ''), $lopts, $tov);
    // صورة جديدة اتولّدت = أساس جديد، فنلغي مستند التصميم اليدوي القديم (ما بقاش ينطبق على الصورة الجديدة)
    db()->prepare('UPDATE cp_items SET fmt = ?, media_raw = ?, media_ids = ?, media_id = ?, design_doc = NULL WHERE id = ?')->execute([$kind, json_encode($raw), json_encode(array_values($stamped)), $stamped[0], $itemId]);
}

/** العنوان اللي بيتطبع على كل وسيط: صورة = عنوان المنشور، كاروسيل = عنوان كل شريحة، فيديو = بدون */
function cpl_media_headlines(array $it, string $kind, int $n): array
{
    if ($kind === 'image') return [(string)($it['headline'] ?? '')];
    if ($kind === 'carousel') { $h = []; foreach ((array)($it['carousel'] ?? []) as $s) $h[] = (string)($s['title'] ?? ''); return $h; }
    return array_fill(0, max(1, $n), '');
}

/** يعيد تركيب اللوجو على صور المنشور من النسخ الخام بإعدادات لوجو خاصة بالمنشور (يحفظها على المنشور) */
function cpl_restamp_item(int $itemId, array $in): ?array
{
    $it = cpl_item($itemId); if (!$it) return null;
    $raw = $it['media_raw'] ?: $it['media_ids']; if (!$raw) return $it;
    $p = cpl_plan($it['plan_id']); if (!$p) return $it;
    $cl = function ($v, $lo, $hi, $d) { $v = is_numeric($v) ? (int)round($v) : $d; return max($lo, min($hi, $v)); };
    $lopts = [
        'logo_on' => 1,
        'logo_x' => $cl($in['logo_x'] ?? null, 0, 100, 86),
        'logo_y' => $cl($in['logo_y'] ?? null, 0, 100, 9),
        'logo_w' => $cl($in['logo_w'] ?? null, 8, 45, 18),
        'logo_op' => $cl($in['logo_op'] ?? null, 30, 100, 100),
        'logo_tint' => in_array($in['logo_tint'] ?? '', ['orig', 'white', 'black'], true) ? $in['logo_tint'] : 'orig',
        'logo_plate' => in_array($in['logo_plate'] ?? '', ['none', 'light', 'dark'], true) ? $in['logo_plate'] : 'none',
    ];
    db()->prepare('UPDATE cp_items SET logo = ? WHERE id = ? AND workspace_id = ?')->execute([json_encode($lopts), $itemId, (int)ws('id')]);
    return cpl_recompose($itemId);
}

/** يحفظ تعديلات النص الخاصة بالمنشور (عنوان معدّل + مكان/حجم/نمط/لون) ثم يعيد تركيب الصورة. */
function cpl_restamp_text(int $itemId, array $in): ?array
{
    $it = cpl_item($itemId); if (!$it) return null;
    $tov = is_array($it['txt_opts']) ? $it['txt_opts'] : [];
    if (array_key_exists('headline', $in)) { $h = trim((string)$in['headline']); $tov['headline'] = mb_substr($h, 0, 160); }
    if (isset($in['txt_pos']) && in_array($in['txt_pos'], ['auto', 'top', 'bottom'], true)) $tov['txt_pos'] = $in['txt_pos'];
    if (isset($in['txt_size']) && in_array($in['txt_size'], ['s', 'm', 'l'], true)) $tov['txt_size'] = $in['txt_size'];
    if (isset($in['txt_scale'])) $tov['txt_scale'] = max(50, min(180, (int)$in['txt_scale']));
    if (isset($in['txt_align']) && in_array($in['txt_align'], ['auto', 'right', 'center', 'left'], true)) $tov['txt_align'] = $in['txt_align'];
    if (array_key_exists('txt_y', $in)) $tov['txt_y'] = ($in['txt_y'] === '' || $in['txt_y'] === null) ? null : max(2, min(92, (int)$in['txt_y']));
    if (isset($in['txt_variant']) && in_array($in['txt_variant'], ['auto', 'box', 'underline', 'color'], true)) $tov['txt_variant'] = $in['txt_variant'];
    if (isset($in['txt_accent'])) { $a = (string)$in['txt_accent']; $tov['txt_accent'] = (preg_match('/^#?[0-9a-fA-F]{6}$/', $a) && strtolower(ltrim($a, '#')) !== '10b981') ? ('#' . ltrim($a, '#')) : ''; }
    db()->prepare('UPDATE cp_items SET txt_opts = ? WHERE id = ? AND workspace_id = ?')->execute([json_encode($tov, JSON_UNESCAPED_UNICODE), $itemId, (int)ws('id')]);
    return cpl_recompose($itemId);
}

/** يحفظ تصميمًا عدّله المستخدم في المحرّر المدمج (Fabric) كصورة ناتجة + مستند تصميم JSON قابل للإعادة.
 *  وجود design_doc يقفل المنشور: لا تعيد دوال التركيب دهسه (الصورة الخام تبقى للرجوع). */
function cpl_save_canvas(int $itemId, string $dataUrl, ?string $doc = null): ?array
{
    $it = cpl_item($itemId); if (!$it) return null;
    if (!preg_match('~^data:image/(png|jpe?g);base64,~', $dataUrl)) return null;
    $bytes = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
    if ($bytes === false || strlen($bytes) < 400 || strlen($bytes) > 15 * 1048576) return null;
    $wid = (int)ws('id');
    // تحقّق من صلاحية مستند التصميم (JSON صالح وبحجم معقول) قبل تخزينه
    $docSave = null;
    if (is_string($doc) && $doc !== '' && strlen($doc) <= 4 * 1048576) { $dec = json_decode($doc, true); if (is_array($dec) && isset($dec['objects'])) $docSave = json_encode($dec, JSON_UNESCAPED_UNICODE); }
    $tmp = tempnam(sys_get_temp_dir(), 'cpc') . '.png'; file_put_contents($tmp, $bytes);
    try { $row = media_store_path($wid, $tmp, 'dz-' . (int)($it['seq'] ?? 0) . '-' . substr(md5(microtime(true) . random_int(0, 9999)), 0, 6) . '.png', 'image/png', media_ai_folder()); }
    catch (Throwable $e) { @unlink($tmp); return null; }
    @unlink($tmp); $mid = (int)$row['id'];
    // سجل النسخ: ندفع النسخة السابقة إلى التاريخ قبل الكتابة (نحتفظ بآخر 8)
    if ($docSave !== null) {
        $cur = db()->prepare('SELECT design_doc, design_doc_hist FROM cp_items WHERE id = ? AND workspace_id = ?'); $cur->execute([$itemId, $wid]); $cr = $cur->fetch() ?: [];
        $prev = (string)($cr['design_doc'] ?? ''); $hist = json_decode((string)($cr['design_doc_hist'] ?? ''), true); if (!is_array($hist)) $hist = [];
        if ($prev !== '' && $prev !== $docSave) { array_unshift($hist, ['t' => time(), 'doc' => $prev]); $hist = array_slice($hist, 0, 8); }
        db()->prepare('UPDATE cp_items SET media_ids = ?, media_id = ?, design_doc = ?, design_doc_hist = ? WHERE id = ? AND workspace_id = ?')->execute([json_encode([$mid]), $mid, $docSave, json_encode($hist, JSON_UNESCAPED_UNICODE), $itemId, $wid]);
    } else {
        db()->prepare('UPDATE cp_items SET media_ids = ?, media_id = ?, design_doc = ? WHERE id = ? AND workspace_id = ?')->execute([json_encode([$mid]), $mid, $docSave, $itemId, $wid]);
    }
    return cpl_item($itemId);
}

/** يطبّق إعدادات النص (مكان/حجم/نمط/لون — بدون العنوان) على كل صور الخطة ويعيد تركيبها. يرجّع العدد. */
function cpl_restamp_text_all(int $planId, array $in): int
{
    $p = cpl_plan($planId); if (!$p) return 0;
    unset($in['headline']); // العنوان خاص بكل منشور؛ «الكل» يشارك الإعدادات فقط
    $st = db()->prepare('SELECT id FROM cp_items WHERE plan_id = ? AND workspace_id = ? AND fmt = "image"'); $st->execute([$planId, (int)ws('id')]);
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $iid) {
        $it = cpl_item((int)$iid); if (!$it) continue;
        $raw = $it['media_raw'] ?: $it['media_ids']; if (!$raw) continue;
        cpl_restamp_text((int)$iid, $in); $n++;
    }
    return $n;
}

/** يعيد تركيب صور المنشور من النسخ الخام بإعدادات اللوجو والنص المحفوظة على المنشور.
 *  يحترم قفل التعديل اليدوي: لو في مستند تصميم محفوظ (design_doc) لا يدهسه إلا بطلب صريح ($force). */
function cpl_recompose(int $itemId, bool $force = false): ?array
{
    $it = cpl_item($itemId); if (!$it) return null;
    if (!$force) { $st = db()->prepare('SELECT design_doc FROM cp_items WHERE id = ? AND workspace_id = ?'); $st->execute([$itemId, (int)ws('id')]); if (trim((string)$st->fetchColumn()) !== '') return $it; } // تعديل يدوي مقفول
    $raw = $it['media_raw'] ?: $it['media_ids']; if (!$raw) return $it;
    $p = cpl_plan($it['plan_id']); if (!$p) return $it;
    $lopts = $it['logo'] ?: null; $tov = is_array($it['txt_opts']) ? $it['txt_opts'] : [];
    $heads = cpl_media_headlines($it, (string)$it['fmt'], count($raw));
    if ((string)$it['fmt'] === 'image' && !empty($tov['headline'])) $heads[0] = (string)$tov['headline'];
    $raw = array_values($raw); $stamped = []; foreach ($raw as $i => $m) $stamped[] = cpl_compose_media($p, $m, (string)($heads[$i] ?? ''), $lopts, $tov);
    db()->prepare('UPDATE cp_items SET media_raw = ?, media_ids = ?, media_id = ? WHERE id = ? AND workspace_id = ?')
        ->execute([json_encode(array_values($raw)), json_encode(array_values($stamped)), $stamped[0], $itemId, (int)ws('id')]);
    return cpl_item($itemId);
}

function cpl_text_dir(array $p): string
{
    $v = cpl_opts($p)['lang_img'] ?? cpl_opts($p)['lang'];
    if ($v === 'en') return "On-image text is English only; left-to-right (LTR) layout, latin typography.";
    if ($v === 'none') return "No text on the image.";
    if ($v === 'ar_en' || $v === 'both') return "Bilingual text: Arabic is primary (larger, full-strength brand colour, right-to-left) and the English line is noticeably smaller and in a lighter/muted tone beneath it. Overall RTL layout, correct Arabic letter joining.";
    if ($v === 'en_ar') return "Bilingual text: English is primary (larger, full-strength colour, left-to-right) and the Arabic line is noticeably smaller and in a lighter/muted tone beneath it. Correct Arabic letter joining.";
    return "On-image text is Arabic only; right-to-left (RTL) layout, correct Arabic letter joining.";
}
function cpl_font_note(array $p): string
{
    $f = cpl_opts($p)['font'] ?? 'auto';
    $d = ['Cairo' => 'geometric humanist sans (Cairo)', 'Alexandria' => 'clean modern geometric sans (Alexandria)', 'Tajawal' => 'modern geometric sans (Tajawal)', 'Almarai' => 'neutral rounded sans (Almarai)', 'Changa' => 'bold condensed display (Changa)', 'El Messiri' => 'elegant contemporary with calligraphic touch (El Messiri)', 'Reem Kufi' => 'geometric kufic display (Reem Kufi)', 'Amiri' => 'classic Naskh serif (Amiri)', 'Lateef' => 'traditional Arabic (Lateef)', 'IBM Plex Sans Arabic' => 'technical corporate sans (IBM Plex Sans Arabic)'];
    return isset($d[$f]) ? ' Headline typography: ' . $d[$f] . ', rendered cleanly and spelled correctly.' : ' Headline typography: choose a strong typeface matching the brand references.';
}
function cpl_job_prompt(array $p, array $it, array $j): string
{
    $o = cpl_opts($p); $vis = trim((string)$p['visual']);
    [$car, $cag, $cab] = cpl_accent_rgb($p); $accHex = sprintf('#%02X%02X%02X', $car, $cag, $cab);
    $styleTxt = ''; $st = (string)($o['style'] ?? 'auto'); $styles = cpl_styles();
    if ($st !== 'auto' && !empty($styles[$st][1])) $styleTxt = "\n" . $styles[$st][1];
    $rules = "\nBRAND IDENTITY FIRST: match THIS brand's own visual identity — its palette, brightness and mood — from the Brand visual DNA and the chosen style below. Do NOT default to a dark, moody or neon look; if the brand is light, clean, colourful or minimal, make the image light/clean/colourful to match it. Only go dark and cinematic when the brand's own identity or the chosen style is dark.\nBRAND ACCENT COLOUR: use {$accHex} as the single signature accent of the whole series — the rim/edge light, the coloured glow and any highlight should be this exact colour, used with restraint over a premium base whose palette and brightness match the brand, so every post feels like one cohesive campaign.\nLOOK & FEEL — HIGH-END CREATIVE-AGENCY POSTER (mandatory): this must look like a premium, award-winning advertising campaign visual, NOT a plain stock photo. One strong hero subject as a clear focal point; confident directional lighting with a coloured rim/accent glow in the brand colour; a rich, premium environment (in the brand's own palette and brightness, not forced dark) with real depth, volumetric light, reflections and tactile premium materials; deliberate composition with the subject offset and the headline area kept calmer and lower-contrast as negative space for the headline; shallow depth of field, professional colour grade, subtle film grain. Make it striking and scroll-stopping. Never flat, empty, generic, cluttered or AI-plastic.\nFULL-BLEED: one complete, edge-to-edge photographic image that fills the entire 1080x1350 frame; do NOT add or leave any solid colour band, gradient bar, letterbox, flat panel, or cropped colour strip, and no borders or margins of flat colour, UNLESS the supplied reference designs clearly use such a band (then match them). Maximum photographic quality: sharp focus, rich detail, correct exposure, natural depth of field, professional colour grading; not flat, not AI-plastic.\nIf the image shows people: make them look like real, candid photographed humans, not AI renders. Add authentic, specific features: natural asymmetric faces, real skin texture with pores and subtle blemishes, flyaway hair, correct hands with five fingers, natural eye catchlights and gaze, believable clothing fit and wrinkles, and lived-in environment detail. No waxy skin, no plastic smoothness, no uncanny symmetry, no extra fingers.\nCRITICAL — NO TEXT AND NO LOGOS ANYWHERE: do NOT render any text, letters, words, headline, caption, numbers, labels, signage copy, logo, brand name, wordmark or watermark ANYWHERE in the image — this includes on clothing, uniforms, hard hats, badges, lanyards, ID cards, device/tablet/monitor screens, machines, equipment, cabinets, packaging, walls, posters, vehicles and any surface. People's uniforms and chest areas must be PLAIN with no company name, no made-up logo and no emblem. Device and monitor screens must show abstract graphics only, never readable text or a logo. The headline and the real company logo are added afterwards by the system, so output a purely visual photograph with NO typography and NO brand marks of any kind. Any text or logo the model invents will be wrong and must not appear.\nLEAVE CLEAN SPACE (VARY IT): keep EITHER the top ~30% OR the bottom ~30% of the frame calmer, simpler and lower-contrast (sky, wall, soft shadow, shallow background — lighter or darker to match the brand) as uncluttered negative space where the headline will be placed later — choose whichever side suits this composition and vary it across the series so the text position is not always the same. Keep the " . cpl_logo_corner($p) . " corner area (about 260x160px) clean for the logo. Keep the main subject and every key element at least 100px (about 9%) inside each edge; nothing touches or is cropped by an edge.";
    if ($j['kind'] === 'image' && (string)($o['design_mode'] ?? 'photo') === 'ai') {
        // نمط (ب): الموديل يولّد التصميم كامل متكامل (نص + أشكال + تخطيط داخل الصورة نفسها)
        $hl = trim((string)($it['headline'] ?? '')); $hl = trim(str_replace(['«', '»'], '', $hl));
        $langImg = ((string)($o['lang_img'] ?? $o['lang'] ?? 'ar') === 'en') ? 'English' : 'Arabic';
        $corner = cpl_logo_corner($p);
        $rulesAI = "\n\n=== PRODUCE A COMPLETE, FINISHED, FULL-CANVAS SOCIAL-MEDIA DESIGN (not a bare photo, not a photo inside a frame) ===\n"
            . "The ENTIRE 1080x1350 canvas IS the design, edge to edge: a polished, award-winning creative-agency social post built as ONE finished graphic composition that fills the whole frame — exactly like the supplied reference DESIGNS (a fully-designed social post that occupies the full screen), NOT a photograph placed inside a template frame, card, rounded panel or bordered box. If reference designs are attached, match their overall composition, full-bleed layout, type treatment and how they use the whole canvas.\n"
            . "Integrate the hero imagery INTO the design (full-bleed or as a large deliberate block that bleeds to the edges), with brand-coloured shapes/panels/bars, clean typographic hierarchy, generous spacing and a clear grid wrapping around it. No thin photo-in-a-box look, no leftover flat margins. It must read as something a senior designer built in a design tool: cohesive, premium, intentional.\n"
            . "HEADLINE — render this EXACT {$langImg} text prominently as the main headline, spelled 100% correctly with perfect letterforms and correct letter-joining, in a bold modern typeface, high contrast and easy to read: \"{$hl}\".\n"
            . ($langImg === 'Arabic' ? "The headline is ARABIC: render fully-connected, correctly-shaped right-to-left Arabic letters — never broken, mirrored, disconnected or Latin-looking glyphs. If you cannot render Arabic perfectly, keep the letters crisp and correct rather than decorative.\n" : "")
            . "Use {$accHex} as the signature brand accent across shapes, the headline highlight and graphic elements, over a premium base whose palette and brightness MATCH THIS BRAND (do not default to dark — light/clean/colourful if the brand is). Keep the {$corner} corner (about 260x160px) clean and empty for the real logo to be placed afterwards. No other text, no body paragraphs, no fake logos, no watermarks, no gibberish text anywhere — only the single headline above. Photoreal hero imagery, correct human hands and faces, no AI-plastic look.";
        return $it['image_prompt'] . ($vis !== '' ? "\n\nBrand visual DNA: " . mb_substr($vis, 0, 1200) : '') . $styleTxt . $rulesAI;
    }
    if ($j['kind'] === 'image') return $it['image_prompt'] . ($vis !== '' ? "\n\nBrand visual DNA: " . mb_substr($vis, 0, 1200) : '') . $styleTxt . $rules;
    if ($j['kind'] === 'video') return $it['video_prompt']
        . "\n\n=== CINEMATIC LOOK — shoot it like a premium ad film ===\n"
        . "ONE strong hero subject; dramatic cinematic lighting with a coloured rim/accent glow in {$accHex}; a moody, rich, premium environment with real depth, volumetric light and reflections; shallow depth of field; slow, deliberate, smooth camera motion (gentle push-in, parallax or orbit); cinematic colour grade and subtle film grain. Striking and scroll-stopping, never flat, bright-and-empty, generic or AI-plastic.\n"
        . "\n=== HARD VIDEO RULES — OBEY EXACTLY, OVERRIDE ANYTHING ABOVE THAT CONFLICTS ===\n"
        . "Deliver ONE clear, simple visual idea with smooth, natural, physically believable motion — a single coherent concept, not a busy montage.\n"
        . "ABSOLUTELY NO TEXT OF ANY KIND anywhere on screen at any moment: no words, letters, numbers, captions, subtitles, titles, headlines, lower-thirds, menus, readable UI, dashboard figures, chart labels, signage, logos, brand names, wordmarks or watermarks — on any surface, screen, monitor, tablet, wall, badge, uniform, hard hat or device. AI video renders text as broken gibberish, so every screen and monitor must show only abstract, out-of-focus coloured graphics with NO readable content.\n"
        . "Do NOT show any call-to-action, end card, title card, logo reveal, lower-third or caption. If the description above mentions a logo, brand name, headline, CTA, voice-over captions or dashboard readings, render that beat as PURELY visual action with no text and no logo.\n"
        . "End on a clean, calm cinematic frame with empty negative space (for a logo to be added later in editing).\n"
        . "Photoreal and cinematic: natural lighting and colour grading, realistic humans with natural faces and correct hands (five fingers, no extra), believable physics; no morphing, warping, flicker, text artifacts or plastic AI look.";
    $s = $it['carousel'][(int)$j['slide']] ?? []; $n = count($it['carousel']);
    return "Carousel slide " . ((int)$j['slide'] + 1) . " of {$n} (" . ($s['role'] ?? '') . ") for a premium social campaign, 4:5 1080x1350, one consistent series across all slides.\n"
        . "Visual concept: " . ($s['visual'] ?? '') . "\nLayout: " . ($s['layout'] ?? '')
        . ($vis !== '' ? "\n\nBrand visual DNA: " . mb_substr($vis, 0, 1200) : '') . $styleTxt . $rules;
}

/** ركن اللوجو: عربي = أعلى اليمين، إنجليزي = أعلى اليسار */
/** وصف منطقة اللوجو للبرومبت (عشان الموديل يسيبها فاضية) من إحداثيات المستخدم */
function cpl_logo_corner(array $p): string
{
    $o = cpl_opts($p); if (!isset($o['logo_x'])) return (string)($o['logo_pos'] ?? ((($o['lang_img'] ?? $o['lang']) === 'en') ? 'top-left' : 'top-right'));
    $v = $o['logo_y'] < 34 ? 'top' : ($o['logo_y'] > 66 ? 'bottom' : 'middle'); $h = $o['logo_x'] < 34 ? 'left' : ($o['logo_x'] > 66 ? 'right' : 'center');
    return $v === 'middle' && $h === 'center' ? 'center' : $v . '-' . $h;
}

/** صندوق اللوجو بالبكسل: نفس معادلة المعاينة في المتصفح (عرض %، ارتفاع أقصاه 14%، مركز عند x/y، جوه هامش 7%) */
function cpl_logo_box(array $o, int $W, int $Hh, float $ar): array
{
    if (!isset($o['logo_x'])) { $pos = $o['logo_pos'] ?? 'top-right'; $o['logo_x'] = substr($pos, -4) === 'left' ? 0 : (substr($pos, -5) === 'right' ? 100 : 50); $o['logo_y'] = strpos($pos, 'top') === 0 ? 0 : 100; $o['logo_w'] = (int)($o['logo_size'] ?? 20); }
    $w = $o['logo_w'] / 100 * $W; $h = $w / max(0.01, $ar); if ($h > 0.14 * $Hh) { $h = 0.14 * $Hh; $w = $h * $ar; }
    $cx = min(max($o['logo_x'] / 100 * $W, 0), $W); $cy = min(max($o['logo_y'] / 100 * $Hh, 0), $Hh); // يُسمح بخروج اللوجو عن الإطار
    return [(int)round($cx - $w / 2), (int)round($cy - $h / 2), max(1, (int)round($w)), max(1, (int)round($h))];
}

/** يركّب لوجو الشركة الحقيقي (من ملف الشركة) على الصورة بهامش آمن، ويحفظها نسخة جديدة في المكتبة. لو مفيش لوجو بترجع نفس الصورة */
function cpl_stamp_logo(array $p, int $mid, ?array $optsOverride = null): int
{
    $wid = (int)ws('id'); $lg = function_exists('kb_logo') ? kb_logo($wid) : null; $m = media_get($wid, $mid);
    $o = $optsOverride ?: cpl_opts($p); if (isset($o['logo_on']) && !$o['logo_on']) return $mid;
    if (!$lg || !$m || !function_exists('imagecreatefromstring')) return $mid;
    $im = @imagecreatefromstring((string)file_get_contents(media_file_path($wid, $m))); $lo = @imagecreatefromstring((string)file_get_contents(media_file_path($wid, $lg)));
    if (!$im || !$lo) return $mid;
    if (!imageistruecolor($im)) imagepalettetotruecolor($im); if (!imageistruecolor($lo)) imagepalettetotruecolor($lo);
    $W = imagesx($im); $Hh = imagesy($im); $lw = imagesx($lo); $lh = imagesy($lo);
    [$x, $y, $tw, $th] = cpl_logo_box($o, $W, $Hh, $lw / max(1, $lh));
    $rs = imagecreatetruecolor($tw, $th); imagealphablending($rs, false); imagesavealpha($rs, true); imagefill($rs, 0, 0, imagecolorallocatealpha($rs, 0, 0, 0, 127));
    imagecopyresampled($rs, $lo, 0, 0, 0, 0, $tw, $th, $lw, $lh);
    $tint = $o['logo_tint'] ?? 'orig'; $op = (int)($o['logo_op'] ?? 100) / 100;
    if ($tint !== 'orig' || $op < 1) for ($yy = 0; $yy < $th; $yy++) for ($xx = 0; $xx < $tw; $xx++) { // لون أبيض/أسود مع الحفاظ على الشفافية، وتخفيف الشفافية
        $c = imagecolorat($rs, $xx, $yy); $a = ($c >> 24) & 127; $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
        if ($tint === 'white') $r = $g = $b = 255; elseif ($tint === 'black') $r = $g = $b = 0;
        $a = 127 - (int)round((127 - $a) * $op); imagesetpixel($rs, $xx, $yy, imagecolorallocatealpha($rs, $r, $g, $b, $a)); }
    if (($o['logo_plate'] ?? 'none') !== 'none') { // خلفية مستديرة خلف اللوجو
        $pd = (int)round($th * 0.3); $col = $o['logo_plate'] === 'light' ? imagecolorallocatealpha($im, 255, 255, 255, 19) : imagecolorallocatealpha($im, 0, 0, 0, 57);
        $x1 = $x - $pd; $y1 = $y - $pd; $x2 = $x + $tw + $pd; $y2 = $y + $th + $pd; $rr = min($pd, 24); imagealphablending($im, true);
        imagefilledrectangle($im, $x1 + $rr, $y1, $x2 - $rr, $y2, $col); imagefilledrectangle($im, $x1, $y1 + $rr, $x1 + $rr - 1, $y2 - $rr, $col); imagefilledrectangle($im, $x2 - $rr + 1, $y1 + $rr, $x2, $y2 - $rr, $col);
        foreach ([[$x1 + $rr, $y1 + $rr, 180, 270], [$x2 - $rr, $y1 + $rr, 270, 360], [$x1 + $rr, $y2 - $rr, 90, 180], [$x2 - $rr, $y2 - $rr, 0, 90]] as [$cx, $cy, $s1, $s2]) imagefilledarc($im, $cx, $cy, 2 * $rr, 2 * $rr, $s1, $s2, $col, IMG_ARC_PIE); }
    imagealphablending($im, true);
    $sx = 0; $sy = 0; $cw = $tw; $ch = $th; $dx = $x; $dy = $y; if ($dx < 0) { $sx = -$dx; $cw += $dx; $dx = 0; } if ($dy < 0) { $sy = -$dy; $ch += $dy; $dy = 0; } if ($dx + $cw > $W) $cw = $W - $dx; if ($dy + $ch > $Hh) $ch = $Hh - $dy;
    if ($cw > 0 && $ch > 0) imagecopy($im, $rs, $dx, $dy, $sx, $sy, $cw, $ch);
    $tmp = tempnam(sys_get_temp_dir(), 'cpl') . '.png'; imagepng($im, $tmp); imagedestroy($im); imagedestroy($lo); imagedestroy($rs);
    try { $row = media_store_path($wid, $tmp, 'logo-' . preg_replace('/^ai-/', '', (string)$m['name']), 'image/png', media_ai_folder()); } catch (Throwable $e) { @unlink($tmp); return $mid; }
    @unlink($tmp); return (int)$row['id'];
}

/** يفك ترميز UTF-8 لمصفوفة نقاط Unicode، والعكس */
function cpl_u8_decode(string $s): array { $o = []; $n = strlen($s); for ($i = 0; $i < $n;) { $c = ord($s[$i]); if ($c < 0x80) { $o[] = $c; $i++; } elseif ($c < 0xE0) { $o[] = (($c & 0x1F) << 6) | (ord($s[$i + 1]) & 0x3F); $i += 2; } elseif ($c < 0xF0) { $o[] = (($c & 0x0F) << 12) | ((ord($s[$i + 1]) & 0x3F) << 6) | (ord($s[$i + 2]) & 0x3F); $i += 3; } else { $o[] = (($c & 0x07) << 18) | ((ord($s[$i + 1]) & 0x3F) << 12) | ((ord($s[$i + 2]) & 0x3F) << 6) | (ord($s[$i + 3]) & 0x3F); $i += 4; } } return $o; }
function cpl_u8_encode(int $cp): string { if ($cp < 0x80) return chr($cp); if ($cp < 0x800) return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F)); if ($cp < 0x10000) return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F)); return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F)); }

/** يحوّل نصًا عربيًا منطقيًا لصيغته البصرية (أشكال العرض + ربط الحروف + لا + اتجاه RTL) عشان GD يرسمه صح.
 *  موديلات الصور بترسم العربي مكسور، فبنرسم النص بنفسنا بدل ما نسيبه للموديل. */
function cpl_ar_shape(string $text): string
{
    static $F = [
        0x0621=>[0xFE80,0,0,0],0x0622=>[0xFE81,0xFE82,0,0],0x0623=>[0xFE83,0xFE84,0,0],0x0624=>[0xFE85,0xFE86,0,0],0x0625=>[0xFE87,0xFE88,0,0],
        0x0626=>[0xFE89,0xFE8A,0xFE8B,0xFE8C],0x0627=>[0xFE8D,0xFE8E,0,0],0x0628=>[0xFE8F,0xFE90,0xFE91,0xFE92],0x0629=>[0xFE93,0xFE94,0,0],
        0x062A=>[0xFE95,0xFE96,0xFE97,0xFE98],0x062B=>[0xFE99,0xFE9A,0xFE9B,0xFE9C],0x062C=>[0xFE9D,0xFE9E,0xFE9F,0xFEA0],0x062D=>[0xFEA1,0xFEA2,0xFEA3,0xFEA4],
        0x062E=>[0xFEA5,0xFEA6,0xFEA7,0xFEA8],0x062F=>[0xFEA9,0xFEAA,0,0],0x0630=>[0xFEAB,0xFEAC,0,0],0x0631=>[0xFEAD,0xFEAE,0,0],0x0632=>[0xFEAF,0xFEB0,0,0],
        0x0633=>[0xFEB1,0xFEB2,0xFEB3,0xFEB4],0x0634=>[0xFEB5,0xFEB6,0xFEB7,0xFEB8],0x0635=>[0xFEB9,0xFEBA,0xFEBB,0xFEBC],0x0636=>[0xFEBD,0xFEBE,0xFEBF,0xFEC0],
        0x0637=>[0xFEC1,0xFEC2,0xFEC3,0xFEC4],0x0638=>[0xFEC5,0xFEC6,0xFEC7,0xFEC8],0x0639=>[0xFEC9,0xFECA,0xFECB,0xFECC],0x063A=>[0xFECD,0xFECE,0xFECF,0xFED0],
        0x0641=>[0xFED1,0xFED2,0xFED3,0xFED4],0x0642=>[0xFED5,0xFED6,0xFED7,0xFED8],0x0643=>[0xFED9,0xFEDA,0xFEDB,0xFEDC],0x0644=>[0xFEDD,0xFEDE,0xFEDF,0xFEE0],
        0x0645=>[0xFEE1,0xFEE2,0xFEE3,0xFEE4],0x0646=>[0xFEE5,0xFEE6,0xFEE7,0xFEE8],0x0647=>[0xFEE9,0xFEEA,0xFEEB,0xFEEC],0x0648=>[0xFEED,0xFEEE,0,0],
        0x0649=>[0xFEEF,0xFEF0,0,0],0x064A=>[0xFEF1,0xFEF2,0xFEF3,0xFEF4],
        0x067E=>[0xFB56,0xFB57,0xFB58,0xFB59],0x0686=>[0xFB7A,0xFB7B,0xFB7C,0xFB7D],0x0698=>[0xFB8A,0xFB8B,0,0],0x06A9=>[0xFB8E,0xFB8F,0xFB90,0xFB91],
        0x06AF=>[0xFB92,0xFB93,0xFB94,0xFB95],0x06CC=>[0xFBFC,0xFBFD,0xFBFE,0xFBFF],
    ];
    static $LA = [0x0622=>[0xFEF5,0xFEF6],0x0623=>[0xFEF7,0xFEF8],0x0625=>[0xFEF9,0xFEFA],0x0627=>[0xFEFB,0xFEFC]];
    $marks = array_flip([0x064B,0x064C,0x064D,0x064E,0x064F,0x0650,0x0651,0x0652,0x0653,0x0654,0x0655,0x0656,0x0657,0x0658,0x0670]);
    $cps = array_values(array_filter(cpl_u8_decode($text), function ($c) use ($marks) { return !isset($marks[$c]); }));
    $u = []; for ($i = 0; $i < count($cps); $i++) { if ($cps[$i] === 0x0644 && isset($cps[$i + 1], $LA[$cps[$i + 1]])) { $u[] = ['la' => $LA[$cps[$i + 1]]]; $i++; } else { $u[] = $cps[$i]; } }
    $isLetter = function ($t) use ($F) { return is_array($t) || isset($F[$t]); };
    $joinsFwd = function ($t) use ($F) { return is_array($t) ? false : (isset($F[$t]) && $F[$t][2] !== 0); };
    $out = [];
    for ($i = 0; $i < count($u); $i++) { $t = $u[$i];
        if (is_array($t)) { $out[] = ($i > 0 && $joinsFwd($u[$i - 1])) ? $t['la'][1] : $t['la'][0]; continue; }
        if (!isset($F[$t])) { $out[] = $t; continue; }
        $prevJoins = $i > 0 && $joinsFwd($u[$i - 1]);
        $nextJoins = $F[$t][2] !== 0 && $i + 1 < count($u) && $isLetter($u[$i + 1]);
        if ($prevJoins && $nextJoins) $out[] = $F[$t][3];
        elseif ($nextJoins) $out[] = $F[$t][2];
        elseif ($prevJoins && $F[$t][1] !== 0) $out[] = $F[$t][1];
        else $out[] = $t; // الصيغة المعزولة = الحرف الأساسي نفسه (خطوط مثل Cairo بترسمها من الـ cmap الأساسي، ومش بتضمّن أشكال العرض المعزولة)
    }
    $isAr = function ($c) { return ($c >= 0x0600 && $c <= 0x06FF) || ($c >= 0xFB50 && $c <= 0xFEFF); };
    $runs = []; $cur = null; $curAr = null;
    foreach ($out as $c) { $a = $isAr($c); if ($cur === null || $a !== $curAr) { if ($cur !== null) $runs[] = [$curAr, $cur]; $cur = [$c]; $curAr = $a; } else { $cur[] = $c; } }
    if ($cur !== null) $runs[] = [$curAr, $cur];
    $res = ''; foreach (array_reverse($runs) as [$a, $run]) { if ($a) $run = array_reverse($run); foreach ($run as $c) $res .= cpl_u8_encode($c); }
    return $res;
}

/** يلفّ النص لأسطر تناسب العرض (يقيس بالنسخة المُشكّلة لو عربي) */
function cpl_wrap_lines(string $text, string $font, int $size, int $maxW, bool $rtl): array
{
    $words = preg_split('/\s+/u', trim($text)); $lines = []; $cur = '';
    foreach ($words as $w) { if ($w === '') continue; $try = $cur === '' ? $w : $cur . ' ' . $w; $shaped = $rtl ? cpl_ar_shape($try) : $try;
        $bb = imagettfbbox($size, 0, $font, $shaped); $tw = abs($bb[2] - $bb[0]);
        if ($tw <= $maxW || $cur === '') $cur = $try; else { $lines[] = $cur; $cur = $w; } }
    if ($cur !== '') $lines[] = $cur; return $lines;
}

/** ملف الخط المستخدم لطباعة العنوان على الصورة حسب اختيار الخطة (يرجع app.ttf الافتراضي لو الملف مش موجود). */
function cpl_font_file(array $p): string
{
    $dir = ROOT_DIR . '/assets/fonts/';
    $map = ['Alexandria' => 'alexandria.ttf', 'Cairo' => 'app.ttf'];
    $f = (string)(cpl_opts($p)['font'] ?? 'auto');
    if (isset($map[$f]) && is_file($dir . $map[$f])) return $dir . $map[$f];
    // الافتراضي: Alexandria لو موجود (أنضف وأعرض للعناوين)، وإلا app.ttf
    return is_file($dir . 'alexandria.ttf') ? $dir . 'alexandria.ttf' : $dir . 'app.ttf';
}

/** لون الهوية (accent) من ألوان اللوجو، وإلا أخضر تيل افتراضي — يُستخدم في هايلايت العنوان. */
function cpl_accent_rgb(array $p): array
{
    // لون تمييز مخصص من إعدادات الخطة له الأولوية
    $custom = (string)(cpl_opts($p)['txt_accent'] ?? '');
    if (preg_match('/^#?([0-9a-fA-F]{6})$/', $custom, $cm)) return [hexdec(substr($cm[1], 0, 2)), hexdec(substr($cm[1], 2, 2)), hexdec(substr($cm[1], 4, 2))];
    if (function_exists('kb_logo_palette')) {
        try {
            foreach ((array)kb_logo_palette((int)ws('id')) as $hex) {
                if (preg_match('/#?([0-9a-fA-F]{6})/', (string)$hex, $mm)) {
                    $r = hexdec(substr($mm[1], 0, 2)); $g = hexdec(substr($mm[1], 2, 2)); $b = hexdec(substr($mm[1], 4, 2));
                    $mx = max($r, $g, $b); $mn = min($r, $g, $b);
                    if ($mx - $mn > 28 && $mx > 60 && $mx < 252) return [$r, $g, $b]; // لون واضح مش أبيض/أسود/رمادي
                }
            }
        } catch (Throwable $e) {}
    }
    return [16, 185, 129];
}

/** مستطيل بحواف دائرية (للهايلايت خلف الكلمة المميّزة). */
function cpl_round_box($im, int $x1, int $y1, int $x2, int $y2, int $r, int $col): void
{
    $r = max(0, min($r, (int)(($x2 - $x1) / 2), (int)(($y2 - $y1) / 2)));
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $col);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $col);
    if ($r > 0) { imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $col); imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $col); imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $col); imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $col); }
}

/** يختار أنسب منطقة للعنوان (فوق/تحت) حسب أهدأ وأغمق جزء في الصورة — فالمكان بيتغيّر مع كل صورة. */
function cpl_text_zone($im, int $W, int $H): string
{
    $score = function (int $y0, int $y1) use ($im, $W) {
        $sx = max(1, (int)($W / 48)); $sy = max(1, (int)(($y1 - $y0) / 40)); $s = 0.0; $s2 = 0.0; $n = 0;
        for ($y = $y0; $y < $y1; $y += $sy) for ($x = 0; $x < $W; $x += $sx) {
            $c = imagecolorat($im, $x, $y); $l = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
            $s += $l; $s2 += $l * $l; $n++;
        }
        $m = $n ? $s / $n : 128; $v = $n ? max(0, $s2 / $n - $m * $m) : 0; return $m + sqrt($v) * 0.6; // أقل = أهدأ وأغمق = أنسب للنص
    };
    return $score((int)($H * 0.60), $H) < $score(0, (int)($H * 0.34)) - 6 ? 'bottom' : 'top';
}

/** متوسط لمعان + تباين منطقة (أقل = أهدأ وأغمق = أنسب للنص). */
function cpl_region_score($im, int $x0, int $y0, int $x1, int $y1): float
{
    $x0 = max(0, $x0); $y0 = max(0, $y0); $x1 = min(imagesx($im), $x1); $y1 = min(imagesy($im), $y1);
    if ($x1 <= $x0 || $y1 <= $y0) return 999.0;
    $sx = max(1, (int)(($x1 - $x0) / 44)); $sy = max(1, (int)(($y1 - $y0) / 44)); $s = 0.0; $s2 = 0.0; $n = 0;
    for ($y = $y0; $y < $y1; $y += $sy) for ($x = $x0; $x < $x1; $x += $sx) {
        $c = imagecolorat($im, $x, $y); $l = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
        $s += $l; $s2 += $l * $l; $n++;
    }
    $m = $n ? $s / $n : 128; $v = $n ? max(0, $s2 / $n - $m * $m) : 0; return $m + sqrt($v) * 0.6;
}

/** يرسم سطر عنوان مع تلوين الكلمة المفتاحية جوّه السطر (آمن للعربي: الحروف ما بتتوصلش عبر المسافات).
 *  لو $boxKey يرسم خلفية بلون الهوية خلف الكلمة، وإلا يلوّنها بلون الهوية. يرجّع [x1,x2,y] لحدود الكلمة أو null. */
/** إعدادات خدمة رندر المؤثرات (fx-service على VPS): [url, token]. فاضي = غير مفعّلة. */
function cpl_fx_cfg(): array
{
    $url = (string)(getenv('FX_RENDER_URL') ?: (function_exists('setting') ? setting('fx_render_url', '') : ''));
    $tok = (string)(getenv('FX_RENDER_TOKEN') ?: (function_exists('setting') ? setting('fx_render_token', '') : ''));
    return [rtrim(trim($url), '/'), trim($tok)];
}

/** يطلب من خدمة fx-service رندر عنوان بتأثير، ويرجّع PNG خام (binary) أو null للرجوع لـ GD.
 *  $effect: shadow/outline/sticker/3d/gold/glow. $accentHex بـ/بدون #. */
function cpl_fx_render(string $text, string $effect, string $accentHex, string $fontKey, int $size, int $width, bool $rtl, string $align = '', string $keyword = '', int $height = 0): ?string
{
    if ($effect === '' || $effect === 'none' || !function_exists('curl_init')) return null;
    [$url, $tok] = cpl_fx_cfg();
    if ($url === '') return null;
    $payload = json_encode([
        'token' => $tok, 'text' => $text, 'keyword' => $keyword, 'effect' => $effect,
        'color' => ltrim($accentHex, '#'), 'size' => $size, 'width' => $width, 'height' => $height,
        'rtl' => $rtl, 'align' => $align, 'font' => $fontKey, 'scale' => 2,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url . '/render');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Fx-Token: ' . $tok],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5]);
    $out = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($code !== 200 || strpos($ctype, 'image/png') === false || !$out) { error_log('cpl_fx_render failed: http ' . $code); return null; }
    return $out;
}

/** يركّب PNG خام (من الخدمة، مرندر بـ scale) على صورة GD عند (destX,destY) مع مراعاة الشفافية. */
function cpl_fx_place($im, string $png, int $destX, int $destY, int $scale = 2): bool
{
    $src = @imagecreatefromstring($png); if (!$src) return false;
    imagealphablending($src, false); imagesavealpha($src, true);
    $sw = imagesx($src); $sh = imagesy($src);
    imagealphablending($im, true); imagesavealpha($im, true);
    if ($scale <= 1) { imagecopy($im, $src, $destX, $destY, 0, 0, $sw, $sh); imagedestroy($src); return true; }
    $tw = max(1, (int)round($sw / $scale)); $th = max(1, (int)round($sh / $scale));
    $tmp = imagecreatetruecolor($tw, $th); imagealphablending($tmp, false); imagesavealpha($tmp, true);
    imagefilledrectangle($tmp, 0, 0, $tw, $th, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);
    imagecopy($im, $tmp, $destX, $destY, 0, 0, $tw, $th);
    imagedestroy($tmp); imagedestroy($src); return true;
}

/** محاولة رسم كتلة عنوان عبر خدمة المؤثرات. يرجّع true لو ركّبها (ونتخطّى GD)، أو false للرجوع لـ GD. */
function cpl_fx_block($im, array $lines, int $size, int $colLeft, int $colW, int $yTop, bool $rtl, string $align, string $key, int $blockH = 0): bool
{
    $fx = (string)($GLOBALS['cpl_fx'] ?? 'none');
    if ($fx === 'none' || $fx === '') return false;
    $acc = (array)($GLOBALS['cpl_fx_acc'] ?? [37, 99, 235]);
    $hex = sprintf('%02x%02x%02x', (int)$acc[0], (int)$acc[1], (int)$acc[2]);
    $png = cpl_fx_render(implode("\n", $lines), $fx, $hex, (string)($GLOBALS['cpl_fx_font'] ?? 'auto'), $size, $colW, $rtl, $align, $key, $blockH);
    if ($png === null) return false;
    $pad = (int)round($size * 0.55);
    return cpl_fx_place($im, $png, $colLeft - $pad, $yTop - $pad);
}

/** يرسم نصًّا بتأثير اختياري (ظل/حدود/ثري دي/نيون) مقروء من $GLOBALS['cpl_fx']. none = عادي. */
function cpl_ttf_fx($im, int $size, int $x, int $y, int $color, string $font, string $text): void
{
    $fx = (string)($GLOBALS['cpl_fx'] ?? 'none');
    if ($fx === 'none' || $fx === '') { imagettftext($im, $size, 0, $x, $y, $color, $font, $text); return; }
    $acc = (array)($GLOBALS['cpl_fx_acc'] ?? [37, 99, 235]);
    $s = max(2, (int)round($size * 0.055));
    $ring = [[-$s, 0], [$s, 0], [0, -$s], [0, $s], [-$s, -$s], [$s, $s], [-$s, $s], [$s, -$s]];
    if ($fx === 'shadow') {
        imagettftext($im, $size, 0, $x + $s, $y + $s, imagecolorallocatealpha($im, 10, 12, 18, 65), $font, $text);
    } elseif ($fx === 'outline') {
        $oc = imagecolorallocate($im, 15, 17, 22);
        foreach ($ring as [$dx, $dy]) imagettftext($im, $size, 0, $x + $dx, $y + $dy, $oc, $font, $text);
    } elseif ($fx === '3d') {
        for ($i = $s * 3; $i >= 1; $i--) imagettftext($im, $size, 0, $x + $i, $y + $i, imagecolorallocatealpha($im, 12, 14, 20, 38), $font, $text);
        $oc = imagecolorallocate($im, 15, 17, 22);
        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) imagettftext($im, $size, 0, $x + $dx, $y + $dy, $oc, $font, $text);
    } elseif ($fx === 'glow') {
        $g = imagecolorallocatealpha($im, (int)$acc[0], (int)$acc[1], (int)$acc[2], 80);
        foreach ($ring as [$dx, $dy]) imagettftext($im, $size, 0, $x + $dx, $y + $dy, $g, $font, $text);
    }
    imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
}

function cpl_draw_hline($im, int $size, string $font, string $line, string $key, bool $rtl, int $colLeft, int $colRight, int $y, int $white, int $acc, int $onAcc, int $shadow, bool $boxKey, string $align = 'right'): ?array
{
    $line = trim($line); if ($line === '') return null;
    $off = -1;
    if ($key !== '' && preg_match('/(?<![^\s])' . preg_quote($key, '/') . '(?![^\s])/u', $line, $mm, PREG_OFFSET_CAPTURE)) $off = (int)$mm[0][1];
    $ax = function (int $wd) use ($align, $colLeft, $colRight) { if ($align === 'left') return $colLeft; if ($align === 'center') return $colLeft + (int)round((($colRight - $colLeft) - $wd) / 2); return $colRight - $wd; };
    if ($off < 0) { // مفيش كلمة مفتاحية في السطر: أبيض عادي
        $sh = $rtl ? cpl_ar_shape($line) : $line; $bb = imagettfbbox($size, 0, $font, $sh); $tw = abs($bb[2] - $bb[0]);
        $x = $ax($tw);
        cpl_ttf_fx($im, $size, $x, $y, $white, $font, $sh);
        return null;
    }
    $before = rtrim(substr($line, 0, $off)); $after = ltrim(substr($line, $off + strlen($key)));
    $segs = []; if ($before !== '') $segs[] = [$before . ' ', 'w']; $segs[] = [$key, 'k']; if ($after !== '') $segs[] = [' ' . $after, 'w'];
    $meas = []; $tot = 0;
    foreach ($segs as [$txt, $role]) { $sh = $rtl ? cpl_ar_shape($txt) : $txt; $bb = imagettfbbox($size, 0, $font, $sh); $w = abs($bb[2] - $bb[0]); $meas[] = [$sh, $role, $w]; $tot += $w; }
    $x = $ax($tot); $order = $rtl ? array_reverse($meas) : $meas;
    $keyX1 = null; $keyX2 = null;
    foreach ($order as [$sh, $role, $w]) {
        if ($role === 'k') { $keyX1 = $x; $keyX2 = $x + $w;
            if ($boxKey) {
                $padx = (int)round($size * 0.22); $pady = (int)round($size * 0.14); $rad = (int)round($size * 0.2);
                cpl_round_box($im, $x - $padx, $y - $size - $pady, $x + $w + $padx, $y + $pady, $rad, $acc);
                imagettftext($im, $size, 0, $x, $y, $onAcc, $font, $sh);
            } else {
                cpl_ttf_fx($im, $size, $x, $y, $acc, $font, $sh);
            }
        } else {
            cpl_ttf_fx($im, $size, $x, $y, $white, $font, $sh);
        }
        $x += $w;
    }
    return [$keyX1, $keyX2, $y];
}

/** يطبع العنوان على الصورة بنفسنا (عربي سليم مضمون) — محرّك تخطيط يختار مكان النص (فوق/تحت + يمين/شمال/كامل)
 *  حسب أهدأ منطقة في كل صورة، وقالبًا احترافيًا مختلفًا لكل منشور (كلمة مفتاحية ملوّنة جوّه السطر). */
function cpl_stamp_text(array $p, int $mid, string $headline, array $ov = [], bool $preview = false): int
{
    $headline = trim($headline); if ($headline === '') return $mid;
    // الكلمة المفتاحية: الموديل بيعلّمها بـ«» — نستخرجها ونشيل العلامات. لو مفيش، ناخد آخر كلمة.
    $key = ''; if (preg_match('/«\s*(.+?)\s*»/u', $headline, $kk)) $key = trim($kk[1]);
    // إزالة فواصل زخرفية يحطها الموديل بين العربي والإنجليزي (| ｜ /) — بتتطبع كشرطة غلط
    $headline = trim(preg_replace(['/\s*[|｜]\s*/u', '/\s+/u'], [' ', ' '], str_replace(['«', '»'], '', $headline)));
    if ($key === '') { $pp = preg_split('/\s+/u', $headline); $lastW = (string)end($pp); if (mb_strlen($lastW) >= 2) $key = $lastW; }
    $wid = (int)ws('id'); $m = media_get($wid, $mid); if (!$m || !function_exists('imagettftext')) return $mid;
    $font = cpl_font_file($p); if (!is_file($font)) return $mid;
    $im = @imagecreatefromstring((string)file_get_contents(media_file_path($wid, $m))); if (!$im) return $mid;
    if (!imageistruecolor($im)) imagepalettetotruecolor($im);
    $W = imagesx($im); $H = imagesy($im); $rtl = (bool)preg_match('/[\x{0600}-\x{06FF}]/u', $headline);
    $margin = (int)round($W * 0.08);
    $to = $ov + cpl_opts($p); // إعدادات النص: تعديلات المنشور لها الأولوية على إعدادات الخطة

    // --- اختيار المنطقة الرأسية (فوق/تحت): من الإعدادات أو تلقائي حسب الأهدأ ---
    $zone = in_array($to['txt_pos'] ?? 'auto', ['top', 'bottom'], true) ? $to['txt_pos'] : cpl_text_zone($im, $W, $H);
    $bandY0 = $zone === 'top' ? 0 : (int)round($H * 0.52); $bandY1 = $zone === 'top' ? (int)round($H * 0.48) : $H;
    // --- اختيار الجهة الأفقية: لو جنب أهدأ بوضوح من التاني، نحط النص في عمود جانبي (زي شغل الوكالات) ---
    $scoreL = cpl_region_score($im, 0, $bandY0, (int)round($W * 0.52), $bandY1);
    $scoreR = cpl_region_score($im, (int)round($W * 0.48), $bandY0, $W, $bandY1);
    $anchor = 'full'; if (abs($scoreL - $scoreR) > 9) $anchor = $scoreL < $scoreR ? 'left' : 'right';
    $colW = $anchor === 'full' ? ($W - 2 * $margin) : (int)round($W * 0.60);
    $colLeft = $anchor === 'right' ? ($W - $margin - $colW) : $margin;
    $colRight = $colLeft + $colW;

    // --- قياس الخط ولفّ الأسطر داخل العمود المختار (الحجم من الإعدادات) ---
    $szMul = isset($to['txt_scale']) ? max(50, min(180, (int)$to['txt_scale'])) / 100 : (['s' => 0.86, 'm' => 1.0, 'l' => 1.16][$to['txt_size'] ?? 'm'] ?? 1.0);
    $size = (int)round($W * ($anchor === 'full' ? 0.072 : 0.064) * $szMul); $lines = null; $min = max(12, (int)round($W * 0.028));
    $maxLines = $anchor === 'full' ? 3 : 4;
    for (; $size >= $min; $size -= 2) { $w = cpl_wrap_lines($headline, $font, $size, $colW, $rtl); if (count($w) <= $maxLines) { $lines = $w; break; } }
    if (!$lines) $lines = array_slice(cpl_wrap_lines($headline, $font, $size, $colW, $rtl), 0, $maxLines);
    $lh = (int)round($size * ($rtl ? 1.56 : 1.46)); $blockH = $lh * count($lines);

    // --- قالب التمييز: من الإعدادات أو تلقائي (يتغيّر لكل منشور) ---
    $vmap = ['box' => 0, 'underline' => 1, 'color' => 2];
    $variant = isset($vmap[$to['txt_variant'] ?? 'auto']) ? $vmap[$to['txt_variant']] : (crc32($headline . '|' . $mid) % 3 + 3) % 3;
    $top = $zone === 'bottom' ? max((int)round($H * 0.40), $H - (int)round($H * 0.08) - $blockH) : (int)round($H * 0.08);
    // موضع رأسي حرّ (سحب على الصورة): نسبة من ارتفاع الصورة لأعلى كتلة النص
    if (isset($to['txt_y']) && is_numeric($to['txt_y'])) {
        $top = (int)round($H * max(2, min(92, (float)$to['txt_y'])) / 100);
        $top = max((int)round($H * 0.02), min($H - $blockH - (int)round($H * 0.02), $top));
        $zone = ($top + $blockH / 2) > $H * 0.5 ? 'bottom' : 'top';
    }
    imagealphablending($im, true); imagesavealpha($im, true);

    // --- سكريم متدرّج ناعم (smoothstep) بلا حد أفقي حاد — يسيب الصورة «تتنفّس» ---
    $txtTop = $top - (int)round($size * 1.4); $txtBot = $top + $blockH;
    // سطوع منطقة النص: كل ما كانت أفتح، نقوّي السكريم تلقائيًا ليبقى النص الأبيض مقروءًا على أي خلفية
    $by0 = max(0, min($txtTop, $top)); $by1 = min($H - 1, $top + $blockH);
    $lsum = 0.0; $lc = 0; $stY = max(1, (int)(($by1 - $by0) / 14)); $stX = max(1, (int)($W / 14));
    for ($sy = $by0; $sy <= $by1; $sy += $stY) for ($sx = 0; $sx < $W; $sx += $stX) {
        $rgb = imagecolorat($im, $sx, $sy); $lsum += 0.299 * (($rgb >> 16) & 255) + 0.587 * (($rgb >> 8) & 255) + 0.114 * ($rgb & 255); $lc++;
    }
    $zlum = $lc ? $lsum / $lc : 60.0;
    $peak = max(0.80, min(0.97, 0.74 + ($zlum / 255) * 0.3)); $span = max(1, $blockH + (int)round($size * 3.4));
    for ($yy = 0; $yy < $H; $yy++) {
        if ($zone === 'top') { $d = $yy <= $txtBot ? 0.0 : ($yy - $txtBot) / $span; }
        else { $d = $yy >= $txtTop ? 0.0 : ($txtTop - $yy) / $span; }
        $u = max(0.0, 1.0 - $d); $s = $u * $u * (3 - 2 * $u);
        $a = (int)round(127 * (1 - $peak * $s)); if ($a >= 127) continue;
        imagefilledrectangle($im, 0, $yy, $W, $yy, imagecolorallocatealpha($im, 0, 0, 0, $a));
    }

    $white = imagecolorallocate($im, 255, 255, 255);
    $shadow = imagecolorallocatealpha($im, 0, 0, 0, 62);
    // لون التمييز: تعديل المنشور ($to) له الأولوية، وإلا من هوية الشركة
    $cAcc = (string)($to['txt_accent'] ?? '');
    if (preg_match('/^#?([0-9a-fA-F]{6})$/', $cAcc, $cm)) { $ar = hexdec(substr($cm[1], 0, 2)); $ag = hexdec(substr($cm[1], 2, 2)); $ab = hexdec(substr($cm[1], 4, 2)); }
    else { [$ar, $ag, $ab] = cpl_accent_rgb($p); }
    $acc = imagecolorallocate($im, $ar, $ag, $ab);
    $lum = 0.299 * $ar + 0.587 * $ag + 0.114 * $ab; $onAcc = $lum > 150 ? imagecolorallocate($im, 12, 16, 24) : $white;
    // المحاذاة الأفقية: من الإعدادات (يمين/وسط/يسار) أو تلقائي حسب اتجاه النص
    $align = (string)($to['txt_align'] ?? 'auto');
    if (!in_array($align, ['right', 'left', 'center'], true)) $align = $rtl ? 'right' : 'left';
    // (أُزيل شريط لون الهوية فوق العنوان بناءً على طلب المستخدم)
    $y = $top + $size;
    // الكلمة المفتاحية تتلوّن جوّه السطر: قالب 0 = خلفية بلون الهوية، قالب 1/2 = نص ملوّن (مع خط تحتها في 1)
    $boxKey = $variant === 0; $keySpan = null;
    $GLOBALS['cpl_fx'] = (string)($to['txt_effect'] ?? 'none'); $GLOBALS['cpl_fx_acc'] = [$ar, $ag, $ab]; $GLOBALS['cpl_fx_font'] = (string)($to['font'] ?? 'auto');
    $usedFx = cpl_fx_block($im, $lines, $size, $colLeft, $colRight - $colLeft, $top, $rtl, $align, $key, $blockH);
    if (!$usedFx) foreach ($lines as $ln) {
        $sp = cpl_draw_hline($im, $size, $font, $ln, $key, $rtl, $colLeft, $colRight, $y, $white, $acc, $onAcc, $shadow, $boxKey, $align);
        if ($sp && !$keySpan) $keySpan = $sp;
        $y += $lh;
    }
    // قالب 1: خط رفيع بلون الهوية تحت الكلمة المفتاحية مباشرة
    if ($variant === 1 && $keySpan) {
        [$kx1, $kx2, $ky] = $keySpan; $uh = max(4, (int)round($size * 0.1)); $uy = $ky + (int)round($size * 0.2);
        cpl_round_box($im, (int)$kx1, $uy, (int)$kx2, $uy + $uh, (int)round($uh / 2), $acc);
    }
    $tmp = tempnam(sys_get_temp_dir(), 'cpt') . '.png'; imagepng($im, $tmp); imagedestroy($im);
    if ($preview) { $GLOBALS['__cpl_prev'] = $tmp; return 0; } // معاينة: نرجّع المسار بدون حفظ في المكتبة
    try { $row = media_store_path($wid, $tmp, 'txt-' . preg_replace('/^(ai|logo|txt)-/', '', (string)$m['name']), 'image/png', media_ai_folder()); } catch (Throwable $e) { @unlink($tmp); return $mid; }
    @unlink($tmp); return (int)$row['id'];
}

/* ============================ محرّك قوالب التخطيط (تصميم متكامل بالهوية) ============================ */

/** قائمة القوالب المتاحة (مفتاح ⇦ اسم عربي). auto = نوّع تلقائيًا لكل منشور. */
function cpl_layouts(): array
{
    return ['auto' => t('تلقائي (تنويع)'), 'full' => t('الصورة كاملة + نص'), 'brand_footer' => t('شريط سفلي بالهوية'), 'top_band' => t('شريط علوي بالهوية'),
        'side_card' => t('بطاقة جانبية'), 'overlay_panel' => t('لوحة زجاجية'), 'arch' => t('قوس علوي')];
}

/** يلوّن تدرّجًا رأسيًا بين لونين في مستطيل. */
function cpl_grad_v($im, int $x, int $y, int $w, int $h, array $c1, array $c2): void
{
    for ($i = 0; $i < $h; $i++) { $t = $h > 1 ? $i / ($h - 1) : 0;
        $r = (int)round($c1[0] + ($c2[0] - $c1[0]) * $t); $g = (int)round($c1[1] + ($c2[1] - $c1[1]) * $t); $b = (int)round($c1[2] + ($c2[2] - $c1[2]) * $t);
        imagefilledrectangle($im, $x, $y + $i, $x + $w, $y + $i, imagecolorallocate($im, $r, $g, $b)); }
}

/** يقص صورة (cover) داخل مستطيل الوجهة مع حواف دائرية اختيارية وتعتيم اختياري، ويركّبها على $im. */
function cpl_place_photo($im, $src, int $dx, int $dy, int $dw, int $dh, int $radius = 0, float $darken = 0.0): void
{
    if ($dw < 2 || $dh < 2) return; $sw = imagesx($src); $sh = imagesy($src); if ($sw < 2 || $sh < 2) return;
    $srcAR = $sw / $sh; $dstAR = $dw / $dh; // cover: نملأ الوجهة ونقصّ الزيادة
    if ($srcAR > $dstAR) { $ch = $sh; $cw = (int)round($sh * $dstAR); $sx = (int)round(($sw - $cw) / 2); $sy = 0; }
    else { $cw = $sw; $ch = (int)round($sw / $dstAR); $sx = 0; $sy = (int)round(($sh - $ch) / 2); }
    $tile = imagecreatetruecolor($dw, $dh); imagealphablending($tile, false); imagesavealpha($tile, true);
    imagecopyresampled($tile, $src, 0, 0, $sx, $sy, $dw, $dh, $cw, $ch);
    if ($darken > 0.001) { imagealphablending($tile, true); $a = (int)round(127 * (1 - min(0.95, $darken))); imagefilledrectangle($tile, 0, 0, $dw, $dh, imagecolorallocatealpha($tile, 0, 0, 0, $a)); imagealphablending($tile, false); }
    if ($radius > 0) { $r = min($radius, (int)($dw / 2), (int)($dh / 2)); $tr = imagecolorallocatealpha($tile, 0, 0, 0, 127);
        foreach ([[0, 0, $r, $r], [$dw - $r, 0, $dw - $r - 1, $r], [0, $dh - $r, $r, $dh - $r - 1], [$dw - $r, $dh - $r, $dw - $r - 1, $dh - $r - 1]] as [$ox, $oy, $ccx, $ccy]) {
            for ($yy = 0; $yy < $r; $yy++) for ($xx = 0; $xx < $r; $xx++) { $px = $ox + $xx; $py = $oy + $yy; $d = sqrt(($px - $ccx) * ($px - $ccx) + ($py - $ccy) * ($py - $ccy)); if ($d > $r) imagesetpixel($tile, $px, $py, $tr); } } }
    imagealphablending($im, true); imagecopy($im, $tile, $dx, $dy, 0, 0, $dw, $dh); imagedestroy($tile);
}

/** يرسم كتلة العنوان (شريط هوية + أسطر ملفوفة مع تلوين الكلمة المفتاحية) داخل عمود، ويرجّع ارتفاع الكتلة. */
function cpl_draw_title_block($im, string $font, string $headline, string $key, int $size, int $colLeft, int $colRight, int $yTop, string $align, int $white, int $acc, int $onAcc, bool $rtl, int $maxLines, bool $kicker = true): int
{
    $colW = $colRight - $colLeft; $lines = null; $min = max(14, (int)round($size * 0.5));
    for ($s = $size; $s >= $min; $s -= 2) { $w = cpl_wrap_lines($headline, $font, $s, $colW, $rtl); if (count($w) <= $maxLines) { $lines = $w; $size = $s; break; } }
    if (!$lines) { $lines = array_slice(cpl_wrap_lines($headline, $font, $size, $colW, $rtl), 0, $maxLines); }
    $lh = (int)round($size * ($rtl ? 1.5 : 1.4)); $y = $yTop + $size; $top0 = $yTop;
    // لو خدمة المؤثرات مفعّلة ونجحت: نركّب الكتلة ونتخطّى رسم GD
    if (cpl_fx_block($im, $lines, $size, $colLeft, $colW, $yTop, $rtl, $align, $key, $lh * count($lines))) return $lh * count($lines);
    // (أُزيل شريط لون الهوية فوق العنوان بناءً على طلب المستخدم)
    $shadow = imagecolorallocatealpha($im, 0, 0, 0, 80);
    foreach ($lines as $ln) { cpl_draw_hline($im, $size, $font, $ln, $key, $rtl, $colLeft, $colRight, $y, $white, $acc, $onAcc, $shadow, false, $align); $y += $lh; }
    return $y - $top0;
}

/** يركّب تصميمًا متكاملًا بالهوية (قالب تخطيط) حول الصورة الخام. يرجّع media id (أو $rawMid لو فشل). */
function cpl_render_layout(array $p, int $rawMid, string $headline, array $ov = [], bool $preview = false): int
{
    $headline = trim($headline); if ($headline === '') return $rawMid;
    $key = ''; if (preg_match('/«\s*(.+?)\s*»/u', $headline, $kk)) $key = trim($kk[1]);
    $headline = trim(preg_replace(['/\s*[|｜]\s*/u', '/\s+/u'], [' ', ' '], str_replace(['«', '»'], '', $headline)));
    if ($key === '') { $pp = preg_split('/\s+/u', $headline); $lastW = (string)end($pp); if (mb_strlen($lastW) >= 2) $key = $lastW; }
    $wid = (int)ws('id'); $m = media_get($wid, $rawMid); if (!$m || !function_exists('imagettftext')) return $rawMid;
    $font = cpl_font_file($p); if (!is_file($font)) return $rawMid;
    $src = @imagecreatefromstring((string)file_get_contents(media_file_path($wid, $m))); if (!$src) return $rawMid;
    if (!imageistruecolor($src)) imagepalettetotruecolor($src);
    $to = $ov + cpl_opts($p);
    // المقاس الناتج من نسبة الخطة
    $ar = cpl_aspect($p); [$W, $H] = ['4:5' => [1080, 1350], '1:1' => [1080, 1080], '9:16' => [1080, 1920], '16:9' => [1350, 760]][$ar] ?? [1080, 1350];
    $rtl = (bool)preg_match('/[\x{0600}-\x{06FF}]/u', $headline);
    // لون الهوية
    $cAcc = (string)($to['txt_accent'] ?? '');
    if (preg_match('/^#?([0-9a-fA-F]{6})$/', $cAcc, $cm)) { $ar2 = hexdec(substr($cm[1], 0, 2)); $ag = hexdec(substr($cm[1], 2, 2)); $ab = hexdec(substr($cm[1], 4, 2)); }
    else { [$ar2, $ag, $ab] = cpl_accent_rgb($p); }
    $accRGB = [$ar2, $ag, $ab]; $lum = 0.299 * $ar2 + 0.587 * $ag + 0.114 * $ab;
    $GLOBALS['cpl_fx'] = (string)($to['txt_effect'] ?? 'none'); $GLOBALS['cpl_fx_acc'] = $accRGB; $GLOBALS['cpl_fx_font'] = (string)($to['font'] ?? 'auto');
    // خلفية داكنة مشتقة من لون الهوية (نسخة غامقة جدًا) — تحافظ على التناسق
    $dark = [max(8, (int)round($ar2 * 0.16) + 8), max(10, (int)round($ag * 0.16) + 10), max(14, (int)round($ab * 0.16) + 16)];
    // اختيار القالب
    $lay = (string)($to['layout'] ?? 'auto'); $keys = array_keys(cpl_layouts()); $keys = array_values(array_filter($keys, fn($k) => $k !== 'auto'));
    if (!in_array($lay, $keys, true)) { $lay = $keys[(crc32($headline . '|' . $rawMid) % count($keys) + count($keys)) % count($keys)]; }

    $im = imagecreatetruecolor($W, $H); imagealphablending($im, true); imagesavealpha($im, true);
    $white = imagecolorallocate($im, 255, 255, 255);
    $acc = imagecolorallocate($im, $ar2, $ag, $ab); $onAcc = $lum > 150 ? imagecolorallocate($im, 12, 16, 24) : $white;
    $align = (string)($to['txt_align'] ?? 'auto'); if (!in_array($align, ['right', 'left', 'center'], true)) $align = $rtl ? 'right' : 'left';
    $mg = (int)round($W * 0.075);

    if ($lay === 'full') { // الصورة كاملة (full-bleed) + تدرّج قوي تحت + العنوان فوق الصورة
        cpl_place_photo($im, $src, 0, 0, $W, $H, 0, 0.0);
        // تدرّج داكن من الأسفل (مموّه بلون الهوية قليلًا) لإبراز النص مع إبقاء الصورة كاملة
        $gh = (int)round($H * 0.52); $gy = $H - $gh;
        for ($i = 0; $i < $gh; $i++) { $t = $i / max(1, $gh - 1); $s = $t * $t; // أقوى تحت
            $a = (int)round(127 * (1 - 0.92 * $s)); if ($a >= 127) continue;
            imagefilledrectangle($im, 0, $gy + $i, $W, $gy + $i, imagecolorallocatealpha($im, max(0, (int)($dark[0] * 0.5)), max(0, (int)($dark[1] * 0.5)), max(0, (int)($dark[2] * 0.6)), $a)); }
        $size = (int)round($W * 0.082);
        // نقيس كتلة العنوان ونحطها قرب الأسفل
        $tmpLines = cpl_wrap_lines($headline, $font, $size, $W - 2 * $mg, $rtl); $nl = max(1, min(3, count($tmpLines)));
        $blockH = (int)round($size * 1.5 * $nl); $yTop = $H - (int)round($H * 0.07) - $blockH;
        cpl_draw_title_block($im, $font, $headline, $key, $size, $mg, $W - $mg, $yTop, $align, $white, $acc, $onAcc, $rtl, 3, true);
    } elseif ($lay === 'brand_footer') { // صورة فوق + شريط هوية سفلي بالعنوان
        $footH = (int)round($H * 0.30); $phH = $H - $footH;
        cpl_place_photo($im, $src, 0, 0, $W, $phH, 0, 0.12);
        cpl_grad_v($im, 0, $phH, $W, $footH, [$ar2, $ag, $ab], [max(0, (int)($ar2 * 0.7)), max(0, (int)($ag * 0.7)), max(0, (int)($ab * 0.7))]);
        $col1 = $align === 'left' ? $mg : ($W - $mg); // عمود كامل
        $size = (int)round($W * 0.07);
        cpl_draw_title_block($im, $font, $headline, $key, $size, $mg, $W - $mg, $phH + (int)round($footH * 0.30), $align, $onAcc, $onAcc === $white ? $white : $white, $onAcc, $rtl, 3, false);
    } elseif ($lay === 'top_band') { // شريط علوي بالهوية + صورة تحت بحواف علوية دائرية
        $bandH = (int)round($H * 0.34);
        cpl_grad_v($im, 0, 0, $W, $bandH, [$ar2, $ag, $ab], [max(0, (int)($ar2 * 0.72)), max(0, (int)($ag * 0.72)), max(0, (int)($ab * 0.72))]);
        cpl_place_photo($im, $src, 0, $bandH, $W, $H - $bandH, 0, 0.05);
        $size = (int)round($W * 0.068);
        cpl_draw_title_block($im, $font, $headline, $key, $size, $mg, $W - $mg, (int)round($bandH * 0.30), $align, $onAcc, $onAcc === $white ? $white : $white, $onAcc, $rtl, 3, false);
    } elseif ($lay === 'side_card') { // خلفية هوية + بطاقة صورة جانبية + العنوان في العمود الآخر
        cpl_grad_v($im, 0, 0, $W, $H, $dark, [max(0, $dark[0] - 4), max(0, $dark[1] - 4), max(2, $dark[2] + 10)]);
        $cardW = (int)round($W * 0.52); $cardH = (int)round($H * 0.62); $cardY = (int)round(($H - $cardH) / 2);
        $cardX = $rtl ? $mg : ($W - $mg - $cardW); // البطاقة في جهة، النص في الجهة التانية
        cpl_place_photo($im, $src, $cardX, $cardY, $cardW, $cardH, (int)round($W * 0.045), 0.0);
        $tx0 = $rtl ? ($cardX + $cardW + (int)round($W * 0.04)) : $mg; $tx1 = $rtl ? ($W - $mg) : ($cardX - (int)round($W * 0.04));
        $size = (int)round($W * 0.06);
        cpl_draw_title_block($im, $font, $headline, $key, $size, $tx0, $tx1, $cardY + (int)round($cardH * 0.12), $align, $white, $acc, $onAcc, $rtl, 5, true);
    } elseif ($lay === 'overlay_panel') { // صورة كاملة + لوحة زجاجية بالعنوان أسفل
        cpl_place_photo($im, $src, 0, 0, $W, $H, 0, 0.08);
        $panH = (int)round($H * 0.30); $panY = $H - $panH - $mg; $panX = $mg; $panW = $W - 2 * $mg; $rad = (int)round($W * 0.04);
        // لوحة شبه شفافة داكنة
        cpl_round_box($im, $panX, $panY, $panX + $panW, $panY + $panH, $rad, imagecolorallocatealpha($im, $dark[0], $dark[1], $dark[2], 38));
        // شريط لون هوية على الحافة الابتدائية
        $edge = (int)round($W * 0.012); if ($rtl) cpl_round_box($im, $panX + $panW - $edge, $panY + $rad, $panX + $panW, $panY + $panH - $rad, (int)round($edge / 2), $acc);
        else cpl_round_box($im, $panX, $panY + $rad, $panX + $edge, $panY + $panH - $rad, (int)round($edge / 2), $acc);
        $size = (int)round($W * 0.058); $pad = (int)round($W * 0.045);
        cpl_draw_title_block($im, $font, $headline, $key, $size, $panX + $pad, $panX + $panW - $pad, $panY + (int)round($panH * 0.26), $align, $white, $acc, $onAcc, $rtl, 3, true);
    } else { // arch: خلفية هوية داكنة + صورة بقوس علوي + عنوان أسفل
        cpl_grad_v($im, 0, 0, $W, $H, $dark, [max(0, $dark[0] - 3), max(0, $dark[1] - 2), max(4, $dark[2] + 12)]);
        $pad = $mg; $phW = $W - 2 * $pad; $phH = (int)round($H * 0.56); $phY = $pad;
        // قوس: نرسم الصورة بحواف دائرية كبيرة أعلى
        cpl_place_photo($im, $src, $pad, $phY, $phW, $phH, (int)round($phW * 0.5), 0.0);
        // الحواف السفلية مش لازم تبقى قوس — نعيد رسم النص تحت
        $size = (int)round($W * 0.066);
        cpl_draw_title_block($im, $font, $headline, $key, $size, $pad, $W - $pad, $phY + $phH + (int)round($H * 0.05), $align, $white, $acc, $onAcc, $rtl, 3, true);
    }
    imagedestroy($src);
    $tmp = tempnam(sys_get_temp_dir(), 'cpL') . '.png'; imagepng($im, $tmp); imagedestroy($im);
    if ($preview) { $GLOBALS['__cpl_prev'] = $tmp; return 0; }
    try { $row = media_store_path($wid, $tmp, 'lay-' . preg_replace('/^(ai|logo|txt|lay)-/', '', (string)$m['name']), 'image/png', media_ai_folder()); } catch (Throwable $e) { @unlink($tmp); return $rawMid; }
    @unlink($tmp); return (int)$row['id'];
}

/** معاينة حيّة: يطبع النص على الصورة الخام بالإعدادات المعطاة ويرجّع data URL مصغّر — بدون حفظ. */
function cpl_text_preview(int $itemId, array $in): ?string
{
    $it = cpl_item($itemId); if (!$it) return null;
    $raw = $it['media_raw'] ?: $it['media_ids']; if (!$raw) return null;
    $p = cpl_plan($it['plan_id']); if (!$p) return null;
    $ov = [];
    foreach (['txt_pos', 'txt_size', 'txt_variant', 'txt_align', 'txt_effect'] as $k) if (isset($in[$k])) $ov[$k] = (string)$in[$k];
    if (isset($in['txt_scale'])) $ov['txt_scale'] = max(50, min(180, (int)$in['txt_scale']));
    if (isset($in['txt_y']) && is_numeric($in['txt_y'])) $ov['txt_y'] = max(2, min(92, (int)$in['txt_y']));
    if (isset($in['txt_accent']) && preg_match('/^#?[0-9a-fA-F]{6}$/', (string)$in['txt_accent']) && strtolower(ltrim((string)$in['txt_accent'], '#')) !== '10b981') $ov['txt_accent'] = '#' . ltrim((string)$in['txt_accent'], '#');
    if (isset($in['layout'])) $ov['layout'] = (string)$in['layout'];
    $head = array_key_exists('headline', $in) ? trim((string)$in['headline']) : (string)($it['headline'] ?? '');
    $mode = (string)(($in['design_mode'] ?? null) ?: cpl_opts($p)['design_mode'] ?? 'photo');
    unset($GLOBALS['__cpl_prev']);
    if ($mode === 'layout') { $ov['design_mode'] = 'layout'; cpl_render_layout($p, (int)$raw[0], $head, $ov, true); }
    else cpl_stamp_text($p, (int)$raw[0], $head, $ov, true);
    $path = $GLOBALS['__cpl_prev'] ?? ''; unset($GLOBALS['__cpl_prev']); if (!$path || !is_file($path)) return null;
    // تصغير للنقل السريع (≈560 عرض)
    $im = @imagecreatefrompng($path);
    if ($im) { $w = imagesx($im); $h = imagesy($im); $nw = 560; $nh = (int)round($h * $nw / max(1, $w)); $sc = imagescale($im, $nw, $nh); if ($sc) { $t2 = tempnam(sys_get_temp_dir(), 'cpv') . '.png'; imagepng($sc, $t2, 7); imagedestroy($sc); imagedestroy($im); @unlink($path); $path = $t2; } else imagedestroy($im); }
    $b64 = base64_encode((string)file_get_contents($path)); @unlink($path);
    return 'data:image/png;base64,' . $b64;
}

/** يركّب نسخة العرض من الصورة الخام: نطبع العنوان بنفسنا ثم اللوجو فوقه. */
function cpl_compose_media(array $p, int $rawMid, string $headline, ?array $lopts = null, array $tov = []): int
{
    $mode = (string)(($tov['design_mode'] ?? null) ?: cpl_opts($p)['design_mode'] ?? 'photo');
    if ($mode === 'ai') $mid = $rawMid; // التصميم اتولّد كامل بالذكاء — بلا تركيب نص فوقه
    elseif ($mode === 'layout') $mid = cpl_render_layout($p, $rawMid, $headline, $tov);
    else $mid = cpl_stamp_text($p, $rawMid, $headline, $tov);
    return cpl_stamp_logo($p, $mid, $lopts);
}

/** تنفيذ مهمة واحدة (أو متابعة فيديو شغال). بيرجع true لو عمل حاجة */
function cpl_jobs_tick(int $planId): bool
{
    $GLOBALS['ai_usage_ref'] = 'plan:' . $planId; // نسب تكلفة الصور/الفيديو لهذه الحملة
    $lk = 'cplj_' . $planId; if (!db()->query('SELECT GET_LOCK(' . db()->quote($lk) . ', 0)')->fetchColumn()) return false;
    try {
        @set_time_limit(330); $p = cpl_plan($planId); if (!$p) return false; $wid = (int)ws('id');
        // استرداد المهام العالقة: صورة/كاروسيل بقيت "running" أكثر من 8 دقائق (السيرفر مات وسط النداء) — نعيدها للطابور بحد محاولات، وإلا نفشلها (بدل خصم صامت بلا نتيجة)
        db()->prepare('UPDATE cp_jobs SET status = "queued" WHERE plan_id = ? AND status = "running" AND kind <> "video" AND tries < 2 AND updated_at < (NOW() - INTERVAL 8 MINUTE)')->execute([$planId]);
        db()->prepare('UPDATE cp_jobs SET status = "failed", error = "timed out" WHERE plan_id = ? AND status = "running" AND kind <> "video" AND tries >= 2 AND updated_at < (NOW() - INTERVAL 8 MINUTE)')->execute([$planId]);
        // الفيديوهات الشغالة: متابعة
        $st = db()->prepare('SELECT * FROM cp_jobs WHERE plan_id = ? AND status = "running" AND kind = "video" ORDER BY id LIMIT 3'); $st->execute([$planId]);
        foreach ($st->fetchAll() as $j) {
            try { $r = vid_or_poll((string)$j['ext_id']); } catch (Throwable $e) { $r = ['status' => 'pending']; }
            if ($r['status'] === 'completed') { $ms = cpl_media_for_names($r['names']); db()->prepare('UPDATE cp_jobs SET status = "done", result = ? WHERE id = ?')->execute([json_encode($ms), $j['id']]); cpl_jobs_finalize((int)$j['item_id'], 'video'); cpl_log($planId, t('اكتمل فيديو المنشور %s.', cpl_item((int)$j['item_id'])['seq'] ?? ''), 'ok'); }
            elseif ($r['status'] === 'failed') { db()->prepare('UPDATE cp_jobs SET status = "failed", error = ? WHERE id = ?')->execute([mb_substr((string)($r['error'] ?? ''), 0, 300), $j['id']]); cpl_log($planId, t('فشل فيديو المنشور %s: %s', cpl_item((int)$j['item_id'])['seq'] ?? '', $r['error'] ?? ''), 'err'); }
        }
        $st = db()->prepare('SELECT * FROM cp_jobs WHERE plan_id = ? AND status = "queued" ORDER BY id LIMIT 1'); $st->execute([$planId]); $j = $st->fetch();
        if (!$j) return false;
        $it = cpl_item((int)$j['item_id']); if (!$it) { db()->prepare('UPDATE cp_jobs SET status = "failed", error = "item" WHERE id = ?')->execute([$j['id']]); return true; }
        db()->prepare('UPDATE cp_jobs SET status = "running", tries = tries + 1 WHERE id = ?')->execute([$j['id']]);
        $label = ['image' => t('صورة'), 'video' => t('فيديو'), 'carousel' => t('شريحة كاروسيل %s', (int)$j['slide'] + 1)][$j['kind']];
        cpl_log($planId, t('توليد %s للمنشور %s…', $label, $it['seq'])); $t0 = microtime(true);
        try {
            $prompt = cpl_job_prompt($p, $it, $j);
            if ($j['kind'] === 'video') {
                $vid = vid_or_start($prompt, '9:16'); db()->prepare('UPDATE cp_jobs SET ext_id = ? WHERE id = ?')->execute([$vid, $j['id']]);
                cpl_log($planId, t('بدأ توليد الفيديو للمنشور %s، وسنتابعه حتى يكتمل (قد يستغرق دقائق).', $it['seq']));
            } else {
                $GLOBALS['img_no_trim'] = true;
                [$refFiles, $refHint] = cpl_ref_images($p, $wid, 4, cpl_item_ref_override($it)); $prompt .= $refHint;
                try { $names = img_generate($prompt, cpl_aspect($p), 1, $refFiles, cpl_img_quality($p)); } finally { foreach ($refFiles as $tf) @unlink($tf); } $ms = cpl_media_for_names($names); if (!$ms) throw new RuntimeException(t('لم تُحفظ الصورة الناتجة.'));
                db()->prepare('UPDATE cp_jobs SET status = "done", result = ? WHERE id = ?')->execute([json_encode($ms), $j['id']]); // الخام؛ الختم في finalize
                cpl_log($planId, t('اكتملت %s للمنشور %s في %s ثانية.', $label, $it['seq'], round(microtime(true) - $t0)), 'ok');
                cpl_jobs_finalize($it['id'], $j['kind']);
            }
        } catch (Throwable $e) {
            $retry = (int)$j['tries'] + 1 < 2; // محاولتين لكل مهمة
            db()->prepare('UPDATE cp_jobs SET status = ?, error = ? WHERE id = ?')->execute([$retry ? 'queued' : 'failed', mb_substr($e->getMessage(), 0, 300), $j['id']]);
            cpl_log($planId, t('فشل توليد %s للمنشور %s: %s', $label, $it['seq'], $e->getMessage()) . ($retry ? ' ' . t('(سنعيد المحاولة)') : ''), 'err');
            if (!$retry) cpl_jobs_finalize($it['id'], $j['kind']);
        }
        return true;
    } finally { db()->query('SELECT RELEASE_LOCK(' . db()->quote($lk) . ')'); }
}

/**
 * نسخة متوازية: بتولّد صور عدة مهام في نفس الوقت (curl_multi) بدل واحدة واحدة — تسريع التوليد الكبير.
 * بترجّع عدد الصور اللي خلصت. الفيديو والكاروسيل-بدون-صور بيرجعوا لـ cpl_jobs_tick.
 */
function cpl_jobs_burst(int $planId, int $concurrency = 5): int
{
    $GLOBALS['ai_usage_ref'] = 'plan:' . $planId;
    if (img_provider() !== 'openai' || !function_exists('img_openai_batch')) return cpl_jobs_tick($planId) ? 1 : 0;
    $lk = 'cplj_' . $planId; if (!db()->query('SELECT GET_LOCK(' . db()->quote($lk) . ', 0)')->fetchColumn()) return 0;
    try {
        @set_time_limit(330); $p = cpl_plan($planId); if (!$p) return 0; $wid = (int)ws('id');
        // متابعة الفيديوهات الشغالة
        $st = db()->prepare('SELECT * FROM cp_jobs WHERE plan_id = ? AND status = "running" AND kind = "video" ORDER BY id LIMIT 4'); $st->execute([$planId]);
        foreach ($st->fetchAll() as $j) {
            try { $r = vid_or_poll((string)$j['ext_id']); } catch (Throwable $e) { $r = ['status' => 'pending']; }
            if ($r['status'] === 'completed') { $ms = cpl_media_for_names($r['names']); db()->prepare('UPDATE cp_jobs SET status = "done", result = ? WHERE id = ?')->execute([json_encode($ms), $j['id']]); cpl_jobs_finalize((int)$j['item_id'], 'video'); cpl_log($planId, t('اكتمل فيديو المنشور %s.', cpl_item((int)$j['item_id'])['seq'] ?? ''), 'ok'); }
            elseif ($r['status'] === 'failed') { db()->prepare('UPDATE cp_jobs SET status = "failed", error = ? WHERE id = ?')->execute([mb_substr((string)($r['error'] ?? ''), 0, 300), $j['id']]); cpl_log($planId, t('فشل فيديو المنشور %s.', cpl_item((int)$j['item_id'])['seq'] ?? ''), 'err'); }
        }
        // مهام الصور/الكاروسيل المنتظرة: دفعة واحدة متوازية
        $st = db()->prepare('SELECT * FROM cp_jobs WHERE plan_id = ? AND status = "queued" AND kind IN ("image","carousel") ORDER BY id LIMIT ' . max(1, min(8, (int)$concurrency))); $st->execute([$planId]);
        $jobs = $st->fetchAll();
        if (!$jobs) return cpl_jobs_tick($planId) ? 1 : 0; // مفيش صور: خلي cpl_jobs_tick يبدأ فيديو منتظر أو يخلص
        // ملفات الريفرانس المشتركة للخطة (تُحسب مرة واحدة وتُعاد استخدامها للمنشورات بلا مراجع خاصة)
        [$refFiles, $refHint] = cpl_ref_images($p, $wid, 4);
        $extraFiles = []; // ملفات مؤقتة إضافية لمنشورات لها مراجع خاصة (تُحذف في النهاية)
        $batch = []; $meta = [];
        foreach ($jobs as $j) {
            $it = cpl_item((int)$j['item_id']); if (!$it) { db()->prepare('UPDATE cp_jobs SET status = "failed", error = "item" WHERE id = ?')->execute([$j['id']]); continue; }
            db()->prepare('UPDATE cp_jobs SET status = "running", tries = tries + 1 WHERE id = ?')->execute([$j['id']]);
            $GLOBALS['img_no_trim'] = true;
            $ov = cpl_item_ref_override($it);
            if ($ov !== null) { [$iFiles, $iHint] = cpl_ref_images($p, $wid, 4, $ov); $extraFiles = array_merge($extraFiles, $iFiles); $jRefs = $iFiles; $jHint = $iHint; }
            else { $jRefs = $refFiles; $jHint = $refHint; }
            $batch[] = ['prompt' => cpl_job_prompt($p, $it, $j) . $jHint, 'refs' => $jRefs];
            $meta[] = ['job' => $j, 'it' => $it];
            cpl_log($planId, t('توليد صورة للمنشور %s…', $it['seq']));
        }
        $fail_all = function (string $msg) use ($meta) { foreach ($meta as $mm) { $retry = (int)$mm['job']['tries'] + 1 < 2; db()->prepare('UPDATE cp_jobs SET status = ?, error = ? WHERE id = ?')->execute([$retry ? 'queued' : 'failed', mb_substr($msg, 0, 300), $mm['job']['id']]); if (!$retry) cpl_jobs_finalize((int)$mm['it']['id'], $mm['job']['kind']); } };
        try { $res = img_openai_batch($batch, cpl_aspect($p), cpl_img_quality($p)); }
        catch (Throwable $e) { $fail_all($e->getMessage()); foreach (array_merge($refFiles, $extraFiles) as $tf) @unlink($tf); return 0; }
        $n = 0;
        foreach ($meta as $k => $mm) {
            $j = $mm['job']; $it = $mm['it']; $r = $res[$k] ?? ['error' => 'no result'];
            if (!empty($r['names']) && ($ms = cpl_media_for_names($r['names']))) {
                db()->prepare('UPDATE cp_jobs SET status = "done", result = ? WHERE id = ?')->execute([json_encode($ms), $j['id']]);
                cpl_jobs_finalize((int)$it['id'], $j['kind']); cpl_log($planId, t('اكتملت صورة المنشور %s.', $it['seq']), 'ok'); $n++;
            } else {
                $retry = (int)$j['tries'] + 1 < 2; db()->prepare('UPDATE cp_jobs SET status = ?, error = ? WHERE id = ?')->execute([$retry ? 'queued' : 'failed', mb_substr((string)($r['error'] ?? 'fail'), 0, 300), $j['id']]);
                cpl_log($planId, t('فشل توليد صورة المنشور %s: %s', $it['seq'], (string)($r['error'] ?? '')) . ($retry ? ' ' . t('(سنعيد المحاولة)') : ''), 'err');
                if (!$retry) cpl_jobs_finalize((int)$it['id'], $j['kind']);
            }
        }
        foreach (array_merge($refFiles, $extraFiles) as $tf) @unlink($tf);
        return $n;
    } finally { db()->query('SELECT RELEASE_LOCK(' . db()->quote($lk) . ')'); }
}

function cpl_jobs_status(int $planId): array
{
    $by = []; $pending = 0;
    foreach (cpl_jobs($planId) as $j) { $k = (int)$j['item_id']; $by[$k][$j['kind']][(string)$j['slide']] = ['s' => $j['status'], 'e' => (string)$j['error']]; if (in_array($j['status'], ['queued', 'running'], true)) $pending++; }
    $out = []; foreach ($by as $item => $kinds) foreach ($kinds as $kind => $sl) { $c = array_count_values(array_column($sl, 's')); $out[$item][$kind] = ['done' => $c['done'] ?? 0, 'total' => count($sl), 'failed' => $c['failed'] ?? 0, 'busy' => ($c['queued'] ?? 0) + ($c['running'] ?? 0), 'err' => implode(' | ', array_filter(array_unique(array_column($sl, 'e'))))]; }
    return ['pending' => $pending, 'items' => $out];
}

/** قيم مبدئية من قاعدة المعرفة (للتعبئة فقط؛ التعديل في المعالج يخص الحملة ولا يغيّر قاعدة المعرفة) */
function cpl_kb_defaults(int $wid): array
{
    $k = function_exists('kb_get') ? kb_get($wid) : [];
    $co = $k['company'] ?? []; $aud = $k['audience'] ?? []; $tone = $k['tone'] ?? [];
    $toneTxt = '';
    if (function_exists('kb_tone_options') && !empty($tone['style'])) $toneTxt = implode('، ', array_intersect_key(kb_tone_options(), array_flip((array)$tone['style'])));
    if (trim((string)($tone['notes'] ?? '')) !== '') $toneTxt = trim($toneTxt . ($toneTxt ? ' — ' : '') . $tone['notes']);
    $dialMap = ['egyptian' => 'egyptian', 'gulf' => 'gulf', 'saudi' => 'saudi', 'levantine' => 'levantine', 'msa' => 'msa', 'match' => 'white'];
    $svc = array_values(array_filter(array_map(function ($r) { return trim((string)($r['name'] ?? '')); }, (array)($k['services']['rows'] ?? []))));
    return [
        'company' => trim((string)($co['name'] ?? '')),
        'about' => trim((string)($co['about'] ?? $co['tagline'] ?? '')),
        'audience' => trim((string)($aud['who'] ?? $aud['text'] ?? '')),
        'services' => $svc,
        'tone' => $toneTxt,
        'dialect_k' => $dialMap[$tone['dialect'] ?? ''] ?? '',
        'cta' => trim((string)($co['cta'] ?? '')),
        'has_logo' => (function_exists('kb_logo') && kb_logo($wid)) ? 1 : 0,
    ];
}

/* ===================== ملخص الشركة التلقائي (مرجع مضغوط للذكاء الاصطناعي) ===================== */
/* ملف مثبت في ملفات الشركة، غير قابل للتعديل من المستخدم، يتجدد تلقائيًا مع أي تغيير في قاعدة المعرفة.
   الهدف: يفهم الـ AI الشركة بسرعة دون قراءة كل نصوص قاعدة المعرفة الضخمة في كل طلب (توفير تكلفة). */

function cpl_brief_hash(int $wid): string
{
    $parts = [];
    foreach (db()->query('SELECT section, data FROM kb_sections WHERE workspace_id = ' . (int)$wid . ' ORDER BY section')->fetchAll() as $r) $parts[] = $r['section'] . '=' . $r['data'];
    foreach (db()->query("SELECT name, size, method, chars FROM kb_files WHERE workspace_id = " . (int)$wid . " AND method <> 'auto_brief' ORDER BY id")->fetchAll() as $r) $parts[] = implode('|', $r);
    return md5(implode("\n", $parts));
}

/** يرجع ملخص الشركة (مبني من الكاش لو الهاش ما اتغيرش). $force يعيد البناء. */
function cpl_company_brief(int $wid, bool $force = false): string
{
    $hash = cpl_brief_hash($wid); $cur = (string)setting('cpl_cbrief_' . $wid, '');
    if (!$force && $cur !== '' && setting('cpl_cbrief_hash_' . $wid, '') === $hash) return $cur;
    // السياق الخام: الأقسام المنظّمة (مضغوطة أصلًا) + عيّنة من ملفات النصوص الكبيرة
    $structured = function_exists('kb_compile') ? kb_compile($wid) : '';
    $files = '';
    foreach (db()->query("SELECT name, content, chunked FROM kb_files WHERE workspace_id = " . (int)$wid . " AND method <> 'auto_brief' AND enabled = 1 ORDER BY id")->fetchAll() as $f) {
        $txt = (string)$f['content'];
        if ($txt === '' && (int)$f['chunked']) { $c = db()->prepare('SELECT body FROM kb_chunks WHERE file_id = (SELECT id FROM kb_files WHERE workspace_id = ? AND name = ? LIMIT 1) ORDER BY id LIMIT 3'); $c->execute([$wid, $f['name']]); $txt = implode("\n", $c->fetchAll(PDO::FETCH_COLUMN)); }
        if (trim($txt) !== '') $files .= "\n\n### " . $f['name'] . "\n" . mb_substr(trim($txt), 0, 4000);
    }
    $raw = trim($structured . ($files !== '' ? "\n\n## UPLOADED FILES (samples)\n" . mb_substr($files, 0, 12000) : ''));
    $brief = '';
    if ($raw !== '') {
        try {
            $sys = "COMPANY_BRIEF. You are briefing another AI that will create marketing content for this company. From the knowledge base below, write a tight COMPANY BRIEF (250-450 words) that lets any AI think like this company without reading everything. Cover: what the company is and its one-line positioning; the main products/services with their real prices; the target audience and their motivation; the key differentiators and proof; the brand voice, language and dialect; and 3-6 hard do's and don'ts (never invent facts/prices/claims). Use the company's own language. Plain text with short labelled lines. Never add anything not in the knowledge base.";
            $GLOBALS['__kb_skip'] = true;
            $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => mb_substr($raw, 0, 24000)]], 900, 'analyze');
            unset($GLOBALS['__kb_skip']);
            $brief = trim((string)($r['text'] ?? ''));
        } catch (Throwable $e) { unset($GLOBALS['__kb_skip']); }
    }
    if ($brief === '') $brief = mb_substr($raw !== '' ? $raw : (string)(kb_get($wid)['company']['about'] ?? ''), 0, 3000); // fallback بدون AI
    save_setting('cpl_cbrief_' . $wid, $brief); save_setting('cpl_cbrief_hash_' . $wid, $hash);
    cpl_brief_pin($wid, $brief);
    return $brief;
}

/** يثبّت/يحدّث الملخص كملف للقراءة فقط في ملفات الشركة */
function cpl_brief_pin(int $wid, string $brief): void
{
    $name = t('ملخص الشركة (تلقائي)');
    $st = db()->prepare("SELECT id FROM kb_files WHERE workspace_id = ? AND method = 'auto_brief' LIMIT 1"); $st->execute([$wid]); $id = (int)$st->fetchColumn();
    if ($id) db()->prepare("UPDATE kb_files SET name = ?, content = ?, size = ?, chars = ?, read_state = 'done', enabled = 1 WHERE id = ?")->execute([$name, $brief, strlen($brief), mb_strlen($brief), $id]);
    else db()->prepare("INSERT INTO kb_files (workspace_id, name, mime, size, method, content, enabled, read_state, chars) VALUES (?, ?, 'text/plain', ?, 'auto_brief', ?, 1, 'done', ?)")->execute([$wid, $name, strlen($brief), $brief, mb_strlen($brief)]);
}

/** يعيد البناء لو قاعدة المعرفة اتغيرت (يُستدعى من صفحة قاعدة المعرفة) — نداء AI واحد عند التغيير فقط */
function cpl_brief_refresh_if_stale(int $wid): void
{
    if (setting('cpl_cbrief_hash_' . $wid, '') !== cpl_brief_hash($wid)) { try { cpl_company_brief($wid, true); } catch (Throwable $e) {} }
}
