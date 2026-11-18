<?php
/* المنافسات المرساة (اعتماد): مين كسب، بكام، ومين اتقدم — عن طريق أدوات Apify
 * ثم: بيانات التواصل مع الفايز (Google Maps ثم Apollo)، ومطابقة نطاق المنافسة مع خدماتنا من ملف الشركة، وعميل + عرض سعر بضغطة. */

function taw_actors(): array
{
    return [
        'automatic~etimad-awards-scraper' => ['name' => 'Etimad Award Results (automatic)', 'price' => 0.01, 'desc' => t('الأرخص: 0.01 دولار لكل ترسية. يبحث بكلمة واحدة في التشغيلة، فنشغّل تشغيلة لكل كلمة.')],
        'dottti~etimad-awards' => ['name' => 'Etimad Award Results (dottti)', 'price' => 0.03, 'desc' => t('0.03 دولار لكل ترسية، ومجرّبة أكثر. تبحث بكل الكلمات في تشغيلة واحدة.')],
    ];
}
function taw_actor(): string { $a = (string)setting('taw_actor_' . (int)ws('id'), ''); return isset(taw_actors()[$a]) ? $a : 'automatic~etimad-awards-scraper'; }
function taw_on(): bool { return function_exists('tnd_apify_on') && tnd_apify_on(); }
function taw_words(): array
{
    $w = function_exists('tnd_cfg') ? (array)(tnd_cfg()['words'] ?? []) : [];
    if (!$w && function_exists('tnd_kb_words')) $w = tnd_kb_words();
    return array_values(array_slice(array_filter(array_map('trim', $w)), 0, 40));
}
/** يبدأ تشغيلات البحث في الترسيات. $words: كلمات المجال، $days: آخر كام يوم، $max: أقصى عدد نتائج (= سقف التكلفة) */
function taw_start(array $words, int $days, int $max): array
{
    $wid = (int)ws('id'); $actor = taw_actor(); $words = array_values(array_slice(array_filter(array_map(function ($x) { return mb_substr(trim((string)$x), 0, 60); }, $words)), 0, 10));
    if (!$words) throw new RuntimeException(t('اكتب كلمة واحدة على الأقل (مجال عملك).'));
    $days = max(7, min(730, $days)); $max = max(10, min(2000, $max));
    $runs = json_decode((string)setting('taw_runs_' . $wid, '[]'), true) ?: [];
    if ($actor === 'dottti~etimad-awards') {
        $in = ['keywords' => $words, 'matchMode' => 'any', 'offerDeadlineFrom' => date('Y-m-d', time() - $days * 86400), 'offerDeadlineTo' => date('Y-m-d'), 'maxItems' => $max, 'maxTendersChecked' => $max * 4];
        $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode($actor) . '/runs', $in);
        $runs[] = ['id' => (string)($r['data']['id'] ?? ''), 'actor' => $actor, 'kw' => implode('، ', $words), 'at' => time()];
    } else {
        $per = max(5, (int)floor($max / count($words)));
        foreach ($words as $w) {
            $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode($actor) . '/runs', ['keyword' => $w, 'publishedWithinDays' => $days, 'maxResults' => $per, 'includeBidders' => true]);
            $runs[] = ['id' => (string)($r['data']['id'] ?? ''), 'actor' => $actor, 'kw' => $w, 'at' => time()];
        }
    }
    $runs = array_values(array_filter($runs, function ($x) { return $x['id'] !== ''; }));
    save_setting('taw_runs_' . $wid, json_encode(array_slice($runs, -30), JSON_UNESCAPED_UNICODE));
    log_activity('tenders.awards_search', implode(',', $words));
    return $runs;
}
/** يلم التشغيلات اللي خلصت. بيرجع [جديد، شغال] */
function taw_collect(): array
{
    $wid = (int)ws('id'); $runs = json_decode((string)setting('taw_runs_' . $wid, '[]'), true) ?: []; $left = []; $new = 0; $err = '';
    foreach ($runs as $run) {
        try { $r = tnd_apify_req('GET', '/v2/actor-runs/' . rawurlencode($run['id'])); } catch (Throwable $e) { $left[] = $run; $err = $e->getMessage(); continue; }
        $st = (string)($r['data']['status'] ?? '');
        if (in_array($st, ['READY', 'RUNNING'], true)) { $left[] = $run; continue; }
        if ($st !== 'SUCCEEDED' && $st !== 'TIMED-OUT') { $err = t('تشغيلة «%s» فشلت في Apify (%s).', $run['kw'], $st); continue; }
        $ds = (string)($r['data']['defaultDatasetId'] ?? '');
        for ($off = 0; $off < 5000 && $ds !== ''; $off += 500) {
            $items = tnd_apify_req('GET', '/v2/datasets/' . rawurlencode($ds) . '/items?clean=1&format=json&limit=500&offset=' . $off);
            if (!$items || !isset($items[0])) break;
            foreach ($items as $it) $new += taw_ingest($it, (string)$run['actor'], (string)$run['kw']);
            if (count($items) < 500) break;
        }
    }
    save_setting('taw_runs_' . $wid, json_encode($left, JSON_UNESCAPED_UNICODE));
    if ($err !== '') save_setting('taw_err_' . $wid, $err); elseif (!$left) save_setting('taw_err_' . $wid, '');
    return ['new' => $new, 'running' => count($left), 'error' => $err];
}
/** صف من أي أداة من الاتنين → صف (أو أكتر لو الترسية مقسّمة) في جدولنا */
function taw_ingest(array $x, string $actor, string $kw): int
{
    $wid = (int)ws('id'); $rows = [];
    $base = ['ref' => (string)($x['referenceNumber'] ?? ''), 'name' => (string)($x['title'] ?? $x['tenderName'] ?? ''), 'agency' => (string)($x['buyer'] ?? $x['agencyName'] ?? ''), 'branch' => (string)($x['branch'] ?? $x['branchName'] ?? ''),
        'activity' => (string)($x['activity'] ?? $x['tenderActivityName'] ?? ''), 'ttype' => (string)($x['tenderType'] ?? $x['tenderTypeName'] ?? ''), 'url' => (string)($x['url'] ?? ''),
        'published' => tnd_dt($x['publishedAt'] ?? $x['lastOfferPresentationDate'] ?? null), 'total' => (float)($x['awardedValueTotal'] ?? $x['totalAwardSar'] ?? 0), 'atype' => (string)($x['awardTypeArabic'] ?? $x['awardType'] ?? '')];
    $bid = []; foreach ((array)($x['bidders'] ?? []) as $b) if (is_array($b)) $bid[] = ['s' => (string)($b['supplier'] ?? $b['supplierName'] ?? ''), 'v' => (float)($b['offerValue'] ?? $b['offerSar'] ?? 0), 'r' => (string)($b['technicalResult'] ?? '')];
    if (isset($x['supplier'])) $rows[] = ['winner' => (string)$x['supplier'], 'award' => (float)($x['awardedValue'] ?? 0)];
    foreach ((array)($x['winners'] ?? []) as $w) if (is_array($w)) $rows[] = ['winner' => (string)($w['supplierName'] ?? ''), 'award' => (float)($w['awardSar'] ?? $w['offerSar'] ?? 0)];
    if (($x['awardStatus'] ?? '') === 'not_announced' || !$rows) return 0;
    $n = 0;
    $ins = db()->prepare('INSERT INTO tender_awards (workspace_id, ukey, ref, tender_name, agency, branch, activity, ttype, url, published_at, winner, award_sar, total_sar, award_type, bidders, bidder_count, actor, kw)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE award_sar = VALUES(award_sar), total_sar = VALUES(total_sar), bidders = VALUES(bidders), bidder_count = VALUES(bidder_count)');
    foreach ($rows as $w) {
        if (trim($w['winner']) === '') continue;
        $uk = sha1(($base['ref'] !== '' ? $base['ref'] : $base['url']) . '|' . mb_strtolower(trim($w['winner'])));
        $ins->execute([$wid, $uk, mb_substr($base['ref'], 0, 40), mb_substr($base['name'], 0, 500), mb_substr($base['agency'], 0, 255), mb_substr($base['branch'], 0, 255), mb_substr($base['activity'], 0, 255), mb_substr($base['ttype'], 0, 80),
            mb_substr($base['url'], 0, 600), $base['published'], mb_substr($w['winner'], 0, 255), $w['award'], $base['total'] ?: $w['award'], mb_substr($base['atype'], 0, 40), json_encode($bid, JSON_UNESCAPED_UNICODE), count($bid), $actor, mb_substr($kw, 0, 120)]);
        if ($ins->rowCount() === 1) $n++;
    }
    return $n;
}
function taw_list(string $q = '', int $limit = 300): array
{
    $w = 'workspace_id = ?'; $p = [(int)ws('id')];
    if ($q !== '') { $w .= ' AND (tender_name LIKE ? OR winner LIKE ? OR agency LIKE ? OR activity LIKE ?)'; for ($i = 0; $i < 4; $i++) $p[] = '%' . $q . '%'; }
    $st = db()->prepare("SELECT * FROM tender_awards WHERE $w ORDER BY published_at DESC, id DESC LIMIT " . (int)$limit); $st->execute($p);
    return $st->fetchAll();
}
function taw_get(int $id): ?array { $st = db()->prepare('SELECT * FROM tender_awards WHERE id = ? AND workspace_id = ?'); $st->execute([$id, (int)ws('id')]); return $st->fetch() ?: null; }

/** مطابقة نطاق المنافسة مع خدماتنا (ملف الشركة) بالكلمات المشتركة — من غير ذكاء اصطناعي */
function taw_match(array $a): array
{
    $kb = function_exists('kb_get') ? kb_get((int)ws('id')) : [];
    $rows = (array)($kb['services']['rows'] ?? []); if (!$rows) return [];
    $tt = kb_terms($a['tender_name'] . ' ' . $a['activity']); if (!$tt) return [];
    $out = [];
    foreach ($rows as $r) {
        $name = (string)($r['name'] ?? reset($r) ?? ''); if (trim($name) === '') continue;
        $st = kb_terms($name . ' ' . (string)($r['details'] ?? '') . ' ' . (string)($r['desc'] ?? ''));
        $hit = 0; foreach ($tt as $w) foreach ($st as $x) if ($x === $w || (mb_strlen($w) > 4 && (mb_strpos($x, $w) === 0 || mb_strpos($w, $x) === 0))) { $hit++; break; }
        if ($hit) $out[] = ['name' => $name, 'price' => (string)($r['price'] ?? ''), 'score' => $hit];
    }
    usort($out, function ($x, $y) { return $y['score'] <=> $x['score']; });
    return array_slice($out, 0, 8);
}
/** بيانات التواصل مع الشركة الفايزة: Google Maps (تليفون، موقع، عنوان) ثم Apollo بالموقع لو مربوط */
function taw_contact(array $a): array
{
    $c = json_decode((string)($a['contact'] ?? ''), true) ?: [];
    if (!empty($c['at']) && $c['at'] > time() - 30 * 86400) return $c;
    $c = ['at' => time(), 'phone' => '', 'website' => '', 'address' => '', 'maps' => '', 'rating' => null, 'source' => '', 'apollo' => null];
    try {
        $items = tnd_apify_req('POST', '/v2/acts/' . rawurlencode('compass~crawler-google-places') . '/run-sync-get-dataset-items?timeout=120', ['searchStringsArray' => [$a['winner']], 'locationQuery' => 'Saudi Arabia', 'maxCrawledPlacesPerSearch' => 1, 'language' => 'ar', 'skipClosedPlaces' => false]);
        $p = is_array($items) && isset($items[0]) ? $items[0] : null;
        if ($p) { $c['phone'] = (string)($p['phoneUnformatted'] ?? $p['phone'] ?? ''); $c['website'] = (string)($p['website'] ?? ''); $c['address'] = (string)($p['address'] ?? ''); $c['maps'] = (string)($p['url'] ?? ''); $c['rating'] = $p['totalScore'] ?? null; $c['place'] = (string)($p['title'] ?? ''); $c['source'] = 'maps'; }
    } catch (Throwable $e) { $c['error'] = $e->getMessage(); }
    if ($c['website'] !== '' && function_exists('crm_has_key') && crm_has_key()) {
        $dom = preg_replace('/^www\./', '', strtolower((string)parse_url((preg_match('~^https?://~i', $c['website']) ? '' : 'https://') . $c['website'], PHP_URL_HOST)));
        if ($dom !== '') { try { $o = (array)(crm_api('/organizations/enrich', ['domain' => $dom], null, 'GET')['organization'] ?? []);
            if (!empty($o['id'])) $c['apollo'] = ['id' => (string)$o['id'], 'domain' => $dom, 'linkedin' => (string)($o['linkedin_url'] ?? ''), 'emp' => (int)($o['estimated_num_employees'] ?? 0), 'phone' => (string)($o['phone'] ?? ($o['primary_phone']['number'] ?? ''))]; } catch (Throwable $e) {} }
    }
    db()->prepare('UPDATE tender_awards SET contact = ? WHERE id = ?')->execute([json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$a['id']]);
    return $c;
}
/** الشركة الفايزة → عميل في CRM + صفقة بعرض سعر من الخدمات المطابقة */
function taw_to_lead(array $a): array
{
    $c = json_decode((string)($a['contact'] ?? ''), true) ?: []; $m = taw_match($a);
    $note = t('فازت بمنافسة «%s» من %s بقيمة %s ريال.', $a['tender_name'], $a['agency'], number_format((float)$a['award_sar'])) . ($a['url'] ? "\n" . $a['url'] : '');
    if ($m) $note .= "\n" . t('خدماتنا المطابقة: %s', implode('، ', array_column($m, 'name')));
    $r = crm_lead_upsert(['name' => $a['winner'], 'company' => $a['winner'], 'phone' => (string)($c['phone'] ?? ''), 'country' => 'SA', 'source' => 'tender_award', 'ext_id' => 'aw:' . $a['ukey'], 'note' => $note, 'src_url' => (string)($c['website'] ?? '')]);
    $lid = (int)($r['id'] ?? 0); if (!$lid) throw new RuntimeException(t('تعذّر إضافة العميل.'));
    $items = []; $sum = 0.0;
    foreach ($m as $s) { $pr = (float)preg_replace('/[^\d.]/', '', strtr((string)$s['price'], ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', ',' => ''])); $items[] = ['t' => $s['name'], 'q' => 1, 'p' => $pr]; $sum += $pr; }
    db()->prepare("INSERT INTO crm_deals (workspace_id, lead_id, owner_id, title, value, status) VALUES (?, ?, ?, ?, ?, 'open')")->execute([(int)ws('id'), $lid, (int)(current_user()['id'] ?? 0) ?: null, mb_substr(t('عرض لـ %s', $a['winner']), 0, 255), $sum]);
    $did = (int)db()->lastInsertId();
    if ($items) save_setting('crm_deal_items_' . $did, json_encode($items, JSON_UNESCAPED_UNICODE));
    crm_act_add($lid, 'note', '🏛 ' . $note);
    db()->prepare('UPDATE tender_awards SET lead_id = ? WHERE id = ?')->execute([$lid, (int)$a['id']]);
    log_activity('tenders.award_lead', $a['id'] . ' ' . $a['winner']);
    return ['lead' => $lid, 'deal' => $did];
}
