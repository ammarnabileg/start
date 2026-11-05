<?php
/** المستخدم مابيكتبش البروفايل: بنشيل عمود profiles لو موجود ونحط البروفايل الحالي في كل سطر */
function bulk_with_profile(string $path, string $profileId): string
{
    $in = fopen($path, 'r');
    $bom = fread($in, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($in);
    $head = fgetcsv($in, 0, ',', '"', '');
    if (!$head) { fclose($in); return $path; }
    $head = array_map(function ($h) { return trim((string)$h); }, $head);
    $drop = array_search('profiles', array_map('strtolower', $head), true);
    $outPath = tempnam(sys_get_temp_dir(), 'bulk');
    $out = fopen($outPath, 'w');
    $h2 = $head; if ($drop !== false) unset($h2[$drop]);
    fputcsv($out, array_merge(array_values($h2), ['profiles']), ',', '"', '');
    while (($r = fgetcsv($in, 0, ',', '"', '')) !== false) {
        if (!array_filter($r, function ($x) { return trim((string)$x) !== ''; })) continue;
        $r = array_pad($r, count($head), '');
        if ($drop !== false) unset($r[$drop]);
        fputcsv($out, array_merge(array_values($r), [$profileId]), ',', '"', '');
    }
    fclose($in); fclose($out);
    register_shutdown_function(function () use ($outPath) { if (is_file($outPath)) unlink($outPath); });
    return $outPath;
}

$err = null;
$z = zernio_or_null($err);
$fxd = getenv('UI_FIXTURES');
if ($fxd && is_file($fxd . '/bulk_fake_z.php')) $z = require $fxd . '/bulk_fake_z.php'; // اختبارات بس: مفيش رفع حقيقي

/** أكواد أخطاء Zernio → كلام واضح + إزاي تصلحها */
$bkErr = function (string $raw): array {
    [$code, $arg] = array_pad(explode(':', trim($raw), 2), 2, '');
    $m = [
        'unknown_profile' => [t('البروفايل مش موجود في حساب Zernio.'), t('افتح التكاملات واتأكد إن مفتاح Zernio والبروفايل صح.')],
        'no_account_for_platform' => [t('مفيش حساب %s مربوط.', $arg), t('اربط الحساب من "الحسابات المربوطة"، أو شيل %s من عمود platforms.', $arg)],
        'rows_exceed_advisory_limit' => [t('الملف فيه أكتر من %s سطر.', $arg), t('Zernio بينصح بـ %s سطر أو أقل في المرة. قسّم الملف لملفين أو أكتر.', $arg)],
        'unknown_platform' => [t('منصة مش معروفة: %s', $arg), t('استخدم أسماء المنصات بالإنجليزي زي facebook أو instagram.')],
        'invalid_platform' => [t('منصة مش معروفة: %s', $arg), t('استخدم أسماء المنصات بالإنجليزي زي facebook أو instagram.')],
        'missing_content' => [t('نص المنشور فاضي.'), t('اكتب النص في عمود post_content.')],
        'missing_post_content' => [t('نص المنشور فاضي.'), t('اكتب النص في عمود post_content.')],
        'invalid_schedule_time' => [t('الميعاد مكتوب غلط: %s', $arg), t('اكتبه بالشكل 2026-12-01 10:00.')],
        'schedule_time_in_past' => [t('الميعاد ده عدّى.'), t('حط ميعاد في المستقبل، أو اكتب true في use_queue.')],
        'missing_schedule' => [t('مفيش ميعاد للمنشور.'), t('اكتب schedule_time، أو true في use_queue أو publish_now أو is_draft.')],
        'invalid_timezone' => [t('المنطقة الزمنية غلط: %s', $arg), t('اكتبها زي %s، أو سيبها فاضية.', site_timezone())],
        'invalid_media_url' => [t('رابط الصورة أو الفيديو مش صالح: %s', $arg), t('لازم يكون رابط عام بيبدأ بـ https://')],
        'content_too_long' => [t('النص أطول من المسموح للمنصة: %s', $arg), t('قصّر النص، أو اكتب نسخة أقصر في custom_content_%s.', $arg ?: '<platform>')],
    ];
    return $m[$code] ?? [$raw, ''];
};

/* كتابة منشورات بالذكاء الاصطناعي — بترجع نصوص بس، والمستخدم بيراجعها في الجدول قبل أي رفع */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === 'gen') {
    header('Content-Type: application/json; charset=utf-8');
    if (!can('ai.use')) { echo json_encode(['ok' => false, 'error' => t('مالكش صلاحية استخدام الذكاء الاصطناعي.')], JSON_UNESCAPED_UNICODE); exit; }
    $topic = trim(mb_substr((string)($_POST['topic'] ?? ''), 0, 1500));
    $n = max(1, min(30, (int)($_POST['count'] ?? 10)));
    $tones = ['normal' => 'ودود وطبيعي', 'formal' => 'رسمي ومحترف', 'fun' => 'خفيف ومرح', 'sales' => 'بيعي ومقنع مع دعوة واضحة لاتخاذ إجراء'];
    $langs = ['eg' => 'العامية المصرية', 'msa' => 'العربية الفصحى المبسطة', 'gulf' => 'اللهجة الخليجية', 'en' => 'English'];
    $tone = $tones[$_POST['tone'] ?? ''] ?? $tones['normal'];
    $lng = $langs[$_POST['lang'] ?? ''] ?? $langs['eg'];
    if ($topic === '') { echo json_encode(['ok' => false, 'error' => t('اكتب الموضوع أو الفكرة الأول.')], JSON_UNESCAPED_UNICODE); exit; }
    $sys = 'BULK_POSTS_JSON. أنت كاتب محتوى سوشيال ميديا محترف. اكتب ' . $n . ' منشورات مختلفة عن الموضوع، كل واحد بزاوية مختلفة (معلومة، سؤال للجمهور، عرض، قصة قصيرة، نصيحة...)، بدون تكرار الافتتاحية. '
        . 'اللغة: ' . $lng . '. الأسلوب: ' . $tone . '. ' . (!empty($_POST['emoji']) ? 'استخدم إيموجي باعتدال. ' : 'بدون إيموجي. ') . (!empty($_POST['tags']) ? 'اختم كل منشور بـ 2 إلى 4 هاشتاجات مناسبة. ' : 'بدون هاشتاجات. ')
        . 'كل منشور أقل من 600 حرف. أعد JSON فقط بالشكل {"posts":[{"text":"..."}]} بدون أي شرح.';
    try {
        $r = ai_chat([['role' => 'system', 'content' => $sys . ai_country_note()], ['role' => 'user', 'content' => $topic]], min(3800, 200 + $n * 160), 'compose');
        $txt = (string)$r['text'];
        if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
        $j = json_decode($txt, true);
        $posts = [];
        foreach ((array)($j['posts'] ?? []) as $p) { $t = trim((string)(is_array($p) ? ($p['text'] ?? '') : $p)); if ($t !== '') $posts[] = mb_substr($t, 0, 3000); }
        if (!$posts) throw new RuntimeException(t('الرد مكانش بالشكل المتوقع، جرّب تاني.'));
        log_activity('ai.bulk_posts', count($posts) . ' ' . mb_substr($topic, 0, 60));
        echo json_encode(['ok' => true, 'posts' => array_slice($posts, 0, $n)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* نص لكل صورة/سطر بالذكاء الاصطناعي (دفعات لحد 20) — بيرجع النصوص بس */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === 'caption') {
    header('Content-Type: application/json; charset=utf-8');
    if (!can('ai.use')) { echo json_encode(['ok' => false, 'error' => t('مالكش صلاحية استخدام الذكاء الاصطناعي.')], JSON_UNESCAPED_UNICODE); exit; }
    $vision = !empty($_POST['vision']);
    $items = array_slice((array)json_decode((string)($_POST['items'] ?? '[]'), true), 0, $vision ? 5 : 20);
    $wid = (int)ws('id');
    $brief = trim(mb_substr((string)($_POST['brief'] ?? ''), 0, 800));
    $langs = ['eg' => 'العامية المصرية', 'msa' => 'العربية الفصحى المبسطة', 'gulf' => 'اللهجة الخليجية', 'en' => 'English'];
    $lng = $langs[$_POST['lang'] ?? ''] ?? $langs['eg'];
    $lines = [];
    foreach ($items as $k => $it) $lines[] = ($k + 1) . '. ' . mb_substr(trim((string)($it['hint'] ?? '')), 0, 300);
    if (!$lines) { echo json_encode(['ok' => false, 'error' => t('مفيش سطور')], JSON_UNESCAPED_UNICODE); exit; }
    // الصور نفسها (لما المستخدم يختار إن الذكاء الاصطناعي يشوفها): من المكتبة كملف مصغر، أو رابط عام
    $content = [];
    $libIds = [];
    if ($vision) {
        foreach ($items as $k => $it) {
            $part = null;
            $lid = (int)($it['lib'] ?? 0);
            if ($lid && ($mr = media_get($wid, $lid)) && media_kind((string)$mr['mime']) === 'image') {
                $pth = media_thumb_path($wid, $mr);
                if (is_file($pth) && filesize($pth) <= 4 * 1048576) {
                    $mime = strpos($pth, '/thumbs/') !== false ? 'image/jpeg' : (string)$mr['mime'];
                    $part = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($pth))]];
                    $libIds[$k] = $lid;
                }
            } elseif (preg_match('#^https://\S+\.(jpe?g|png|webp|gif)(\?\S*)?$#i', (string)($it['url'] ?? ''))) {
                $part = ['type' => 'image_url', 'image_url' => ['url' => (string)$it['url']]];
            }
            if ($part) { $content[] = ['type' => 'text', 'text' => 'صورة العنصر رقم ' . ($k + 1) . ':']; $content[] = $part; }
        }
    }
    $sys = 'BULK_CAPTIONS_JSON. أنت كاتب محتوى سوشيال ميديا. لكل عنصر مرقم في الرسالة (وصف صورة أو اسم ملف أو فكرة) اكتب منشورًا واحدًا مناسبًا له، مختلفًا عن الباقي في البداية والزاوية. '
        . ($content ? 'مرفق صور بعض العناصر: اكتب عن اللي باين في الصورة فعلًا (المنتج، الألوان، المكان، الناس)، ومتألفش أسماء أو أسعار أو عروض مش مكتوبة. ورجّع كمان "desc" جملة قصيرة بالعربي بتوصف الصورة. ' : '')
        . 'اللغة: ' . $lng . '. استخدم إيموجي باعتدال واختم بـ 2 أو 3 هاشتاجات. كل منشور أقل من 400 حرف. '
        . ($brief !== '' ? 'معلومات عامة عن الحملة: ' . $brief . '. ' : '')
        . 'أعد JSON فقط بالشكل {"posts":[{"n":1,"text":"..."' . ($content ? ',"desc":"..."' : '') . '}]} بنفس عدد العناصر وبنفس الترقيم.';
    $userMsg = implode("\n", $lines);
    if ($content) $userMsg = array_merge([['type' => 'text', 'text' => $userMsg]], $content);
    try {
        $r = ai_chat([['role' => 'system', 'content' => $sys . ai_country_note()], ['role' => 'user', 'content' => $userMsg]], min(3800, 200 + count($lines) * ($content ? 220 : 140)), 'compose');
        $txt = (string)$r['text'];
        if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
        $j = json_decode($txt, true);
        $out = [];
        foreach ((array)($j['posts'] ?? []) as $k => $pp) {
            $n = (int)($pp['n'] ?? ($k + 1)) - 1;
            $t = trim((string)($pp['text'] ?? ''));
            if ($t !== '' && isset($items[$n])) $out[] = ['i' => $items[$n]['i'] ?? $n, 'text' => mb_substr($t, 0, 3000)];
            // الوصف بيتحفظ في المكتبة (لو مفيش وصف قبل كده) عشان المرة الجاية يبقى ببلاش
            $dsc = trim((string)($pp['desc'] ?? ''));
            if ($dsc !== '' && isset($libIds[$n])) db()->prepare('UPDATE media_files SET ai_desc = ?, ai_at = COALESCE(ai_at, NOW()) WHERE workspace_id = ? AND id = ? AND (ai_desc IS NULL OR ai_desc = \'\')')->execute([mb_substr($dsc, 0, 500), $wid, $libIds[$n]]);
        }
        if (!$out) throw new RuntimeException(t('الرد مكانش بالشكل المتوقع، جرّب تاني.'));
        if ($content) log_activity('ai.bulk_vision', count($libIds) . ' images');
        echo json_encode(['ok' => true, 'posts' => $out, 'saw' => count(array_filter($content, function ($c) { return $c['type'] === 'image_url'; }))], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* الرفع على دفعات من الصفحة (كل دفعة 500 سطر أو أقل) — بيرجع JSON */
if ($z && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $dry = ($_POST['mode'] ?? 'check') !== 'create';
    $csv = (string)($_POST['csv_text'] ?? '');
    try {
        if (trim($csv) === '') throw new ZernioException(t('اختار ملف أو اكتب سطر واحد على الأقل.'));
        if (strlen($csv) > 5 * 1024 * 1024) throw new ZernioException(t('الملف أكبر من 5 ميجا.'));
        // صور مكتبة الوسائط (lib:ID) → رابط عام. بتترفع لـ Zernio أول مرة بس وبيتحفظ رابطها
        $wid = (int)ws('id');
        $libMap = [];
        $csv = preg_replace_callback('/\blib:(\d+)\b/', function ($m) use ($z, $wid, &$libMap) {
            $id = (int)$m[1];
            if (!isset($libMap[$id])) {
                try { $libMap[$id] = media_public_url($z, $wid, $id, 'bulk'); }
                catch (Throwable $t) { $libMap[$id] = 'missing-media-' . $id; }
            }
            return $libMap[$id];
        }, $csv);
        $tmp = tempnam(sys_get_temp_dir(), 'bkx');
        file_put_contents($tmp, $csv);
        register_shutdown_function(function () use ($tmp) { if (is_file($tmp)) unlink($tmp); });
        $r = pub_bulk_upload($z, bulk_with_profile($tmp, current_profile_id($z)), $dry);
        $codes = [];
        foreach ((array)($r['warnings'] ?? []) as $w) $codes[(string)$w] = 1;
        foreach ((array)($r['results'] ?? []) as $row) foreach ((array)($row['errors'] ?? []) as $er) $codes[(string)$er] = 1;
        $explain = [];
        foreach (array_keys($codes) as $c) $explain[$c] = $bkErr($c);
        if (!$dry) log_activity('post.bulk', ($r['valid'] ?? 0) . '/' . ($r['total'] ?? 0) . ' ' . mb_substr((string)($_POST['fname'] ?? ''), 0, 80));
        echo json_encode(['ok' => true, 'data' => $r, 'explain' => $explain], JSON_UNESCAPED_UNICODE);
    } catch (ZernioException $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($z && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_FILES['csv'] ?? null;
    $dry = ($_POST['mode'] ?? 'check') !== 'create';
    try {
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            throw new ZernioException(t('اختار ملف CSV الأول.'));
        }
        if (!preg_match('/\.csv$/i', (string)$f['name'])) {
            throw new ZernioException(t('الملف لازم يكون بامتداد .csv'));
        }
        if ($f['size'] > 5 * 1024 * 1024) {
            throw new ZernioException(t('الملف أكبر من 5 ميجا.'));
        }
        $r = pub_bulk_upload($z, bulk_with_profile($f['tmp_name'], current_profile_id($z)), $dry);
        $_SESSION['bulk_result'] = ['dry' => $dry, 'data' => $r, 'file' => (string)$f['name']];
        if (!$dry) {
            log_activity('post.bulk', ($r['valid'] ?? 0) . '/' . ($r['total'] ?? 0));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('bulk'));
}

$result = $_SESSION['bulk_result'] ?? null;
unset($_SESSION['bulk_result']);

/* البروفايلات والمنصات المربوطة فعلاً (قراءة بس، متخزنة 10 دقايق) — عشان ملف المثال والفحص يبقوا بقيمك الحقيقية */
$profs = [];
$fxd = getenv('UI_FIXTURES');
if ($fxd && is_file($fxd . '/bulk.json')) {
    $profs = (array)(json_decode((string)file_get_contents($fxd . '/bulk.json'), true)['profiles'] ?? []);
} elseif ($z) {
    $ck = 'bulk_profs_' . (int)ws('id');
    if (isset($_SESSION[$ck]) && $_SESSION[$ck]['t'] > time() - 600) {
        $profs = $_SESSION[$ck]['d'];
    } else {
        try {
            foreach ($z->profiles() as $p) {
                $pid = (string)($p['_id'] ?? $p['id'] ?? '');
                if ($pid === '') continue;
                $pl = [];
                foreach ($z->accounts($pid) as $a) if (!empty($a['platform']) && ($a['enabled'] ?? true) !== false) $pl[strtolower((string)$a['platform'])] = 1;
                $profs[] = ['id' => $pid, 'name' => (string)($p['name'] ?? $pid), 'platforms' => array_keys($pl)];
            }
            $_SESSION[$ck] = ['t' => time(), 'd' => $profs];
        } catch (ZernioException $ex) { /* الفحص هيبقى من غير البروفايلات */ }
    }
}

if (isset($_GET['sample'])) {
    $pr = null;
    foreach ($profs as $p) if ($p['platforms']) { $pr = $p; break; }
    $pr = $pr ?: ($profs[0] ?? ['name' => 'MyProfile', 'platforms' => ['facebook']]);
    $pls = $pr['platforms'] ?: ['facebook'];
    $tz = site_timezone();
    $d = new DateTime('tomorrow 10:00', new DateTimeZone($tz));
    $at = function (int $days, string $hm) use ($d) { $x = clone $d; $x->modify('+' . $days . ' day'); [$h, $m] = explode(':', $hm); $x->setTime((int)$h, (int)$m); return $x->format('Y-m-d H:i'); };
    $two = implode('|', array_slice($pls, 0, 2));
    $first = $pls[0];
    $ar = lang() === 'ar';
    $rows = [
        ['post_content', 'platforms', 'schedule_time', 'tz', 'media_urls', 'use_queue', 'custom_content_' . $first],
        [$ar ? 'صباح الخير! المنيو الجديد نزل النهارده 🎉' : 'Good morning! Our new menu is out today 🎉', $first, $at(0, '10:00'), $tz, '', '', ''],
        [$ar ? 'عرض نهاية الأسبوع: خصم 20% على كل حاجة' : 'Weekend offer: 20% off everything', $two, $at(3, '18:30'), $tz, 'https://example.com/offer.jpg', '', $ar ? 'نسخة خاصة بـ ' . $first . ': خصم 20%!' : 'Special ' . $first . ' version: 20% off!'],
        [$ar ? 'منشور هيتحط في أقرب ميعاد فاضي في الطابور' : 'This post goes into the next free queue slot', $first, '', $tz, '', 'true', ''],
    ];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bulk-template.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    foreach ($rows as $r) fputcsv($o, $r);
    exit;
}


$best = [];
if ($fxd && is_file($fxd . '/bulk.json')) {
    $best = (array)(json_decode((string)file_get_contents($fxd . '/bulk.json'), true)['best'] ?? []);
} elseif ($z && function_exists('best_slots_local') && module_enabled('analytics')) {
    $ck = 'bulk_best_' . (int)ws('id');
    if (isset($_SESSION[$ck]) && $_SESSION[$ck]['t'] > time() - 3600) $best = $_SESSION[$ck]['d'];
    else { $best = best_slots_local($z, 7); $_SESSION[$ck] = ['t' => time(), 'd' => $best]; }
}

render(t('رفع منشورات بالجملة'), 'bulk', function () use ($z, $result, $profs, $bkErr, $best) {
    if (!$z && !getenv('UI_FIXTURES')) { no_api_box(); return; }
    $cols = [
        ['post_content', t('نص المنشور.'), true],
        ['platforms', t('المنصات، مفصولة بـ | لو أكتر من واحدة (مثلاً facebook|instagram).'), true],
        ['schedule_time', t('ميعاد النشر بالشكل 2026-12-01 10:00.'), true],
        ['tz', t('المنطقة الزمنية (مثلاً %s). لو فاضي بياخد منطقة الـ Profile.', cx('tz')), false],
        ['media_urls', t('روابط صور أو فيديو عامة، مفصولة بـ |.'), false],
        ['title', t('عنوان (يوتيوب وبنترست مثلاً).'), false],
        ['use_queue', t('اكتب true عشان يتحط في أقرب ميعاد في الطابور بدل schedule_time.'), false],
        ['publish_now', t('اكتب true للنشر فوراً.'), false],
        ['is_draft', t('اكتب true للحفظ كمسودة.'), false],
        ['custom_content_<platform>', t('نص خاص بمنصة معينة، مثلاً custom_content_facebook.'), false],
        ['schedule_time_<platform>', t('ميعاد مختلف لمنصة معينة.'), false],
    ];
    $allPl = [];
    foreach ($profs as $p) foreach ($p['platforms'] as $pl) $allPl[$pl] = 1;
    $allPl = array_keys($allPl);
    $icons = platform_icons();
    $plIcon = function (string $pl, string $cls = 'w-3.5 h-3.5') use ($icons) {
        if (!isset($icons[$pl])) return '';
        return '<svg viewBox="0 0 24 24" class="' . $cls . '" fill="' . e($icons[$pl][0]) . '" aria-hidden="true"><path d="' . e($icons[$pl][1]) . '"/></svg>';
    };
    $steps = [t('جهّز المنشورات'), t('راجع وعدّل'), t('افحص وارفع')];
    ?>
    <ol id="bk-steps" class="flex items-center gap-2 mb-5 text-sm" aria-label="<?= e(t('الخطوات')) ?>">
      <?php foreach ($steps as $i => $st): ?>
        <li class="bk-step flex items-center gap-2 <?= $i ? 'text-ink-400' : 'text-brand-700 font-semibold' ?>" data-s="<?= $i + 1 ?>">
          <span class="bk-dot w-7 h-7 rounded-full grid place-items-center text-xs font-bold border <?= $i ? 'border-ink-200 bg-white' : 'border-brand-600 bg-brand-600 text-white' ?>"><?= $i + 1 ?></span>
          <span class="hidden sm:inline"><?= e($st) ?></span>
        </li>
        <?php if ($i < 2): ?><li class="flex-1 h-px bg-ink-200 min-w-4" aria-hidden="true"></li><?php endif; ?>
      <?php endforeach; ?>
    </ol>

    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-5 min-w-0">
        <form method="post" enctype="multipart/form-data" class="space-y-5" id="bk-form">
          <?= csrf_field() ?>

          <!-- 1) البداية -->
          <section id="bk-start" class="card p-5">
            <h2 class="font-bold text-ink-900"><?= e(t('هتجيب المنشورات منين؟')) ?></h2>
            <p class="text-sm text-ink-500 mt-1"><?= e(t('كل سطر = منشور. هتشوفهم في جدول وتعدّل قبل ما أي حاجة تتبعت.')) ?></p>
            <div id="bk-draft" class="hidden mt-4 rounded-xl border border-brand-200 bg-brand-50/60 p-3 flex flex-wrap items-center gap-2 text-sm">
              <span>💾 <span id="bk-draft-t"></span></span>
              <button type="button" id="bk-draft-go" class="btn-primary !py-1 text-xs ms-auto"><?= e(t('كمّل')) ?></button>
              <button type="button" id="bk-draft-x" class="btn-ghost !py-1 text-xs"><?= e(t('امسحه')) ?></button>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4">
              <label id="bk-drop" class="group flex flex-col items-center text-center gap-2 rounded-2xl border-2 border-dashed border-ink-200 hover:border-brand-400 hover:bg-brand-50/40 p-5 cursor-pointer transition focus-within:ring-2 ring-brand-300">
                <span class="w-11 h-11 rounded-xl bg-brand-50 text-brand-600 grid place-items-center text-xl group-hover:scale-110 transition">⇪</span>
                <span class="font-semibold text-ink-900" id="bk-fn"><?= e(t('ارفع ملف')) ?></span>
                <span class="text-xs text-ink-500"><?= e(t('CSV من Excel أو Google Sheets، أو اسحبه هنا')) ?></span>
                <input type="file" name="csv" id="bk-file" accept=".csv,.tsv,.txt,text/csv" class="sr-only">
              </label>
              <button type="button" id="bk-paste-btn" class="group flex flex-col items-center text-center gap-2 rounded-2xl border-2 border-ink-200 hover:border-brand-400 hover:bg-brand-50/40 p-5 transition">
                <span class="w-11 h-11 rounded-xl bg-lime-50 text-lime-700 grid place-items-center text-xl group-hover:scale-110 transition">📋</span>
                <span class="font-semibold text-ink-900"><?= e(t('الصق من Excel')) ?></span>
                <span class="text-xs text-ink-500"><?= e(t('حدد الخانات في الشيت وانسخها والصقها هنا')) ?></span>
              </button>
              <?php if (can('ai.use')): ?>
              <button type="button" id="bk-ai-btn" class="group flex flex-col items-center text-center gap-2 rounded-2xl border-2 border-violet-200 bg-gradient-to-br from-violet-50 to-white hover:border-violet-400 p-5 transition">
                <span class="w-11 h-11 rounded-xl bg-violet-100 text-violet-700 grid place-items-center text-xl group-hover:scale-110 transition">✦</span>
                <span class="font-semibold text-ink-900"><?= e(t('اكتبها بالذكاء الاصطناعي')) ?></span>
                <span class="text-xs text-ink-500"><?= e(t('اكتب الفكرة وخد 10 أو 30 منشور مختلفين')) ?></span>
              </button>
              <?php endif; ?>
              <button type="button" id="bk-lib-start" class="group flex flex-col items-center text-center gap-2 rounded-2xl border-2 border-sky-200 bg-gradient-to-br from-sky-50 to-white hover:border-sky-400 p-5 transition">
                <span class="w-11 h-11 rounded-xl bg-sky-100 text-sky-700 grid place-items-center text-xl group-hover:scale-110 transition">🖼</span>
                <span class="font-semibold text-ink-900"><?= e(t('من مكتبة الوسائط')) ?></span>
                <span class="text-xs text-ink-500"><?= e(t('اختار 50 صورة وخد 50 منشور، كل صورة في منشور')) ?></span>
              </button>
              <button type="button" id="bk-new" class="group flex flex-col items-center text-center gap-2 rounded-2xl border-2 border-ink-200 hover:border-brand-400 hover:bg-brand-50/40 p-5 transition">
                <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 grid place-items-center text-xl group-hover:scale-110 transition">✎</span>
                <span class="font-semibold text-ink-900"><?= e(t('اكتب هنا')) ?></span>
                <span class="text-xs text-ink-500"><?= e(t('جدول فاضي تكتب فيه على طول')) ?></span>
              </button>
            </div>
            <div id="bk-paste" class="hidden mt-4">
              <textarea id="bk-paste-txt" rows="6" class="input font-mono text-xs" dir="auto" placeholder="<?= e(t('الصق هنا (Ctrl+V). أول سطر لازم يكون أسماء الأعمدة، زي: النص، المنصات، الميعاد')) ?>"></textarea>
              <div class="flex gap-2 mt-2"><button type="button" id="bk-paste-go" class="btn-primary"><?= e(t('اعرض في الجدول')) ?></button><button type="button" id="bk-paste-x" class="btn-ghost"><?= e(t('إلغاء')) ?></button></div>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-4 text-sm">
              <a class="link" download="bulk-template.csv" href="<?= e(url('bulk', ['sample' => 1])) ?>">⇣ <?= e(t('نزّل الملف الجاهز')) ?></a>
              <span class="text-xs text-ink-500"><?= e(t('أسماء الأعمدة ممكن تبقى بالعربي (النص، المنصات، الميعاد، الصورة) وهنفهمها.')) ?></span>
            </div>
          </section>

          <!-- 2) المراجعة -->
          <section id="bk-grid-wrap" class="card p-4 space-y-3" style="display:none">
            <div class="flex flex-wrap items-center gap-2">
              <div class="min-w-0">
                <div class="font-semibold text-ink-900 truncate" id="bk-fname"></div>
                <div id="bk-sum" class="text-sm mt-0.5"></div>
              </div>
              <div class="ms-auto flex flex-wrap items-center gap-1.5">
                <button type="button" id="bk-undo" class="btn-ghost !py-1 text-xs" disabled title="Ctrl+Z">↶ <?= e(t('تراجع')) ?></button>
                <button type="button" id="bk-add" class="btn-ghost !py-1 text-xs">+ <?= e(t('سطر')) ?></button>
                <button type="button" id="bk-lib-add" class="btn-ghost !py-1 text-xs text-sky-700">🖼 <?= e(t('صور من المكتبة')) ?></button>
                <?php if (can('ai.use')): ?><button type="button" id="bk-ai-add" class="btn-ghost !py-1 text-xs text-violet-700">✦ <?= e(t('زوّد بالذكاء الاصطناعي')) ?></button>
                <button type="button" id="bk-cap-btn" class="btn-ghost !py-1 text-xs text-violet-700">✦ <?= e(t('اكتب النصوص الفاضية')) ?></button><?php endif; ?>
                <button type="button" id="bk-dl" class="btn-ghost !py-1 text-xs">⇣ CSV</button>
                <button type="button" id="bk-reset" class="btn-ghost !py-1 text-xs"><?= e(t('ابدأ من جديد')) ?></button>
              </div>
            </div>
            <div id="bk-notes" class="space-y-1.5"></div>
            <div id="bk-map" class="hidden rounded-xl border border-amber-200 bg-amber-50/60 p-3"></div>

            <details id="bk-tools" class="rounded-xl border border-ink-200 bg-ink-50/50" open>
              <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-ink-800">⚡ <?= e(t('أدوات سريعة')) ?></summary>
              <div class="px-3 pb-1 flex flex-wrap items-center gap-1.5 text-xs" role="radiogroup" aria-label="<?= e(t('طبّق على')) ?>">
                <span class="text-ink-600"><?= e(t('طبّق على:')) ?></span>
                <label class="cursor-pointer"><input type="radio" name="bk-scope" value="all" checked class="peer sr-only"><span class="inline-block px-2 py-1 rounded-lg border border-ink-200 bg-white peer-checked:bg-ink-900 peer-checked:text-white peer-checked:border-ink-900"><?= e(t('كل السطور')) ?></span></label>
                <label class="cursor-pointer"><input type="radio" name="bk-scope" value="sel" class="peer sr-only"><span class="inline-block px-2 py-1 rounded-lg border border-ink-200 bg-white peer-checked:bg-ink-900 peer-checked:text-white peer-checked:border-ink-900"><?= e(t('المتحدد بس')) ?> <b id="bk-selc">0</b></span></label>
                <label class="cursor-pointer"><input type="radio" name="bk-scope" value="empty" class="peer sr-only"><span class="inline-block px-2 py-1 rounded-lg border border-ink-200 bg-white peer-checked:bg-ink-900 peer-checked:text-white peer-checked:border-ink-900"><?= e(t('الخانات الفاضية بس')) ?></span></label>
              </div>
              <div class="grid md:grid-cols-2 gap-3 p-3 pt-2">
                <div class="rounded-xl bg-white border border-ink-200 p-3">
                  <div class="text-xs font-semibold text-ink-700 mb-2"><?= e(t('المنصات')) ?></div>
                  <div class="flex flex-wrap gap-1.5" id="bk-pl-pick">
                    <?php foreach (($allPl ?: ['facebook', 'instagram', 'linkedin', 'twitter', 'tiktok']) as $pl): ?>
                      <label class="cursor-pointer"><input type="checkbox" value="<?= e($pl) ?>" class="peer sr-only"><span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-ink-200 text-xs text-ink-600 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-focus-visible:ring-2 ring-brand-300"><?= $plIcon($pl) ?><?= e($pl) ?></span></label>
                    <?php endforeach; ?>
                  </div>
                  <button type="button" id="bk-pl-apply" class="btn-primary !py-1 text-xs mt-2"><?= e(t('طبّق')) ?></button>
                </div>
                <div class="rounded-xl bg-white border border-ink-200 p-3 space-y-2">
                  <div class="flex items-center gap-2">
                    <div class="text-xs font-semibold text-ink-700"><?= e(t('وزّع المواعيد')) ?></div>
                    <div class="ms-auto inline-flex rounded-lg border border-ink-200 p-0.5 text-[11px]" role="group">
                      <button type="button" class="bk-spm px-2 py-0.5 rounded-md bg-ink-900 text-white" data-m="gap"><?= e(t('مسافة ثابتة')) ?></button>
                      <button type="button" class="bk-spm px-2 py-0.5 rounded-md text-ink-600 <?= $best ? '' : 'opacity-50' ?>" data-m="best" <?= $best ? '' : 'disabled title="' . e(t('محتاج منشورات قديمة عشان نعرف أحسن أوقاتك')) . '"' ?>>★ <?= e(t('أحسن أوقاتك')) ?></button>
                    </div>
                  </div>
                  <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span><?= e(t('من')) ?></span><input type="datetime-local" id="bk-sp-start" class="input !h-8 !py-0 text-xs !w-auto">
                    <span class="bk-gap"><?= e(t('وكل')) ?></span><input type="number" id="bk-sp-n" min="1" max="720" value="1" class="bk-gap input !h-8 !py-0 text-xs !w-14">
                    <select id="bk-sp-u" class="bk-gap input !h-8 !py-0 text-xs !w-auto"><option value="d"><?= e(t('يوم')) ?></option><option value="h"><?= e(t('ساعة')) ?></option></select>
                  </div>
                  <div class="bk-gap flex flex-wrap items-center gap-1.5 text-xs">
                    <span><?= e(t('ساعات النشر')) ?></span>
                    <select id="bk-sp-from" class="input !h-8 !py-0 text-xs !w-auto"><?php for ($h = 0; $h < 24; $h++): ?><option value="<?= $h ?>" <?= $h === 9 ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option><?php endfor; ?></select>
                    <span>–</span>
                    <select id="bk-sp-to" class="input !h-8 !py-0 text-xs !w-auto"><?php for ($h = 1; $h <= 24; $h++): ?><option value="<?= $h ?>" <?= $h === 23 ? 'selected' : '' ?>><?= sprintf('%02d:00', $h % 24) ?></option><?php endfor; ?></select>
                  </div>
                  <div class="bk-best hidden text-[11px] text-violet-700" id="bk-best-txt"></div>
                  <div class="flex flex-wrap gap-1" id="bk-sp-days" aria-label="<?= e(t('أيام النشر')) ?>">
                    <?php foreach ([6, 0, 1, 2, 3, 4, 5] as $dw): ?><label class="cursor-pointer"><input type="checkbox" value="<?= $dw ?>" checked class="peer sr-only"><span class="inline-block px-2 text-center py-1 rounded-md border border-ink-200 text-[11px] text-ink-500 peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600"><?= e(is_rtl() ? preg_replace('/^ال/u', '', weekday_name($dw)) : mb_substr(weekday_name($dw), 0, 3)) ?></span></label><?php endforeach; ?>
                  </div>
                  <button type="button" id="bk-sp-apply" class="btn-primary !py-1 text-xs"><?= e(t('طبّق')) ?></button>
                </div>
              </div>
            </details>

            <div id="bk-days" class="hidden"></div>

            <div class="flex flex-wrap items-center gap-2">
              <div id="bk-filter" class="flex flex-wrap gap-1.5" role="group">
                <button type="button" class="chip on text-xs" data-f=""><?= e(t('الكل')) ?></button>
                <button type="button" class="chip text-xs" data-f="bad"><?= e(t('فيها مشكلة')) ?></button>
                <button type="button" class="chip text-xs hidden" data-f="srv"><?= e(t('رفضها Zernio')) ?></button>
              </div>
              <input id="bk-q" type="search" class="input !h-8 text-xs w-full sm:w-48 ms-auto" placeholder="<?= e(t('دوّر في النصوص…')) ?>">
            </div>
            <div id="bk-selbar" class="hidden sticky top-16 z-10 rounded-xl bg-ink-900 text-white px-3 py-2 text-xs flex flex-wrap items-center gap-2">
              <b id="bk-seln"></b>
              <button type="button" id="bk-sel-dup" class="rounded-md bg-white/10 hover:bg-white/20 px-2 py-1"><?= e(t('كرّرهم')) ?></button>
              <button type="button" id="bk-sel-del" class="rounded-md bg-red-500/80 hover:bg-red-500 px-2 py-1"><?= e(t('امسحهم')) ?></button>
              <button type="button" id="bk-sel-x" class="ms-auto rounded-md hover:bg-white/10 px-2 py-1"><?= e(t('إلغاء التحديد')) ?></button>
            </div>
            <div class="overflow-auto max-h-[30rem] rounded-xl border border-ink-200" dir="ltr"><table class="w-full text-xs" id="bk-grid"></table></div>
            <p class="text-[11px] text-ink-500"><?= e(t('دوس على أي خانة عشان تعدّلها. التعديلات بتتبعت مع الملف.')) ?></p>
          </section>

          <!-- 3) الرفع -->
          <section id="bk-send" class="card p-5 space-y-3" style="display:none">
            <div class="grid sm:grid-cols-2 gap-2">
              <label class="cursor-pointer"><input type="radio" name="mode" value="check" checked class="peer sr-only">
                <span class="block h-full rounded-xl border-2 border-ink-200 p-3 peer-checked:border-brand-600 peer-checked:bg-brand-50/50 peer-focus-visible:ring-2 ring-brand-300">
                  <span class="font-semibold text-ink-900">🔍 <?= e(t('افحص بس')) ?></span>
                  <span class="block text-xs text-ink-500 mt-0.5"><?= e(t('مش هيتعمل أي منشور. Zernio هيقولك كل سطر سليم ولا لأ.')) ?></span></span></label>
              <label class="cursor-pointer"><input type="radio" name="mode" value="create" class="peer sr-only">
                <span class="block h-full rounded-xl border-2 border-ink-200 p-3 peer-checked:border-brand-600 peer-checked:bg-brand-50/50 peer-focus-visible:ring-2 ring-brand-300">
                  <span class="font-semibold text-ink-900">🚀 <?= e(t('اعمل المنشورات')) ?></span>
                  <span class="block text-xs text-ink-500 mt-0.5"><?= e(t('كل سطر سليم هيتجدول أو يتنشر. السطور اللي اتعملت بتتشال من الجدول عشان ماتتكررش.')) ?></span></span></label>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <button class="btn-primary" id="bk-go"><?= e(t('ابدأ')) ?></button>
              <span id="bk-go-hint" class="text-xs text-ink-500"></span>
            </div>
            <div id="bk-prog" class="hidden">
              <div class="flex justify-between text-xs text-ink-600 mb-1"><span id="bk-prog-t"></span><span id="bk-prog-p"></span></div>
              <div class="h-2 rounded-full bg-ink-100 overflow-hidden"><div id="bk-prog-bar" class="h-full bg-gradient-to-l from-brand-600 to-cyan-500 transition-all" style="width:0"></div></div>
            </div>
          </section>
        </form>

        <div id="bk-ai" class="fixed inset-0 z-50 hidden grid place-items-center p-4" aria-hidden="true">
          <div class="absolute inset-0 bg-ink-900/40" data-close></div>
          <div class="relative card p-5 w-full max-w-lg space-y-3" role="dialog" aria-modal="true" aria-labelledby="bk-ai-h">
            <h3 id="bk-ai-h" class="font-bold text-ink-900">✦ <?= e(t('اكتب المنشورات بالذكاء الاصطناعي')) ?></h3>
            <textarea id="bk-ai-topic" class="input" rows="4" maxlength="1500" placeholder="<?= e(t('الفكرة: مثلاً "عروض رمضان على المشويات، توصيل مجاني فوق 300 جنيه، فرع مدينة نصر والتجمع"')) ?>"></textarea>
            <div>
              <span class="label"><?= e(t('العدد')) ?></span>
              <div class="flex flex-wrap gap-1.5" id="bk-ai-n"><?php foreach ([5, 10, 20, 30] as $i => $n): ?><label class="cursor-pointer"><input type="radio" name="bk-ai-n" value="<?= $n ?>" class="peer sr-only" <?= $n === 10 ? 'checked' : '' ?>><span class="inline-block px-3 py-1 rounded-lg border border-ink-200 text-sm peer-checked:bg-violet-600 peer-checked:text-white peer-checked:border-violet-600"><?= $n ?></span></label><?php endforeach; ?></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <label class="block"><span class="label"><?= e(t('الأسلوب')) ?></span><select id="bk-ai-tone" class="input"><option value="normal"><?= e(t('ودود')) ?></option><option value="sales"><?= e(t('بيعي')) ?></option><option value="fun"><?= e(t('مرح')) ?></option><option value="formal"><?= e(t('رسمي')) ?></option></select></label>
              <label class="block"><span class="label"><?= e(t('اللغة')) ?></span><select id="bk-ai-lang" class="input"><option value="eg"><?= e(t('مصري')) ?></option><option value="gulf"><?= e(t('خليجي')) ?></option><option value="msa"><?= e(t('فصحى')) ?></option><option value="en">English</option></select></label>
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
              <label class="flex items-center gap-1.5"><input type="checkbox" id="bk-ai-emoji" checked> <?= e(t('إيموجي')) ?></label>
              <label class="flex items-center gap-1.5"><input type="checkbox" id="bk-ai-tags" checked> <?= e(t('هاشتاجات')) ?></label>
            </div>
            <p class="text-xs text-ink-500"><?= e(t('المنشورات بتنزل في الجدول وتراجعها وتعدّلها قبل أي رفع. بيستهلك من رصيد الذكاء الاصطناعي.')) ?></p>
            <div class="flex items-center gap-2"><button type="button" id="bk-ai-go" class="btn-primary flex-1"><?= e(t('اكتب')) ?></button><button type="button" class="btn-ghost" data-close><?= e(t('إلغاء')) ?></button></div>
            <div id="bk-ai-msg" class="text-xs"></div>
          </div>
        </div>

        <div id="bk-lib" class="fixed inset-0 z-50 hidden" aria-hidden="true">
          <div class="absolute inset-0 bg-ink-900/40" data-close></div>
          <div class="absolute inset-2 sm:inset-6 lg:inset-x-16 lg:inset-y-8 card flex flex-col overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="bk-lib-h">
            <div class="flex flex-wrap items-center gap-2 p-3 border-b border-ink-100">
              <h3 id="bk-lib-h" class="font-bold text-ink-900">🖼 <?= e(t('مكتبة الوسائط')) ?></h3>
              <div class="inline-flex rounded-lg border border-ink-200 p-0.5 text-xs" role="group" id="bk-lib-kind">
                <button type="button" data-k="image" class="px-2.5 py-1 rounded-md bg-ink-900 text-white"><?= e(t('صور')) ?></button>
                <button type="button" data-k="video" class="px-2.5 py-1 rounded-md text-ink-600"><?= e(t('فيديو')) ?></button>
              </div>
              <select id="bk-lib-folder" class="input !h-8 !py-0 text-xs !w-auto"><option value=""><?= e(t('كل الفولدرات')) ?></option></select>
              <input id="bk-lib-q" type="search" class="input !h-8 text-xs w-40" placeholder="<?= e(t('دوّر…')) ?>">
              <button type="button" id="bk-lib-all" class="btn-ghost !py-1 text-xs"><?= e(t('اختار الكل')) ?></button>
              <?php if (can('posts.create')): ?>
              <label class="btn-ghost !py-1 text-xs cursor-pointer">⇪ <?= e(t('ارفع من جهازك')) ?><input type="file" id="bk-lib-up" multiple accept="image/*,video/*" class="sr-only"></label>
              <?php endif; ?>
              <button type="button" class="btn-ghost !py-1 text-xs ms-auto" data-close aria-label="<?= e(t('اقفل')) ?>">✕</button>
            </div>
            <div id="bk-lib-drop" class="flex-1 overflow-y-auto p-3 relative">
              <div id="bk-lib-grid" class="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-7 xl:grid-cols-8 gap-2"></div>
              <div id="bk-lib-more" class="hidden text-center py-3"><button type="button" class="btn-ghost text-xs"><?= e(t('اعرض أكتر')) ?></button></div>
              <div id="bk-lib-empty" class="hidden text-center text-sm text-ink-500 py-12"><?= e(t('مفيش ملفات هنا. اسحب صور من جهازك على المربع ده عشان ترفعها.')) ?></div>
              <div id="bk-lib-dz" class="hidden absolute inset-2 rounded-2xl border-2 border-dashed border-sky-500 bg-sky-50/90 grid place-items-center text-sky-800 font-semibold"><?= e(t('سيب الصور هنا عشان ترفعها للمكتبة')) ?></div>
            </div>
            <div class="border-t border-ink-100 p-3 flex flex-wrap items-center gap-2">
              <span class="text-sm"><b id="bk-lib-n">0</b> <?= e(t('متحدد')) ?></span>
              <span id="bk-lib-msg" class="text-xs text-ink-500"></span>
              <div class="ms-auto flex flex-wrap items-center gap-2" id="bk-lib-acts">
                <button type="button" data-a="row" class="btn-primary text-sm hidden"><?= e(t('حطها في السطر ده')) ?></button>
                <button type="button" data-a="new" class="btn-primary text-sm"><?= e(t('منشور لكل صورة')) ?></button>
                <button type="button" data-a="spread" class="btn-ghost text-sm"><?= e(t('صورة لكل سطر موجود')) ?></button>
                <button type="button" data-a="same" class="btn-ghost text-sm"><?= e(t('نفس الصور لكل السطور')) ?></button>
              </div>
            </div>
          </div>
        </div>

        <div id="bk-cap" class="fixed inset-0 z-50 hidden grid place-items-center p-4" aria-hidden="true">
          <div class="absolute inset-0 bg-ink-900/40" data-close></div>
          <div class="relative card p-5 w-full max-w-lg space-y-3" role="dialog" aria-modal="true" aria-labelledby="bk-cap-h">
            <h3 id="bk-cap-h" class="font-bold text-ink-900">✦ <?= e(t('اكتب نص لكل سطر فاضي')) ?></h3>
            <p class="text-sm text-ink-600" id="bk-cap-info"></p>
            <textarea id="bk-cap-brief" class="input" rows="3" maxlength="800" placeholder="<?= e(t('معلومة عن الحملة (اختياري): مثلاً "خصم 20% لحد آخر الشهر، التوصيل مجاني"')) ?>"></textarea>
            <label id="bk-cap-vis-l" class="flex items-start gap-2 rounded-xl border border-violet-200 bg-violet-50/60 p-3 cursor-pointer">
              <input type="checkbox" id="bk-cap-vis" class="mt-1" checked>
              <span><span class="font-semibold text-ink-900 text-sm">👁 <?= e(t('خلّي الذكاء الاصطناعي يشوف الصور')) ?></span>
                <span class="block text-xs text-ink-600 mt-0.5" id="bk-cap-vis-t"></span></span>
            </label>
            <label class="block"><span class="label"><?= e(t('اللغة')) ?></span><select id="bk-cap-lang" class="input"><option value="eg"><?= e(t('مصري')) ?></option><option value="gulf"><?= e(t('خليجي')) ?></option><option value="msa"><?= e(t('فصحى')) ?></option><option value="en">English</option></select></label>
            <div id="bk-cap-prog" class="hidden"><div class="h-2 rounded-full bg-ink-100 overflow-hidden"><div id="bk-cap-bar" class="h-full bg-violet-500 transition-all" style="width:0"></div></div></div>
            <div class="flex items-center gap-2"><button type="button" id="bk-cap-go" class="btn-primary flex-1"><?= e(t('اكتب')) ?></button><button type="button" class="btn-ghost" data-close><?= e(t('إلغاء')) ?></button></div>
            <div id="bk-cap-msg" class="text-xs"></div>
          </div>
        </div>

        <div id="bk-pv" class="fixed inset-0 z-50 hidden grid place-items-center p-4" aria-hidden="true">
          <div class="absolute inset-0 bg-ink-900/40" data-close></div>
          <div class="relative card w-full max-w-md overflow-hidden" role="dialog" aria-modal="true" aria-label="<?= e(t('معاينة')) ?>">
            <div id="bk-pv-body"></div>
            <div class="p-3 border-t border-ink-100 flex items-center gap-2"><button type="button" id="bk-pv-prev" class="btn-ghost !py-1 text-xs"><?= is_rtl() ? '→' : '←' ?></button><span id="bk-pv-n" class="text-xs text-ink-500"></span><button type="button" id="bk-pv-next" class="btn-ghost !py-1 text-xs"><?= is_rtl() ? '←' : '→' ?></button><button type="button" class="btn-ghost !py-1 text-xs ms-auto" data-close><?= e(t('اقفل')) ?></button></div>
          </div>
        </div>

        <div id="bk-res" aria-live="polite"></div>

        <?php if ($result): $d = $result['data'];
            // نتيجة الرفع من غير جافاسكربت
            $groups = [];
            foreach ($d['results'] ?? [] as $row) {
                if (!empty($row['ok'])) continue;
                foreach ((array)($row['errors'] ?? []) as $er) { $groups[(string)$er][] = (int)($row['rowIndex'] ?? 0); }
            }
            uasort($groups, function ($a, $b) { return count($b) - count($a); });
            ?>
          <div class="card p-5 space-y-3">
            <h2 class="font-bold"><?= e($result['dry'] ? t('نتيجة الفحص') : t('نتيجة الرفع')) ?> · <span class="text-ink-500 text-sm" dir="ltr"><?= e($result['file']) ?></span></h2>
            <p class="text-sm"><?= e(t('إجمالي %s سطر: %s سليم و %s فيه مشكلة.', (int)($d['total'] ?? 0), (int)($d['valid'] ?? 0), (int)($d['invalid'] ?? 0))) ?></p>
            <?php foreach ($groups as $code => $ix): [$msg, $fix] = $bkErr($code); ?>
              <div class="rounded-xl border border-red-200 bg-red-50/40 p-3"><div class="font-medium text-red-800"><?= e($msg) ?> <span class="text-xs text-red-600">(<?= e(t('%s سطر', count(array_unique($ix)))) ?>)</span></div><?php if ($fix): ?><div class="text-sm text-ink-700 mt-1">✅ <?= e($fix) ?></div><?php endif; ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <aside class="space-y-4 h-fit lg:sticky lg:top-20">
        <?php if ($profs): ?>
          <div class="card p-4 space-y-2">
            <div class="text-sm font-semibold text-ink-900"><?= e(t('المنصات المربوطة')) ?></div>
            <div class="flex flex-wrap gap-1.5">
              <?php foreach ($allPl as $pl): ?><button type="button" class="bk-copy inline-flex items-center gap-1 font-mono text-xs rounded-lg bg-white border border-ink-200 px-2 py-1 hover:border-brand-400" data-v="<?= e($pl) ?>"><?= $plIcon($pl) ?><span><?= e($pl) ?></span></button><?php endforeach; ?>
              <?php if (!$allPl): ?><span class="text-xs text-amber-700"><?= e(t('مفيش حسابات مربوطة')) ?></span><?php endif; ?>
            </div>
            <div class="text-[11px] text-ink-400"><?= e(t('دوس على أي منصة عشان تنسخها. لو أكتر من منصة افصل بـ | (مثلاً facebook|instagram).')) ?></div>
          </div>
        <?php endif; ?>
        <div class="card p-4 text-sm space-y-2">
          <div class="font-semibold text-ink-900"><?= e(t('هنصلح لوحدنا')) ?></div>
          <ul class="text-xs text-ink-600 space-y-1.5 list-disc ps-4">
            <li><?= e(t('أسماء الأعمدة بالعربي أو الإنجليزي')) ?></li>
            <li><?= e(t('المواعيد زي 1/12/2026 10 م أو تاريخ Excel')) ?></li>
            <li><?= e(t('المنصات زي فيسبوك، انستجرام، FB، IG')) ?></li>
            <li><?= e(t('الملفات الكبيرة بتترفع على دفعات 500 سطر')) ?></li>
            <li><?= e(t('عمود profiles بيتجاهل، والبروفايل بيتحط لوحده')) ?></li>
          </ul>
        </div>
        <details class="card p-4">
          <summary class="cursor-pointer font-semibold text-ink-900 text-sm"><?= e(t('دليل الأعمدة')) ?></summary>
          <ul class="space-y-2 text-sm mt-3">
            <?php foreach ($cols as [$c, $desc, $req]): ?>
              <li><code class="badge" dir="ltr"><?= e($c) ?></code> <?php if ($req): ?><span class="text-xs text-red-600"><?= e(t('مطلوب')) ?></span><?php endif; ?>
                <span class="block text-ink-600 text-xs mt-0.5"><?= e($desc) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <p class="text-xs text-ink-500 mt-2"><?= e(t('لازم يكون فيه ميعاد لكل سطر: schedule_time أو use_queue أو publish_now أو is_draft. الحد الأقصى 5000 سطر.')) ?></p>
        </details>
      </aside>
    </div>
    <script>window.BKX = <?= json_encode([
        'tz' => site_timezone(), 'url' => url('bulk'), 'csrf' => csrf_token(), 'cal' => url('calendar'), 'edit' => url('post_edit'),
        'platforms' => array_keys(platform_icons()), 'connected' => $allPl, 'icons' => array_map(function ($i) { return $i; }, $icons),
        'rtl' => is_rtl(), 'lang' => lang(), 'media' => ['list' => url('media', ['ajax' => 'list']), 'post' => url('media'), 'thumb' => url('media', ['file' => '__ID__', 'thumb' => 1]), 'file' => url('media', ['file' => '__ID__']), 'canUp' => can('posts.create')], 'best' => $best, 'ws' => (int)ws('id'), 'brand' => (string)ws('name'),
        'lim' => ['twitter' => 280, 'threads' => 500, 'bluesky' => 300, 'instagram' => 2200, 'tiktok' => 2200, 'linkedin' => 3000, 'facebook' => 63206, 'pinterest' => 500, 'youtube' => 5000, 'googlebusiness' => 1500, 'reddit' => 40000, 'telegram' => 4096],
        's' => [
            'rows' => t('%s سطر'), 'ok' => t('%s سليم'), 'bad' => t('%s فيه مشكلة'), 'need' => t('عمود ناقص: %s'), 'req' => t('فاضي'), 'date' => t('الميعاد لازم يكون بالشكل 2026-12-01 10:00'),
            'past' => t('الميعاد ده عدّى'), 'plat' => t('منصة مش معروفة: %s'), 'x' => t('أطول من 280 حرف (X)'), 'url' => t('الرابط لازم يبدأ بـ http'), 'when' => t('محتاج ميعاد أو use_queue أو publish_now أو is_draft'),
            'noacc' => t('مفيش حساب %s مربوط'), 'copied' => t('اتنسخ'), 'max' => t('أكتر من 5000 سطر'), 'pick' => t('اختار ملف أو اكتب سطر واحد على الأقل.'), 'del' => t('شيل'), 'edited' => t('(متعدّل)'),
            'mapped' => t('فهمنا الأعمدة: %s'), 'unknown' => t('أعمدة مش معروفة — اختار هي إيه أو سيبها تتجاهل:'), 'ignore' => t('تجاهل'), 'merged' => t('دمجنا عمودين التاريخ والوقت في ميعاد واحد'),
            'fixdates' => t('صلّحنا %s ميعاد لصيغة 2026-12-01 10:00'), 'fixpl' => t('صلّحنا أسماء المنصات في %s سطر'), 'notime' => t('%s ميعاد كان من غير ساعة، حطينا 10:00'),
            'dropped' => t('عمود profiles اتشال، البروفايل بيتحط لوحده'), 'days' => t('توزيع المنشورات على الأيام'), 'queue' => t('%s في الطابور'), 'now' => t('%s فوري'), 'draft' => t('%s مسودة'),
            'plpick' => t('اختار منصة واحدة على الأقل'), 'spstart' => t('اختار ميعاد البداية'), 'applied' => t('اتطبّق على %s سطر'),
            'goCheck' => t('هنفحص %s سطر على %s دفعة'), 'goCreate' => t('هيتعمل منشور لكل سطر سليم (%s سطر على %s دفعة)'), 'confirm' => t('هيتعمل منشور لكل سطر سليم من %s سطر، و%s منهم هيتنشر فوراً. تكمل؟'),
            'batch' => t('دفعة %s من %s'), 'done' => t('خلصنا'), 'stopped' => t('وقفنا عند الدفعة %s: %s'), 'res' => t('نتيجة الفحص'), 'resC' => t('نتيجة الرفع'),
            'tot' => t('السطور'), 'okL' => t('سليم'), 'badL' => t('فيه مشكلة'), 'probs' => t('المشاكل وإزاي تصلحها'), 'nrows' => t('%s سطر'), 'show' => t('اعرضهم في الجدول'),
            'allok' => t('كل السطور سليمة 🎉 اختار "اعمل المنشورات" ودوس ابدأ.'), 'created' => t('اتعمل %s منشور واتشالوا من الجدول.'), 'opencal' => t('افتح التقويم'),
            'srv' => t('Zernio: %s'),
            'libn' => t('%s ملف'), 'libup' => t('بنرفع %s ملف…'), 'libdone' => t('اترفع %s ملف'), 'libpick' => t('اختار صورة واحدة على الأقل'), 'libnew' => t('اتعمل %s منشور، كل صورة في منشور.'),
            'libspread' => t('اتحطت صورة لكل سطر في %s سطر'), 'libsame' => t('اتحطت نفس الصور في %s سطر'), 'libcycle' => t('الصور أقل من السطور، فبتتكرر بالترتيب'), 'libmissing' => t('الصورة دي مش موجودة في المكتبة'),
            'visinfo' => t('%s سطر فيهم صورة. النص هيبقى عن اللي في الصورة فعلًا، بس أغلى بكتير وأبطأ (5 صور في كل طلب). الموديل لازم يكون بيقرا صور.'), 'visnone' => t('مفيش صور في السطور الفاضية، فهنكتب من الأسماء.'), 'saw' => t('شاف %s صورة'),
            'capinfo' => t('هنكتب نص لـ %s سطر نصهم فاضي، من وصف الصورة أو اسمها. بيستهلك من رصيد الذكاء الاصطناعي.'), 'capnone' => t('مفيش سطور نصها فاضي'), 'capwait' => t('بنكتب %s من %s…'), 'capdone' => t('اتكتب %s نص. راجعهم قبل الرفع.'), 'pickrow' => t('اختار صورة'),
            'lim' => t('أطول من حد %s (%s حرف)'), 'dup' => t('النص ده متكرر'), 'dups' => t('%s نص متكرر'), 'sel' => t('%s متحدد'), 'draft' => t('عندك شغل متحفظ: %s سطر من "%s" (%s)'),
            'aiwait' => t('بنكتب…'), 'aidone' => t('اتضاف %s منشور. وزّع المواعيد عليهم من الأدوات.'), 'aitopic' => t('اكتب الفكرة الأول.'), 'nosel' => t('حدد سطور الأول من الخانة اللي على الشمال'),
            'bestt' => t('هنستخدم أحسن أوقاتك: %s'), 'nofit' => t('مفيش مواعيد كفاية في الأيام والساعات دي لـ %s سطر'), 'pv' => t('معاينة'), 'pvof' => t('%s من %s'), 'nowL' => t('فورًا'), 'queueL' => t('في الطابور'), 'draftL' => t('مسودة'), 'left' => t('فاضل %s سطر محتاجين تصليح'), 'reset' => t('هتمسح الجدول وتبدأ من الأول. متأكد؟'), 'empty' => t('مفيش سطور'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/ui-bulk.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
