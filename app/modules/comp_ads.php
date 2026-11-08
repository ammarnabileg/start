<?php
/**
 * إعلانات المنافسين (من غير ذكاء اصطناعي): مكتبة إعلانات ميتا العامة عن طريق Apify (الـ API الرسمي
 * بيرجّع الإعلانات السياسية وإعلانات أوروبا بس). الإعلانات الشغالة، اللي بقالها مدة (= بتكسب غالبًا)،
 * الجديدة، المنصات، وصفحات الهبوط.
 */

function ca_actor(): string { return 'apify~facebook-ads-scraper'; }

/** صفحة فيسبوك المنافس (من روابطه) */
function ca_page(array $c): ?string
{
    foreach (array_filter(explode("\n", (string)$c['links'])) as $l) { $d = function_exists('cs_detect') ? cs_detect($l) : null; if ($d && $d[0] === 'facebook') return $d[2]; }
    return null;
}

function ca_pending(int $cid): bool { $r = json_decode((string)setting('ca_run_' . $cid, ''), true); return !empty($r['id']) && (int)($r['at'] ?? 0) > time() - 1800; }

/** يبدأ قراءة الإعلانات الشغالة (من غير ما يستنى) */
function ca_start(array $c): bool
{
    $pg = ca_page($c); if (!$pg || !function_exists('cs_on') || !cs_on()) return false;
    $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode(ca_actor()) . '/runs?memory=1024', ['startUrls' => [['url' => $pg]], 'resultsLimit' => 150, 'activeStatus' => 'active', 'isDetailsPerAd' => false]);
    if (empty($r['data']['id'])) return false;
    save_setting('ca_run_' . (int)$c['id'], json_encode(['id' => (string)$r['data']['id'], 'at' => time()]));
    return true;
}

/** يجمع النتيجة لو خلصت. true = خلصت (نجحت أو فشلت) */
function ca_collect(array $c): bool
{
    $key = 'ca_run_' . (int)$c['id']; $run = json_decode((string)setting($key, ''), true); if (empty($run['id'])) return true;
    $r = tnd_apify_req('GET', '/v2/actor-runs/' . rawurlencode($run['id'])); $st = (string)($r['data']['status'] ?? '');
    if (in_array($st, ['READY', 'RUNNING'], true) && (int)$run['at'] > time() - 1800) return false;
    save_setting($key, '');
    if ($st !== 'SUCCEEDED') { save_setting('ca_err_' . (int)$c['id'], t('تعذّرت قراءة مكتبة الإعلانات (%s)', $st ?: '?')); return true; }
    $items = tnd_apify_req('GET', '/v2/datasets/' . rawurlencode((string)$r['data']['defaultDatasetId']) . '/items?clean=1&limit=300');
    ca_store($c, $items); save_setting('ca_err_' . (int)$c['id'], '');
    return true;
}

function ca_date($v): ?string { if ($v === null || $v === '') return null; $t = is_numeric($v) ? (int)$v : strtotime((string)$v); if ($t > 2000000000000) $t = (int)($t / 1000); return $t > 0 ? date('Y-m-d', $t) : null; }

/** إعلان واحد بشكل موحد */
function ca_parse(array $x): ?array
{
    $id = (string)($x['adArchiveID'] ?? $x['adArchiveId'] ?? $x['ad_archive_id'] ?? $x['adid'] ?? ''); if ($id === '') return null;
    $sn = (array)($x['snapshot'] ?? []); $card = (array)($sn['cards'][0] ?? []);
    $body = (string)($sn['body']['text'] ?? (is_string($sn['body'] ?? null) ? $sn['body'] : '') ?: ($card['body'] ?? ''));
    if ($body === '' && !empty($sn['body']['markup']['__html'])) $body = trim(html_entity_decode(strip_tags((string)$sn['body']['markup']['__html'])));
    if (preg_match('/^\{\{product\.\w+\}\}$/', $body)) $body = (string)($card['body'] ?? '');
    $fmt = strtolower((string)($sn['displayFormat'] ?? $sn['display_format'] ?? ''));
    $vid = (array)($sn['videos'][0] ?? []); $img = (array)($sn['images'][0] ?? []);
    $format = $fmt === 'video' || $vid ? 'video' : ($fmt === 'carousel' || $fmt === 'dco' || count((array)($sn['cards'] ?? [])) > 1 ? 'carousel' : 'image');
    $pl = array_values(array_unique(array_map('strtolower', (array)($x['publisherPlatform'] ?? $x['publisher_platform'] ?? $x['publisherPlatforms'] ?? []))));
    return ['id' => $id, 'page' => (string)($x['pageName'] ?? $x['page_name'] ?? $sn['pageName'] ?? ''), 'active' => !isset($x['isActive']) && !isset($x['is_active']) ? true : !empty($x['isActive'] ?? $x['is_active']),
        'start' => ca_date($x['startDate'] ?? $x['start_date'] ?? $x['startDateFormatted'] ?? null), 'end' => ca_date($x['endDate'] ?? $x['end_date'] ?? null), 'platforms' => $pl,
        'body' => mb_substr($body, 0, 2000), 'title' => mb_substr((string)($sn['title'] ?? $card['title'] ?? ''), 0, 300), 'cta' => mb_substr((string)($sn['ctaText'] ?? $sn['cta_text'] ?? $card['ctaText'] ?? ''), 0, 80),
        'link' => mb_substr((string)($sn['linkUrl'] ?? $sn['link_url'] ?? $card['linkUrl'] ?? ''), 0, 700), 'format' => $format,
        'thumb' => mb_substr((string)($vid['videoPreviewImageUrl'] ?? $img['resizedImageUrl'] ?? $img['originalImageUrl'] ?? $card['resizedImageUrl'] ?? $card['originalImageUrl'] ?? ''), 0, 700),
        'variants' => max(1, (int)($x['collationCount'] ?? $x['collation_count'] ?? 1))];
}

/** تخزين: الجديد يتضاف، الموجود يتحدث، واللي اختفى يتقفل (بحماية: مرتين فاضيين ورا بعض قبل ما نقفل الكل) */
function ca_store(array $c, array $items): void
{
    $pdo = db(); $cid = (int)$c['id']; $wid = (int)$c['workspace_id'];
    $first = !(int)$pdo->query('SELECT COUNT(*) FROM competitor_ads WHERE competitor_id = ' . $cid)->fetchColumn();
    $seen = []; $new = [];
    $sel = $pdo->prepare('SELECT id, active FROM competitor_ads WHERE competitor_id = ? AND ext_id = ?');
    foreach ($items as $x) {
        if (!is_array($x) || !($a = ca_parse($x)) || !$a['active']) continue; $seen[$a['id']] = 1;
        $sel->execute([$cid, $a['id']]); $row = $sel->fetch();
        if (!$row) {
            $pdo->prepare('INSERT INTO competitor_ads (workspace_id, competitor_id, ext_id, page_name, active, started, platforms, body, title, cta, link, format, thumb, variants, last_seen) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$wid, $cid, $a['id'], mb_substr($a['page'], 0, 190) ?: null, $a['start'], implode(',', $a['platforms']) ?: null, $a['body'], $a['title'] ?: null, $a['cta'] ?: null, $a['link'] ?: null, $a['format'], $a['thumb'] ?: null, $a['variants']]);
            if (!$first) $new[] = $a;
        } else {
            $pdo->prepare('UPDATE competitor_ads SET active = 1, ended = NULL, platforms = ?, thumb = COALESCE(?, thumb), variants = ?, last_seen = NOW() WHERE id = ?')->execute([implode(',', $a['platforms']) ?: null, $a['thumb'] ?: null, $a['variants'], (int)$row['id']]);
        }
    }
    // اللي وقف
    $act = $pdo->prepare('SELECT id, ext_id, body, title FROM competitor_ads WHERE competitor_id = ? AND active = 1'); $act->execute([$cid]); $stopped = [];
    $emptyKey = 'ca_empty_' . $cid;
    if (!$seen) { $n = (int)setting($emptyKey, '0') + 1; save_setting($emptyKey, (string)$n); if ($n < 2) $act = null; } else save_setting($emptyKey, '0');
    if ($act) foreach ($act->fetchAll() as $r) if (!isset($seen[$r['ext_id']])) { $pdo->prepare('UPDATE competitor_ads SET active = 0, ended = CURDATE() WHERE id = ?')->execute([(int)$r['id']]); $stopped[] = $r; }
    $ev = $pdo->prepare('INSERT INTO competitor_events (workspace_id, competitor_id, kind, title, detail) VALUES (?, ?, ?, ?, ?)');
    foreach (array_slice($new, 0, 10) as $a) $ev->execute([$wid, $cid, 'ad_new', mb_substr($a['title'] ?: mb_substr($a['body'], 0, 80), 0, 255), mb_substr($a['body'], 0, 500)]);
    if (count($new) > 10) $ev->execute([$wid, $cid, 'ad_new', t('و%s إعلان آخر', count($new) - 10), null]);
    if (!$first) foreach (array_slice($stopped, 0, 10) as $r) $ev->execute([$wid, $cid, 'ad_stop', mb_substr((string)($r['title'] ?: mb_substr((string)$r['body'], 0, 80)), 0, 255), null]);
    if ($new && function_exists('crm_notify') && module_enabled('crm')) crm_notify(null, null, 'competitor', '📣 ' . $c['name'] . ': ' . t('%s إعلان جديد', count($new)));
    $pdo->prepare('UPDATE competitors SET ads_check = NOW() WHERE id = ?')->execute([$cid]);
    ca_landing($c, 4);
}

/** 26: صفحات الهبوط: العنوان والوصف والأسعار الظاهرة (لحد $max صفحة جديدة في المرة) */
function ca_landing(array $c, int $max = 4): int
{
    $pdo = db(); $cid = (int)$c['id'];
    $st = $pdo->prepare('SELECT DISTINCT link FROM competitor_ads WHERE competitor_id = ? AND active = 1 AND link IS NOT NULL AND link <> ""'); $st->execute([$cid]);
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $u) {
        $clean = ca_clean_url((string)$u); if ($clean === '' || !preg_match('~^https?://~i', $clean)) continue;
        $h = md5($clean); $ex = $pdo->prepare('SELECT fetched_at FROM competitor_landing WHERE competitor_id = ? AND url_hash = ?'); $ex->execute([$cid, $h]); $f = $ex->fetchColumn();
        if ($f && strtotime((string)$f) > time() - 7 * 86400) continue;
        if ($n >= $max) break; $n++;
        $p = ca_fetch_page($clean);
        $pdo->prepare('INSERT INTO competitor_landing (competitor_id, url, url_hash, title, h1, descr, prices, ok, fetched_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE title = VALUES(title), h1 = VALUES(h1), descr = VALUES(descr), prices = VALUES(prices), ok = VALUES(ok), fetched_at = NOW()')
            ->execute([$cid, mb_substr($clean, 0, 700), $h, $p['title'], $p['h1'], $p['descr'], $p['prices'] ? implode(' · ', $p['prices']) : null, $p['ok'] ? 1 : 0]);
    }
    return $n;
}

/** الرابط من غير متغيرات التتبع (utm, fbclid...) عشان نفس الصفحة ماتتكررش */
function ca_clean_url(string $u): string
{
    $u = trim($u); if ($u === '') return '';
    if (preg_match('~^https?://l\.facebook\.com/l\.php\?u=([^&]+)~', $u, $m)) $u = urldecode($m[1]);
    $p = parse_url($u); if (empty($p['host'])) return '';
    $q = []; if (!empty($p['query'])) { parse_str($p['query'], $q); $q = array_filter($q, function ($k) { return !preg_match('/^(utm_|fbclid|gclid|ttclid|ref$|mc_)/i', (string)$k); }, ARRAY_FILTER_USE_KEY); }
    return ($p['scheme'] ?? 'https') . '://' . strtolower($p['host']) . (!empty($p['port']) ? ':' . (int)$p['port'] : '') . ($p['path'] ?? '/') . ($q ? '?' . http_build_query($q) : '');
}

function ca_fetch_page(string $url): array
{
    $out = ['ok' => false, 'title' => null, 'h1' => null, 'descr' => null, 'prices' => []];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MarketWatch/1.0)', CURLOPT_HTTPHEADER => ['Accept-Language: ar,en;q=0.8']]);
    $html = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code >= 400 || $html === '') return $out;
    $html = mb_substr($html, 0, 600000); $out['ok'] = true;
    $txt = function ($s) { return mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$s), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, 300) ?: null; };
    if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) $out['title'] = $txt($m[1]);
    if (preg_match('~<h1[^>]*>(.*?)</h1>~is', $html, $m)) $out['h1'] = $txt($m[1]);
    if (preg_match('~<meta[^>]+(?:name|property)=["\'](?:description|og:description)["\'][^>]+content=["\']([^"\']*)~i', $html, $m) || preg_match('~<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:name|property)=["\'](?:description|og:description)~i', $html, $m)) $out['descr'] = $txt($m[1]);
    $body = $txt(preg_replace('~<(script|style|noscript)[^>]*>.*?</\1>~is', ' ', $html)) ?? '';
    $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('~<(script|style|noscript)[^>]*>.*?</\1>~is', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    preg_match_all('/(?:(?:SAR|SR|EGP|AED|KWD|QAR|USD|\$|ر\.?\s?س|ريال|جنيه|ج\.?\s?م|درهم|دينار)\s?\d[\d,٬.]*|\d[\d,٬.]*\s?(?:SAR|SR|EGP|AED|KWD|QAR|USD|ر\.?\s?س|ريال|جنيه|ج\.?\s?م|درهم|دينار))/u', $plain, $pm);
    $out['prices'] = array_slice(array_values(array_unique(array_map('trim', $pm[0]))), 0, 6);
    unset($body);
    return $out;
}

/** تحديث يدوي */
function ca_refresh(array $c): bool { if (ca_pending((int)$c['id'])) return true; return ca_start($c); }
function ca_poll(array $c): bool { if (!ca_pending((int)$c['id'])) return false; try { return !ca_collect($c); } catch (Throwable $e) { save_setting('ca_run_' . (int)$c['id'], ''); return false; } }

/** التشغيل الدوري: يجمع اللي خلص، ويبدأ المنافسين اللي عدّى عليهم يوم (لحد $max) */
function ca_tick(int $max = 3): int
{
    if (!function_exists('cs_on') || !cs_on()) return 0;
    $n = 0; $touched = [];
    foreach (comp_list() as $c) if (ca_pending((int)$c['id'])) { $touched[(int)$c['id']] = 1; try { if (ca_collect($c)) $n++; } catch (Throwable $e) {} }
    $k = 0;
    foreach (comp_list() as $c) {
        if ($k >= $max) break;
        if (isset($touched[(int)$c['id']]) || ca_pending((int)$c['id']) || !ca_page($c) || ($c['ads_check'] && strtotime((string)$c['ads_check']) > time() - 20 * 3600)) continue;
        try { if (ca_start($c)) $k++; } catch (Throwable $e) {}
    }
    return $n;
}

/** أرقام تبويب الإعلانات */
function ca_report(array $c): array
{
    $pdo = db(); $cid = (int)$c['id'];
    $st = $pdo->prepare('SELECT * FROM competitor_ads WHERE competitor_id = ? ORDER BY active DESC, COALESCE(started, DATE(first_seen)) ASC'); $st->execute([$cid]); $rows = $st->fetchAll();
    $today = new DateTime('today');
    $map = function ($r) use ($today) {
        $s = $r['started'] ?: substr((string)$r['first_seen'], 0, 10); $e = $r['active'] ? $today : new DateTime((string)($r['ended'] ?: $r['last_seen'] ?: $s));
        $days = max(1, (int)(new DateTime($s))->diff($e)->days + 1);
        return ['id' => (int)$r['id'], 'active' => (int)$r['active'], 'start' => $s ? date('d/m/Y', strtotime($s)) : '', 'end' => $r['ended'] ? date('d/m/Y', strtotime($r['ended'])) : '', 'days' => $days,
            'platforms' => array_values(array_filter(explode(',', (string)$r['platforms']))), 'body' => (string)$r['body'], 'title' => (string)$r['title'], 'cta' => (string)$r['cta'], 'link' => (string)$r['link'],
            'host' => preg_replace('/^www\./', '', (string)parse_url(ca_clean_url((string)$r['link']), PHP_URL_HOST)), 'format' => (string)$r['format'], 'thumb' => (string)$r['thumb'], 'variants' => (int)$r['variants'],
            // جديد = بدأ خلال 7 أيام (من تاريخ الإعلان نفسه، مش من أول ما شفناه، عشان أول قراءة ماتطلعش كله جديد)
            'isNew' => $r['started'] ? strtotime($r['started']) >= strtotime('-7 days') : strtotime((string)$r['first_seen']) >= strtotime('-7 days'),
            'lib' => 'https://www.facebook.com/ads/library/?id=' . rawurlencode((string)$r['ext_id'])];
    };
    $all = array_map($map, $rows); $act = array_values(array_filter($all, function ($a) { return $a['active']; }));
    usort($act, function ($a, $b) { return $b['days'] <=> $a['days']; });
    $win = array_values(array_filter($act, function ($a) { return $a['days'] >= 30; }));
    $new = array_values(array_filter($act, function ($a) { return $a['isNew']; }));
    $pl = []; foreach ($act as $a) foreach ($a['platforms'] as $p) $pl[$p] = ($pl[$p] ?? 0) + 1; arsort($pl);
    $fmt = []; foreach ($act as $a) $fmt[$a['format']] = ($fmt[$a['format']] ?? 0) + 1; arsort($fmt);
    $cta = []; foreach ($act as $a) if ($a['cta'] !== '') $cta[$a['cta']] = ($cta[$a['cta']] ?? 0) + 1; arsort($cta);
    $lp = []; $ls = $pdo->prepare('SELECT * FROM competitor_landing WHERE competitor_id = ?'); $ls->execute([$cid]); $land = []; foreach ($ls->fetchAll() as $l) $land[$l['url_hash']] = $l;
    foreach ($act as $a) { $u = ca_clean_url($a['link']); if ($u === '') continue; $h = md5($u); if (!isset($lp[$h])) { $l = $land[$h] ?? null; $lp[$h] = ['url' => $u, 'n' => 0, 'title' => $l['title'] ?? null, 'h1' => $l['h1'] ?? null, 'descr' => $l['descr'] ?? null, 'prices' => $l && $l['prices'] ? explode(' · ', $l['prices']) : [], 'ok' => $l ? (int)$l['ok'] : null]; } $lp[$h]['n']++; }
    usort($lp, function ($a, $b) { return $b['n'] <=> $a['n']; });
    $started30 = count(array_filter($all, function ($a) { return strtotime(str_replace('/', '-', $a['start'])) >= strtotime('-30 days'); }));
    return ['page' => ca_page($c), 'on' => function_exists('cs_on') && cs_on(), 'pending' => ca_pending($cid), 'err' => (string)setting('ca_err_' . $cid, ''), 'checked' => $c['ads_check'] ? fmt_date($c['ads_check'], 'd/m h:i A') : '',
        'k' => ['active' => count($act), 'win' => count($win), 'new' => count($new), 'avgDays' => $act ? (int)round(array_sum(array_column($act, 'days')) / count($act)) : 0, 'started30' => $started30, 'stopped' => count($all) - count($act)],
        'active' => $act, 'win' => array_slice($win, 0, 20), 'new' => $new, 'platforms' => $pl, 'formats' => $fmt, 'ctas' => array_slice($cta, 0, 8, true), 'landing' => array_slice($lp, 0, 15),
        'stopped' => array_slice(array_values(array_filter($all, function ($a) { return !$a['active']; })), 0, 20)];
}
