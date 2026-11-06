<?php
if (!function_exists('array_is_list')) { function array_is_list(array $a): bool { $i = 0; foreach ($a as $k => $_) if ($k !== $i++) return false; return true; } } // PHP 7.4
/** ملء ملف الشركة من موقعها (من غير ذكاء اصطناعي): بيانات منظمة (JSON-LD) + لينكات + نصوص الصفحات.
 *  النتيجة اقتراحات، والمستخدم بيراجع ويختار قبل ما حاجة تتحفظ. */

/** جلب صفحة عامة بأمان: الدومين لازم يشاور على IP عام، والـ IP بيتثبت للاتصال، وكل تحويل بيتشيك من جديد */
function kbi_fetch(string $url, int $max = 600000): array
{
    for ($hop = 0; $hop < 4; $hop++) {
        if (!preg_match('~^https?://~i', $url)) return ['err' => t('اكتب رابط يبدأ بـ https://')];
        $p = parse_url($url); $host = strtolower((string)($p['host'] ?? '')); if ($host === '') return ['err' => t('الرابط مش صحيح.')];
        $port = (int)($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
        $fx = getenv('UI_FIXTURES');
        $ips = $fx && in_array($host, ['127.0.0.1', 'localhost'], true) ? ['127.0.0.1'] : (@gethostbynamel($host) ?: []);
        if (!$ips) return ['err' => t('مش قادرين نوصل للموقع ده.')];
        foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) && !($fx && $ip === '127.0.0.1')) return ['err' => t('الرابط ده مش متاح.')];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_HEADER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ips[0]], CURLOPT_RANGE => '0-' . $max,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AIPushProfile/1.0)', CURLOPT_HTTPHEADER => ['Accept-Language: ar,en;q=0.8']]);
        $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE); $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
        if ($code >= 300 && $code < 400 && preg_match('/^Location:\s*(\S+)/mi', substr($raw, 0, $hs), $m)) { $url = kbi_abs(trim($m[1]), $url); continue; }
        if ($raw === '' || $code >= 400) return ['err' => t('الموقع رد بخطأ (%s).', $code ?: '—')];
        if ($type !== '' && stripos($type, 'html') === false) return ['err' => t('الرابط ده مش صفحة ويب.')];
        return ['url' => $url, 'html' => substr($raw, $hs, $max)];
    }
    return ['err' => t('تحويلات كتير.')];
}
function kbi_abs(string $href, string $base): string
{
    if (preg_match('~^https?://~i', $href)) return $href; if (strpos($href, '//') === 0) return parse_url($base, PHP_URL_SCHEME) . ':' . $href;
    $b = parse_url($base); $root = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    if ($href !== '' && $href[0] === '/') return $root . $href;
    $dir = preg_replace('~/[^/]*$~', '/', $b['path'] ?? '/'); return $root . $dir . $href;
}
function kbi_txt(string $s): string { return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8'))); }

/** بيرجع ['sections' => [sec => data], 'src' => [sec.field => url], 'pages' => [..], 'err' => ''] */
function kb_import_site(string $url): array
{
    $home = kbi_fetch($url); if (!empty($home['err'])) return ['err' => $home['err']];
    $pages = [$home['url'] => $home['html']]; $hostOf = function ($u) { return preg_replace('/^www\./', '', strtolower((string)parse_url($u, PHP_URL_HOST))); }; $host = $hostOf($home['url']);
    // صفحات داخلية مهمة من لينكات الرئيسية
    $want = '/(about|من-نحن|من نحن|عن|contact|تواصل|اتصل|service|خدمات|خدماتنا|price|pricing|أسعار|الأسعار|menu|منيو|قائمة|faq|أسئلة|الأسئلة|branches|فروع)/iu';
    if (preg_match_all('~<a\b[^>]*href=["\']([^"\'#]+)["\'][^>]*>(.*?)</a>~is', $home['html'], $m, PREG_SET_ORDER)) {
        $cands = [];
        foreach ($m as $a) { $u = kbi_abs(html_entity_decode($a[1]), $home['url']); if ($hostOf($u) !== $host || isset($pages[$u]) || preg_match('~\.(pdf|jpe?g|png|zip|mp4)$~i', $u)) continue;
            if (preg_match($want, rawurldecode($u) . ' ' . kbi_txt($a[2]))) $cands[$u] = 1; }
        foreach (array_slice(array_keys($cands), 0, 5) as $u) { $r = kbi_fetch($u, 400000); if (empty($r['err'])) $pages[$r['url']] = $r['html']; }
    }
    $S = []; $src = [];
    $put = function (string $sec, string $f, $v, string $u) use (&$S, &$src) { if (is_string($v)) $v = trim($v); if ($v === '' || $v === null || $v === []) return; if (isset($S[$sec][$f]) && $S[$sec][$f] !== '') return; $S[$sec][$f] = $v; $src[$sec . '.' . $f] = $u; };
    $svc = []; $faq = []; $social = []; $hoursTxt = [];
    $dayKeys = ['sat' => ['sa', 'saturday', 'السبت'], 'sun' => ['su', 'sunday', 'الأحد', 'الاحد'], 'mon' => ['mo', 'monday', 'الاثنين', 'الإثنين'], 'tue' => ['tu', 'tuesday', 'الثلاثاء'], 'wed' => ['we', 'wednesday', 'الأربعاء', 'الاربعاء'], 'thu' => ['th', 'thursday', 'الخميس'], 'fri' => ['fr', 'friday', 'الجمعة']];
    $dayOf = function (string $d) use ($dayKeys) { $d = mb_strtolower(preg_replace('~^https?://schema.org/~', '', trim($d))); foreach ($dayKeys as $k => $al) foreach ($al as $a) if ($d === $a || mb_strpos($d, $a) === 0 && mb_strlen($a) > 2) return $k; return null; };
    foreach ($pages as $pu => $html) {
        // 1) JSON-LD
        if (preg_match_all('~<script[^>]+application/ld\+json[^>]*>(.*?)</script>~is', $html, $jm)) foreach ($jm[1] as $js) {
            $j = json_decode(html_entity_decode(trim($js), ENT_QUOTES, 'UTF-8'), true); if (!is_array($j)) continue;
            $nodes = isset($j['@graph']) ? (array)$j['@graph'] : (array_is_list($j) ? $j : [$j]);
            foreach ($nodes as $n) {
                if (!is_array($n)) continue; $ty = implode(',', (array)($n['@type'] ?? ''));
                if (preg_match('/Organization|LocalBusiness|Restaurant|Store|Clinic|Hotel|Service|Corporation|Dentist|Physician|Cafe|Bakery|Salon|Agency/i', $ty)) {
                    $put('company', 'name', (string)($n['name'] ?? ''), $pu); $put('company', 'about', mb_substr(kbi_txt((string)($n['description'] ?? '')), 0, 1500), $pu); $put('company', 'tagline', (string)($n['slogan'] ?? ''), $pu);
                    $put('contact', 'phone', (string)($n['telephone'] ?? ''), $pu); $put('contact', 'mail', (string)($n['email'] ?? ''), $pu);
                    $ad = $n['address'] ?? null; if (is_array($ad)) $put('company', 'branches', trim(implode('، ', array_filter([(string)($ad['streetAddress'] ?? ''), (string)($ad['addressLocality'] ?? ''), (string)($ad['addressRegion'] ?? ''), (string)($ad['addressCountry']['name'] ?? $ad['addressCountry'] ?? '')], function ($x) { return is_string($x) && trim($x) !== ''; }))), $pu);
                    elseif (is_string($ad)) $put('company', 'branches', $ad, $pu);
                    foreach ((array)($n['sameAs'] ?? []) as $sa) if (is_string($sa)) $social[] = $sa;
                    if (!empty($n['hasMap'])) $put('contact', 'maps', (string)(is_array($n['hasMap']) ? ($n['hasMap']['url'] ?? '') : $n['hasMap']), $pu);
                    if (!empty($n['paymentAccepted'])) $put('company', 'payment', is_array($n['paymentAccepted']) ? implode('، ', $n['paymentAccepted']) : (string)$n['paymentAccepted'], $pu);
                    if (!empty($n['areaServed'])) { $as = $n['areaServed']; $put('company', 'service_area', is_array($as) ? implode('، ', array_map(function ($x) { return is_array($x) ? (string)($x['name'] ?? '') : (string)$x; }, array_is_list($as) ? $as : [$as])) : (string)$as, $pu); }
                    foreach ((array)($n['openingHoursSpecification'] ?? []) as $oh) if (is_array($oh)) foreach ((array)($oh['dayOfWeek'] ?? []) as $dw) if (($k = $dayOf((string)$dw))) $put('hours', $k, trim(($oh['opens'] ?? '') . ' - ' . ($oh['closes'] ?? ''), ' -'), $pu);
                    foreach ((array)($n['openingHours'] ?? []) as $oh) if (is_string($oh) && preg_match_all('/((?:Mo|Tu|We|Th|Fr|Sa|Su)(?:-(?:Mo|Tu|We|Th|Fr|Sa|Su))?(?:,(?:Mo|Tu|We|Th|Fr|Sa|Su))*)\s+(\d{1,2}:\d{2})-(\d{1,2}:\d{2})/', $oh, $om, PREG_SET_ORDER)) foreach ($om as $o) {
                        $all = ['Sa', 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr']; $ds = [];
                        foreach (explode(',', $o[1]) as $rg) { $pr = explode('-', $rg); if (count($pr) === 2) { $i = array_search($pr[0], $all); $j2 = array_search($pr[1], $all); for ($x = $i; $x !== false && $j2 !== false; $x = ($x + 1) % 7) { $ds[] = $all[$x]; if ($x === $j2) break; } } else $ds[] = $pr[0]; }
                        foreach ($ds as $dd) if (($k = $dayOf($dd))) $put('hours', $k, $o[2] . ' - ' . $o[3], $pu);
                    }
                    foreach ((array)($n['hasOfferCatalog']['itemListElement'] ?? $n['makesOffer'] ?? []) as $of) if (is_array($of)) { $it = $of['itemOffered'] ?? $of; $nm = (string)($it['name'] ?? $of['name'] ?? ''); if ($nm !== '') $svc[mb_strtolower($nm)] = ['name' => $nm, 'price' => trim((string)($of['price'] ?? '') . ' ' . (string)($of['priceCurrency'] ?? '')), 'details' => mb_substr(kbi_txt((string)($it['description'] ?? '')), 0, 300)]; }
                }
                if (preg_match('/Product|Service|MenuItem/i', $ty) && !empty($n['name'])) { $of = (array)($n['offers'] ?? []); $of = array_is_list($of) ? ($of[0] ?? []) : $of;
                    $svc[mb_strtolower((string)$n['name'])] = ['name' => (string)$n['name'], 'price' => trim((string)($of['price'] ?? '') . ' ' . (string)($of['priceCurrency'] ?? '')), 'details' => mb_substr(kbi_txt((string)($n['description'] ?? '')), 0, 300)]; }
                if (preg_match('/FAQPage/i', $ty)) foreach ((array)($n['mainEntity'] ?? []) as $q) if (is_array($q) && !empty($q['name'])) $faq[mb_strtolower((string)$q['name'])] = ['q' => kbi_txt((string)$q['name']), 'a' => mb_substr(kbi_txt((string)($q['acceptedAnswer']['text'] ?? '')), 0, 800)];
            }
        }
        // 2) meta
        $meta = function ($names) use ($html) { foreach ($names as $nm) if (preg_match('~<meta[^>]+(?:property|name)=["\']' . preg_quote($nm, '~') . '["\'][^>]*content=["\']([^"\']*)~i', $html, $mm) || preg_match('~<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']' . preg_quote($nm, '~') . '["\']~i', $html, $mm)) return trim(html_entity_decode($mm[1], ENT_QUOTES, 'UTF-8')); return ''; };
        if ($pu === $home['url']) {
            $put('company', 'name', $meta(['og:site_name', 'application-name']), $pu);
            $put('company', 'tagline', mb_substr($meta(['og:description', 'description']), 0, 200), $pu);
            $put('contact', 'website', $home['url'], $pu);
        }
        if (preg_match('/about|من-نحن|من نحن|عن/iu', rawurldecode($pu))) { // فقرة "من نحن": أطول فقرة في الصفحة
            if (preg_match_all('~<p\b[^>]*>(.*?)</p>~is', $html, $pm)) { $best = ''; foreach ($pm[1] as $pp) { $t = kbi_txt($pp); if (mb_strlen($t) > mb_strlen($best)) $best = $t; } if (mb_strlen($best) > 80) $put('company', 'about', mb_substr($best, 0, 1500), $pu); }
        }
        // 3) لينكات التواصل
        if (preg_match('~href=["\']tel:([+\d\s\-()]{6,})["\']~i', $html, $tm)) $put('contact', 'phone', preg_replace('/[^\d+]/', '', $tm[1]), $pu);
        if (preg_match('~href=["\']mailto:([^"\'?]+)~i', $html, $em)) $put('contact', 'mail', $em[1], $pu);
        if (preg_match('~(?:wa\.me/|api\.whatsapp\.com/send\?phone=)(\d{8,15})~i', $html, $wm)) $put('contact', 'whatsapp', '+' . $wm[1], $pu);
        if (preg_match('~href=["\'](https?://(?:www\.)?(?:google\.[a-z.]+/maps|maps\.google\.[a-z.]+|goo\.gl/maps|maps\.app\.goo\.gl)[^"\']*)~i', $html, $mp)) $put('contact', 'maps', html_entity_decode($mp[1]), $pu);
        if (preg_match_all('~href=["\'](https?://(?:www\.)?(?:facebook|instagram|x|twitter|tiktok|linkedin|youtube|snapchat|threads)\.(?:com|net)/[^"\'\s?#]+)~i', $html, $sm)) foreach ($sm[1] as $s1) if (!preg_match('~/(sharer|share|intent|plugins|dialog)~i', $s1)) $social[] = $s1;
        // 4) من النص: إيميل وتليفون وأسعار ومواعيد وأسئلة
        $body = preg_replace('~<(script|style|noscript|svg)\b.*?</\1>~is', ' ', $html);
        $lines = array_values(array_filter(array_map('kbi_txt', preg_split('~<(?:br|/p|/li|/h[1-6]|/div|/tr|/dt|/dd)\b[^>]*>~i', $body)), function ($x) { return $x !== '' && mb_strlen($x) < 400; }));
        $plain = implode("\n", $lines);
        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]{2,}/u', $plain, $em2)) $put('contact', 'mail', $em2[0], $pu);
        foreach ($lines as $i => $ln) {
            // سعر: "اسم ... 250 ريال" أو "Name - SAR 250"
            if (mb_strlen($ln) <= 120 && preg_match('/^(.{2,70}?)[\s:\-–|•]+((?:SAR|EGP|AED|USD|KWD|QAR|\$|ر\.?\s?س|ريال|جنيه|درهم|دينار)?\s?[\d٠-٩][\d٠-٩,.٬]*\s?(?:SAR|EGP|AED|USD|KWD|QAR|ر\.?\s?س|ريال|جنيه|ج\.?م|درهم|دينار|\$)?)\s*$/u', $ln, $pm2) && preg_match('/(SAR|EGP|AED|USD|KWD|QAR|\$|ر\.?\s?س|ريال|جنيه|ج\.?م|درهم|دينار)/u', $pm2[2])) {
                $nm = trim($pm2[1], " \t-:|•"); if (mb_strlen($nm) >= 2 && !preg_match('/^(total|المجموع|الإجمالي|vat|ضريبة)/iu', $nm)) $svc[mb_strtolower($nm)] = $svc[mb_strtolower($nm)] ?? ['name' => $nm, 'price' => trim($pm2[2]), 'details' => ''];
            }
            // مواعيد: "السبت - الخميس: 9 ص - 10 م"
            if (preg_match('/(السبت|الأحد|الاحد|الاثنين|الإثنين|الثلاثاء|الأربعاء|الاربعاء|الخميس|الجمعة|saturday|sunday|monday|tuesday|wednesday|thursday|friday|daily|يوميًا|يوميا|طوال الأسبوع)/iu', $ln) && preg_match('/\d{1,2}(?::\d{2})?\s*(?:ص|م|am|pm|AM|PM)?\s*(?:-|–|إلى|الى|to|حتى)\s*\d{1,2}(?::\d{2})?\s*(?:ص|م|am|pm|AM|PM|مساءً|صباحًا)?/u', $ln, $hm) && mb_strlen($ln) < 120) $hoursTxt[] = $ln;
            // أسئلة شائعة: سطر بينتهي بعلامة استفهام والسطر اللي بعده إجابة
            if (preg_match('/[؟?]\s*$/u', $ln) && mb_strlen($ln) > 8 && mb_strlen($ln) < 160 && isset($lines[$i + 1]) && !preg_match('/[؟?]\s*$/u', $lines[$i + 1]) && mb_strlen($lines[$i + 1]) > 10)
                if (preg_match('/faq|أسئلة|الأسئلة/iu', rawurldecode($pu)) || count($faq) < 1) $faq[mb_strtolower($ln)] = $faq[mb_strtolower($ln)] ?? ['q' => $ln, 'a' => mb_substr($lines[$i + 1], 0, 800)];
        }
        if (empty($S['contact']['phone']) && preg_match('/(?:\+|00)?(?:966|971|20|965|974|973|968|962)?[\s\-]?0?(?:5\d|1[0125])[\d\s\-]{7,10}\d/u', $plain, $ph)) $put('contact', 'phone', preg_replace('/[^\d+]/', '', $ph[0]), $pu);
    }
    if ($social) { $u = []; foreach ($social as $s1) { $k = preg_replace('~^https?://(www\.)?~i', '', rtrim($s1, '/')); $u[strtolower($k)] = rtrim($s1, '/'); } $put('contact', 'social', implode("\n", array_slice(array_values($u), 0, 8)), $home['url']); }
    if ($hoursTxt && empty($S['hours'])) $put('hours', 'notes', implode("\n", array_slice(array_unique($hoursTxt), 0, 6)), $home['url']);
    if ($svc) $S['services'] = ['rows' => array_values(array_slice($svc, 0, 40))];
    if ($faq) $S['faq'] = ['rows' => array_values(array_slice($faq, 0, 20))];
    return ['sections' => $S, 'src' => $src, 'pages' => array_keys($pages), 'err' => ''];
}

/* ---------- ملف الشركة بيغذي باقي المنصة ---------- */
/** ردود سريعة جاهزة من الملف: [['k' => نوع, 'l' => عنوان, 't' => نص]] */
function kb_profile_snips(): array
{
    if (!function_exists('kb_get')) return []; $kb = kb_get((int)ws('id')); $o = [];
    foreach ((array)($kb['services']['rows'] ?? []) as $r) { $n = trim((string)($r['name'] ?? '')); if ($n === '') continue; $p = trim((string)($r['price'] ?? ''));
        $o[] = ['k' => 'price', 'l' => $n . ($p !== '' ? ' · ' . $p : ''), 't' => $p !== '' ? t('سعر %s: %s', $n, $p) . (trim((string)($r['details'] ?? '')) !== '' ? "\n" . trim((string)$r['details']) : '') : $n . (trim((string)($r['details'] ?? '')) !== '' ? ': ' . trim((string)$r['details']) : '')]; }
    $c = (array)($kb['company'] ?? []); $ct = (array)($kb['contact'] ?? []); $h = (array)($kb['hours'] ?? []);
    $days = ['sat' => t('السبت'), 'sun' => t('الأحد'), 'mon' => t('الاثنين'), 'tue' => t('الثلاثاء'), 'wed' => t('الأربعاء'), 'thu' => t('الخميس'), 'fri' => t('الجمعة')];
    $hl = []; $grp = []; foreach ($days as $k => $dn) if (trim((string)($h[$k] ?? '')) !== '') $grp[trim((string)$h[$k])][] = $dn;
    foreach ($grp as $tm => $ds) $hl[] = (count($ds) > 2 ? $ds[0] . ' – ' . end($ds) : implode('، ', $ds)) . ': ' . $tm;
    if ($hl || trim((string)($h['notes'] ?? '')) !== '') $o[] = ['k' => 'hours', 'l' => t('مواعيد العمل'), 't' => t('مواعيدنا:') . "\n" . trim(implode("\n", $hl) . "\n" . trim((string)($h['notes'] ?? '')))];
    if (trim((string)($c['branches'] ?? '')) !== '' || trim((string)($ct['maps'] ?? '')) !== '') $o[] = ['k' => 'place', 'l' => t('عنوان المكان'), 't' => trim(t('عنواننا: %s', trim((string)($c['branches'] ?? ''))) . (trim((string)($ct['maps'] ?? '')) !== '' ? "\n" . trim((string)$ct['maps']) : ''))];
    if (trim((string)($c['delivery'] ?? '')) !== '' || trim((string)($c['service_area'] ?? '')) !== '') $o[] = ['k' => 'delivery', 'l' => t('التوصيل'), 't' => trim(trim((string)($c['delivery'] ?? '')) . (trim((string)($c['service_area'] ?? '')) !== '' ? "\n" . t('بنخدم: %s', trim((string)$c['service_area'])) : ''))];
    if (trim((string)($c['payment'] ?? '')) !== '') $o[] = ['k' => 'pay', 'l' => t('طرق الدفع'), 't' => t('طرق الدفع: %s', trim((string)$c['payment']))];
    $cn = array_filter([trim((string)($ct['whatsapp'] ?? '')) !== '' ? t('واتساب: %s', trim((string)$ct['whatsapp'])) : '', trim((string)($ct['phone'] ?? '')) !== '' ? t('تليفون: %s', trim((string)$ct['phone'])) : '', trim((string)($ct['website'] ?? '')) !== '' ? trim((string)$ct['website']) : '']);
    if ($cn) $o[] = ['k' => 'contact', 'l' => t('طرق التواصل'), 't' => implode("\n", $cn)];
    foreach (array_slice((array)($kb['faq']['rows'] ?? []), 0, 8) as $r) if (trim((string)($r['q'] ?? '')) !== '' && trim((string)($r['a'] ?? '')) !== '') $o[] = ['k' => 'faq', 'l' => mb_substr(trim((string)$r['q']), 0, 60), 't' => trim((string)$r['a'])];
    return $o;
}
/** هاشتاجات من الملف: الاسم والمدينة والمجال والخدمات */
function kb_profile_tags(): array
{
    if (!function_exists('kb_get')) return []; $kb = kb_get((int)ws('id')); $c = (array)($kb['company'] ?? []);
    $tag = function ($s) { $s = trim(preg_replace('/[^\p{L}\p{N}\s_]/u', ' ', (string)$s)); if ($s === '' || mb_strlen($s) > 40) return ''; return '#' . preg_replace('/\s+/u', '_', $s); };
    $o = [$tag($c['name'] ?? ''), $tag($c['industry'] ?? '')];
    foreach (preg_split('/[،,\n]+/u', (string)($c['branches'] ?? '') . '،' . (string)($c['service_area'] ?? '')) as $part) { $part = trim($part); if ($part !== '' && mb_strlen($part) <= 20 && !preg_match('/\d|شارع|street|road|SA|EG/u', $part)) $o[] = $tag($part); }
    foreach (array_slice((array)($kb['services']['rows'] ?? []), 0, 8) as $r) if (mb_strlen(trim((string)($r['name'] ?? ''))) <= 25) $o[] = $tag($r['name'] ?? '');
    return array_values(array_unique(array_filter($o)));
}
