<?php
/**
 * المنافسات الحكومية (اعتماد): بيانات عامة على مستوى المنصة، وكل شركة بتشوف اللي بيطابق كلماتها بس.
 * البيانات بتيجي من تكامل "منافسات اعتماد (Apify)" في التكاملات، مرة في اليوم من التشغيل الدوري.
 * المطابقة من غير ذكاء اصطناعي: كلمات وعبارات في اسم المنافسة ونشاطها، مع توحيد الحروف العربية.
 */

/** توحيد النص العربي للمطابقة: من غير تشكيل، أ إ آ ← ا، ة ← ه، ى ← ي */
function tnd_norm(string $s): string
{
    $s = mb_strtolower($s);
    $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);
    $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
    return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s)));
}

function tnd_dt($v): ?string
{
    $v = trim((string)$v); if ($v === '' || strpos($v, '0001-') === 0) return null;
    try { $d = new DateTime($v, new DateTimeZone('Asia/Riyadh')); return $d->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'); } catch (Throwable $e) { return null; }
}

/** حفظ دفعة منافسات (بالشكل اللي اعتماد بيرجعه). بيرجع ['new' => n, 'updated' => n, 'skipped' => n] */
function tnd_ingest(string $src, array $items): array
{
    $pdo = db(); $new = 0; $up = 0; $skip = 0;
    $sel = $pdo->prepare('SELECT id FROM tenders WHERE src = ? AND ext_id = ?');
    $ins = $pdo->prepare('INSERT INTO tenders (src, ext_id, ref, id_str, name, agency, branch, type, activity, booklet, published_at, inquiry_at, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $upd = $pdo->prepare('UPDATE tenders SET ref = ?, id_str = ?, name = ?, agency = ?, branch = ?, type = ?, activity = ?, booklet = ?, published_at = COALESCE(?, published_at), inquiry_at = ?, deadline = ?, last_seen = NOW() WHERE id = ?');
    foreach ($items as $x) {
        if (!is_array($x)) { $skip++; continue; }
        $ext = (int)($x['tenderId'] ?? 0); $name = trim((string)($x['tenderName'] ?? ''));
        if ($ext <= 0 || $name === '') { $skip++; continue; }
        $v = [mb_substr((string)($x['referenceNumber'] ?? ''), 0, 40) ?: null, mb_substr((string)($x['tenderIdString'] ?? ''), 0, 80) ?: null, mb_substr($name, 0, 500),
            mb_substr(trim((string)($x['agencyName'] ?? '')), 0, 255) ?: null, mb_substr(trim((string)($x['branchName'] ?? '')), 0, 255) ?: null, mb_substr(trim((string)($x['tenderTypeName'] ?? '')), 0, 80) ?: null,
            mb_substr(trim((string)($x['tenderActivityName'] ?? '')), 0, 255) ?: null, is_numeric($x['condetionalBookletPrice'] ?? null) ? (float)$x['condetionalBookletPrice'] : null,
            tnd_dt($x['submitionDate'] ?? ''), tnd_dt($x['lastEnqueriesDate'] ?? ''), tnd_dt($x['lastOfferPresentationDate'] ?? '')];
        $sel->execute([$src, $ext]); $id = $sel->fetchColumn();
        if ($id) { $upd->execute(array_merge($v, [(int)$id])); $up++; }
        else { $ins->execute(array_merge([$src, $ext], $v)); $new++; }
    }
    return ['new' => $new, 'updated' => $up, 'skipped' => $skip];
}

/** رابط المنافسة على اعتماد (صفحة الزوار، من غير تسجيل) */
function tnd_url(array $t): string
{
    return $t['id_str'] ? 'https://tenders.etimad.sa/Tender/DetailsForVisitor?STenderId=' . rawurlencode((string)$t['id_str']) : 'https://tenders.etimad.sa/Tender/AllTendersForVisitor?TenderRefNo=' . rawurlencode((string)$t['ref']);
}

/** مجموعات كلمات جاهزة حسب المجال (نقطة بداية، والشركة بتعدّل) */
function tnd_presets(): array
{
    return [
        'marketing' => [t('تسويق وإعلان'), ['تسويق', 'تسويقيه', 'اعلان', 'اعلانات', 'اعلاميه', 'حملات', 'حمله اعلاميه', 'هويه بصريه', 'تواصل اجتماعي', 'منصات التواصل', 'محتوى', 'علاقات عامه']],
        'events' => [t('فعاليات ومعارض'), ['فعاليه', 'فعاليات', 'معرض', 'مؤتمر', 'حفل', 'احتفال', 'مهرجان', 'ملتقى', 'تنظيم']],
        'design' => [t('تصميم وإنتاج'), ['تصميم', 'تصوير', 'انتاج', 'فيديو', 'افلام', 'موشن', 'مطبوعات', 'طباعه', 'لوحات']],
        'tech' => [t('مواقع وتطبيقات'), ['موقع الكتروني', 'بوابه', 'تطبيق', 'منصه رقميه', 'تطوير', 'برمجه', 'تحول رقمي']],
    ];
}

/** إعدادات الشركة: كلمات، استبعاد، أقل أيام متبقية */
function tnd_cfg(): array
{
    $v = json_decode((string)setting('tnd_cfg_' . (int)ws('id'), ''), true);
    return (is_array($v) ? $v : []) + ['words' => [], 'not' => [], 'min_days' => 2];
}

function tnd_words_clean($raw, int $max = 60): array
{
    $out = [];
    foreach (preg_split('/[\n,،]+/u', (string)$raw) as $w) { $w = mb_substr(trim($w), 0, 40); if ($w !== '' && mb_strlen(tnd_norm($w)) >= 2) $out[tnd_norm($w)] = $w; }
    return array_values(array_slice($out, 0, $max));
}

/** مفتاح المطابقة: نص موحد ومن غير "ال" و"وال" و"بال" و"لل" في أول كل كلمة، عشان "اعلان" تطابق "الاعلانات" */
function tnd_key(string $s): string
{
    $out = [];
    foreach (explode(' ', tnd_norm($s)) as $w) { if ($w === '') continue; do { $w0 = $w; $w = preg_replace('/^(وال|بال|فال|كال|لل|ال)(?=\p{L}{2})/u', '', $w); } while ($w !== $w0); $out[] = $w; }
    return implode(' ', $out);
}

/** المنافسة دي بتطابق الشركة؟ بيرجع الكلمات اللي طابقت (فاضي = لأ) */
function tnd_hits(array $t, array $words, array $not): array
{
    $txt = $t['name'] . ' ' . $t['activity'];
    $hayK = ' ' . tnd_key($txt) . ' '; $hayN = ' ' . tnd_norm($txt) . ' ';
    // من أول الكلمة، ومسموح بحرف واحد قبلها (و، ب، ف، ل، ك): "اعلان" تطابق "واعلانيه" ومش جوه كلمة تانية.
    // بنجرب مرتين: من غير "ال" (اعلان ← الاعلانات) وبالنص زي ما هو (الكتروني ← الالكتروني)
    $has = function (string $w) use ($hayK, $hayN) {
        foreach ([[tnd_key($w), $hayK], [tnd_norm($w), $hayN]] as [$n, $h]) if ($n !== '' && preg_match('/ (?:[وبفلك]|ال|وال|بال|لل)?' . preg_quote($n, '/') . '/u', $h)) return true;
        return false;
    };
    foreach ($not as $w) if ($has($w)) return [];
    $hits = [];
    foreach ($words as $w) if ($has($w)) $hits[] = $w; // من أول الكلمة: "اعلان" تطابق "اعلانات" ومش جوه كلمة تانية
    return $hits;
}

/** المنافسات النشطة المطابقة (أو كلها لو $all) مع حالة الشركة. الأقرب ميعادًا الأول */
function tnd_list(bool $all = false, int $limit = 400): array
{
    $cfg = tnd_cfg(); $wid = (int)ws('id');
    $from = date('Y-m-d H:i:s', time() + 86400 * max(0, (int)$cfg['min_days']));
    $st = db()->prepare('SELECT t.*, m.status mark, m.note mnote, m.lead_id FROM tenders t LEFT JOIN tender_marks m ON m.tender_id = t.id AND m.workspace_id = ? WHERE t.deadline >= ? ORDER BY t.deadline LIMIT 5000');
    $st->execute([$wid, $from]); $out = [];
    foreach ($st->fetchAll() as $t) {
        $t['hits'] = tnd_hits($t, (array)$cfg['words'], (array)$cfg['not']);
        if (!$all && !$t['hits'] && !$t['mark']) continue;
        if ($t['mark'] === 'hidden' && !$all) continue;
        $out[] = $t; if (count($out) >= $limit) break;
    }
    return $out;
}

/** المنافسات اللي الشركة علّمت عليها (حتى لو ميعادها فات) */
function tnd_marked(string $status): array
{
    $st = db()->prepare('SELECT t.*, m.status mark, m.note mnote, m.lead_id FROM tender_marks m JOIN tenders t ON t.id = m.tender_id WHERE m.workspace_id = ? AND m.status = ? ORDER BY t.deadline DESC LIMIT 200');
    $st->execute([(int)ws('id'), $status]); return array_map(function ($t) { $t['hits'] = []; return $t; }, $st->fetchAll());
}

function tnd_mark(int $tid, string $status, ?string $note = null, ?int $leadId = null): void
{
    $wid = (int)ws('id');
    if ($status === '') { db()->prepare('DELETE FROM tender_marks WHERE workspace_id = ? AND tender_id = ?')->execute([$wid, $tid]); return; }
    db()->prepare('INSERT INTO tender_marks (workspace_id, tender_id, status, note, lead_id) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), note = COALESCE(VALUES(note), note), lead_id = COALESCE(VALUES(lead_id), lead_id)')
        ->execute([$wid, $tid, $status, $note, $leadId]);
}

/** حالة السحّاب: آخر مرة وصلت بيانات، ومتأخرة ولا لأ */
function tnd_health(): array
{
    $last = (string)setting('tenders_last_ingest', '');
    $age = $last !== '' ? time() - strtotime($last . ' UTC') : null;
    $n = 0; try { $n = (int)db()->query('SELECT COUNT(*) FROM tenders WHERE deadline >= NOW()')->fetchColumn(); } catch (Throwable $e) {}
    return ['last' => $last, 'age' => $age, 'stale' => $age === null || $age > 8 * 3600, 'active' => $n, 'stats' => json_decode((string)setting('tenders_last_stats', ''), true) ?: []];
}

/** التشغيل الدوري: تنبيه واحد بعدد المنافسات الجديدة المطابقة من آخر مرة */
function tnd_tick(): int
{
    if (!function_exists('crm_notify') || !module_enabled('crm')) return 0;
    $wid = (int)ws('id'); $cfg = tnd_cfg(); if (!$cfg['words']) return 0;
    $since = (string)setting('tnd_seen_' . $wid, '');
    if ($since === '') { save_setting('tnd_seen_' . $wid, date('Y-m-d H:i:s')); return 0; } // أول مرة: من دلوقتي، من غير ما نغرق الناس بالقديم
    $st = db()->prepare('SELECT * FROM tenders WHERE first_seen > ? AND deadline >= ? ORDER BY first_seen'); $st->execute([$since, date('Y-m-d H:i:s', time() + 86400 * max(0, (int)$cfg['min_days']))]);
    $hit = array_values(array_filter($st->fetchAll(), function ($t) use ($cfg) { return (bool)tnd_hits($t, $cfg['words'], $cfg['not']); }));
    save_setting('tnd_seen_' . $wid, date('Y-m-d H:i:s'));
    if (!$hit) return 0;
    crm_notify(null, null, 'tender', '🏛️ ' . (count($hit) === 1 ? t('منافسة جديدة تناسبك: %s', mb_substr($hit[0]['name'], 0, 120)) : t('%s منافسة جديدة تناسبك على اعتماد، أولها: %s', count($hit), mb_substr($hit[0]['name'], 0, 100))));
    return count($hit);
}

/* ---------- مصدر جاهز: Apify (Etimad Tender Radar) — من غير سيرفر ولا متصفح عندنا ---------- */

function tnd_apify_base(): string { return rtrim((string)(getenv('APIFY_BASE') ?: 'https://api.apify.com'), '/'); }
/** الخدمات المدعومة: الأرخص (بيكفيها الرصيد المجاني 5 دولار/شهر للجديد بس) والأغنى بالبيانات */
function tnd_apify_actors(): array
{
    return ['publicmoney~etimad-tender-scraper' => ['Etimad Tender Scraper', t('الأرخص: 0.002 دولار للمنافسة، الجديد كل يوم بيكفيه رصيد Apify المجاني. من غير الفرع وسعر الكراسة.')],
        'dottti~etimad-tender-radar' => ['Etimad Tender Radar', t('بيانات كاملة (الفرع والكراسة والمواعيد): 0.005 دولار للمنافسة.')]];
}
/** اتصال Apify من التكاملات: أول شركة ربطته (مدير المنصة الأول). البيانات نفسها عامة لكل الشركات */
function tnd_apify_conn(): ?array
{
    static $c = false; if ($c !== false) return $c;
    try {
        $st = db()->query("SELECT c.workspace_id FROM connectors c JOIN workspaces w ON w.id = c.workspace_id WHERE c.provider = 'apify' AND COALESCE(w.status, 'active') = 'active' ORDER BY (w.created_by IN (SELECT id FROM users WHERE role = 'admin')) DESC, c.id LIMIT 1");
        $wid = (int)$st->fetchColumn(); $c = $wid ? (connections('apify', $wid)[0] ?? null) : null;
    } catch (Throwable $e) { $c = null; }
    return $c;
}
function tnd_apify_actor(): string { $a = (string)(tnd_apify_conn()['credentials']['actor'] ?? ''); return isset(tnd_apify_actors()[$a]) ? $a : 'publicmoney~etimad-tender-scraper'; }
function tnd_apify_on(): bool { return (string)(tnd_apify_conn()['credentials']['api_key'] ?? '') !== ''; }

function tnd_apify_req(string $method, string $path, ?array $body = null, ?string $tok = null): array
{
    $tok = $tok ?? (string)(tnd_apify_conn()['credentials']['api_key'] ?? ''); if ($tok === '') throw new RuntimeException(t('اربط Apify من التكاملات الأول.'));
    $ch = curl_init(tnd_apify_base() . $path);
    $h = ['Accept: application/json', 'Authorization: Bearer ' . $tok];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE)); }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $h]);
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($raw === false) throw new RuntimeException(t('تعذر الاتصال بـ %s: %s', 'Apify', $err));
    $d = json_decode((string)$raw, true);
    if ($code === 401 || $code === 403) throw new RuntimeException(t('مفتاح Apify غلط أو مالوش صلاحية.'));
    if ($code >= 400) throw new RuntimeException('Apify: ' . (is_array($d) ? (string)($d['error']['message'] ?? $code) : $code));
    return is_array($d) ? $d : [];
}

/** يبدأ تشغيلة. full = كل المنافسات المفتوحة (أول مرة)، وإلا الجديد من آخر يومين */
function tnd_apify_start(bool $full): string
{
    if (tnd_apify_actor() === 'dottti~etimad-tender-radar') {
        $in = ['keywords' => [], 'matchMode' => 'any', 'onlyOpen' => true, 'maxItems' => $full ? 3000 : 400];
        if (!$full) $in['publishDateId'] = '2'; // منذ يومين: بيغطي يوم كامل لو تشغيلة فاتت
    } else $in = ['maxItems' => $full ? 600 : 150]; // الأحدث الأول. أول مرة 600 (≈1.2 دولار)، وبعدها 150 في اليوم (≈0.3 دولار) والجديد حوالي 80: الشهر كله جوه رصيد Apify المجاني
    $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode(tnd_apify_actor()) . '/runs', $in);
    $id = (string)($r['data']['id'] ?? ''); if ($id === '') throw new RuntimeException(t('Apify مارجعش رقم التشغيلة.'));
    save_setting('tenders_apify_run', json_encode(['id' => $id, 'full' => $full, 'at' => gmdate('c')]));
    return $id;
}

/** شكل Apify → شكل اعتماد اللي tnd_ingest بيفهمه */
function tnd_apify_map(array $x): array
{
    // الخدمتين أسماء حقولهم مختلفة شوية
    $u = (string)($x['url'] ?? $x['tenderUrl'] ?? ''); $sid = '';
    if ($u !== '' && preg_match('/[?&]STenderId=([^&]+)/', $u, $m)) $sid = rawurldecode($m[1]);
    $id = (int)($x['tenderId'] ?? 0); if ($id <= 0) $id = (int)preg_replace('/\D/', '', (string)($x['referenceNumber'] ?? '')); // من غير tenderId: الرقم المرجعي (أرقام بس) كمعرّف
    if (!isset($x['tenderActivityName']) && isset($x['tenderActivity'])) $x['tenderActivityName'] = $x['tenderActivity'];
    if (!isset($x['tenderTypeName']) && isset($x['tenderType'])) $x['tenderTypeName'] = $x['tenderType'];
    return ['tenderId' => $id, 'referenceNumber' => $x['referenceNumber'] ?? '', 'tenderName' => $x['tenderName'] ?? '', 'agencyName' => $x['agencyName'] ?? '', 'branchName' => $x['branchName'] ?? '',
        'tenderTypeName' => $x['tenderTypeName'] ?? '', 'tenderActivityName' => $x['tenderActivityName'] ?? '', 'tenderIdString' => $sid, 'condetionalBookletPrice' => $x['conditionalBookletPriceSar'] ?? ($x['condetionalBookletPrice'] ?? null),
        'submitionDate' => $x['submitionDate'] ?? '', 'lastEnqueriesDate' => $x['lastEnqueriesDate'] ?? '', 'lastOfferPresentationDate' => $x['lastOfferPresentationDate'] ?? ''];
}

/** لو فيه تشغيلة خلصت: نجيب نتايجها ونحفظها. بيرجع null (لسه شغالة/مفيش) أو نتيجة الحفظ */
function tnd_apify_collect(): ?array
{
    $run = json_decode((string)setting('tenders_apify_run', ''), true); if (empty($run['id'])) return null;
    $r = tnd_apify_req('GET', '/v2/actor-runs/' . rawurlencode($run['id'])); $st = (string)($r['data']['status'] ?? '');
    if (in_array($st, ['READY', 'RUNNING'], true)) return null;
    save_setting('tenders_apify_run', '');
    if ($st !== 'SUCCEEDED') { save_setting('tenders_last_stats', json_encode(['at' => gmdate('c'), 'src' => 'apify', 'new' => 0, 'updated' => 0, 'skipped' => 0, 'note' => 'error: Apify run ' . $st])); return ['new' => 0, 'updated' => 0, 'skipped' => 0, 'error' => $st]; }
    $ds = (string)($r['data']['defaultDatasetId'] ?? ''); $tot = ['new' => 0, 'updated' => 0, 'skipped' => 0];
    for ($off = 0; $off < 5000; $off += 500) {
        $items = tnd_apify_req('GET', '/v2/datasets/' . rawurlencode($ds) . '/items?clean=1&format=json&limit=500&offset=' . $off);
        if (!$items || !isset($items[0])) break;
        $res = tnd_ingest('etimad', array_map('tnd_apify_map', $items)); foreach ($tot as $k => $v) $tot[$k] += $res[$k];
        if (count($items) < 500) break;
    }
    if ($tot['new'] + $tot['updated'] > 0) save_setting('tenders_last_ingest', gmdate('Y-m-d H:i:s'));
    save_setting('tenders_last_stats', json_encode(['at' => gmdate('c'), 'src' => 'apify' . (!empty($run['full']) ? ' (full)' : '')] + $tot + ['note' => '']));
    if (!empty($run['full'])) save_setting('tenders_apify_full_done', gmdate('Y-m-d'));
    return $tot;
}

/** التشغيل الدوري (على مستوى المنصة): يلم التشغيلة اللي خلصت، ويبدأ تشغيلة اليوم مرة واحدة */
function tnd_apify_tick(): string
{
    if (!tnd_apify_on()) return '';
    try {
        $c = tnd_apify_collect(); if ($c !== null) return 'collected+' . ($c['new'] ?? 0);
        if ((string)setting('tenders_apify_run', '') !== '') return 'running';
        $today = gmdate('Y-m-d');
        if ((string)setting('tenders_apify_day', '') === $today) return '';
        $full = (string)setting('tenders_apify_full_done', '') === '' || (gmdate('j') === '1' && tnd_apify_actor() === 'dottti~etimad-tender-radar'); // أول مرة، وأول كل شهر لفة كاملة (للخدمة الغنية بس) عشان المواعيد اللي اتمدت
        tnd_apify_start($full); save_setting('tenders_apify_day', $today);
        return 'started' . ($full ? ' full' : '');
    } catch (Throwable $e) { save_setting('tenders_last_stats', json_encode(['at' => gmdate('c'), 'src' => 'apify', 'new' => 0, 'updated' => 0, 'skipped' => 0, 'note' => 'error: ' . mb_substr($e->getMessage(), 0, 160)])); return 'error'; }
}
