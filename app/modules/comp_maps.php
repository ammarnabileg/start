<?php
/**
 * سمعة المنافس على خرائط جوجل (من غير ذكاء اصطناعي): التقييم وعدد المراجعات لكل فرع يوميًا، تنبيه بالتقييم السلبي،
 * نسبة وسرعة رده على المراجعات، أكثر الشكاوى تكرارًا، وفروعه. المصدر: Apify (compass).
 */

function cg_actor_places(): string { return 'compass~crawler-google-places'; }
function cg_actor_reviews(): string { return 'compass~google-maps-reviews-scraper'; }

/** روابط فروعه على الخرائط (سطر لكل فرع) */
function cg_links(array $c): array
{
    return array_values(array_unique(array_filter(array_map('trim', explode("\n", (string)($c['maps'] ?? ''))), function ($u) { return (bool)preg_match('~^https?://(www\.)?(google\.[a-z.]+/maps|maps\.google\.[a-z.]+|maps\.app\.goo\.gl|goo\.gl/maps)~i', $u); })));
}

function cg_on(): bool { return function_exists('cs_on') && cs_on(); }
function cg_pending(int $cid): bool { $r = json_decode((string)setting('cg_run_' . $cid, ''), true); return !empty($r['runs']) && (int)($r['at'] ?? 0) > time() - 1800; }

/** يبدأ: read = بيانات الفروع (+ المراجعات كل يومين)، find = البحث عن فروعه بالاسم */
function cg_start(array $c, string $mode = 'read'): bool
{
    if (!cg_on()) return false; $runs = [];
    if ($mode === 'find') {
        $cc = function_exists('ws_country') ? strtoupper((string)ws_country()) : 'SA'; $cn = countries()[$cc]['en'] ?? 'Saudi Arabia';
        $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode(cg_actor_places()) . '/runs?memory=1024', ['searchStringsArray' => [(string)$c['name']], 'locationQuery' => $cn, 'maxCrawledPlacesPerSearch' => 15, 'language' => 'ar', 'maxReviews' => 0, 'maxImages' => 0]);
        if (!empty($r['data']['id'])) $runs['find'] = (string)$r['data']['id'];
    } else {
        $links = cg_links($c); if (!$links) return false; $start = array_map(function ($u) { return ['url' => $u]; }, $links);
        $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode(cg_actor_places()) . '/runs?memory=1024', ['startUrls' => $start, 'language' => 'ar', 'maxReviews' => 0, 'maxImages' => 0]);
        if (!empty($r['data']['id'])) $runs['places'] = (string)$r['data']['id'];
        // المراجعات: كل يومين (بتتحاسب بالمراجعة)
        $last = db()->prepare('SELECT MIN(COALESCE(reviews_at, "2000-01-01")) FROM competitor_places WHERE competitor_id = ?'); $last->execute([(int)$c['id']]); $la = (string)$last->fetchColumn();
        if ($la === '' || strtotime($la) < time() - 2 * 86400) {
            $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode(cg_actor_reviews()) . '/runs?memory=1024', ['startUrls' => $start, 'maxReviews' => 40, 'reviewsSort' => 'newest', 'language' => 'ar', 'personalData' => true]);
            if (!empty($r['data']['id'])) $runs['reviews'] = (string)$r['data']['id'];
        }
    }
    if (!$runs) return false;
    save_setting('cg_run_' . (int)$c['id'], json_encode(['runs' => $runs, 'at' => time(), 'mode' => $mode])); return true;
}

/** يجمع اللي خلص. true = خلص */
function cg_collect(array $c): bool
{
    $key = 'cg_run_' . (int)$c['id']; $run = json_decode((string)setting($key, ''), true); if (empty($run['runs'])) return true;
    $items = []; $err = '';
    foreach ($run['runs'] as $k => $id) {
        $r = tnd_apify_req('GET', '/v2/actor-runs/' . rawurlencode($id)); $st = (string)($r['data']['status'] ?? '');
        if (in_array($st, ['READY', 'RUNNING'], true) && (int)$run['at'] > time() - 1800) return false;
        if ($st !== 'SUCCEEDED') { $err = t('تعذّرت قراءة خرائط جوجل (%s)', $st ?: '?'); continue; }
        $items[$k] = tnd_apify_req('GET', '/v2/datasets/' . rawurlencode((string)$r['data']['defaultDatasetId']) . '/items?clean=1&limit=500');
    }
    save_setting($key, ''); save_setting('cg_err_' . (int)$c['id'], $err);
    if (isset($items['find'])) { cg_found($c, $items['find']); return true; }
    if (isset($items['places'])) cg_store_places($c, $items['places']);
    if (isset($items['reviews'])) cg_store_reviews($c, $items['reviews']);
    return true;
}

function cg_norm(string $s): string { return trim(preg_replace('/\s+/u', ' ', mb_strtolower(strtr(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s), ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي'])))); }

/** نتايج البحث بالاسم: الأماكن اللي اسمها فيه اسم المنافس بتتضاف كفروع، وبعدين تتقري */
function cg_found(array $c, array $items): int
{
    $name = cg_norm((string)$c['name']); $words = array_filter(explode(' ', $name), function ($w) { return mb_strlen($w) >= 3; });
    $links = cg_links($c); $have = []; foreach ($links as $u) $have[$u] = 1; $n = 0;
    foreach ($items as $x) {
        $t = cg_norm((string)($x['title'] ?? '')); if ($t === '') continue;
        $hit = $name !== '' && (mb_strpos($t, $name) !== false || mb_strpos($name, $t) !== false); if (!$hit && $words) { $m = 0; foreach ($words as $w) if (mb_strpos($t, $w) !== false) $m++; $hit = $m >= max(1, (int)ceil(count($words) * 0.6)); }
        $u = (string)($x['url'] ?? ''); if (!$hit || $u === '' || isset($have[$u])) continue;
        $have[$u] = 1; $links[] = $u; $n++;
    }
    db()->prepare('UPDATE competitors SET maps = ? WHERE id = ?')->execute([implode("\n", array_slice($links, 0, 30)) ?: null, (int)$c['id']]);
    save_setting('cg_found_' . (int)$c['id'], (string)$n);
    if ($n) { $c2 = comp_get((int)$c['id']); if ($c2) { try { cg_start($c2, 'read'); } catch (Throwable $e) {} } }
    return $n;
}

/** بيانات الفروع + صف اليوم + حدث لو التقييم اتغيّر */
function cg_store_places(array $c, array $items): void
{
    $pdo = db(); $cid = (int)$c['id']; $wid = (int)$c['workspace_id']; $seen = [];
    $ev = $pdo->prepare('INSERT INTO competitor_events (workspace_id, competitor_id, kind, title, detail) VALUES (?, ?, ?, ?, ?)');
    foreach ($items as $x) {
        $pid = (string)($x['placeId'] ?? $x['place_id'] ?? ''); if ($pid === '') continue; $seen[$pid] = 1;
        $rating = isset($x['totalScore']) && $x['totalScore'] !== null ? round((float)$x['totalScore'], 1) : null; $cnt = isset($x['reviewsCount']) ? (int)$x['reviewsCount'] : null;
        $hours = []; foreach ((array)($x['openingHours'] ?? []) as $h) if (!empty($h['day'])) $hours[] = ['day' => (string)$h['day'], 'hours' => (string)($h['hours'] ?? '')];
        $old = $pdo->prepare('SELECT id, rating FROM competitor_places WHERE competitor_id = ? AND place_id = ?'); $old->execute([$cid, $pid]); $o = $old->fetch();
        $vals = [mb_substr((string)($x['title'] ?? ''), 0, 255), mb_substr((string)($x['address'] ?? ''), 0, 500) ?: null, mb_substr((string)($x['categoryName'] ?? ''), 0, 190) ?: null, $x['location']['lat'] ?? null, $x['location']['lng'] ?? null, $rating, $cnt, $hours ? json_encode($hours, JSON_UNESCAPED_UNICODE) : null, mb_substr((string)($x['url'] ?? ''), 0, 700) ?: null, !empty($x['permanentlyClosed']) || !empty($x['temporarilyClosed']) ? 1 : 0];
        if ($o) { $pdo->prepare('UPDATE competitor_places SET title = ?, address = ?, category = ?, lat = ?, lng = ?, rating = ?, reviews_count = ?, hours = ?, url = ?, closed = ?, last_check = NOW() WHERE id = ?')->execute(array_merge($vals, [(int)$o['id']])); $rowId = (int)$o['id'];
            if ($o['rating'] !== null && $rating !== null && abs((float)$o['rating'] - $rating) >= 0.1) $ev->execute([$wid, $cid, 'rating', mb_substr($vals[0], 0, 255), $o['rating'] . ' → ' . $rating]); }
        else { $pdo->prepare('INSERT INTO competitor_places (title, address, category, lat, lng, rating, reviews_count, hours, url, closed, last_check, workspace_id, competitor_id, place_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)')->execute(array_merge($vals, [$wid, $cid, $pid])); $rowId = (int)$pdo->lastInsertId(); }
        if ($rating !== null || $cnt !== null) $pdo->prepare('REPLACE INTO competitor_place_days (place_row, day, rating, reviews_count) VALUES (?, ?, ?, ?)')->execute([$rowId, date('Y-m-d'), $rating, $cnt]);
    }
    // فرع اتشال رابطه: بيتشال (بس لو القراءة رجّعت بيانات فعلًا)
    if ($seen) { $all = $pdo->prepare('SELECT id, place_id FROM competitor_places WHERE competitor_id = ?'); $all->execute([$cid]); foreach ($all->fetchAll() as $r) if (!isset($seen[$r['place_id']])) $pdo->prepare('DELETE FROM competitor_places WHERE id = ?')->execute([(int)$r['id']]); }
}

/** المراجعات: الجديدة تتضاف، والرد بيتحدّث، والسلبية الجديدة بتعمل تنبيه */
function cg_store_reviews(array $c, array $items): void
{
    $pdo = db(); $cid = (int)$c['id']; $wid = (int)$c['workspace_id'];
    $pl = $pdo->prepare('SELECT id, place_id, title, reviews_at FROM competitor_places WHERE competitor_id = ?'); $pl->execute([$cid]); $places = []; foreach ($pl->fetchAll() as $p) $places[$p['place_id']] = $p;
    $ins = $pdo->prepare('INSERT INTO competitor_reviews (place_row, ext_id, stars, text, author, at, reply, reply_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE reply = VALUES(reply), reply_at = VALUES(reply_at)');
    $ev = $pdo->prepare('INSERT INTO competitor_events (workspace_id, competitor_id, kind, title, detail) VALUES (?, ?, ?, ?, ?)'); $bad = 0; $touched = [];
    foreach ($items as $x) {
        $p = $places[(string)($x['placeId'] ?? '')] ?? null; if (!$p) continue; $rid = (string)($x['reviewId'] ?? $x['id'] ?? ''); if ($rid === '') continue;
        $stars = isset($x['stars']) ? (int)$x['stars'] : (isset($x['rating']) ? (int)$x['rating'] : null); $text = (string)($x['text'] ?? $x['textTranslated'] ?? '');
        $ins->execute([(int)$p['id'], mb_substr($rid, 0, 190), $stars, mb_substr($text, 0, 3000), mb_substr((string)($x['name'] ?? ''), 0, 190) ?: null, cs_time($x['publishedAtDate'] ?? null), ($x['responseFromOwnerText'] ?? '') !== '' ? mb_substr((string)$x['responseFromOwnerText'], 0, 3000) : null, cs_time($x['responseFromOwnerDate'] ?? null)]);
        $isNew = $ins->rowCount() === 1; $touched[(int)$p['id']] = 1;
        // 37: تقييم سلبي جديد (مش في أول قراءة للفرع)
        if ($isNew && !empty($p['reviews_at']) && $stars !== null && $stars <= 2) { $bad++; if ($bad <= 5) $ev->execute([$wid, $cid, 'rev_bad', mb_substr((string)$p['title'], 0, 255), $stars . '★ ' . mb_substr($text, 0, 400)]); }
    }
    foreach (array_keys($touched) as $rowId) $pdo->prepare('UPDATE competitor_places SET reviews_at = NOW() WHERE id = ?')->execute([$rowId]);
    if ($bad && function_exists('crm_notify') && module_enabled('crm')) crm_notify(null, null, 'competitor', '⭐ ' . $c['name'] . ': ' . t('%s تقييم سلبي جديد على خرائط جوجل', $bad));
}

function cg_refresh(array $c): bool { if (cg_pending((int)$c['id'])) return true; return cg_start($c, 'read'); }
function cg_poll(array $c): bool { if (!cg_pending((int)$c['id'])) return false; try { return !cg_collect($c); } catch (Throwable $e) { save_setting('cg_run_' . (int)$c['id'], ''); return false; } }

/** التشغيل الدوري: يجمع اللي خلص، ويبدأ المنافسين اللي ليهم فروع ومر عليهم يوم (لحد $max) */
function cg_tick(int $max = 3): int
{
    if (!cg_on()) return 0; $n = 0; $touched = [];
    foreach (comp_list() as $c) if (cg_pending((int)$c['id'])) { $touched[(int)$c['id']] = 1; try { if (cg_collect($c)) $n++; } catch (Throwable $e) {} }
    $k = 0;
    foreach (comp_list() as $c) {
        if ($k >= $max) break; if (isset($touched[(int)$c['id']]) || cg_pending((int)$c['id']) || !cg_links($c)) continue;
        $lc = db()->prepare('SELECT MIN(COALESCE(last_check, "2000-01-01")) FROM competitor_places WHERE competitor_id = ?'); $lc->execute([(int)$c['id']]); $l = (string)$lc->fetchColumn();
        if ($l !== '' && strtotime($l) > time() - 20 * 3600) continue;
        try { if (cg_start($c, 'read')) $k++; } catch (Throwable $e) {}
    }
    return $n;
}

/** كلمات الشكوى في المراجعات (فوق كلمات التعليقات) */
function cg_neg_words(): array
{
    return array_merge(function_exists('cs_neg_words') ? cs_neg_words() : [], ['انتظار', 'انتظرت', 'زحمه', 'زحمة', 'بارد', 'بارده', 'نظافه', 'نظافة', 'وسخ', 'مو نظيف', 'غرور', 'سوء تعامل', 'ما يردون', 'مافي رد', 'الحجز', 'موعد', 'خربوا', 'تالف', 'ريحه', 'wait', 'waiting', 'cold', 'unclean', 'staff']);
}

/** أرقام تبويب التقييمات */
function cg_report(array $c): array
{
    $pdo = db(); $cid = (int)$c['id'];
    $st = $pdo->prepare('SELECT * FROM competitor_places WHERE competitor_id = ? ORDER BY reviews_count DESC'); $st->execute([$cid]); $pl = $st->fetchAll();
    $P = []; $sumW = 0; $sumN = 0; $ids = [];
    foreach ($pl as $p) {
        $ids[] = (int)$p['id'];
        $d = $pdo->prepare('SELECT day, rating, reviews_count FROM competitor_place_days WHERE place_row = ? AND day >= ? ORDER BY day'); $d->execute([(int)$p['id'], date('Y-m-d', strtotime('-90 days'))]); $ser = $d->fetchAll();
        $base = null; $lim = date('Y-m-d', strtotime('-30 days')); foreach ($ser as $r) if ($r['day'] <= $lim) $base = $r; if (!$base && count($ser) > 1) $base = $ser[0];
        $rt = $p['rating'] !== null ? (float)$p['rating'] : null; $cn = $p['reviews_count'] !== null ? (int)$p['reviews_count'] : null;
        if ($rt !== null && $cn) { $sumW += $rt * $cn; $sumN += $cn; }
        $P[] = ['id' => (int)$p['id'], 'title' => (string)$p['title'], 'address' => (string)$p['address'], 'category' => (string)$p['category'], 'rating' => $rt, 'count' => $cn, 'closed' => (int)$p['closed'], 'url' => (string)$p['url'],
            'lat' => $p['lat'] !== null ? (float)$p['lat'] : null, 'lng' => $p['lng'] !== null ? (float)$p['lng'] : null, 'hours' => json_decode((string)$p['hours'], true) ?: [],
            'dRating' => $base && $base['rating'] !== null && $rt !== null && count($ser) > 1 ? round($rt - (float)$base['rating'], 1) : null, 'dCount' => $base && $base['reviews_count'] !== null && $cn !== null && count($ser) > 1 ? $cn - (int)$base['reviews_count'] : null,
            'series' => array_map(function ($r) { return [$r['day'], $r['rating'] !== null ? (float)$r['rating'] : null]; }, $ser), 'checked' => $p['last_check'] ? fmt_date($p['last_check'], 'd/m h:i A') : ''];
    }
    $rev = []; if ($ids) { $q = $pdo->query('SELECT r.*, p.title FROM competitor_reviews r JOIN competitor_places p ON p.id = r.place_row WHERE r.place_row IN (' . implode(',', $ids) . ') AND (r.at IS NULL OR r.at >= NOW() - INTERVAL 90 DAY) ORDER BY r.at DESC LIMIT 1000'); $rev = $q->fetchAll(); }
    $dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]; $rep = 0; $mins = []; $words = []; $bad = []; $norm = function ($t) { return mb_strtolower(strtr($t, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي'])); };
    $lex = []; foreach (cg_neg_words() as $w) if (!isset($lex[$norm($w)])) $lex[$norm($w)] = $w; // العرض بأول إملاء (بالهمزة)
    foreach ($rev as $r) {
        $s = (int)$r['stars']; if ($s >= 1 && $s <= 5) $dist[$s]++;
        if ($r['reply'] !== null && $r['reply'] !== '') { $rep++; if ($r['reply_at'] && $r['at']) $mins[] = max(0, (strtotime($r['reply_at']) - strtotime($r['at'])) / 60); }
        if ($s && $s <= 3 && trim((string)$r['text']) !== '') { $nt = $norm((string)$r['text']); $hit = [];
            foreach ($lex as $w => $ow) if ($w !== '' && preg_match('/(^|[^\p{L}])(?:و|ف|ب|ال|وال|بال)?' . preg_quote($w, '/') . '($|[^\p{L}])/u', $nt)) $hit[$w] = $ow;
            // «سيئة» جوه «خدمة سيئة»: نعدّ العبارة الأطول بس
            foreach ($hit as $w => $ow) { foreach ($hit as $w2 => $_) if ($w2 !== $w && mb_strpos($w2, $w) !== false) continue 2; $words[$ow] = ($words[$ow] ?? 0) + 1; } }
        if ($s && $s <= 2 && count($bad) < 15) $bad[] = ['branch' => (string)$r['title'], 'stars' => $s, 'text' => mb_substr((string)$r['text'], 0, 400), 'who' => (string)$r['author'], 'at' => $r['at'] ? fmt_date($r['at'], 'd/m/Y') : '', 'reply' => (string)$r['reply']];
    }
    sort($mins); arsort($words);
    return ['on' => cg_on(), 'links' => cg_links($c), 'pending' => cg_pending($cid), 'err' => (string)setting('cg_err_' . $cid, ''), 'found' => setting('cg_found_' . $cid, null),
        'branches' => $P, 'avg' => $sumN ? round($sumW / $sumN, 2) : null, 'total' => array_sum(array_column($P, 'count')),
        'rev' => ['n' => count($rev), 'dist' => $dist, 'replied' => $rep, 'rate' => $rev ? (int)round($rep / count($rev) * 100) : null, 'median' => $mins ? (int)round($mins[(int)floor((count($mins) - 1) / 2)]) : null, 'words' => array_slice($words, 0, 12, true), 'bad' => $bad]];
}
