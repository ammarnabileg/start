<?php
/** منشئ الحملات المتقدم: مشاريع → حملة → مجموعات → إعلانات */
$err = null;
$z = zernio_or_null($err);
$wid = (int)ws('id');
$id = (int)($_GET['id'] ?? 0);

// ---- الذكاء الاصطناعي: بناء الحملة، جمهور مجموعة، نسخ إعلان، مراجعة الهيكل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['ai_build', 'ai_plan', 'ai_ads', 'ai_audience', 'ai_copy', 'ai_review'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    if (!can('ai.use')) { echo json_encode(['ok' => false, 'error' => t('مالكش صلاحية.')], JSON_UNESCAPED_UNICODE); exit; }
    if (!ai_connection()) { echo json_encode(['ok' => false, 'error' => t('اربط مزود ذكاء اصطناعي أولًا من التكاملات.'), 'connect' => url('connectors')], JSON_UNESCAPED_UNICODE); exit; }
    $b = ab_get($wid, (int)($_POST['id'] ?? 0));
    if (!$b) { echo json_encode(['ok' => false, 'error' => t('المشروع مش موجود')]); exit; }
    $act = $_POST['action'] === 'ai_build' ? 'ai_plan' : $_POST['action']; $d = $b['data']; $p = (string)$d['platform']; $C = ab_caps($p);
    $in = json_decode((string)($_POST['input'] ?? '{}'), true) ?: [];
    $clip = function ($v, $n = 400) { return mb_substr(is_scalar($v) ? trim((string)$v) : json_encode($v, JSON_UNESCAPED_UNICODE), 0, $n); };
    $capTxt = 'المنصة: ' . ab_platform_label($p) . '. الأهداف المسموحة: ' . implode(', ', $C['goals']) . '. أزرار الإجراء المسموحة: ' . implode(', ', array_slice($C['ctas'], 0, 30))
        . '. أقصى طول للعنوان: ' . $C['maxHeadline'] . ($C['maxBody'] ? '. أقصى طول للنص: ' . $C['maxBody'] : '') . '. ميزانية على مستوى الحملة متاحة: ' . ($C['cbo'] ? 'نعم' : 'لا')
        . '. إعلانات أكثر من واحد في المجموعة: ' . ($C['maxAdsPerSet'] === 1 ? 'لا (إعلان واحد فقط)' : 'نعم') . '. استهداف الاهتمامات متاح: ' . ($C['targeting']['interests'] ? 'نعم' : 'لا')
        . '. العملة: ' . $d['currency'] . '. دولة الشركة: ' . ws_country() . '.';
    // اللغة واللهجة اللي اختارها المستخدم (زي منشئ المحتوى)
    $dk = ['msa' => 'Modern Standard Arabic (Fus-ha)', 'saudi' => 'Saudi Arabic (light Najdi/Hijazi)', 'gulf' => 'Gulf Arabic', 'emirati' => 'Emirati Arabic', 'kuwaiti' => 'Kuwaiti Arabic', 'egyptian' => 'Egyptian Arabic', 'levantine' => 'Levantine Arabic', 'maghrebi' => 'simplified Maghrebi Arabic', 'white' => 'neutral "white" Arabic understood across the Arab world'];
    $langV = (string)($in['lang'] ?? 'ar');
    $dialectV = ($in['dialect'] ?? '') === 'other' ? mb_substr(trim((string)($in['dialectOther'] ?? '')), 0, 120) : ($dk[$in['dialect'] ?? ''] ?? '');
    $langMap = ['ar' => 'Write every ad entirely in Arabic (RTL).', 'en' => 'Write every ad entirely in English (LTR).',
        'ar_en' => 'Write each ad in Arabic as the primary language, with a short English secondary line where it helps.', 'en_ar' => 'Write each ad in English as the primary language, with a short Arabic secondary line where it helps.'];
    $langNote = ' اللغة واللهجة: ' . ($langMap[$langV] ?? $langMap['ar']) . ($dialectV !== '' && $langV !== 'en' ? ' Use this Arabic dialect/tone: ' . $dialectV . '.' : '') . ' التزم بها حرفيًا في كل العناوين والنصوص وأزرار الإجراء.';
    $rules = ' قواعد صارمة: استخدم معلومات الشركة المتاحة لك فقط ولا تخترع أسعارًا أو خصومات أو أرقامًا أو شهادات. تجنب العبارات التي ترفضها المنصات (الأفضل، الأرخص، مضمون 100%، مخاطبة صفات شخصية حساسة مثل "هل أنت...؟").' . $langNote . ' أعد JSON فقط بدون أي شرح خارجه.';
    $tasks = [
        'ai_plan' => ['أنت ميديا باير بخبرة 35 سنة أدرت ميزانيات بالملايين لشركات كبيرة ومتوسطة في الخليج ومصر والعالم، وفي نفس الوقت بيّاع شاطر بيفهم نفسية المشتري وبيكتب Direct Response، وآرت دايركتور بيبني مفاهيم بصرية بتوقف الإسكرول. بتفكر بالأرقام: مرحلة التعلم، تكلفة النتيجة المتوقعة، تداخل الجماهير، وإجهاد الإعلان. المطلوب الآن: استراتيجية الحملة وهيكلها فقط (بدون كتابة الإعلانات نفسها). '
            . 'لكل مجموعة لازم تملأ الاستهداف بالكامل: countries بأكواد ISO2 فقط (SA وليس "السعودية")، وسن واقعي (ageMin/ageMax)، وجنس. وحسب المنصة (' . ab_platform_label($p) . ') املأ مصفوفات الاستهداف المناسبة بكلمات بحث إنجليزية دقيقة (مش جمل): '
            . ($C['targeting']['interests'] ? 'interestQueries (اهتمامات Meta). ' : '') . (!empty($C['targeting']['b2b']) ? 'للـ B2B على LinkedIn: industryQueries (صناعات) وseniorityQueries (مستويات وظيفية مثل Director، CXO) وjobFunctionQueries (وظائف مثل IT، Engineering) وskillQueries (مهارات). ' : '') . (!empty($C['targeting']['work']) ? 'workQueries (مسميات/جهات عمل). ' : '')
            . 'من 3 إلى 6 عناصر لكل مصفوفة مناسبة. لا تترك الاستهداف فاضيًا. '
            . 'اعمل من 3 إلى 5 مجموعات إعلانية بجماهير مختلفة فعلًا وغير متداخلة: نوّع الأماكن (دول أو أسواق مختلفة لو منطقي للشركة)، والفئات (سن/نوع/اهتمامات/مسميات)، ومرحلة الوعي (بارد/دافئ). لكل مجموعة حدد شخصية المشتري وألمه ورغبته، ومن 3 إلى 7 مفاهيم إبداعية مختلفة الزاوية والشكل (صورة/فيديو/كاروسيل). وزّع الميزانية بنسب منطقية بحيث كل مجموعة تقدر تطلع من مرحلة التعلم، واذكر لو الميزانية مش كفاية. '
            . 'الشكل: {"campaign":{"name":"","goal":"","budgetLevel":"adset|campaign","dailyBudget":رقم,"why":"سطرين","kpis":"تكلفة النتيجة ونسبة النقر المتوقعة بالأرقام"},"adSets":[{"name":"","why":"","persona":"مين ده وبيوجعه إيه وعاوز إيه","stage":"cold|warm","budgetShare":نسبة,"targeting":{"countries":["ISO2"],"ageMin":18,"ageMax":65,"gender":"all|male|female","interestQueries":["English"],"industryQueries":["English"],"seniorityQueries":["English"],"jobFunctionQueries":["English"],"skillQueries":["English"],"workQueries":["English"]},"concepts":[{"angle":"","format":"image|video|carousel","hook":"فكرة أول ثانية/أول سطر"}]}],"tips":["نصيحة تنفيذية بالأرقام"]}', 3800],
        'ai_ads' => ['أنت ميديا باير بخبرة 35 سنة أدرت ميزانيات بالملايين لشركات كبيرة ومتوسطة في الخليج ومصر والعالم، وفي نفس الوقت بيّاع شاطر بيفهم نفسية المشتري وبيكتب Direct Response، وآرت دايركتور بيبني مفاهيم بصرية بتوقف الإسكرول. بتفكر بالأرقام: مرحلة التعلم، تكلفة النتيجة المتوقعة، تداخل الجماهير، وإجهاد الإعلان. المطلوب: اكتب الإعلانات الكاملة لمجموعة واحدة بناءً على مفاهيمها، إعلان لكل مفهوم. كبيّاع: هوك قوي في أول سطر، فائدة واضحة، اعتراض متوقع ورده، دعوة لإجراء محددة. كآرت دايركتور: لكل إعلان توجيه فني دقيق يقدر مصمم ينفذه أو أداة توليد صور تطلّعه. '
            . 'اكتب حقل prompt كآرت دايركتور محترف: فقرة إنجليزية واحدة متكاملة جاهزة للإنتاج بقيم محددة لا صفات مبهمة، بالترتيب: (1) فكرة الإعلان في جملة ونوع المفهوم؛ (2) الموضوع بالضبط: مين/إيه، السن والدور والملابس المناسبة للسوق، الوضعية والتعبير، أو المنتج وخامته وحالته؛ (3) البيئة الحقيقية المحددة (مش "مكتب" بل مثلًا "غرفة تحكم محطة ليلًا بشاشات SCADA")، الوقت والجو؛ (4) التكوين: التأطير (قاعدة الأثلاث)، ارتفاع الكاميرا، طبقات أمامية/خلفية، والمساحة الفاضية للعنوان كمنطقة أغمق طبيعية؛ (5) الكاميرا: العدسة بالمليمتر، فتحة العدسة، الزاوية؛ (6) الإضاءة: مفتاحية/ملء/حافة باتجاه ودرجة حرارة كلفن والجودة والظلال؛ (7) الألوان والمعالجة: ألوان الهوية كأكواد hex والتباين؛ (8) الواقعية: ملمس بشرة/قماش/معدن، عيوب طبيعية، لا بلاستيكية ولا AI؛ (9) المخرج: 4:5، 1080x1350، جودة حملة تجارية فوتوغرافية. وممنوع في الصورة: أي نص أو لوجو أو علامة مائية (العنوان واللوجو بيتحطوا بعدين)، وجوه AI مزيفة، أصابع زيادة، أيادي مشوهة، أيقونات عائمة، زحمة. اكتب prompt مختلف لكل إعلان حسب مفهومه. '
            . 'الشكل: {"ads":[{"name":"","angle":"","format":"image|video|carousel","headline":"","body":"","headlines":["بديلان"],"bodies":["بديل"],"cta":"","art":{"concept":"الفكرة البصرية","composition":"التكوين واللقطة","palette":"ألوان الهوية hex","overlay":"النص المقترح على التصميم (قصير)","prompt":"full professional production-ready English image-generation prompt, detailed, NO text/logo in image"},"cards":[{"headline":"","description":""}]}]}. cards فقط لو الشكل كاروسيل (3-5 كروت).', 4200],
        'ai_audience' => ['أنت خبير استهداف. اقترح جمهورًا لهذه المجموعة الإعلانية يكون مختلفًا عن جماهير المجموعات الأخرى المذكورة حتى لا تتنافس. الشكل: {"name":"اسم قصير للجمهور","why":"سطرين","targeting":{"countries":["ISO2"],"ageMin":18,"ageMax":65,"gender":"all|male|female","interestQueries":["إنجليزي"]}}', 700],
        'ai_copy' => ['أنت كاتب إعلانات ممولة وخبير علم نفس البيع. اكتب 3 نسخ مختلفة الزاوية لهذا الإعلان تناسب جمهور مجموعته. الشكل: {"variants":[{"angle":"","headline":"","body":"","headlines":["بديلان"],"bodies":["بديل"],"cta":""}]}', 1400],
        'ai_review' => ['أنت ميديا باير كبير تراجع حملة قبل إطلاقها لعميل. انتقدها بصراحة: تداخل الجماهير، ميزانية غير كافية لعدد المجموعات (القاعدة: كل مجموعة تحتاج ميزانية تكفي 50 نتيجة في الأسبوع تقريبًا لتخرج من مرحلة التعلم)، جماهير ضيقة أو واسعة جدًا، تشابه الإعلانات داخل المجموعة، ضعف الهوك في أول سطر، زر إجراء لا يناسب الهدف، الهدف لا يناسب الغاية. '
            . 'الشكل: {"score":رقم من 100,"verdict":"سطر","findings":[{"level":"high|medium|low","where":"campaign|set:رقم|ad:رقم المجموعة.رقم الإعلان","msg":"المشكلة بالأرقام","fix":"التصحيح المقترح"}]}. رتب حسب الخطورة، ولا تتجاوز 10 ملاحظات.', 1500],
    ];
    [$sys, $max] = $tasks[$act];
    // ملخص الحملة الحالية للسياق
    $sum = ['campaign' => ['name' => $d['campaign']['name'], 'goal' => $d['campaign']['goal'], 'budgetLevel' => $d['campaign']['budgetLevel'], 'budget' => $d['campaign']['budget']]];
    foreach ($d['adSets'] as $si => $st) {
        $sum['adSets'][] = ['n' => $si + 1, 'name' => $st['name'], 'budget' => $st['budget'], 'countries' => $st['targeting']['countries'], 'age' => [$st['targeting']['ageMin'], $st['targeting']['ageMax']], 'gender' => $st['targeting']['gender'],
            'interests' => array_map(function ($x) { return $x['name']; }, $st['targeting']['interests'] ?? []), 'cities' => array_map(function ($x) { return $x['name']; }, $st['targeting']['cities'] ?? []),
            'ads' => array_map(function ($a) { return ['headline' => mb_substr($a['headline'], 0, 120), 'body' => mb_substr($a['body'], 0, 300), 'cta' => $a['cta'], 'format' => $a['format'], 'hasMedia' => (bool)($a['imageUrl'] || $a['videoUrl'] || $a['cards'])]; }, $st['ads'])];
    }
    $user = "طلب المستخدم: " . $clip($in['brief'] ?? '', 1200) . (!empty($in['nSets']) ? "\nعدد المجموعات المطلوب: " . max(3, min(5, (int)$in['nSets'])) : '') . (!empty($in['nAds']) ? "\nعدد الإعلانات في كل مجموعة: " . max(3, min(7, (int)$in['nAds'])) : '') . (!empty($in['markets']) ? "\nالأسواق/الأماكن المطلوبة: " . $clip($in['markets'], 200) : '') . "\nالمنتج/الخدمة: " . $clip($in['product'] ?? '', 300) . "\nالميزانية اليومية المتاحة: " . $clip($in['budget'] ?? '', 30) . ' ' . $d['currency']
        . ($act === 'ai_ads' ? "\nالاستراتيجية: " . $clip($in['strategy'] ?? '', 1500) . "\nالمجموعة المطلوب كتابة إعلاناتها: " . $clip($in['setPlan'] ?? '', 2500) . (($vdna = ab_brand_visual()) !== '' ? "\nهوية الشركة البصرية (استخدم ألوانها وأسلوبها في حقل prompt): " . $vdna : '') : '')
        . "\nالمجموعة المقصودة: " . $clip($in['set'] ?? '', 10) . "\nالإعلان المقصود: " . $clip($in['ad'] ?? '', 10) . "\nالحملة الحالية: " . json_encode($sum, JSON_UNESCAPED_UNICODE);
    try {
        $msgs = [['role' => 'system', 'content' => $sys . ' ' . $capTxt . $rules . ai_country_note()], ['role' => 'user', 'content' => $user]];
        $r = ai_chat($msgs, $max, 'ads_builder'); $j = ab_json((string)$r['text']);
        $want = $act === 'ai_plan' ? max(3, min(5, (int)($in['nSets'] ?? 3))) : ($act === 'ai_ads' ? max(1, min(7, (int)($in['nAds'] ?? 3))) : 0);
        $got = $act === 'ai_plan' ? count((array)($j['adSets'] ?? [])) : ($act === 'ai_ads' ? count((array)($j['ads'] ?? [])) : $want);
        $partial = $j; if (is_array($j) && $got < $want) $j = null; // الرد اتقطع واتصلّح ناقص: نطلب نسخة كاملة أقصر
        if (!is_array($j)) { // محاولة تانية: نطلب JSON مختصر وصالح بس
            $msgs[] = ['role' => 'assistant', 'content' => mb_substr((string)$r['text'], 0, 1500)];
            $msgs[] = ['role' => 'user', 'content' => 'الرد اتقطع أو مش JSON صالح. أعد نفس المحتوى كـ JSON صالح فقط، أقصر في النصوص، بدون أي كلام خارجه.'];
            $r = ai_chat($msgs, $max, 'ads_builder'); $j2 = ab_json((string)$r['text']); if (is_array($j2)) $j = $j2;
        }
        if (!is_array($j) && is_array($partial)) $j = $partial; // أحسن من لا شيء
        if (!is_array($j)) throw new RuntimeException(t('الرد مكانش بالشكل المتوقع، جرّب تاني.'));
        // تطبيع الاستهداف: الموديل أحيانًا بيرجّع أسماء دول أو أعمار/جنس غير صالحة، فنصلّحها عشان تتطبّق فعلًا
        if ($act === 'ai_plan' && !empty($j['adSets']) && is_array($j['adSets'])) {
            foreach ($j['adSets'] as &$asRef) { if (!empty($asRef['targeting']) && is_array($asRef['targeting'])) $asRef['targeting'] = ab_normalize_targeting($asRef['targeting']); }
            unset($asRef);
        }
        if ($act === 'ai_audience' && !empty($j['targeting']) && is_array($j['targeting'])) $j['targeting'] = ab_normalize_targeting($j['targeting']);
        log_activity('ads.builder_ai', $act);
        echo json_encode(['ok' => true, 'r' => $j], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ---- توليد صورة/كاروسيل للإعلان من البرومبت، مع ختم اللوجو (نفس منطق مخزون المحتوى)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ai_image') {
    header('Content-Type: application/json; charset=utf-8');
    $J = function ($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
    if (!can('ai.use')) $J(['ok' => false, 'error' => t('مالكش صلاحية.')]);
    if (!function_exists('img_provider') || img_provider() === '') $J(['ok' => false, 'error' => t('اربط OpenAI أو OpenRouter من التكاملات لتوليد الصور.'), 'connect' => url('connectors')]);
    $b = ab_get($wid, (int)($_POST['id'] ?? 0)); if (!$b) $J(['ok' => false, 'error' => t('المشروع مش موجود')]);
    $prompt = trim((string)($_POST['prompt'] ?? '')); if ($prompt === '') $J(['ok' => false, 'error' => t('اكتب وصف الصورة.')]);
    $kind = ($_POST['kind'] ?? '') === 'carousel' ? 'carousel' : 'image';
    $n = $kind === 'carousel' ? max(2, min(5, (int)($_POST['n'] ?? 5))) : 1;
    $lopts = ab_logo_opts($b['data']); // إعدادات اللوجو المحفوظة على الحملة
    $rules = "\nFULL-BLEED: one complete edge-to-edge photographic image filling the whole 4:5 1080x1350 frame; no solid colour band or flat panel. Maximum commercial photographic quality, sharp and realistic, not AI-plastic. People must look like real photographed humans (natural skin, correct hands). Do NOT render any text, letters, headline, logo, brand name or watermark anywhere; keep the top area and the " . ((($lopts['logo_x'] ?? 86) < 40) ? 'top-left' : 'top-right') . " corner cleaner for the headline and logo added later.";
    session_write_close(); @set_time_limit(300); $GLOBALS['ai_timeout'] = 280;
    try {
        $names = img_generate($prompt . $rules, '4:5', $n); $ms = function_exists('cpl_media_for_names') ? cpl_media_for_names($names) : [];
        if (!$ms) $J(['ok' => false, 'error' => t('لم تُحفظ الصورة الناتجة.')]);
        $z = zernio_or_null($ze); $urls = [];
        foreach ($ms as $mid) {
            $sid = function_exists('cpl_stamp_logo') ? cpl_stamp_logo([], (int)$mid, $lopts) : (int)$mid;
            $u = '';
            if ($z) { try { $u = media_public_url($z, $wid, $sid, 'ad', (string)$b['id']); } catch (Throwable $pe) { $u = ''; } }
            if ($u === '') $u = base_url() . '/' . url('media', ['file' => $sid]); // احتياطي للمعاينة؛ الرابط العام الحقيقي بيتعمل عند الإطلاق
            $urls[] = $u;
        }
        log_activity('ads.ai_image', $kind . ' x' . count($urls));
        $J(['ok' => true, 'urls' => $urls]);
    } catch (Throwable $ex) { $J(['ok' => false, 'error' => $ex->getMessage()]); }
}
// ---- فيديو للإعلان: بدء (async) ثم متابعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ai_video') {
    header('Content-Type: application/json; charset=utf-8');
    $J = function ($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
    if (!can('ai.use')) $J(['ok' => false, 'error' => t('مالكش صلاحية.')]);
    if (!function_exists('vid_or_start')) $J(['ok' => false, 'error' => t('توليد الفيديو غير متاح.')]);
    $b = ab_get($wid, (int)($_POST['id'] ?? 0)); if (!$b) $J(['ok' => false, 'error' => t('المشروع مش موجود')]);
    $prompt = trim((string)($_POST['prompt'] ?? '')); if ($prompt === '') $J(['ok' => false, 'error' => t('اكتب وصف الفيديو.')]);
    $asp = in_array($_POST['aspect'] ?? '', ['9:16', '1:1', '16:9'], true) ? $_POST['aspect'] : '9:16';
    try { $jobId = vid_or_start($prompt, $asp); $J(['ok' => true, 'jobId' => $jobId]); }
    catch (Throwable $ex) { $J(['ok' => false, 'error' => $ex->getMessage()]); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ai_video_poll') {
    header('Content-Type: application/json; charset=utf-8');
    $J = function ($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
    $b = ab_get($wid, (int)($_POST['id'] ?? 0)); if (!$b) $J(['ok' => false], 404);
    $jobId = trim((string)($_POST['jobId'] ?? '')); if ($jobId === '' || !function_exists('vid_or_poll')) $J(['ok' => false, 'error' => t('طلب غير صحيح.')]);
    try {
        $r = vid_or_poll($jobId);
        if (($r['status'] ?? '') === 'failed') $J(['ok' => false, 'error' => (string)($r['error'] ?? t('فشل الفيديو.'))]);
        if (($r['status'] ?? '') !== 'completed') $J(['ok' => true, 'pending' => true]);
        $ms = function_exists('cpl_media_for_names') ? cpl_media_for_names((array)($r['names'] ?? [])) : [];
        if (!$ms) $J(['ok' => false, 'error' => t('اكتمل الفيديو لكن تعذّر حفظه.')]);
        $z = zernio_or_null($ze); $mid = (int)$ms[0]; $u = '';
        if ($z) { try { $u = media_public_url($z, $wid, $mid, 'ad', (string)$b['id']); } catch (Throwable $pe) { $u = ''; } }
        if ($u === '') $u = base_url() . '/' . url('media', ['file' => $mid]);
        log_activity('ads.ai_video', 'done');
        $J(['ok' => true, 'url' => $u]);
    } catch (Throwable $ex) { $J(['ok' => false, 'error' => $ex->getMessage()]); }
}
// ---- حفظ إعدادات اللوجو على الحملة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ad_logo') {
    header('Content-Type: application/json; charset=utf-8');
    $J = function ($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
    $b = ab_get($wid, (int)($_POST['id'] ?? 0)); if (!$b) $J(['ok' => false], 404);
    $lo = ab_logo_opts((array)json_decode((string)($_POST['logo'] ?? '{}'), true), true);
    $d = $b['data']; $d['logo'] = $lo; ab_save($wid, (int)$b['id'], $d);
    $lg = function_exists('kb_logo') ? kb_logo($wid) : null;
    $J(['ok' => true, 'logo' => $lo, 'logoUrl' => $lg ? url('media', ['file' => $lg['id']]) : null]);
}

// ---- تقدير الوصول من المنصة (تقدير بس، مفيش إنشاء)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reach') {
    header('Content-Type: application/json; charset=utf-8');
    $b = ab_get($wid, (int)($_POST['id'] ?? 0)); $si = (int)($_POST['si'] ?? 0);
    if (!$b || !$z || empty($b['data']['adSets'][$si]) || !ab_caps((string)$b['data']['platform'])['reach']) { echo json_encode(['ok' => false]); exit; }
    $tg = json_decode((string)($_POST['targeting'] ?? ''), true) ?: $b['data']['adSets'][$si]['targeting'];
    try {
        $r = $z->request('POST', '/v1/ads/targeting/reach-estimate', [], ['accountId' => $b['data']['connId'], 'adAccountId' => $b['data']['adAccountId'], 'spec' => ab_targeting($tg, (string)$b['data']['platform'])]);
        echo json_encode(['ok' => true, 'available' => (bool)($r['available'] ?? false), 'lower' => $r['lower'] ?? null, 'upper' => $r['upper'] ?? null, 'daily' => $r['daily'] ?? null, 'ready' => $r['estimateReady'] ?? true]);
    } catch (ZernioException $ex) { echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE); }
    exit;
}

// ---- الإطلاق خطوة بخطوة + الفحص على المنصة (validateOnly)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['launch_step', 'validate', 'reset_inflight'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $b = ab_get($wid, (int)($_POST['id'] ?? 0));
    if (!$b || !$z) { echo json_encode(['ok' => false, 'error' => t('المشروع مش موجود')]); exit; }
    if (!can('ads.spend')) { echo json_encode(['ok' => false, 'error' => t('الإطلاق لصاحب صلاحية الصرف بس.')]); exit; }
    $d = $b['data']; $plan = ab_plan($d); $C = ab_caps((string)$d['platform']);
    $pr = $b['progress'] ?: ['ids' => ['campaign' => null, 'sets' => []], 'done' => [], 'inflight' => null, 'errors' => [], 'log' => []];
    if ($_POST['action'] === 'reset_inflight') { if ($pr['inflight'] === null || $b['state'] !== 'failed') { echo json_encode(['ok' => false, 'error' => t('لا توجد خطوة معلّقة لإعادة المحاولة.')], JSON_UNESCAPED_UNICODE); exit; } $pr['inflight'] = null; ab_set_progress($wid, $b['id'], 'failed', $pr); echo json_encode(['ok' => true]); exit; }
    if ($_POST['action'] === 'validate') {
        if (!$C['validate']) { echo json_encode(['ok' => false, 'error' => t('المنصة دي مفيهاش فحص من غير إنشاء.')]); exit; }
        $res = [];
        foreach ($plan as $i => $step) {
            if ($step['k'] === 'ad') continue;
            try { $z->request('POST', $step['k'] === 'campaign' ? '/v1/ads/campaigns' : '/v1/ads/create', [], ab_payload($d, $step, [], true)); $res[] = ['i' => $i, 'ok' => true]; }
            catch (ZernioException $ex) { $res[] = ['i' => $i, 'ok' => false, 'error' => $ex->getMessage()]; }
        }
        log_activity('ads.builder_validate', (string)$b['id']);
        echo json_encode(['ok' => true, 'results' => $res], JSON_UNESCAPED_UNICODE); exit;
    }
    // launch_step
    if (empty($_POST['confirm_spend'])) { echo json_encode(['ok' => false, 'error' => t('لازم تعلّم على خانة التأكيد الأول.')]); exit; }
    if ($pr['inflight'] !== null) { echo json_encode(['ok' => false, 'unknown' => true, 'step' => $pr['inflight'], 'error' => t('خطوة اتبعتت قبل كده وما وصلش ردها. افتح صفحة الحملات واتأكد هي اتعملت ولا لأ قبل ما تكمل، عشان ما تتكررش.')], JSON_UNESCAPED_UNICODE); exit; }
    $i = null; foreach ($plan as $k => $st) if (!in_array($k, $pr['done'], true)) { $i = $k; break; }
    if ($i === null) { ab_set_progress($wid, $b['id'], 'launched', $pr); echo json_encode(['ok' => true, 'finished' => true]); exit; }
    $pr['inflight'] = $i; ab_set_progress($wid, $b['id'], 'launching', $pr);
    try {
        [$ok, $warn, $pr['ids'], $raw] = ab_run_step($z, $d, $plan[$i], $pr['ids']);
        $pr['done'][] = $i; unset($pr['errors'][$i]); $pr['log'][] = ['i' => $i, 't' => date('H:i:s'), 'warn' => $warn];
        $pr['inflight'] = null;
        $fin = count($pr['done']) === count($plan);
        ab_set_progress($wid, $b['id'], $fin ? 'launched' : 'launching', $pr);
        log_activity('ads.builder_step', $b['id'] . ' #' . $i);
        echo json_encode(['ok' => true, 'step' => $i, 'warn' => $warn, 'finished' => $fin, 'progress' => $pr], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        $unk = $ex instanceof AbCreatedNoId || ($ex instanceof ZernioException && ((int)$ex->status === 0 || (int)$ex->status >= 500)); $pr['inflight'] = $unk ? $i : null; $pr['errors'][$i] = $ex->getMessage();
        ab_set_progress($wid, $b['id'], 'failed', $pr);
        echo json_encode(['ok' => false, 'unknown' => $unk, 'step' => $i, 'error' => $ex->getMessage(), 'progress' => $pr], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ---- «اقترح من عملائي»: استهداف لينكدإن من ملف عملاء الـ CRM
if (($_GET['ajax'] ?? '') === 'icp') { header('Content-Type: application/json; charset=utf-8'); echo json_encode(ab_icp_targeting(), JSON_UNESCAPED_UNICODE); exit; }
// ---- الجماهير المخصصة (قراءة بس)
if (($_GET['ajax'] ?? '') === 'audiences' && $z) {
    header('Content-Type: application/json; charset=utf-8');
    $b = ab_get($wid, (int)($_GET['id'] ?? 0)); $out = [];
    if ($b) { try { foreach (ads_audiences($z, $b['data']['connId'], $b['data']['adAccountId']) as $a) { $aid = (string)($a['id'] ?? $a['platformAudienceId'] ?? ''); if ($aid !== '') $out[] = ['id' => $aid, 'name' => (string)($a['name'] ?? $aid)]; } } catch (ZernioException $e) {} }
    echo json_encode(['audiences' => $out], JSON_UNESCAPED_UNICODE); exit;
}

// ---- AJAX (JSON)
// ---- ميزانية نسخة (قبل الإطلاق بس): كل الميزانيات بنفس النسبة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'copy_budget') {
    header('Content-Type: application/json; charset=utf-8');
    $b = ab_get($wid, (int)($_POST['id'] ?? 0)); $amt = (float)str_replace(',', '.', (string)($_POST['amount'] ?? ''));
    if (!$b || empty($b['data']['cloneOf'])) { echo json_encode(['ok' => false, 'error' => t('المشروع مش موجود')]); exit; }
    if (!can('ads.spend')) { echo json_encode(['ok' => false, 'error' => t('الإطلاق لصاحب صلاحية الصرف بس.')]); exit; }
    if (!in_array($b['state'], ['draft', 'failed'], true) || !empty($b['progress']['done']) || (($b['progress']['inflight'] ?? null) !== null)) { echo json_encode(['ok' => false, 'error' => t('الحملة اتطلقت، مينفعش تتعدل.')]); exit; }
    if ($amt < 1 || $amt > 100000000) { echo json_encode(['ok' => false, 'error' => t('الميزانية لازم تكون 1 على الأقل.')]); exit; }
    $nd = ab_scale_budget($b['data'], $amt); ab_save($wid, $b['id'], $nd);
    log_activity('ads.builder_copy_budget', $b['id'] . ' ' . $amt);
    echo json_encode(['ok' => true, 'budget' => ab_main_budget($nd)]); exit;
}

// ---- نفس الحملة على أكتر من حساب إعلانات: نسخة مستقلة لكل حساب (كل واحدة ليها إطلاقها وسجلها)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clone_to') {
    header('Content-Type: application/json; charset=utf-8');
    $b = ab_get($wid, (int)($_POST['id'] ?? 0));
    if (!$b || !$z) { echo json_encode(['ok' => false, 'error' => t('المشروع مش موجود')]); exit; }
    if (!can('ads.spend')) { echo json_encode(['ok' => false, 'error' => t('الإطلاق لصاحب صلاحية الصرف بس.')]); exit; }
    $want = array_slice(array_values(array_filter(array_map('strval', (array)($_POST['targets'] ?? [])))), 0, 20);
    if (!$want) { echo json_encode(['ok' => false, 'error' => t('اختار حساب واحد على الأقل.')]); exit; }
    $src = $b['data']; $self = $src['connId'] . '|' . $src['adAccountId']; $made = []; $have = ab_copies($wid, (int)$b['id']); $skipped = [];
    try { $all = ads_usable_accounts($z, ads_connections($z)); } catch (ZernioException $ex) { echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE); exit; }
    foreach ($all as $t) {
        $key = $t['conn']['_id'] . '|' . $t['acc']['id'];
        if ($key === $self || !in_array($key, $want, true) || ab_platform_of((string)($t['conn']['platform'] ?? '')) !== (string)$src['platform']) continue;
        if (isset($have[(string)$t['acc']['id']])) { $skipped[] = (string)($t['acc']['name'] ?? $t['acc']['id']); continue; } // فيه نسخة بالفعل: نسختين = إطلاق مرتين وصرف مضاعف
        $d = $src; $d['connId'] = (string)$t['conn']['_id']; $d['adAccountId'] = (string)$t['acc']['id'];
        $d['cloneOf'] = (int)$b['id'];
        $d['currency'] = (string)($t['acc']['currency'] ?? $d['currency']); $d['accName'] = (string)($t['acc']['name'] ?? $t['acc']['id']);
        // الجماهير المخصصة ونماذج العملاء المحتملين خاصة بالحساب الأصلي: مالهاش وجود في الحساب التاني
        $stripped = false;
        foreach ((array)($d['adSets'] ?? []) as $si => $set) {
            if (!empty($set['targeting']['audienceId']) || !empty($set['targeting']['audienceExclude'])) $stripped = true;
            $d['adSets'][$si]['targeting']['audienceId'] = ''; $d['adSets'][$si]['targeting']['audienceExclude'] = [];
            foreach ((array)($set['ads'] ?? []) as $ai => $ad) { if (!empty($ad['leadGenFormId'])) $stripped = true; $d['adSets'][$si]['ads'][$ai]['leadGenFormId'] = ''; }
        }
        $d['campaign']['name'] = mb_substr($src['campaign']['name'] ?: $b['name'], 0, 80) . ' — ' . $d['accName'];
        $nid = ab_create($wid, $d, $d['campaign']['name']);
        $made[] = ['id' => $nid, 'name' => $d['campaign']['name'], 'acc' => $d['accName'], 'cur' => $d['currency'], 'curDiff' => $d['currency'] !== (string)$src['currency'], 'stripped' => $stripped, 'url' => url('ad_builder', ['id' => $nid]), 'budget' => ab_main_budget($d), 'state' => 'draft'];
    }
    if (!$made) { echo json_encode(['ok' => false, 'error' => $skipped ? t('فيه نسخة بالفعل على: %s', implode('، ', $skipped)) : t('مفيش حسابات صالحة على نفس المنصة.')], JSON_UNESCAPED_UNICODE); exit; }
    log_activity('ads.builder_clone', $b['id'] . ' → ' . count($made));
    echo json_encode(['ok' => true, 'made' => $made, 'skipped' => $skipped], JSON_UNESCAPED_UNICODE); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['save', 'delete', 'duplicate'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $act = $_POST['action'];
    $b = ab_get($wid, (int)($_POST['id'] ?? 0));
    if (!$b) { echo json_encode(['ok' => false, 'error' => t('المشروع مش موجود')]); exit; }
    if ($act === 'save') {
        if (!in_array($b['state'], ['draft', 'failed'], true) || !empty($b['progress']['done'])) { echo json_encode(['ok' => false, 'error' => t('الحملة اتطلقت، مينفعش تتعدل من هنا')]); exit; }
        $data = json_decode((string)($_POST['data'] ?? ''), true);
        if (!is_array($data) || strlen((string)$_POST['data']) > 2000000) { echo json_encode(['ok' => false, 'error' => t('بيانات غير صالحة')]); exit; }
        $data['platform'] = $b['data']['platform']; $data['connId'] = $b['data']['connId']; $data['adAccountId'] = $b['data']['adAccountId']; // الحساب ثابت
        ab_save($wid, $b['id'], $data);
        echo json_encode(['ok' => true, 'at' => date('H:i')]); exit;
    }
    if ($act === 'delete') {
        if ($b['state'] === 'launching' || !empty($b['progress']['done']) || !empty($b['progress']['inflight'])) { echo json_encode(['ok' => false, 'error' => t('الحملة بدأ إطلاقها على المنصة. لا يمكن حذف المشروع حتى لا يضيع سجل ما تم إنشاؤه.')], JSON_UNESCAPED_UNICODE); exit; }
        db()->prepare("DELETE FROM ad_builds WHERE workspace_id = ? AND id = ?")->execute([$wid, $b['id']]);
        echo json_encode(['ok' => true]); exit;
    }
    $d = $b['data']; $d['campaign']['name'] = t('نسخة من %s', $b['name']);
    echo json_encode(['ok' => true, 'id' => ab_create($wid, $d, $d['campaign']['name'])]); exit;
}

// ---- مشروع جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new' && $z) {
    [$connId, $accId, $cur, $cpf, $accName] = array_pad(explode('|', (string)($_POST['target'] ?? ''), 5), 5, '');
    if ($connId === '' || $accId === '') { flash('error', t('اختار حساب الإعلانات.')); redirect(url('ad_builder')); }
    $data = ab_default_data(ab_platform_of($cpf), $connId, $accId, $cur, $accName);
    $data['campaign']['name'] = trim((string)($_POST['name'] ?? '')) ?: t('حملة جديدة %s', date('d/m'));
    $goal = (string)($_POST['goal'] ?? ''); if (in_array($goal, ab_caps($data['platform'])['goals'], true)) $data['campaign']['goal'] = $goal;
    $nid = ab_create($wid, $data, $data['campaign']['name']);
    redirect(url('ad_builder', array_filter(['id' => $nid, 'ai' => !empty($_POST['ai']) ? 1 : null])));
}

$targets = [];
$apiError = null;
if ($z && (!$id || can('ads.spend'))) {
    try { $targets = ads_usable_accounts($z, ads_connections($z)); } catch (ZernioException $ex) { $apiError = $ex->getMessage(); }
}
$build = $id ? ab_get($wid, $id) : null;
if ($id && !$build) { flash('error', t('المشروع مش موجود')); redirect(url('ad_builder')); }
$list = $id ? [] : ab_list($wid);

render(t('منشئ الحملات'), 'ad_builder', function () use ($z, $apiError, $targets, $build, $list) {
    if (!$z) { no_api_box(); return; }
    if (empty($build)) ab_mode_switch('ad_builder');
    if (!$build): ?>
    <div class="space-y-5">
      <?php if ($apiError) error_box($apiError); ?>
      <?php $gl = ab_strings()['goals'] ?? []; $goalIc = ['traffic' => 'pointer', 'engagement' => 'heart', 'awareness' => 'eye', 'video_views' => 'film', 'lead_generation' => 'users', 'conversions' => 'money', 'app_promotion' => 'zap', 'page_likes' => 'star']; ?>
      <section class="card p-0 overflow-hidden">
        <div class="p-5 bg-gradient-to-l from-brand-50 to-white border-b border-ink-100"><h2 class="text-lg font-bold text-ink-900"><?= e(t('حملة جديدة')) ?></h2>
          <p class="text-sm text-ink-500 mt-1"><?= e(t('ابني حملة فيها أكتر من مجموعة إعلانية، وفي كل مجموعة أكتر من إعلان. بيتحفظ لوحده، ومش بيتطلق غير بعد مراجعتك وموافقتك.')) ?></p></div>
        <?php if (!$targets): ?><p class="text-sm text-ink-500 p-5"><?= e(t('مفيش حسابات إعلانات شغالة.')) ?> <a class="link" href="<?= e(url('ads')) ?>"><?= e(t('اربط حساب')) ?></a></p>
        <?php else: ?>
        <form method="post" id="ab-new" class="p-5 space-y-5">
          <?= csrf_field() ?><input type="hidden" name="action" value="new">
          <div><b class="flex items-center gap-2 text-sm text-ink-900 mb-2"><span class="ab-n">1</span><?= e(t('حساب الإعلانات')) ?></b>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
            <?php foreach ($targets as $i => $t): $pf = ab_platform_of((string)($t['conn']['platform'] ?? '')); ?>
              <label class="cp-acc !flex-row !items-center !text-start gap-3"><input type="radio" name="target" class="sr-only" data-pf="<?= e($pf) ?>" <?= $i ? '' : 'checked' ?> required value="<?= e(implode('|', [$t['conn']['_id'], $t['acc']['id'], $t['acc']['currency'] ?? '', $t['conn']['platform'] ?? '', $t['acc']['name'] ?? $t['acc']['id']])) ?>">
                <span class="w-10 h-10 rounded-xl bg-ink-50 grid place-items-center shrink-0"><?= platform_icon((string)($t['conn']['platform'] ?? ''), 'w-6 h-6') ?></span>
                <span class="min-w-0 flex-1"><b class="block text-sm text-ink-900 truncate"><?= e($t['acc']['name'] ?? $t['acc']['id']) ?></b><span class="text-[11px] text-ink-500"><?= e(ab_platform_label($pf)) ?> · <span dir="ltr"><?= e($t['acc']['currency'] ?? '') ?></span></span></span>
                <span class="cp-ok !static" aria-hidden="true">✓</span></label>
            <?php endforeach; ?></div></div>
          <div><b class="flex items-center gap-2 text-sm text-ink-900 mb-2"><span class="ab-n">2</span><?= e(t('هدف الحملة')) ?></b>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2" id="ab-goals">
            <?php foreach ($gl as $k => [$l, $sub]): ?>
              <label class="cp-mode !items-start !text-start !px-3 !py-3" data-goal="<?= e($k) ?>"><input type="radio" name="goal" value="<?= e($k) ?>" class="sr-only" <?= $k === 'traffic' ? 'checked' : '' ?>>
                <span class="flex items-center gap-2 text-sm font-semibold"><?= ui_icon($goalIc[$k] ?? 'target', 'w-4 h-4') ?><?= e($l) ?></span><span class="text-[11px] font-normal text-ink-500"><?= e($sub) ?></span></label>
            <?php endforeach; ?></div></div>
          <div><b class="flex items-center gap-2 text-sm text-ink-900 mb-2"><span class="ab-n">3</span><?= e(t('اسم الحملة')) ?></b>
            <div class="flex flex-wrap items-center gap-3"><input name="name" class="input !mt-0 flex-1 min-w-[240px]" maxlength="200" placeholder="<?= e(t('مثلاً: عرض نوفمبر')) ?>">
              <label class="inline-flex items-center gap-2 text-sm text-ink-700 rounded-xl border border-brand-200 bg-brand-50 px-3 py-2.5"><input type="checkbox" name="ai" value="1" class="rounded"> <?= ui_icon('spark', 'w-4 h-4 text-brand-600') ?><?= e(t('ابنيها بالذكاء الاصطناعي من ملف الشركة')) ?></label>
              <button class="btn-primary !px-6"><?= e(t('ابدأ')) ?> ←</button></div></div>
        </form>
        <script>window.AB_GOALS = <?= json_encode(array_combine(array_map(function ($t) { return ab_platform_of((string)($t['conn']['platform'] ?? '')); }, $targets), array_map(function ($t) { return ab_caps(ab_platform_of((string)($t['conn']['platform'] ?? '')))['goals']; }, $targets))) ?>;
          (function () { var f = document.getElementById('ab-new'); if (!f) return; function sync() { var pf = (f.querySelector('[name=target]:checked') || {}).dataset; var ok = (window.AB_GOALS[pf && pf.pf] || []);
              f.querySelectorAll('[data-goal]').forEach(function (l) { var on = ok.indexOf(l.dataset.goal) > -1; l.hidden = !on; if (!on) l.querySelector('input').checked = false; });
              if (!f.querySelector('[name=goal]:checked')) { var first = f.querySelector('[data-goal]:not([hidden]) input'); if (first) first.checked = true; } }
            f.addEventListener('change', function (e) { if (e.target.name === 'target') sync(); }); sync(); })();</script>
        <?php endif; ?>
      </section>
      <section>
        <div class="flex items-center gap-2 mb-3"><h2 class="text-lg font-bold text-ink-900"><?= e(t('مشاريعك')) ?></h2><span class="text-xs text-ink-400"><?= count($list) ?></span></div>
        <?php if (!$list): ?><div class="card p-8 text-center text-sm text-ink-500"><?= e(t('مفيش مشاريع لسه.')) ?></div><?php else: ?>
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
          <?php $stl = ['draft' => [t('مسودة'), 'bg-ink-100 text-ink-700'], 'launching' => [t('بيتطلق'), 'bg-amber-50 text-amber-800'], 'failed' => [t('اتطلق جزئيًا'), 'bg-red-50 text-red-700'], 'launched' => [t('اتطلقت'), 'bg-lime-100 text-lime-800']];
          foreach ($list as $r): $s = $stl[$r['state']] ?? [$r['state'], 'bg-ink-100']; ?>
            <div class="ab-proj card p-4 flex flex-col gap-3 hover:shadow-md hover:border-brand-300 transition" data-id="<?= (int)$r['id'] ?>">
              <div class="flex items-start gap-3"><span class="w-10 h-10 rounded-xl bg-ink-50 grid place-items-center shrink-0"><?= platform_icon($r['platform'] === 'meta' ? 'facebook' : (string)$r['platform'], 'w-5 h-5') ?></span>
                <a href="<?= e(url('ad_builder', ['id' => $r['id']])) ?>" class="min-w-0 flex-1"><b class="block text-ink-900 truncate hover:underline"><?= e($r['name']) ?></b><span class="text-[11px] text-ink-500"><?= e(ab_platform_label($r['platform'])) ?> · <?= e(fmt_date($r['updated_at'], 'd/m h:i A')) ?></span></a>
                <span class="mk-pill <?= $s[1] ?>"><?= e($s[0]) ?></span></div>
              <div class="flex items-center gap-2 pt-2 border-t border-ink-100"><a href="<?= e(url('ad_builder', ['id' => $r['id']])) ?>" class="btn-primary !py-1 !text-xs"><?= e(t('افتح')) ?></a>
                <button type="button" data-ab="duplicate" class="btn-ghost !py-1 !text-xs"><?= ui_icon('copy', 'w-3.5 h-3.5') ?><?= e(t('نسخ')) ?></button>
                <button type="button" data-ab="delete" class="ms-auto p-1.5 rounded-lg text-ink-400 hover:text-red-600 hover:bg-red-50" title="<?= e(t('امسح')) ?>"><?= ui_icon('trash', 'w-4 h-4') ?></button></div>
            </div>
          <?php endforeach; ?>
        </div><?php endif; ?>
      </section>
      <script>
      document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-ab]'); if (!b) return; var row = b.closest('.ab-proj');
        var q0 = b.dataset.ab === 'delete' ? (window.fxAsk ? fxAsk(<?= json_encode(t('تمسح المشروع ده؟ (الحملات اللي اتطلقت على المنصة مش هتتمسح)')) ?>, { danger: true }) : Promise.resolve(confirm(<?= json_encode(t('تمسح المشروع ده؟ (الحملات اللي اتطلقت على المنصة مش هتتمسح)')) ?>))) : Promise.resolve(true);
        q0.then(function (ok) { if (!ok) return; var fd = new FormData(); fd.append('_csrf', <?= json_encode(csrf_token()) ?>); fd.append('action', b.dataset.ab); fd.append('id', row.dataset.id);
        fetch(<?= json_encode(url('ad_builder')) ?>, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
          if (!j.ok) return (window.fxToast ? fxToast(j.error || 'Error', 'error') : alert(j.error || 'error')); if (j.id) location.href = <?= json_encode(url('ad_builder')) ?> + '&id=' + j.id; else row.remove();
        }); });
      });
      </script>
    </div>
    <?php return; endif;
    // ---- المنشئ
    $d = $build['data'];
    ?>
    <?php
    $others = array_values(array_filter($targets, function ($t) use ($d) { return ab_platform_of((string)($t['conn']['platform'] ?? '')) === (string)$d['platform'] && ($t['conn']['_id'] . '|' . $t['acc']['id']) !== ($d['connId'] . '|' . $d['adAccountId']); }));
    // النسخة نفسها مابتتنسخش (كانت هتعرض الحساب الأصلي وتعمل عليه حملة مكررة): بنوري رابط للأصل بس
    if (!empty($d['cloneOf'])) { $others = []; ?><p class="mb-3 text-xs text-ink-500"><?= ui_icon('layers', 'w-3.5 h-3.5 inline') ?> <?= e(t('دي نسخة من حملة على حساب تاني.')) ?> <a class="link" href="<?= e(url('ad_builder', ['id' => (int)$d['cloneOf']])) ?>"><?= e(t('افتح الحملة الأصلية')) ?></a></p><?php }
    $copies = $others ? ab_copies((int)ws('id'), (int)$build['id']) : [];
    $copyRows = [];
    foreach ($copies as $cacc => $cp) { $cb = ab_get((int)ws('id'), $cp['id']); if (!$cb) continue; $copyRows[] = ['id' => $cp['id'], 'name' => $cp['name'], 'acc' => (string)($cb['data']['accName'] ?? $cacc), 'cur' => (string)($cb['data']['currency'] ?? ''), 'curDiff' => ($cb['data']['currency'] ?? '') !== ($d['currency'] ?? ''), 'stripped' => false, 'url' => url('ad_builder', ['id' => $cp['id']]), 'budget' => ab_main_budget($cb['data']), 'state' => $cp['state'], 'locked' => !in_array($cb['state'], ['draft', 'failed'], true), 'partial' => !empty($cb['progress']['done'])]; }
    if ($others && can('ads.spend') && (in_array($build['state'], ['draft', 'failed'], true) && empty($build['progress']['done']) || $copyRows)): ?>
    <details class="card mb-4" id="ab-multi" <?= $copyRows ? 'open' : '' ?>>
      <summary class="flex flex-wrap items-center gap-2 px-5 py-3 cursor-pointer list-none"><?= ui_icon('layers', 'w-4 h-4 text-brand-600') ?><b class="text-sm text-ink-900"><?= e(t('نفس الحملة على حسابات تانية')) ?></b><span class="text-xs text-ink-500"><?= e(t('%s حساب على نفس المنصة', count($others))) ?></span></summary>
      <div class="px-5 pb-5 space-y-3">
        <p class="text-xs text-ink-500"><?= e(t('بنعمل نسخة مستقلة من الحملة لكل حساب تختاره (بنفس الإعدادات والإعلانات)، وتقدر تطلقهم كلهم مرة واحدة. الجماهير المخصصة ونماذج العملاء المحتملين خاصة بكل حساب، فبتتشال من النسخ.')) ?></p>
        <div class="grid sm:grid-cols-2 gap-2">
          <?php foreach ($others as $t): $k = $t['conn']['_id'] . '|' . $t['acc']['id']; $cur = (string)($t['acc']['currency'] ?? ''); ?>
            <?php $hasCopy = isset($copies[(string)$t['acc']['id']]); ?><label class="flex items-center gap-2 rounded-xl border border-ink-200 px-3 py-2 text-sm <?= $hasCopy ? 'opacity-60' : 'cursor-pointer' ?> has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50"><input type="checkbox" value="<?= e($k) ?>" class="ab-mt" <?= $hasCopy ? 'disabled' : '' ?>><?php if ($hasCopy): ?><a class="text-[11px] link" href="<?= e(url('ad_builder', ['id' => $copies[(string)$t['acc']['id']]['id']])) ?>"><?= e(t('نسخة موجودة')) ?></a><?php endif; ?><span class="min-w-0 flex-1 truncate"><?= e((string)($t['acc']['name'] ?? $t['acc']['id'])) ?></span><span class="text-[11px] <?= $cur !== (string)$d['currency'] ? 'text-amber-700' : 'text-ink-400' ?>" dir="ltr"><?= e($cur) ?></span></label>
          <?php endforeach; ?>
        </div>
        <p class="text-[11px] text-amber-700"><?= e(t('الميزانية بتتنسخ بنفس الرقم بعملة كل حساب: راجعها لو العملة مختلفة.')) ?></p>
        <button type="button" class="btn-primary !text-sm" id="ab-mt-go"><?= e(t('اعمل النسخ')) ?></button>
        <div id="ab-mt-out" class="hidden space-y-2"></div>
        <script>window.AB_MT_EXIST = <?= json_encode($copyRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
      </div>
    </details>
    <?php endif; ?>
    <div id="ab-app" class="min-h-[60vh]" data-id="<?= (int)$build['id'] ?>"><div class="card p-8 text-center text-sm text-ink-500"><?= e(t('بيحمّل...')) ?></div></div>
    <script>window.AB = <?= json_encode(['mt' => ['partial' => t('توقفت في المنتصف: ستُستكمل'), 'badBudget' => t('ميزانية إحدى النسخ لم تُحفظ. صحّحها أولًا.'), 'pick' => t('اختار حساب واحد على الأقل.'), 'cur' => t('العملة %s: راجع الميزانية'), 'stripped' => t('اتشال الجمهور المخصص/النموذج'), 'ready' => t('جاهزة'), 'open' => t('افتح'), 'confirm' => t('أوافق على صرف ميزانية %s حملات على حساباتها'), 'launchAll' => t('أطلق الكل'), 'launching' => t('بيتطلق…'), 'launched' => t('اتطلقت'), 'failed' => t('فشل الإطلاق'), 'done' => t('خلصت كلها ✓'), 'doneErr' => t('خلصت، و%s فشلت: افتحها وصلّحها'), 'budget' => t('الميزانية'), 'saved' => t('اتحفظت'), 'skipped' => t('فيه نسخة بالفعل على: %s'), 'existing' => t('النسخ الموجودة'), 'launchedAlready' => t('اتطلقت قبل كده')], 
        'id' => (int)$build['id'], 'state' => $build['state'], 'progress' => $build['progress'], 'data' => $d,
        'caps' => ab_caps($d['platform']), 'plan' => ab_plan($d), 'campaignsUrl' => url('ads'), 'platformLabel' => ab_platform_label($d['platform']),
        'csrf' => csrf_token(), 'url' => url('ad_builder'), 'listUrl' => url('ad_builder'),
        'logoUrl' => ($abLg = function_exists('kb_logo') ? kb_logo((int)ws('id')) : null) ? url('media', ['file' => $abLg['id']]) : '', 'logoOpts' => ab_logo_opts($d),
        'imgProvider' => function_exists('img_provider') ? img_provider() : '',
        'geoUrl' => url('ad_create', ['ajax' => 1]), 'myCountry' => ws_country(), 'lang' => lang(),
        'canSpend' => can('ads.spend'), 'kbProducts' => array_values(array_filter(array_map(function ($r) { return trim((string)($r['name'] ?? '')); }, (array)(kb_get((int)ws('id'))['services']['rows'] ?? [])))),
        's' => ab_strings(),
        'cases' => array_values(array_filter((array)require __DIR__ . '/../data/ad_cases.php', function ($x) use ($d) { return $x['p'] === $d['platform']; })),
        'ai2' => ['intro' => t('اكتب المطلوب وهيشتغل كميديا باير وبيّاع وآرت دايركتور: استراتيجية، من 3 لـ 5 مجموعات بجماهير وأماكن مختلفة، وفي كل مجموعة من 3 لـ 7 إعلانات بزوايا وتصميمات مختلفة. هتراجع كل حاجة قبل ما تطبّق.'),
          'briefPh' => t('مثلًا: عاوزين عملاء شركات للأمن السيبراني في السعودية والإمارات، الخدمة: فحص اختراق وحماية'), 'markets' => t('الأسواق (اختياري)'), 'marketsPh' => t('السعودية، الإمارات، مصر'), 'nSets' => t('عدد المجموعات'), 'nAds' => t('الإعلانات في كل مجموعة'),
          'lang' => t('اللغة'), 'langs' => ['ar' => t('عربي'), 'en' => 'English', 'ar_en' => t('عربي + إنجليزي'), 'en_ar' => t('إنجليزي + عربي')],
          'dialect' => t('اللهجة'), 'dialects' => ['msa' => t('الفصحى'), 'saudi' => t('سعودية'), 'gulf' => t('خليجية'), 'emirati' => t('إماراتية'), 'kuwaiti' => t('كويتية'), 'egyptian' => t('مصرية'), 'levantine' => t('شامية'), 'maghrebi' => t('مغاربية'), 'white' => t('بيضاء'), 'other' => t('أخرى')], 'dialectOtherPh' => t('اكتب اللهجة أو الأسلوب'),
          'note' => t('الصور والفيديوهات مش بتتعمل هنا: كل إعلان بييجي معاه توجيه فني ووصف صورة جاهز تستخدمه في المصمم أو توليد الصور.'), 'planning' => t('بيحلل السوق ويبني الاستراتيجية والمجموعات…'), 'writing' => t('بيكتب إعلانات المجموعة %s من %s…'),
          'ready' => t('جاهز للمراجعة'), 'empty' => t('الرد رجع فاضي. جرّب تاني.'), 'warm' => t('دافي'), 'cold' => t('بارد'), 'summary' => t('%s مجموعة و%s إعلان'),
          'artT' => t('التوجيه الفني'), 'concept' => t('الفكرة'), 'composition' => t('التكوين'), 'palette' => t('الألوان'), 'overlay' => t('النص على التصميم'), 'prompt' => t('وصف الصورة لأداة التوليد'), 'copy' => t('نسخ'), 'copied' => t('اتنسخ ✓'), 'design' => t('صمّمه في المصمم'), 'designUrl' => url('designer'),
          'gen' => t('ولّد الصورة'), 'genCar' => t('ولّد الكاروسيل (%s صور)'), 'genVidBtn' => t('ولّد الفيديو'), 'genning' => t('جارٍ التوليد… قد يأخذ دقيقة'), 'genVidWait' => t('جارٍ توليد الفيديو… قد يأخذ عدة دقائق، سيبها مفتوحة'), 'genErr' => t('فشل التوليد: %s'), 'genVid' => t('توليد الفيديو من التكاملات (OpenRouter)'), 'regen' => t('أعد التوليد'), 'promptEdit' => t('وصف الصورة (قابل للتعديل)'),
          'logoT' => t('اللوجو على الصورة'), 'logoOn' => t('ركّب اللوجو'), 'logoPos' => t('المكان'), 'logoSize' => t('الحجم'), 'logoOp' => t('الشفافية'), 'logoTint' => t('اللون'), 'logoPlate' => t('خلفية اللوجو'), 'saved' => t('اتحفظ'), 'noLogoKb' => t('لا يوجد لوجو في ملف الشركة.'),
          'tOrig' => t('الأصلي'), 'tWhite' => t('أبيض'), 'tBlack' => t('أسود'), 'pNone' => t('بدون'), 'pLight' => t('فاتحة'), 'pDark' => t('داكنة')],
        'ux' => ['dCampaign' => t('الاسم بيظهر ليك بس، مش للعملاء.'), 'dGoal' => t('المنصة بتحسّن العرض على حسب الهدف ده.'), 'dBudget' => t('هتدفع كام وإزاي المنصة تصرفها.'), 'dSchedule' => t('إمتى تبدأ وتخلص، والفئات اللي ليها قواعد خاصة.'),
          'dAdset' => t('كل مجموعة = جمهور وميزانية. اعمل أكتر من واحدة عشان تقارن.'), 'dLoc' => t('الدول والمدن اللي الإعلان هيظهر فيها.'), 'dPeople' => t('السن والنوع والاهتمامات.'), 'dPlace' => t('الإعلان هيظهر فين جوه المنصة.'),
          'dAd' => t('اسم الإعلان وشكله.'), 'dMedia' => t('الصورة أو الفيديو أول حاجة الناس بتشوفها.'), 'dCopy' => t('أول سطر هو اللي بيوقف الناس. خليه قصير وواضح.'), 'dCta' => t('الزرار ورايح على فين.'), 'age' => t('السن'), 'month' => t('≈ %s في الشهر')],
        'px' => ['s1' => t('الحملة'), 's2' => t('الجمهور والميزانية'), 's3' => t('الإعلانات'), 's4' => t('المراجعة والإطلاق'), 'stepN' => t('خطوة %s'), 'nSets' => t('%s مجموعة'), 'nAds' => t('%s إعلان'), 'nErr' => t('%s محتاج تصحيح'), 'review' => t('جاهز للإطلاق؟'),
          'next' => t('التالي: %s'), 'live' => t('معاينة حية'), 'story' => t('ستوري'), 'feed' => t('الفيد'), 'sponsored' => t('ممول'), 'promoted' => t('مُروَّج'), 'seeMore' => t('عرض المزيد'), 'noHead' => t('العنوان هيظهر هنا'),
          'noImg' => t('ضيف صورة'), 'noVideo' => t('ضيف فيديو'), 'like' => t('أعجبني'), 'comment' => t('تعليق'), 'share' => t('مشاركة'), 'variant' => t('نسخة %s من %s'), 'looksGood' => t('الإعلان شكله كامل'),
          'cutBody' => t('النص أطول من %s حرف، هيتقص ويظهر "عرض المزيد".'), 'cutHead' => t('العنوان أطول من %s حرف وممكن يتقص.'), 'noLink' => t('مفيش رابط للزرار.')],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/ad-builder.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ad-builder-launch.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ad-builder-ai.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ad-builder-pro.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/ad-builder-ui.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php if (!empty($_GET['ai'])): ?><script>(function (n) { var t = setInterval(function () { var b = document.getElementById('ab-ai-build'); if (b) { clearInterval(t); b.click(); } else if (++n > 40) clearInterval(t); }, 150); })(0);</script><?php endif; ?>
    <?php
}, ['full' => true]);
