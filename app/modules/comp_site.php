<?php
/**
 * موقع المنافس (من غير ذكاء اصطناعي): التقنيات وأدوات التتبع، السرعة (Google PageSpeed)، الصفحات الجديدة
 * من الـ sitemap، عمر الدومين (RDAP)، التوفر، الـ SEO، الترتيب في جوجل، وسائل الدفع والتوصيل، أكواد الخصم.
 * كل الطلبات لمواقع خارجية بتعدي على safe_fetch (ممنوع العناوين الداخلية).
 */

/** بصمات التقنيات: [الاسم، المجموعة، regex على HTML الرئيسية] */
function cw_signatures(): array
{
    return [
        ['Salla', 'platform', '~cdn\.salla\.(sa|network)|salla\.sa/|window\.salla~i'], ['Zid', 'platform', '~zid\.store|zidcdn|media\.zid\.sa|zid\.sa/~i'], ['Shopify', 'platform', '~cdn\.shopify\.com|Shopify\.theme~i'],
        ['WooCommerce', 'platform', '~woocommerce~i'], ['WordPress', 'platform', '~/wp-content/|/wp-includes/~i'], ['Wix', 'platform', '~static\.wixstatic\.com|wix\.com~i'], ['Squarespace', 'platform', '~squarespace~i'],
        ['Magento', 'platform', '~Mage\.Cookies|/static/frontend/~i'], ['YouCan', 'platform', '~youcan\.shop|youcanshop~i'], ['ExpandCart', 'platform', '~expandcart~i'], ['OpenCart', 'platform', '~catalog/view/theme|route=common/~i'],
        ['Webflow', 'platform', '~webflow\.(com|io)|data-wf-page~i'], ['Next.js', 'platform', '~__NEXT_DATA__|/_next/static~i'],
        ['Meta Pixel', 'track', '~connect\.facebook\.net/[^"\']*/fbevents\.js|fbq\(\s*[\'"]init~i'], ['Google Analytics', 'track', '~gtag/js\?id=G-|google-analytics\.com/(analytics|ga)\.js~i'], ['Google Tag Manager', 'track', '~googletagmanager\.com/gtm\.js|GTM-[A-Z0-9]{4,}~'],
        ['Google Ads', 'track', '~gtag/js\?id=AW-|googleadservices\.com|[\'"]AW-\d{6,}~'], ['TikTok Pixel', 'track', '~analytics\.tiktok\.com|ttq\.load~i'], ['Snap Pixel', 'track', '~sc-static\.net/scevent|snaptr\(~i'],
        ['X Pixel', 'track', '~static\.ads-twitter\.com|twq\(~i'], ['LinkedIn Insight', 'track', '~snap\.licdn\.com~i'], ['Hotjar', 'track', '~static\.hotjar\.com~i'], ['Microsoft Clarity', 'track', '~clarity\.ms/tag~i'],
        ['WhatsApp', 'chat', '~wa\.me/|api\.whatsapp\.com/send~i'], ['Tawk.to', 'chat', '~embed\.tawk\.to~i'], ['Intercom', 'chat', '~widget\.intercom\.io|intercomSettings~i'], ['Zendesk', 'chat', '~static\.zdassets\.com~i'],
        ['Crisp', 'chat', '~client\.crisp\.chat~i'], ['Tidio', 'chat', '~code\.tidio\.co~i'], ['LiveChat', 'chat', '~cdn\.livechatinc\.com~i'], ['Freshchat', 'chat', '~wchat\.freshchat\.com~i'],
    ];
}
function cw_tech(string $html): array { $o = []; foreach (cw_signatures() as $s) if (preg_match($s[2], $html)) $o[$s[0]] = $s[1]; return $o; }

/** وسائل الدفع والتوصيل الظاهرة في الصفحة (نص + أسماء الصور) */
function cw_pay(string $html, string $text): array
{
    $hay = $text . ' ' . implode(' ', (function ($h) { preg_match_all('~<img[^>]+(?:alt|src)=["\']([^"\']+)~i', $h, $m); return $m[1]; })($html));
    $pay = ['Tabby' => '~tabby|تابي~iu', 'Tamara' => '~tamara|تمارا~iu', 'Apple Pay' => '~apple\s?pay|ابل باي~iu', 'Mada' => '~\bmada\b|مدى~iu', 'Visa' => '~\bvisa\b|فيزا~iu', 'Mastercard' => '~master\s?card|ماستر~iu',
        'STC Pay' => '~stc\s?pay~i', 'Google Pay' => '~google\s?pay~i', 'PayPal' => '~paypal~i', 'Valu' => '~\bvalu\b|فاليو~iu', 'Fawry' => '~fawry|فوري~iu', 'InstaPay' => '~instapay|انستا\s?باي~iu', 'Vodafone Cash' => '~vodafone\s?cash|فودافون كاش~iu',
        'Postpay' => '~postpay~i', 'Sympl' => '~sympl~i', 'COD' => '~الدفع عند الاستلام|كاش عند الاستلام|cash on delivery|\bCOD\b~iu', 'Bank transfer' => '~تحويل بنكي|bank transfer~iu'];
    $ship = ['Aramex' => '~aramex|أرامكس|ارامكس~iu', 'SMSA' => '~smsa|سمسا~iu', 'DHL' => '~\bdhl\b~i', 'Bosta' => '~bosta|بوسطة~iu', 'J&T' => '~j\s?&\s?t express|جي اند تي~iu', 'Naqel' => '~naqel|ناقل~iu', 'RedBox' => '~redbox|ريدبوكس~iu',
        'Free shipping' => '~شحن مجاني|توصيل مجاني|free shipping|free delivery~iu'];
    $o = ['pay' => [], 'ship' => []]; foreach ($pay as $k => $re) if (preg_match($re, $hay)) $o['pay'][] = $k; foreach ($ship as $k => $re) if (preg_match($re, $hay)) $o['ship'][] = $k;
    return $o;
}

/** أكواد الخصم المكتوبة في الصفحة (كود: SAVE20) */
function cw_codes(string $text): array
{
    preg_match_all('~(?:كود\s?الخصم|كود|كوبون|الكوبون|الكود|رمز الخصم|promo\s?code|discount\s?code|coupon|code)\s*[:：\-]?\s*["«“\']?([A-Z0-9][A-Z0-9_\-]{2,14})~iu', $text, $m, PREG_OFFSET_CAPTURE);
    $o = [];
    foreach ($m[1] as $x) { $code = strtoupper($x[0]); if (!preg_match('/[A-Z]/', $code) || !preg_match('/^[A-Z0-9_\-]{4,15}$/', $code) || in_array($code, ['HTML', 'HTTP', 'HTTPS', 'TRUE', 'NULL', 'NONE', 'FREE', 'HERE'], true)) continue;
        // النسبة: الأول بعد الكود مباشرة (نفس الجملة)، وإلا قبله
        $pos = (int)mb_strlen(substr($text, 0, $x[1])); $after = mb_substr($text, $pos, 50); $before = mb_substr($text, max(0, $pos - 50), min(50, $pos));
        if (!preg_match('/(\d{1,2})\s?%/u', $after, $pm) && preg_match_all('/(\d{1,2})\s?%/u', $before, $bm)) $pm = [1 => end($bm[1])];
        $o[$code] = ['code' => $code, 'pct' => isset($pm[1]) ? (int)$pm[1] : null]; }
    return array_values(array_slice($o, 0, 10));
}

/** لقطة SEO للرئيسية */
function cw_seo(string $html, string $text): array
{
    $one = function ($re) use ($html) { return preg_match($re, $html, $m) ? mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, 300) : ''; };
    $meta = function ($n) use ($html) { if (preg_match('~<meta[^>]+(?:name|property)=["\']' . $n . '["\'][^>]*content=["\']([^"\']*)~i', $html, $m) || preg_match('~<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:name|property)=["\']' . $n . '["\']~i', $html, $m)) return mb_substr(trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 400); return ''; };
    preg_match_all('~<h1[^>]*>(.*?)</h1>~is', $html, $h1); preg_match_all('~<h2[\s>]~i', $html, $h2);
    preg_match_all('~"@type"\s*:\s*"([A-Za-z]+)"~', $html, $ld); preg_match_all('~hreflang=~i', $html, $hl);
    return ['title' => $one('~<title[^>]*>(.*?)</title>~is'), 'desc' => $meta('description'), 'h1' => isset($h1[1][0]) ? mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($h1[1][0])))), 0, 200) : '', 'h1n' => count($h1[1]), 'h2n' => count($h2[0]),
        'lang' => preg_match('~<html[^>]+lang=["\']([\w-]+)~i', $html, $l) ? $l[1] : '', 'canonical' => preg_match('~<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)~i', $html, $c) ? $c[1] : '',
        'og' => $meta('og:title') !== '', 'schema' => array_values(array_unique($ld[1])), 'hreflang' => count($hl[0]), 'noindex' => (bool)preg_match('~<meta[^>]+robots[^>]+noindex~i', $html), 'words' => count(preg_split('/\s+/u', trim($text)) ?: [])];
}

/** روابط الـ sitemap (robots.txt ثم الأماكن المعتادة، مع sitemap index) */
function cw_sitemap(string $home): array
{
    $p = parse_url($home); $root = ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $maps = [];
    $rb = safe_fetch($root . '/robots.txt', ['max' => 200000]); if (empty($rb['err']) && preg_match_all('~^\s*sitemap:\s*(\S+)~im', (string)($rb['body'] ?? ''), $m)) $maps = $m[1];
    if (!$maps) $maps = [$root . '/sitemap.xml', $root . '/sitemap_index.xml', $root . '/wp-sitemap.xml'];
    $urls = []; $seen = []; $queue = array_slice($maps, 0, 4); $fetched = 0;
    while ($queue && $fetched < 10 && count($urls) < 5000) {
        $u = array_shift($queue); if (isset($seen[$u])) continue; $seen[$u] = 1; $fetched++;
        $r = safe_fetch($u, ['max' => 4000000]); if (!empty($r['err'])) continue; $x = (string)($r['body'] ?? ''); if (substr($x, 0, 2) === "\x1f\x8b") $x = (string)@gzdecode($x);
        if (stripos($x, '<sitemapindex') !== false) { preg_match_all('~<loc>\s*([^<\s]+)\s*</loc>~i', $x, $m); $kids = $m[1]; usort($kids, function ($a, $b) { $s = function ($v) { return preg_match('~product|page|post|service|collection|category~i', $v) ? 0 : 1; }; return $s($a) <=> $s($b); }); foreach (array_slice($kids, 0, 8) as $k) $queue[] = html_entity_decode($k); continue; }
        if (preg_match_all('~<url>(.*?)</url>~is', $x, $m)) foreach ($m[1] as $blk) { if (!preg_match('~<loc>\s*([^<\s]+)\s*</loc>~i', $blk, $l)) continue; $lm = preg_match('~<lastmod>\s*([^<\s]+)~i', $blk, $q) ? substr($q[1], 0, 10) : null; $urls[html_entity_decode($l[1])] = $lm; if (count($urls) >= 5000) break; }
    }
    return $urls;
}

/** الدومين الأساسي (example.com.sa → example.com.sa) */
function cw_regdomain(string $host): string
{
    $h = preg_replace('/^www\./', '', strtolower($host)); if (filter_var($h, FILTER_VALIDATE_IP)) return $h; $p = explode('.', $h); $n = count($p); if ($n <= 2) return $h;
    $sld = $p[$n - 2] . '.' . $p[$n - 1];
    return preg_match('/^(com|net|org|gov|edu|co|med|sch)\.[a-z]{2}$/', $sld) ? implode('.', array_slice($p, -3)) : $sld;
}

/** عمر الدومين من RDAP (عام ومجاني) */
function cw_domain(string $host): ?array
{
    $d = cw_regdomain($host); if ($d === '' || filter_var($d, FILTER_VALIDATE_IP)) return null;
    $r = safe_fetch(rtrim((string)(getenv('RDAP_BASE') ?: 'https://rdap.org'), '/') . '/domain/' . rawurlencode($d), ['max' => 400000]);
    if (!empty($r['err'])) return ['domain' => $d, 'err' => (string)$r['err']];
    $j = json_decode((string)($r['body'] ?? ''), true); if (!is_array($j)) return ['domain' => $d, 'err' => 'rdap'];
    $ev = []; foreach ((array)($j['events'] ?? []) as $e) $ev[(string)($e['eventAction'] ?? '')] = substr((string)($e['eventDate'] ?? ''), 0, 10);
    $reg = ''; foreach ((array)($j['entities'] ?? []) as $e) if (in_array('registrar', (array)($e['roles'] ?? []), true)) { foreach ((array)($e['vcardArray'][1] ?? []) as $v) if (($v[0] ?? '') === 'fn') $reg = (string)$v[3]; }
    return ['domain' => $d, 'created' => $ev['registration'] ?? '', 'expires' => $ev['expiration'] ?? '', 'changed' => $ev['last changed'] ?? '', 'registrar' => $reg];
}

/** سرعة الموقع من Google PageSpeed (موبايل) */
function cw_psi(string $url): ?array
{
    $q = http_build_query(array_filter(['url' => $url, 'strategy' => 'mobile', 'category' => 'performance', 'key' => (string)setting('psi_key', '')]));
    $ch = curl_init(rtrim((string)(getenv('PSI_BASE') ?: 'https://www.googleapis.com'), '/') . '/pagespeedonline/v5/runPagespeed?' . $q);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 10]);
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = json_decode($raw, true); if ($code !== 200 || !is_array($j)) return ['err' => $code === 429 ? t('تجاوزنا حد Google PageSpeed المجاني، نحاول لاحقًا.') : 'PSI ' . $code];
    $a = (array)($j['lighthouseResult']['audits'] ?? []); $v = function ($k) use ($a) { return isset($a[$k]['numericValue']) ? round((float)$a[$k]['numericValue'], 3) : null; };
    $sc = $j['lighthouseResult']['categories']['performance']['score'] ?? null;
    return ['score' => $sc !== null ? (int)round($sc * 100) : null, 'lcp' => $v('largest-contentful-paint'), 'fcp' => $v('first-contentful-paint'), 'cls' => $v('cumulative-layout-shift'), 'tbt' => $v('total-blocking-time'), 'si' => $v('speed-index'), 'at' => date('Y-m-d')];
}

function cw_row(int $cid): array
{
    $st = db()->prepare('SELECT * FROM competitor_site WHERE competitor_id = ?'); $st->execute([$cid]); $r = $st->fetch();
    if (!$r) { db()->prepare('INSERT IGNORE INTO competitor_site (competitor_id) VALUES (?)')->execute([$cid]); $st->execute([$cid]); $r = $st->fetch(); }
    return $r ?: ['competitor_id' => $cid];
}

/** الفحص اليومي للموقع (بيتنادى بعد قراءة الأسعار) */
function cw_check(array $c, bool $force = false): int
{
    static $psiBudget = 2; // PageSpeed بطيء (لحد دقيقة): أقصى 2 في التشغيلة الواحدة
    $pdo = db(); $cid = (int)$c['id']; $wid = (int)$c['workspace_id']; $old = cw_row($cid); $ev = [];
    $t0 = microtime(true);
    $k = kbi_fetch((string)$c['url'], 1500000); // نفس القراءة (مع Apify لو الموقع حاجب السيرفر)
    $r = empty($k['err']) ? ['url' => $k['url'], 'body' => $k['html']] : ['err' => $k['err']];
    $ms = (int)round((microtime(true) - $t0) * 1000); $ok = empty($r['err']) && (string)($r['body'] ?? '') !== '';
    if ($ok && ($k['via'] ?? '') === 'apify') $ms = null; // الوقت هنا وقت Apify مش وقت الموقع
    $pdo->prepare('REPLACE INTO competitor_site_days (competitor_id, day, ok, ms) VALUES (?, ?, ?, ?)')->execute([$cid, date('Y-m-d'), $ok ? 1 : 0, $ok && $ms !== null ? $ms : null]);
    $set = []; $had = !empty($old['checked_at']);
    if ($ok) {
        $html = (string)$r['body']; $clean = preg_replace('~<(script|style|noscript|svg)[^>]*>.*?</\1>~is', ' ', $html);
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $tech = cw_tech($html); $pay = cw_pay($clean, $text); $codes = cw_codes($text); $seo = cw_seo($html, $text);
        if ($had) {
            $ot = json_decode((string)$old['tech'], true) ?: []; foreach (array_diff_key($tech, $ot) as $k => $g) $ev[] = ['tech_new', $k, $g];
            $os = json_decode((string)$old['seo'], true) ?: []; if (($os['title'] ?? '') !== '' && $seo['title'] !== '' && $os['title'] !== $seo['title']) $ev[] = ['seo', t('عنوان الصفحة الرئيسية'), $os['title'] . ' → ' . $seo['title']];
            if (($os['desc'] ?? '') !== '' && $seo['desc'] !== '' && $os['desc'] !== $seo['desc']) $ev[] = ['seo', t('وصف الصفحة الرئيسية'), $seo['desc']];
            $oc = array_column(json_decode((string)$old['codes'], true) ?: [], 'code'); foreach ($codes as $x) if (!in_array($x['code'], $oc, true)) $ev[] = ['code_new', $x['code'], $x['pct'] ? $x['pct'] . '%' : null];
        }
        // الأكواد: نحتفظ بالقديم مع تاريخ أول ظهور
        $prev = []; foreach (json_decode((string)($old['codes'] ?? ''), true) ?: [] as $x) $prev[$x['code']] = $x;
        foreach ($codes as &$x) { $x['since'] = $prev[$x['code']]['since'] ?? date('Y-m-d'); } unset($x);
        $set = ['tech' => json_encode($tech), 'pay' => json_encode($pay), 'codes' => json_encode($codes, JSON_UNESCAPED_UNICODE), 'seo' => json_encode($seo, JSON_UNESCAPED_UNICODE), 'checked_at' => date('Y-m-d H:i:s')];
        // sitemap كل 3 أيام
        if ($force || empty($old['sitemap_at']) || strtotime((string)$old['sitemap_at']) < time() - 3 * 86400) {
            $urls = cw_sitemap((string)$r['url']);
            if ($urls) {
                $first = !(int)$pdo->query('SELECT COUNT(*) FROM competitor_pages WHERE competitor_id = ' . $cid)->fetchColumn(); $ins = $pdo->prepare('INSERT IGNORE INTO competitor_pages (competitor_id, url_hash, url, lastmod) VALUES (?, ?, ?, ?)'); $new = [];
                foreach ($urls as $u => $lm) { $ins->execute([$cid, md5($u), mb_substr($u, 0, 700), $lm && preg_match('/^\d{4}-\d{2}-\d{2}$/', $lm) ? $lm : null]); if ($ins->rowCount() && !$first) $new[] = $u; }
                foreach (array_slice($new, 0, 8) as $u) $ev[] = ['page_new', urldecode((string)parse_url($u, PHP_URL_PATH)) ?: $u, $u];
                if (count($new) > 8) $ev[] = ['page_new', t('و%s صفحة أخرى', count($new) - 8), null];
                $set['pages_n'] = count($urls);
            }
            $set['sitemap_at'] = date('Y-m-d H:i:s');
        }
        // B2B (العملاء، المشاريع، الشهادات، الوظائف) كل 3 أيام
        if (function_exists('cb_check') && ($force || empty($old['b2b_at']) || strtotime((string)$old['b2b_at']) < time() - 3 * 86400)) {
            try { [$b2b, $evB] = cb_check($html, (string)$r['url'], json_decode((string)($old['b2b'] ?? ''), true) ?: []); $set['b2b'] = json_encode($b2b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); $set['b2b_at'] = date('Y-m-d H:i:s'); $ev = array_merge($ev, $evB); } catch (Throwable $e) {}
        }
        // الدومين كل 30 يوم
        if ($force || empty($old['domain_at']) || strtotime((string)$old['domain_at']) < time() - 30 * 86400) { $d = cw_domain((string)parse_url((string)$r['url'], PHP_URL_HOST)); if ($d) { $set['domain'] = json_encode($d, JSON_UNESCAPED_UNICODE); $set['domain_at'] = date('Y-m-d H:i:s'); } }
        // السرعة كل 7 أيام
        if (empty($GLOBALS['cw_no_psi']) && ($force || empty($old['psi_at']) || strtotime((string)$old['psi_at']) < time() - 7 * 86400) && $psiBudget > 0) { $psiBudget--; $p = cw_psi((string)$r['url']); if ($p) { $set['psi'] = json_encode($p); $set['psi_at'] = date('Y-m-d H:i:s'); } }
    }
    if ($set) $pdo->prepare('UPDATE competitor_site SET ' . implode(', ', array_map(function ($k) { return $k . ' = ?'; }, array_keys($set))) . ' WHERE competitor_id = ?')->execute(array_merge(array_values($set), [$cid]));
    $ins = $pdo->prepare('INSERT INTO competitor_events (workspace_id, competitor_id, kind, title, detail) VALUES (?, ?, ?, ?, ?)');
    foreach ($ev as $e) $ins->execute([$wid, $cid, $e[0], mb_substr((string)$e[1], 0, 255), $e[2] !== null ? mb_substr((string)$e[2], 0, 500) : null]);
    return count($ev);
}

/* ---------- 33: الترتيب في جوجل (Apify Google Search، أسبوعيًا، على كلماتك) ---------- */
function cw_kw(int $wid): array { $k = json_decode((string)setting('comp_kw_' . $wid, ''), true); return is_array($k) ? array_values(array_slice($k, 0, 10)) : []; }
function cw_rank_pending(int $wid): bool { $r = json_decode((string)setting('comp_rank_run_' . $wid, ''), true); return !empty($r['id']) && (int)($r['at'] ?? 0) > time() - 1800; }
function cw_rank_start(int $wid): bool
{
    $kw = cw_kw($wid); if (!$kw || !function_exists('cs_on') || !cs_on()) return false;
    $cc = strtolower((string)(function_exists('ws_country') ? ws_country($wid) : 'sa')) ?: 'sa';
    $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode('apify~google-search-scraper') . '/runs?memory=1024', ['queries' => implode("\n", $kw), 'countryCode' => $cc, 'languageCode' => lang() === 'en' ? 'en' : 'ar', 'resultsPerPage' => 30, 'maxPagesPerQuery' => 1, 'mobileResults' => false]);
    if (empty($r['data']['id'])) return false;
    save_setting('comp_rank_run_' . $wid, json_encode(['id' => (string)$r['data']['id'], 'at' => time()])); return true;
}
function cw_rank_collect(int $wid): bool
{
    $key = 'comp_rank_run_' . $wid; $run = json_decode((string)setting($key, ''), true); if (empty($run['id'])) return true;
    $r = tnd_apify_req('GET', '/v2/actor-runs/' . rawurlencode($run['id'])); $st = (string)($r['data']['status'] ?? '');
    if (in_array($st, ['READY', 'RUNNING'], true) && (int)$run['at'] > time() - 1800) return false;
    save_setting($key, ''); if ($st !== 'SUCCEEDED') return true;
    $items = tnd_apify_req('GET', '/v2/datasets/' . rawurlencode((string)$r['data']['defaultDatasetId']) . '/items?clean=1&limit=100');
    $prev = json_decode((string)setting('comp_rank_' . $wid, ''), true) ?: []; $out = ['at' => date('Y-m-d'), 'kw' => [], 'prev' => $prev['kw'] ?? []];
    foreach ($items as $it) { $term = (string)($it['searchQuery']['term'] ?? ''); if ($term === '') continue; $hosts = [];
        foreach ((array)($it['organicResults'] ?? []) as $o) { $h = cw_regdomain((string)parse_url((string)($o['url'] ?? ''), PHP_URL_HOST)); if ($h !== '' && !isset($hosts[$h])) $hosts[$h] = (int)($o['position'] ?? 0); }
        $out['kw'][$term] = $hosts; }
    save_setting('comp_rank_' . $wid, json_encode($out, JSON_UNESCAPED_UNICODE)); return true;
}
/** جدول الترتيب لدومينات معينة */
function cw_rank_table(int $wid, array $domains): array
{
    $d = json_decode((string)setting('comp_rank_' . $wid, ''), true) ?: []; $rows = [];
    foreach (cw_kw($wid) as $k) { $cur = $d['kw'][$k] ?? null; $pv = $d['prev'][$k] ?? null; $cells = [];
        foreach ($domains as $dm) { $p = $cur[$dm] ?? null; $q = $pv[$dm] ?? null; $cells[$dm] = ['pos' => $p, 'move' => ($p && $q) ? $q - $p : null]; }
        $rows[] = ['kw' => $k, 'cells' => $cells, 'read' => $cur !== null]; }
    return ['at' => $d['at'] ?? '', 'rows' => $rows, 'pending' => cw_rank_pending($wid)];
}
/** التشغيل الدوري: أسبوعيًا */
function cw_rank_tick(): void
{
    $wid = (int)ws('id'); if (!cw_kw($wid)) return;
    if (cw_rank_pending($wid)) { try { cw_rank_collect($wid); } catch (Throwable $e) {} return; }
    $d = json_decode((string)setting('comp_rank_' . $wid, ''), true) ?: [];
    if (empty($d['at']) || strtotime((string)$d['at']) < time() - 7 * 86400) { try { cw_rank_start($wid); } catch (Throwable $e) {} }
}

/** دومين الشركة نفسها (من ملف الشركة) */
function cw_my_domain(int $wid): string
{
    $k = function_exists('kb_get') ? kb_get($wid) : []; $u = (string)($k['contact']['website'] ?? ''); if ($u === '') return '';
    return cw_regdomain((string)parse_url(preg_match('~^https?://~i', $u) ? $u : 'https://' . $u, PHP_URL_HOST));
}

/** كل أرقام تبويب الموقع */
function cw_report(array $c): array
{
    $pdo = db(); $cid = (int)$c['id']; $wid = (int)ws('id'); $r = cw_row($cid);
    $days = $pdo->prepare('SELECT day, ok, ms FROM competitor_site_days WHERE competitor_id = ? AND day >= ? ORDER BY day'); $days->execute([$cid, date('Y-m-d', strtotime('-29 days'))]); $dd = $days->fetchAll();
    $okN = count(array_filter($dd, function ($x) { return (int)$x['ok']; })); $ms = array_filter(array_map(function ($x) { return $x['ms'] !== null ? (int)$x['ms'] : null; }, $dd), function ($v) { return $v !== null; });
    $np = $pdo->prepare('SELECT url, lastmod, first_seen FROM competitor_pages WHERE competitor_id = ? AND first_seen >= ? ORDER BY first_seen DESC LIMIT 60'); $np->execute([$cid, date('Y-m-d', strtotime('-30 days'))]);
    // أول قراءة بتسجل كل الصفحات مرة واحدة: دي مش «جديدة»، فبنستبعد أول دفعة
    $firstPg = strtotime((string)$pdo->query('SELECT MIN(first_seen) FROM competitor_pages WHERE competitor_id = ' . $cid)->fetchColumn());
    $newPages = []; foreach ($np->fetchAll() as $p) { if ($firstPg && strtotime((string)$p['first_seen']) <= $firstPg + 120) continue; $newPages[] = ['url' => $p['url'], 'path' => urldecode((string)parse_url($p['url'], PHP_URL_PATH)), 'at' => fmt_date($p['first_seen'], 'd/m')]; }
    $dom = json_decode((string)($r['domain'] ?? ''), true) ?: null;
    if ($dom && !empty($dom['created'])) { $dt = new DateTime($dom['created']); $iv = $dt->diff(new DateTime('today')); $dom['years'] = $iv->y; $dom['months'] = $iv->m; }
    $host = cw_regdomain((string)parse_url((string)$c['url'], PHP_URL_HOST)); $mine = cw_my_domain($wid);
    $rank = cw_rank_table($wid, array_values(array_filter([$host, $mine])));
    $tech = json_decode((string)($r['tech'] ?? ''), true) ?: []; $g = ['platform' => [], 'track' => [], 'chat' => []]; foreach ($tech as $k => $grp) $g[$grp][] = $k;
    return ['checked' => !empty($r['checked_at']) ? fmt_date($r['checked_at'], 'd/m h:i A') : '', 'tech' => $g, 'pay' => json_decode((string)($r['pay'] ?? ''), true) ?: ['pay' => [], 'ship' => []],
        'codes' => json_decode((string)($r['codes'] ?? ''), true) ?: [], 'seo' => json_decode((string)($r['seo'] ?? ''), true) ?: null, 'psi' => json_decode((string)($r['psi'] ?? ''), true) ?: null,
        'domain' => $dom, 'pagesN' => $r['pages_n'] !== null ? (int)$r['pages_n'] : null, 'newPages' => $newPages,
        'up' => ['days' => count($dd), 'ok' => $okN, 'pct' => $dd ? round($okN / count($dd) * 100, 1) : null, 'ms' => $ms ? (int)round(array_sum($ms) / count($ms)) : null, 'series' => array_map(function ($x) { return [$x['day'], (int)$x['ok'], $x['ms'] !== null ? (int)$x['ms'] : null]; }, $dd)],
        'rank' => $rank + ['host' => $host, 'mine' => $mine, 'kw' => cw_kw($wid), 'on' => function_exists('cs_on') && cs_on()], 'b2b' => function_exists('cb_report') ? cb_report($r) : null];
}
