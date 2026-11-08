<?php
/**
 * قاعدة المعرفة: كل اللي الذكاء الاصطناعي يعرفه عن الشركة.
 * الأقسام بتتجمع في نص واحد بيتبعت مع الطلبات، والملفات بتتقسم أجزاء ونبعت المناسب للسؤال بس.
 */

/** الأقسام وشكل كل واحد */
function kb_groups(): array
{
    return ['info' => t('معلومات الشركة'), 'pos' => t('بيان التموضع')];
}

function kb_schema(): array
{
    return [
        // معلومات الشركة
        'company' => ['group' => 'info', 'label' => t('عن الشركة'), 'hint' => t('هوية الشركة بدقة: الاسم، النشاط، الفروع، الدفع والتوصيل.'), 'type' => 'fields', 'fields' => [
            'name' => [t('اسم الشركة'), 'text', ''], 'industry' => [t('المجال / النشاط'), 'text', t('مثال: مطعم بيتزا، وكالة تسويق، عيادة أسنان')],
            'tagline' => [t('وصف في سطر واحد'), 'text', t('ماذا تقدم ولمن، في جملة واحدة'), 2],
            'about' => [t('نبذة عن الشركة'), 'area', t('التأسيس، الخبرة، الفريق، أهم الإنجازات'), 2],
            'branches' => [t('العنوان والفروع'), 'area', t('فرع واحد في كل سطر. مثال: %s', cx('city') . ' - ' . cx('area'))], 'service_area' => [t('نطاق الخدمة'), 'area', t('المدن أو المناطق التي تخدمها، أو "أونلاين"')],
            'payment' => [t('طرق الدفع'), 'text', t('مثال: %s', cx('pay'))], 'delivery' => [t('التوصيل والشحن'), 'text', t('المناطق والمدة. بدون أسعار غير مؤكدة')],
        ]],
        'contact' => ['group' => 'info', 'label' => t('الموقع وبيانات التواصل'), 'hint' => t('القنوات الرسمية فقط. الذكاء الاصطناعي يعطيها للعملاء كما هي بالضبط.'), 'type' => 'fields', 'fields' => [
            'website' => [t('الموقع الإلكتروني'), 'url', 'https://'], 'mail' => [t('البريد الإلكتروني'), 'email', ''],
            'phone' => [t('رقم الهاتف'), 'tel', cx_phone()], 'whatsapp' => [t('رقم واتساب'), 'tel', cx_phone()],
            'maps' => [t('رابط الموقع على الخريطة'), 'url', 'https://maps.google.com/...', 2],
            'social' => [t('حسابات التواصل الاجتماعي'), 'area', t('رابط واحد في كل سطر'), 2],
        ]],
        'hours' => ['group' => 'info', 'label' => t('ساعات العمل'), 'hint' => t('اكتب المواعيد لكل يوم، أو "مغلق". الذكاء الاصطناعي لن يذكر مواعيد غير مكتوبة هنا.'), 'type' => 'fields', 'fields' => [
            'sat' => [t('السبت'), 'text', '10:00 - 22:00'], 'sun' => [t('الأحد'), 'text', ''], 'mon' => [t('الاثنين'), 'text', ''], 'tue' => [t('الثلاثاء'), 'text', ''],
            'wed' => [t('الأربعاء'), 'text', ''], 'thu' => [t('الخميس'), 'text', ''], 'fri' => [t('الجمعة'), 'text', t('مغلق')], 'notes' => [t('ملاحظات وإجازات'), 'area', t('الإجازات الرسمية، مواعيد رمضان...'), 2],
        ]],
        'services' => ['group' => 'info', 'label' => t('الخدمات والمنتجات'), 'hint' => t('كل خدمة أو منتج بسعره ووصف مختصر. الذكاء الاصطناعي لن يذكر سعرًا غير موجود هنا.'), 'type' => 'rows', 'cols' => ['name' => t('الاسم'), 'price' => t('السعر'), 'details' => t('التفاصيل')]],
        'faq' => ['group' => 'info', 'label' => t('الأسئلة الشائعة'), 'hint' => t('الأسئلة اللي العملاء بيسألوها كتير وإجابتها المعتمدة.'), 'type' => 'rows', 'cols' => ['q' => t('السؤال'), 'a' => t('الإجابة')]],
        'tone' => ['group' => 'info', 'label' => t('نبرة الكلام'), 'hint' => t('إزاي الشركة بتتكلم مع عملائها.'), 'type' => 'tone'],
        'focus' => ['group' => 'info', 'label' => t('التركيز الحالي'), 'hint' => t('إيه اللي عايز تروّج له دلوقتي: عرض، منتج جديد، هدف الشهر.'), 'type' => 'text'],
        'never_say' => ['group' => 'info', 'label' => t('ممنوع قوله'), 'hint' => t('كلمات أو مواضيع أو وعود ممنوع الذكاء الاصطناعي يذكرها نهائيًا. كل واحدة في سطر.'), 'type' => 'list'],
        'anti_hype' => ['group' => 'info', 'label' => t('ضد المبالغة'), 'hint' => t('قواعد تمنع الكلام المبالغ فيه والوعود اللي مش هتتحقق.'), 'type' => 'antihype'],
        'visual_style' => ['group' => 'info', 'label' => t('الهوية البصرية'), 'hint' => t('ألوان وستايل الصور. بيستخدمها توليد الصور تلقائيًا.'), 'type' => 'visual'],
        'escalation' => ['group' => 'info', 'label' => t('التحويل لموظف'), 'hint' => t('إمتى الذكاء الاصطناعي يوقف ويحوّل المحادثة لموظف، ولمين يبعت إيميل.'), 'type' => 'escalation'],
        // بيان التموضع (Positioning Statement)
        'audience' => ['group' => 'pos', 'nav' => t('الجمهور ومشكلته'), 'label' => t('الجمهور المستهدف ومشكلته'), 'type' => 'fields',
            'hint' => t('مين بالظبط العميل اللي بتخدمه، وإيه المشكلة أو الاحتياج اللي بيعاني منه وبيخليه يدوّر على حل. كل ما كان أدق كان أقوى.'), 'fields' => [
            'who' => [t('الجمهور المستهدف'), 'area', t('مثال: أمهات عاملات 28-40 سنة في %s', cx('city') . ' (' . cx('area') . ')'), 2],
            'traits' => [t('خصائصهم وسلوكهم'), 'area', t('الدخل، الاهتمامات، أين يقضون وقتهم، كيف يشترون')],
            'problem' => [t('المشكلة أو الاحتياج'), 'area', t('مثال: لا وقت لطهي طعام صحي لأطفالهن في أيام العمل')],
            'objections' => [t('مخاوفهم واعتراضاتهم قبل الشراء'), 'area', t('السعر، الثقة، الجودة، الوقت...'), 2],
        ]],
        'frame_ref' => ['group' => 'pos', 'nav' => t('الفئة'), 'label' => t('الفئة (Frame of Reference)'), 'type' => 'fields',
            'hint' => t('منتجك أو خدمتك بتنتمي لأنهي فئة في دماغ العميل؟ دي الفئة اللي هيقارنك فيها بالمنافسين.'), 'fields' => [
            'category' => [t('الفئة'), 'text', t('مثال: خدمة اشتراك وجبات صحية جاهزة للمنازل'), 2],
            'competitors' => [t('المنافسون في نفس الفئة'), 'area', t('منافس أو نوع منافس في كل سطر'), 2],
        ]],
        'pod' => ['group' => 'pos', 'nav' => t('نقطة الاختلاف'), 'label' => t('نقطة الاختلاف (Point of Difference)'), 'type' => 'text',
            'hint' => t('الفرق الجوهري بينك وبين المنافسين في نفس الفئة. حاجة العميل يهتم بيها، ومش موجودة عندهم أو مش بنفس المستوى.'),
            'ph' => t("مثال:\nالوجبات بتتعمل حسب عمر كل طفل واحتياجه الغذائي بإشراف أخصائية تغذية، مش منيو ثابت للكل")],
        'rtb' => ['group' => 'pos', 'nav' => t('سبب التصديق'), 'label' => t('سبب التصديق (Reason to Believe)'), 'type' => 'text',
            'hint' => t('ليه العميل هيصدقك لما تقوله إنك أحسن من المنافس؟ دليل حقيقي: أرقام، شهادات، تجربة، ضمان، آراء عملاء.'),
            'ph' => t("مثال:\nأخصائية تغذية معتمدة في الفريق\n+1200 أسرة مشتركة من 2022\nتقدر توقف أو تغيّر الاشتراك في أي وقت")],
        'pos_offers' => ['group' => 'pos', 'nav' => t('لكل منتج أو خدمة'), 'label' => t('تموضع كل منتج أو خدمة'), 'type' => 'rows',
            'hint' => t('كل خدمة في "الخدمات والمنتجات" لها بطاقة هنا تلقائيًا، لأن لكل منتج جمهورًا ومشكلة وفرقًا مختلفًا. الأقسام السابقة هي تموضع الشركة ككل.'),
            'cols' => ['offer' => t('المنتج أو الخدمة'), 'audience' => t('الجمهور المستهدف'), 'problem' => t('المشكلة أو الاحتياج'), 'frame' => t('الفئة والمنافسين'), 'pod' => t('نقطة الاختلاف'), 'rtb' => t('سبب التصديق')]],
    ];
}

function kb_get(int $wid): array
{
    static $cache = [];
    if (isset($cache[$wid])) return $cache[$wid];
    $out = [];
    try {
        $st = db()->prepare("SELECT section, data FROM kb_sections WHERE workspace_id = ?");
        $st->execute([$wid]);
        foreach ($st->fetchAll() as $r) $out[$r['section']] = json_decode($r['data'], true);
    } catch (Throwable $e) {
    }
    // الإصدار القديم: نص واحد في الإعدادات
    if (empty($out['company']['text']) && ($old = trim((string)setting('ai_kb_' . $wid, ''))) !== '') $out['company'] = ['text' => $old];
    foreach (['company' => 'about', 'audience' => 'who', 'frame_ref' => 'category'] as $sk => $fk) if (isset($out[$sk]['text']) && !isset($out[$sk][$fk])) { $out[$sk][$fk] = $out[$sk]['text']; unset($out[$sk]['text']); }
    return $cache[$wid] = $out;
}

function kb_save(int $wid, string $section, array $data): void
{
    db()->prepare("INSERT INTO kb_sections (workspace_id, section, data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data)")
        ->execute([$wid, $section, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

/** القسم ده متعبّي؟ */
function kb_filled(array $sec, string $type): bool
{
    switch ($type) {
        case 'fields': return trim(implode('', array_map('strval', array_filter((array)$sec, 'is_scalar')))) !== '';
        case 'rows': return !empty(array_filter((array)($sec['rows'] ?? []), function ($r) { return trim(implode('', (array)$r)) !== ''; }));
        case 'list': return trim((string)($sec['text'] ?? '')) !== '';
        case 'tone': return !empty($sec['style']) || trim((string)($sec['notes'] ?? '')) !== '';
        case 'antihype': return !empty($sec['rules']) || trim((string)($sec['words'] ?? '')) !== '';
        case 'visual': return trim((string)($sec['colors'] ?? '') . ($sec['style'] ?? '')) !== '';
        case 'escalation': return trim((string)($sec['email'] ?? '')) !== '';
        default: return trim((string)($sec['text'] ?? '')) !== '';
    }
}

function kb_tone_options(): array
{
    return ['friendly' => t('ودود وقريب'), 'professional' => t('رسمي ومحترف'), 'warm' => t('دافئ ومطمئن'), 'energetic' => t('حماسي'), 'premium' => t('فخم وراقي'), 'playful' => t('خفيف ومرح')];
}

function kb_lang_options(): array
{
    $loc = (string)cx('dialect'); return ($loc !== '' ? ['ar_local' => t('اللهجة %s', $loc)] : []) + ['ar_msa' => t('العربية الفصحى'), 'ar_eg' => t('العامية المصرية'), 'ar_gulf' => t('الخليجية'), 'ar_levant' => t('الشامية'), 'ar_maghreb' => t('المغاربية'),
        'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'es' => 'Español', 'it' => 'Italiano', 'tr' => 'Türkçe', 'ru' => 'Русский', 'zh' => '中文', 'ur' => 'اردو', 'hi' => 'हिन्दी'];
}

function kb_dialect_options(): array
{
    return ['match' => t('نفس لهجة العميل'), 'msa' => t('عربي فصحى مبسطة'), 'egyptian' => t('مصري'), 'gulf' => t('خليجي'), 'levant' => t('شامي'), 'english' => t('English')];
}

function kb_antihype_rules(): array
{
    return [
        'no_superlatives' => t('ممنوع "الأفضل" و"رقم 1" و"الأرخص" إلا لو فيه دليل مكتوب هنا'),
        'no_guarantees' => t('ممنوع ضمانات أو نتائج مضمونة غير مكتوبة في السياسات'),
        'no_fake_urgency' => t('ممنوع استعجال مزيف ("آخر قطعة"، "العرض بينتهي النهارده") إلا لو حقيقي'),
        'no_invent_numbers' => t('ممنوع أرقام أو إحصائيات أو آراء عملاء مش موجودة هنا'),
        'emoji_light' => t('إيموجي قليل (واحد أو اتنين بالكتير)'),
    ];
}

/** بيجمع كل الأقسام في نص واحد للذكاء الاصطناعي، ومعاه أجزاء الملفات المناسبة للسؤال */
/** أعلى صفوف تطابقًا مع السؤال، مع الحفاظ على ترتيبها الأصلي */
function kb_top(array $rows, string $query, int $n, callable $text): array
{
    if (count($rows) <= $n || $query === '') return array_slice(array_values($rows), 0, $n);
    $pf = function ($t) { $o = []; foreach (kb_terms($t) as $w) $o[mb_substr($w, 0, 5)] = 1; return $o; }; $q = $pf($query); $sc = [];
    foreach (array_values($rows) as $i => $r) $sc[$i] = count(array_intersect_key($pf($text($r)), $q)) * 100 - $i;
    arsort($sc); $keep = array_slice(array_keys($sc), 0, $n); sort($keep);
    $rows = array_values($rows); return array_map(function ($i) use ($rows) { return $rows[$i]; }, $keep);
}

/** تصنيف السؤال لأقسام قاعدة المعرفة بالكلمات (بدون استدعاء ذكاء = بدون رصيد). [] = مش واضح → كل الأقسام المختصرة */
function kb_intent(string $q): array
{
    $q = mb_strtolower(strtr($q, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']));
    $map = [
        'services' => '/(سعر|اسعار|بكام|بكم|كام|تكلف|تكلفه|ثمن|خدم|باق|عرض|عروض|خصم|منتج|متوفر|موجود عندكم|انواع|منيو|قائمه|price|cost|how much|service|package|offer|discount|product|menu|available)/u',
        'hours' => '/(مواعيد|ميعاد|الساعه كام|امتي|متي|تفتح|تقفل|تغلق|مفتوح|دوام|اجاز|عطل|الجمعه|السبت|hours|open|close|when|holiday|friday)/u',
        'contact' => '/(عنوان|مكان|موقع|فين|وين|فرع|فروع|لوكيشن|خريطه|رقم|تليفون|هاتف|واتس|ايميل|تواصل|اتصل|حجز|احجز|موعد|address|location|where|branch|map|phone|number|whatsapp|email|contact|book|appointment)/u',
        'policy' => '/(توصيل|شحن|دفع|فيزا|كاش|تقسيط|استرجاع|استرداد|ارجاع|استبدال|الغاء|الغي|ضمان|سياس|شروط|مده|يوصل|delivery|shipping|pay|card|cash|install|refund|return|exchange|cancel|warranty|policy|terms)/u',
    ];
    $o = []; foreach ($map as $k => $re) if (preg_match($re, $q)) $o[] = $k;
    return $o;
}

/** $lean = نسخة مختصرة للرد على العملاء: من غير التموضع والهوية البصرية، وأنسب الأسئلة والخدمات بس */
function kb_compile(int $wid, string $query = '', bool $lean = false): string
{
    $k = kb_get($wid);
    $out = [];
    $sch = kb_schema();
    $fields = function ($sec) use ($k, $sch) { $l = []; foreach ($sch[$sec]['fields'] as $fk => $f) if (($v = trim((string)($k[$sec][$fk] ?? ''))) !== '') $l[] = $f[0] . ': ' . (strpos($v, "\n") !== false ? "\n  " . str_replace("\n", "\n  ", $v) : $v); return implode("\n", $l); };
    // الرد الذكي: نصنّف السؤال الأول وبعدين نبحث جوه الأقسام دي بس
    $in = $lean ? kb_intent($query) : [];
    if ($in) {
        $has = ['services' => !empty($k['services']['rows']), 'hours' => $fields('hours') !== '', 'contact' => $fields('contact') !== '', 'policy' => !empty($k['faq']['rows'])];
        foreach ($in as $x) if (!$has[$x]) { $in = []; break; } // القسم فاضي: الإجابة ممكن تكون في مكان تاني، فنرجع للبحث العام
    }
    $want = function ($sec) use ($in) { return !$in || in_array($sec, $in, true); };
    if ($v = $fields('company')) $out[] = "## COMPANY\n" . $v;
    if ($want('contact') && $v = $fields('contact')) $out[] = "## CONTACT (give exactly as written; never invent other channels)\n" . $v;
    if (($want('hours') || $want('contact')) && $v = $fields('hours')) $out[] = "## WORKING HOURS (only these; never guess other times)\n" . $v;
    $rows = array_filter((array)($k['services']['rows'] ?? []), function ($r) { return trim((string)($r['name'] ?? '')) !== ''; });
    // «إيه خدماتكم/أسعاركم؟» = محتاج القائمة كلها
    if ($lean && !preg_match('/(خدمات|اسعار|أسعار|قائم|منيو|كل الباقات|الباقات|services|prices|price list|menu|packages)/iu', $query)) $rows = kb_top($rows, $query, 10, function ($r) { return ($r['name'] ?? '') . ' ' . ($r['desc'] ?? $r['details'] ?? ''); });
    if (!$want('services')) $rows = [];
    if ($rows) $out[] = "## SERVICES & PRICES (only these prices are valid)\n" . implode("\n", array_map(function ($r) { return '- ' . trim($r['name']) . (trim((string)($r['price'] ?? '')) !== '' ? ' — ' . trim($r['price']) : '') . (trim((string)($r['details'] ?? '')) !== '' ? ': ' . trim($r['details']) : ''); }, $rows));
    $faq = array_filter((array)($k['faq']['rows'] ?? []), function ($r) { return trim((string)($r['q'] ?? '')) !== '' && trim((string)($r['a'] ?? '')) !== ''; });
    if ($lean) $faq = kb_top($faq, $query, $in && !in_array('policy', $in, true) ? 3 : 6, function ($r) { return $r['q'] . ' ' . $r['a']; });
    if ($faq) $out[] = "## FAQ (approved answers)\n" . implode("\n", array_map(function ($r) { return 'Q: ' . trim($r['q']) . "\nA: " . trim($r['a']); }, $faq));
    $tone = $k['tone'] ?? [];
    if (!empty($tone['style']) || !empty($tone['dialect']) || trim((string)($tone['notes'] ?? '')) !== '') {
        $styles = array_intersect_key(kb_tone_options(), array_flip((array)($tone['style'] ?? [])));
        $out[] = "## TONE\n" . ($styles ? 'Style: ' . implode(', ', $styles) . "\n" : '') . (!empty($tone['dialect']) ? 'Language/dialect: ' . (kb_dialect_options()[$tone['dialect']] ?? $tone['dialect']) . "\n" : '')
            . (!empty($tone['langs']) ? 'Supported languages: ' . implode(', ', array_intersect_key(kb_lang_options(), array_flip((array)$tone['langs']))) . '. ' . (($tone['lang_rule'] ?? 'match') === 'match' ? 'Reply in the language the customer writes in if it is one of these; otherwise use the default language/dialect.' : 'Always reply in the default language/dialect, even if the customer writes in another language.') . "\n" : '')
            . (isset($tone['emoji']) ? 'Emoji: ' . ['none' => 'never', 'light' => 'at most 1-2', 'normal' => 'normal'][$tone['emoji']] . "\n" : '') . trim((string)($tone['notes'] ?? ''));
    }
    $pos = [];
    if (!$lean) {
    if ($v = $fields('audience')) $pos[] = "### Target audience & their problem\n" . $v;
    if ($v = $fields('frame_ref')) $pos[] = "### Frame of reference (category we compete in)\n" . $v;
    foreach (['pod' => 'Point of difference vs competitors', 'rtb' => 'Reasons to believe (proof)'] as $pk => $pl) if ($v = trim((string)($k[$pk]['text'] ?? ''))) $pos[] = "### " . $pl . "\n" . $v;
    $po = array_filter((array)($k['pos_offers']['rows'] ?? []), function ($r) { return trim((string)($r['offer'] ?? '')) !== ''; });
    if ($po) $pos[] = "### Per product/service positioning\n" . implode("\n", array_map(function ($r) { $l = '- ' . trim($r['offer']) . ':'; foreach (['audience' => 'audience', 'problem' => 'problem', 'frame' => 'category', 'pod' => 'difference', 'rtb' => 'proof'] as $c => $n) if (trim((string)($r[$c] ?? '')) !== '') $l .= ' ' . $n . ': ' . trim(preg_replace('/\s+/', ' ', $r[$c])) . ';'; return rtrim($l, ';'); }, $po));
    }
    if ($pos) $out[] = "## POSITIONING STATEMENT (use it to frame strategy, content and replies; never overclaim beyond the proof)\n" . implode("\n", $pos);
    if ((!$in || $want('services')) && $v = trim((string)($k['focus']['text'] ?? ''))) $out[] = "## CURRENT FOCUS (what to promote now)\n" . ($lean ? mb_substr($v, 0, 300) : $v);
    if ($v = trim((string)($k['never_say']['text'] ?? ''))) $out[] = "## NEVER SAY (strictly forbidden words, topics and promises)\n" . implode("\n", array_map(function ($l) { return '- ' . trim($l); }, array_filter(explode("\n", $v), 'trim')));
    $ah = $k['anti_hype'] ?? [];
    $rules = array_intersect_key(kb_antihype_rules(), array_flip((array)($ah['rules'] ?? [])));
    if ($rules || trim((string)($ah['words'] ?? '')) !== '') $out[] = "## ANTI-HYPE RULES\n" . implode("\n", array_map(function ($r) { return '- ' . $r; }, $rules)) . (trim((string)($ah['words'] ?? '')) !== '' ? "\n- Never use these words: " . trim($ah['words']) : '');
    $vs = $k['visual_style'] ?? [];
    if (!$lean && trim((string)($vs['colors'] ?? '') . ($vs['style'] ?? '')) !== '') $out[] = "## VISUAL STYLE\nColors: " . trim((string)($vs['colors'] ?? '')) . "\nStyle: " . trim((string)($vs['style'] ?? ''));
    $txt = implode("\n\n", $out);
    // الملفات: أنسب أجزاء للسؤال (أو أول أجزاء لو مفيش سؤال)
    // المواعيد/التواصل/الأسعار متسجلة في أقسامها، فمش محتاجين ملفات؛ السياسات والأسئلة الغامضة بس
    $chunks = $in && !in_array('policy', $in, true) ? [] : kb_relevant_chunks($wid, $query, $lean ? 2 : ($query !== '' ? 5 : 3));
    if ($chunks) $txt .= "\n\n## FROM COMPANY DOCUMENTS\n" . implode("\n---\n", $chunks);
    return mb_substr($txt, 0, $lean ? 6000 : 14000);
}

/** بيقسم النص لأجزاء ~900 حرف على حدود الفقرات */
function kb_chunks(string $text, int $size = 900): array
{
    $paras = preg_split("/\n\s*\n/u", $text) ?: [];
    $out = []; $cur = '';
    foreach ($paras as $p) {
        $p = trim($p); if ($p === '') continue;
        if (mb_strlen($cur) + mb_strlen($p) > $size && $cur !== '') { $out[] = $cur; $cur = ''; }
        while (mb_strlen($p) > $size) { $out[] = mb_substr($p, 0, $size); $p = mb_substr($p, $size); }
        $cur = $cur === '' ? $p : $cur . "\n" . $p;
    }
    if ($cur !== '') $out[] = $cur;
    return $out;
}

/** كلمات للبحث: بنشيل التشكيل والأل والكلمات الصغيرة */
function kb_terms(string $s): array
{
    $s = mb_strtolower(preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s));
    $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
    preg_match_all('/[\p{L}\p{N}]{3,}/u', $s, $m);
    return array_values(array_unique(array_map(function ($w) { return preg_replace('/^(ال|وال|بال|لل)(?=\p{L}{3,})/u', '', $w); }, $m[0])));
}

/** أنسب أجزاء الملفات لسؤال معين (بحث بالكلمات المشتركة) */
function kb_relevant_chunks(int $wid, string $query, int $limit = 5): array
{
    try {
        $st = db()->prepare("SELECT name, content FROM kb_files WHERE workspace_id = ? AND enabled = 1 ORDER BY id");
        $st->execute([$wid]);
        $files = $st->fetchAll();
    } catch (Throwable $e) { return []; }
    if (!$files) return [];
    $q = kb_terms($query);
    $scored = [];
    foreach ($files as $f) {
        foreach (kb_chunks((string)$f['content']) as $i => $c) {
            $score = 0;
            if ($q) { $ct = kb_terms($c); foreach ($q as $w) foreach ($ct as $x) if ($x === $w || (mb_strlen($w) > 4 && mb_strpos($x, $w) === 0)) { $score++; break; } }
            else $score = $i === 0 ? 1 : 0;
            if ($score > 0) $scored[] = [$score, '[' . $f['name'] . "]\n" . $c];
        }
    }
    usort($scored, function ($a, $b) { return $b[0] <=> $a[0]; });
    return array_column(array_slice($scored, 0, $limit), 1);
}

/* ---------- تحويل الملفات لنص ---------- */

/**
 * بيرجع [النص, الطريقة] أو بيرمي Exception.
 * $client = النص اللي المتصفح طلّعه (PDF.js / OCR)، بيتستخدم قبل الذكاء الاصطناعي عشان نوفّر.
 */
function kb_extract(string $path, string $name, string $mime, ?array $client = null): array
{
    $client = $client && mb_strlen(preg_replace('/\s+/u', '', (string)$client['text'])) >= 15 ? ['text' => kb_clean((string)$client['text']), 'method' => preg_replace('/[^a-z+]/', '', (string)$client['method']) ?: 'browser'] : null;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, ['txt', 'md', 'csv', 'tsv', 'json'], true)) return [kb_clean((string)file_get_contents($path)), 'text'];
    if (in_array($ext, ['html', 'htm'], true)) return [kb_clean(html_entity_decode(strip_tags(preg_replace('#<(script|style)[^>]*>.*?</\1>#si', '', (string)file_get_contents($path))), ENT_QUOTES, 'UTF-8')), 'html'];
    if (in_array($ext, ['docx', 'xlsx', 'pptx'], true)) return [kb_office($path, $ext), $ext];
    if ($ext === 'pdf') return kb_pdf($path, $name, $client);
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'bmp'], true)) {
        if ($client) return [$client['text'], $client['method']]; // OCR في المتصفح: من غير تكلفة
        return [kb_image($path, $mime), 'vision'];
    }
    throw new Exception(t('نوع الملف غير مدعوم: %s', $ext));
}

function kb_clean(string $s): string
{
    if (!mb_check_encoding($s, 'UTF-8')) $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1256');
    $s = preg_replace("/^\xEF\xBB\xBF/", '', $s);
    // أشكال الحروف العربية المرسومة (من الـ PDF) لحروف عادية، عشان البحث يلاقيها
    if (class_exists('Normalizer')) $s = Normalizer::normalize($s, Normalizer::FORM_KC) ?: $s;
    // علامات الاتجاه المخفية اللي pdftotext بيحطها
    $s = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $s);
    $s = preg_replace("/[ \t]+/u", ' ', str_replace("\r", '', $s));
    return trim(preg_replace("/\n{3,}/", "\n\n", $s));
}

/** Word / Excel / PowerPoint: كلها ZIP فيه XML */
function kb_office(string $path, string $ext): string
{
    $z = new ZipArchive();
    if ($z->open($path) !== true) throw new Exception(t('الملف تالف أو غير مقروء.'));
    $parts = [];
    if ($ext === 'docx') {
        $xml = (string)$z->getFromName('word/document.xml');
        $xml = preg_replace(['#</w:p>#', '#<w:tab/>#', '#<w:br[^>]*/>#'], ["\n", "\t", "\n"], $xml);
        $parts[] = strip_tags($xml);
    } elseif ($ext === 'pptx') {
        for ($i = 1; $i <= 300; $i++) {
            $x = $z->getFromName("ppt/slides/slide$i.xml"); if ($x === false) break;
            $parts[] = '# ' . $i . "\n" . strip_tags(preg_replace('#</a:p>#', "\n", $x));
        }
    } else {
        $shared = [];
        if (($ss = $z->getFromName('xl/sharedStrings.xml')) !== false) {
            preg_match_all('#<si>(.*?)</si>#s', $ss, $m);
            foreach ($m[1] as $si) $shared[] = html_entity_decode(strip_tags($si), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        for ($i = 1; $i <= 50; $i++) {
            $x = $z->getFromName("xl/worksheets/sheet$i.xml"); if ($x === false) break;
            preg_match_all('#<row[^>]*>(.*?)</row>#s', $x, $rows);
            foreach ($rows[1] as $row) {
                preg_match_all('#<c([^>]*)>(?:<f>.*?</f>)?(?:<v>(.*?)</v>|<is>(.*?)</is>)?</c>#s', $row, $cells, PREG_SET_ORDER);
                $vals = [];
                foreach ($cells as $c) {
                    $v = $c[2] ?? ''; if (!empty($c[3])) $v = strip_tags($c[3]);
                    if (strpos($c[1], 't="s"') !== false) $v = $shared[(int)$v] ?? '';
                    if (trim((string)$v) !== '') $vals[] = trim((string)$v);
                }
                if ($vals) $parts[] = implode(' | ', $vals);
            }
        }
    }
    $z->close();
    $t = kb_clean(html_entity_decode(implode("\n", $parts), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    if ($t === '') throw new Exception(t('لم يتم العثور على نص في الملف.'));
    return $t;
}

/**
 * PDF بالترتيب ده (الأرخص الأول):
 * 1) pdftotext على السيرفر لو موجود  2) النص اللي المتصفح طلّعه (PDF.js + OCR للصفحات الممسوحة)
 * 3) smalot/pdfparser (PHP) للملفات غير العربي  4) Claude  5) قارئ بسيط
 */
function kb_pdf(string $path, string $name, ?array $client = null): array
{
    if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
        $bin = trim((string)@shell_exec('command -v pdftotext 2>/dev/null'));
        if ($bin !== '') {
            $t = kb_clean((string)@shell_exec(escapeshellarg($bin) . ' -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null'));
            if (mb_strlen(preg_replace('/\s+/u', '', $t)) >= 8) return [$t, 'pdftotext'];
        }
    }
    if ($client) return [$client['text'], $client['method']];
    // smalot: ممتاز للإنجليزي، لكن بيعكس العربي ويلخبط المسافات، فبنستخدمه بس لو النص مش عربي
    if (is_file(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        try {
            $t = kb_clean((string)(new \Smalot\PdfParser\Parser())->parseFile($path)->getText());
            $letters = preg_match_all('/\p{L}/u', $t); $arabic = preg_match_all('/\p{Arabic}/u', $t);
            if ($letters >= 8 && $arabic / max(1, $letters) < 0.2) return [$t, 'pdfparser'];
        } catch (Throwable $e) {
        }
    }
    $an = connector('anthropic');
    if ($an && empty($an['settings']['disabled']) && filesize($path) < 20 * 1048576) {
        $model = (string)($an['settings']['model'] ?? '') ?: ai_default_model($an['settings']['models'] ?? []);
        $r = ai_req('anthropic', 'POST', '/messages', $an['credentials']['api_key'], ['model' => $model, 'max_tokens' => 8000, 'messages' => [['role' => 'user', 'content' => [
            ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode((string)file_get_contents($path))]],
            ['type' => 'text', 'text' => 'Extract ALL the text of this document faithfully, in its original language. Keep headings, lists and tables (as "a | b" rows). No commentary.'],
        ]]]], 180);
        $t = ''; foreach ($r['content'] ?? [] as $b) if (($b['type'] ?? '') === 'text') $t .= $b['text'];
        ai_log_usage((int)ws('id'), 'kb', 'anthropic', (string)($r['model'] ?? $model), (array)($r['usage'] ?? []));
        if (trim($t) !== '') return [kb_clean($t), 'claude'];
    }
    // قارئ بسيط: بيشتغل مع ملفات PDF النصية الإنجليزي، وغالبًا مش بيشتغل مع العربي
    $raw = (string)file_get_contents($path); $out = '';
    preg_match_all('#stream\r?\n(.*?)\r?\nendstream#s', $raw, $m);
    foreach ($m[1] as $s) {
        $d = @gzuncompress($s); if ($d === false) $d = @gzinflate($s); if ($d === false) $d = $s;
        preg_match_all('#\[(.*?)\]\s*TJ|\((.*?)(?<!\\\\)\)\s*Tj#s', $d, $tm, PREG_SET_ORDER);
        foreach ($tm as $x) {
            if ($x[1] !== '') { preg_match_all('#\((.*?)(?<!\\\\)\)#s', $x[1], $pp); $out .= implode('', $pp[1]); } else $out .= $x[2];
            $out .= ' ';
        }
        $out .= "\n";
    }
    $out = kb_clean(stripcslashes($out));
    if (mb_strlen(preg_replace('/\s+/', '', $out)) < 8) throw new Exception(t('تعذر قراءة الـ PDF. اربط Anthropic (Claude) ليقرأه، أو ارفعه كملف Word أو نص.'));
    return [$out, 'basic'];
}

/** صورة: موديل بيفهم الصور يطلع النص اللي فيها */
function kb_image(string $path, string $mime): string
{
    if (filesize($path) > 4 * 1048576) throw new Exception(t('الصورة أكبر من 4 ميجا.'));
    $r = ai_chat([['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Extract all text in this image faithfully (original language). If it is a price list or menu, keep each item with its price on its own line. If there is no text, describe the image in 2 lines.'], ['type' => 'image_url', 'image_url' => ['url' => 'data:' . ($mime ?: 'image/jpeg') . ';base64,' . base64_encode((string)file_get_contents($path))]]]]], 3000, 'analyze');
    return kb_clean($r['text']);
}

/* ---------- الرد أو التحويل لموظف ---------- */

function kb_escalation(int $wid): array
{
    $e = kb_get($wid)['escalation'] ?? [];
    return ['email' => trim((string)($e['email'] ?? '')), 'triggers' => (array)($e['triggers'] ?? ['angry', 'refund', 'complaint', 'human']), 'keywords' => trim((string)($e['keywords'] ?? '')), 'message' => trim((string)($e['message'] ?? ''))];
}

function kb_trigger_options(): array
{
    return ['angry' => t('العميل غاضب أو بيشتم'), 'complaint' => t('شكوى من منتج أو خدمة'), 'refund' => t('طلب استرجاع أو استبدال أو فلوس'), 'human' => t('طلب يكلم موظف'), 'order_issue' => t('مشكلة في طلب أو شحنة'), 'unknown' => t('سؤال مش موجود في قاعدة المعرفة'), 'legal' => t('تهديد قانوني أو موضوع حساس')];
}

/**
 * قرار الرد: يرد ولا يحوّل لموظف. بيرجع: action (reply|handoff), reply, reason, confidence
 * ده اللي الرد التلقائي على الرسايل والتعليقات هيشتغل بيه.
 */
function kb_decide(int $wid, string $message, string $channel = 'message', string $history = '', string $style = '', string $task = 'reply'): array
{
    $esc = kb_escalation($wid);
    $trig = array_intersect_key(kb_trigger_options(), array_flip($esc['triggers']));
    // كلمات بتحوّل فورًا من غير ما نسأل الموديل
    foreach (array_filter(array_map('trim', preg_split('/[,،\n]/u', $esc['keywords']))) as $kw) {
        if ($kw !== '' && mb_stripos($message, $kw) !== false) return ['action' => 'handoff', 'reply' => $esc['message'], 'reason' => t('فيها كلمة تحويل: %s', $kw), 'confidence' => 1, 'model' => '', 'cost' => 0];
    }
    $sys = 'You are the customer service AI for "' . ws('name') . '". Decide whether to answer this customer ' . ($channel === 'comment' ? 'public comment' : 'private message') . ' yourself or hand it off to a human employee.' . "\n"
        . 'Hand off when ANY of these is true: ' . implode('; ', $trig) . ($trig ? '' : 'never') . '. Also hand off if answering would require facts not in the knowledge base.' . "\n"
        . 'If you answer: follow TONE, NEVER SAY and ANTI-HYPE rules, use only knowledge-base facts, keep it short' . ($channel === 'comment' ? ' and public-safe (no personal details; invite to DM for specifics)' : '') . '.' . "\n"
        . 'Return ONLY compact JSON: {"action":"reply"|"handoff","reply":"' . ($esc['message'] !== '' ? 'the reply text (empty string if handoff)' : 'the reply text (if handoff: one short polite line saying a team member will follow up)') . '","reason":"max 6 words in ' . (lang() === 'ar' ? 'Arabic' : 'English') . '"}';
    if ($style !== '') $sys .= "\nREPLY STYLE: " . $style;
    $user = ($history !== '' ? "Conversation so far:\n" . mb_substr($history, -2500) . "\n\n" : '') . "Customer's latest " . $channel . ":\n" . mb_substr($message, 0, 2000);
    $r = ai_chat([['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]], $task === 'reply_fast' ? 350 : 450, $task);
    $j = $r['text']; if (preg_match('/\{.*\}/s', $j, $m)) $j = $m[0];
    $d = json_decode($j, true);
    if (!is_array($d)) {
        $raw = trim((string)$r['text']);
        if ($raw !== '' && $raw[0] !== '{' && strpos($raw, '"action"') === false && strpos($raw, '```') === false) $d = ['action' => 'reply', 'reply' => $raw, 'reason' => '', 'confidence' => 0.5]; // رد نصي عادي من غير JSON
        else {
            // JSON مقطوع أو بايظ: عمرنا ما نبعت شكله للعميل. لو الرد كامل جوّاه (مقفول بعلامة تنصيص) ناخده، غير كده نحوّل للفريق
            $d = ['action' => 'handoff', 'reply' => '', 'reason' => t('رد الذكاء الاصطناعي غير مكتمل'), 'confidence' => 0, 'broken' => 1];
            if (preg_match('/"reply"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"\s*[,}]/u', $raw, $mm)) { $t = json_decode('"' . $mm[1] . '"'); if (is_string($t) && trim($t) !== '' && !preg_match('/handoff/', $raw)) $d = ['action' => 'reply', 'reply' => $t, 'reason' => '', 'confidence' => 0.5]; }
        }
    }
    $d['action'] = ($d['action'] ?? '') === 'handoff' ? 'handoff' : 'reply';
    if ($d['action'] === 'handoff' && $esc['message'] !== '') $d['reply'] = $esc['message'];
    $out = trim((string)($d['reply'] ?? ''));
    if ($out !== '' && preg_match('/^\s*[\{\[]|"action"\s*:|"reply"\s*:|```/u', $out)) { $d['action'] = 'handoff'; $out = ''; $d['reason'] = t('رد الذكاء الاصطناعي غير مكتمل'); }
    if ($d['action'] === 'handoff' && $out === '' && $esc['message'] !== '') $out = $esc['message'];
    $d['reply'] = $out;
    return ['action' => $d['action'], 'reply' => trim((string)($d['reply'] ?? '')), 'reason' => (string)($d['reason'] ?? ''), 'confidence' => (float)($d['confidence'] ?? 0.5), 'model' => $r['model'], 'cost' => $r['cost'] ?? null];
}

/** إيميل التحويل للموظف المسؤول */
function kb_handoff_email(int $wid, string $customer, string $channel, string $message, string $reason, string $link = ''): bool
{
    $to = kb_escalation($wid)['email'];
    if ($to === '') return false;
    $html = '<div style="font-family:sans-serif;line-height:1.6">'
        . '<h2 style="margin:0 0 8px">' . e(t('محادثة محتاجة موظف')) . '</h2>'
        . '<p><b>' . e(t('العميل')) . ':</b> ' . e($customer) . ' · ' . e($channel) . '</p>'
        . '<p><b>' . e(t('السبب')) . ':</b> ' . e($reason) . '</p>'
        . '<blockquote style="border-inline-start:3px solid #3b6ff0;margin:0;padding:8px 12px;background:#f5f7fb">' . nl2br(e(mb_substr($message, 0, 2000))) . '</blockquote>'
        . ($link !== '' ? '<p><a href="' . e($link) . '">' . e(t('افتح المحادثة')) . '</a></p>' : '') . '</div>';
    $ok = 0;
    foreach (array_filter(array_map('trim', preg_split('/[,;\s]+/', $to)), function ($x) { return filter_var($x, FILTER_VALIDATE_EMAIL); }) as $addr) {
        try { send_mail($addr, t('تحويل لموظف: %s', $customer), $html, null, $wid); $ok++; } catch (Throwable $e) {}
    }
    return $ok > 0;
}

/** أقصى حجم رفع حقيقي على السيرفر (بالبايت): الأصغر بين upload_max_filesize و post_max_size و 15 ميجا */
function kb_upload_limit(): int
{
    $b = function ($v) { $v = trim((string)$v); if ($v === '' || $v === '0') return PHP_INT_MAX; $n = (float)$v; $u = strtolower(substr($v, -1)); return (int)($u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n))); };
    return (int)min($b(ini_get('upload_max_filesize')), $b(ini_get('post_max_size')) - 65536, 15 * 1048576);
}

/** يحفظ ملف جديد، أو يبدّل نص ملف موجود لو جاي replace (إعادة قراءة) */
function kb_file_store(int $wid, string $name, string $mime, int $size, string $method, string $text): void
{
    $rid = (int)($_POST['replace'] ?? 0);
    if ($rid > 0) {
        if (($_POST['keep_name'] ?? '') === '1') { $st = db()->prepare("UPDATE kb_files SET method = ?, content = ? WHERE id = ? AND workspace_id = ?"); $st->execute([$method, $text, $rid, $wid]); }
        else { $st = db()->prepare("UPDATE kb_files SET name = ?, mime = ?, size = ?, method = ?, content = ? WHERE id = ? AND workspace_id = ?"); $st->execute([$name, $mime, $size, $method, $text, $rid, $wid]); }
        if ($st->rowCount() || db()->query("SELECT 1 FROM kb_files WHERE id = " . $rid . " AND workspace_id = " . $wid)->fetchColumn()) { $GLOBALS['kb_last_id'] = $rid; return; }
    }
    db()->prepare("INSERT INTO kb_files (workspace_id, name, mime, size, method, content) VALUES (?, ?, ?, ?, ?, ?)")->execute([$wid, $name, $mime, $size, $method, $text]);
    $GLOBALS['kb_last_id'] = (int)db()->lastInsertId();
}

/** نسخة الملف الأصلي على السيرفر (عشان إعادة القراءة من غير ما المستخدم يختاره تاني) */
function kb_orig_dir(int $wid): string { return ROOT_DIR . '/uploads/kb/' . $wid; }
function kb_orig(int $wid, int $id): ?string
{
    foreach ((array)glob(kb_orig_dir($wid) . '/' . $id . '.*') as $f) if (is_file($f) && substr($f, -5) !== '.part') return $f;
    return null;
}
function kb_orig_del(int $wid, int $id): void { foreach ((array)glob(kb_orig_dir($wid) . '/' . $id . '.*') as $f) @unlink($f); }
function kb_orig_ext(string $name): string { $e = strtolower(pathinfo($name, PATHINFO_EXTENSION)); return preg_match('/^[a-z0-9]{1,5}$/', $e) ? $e : 'bin'; }
function kb_orig_save(int $wid, int $id, string $tmp, string $name): void
{
    $d = kb_orig_dir($wid); if (!is_dir($d)) @mkdir($d, 0750, true);
    kb_orig_del($wid, $id);
    @copy($tmp, $d . '/' . $id . '.' . kb_orig_ext($name));
}

/** JSON من رد الموديل حتى لو فيه ``` أو كلام قبله أو أسطر جديدة جوه النصوص */
function kb_parse_json(string $t): ?array
{
    $t = preg_replace('#<think>.*?(</think>|$)#s', '', $t); // موديلات التفكير
    $t = trim(preg_replace('/^```(?:json)?\s*|\s*```$/im', '', trim($t)));
    $a = strpos($t, '{'); if ($a === false) return null;
    $b = strrpos($t, '}');
    $tries = [];
    if ($b !== false && $b > $a) $tries[] = substr($t, $a, $b - $a + 1);
    $tries[] = kb_json_close(substr($t, $a)); // رد مقطوع: نقفل الأقواس
    foreach ($tries as $j) {
        $d = json_decode($j, true); if (is_array($d)) return $d;
        $fixed = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"/s', function ($m) { return str_replace(["\r", "\n", "\t"], ['', '\\n', ' '], $m[0]); }, $j);
        $fixed = preg_replace('/,\s*([}\]])/', '$1', (string)$fixed);
        $d = json_decode((string)$fixed, true); if (is_array($d)) return $d;
    }
    return null;
}

/** بيقفل JSON مقطوع: النص المفتوح، والمفتاح الناقص، والأقواس */
function kb_json_close(string $j): string
{
    $stack = []; $in = false; $esc = false; $lastSafe = 0;
    for ($i = 0, $n = strlen($j); $i < $n; $i++) {
        $c = $j[$i];
        if ($in) { if ($esc) $esc = false; elseif ($c === '\\') $esc = true; elseif ($c === '"') $in = false; continue; }
        if ($c === '"') $in = true;
        elseif ($c === '{' || $c === '[') $stack[] = $c === '{' ? '}' : ']';
        elseif ($c === '}' || $c === ']') { array_pop($stack); $lastSafe = $i + 1; }
        elseif ($c === ',') $lastSafe = $i;
    }
    if ($in) $j .= '"';
    $out = rtrim($j);
    // لو آخر حاجة مفتاح من غير قيمة أو قيمة ناقصة، نرجع لآخر نقطة سليمة
    if (preg_match('/[:,]\s*$/', $out) || preg_match('/"\s*:\s*"?[^"]*$/', $out) === 0 && preg_match('/,\s*"[^"]*"\s*$/', $out)) $out = substr($j, 0, $lastSafe);
    $out = preg_replace('/,\s*$/', '', $out);
    // نعيد حساب الأقواس بعد القص
    $stack = []; $in = false; $esc = false;
    for ($i = 0, $n = strlen($out); $i < $n; $i++) {
        $c = $out[$i];
        if ($in) { if ($esc) $esc = false; elseif ($c === '\\') $esc = true; elseif ($c === '"') $in = false; continue; }
        if ($c === '"') $in = true; elseif ($c === '{' || $c === '[') $stack[] = $c === '{' ? '}' : ']'; elseif ($c === '}' || $c === ']') array_pop($stack);
    }
    if ($in) $out .= '"';
    return $out . implode('', array_reverse($stack));
}

/** الأسعار: أي سعر في اقتراح الذكاء الاصطناعي مش مكتوب في المصادر بيتشال. خانة price بتفضى لو رقمها مش في المصادر. */
function kb_strip_unsourced_prices(array $d, string $sources): array
{
    $norm = function ($t) { return strtr((string)$t, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٬' => '', '٫' => '.']); };
    $src = preg_replace('/(?<=\d)[,\s](?=\d{3}\b)/', '', $norm($sources));
    $nums = function ($t) use ($norm) { preg_match_all('/\d+(?:\.\d+)?/', preg_replace('/(?<=\d)[,\s](?=\d{3}\b)/', '', $norm($t)), $m); return $m[0]; };
    $known = function ($n) use ($src) { return (bool)preg_match('/(?<![\d.])' . preg_quote($n, '/') . '(?![\d])/', $src); };
    $cur = '(?:ج\.?م|جنيه(?:ا|ات)?|جنية|ريال|ر\.س|ر\.ق|ر\.ع|ر\.ي|درهم|د\.إ|د\.م|دينار|د\.ك|د\.ب|د\.أ|د\.ع|د\.ت|د\.ل|دج|ليرة|ل\.ل|شيكل|دولار|يورو|إسترليني|EGP|LE|L\.E\.?|SAR|AED|KWD|QAR|BHD|OMR|JOD|LBP|SYP|ILS|IQD|YER|MAD|DZD|TND|LYD|SDG|TRY|GBP|USD|EUR|\$|€|£)';
    $clean = function ($t) use ($cur, $norm, $known) {
        $t = (string)$t;
        $t = preg_replace('/\[(?:أضف|اضف|add)[^\]]*(?:سعر|price)[^\]]*\]/iu', '', $t);
        return preg_replace_callback('/(?:' . $cur . '\s*)?[\d٠-٩][\d٠-٩.,٬٫ ]*\s*' . $cur . '|' . $cur . '\s*[\d٠-٩][\d٠-٩.,٬٫]*/iu', function ($m) use ($norm, $known) {
            preg_match_all('/\d+(?:\.\d+)?/', preg_replace('/(?<=\d)[,\s](?=\d{3}\b)/', '', $norm($m[0])), $n);
            foreach ($n[0] as $x) if (!$known($x)) return '';
            return $m[0];
        }, $t);
    };
    $walk = function ($v, $k = null) use (&$walk, $clean, $nums, $known) {
        if (is_array($v)) { foreach ($v as $kk => $vv) $v[$kk] = $walk($vv, $kk); return $v; }
        if (!is_string($v)) return $v;
        if ($k === 'price') { $ns = $nums($v); if (!$ns) return ''; foreach ($ns as $x) if (!$known($x)) return ''; return $v; }
        return trim(preg_replace('/[ \t]{2,}/', ' ', $clean($v)));
    };
    return $walk($d);
}

/** بيانات التواصل والمواعيد: أي قيمة فيها رقم أو رابط مش موجود في المصادر بتتمسح */
function kb_strip_unsourced_facts(array $d, string $sources): array
{
    $norm = function ($t) { return strtr((string)$t, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']); };
    $src = mb_strtolower($norm($sources)); $digits = preg_replace('/\D+/', '', $src);
    foreach ($d as $k => $v) {
        if (!is_string($v) || trim($v) === '') continue;
        $t = mb_strtolower($norm($v)); $ok = true;
        preg_match_all('#(?:https?://)?(?:[a-z0-9-]+\.)+[a-z]{2,}(?:/[^\s]*)?|[\w.+-]+@[\w-]+\.[\w.]+#u', $t, $m);
        foreach ($m[0] as $u) { $u = preg_replace('#^https?://(www\.)?#', '', rtrim($u, '/.,')); if ($u !== '' && strpos($src, $u) === false) $ok = false; }
        preg_match_all('/\+?\d[\d\s-]{6,}\d/', $t, $ph);
        foreach ($ph[0] as $n) { $n = preg_replace('/\D+/', '', $n); if (strpos($digits, ltrim($n, '0')) === false && strpos($digits, $n) === false) $ok = false; }
        preg_match_all('/\d{1,2}(?::\d{2})?/', preg_replace('/\+?\d[\d\s-]{6,}\d/', '', $t), $tm);
        if ($k !== 'notes' && $k !== 'social') foreach ($tm[0] as $x) if (!preg_match('/(?<!\d)' . preg_quote($x, '/') . '(?!\d)/', $src)) $ok = false;
        if (!$ok) $d[$k] = '';
    }
    return $d;
}

/** صفوف من الموديل بأسماء مفاتيح مختلفة (service/title/description...) → مفاتيح القسم */
function kb_norm_rows(array $d, array $cols): array
{
    $alias = ['name' => ['name', 'service', 'product', 'title', 'item', 'offer', 'الاسم', 'الخدمة', 'المنتج'], 'details' => ['details', 'description', 'desc', 'info', 'التفاصيل', 'الوصف'], 'price' => ['price', 'cost', 'السعر'],
        'q' => ['q', 'question', 'السؤال'], 'a' => ['a', 'answer', 'الإجابة'], 'offer' => ['offer', 'name', 'service', 'product', 'title'], 'audience' => ['audience', 'target', 'target_audience'], 'problem' => ['problem', 'need', 'pain'], 'frame' => ['frame', 'category', 'frame_of_reference', 'competitors'], 'pod' => ['pod', 'difference', 'point_of_difference'], 'rtb' => ['rtb', 'proof', 'reason_to_believe', 'reasons']];
    $rows = $d['rows'] ?? (($d && array_keys($d) === range(0, count($d) - 1)) ? $d : (reset($d) && is_array(reset($d)) ? reset($d) : []));
    $out = [];
    foreach ((array)$rows as $r) {
        if (is_string($r)) $r = ['name' => $r];
        if (!is_array($r)) continue;
        $low = []; foreach ($r as $k => $v) $low[mb_strtolower((string)$k)] = is_scalar($v) ? (string)$v : implode('، ', array_map('strval', array_filter((array)$v, 'is_scalar')));
        $row = [];
        foreach ($cols as $c) { $row[$c] = ''; foreach ($alias[$c] ?? [$c] as $a) if (isset($low[$a]) && trim($low[$a]) !== '') { $row[$c] = $low[$a]; break; } }
        if (trim(implode('', $row)) !== '') $out[] = $row;
    }
    return ['rows' => $out];
}

/** تنظيف رد "املأ بالذكاء الاصطناعي": فك الغلاف الزيادة ({"audience":{...}} أو {"data":{...}})، وتطابق المفاتيح من غير حساسية لحالة الحروف، وشيل نصوص النموذج <...> */
function kb_fill_norm(?array $d, string $type, array $def, string $sec): ?array
{
    if (!is_array($d)) return null;
    for ($i = 0; $i < 2; $i++) { // غلاف بمفتاح واحد: اسم القسم أو data/result/section
        if (count($d) === 1) { $k = (string)array_key_first($d); $v = $d[$k];
            if (is_array($v) && !array_is_list($v) && (in_array(strtolower($k), [strtolower($sec), 'data', 'result', 'section', 'content', 'fields'], true) || ($type === 'fields' && !isset($def['fields'][$k])))) { $d = $v; continue; } }
        break;
    }
    if ($type === 'rows' && !isset($d['rows'])) { foreach ($d as $v) if (is_array($v) && array_is_list($v)) { $d = ['rows' => $v]; break; } if (array_is_list($d)) $d = ['rows' => $d]; }
    if ($type === 'fields') {
        $out = []; $low = []; foreach ($d as $k => $v) $low[strtolower((string)$k)] = $v;
        foreach ($def['fields'] as $k => $f) { $v = $d[$k] ?? $low[strtolower($k)] ?? $d[$f[0]] ?? ''; $out[$k] = is_array($v) ? implode("\n", array_map('strval', array_filter($v, 'is_scalar'))) : (string)$v; }
        $d = $out;
    }
    if (($type === 'text' || $type === 'list') && !isset($d['text'])) { foreach ($d as $v) if (is_string($v) && trim($v) !== '') { $d = ['text' => $v]; break; } }
    // قيم النموذج اللي رجعت زي ما هي (<...>) تبقى فاضية
    array_walk_recursive($d, function (&$v) { if (is_string($v) && preg_match('/^\s*<[^<>]{0,140}>\s*$/u', $v)) $v = ''; });
    return $d;
}

/** الرد فيه محتوى فعلًا؟ (مش النموذج فاضي) */
function kb_fill_has(array $d): bool
{
    $has = false;
    array_walk_recursive($d, function ($v, $k) use (&$has) { if ($has) return; if (is_string($v) && trim($v) !== '' && !in_array($k, ['lang_rule', 'dialect'], true)) $has = true; });
    return $has;
}
