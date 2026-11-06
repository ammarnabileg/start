<?php
/**
 * المنافسات الحكومية (اعتماد): بيانات عامة على مستوى المنصة، وكل شركة بتشوف اللي بيطابق كلماتها بس.
 * البيانات بتيجي من السحّاب (collector/etimad) أو أي مصدر تاني عن طريق ?p=tenders_ingest.
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
