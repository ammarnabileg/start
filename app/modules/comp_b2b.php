<?php
/**
 * المنافسين B2B: إشارات من موقع المنافس نفسه (من غير ذكاء اصطناعي ومن غير خدمات خارجية)
 * عملاؤه، مشاريعه، شهاداته واعتماداته كمورّد، الوظائف المفتوحة (مؤشر نمو)، طلب عرض السعر وملف الشركة.
 * بيتقري كل 3 أيام مع فحص الموقع، وأي تغيير بيتسجل حدث (عميل جديد، مشروع جديد، شهادة جديدة، توظيف كتير).
 */

/** أنواع الصفحات وكلماتها (عربي وإنجليزي) — بتتطابق على نص الرابط أو مساره */
function cb_page_kinds(): array
{
    return [
        'clients' => '/(عملاؤنا|عملائنا|شركاؤنا|شركائنا|شركاء النجاح|من عملائنا|our[\s_-]*clients|clients|customers|partners|our[\s_-]*partners|trusted[\s_-]*by)/iu',
        'projects' => '/(مشاريعنا|مشروعاتنا|المشاريع|سابقة الأعمال|سابقة اعمال|أعمالنا|اعمالنا|projects|portfolio|case[\s_-]*stud|references|our[\s_-]*work)/iu',
        'certs' => '/(الشهادات|شهاداتنا|الاعتمادات|اعتماداتنا|الجودة|الترخيص|certificat|accreditation|quality|iso|approvals|compliance)/iu',
        'careers' => '/(الوظائف|وظائف|التوظيف|انضم إلينا|انضم الينا|اعمل معنا|careers?|jobs|vacanc|join[\s_-]*us|work[\s_-]*with[\s_-]*us|hiring)/iu',
    ];
}

/** روابط الصفحة: [url, نص] على نفس الدومين */
function cb_links(string $html, string $base): array
{
    $host = preg_replace('/^www\./', '', strtolower((string)parse_url($base, PHP_URL_HOST))); $out = [];
    if (!preg_match_all('~<a\b[^>]*href=["\']([^"\'#]+)["\'][^>]*>(.*?)</a>~is', $html, $m, PREG_SET_ORDER)) return [];
    foreach ($m as $a) {
        $u = function_exists('kbi_abs') ? kbi_abs(html_entity_decode($a[1]), $base) : $a[1]; if (!preg_match('~^https?://~i', $u)) continue;
        $h = preg_replace('/^www\./', '', strtolower((string)parse_url($u, PHP_URL_HOST)));
        $txt = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($a[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $out[] = ['url' => $u, 'text' => mb_substr($txt, 0, 120), 'same' => $h === $host];
    }
    return $out;
}

/** أول صفحة لكل نوع (نص الرابط أولًا، بعدين المسار) */
function cb_find_pages(string $html, string $base): array
{
    $links = cb_links($html, $base); $found = [];
    foreach (cb_page_kinds() as $k => $re) {
        foreach ([0, 1] as $pass) foreach ($links as $l) {
            if (!$l['same'] || isset($found[$k])) continue;
            $path = urldecode((string)parse_url($l['url'], PHP_URL_PATH));
            if ($pass === 0 ? preg_match($re, $l['text']) : preg_match($re, str_replace(['-', '_', '/'], ' ', $path))) $found[$k] = $l['url'];
        }
    }
    return $found;
}

/** نص نظيف من HTML. $frame = يسيب الهيدر والفوتر (السجل التجاري والرقم الضريبي غالبًا في الفوتر) */
function cb_text(string $html, bool $frame = false): string
{
    $html = preg_replace($frame ? '~<(script|style|noscript|svg)[^>]*>.*?</\1>~is' : '~<(script|style|noscript|svg|nav|footer|header)[^>]*>.*?</\1>~is', ' ', $html);
    $html = preg_replace('~<(br|p|div|li|h[1-6]|tr|section)\b[^>]*>~i', ' $0', $html); // مسافة بين العناصر عشان الكلمات ماتلزقش
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

/** محتوى الصفحة الأساسي (من غير القائمة والفوتر) */
function cb_main(string $html): string
{
    if (preg_match('~<main\b[^>]*>(.*?)</main>~is', $html, $m)) return $m[1];
    $html = preg_replace('~<(script|style|noscript|svg|nav|footer|header)[^>]*>.*?</\1>~is', ' ', $html);
    return preg_match('~<body\b[^>]*>(.*)</body>~is', $html, $m) ? $m[1] : $html;
}

/** أسماء العملاء: من alt/title بتاع اللوجوهات، وعناصر القوائم القصيرة */
function cb_clients(string $html): array
{
    $main = cb_main($html); $names = [];
    if (preg_match_all('~<img\b[^>]*>~i', $main, $m)) foreach ($m[0] as $img) {
        // القيمة لحد علامة التنصيص اللي فتحتها بس (عشان «Ma'aden» و«Lowe's» مايتقطعوش)
        foreach (['alt', 'title'] as $at) if (preg_match('~\b' . $at . '=(["\'])(.{2,80}?)\1~iu', $img, $x)) { $names[] = $x[2]; break; }
    }
    if (preg_match_all('~<li\b[^>]*>(.*?)</li>~is', $main, $m)) foreach ($m[1] as $li) { $t = trim(preg_replace('/\s+/u', ' ', strip_tags($li))); if (mb_strlen($t) >= 2 && mb_strlen($t) <= 60) $names[] = $t; }
    $out = [];
    foreach ($names as $n) {
        $n = trim(preg_replace('/\s+/u', ' ', html_entity_decode($n, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $n = trim(preg_replace('/^(شعار|لوجو|logo( of)?)\s+|\s+(logo|شعار|لوجو)$/iu', '', $n), " -–|·");
        if (mb_strlen($n) < 2 || mb_strlen($n) > 60 || preg_match('/^(logo|image|img|icon|banner|slider|photo|picture|صورة|\d+|[\w-]+\.(png|jpe?g|svg|webp))$/iu', $n)) continue;
        $out[mb_strtolower($n)] = $n;
    }
    return array_slice(array_values($out), 0, 80);
}

/** عناوين المشاريع / الوظائف: من h2-h4 (من غير العناوين العامة) */
function cb_headings(string $html, int $max = 60): array
{
    $out = [];
    if (preg_match_all('~<h[2-4]\b[^>]*>(.*?)</h[2-4]>~is', cb_main($html), $m)) foreach ($m[1] as $h) {
        $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (mb_strlen($t) < 4 || mb_strlen($t) > 140 || preg_match('/^(مشاريعنا|المشاريع|أعمالنا|اعمالنا|الوظائف|وظائف|الوظائف المتاحة|انضم إلينا|تواصل معنا|our projects|projects|careers|open positions|join us|contact us|why us|about us|من نحن)$/iu', $t)) continue;
        $out[mb_strtolower($t)] = $t;
    }
    return array_slice(array_values($out), 0, $max);
}

/** الشهادات واعتمادات المورّدين من النص */
function cb_certs(string $text): array
{
    $out = [];
    if (preg_match_all('/(?<![A-Za-z0-9])ISO\s?[\/-]?\s?(\d{4,5})(?:\s?[:\/-]\s?(\d{4}))?/iu', $text, $m, PREG_SET_ORDER)) foreach ($m as $x) $out['ISO ' . $x[1]] = 'ISO ' . $x[1];
    $known = ['OHSAS 18001' => '/OHSAS\s?18001/i', 'SASO' => '/(?<![A-Za-z0-9])SASO(?![A-Za-z0-9])|ساسو|هيئة المواصفات/iu', 'CE' => '/(?<![A-Za-z0-9])CE\s?(mark|certified|marking)(?![A-Za-z0-9])|علامة CE/iu', 'HACCP' => '/(?<![A-Za-z0-9])HACCP(?![A-Za-z0-9])/i',
        'تصنيف المقاولين' => '/تصنيف المقاولين|contractor classification|درجة (أولى|الأولى)/iu', 'IATF 16949' => '/IATF\s?16949/i', 'API' => '/(?<![A-Za-z0-9])API\s?(Q1|monogram|spec)(?![A-Za-z0-9])/i', 'LEED' => '/(?<![A-Za-z0-9])LEED(?![A-Za-z0-9])/i'];
    foreach ($known as $k => $re) if (preg_match($re, $text)) $out[$k] = $k;
    return array_values($out);
}

/** اعتمادات المورّد عند جهات كبيرة (Aramco, SABIC, ...) */
function cb_vendors(string $text): array
{
    $list = ['Saudi Aramco' => '/aramco|أرامكو|ارامكو/iu', 'SABIC' => '/(?<![A-Za-z0-9])sabic(?![A-Za-z0-9])|سابك/iu', 'NEOM' => '/(?<![A-Za-z0-9])neom(?![A-Za-z0-9])|نيوم/iu', 'Ma\'aden' => '/ma[\'’]?aden|معادن/iu', 'SEC' => '/saudi electricity|الشركة السعودية للكهرباء/iu',
        'ROSHN' => '/(?<![A-Za-z0-9])roshn(?![A-Za-z0-9])|روشن/iu', 'Red Sea Global' => '/red sea global|البحر الأحمر الدولية/iu', 'ADNOC' => '/(?<![A-Za-z0-9])adnoc(?![A-Za-z0-9])|أدنوك|ادنوك/iu', 'Etimad' => '/etimad|منصة اعتماد/iu', 'Monshaat' => '/monsha[\'’]?at|منشآت/iu'];
    $ctx = '/(approved|registered|qualified|vendor|supplier|contractor|معتمد|مورد|مقاول|مسجل|مؤهل)/iu'; $out = [];
    foreach ($list as $k => $re) if (preg_match_all($re, $text, $m, PREG_OFFSET_CAPTURE)) foreach ($m[0] as $hit) {
        $win = mb_strcut($text, max(0, $hit[1] - 160), 360, 'UTF-8'); if (preg_match($ctx, $win)) { $out[] = $k; break; } // mb_strcut: مايقطعش الحرف العربي من النص
    }
    return $out;
}

/** الصفحة شكلها مبنية بالجافاسكريبت؟ (سكربتات كتير ونص قليل، أو غلاف تطبيق فاضي، أو كاروسيل من غير صور) */
function cb_looks_js(string $html): bool
{
    $txt = mb_strlen(cb_text($html)); $scripts = substr_count(strtolower($html), '<script');
    // مواقع حديثة (Next.js / React): الصفحة كبيرة والنص المقروء نسبة صغيرة جدًا منها
    $len = strlen($html); if ($len > 150000 && $txt < $len * 0.012) return true;
    if (strpos($html, 'self.__next_f') !== false && $txt < 8000) return true;
    return ($scripts >= 5 && $txt < 600) || preg_match('~<div[^>]+id=["\'](__next|root|app|__nuxt)["\'][^>]*>\s*</div>~i', $html) || (preg_match('~(swiper|slick|owl-carousel|splide|glide)~i', $html) && !preg_match('~<img\b[^>]*\balt=["\'][^"\']{2,}~i', cb_main($html)));
}

/** فحص B2B: بيرجع [البيانات, الأحداث] */
function cb_check(string $homeHtml, string $homeUrl, array $old): array
{
    $pages = cb_find_pages($homeHtml, $homeUrl); $allText = cb_text($homeHtml, true);
    $d = ['pages' => $pages, 'clients' => [], 'projects' => [], 'jobs' => [], 'certs' => [], 'vendors' => [], 'rfq' => false, 'profile' => '', 'cr' => false];
    $render = 2; // أقصى صفحتين بمتصفح حقيقي في الفحص الواحد (تكلفة Apify)
    $pull = function (string $k, string $h) { return $k === 'clients' ? cb_clients($h) : ($k === 'projects' ? cb_headings($h) : ($k === 'careers' ? cb_headings($h, 80) : [])); };
    foreach ($pages as $k => $u) {
        $f = function_exists('kbi_fetch') ? kbi_fetch($u, 900000) : ['err' => 'no fetch']; if (!empty($f['err']) || empty($f['html'])) continue; $h = (string)$f['html'];
        $got = $pull($k, $h);
        // الصفحة طلعت فاضية وشكلها جافاسكريبت: نقراها مرة بمتصفح حقيقي (Apify)
        if (!$got && $k !== 'certs' && $render > 0 && cb_looks_js($h) && function_exists('cs_on') && cs_on() && function_exists('kbi_fetch_apify') && !empty($GLOBALS['net_quick'])) { $GLOBALS['net_quick_skipped'] = true; $render = 0; }
        if (!$got && $k !== 'certs' && $render > 0 && cb_looks_js($h) && function_exists('cs_on') && cs_on() && function_exists('kbi_fetch_apify')) {
            $render--; $a = kbi_fetch_apify($u, true); if (!empty($a['html'])) { $h = (string)$a['html']; $got = $pull($k, $h); $d['rendered'][] = $k; }
        }
        if ($k === 'clients') $d['clients'] = $got;
        elseif ($k === 'projects') $d['projects'] = $got;
        elseif ($k === 'careers') $d['jobs'] = $got;
        $allText .= ' ' . cb_text($h, true);
    }
    // لو مفيش صفحة عملاء: قسم «عملاؤنا» في الرئيسية نفسها
    if (!$d['clients'] && preg_match('~(عملاؤنا|عملائنا|شركاء النجاح|our clients|trusted by|our partners)(.{0,6000})~isu', $homeHtml, $m)) $d['clients'] = array_slice(cb_clients('<main>' . $m[2] . '</main>'), 0, 40);
    $d['certs'] = cb_certs($allText); $d['vendors'] = cb_vendors($allText);
    $d['rfq'] = (bool)preg_match('/(request (a|for) (quote|quotation|proposal)|(?<![A-Za-z0-9])RFQ(?![A-Za-z0-9])|get a quote|طلب عرض سعر|اطلب عرض سعر|طلب تسعير|اطلب تسعيرة|عرض سعر)/iu', $allText);
    $d['cr'] = (bool)preg_match('/(سجل تجاري|السجل التجاري|(?<![A-Za-z0-9])C\.?R\.?\s?(No|Number)|commercial registration|الرقم الضريبي|(?<![A-Za-z0-9])VAT\s?(No|Number))/iu', $allText);
    foreach (cb_links($homeHtml, $homeUrl) as $l) if (preg_match('~\.pdf(\?|$)~i', $l['url']) && preg_match('/(profile|بروفايل|بروفايل|ملف الشركة|تعريف|catalog|كتالوج|brochure)/iu', $l['text'] . ' ' . $l['url'])) { $d['profile'] = $l['url']; break; }
    $d['jobsN'] = count($d['jobs']);
    // الأحداث: بالمقارنة بالقراءة اللي قبلها بس (أول قراءة مش «جديد»)
    $ev = [];
    if ($old) {
        $diff = function (array $now, array $was) { $w = array_flip(array_map('mb_strtolower', $was)); return array_values(array_filter($now, function ($x) use ($w) { return !isset($w[mb_strtolower($x)]); })); };
        $nc = $diff($d['clients'], (array)($old['clients'] ?? [])); foreach (array_slice($nc, 0, 5) as $x) $ev[] = ['client_new', $x, null]; if (count($nc) > 5) $ev[] = ['client_new', t('و%s عميل آخر', count($nc) - 5), null];
        $np = $diff($d['projects'], (array)($old['projects'] ?? [])); foreach (array_slice($np, 0, 5) as $x) $ev[] = ['project_new', $x, null]; if (count($np) > 5) $ev[] = ['project_new', t('و%s مشروع آخر', count($np) - 5), null];
        foreach ($diff(array_merge($d['certs'], $d['vendors']), array_merge((array)($old['certs'] ?? []), (array)($old['vendors'] ?? []))) as $x) $ev[] = ['cert_new', $x, null];
        $oj = (int)($old['jobsN'] ?? 0); if ($d['jobsN'] >= $oj + 3 && $d['jobsN'] >= $oj * 1.5) $ev[] = ['jobs_up', (string)$d['jobsN'], $oj . ' → ' . $d['jobsN']];
        if ($d['rfq'] && empty($old['rfq'])) $ev[] = ['rfq_new', '', null];
    }
    return [$d, $ev];
}

/** للعرض في تبويب الموقع */
function cb_report(array $siteRow): ?array
{
    $d = json_decode((string)($siteRow['b2b'] ?? ''), true); if (!is_array($d)) return null;
    $d['at'] = !empty($siteRow['b2b_at']) ? fmt_date($siteRow['b2b_at'], 'd/m') : '';
    return $d;
}
