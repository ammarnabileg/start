<?php

/** خلية CSV آمنة: أي نص بيبدأ بـ = + - @ يتحول لنص عادي في الإكسل */
function cmp_cell($v) { $v = (string)$v; return $v !== '' && strpos("=+-@\t\r", $v[0]) !== false && !is_numeric($v) ? "'" . $v : $v; }
/**
 * المقارنة والتنبيهات (الأفكار 42-50، من غير ذكاء اصطناعي): «أنت قدامهم» بالترتيب، تنبيهات بقواعد (جرس + إيميل)،
 * ملخص أسبوعي، آخر نشاط، تقويم العروض، تصدير، المحفوظات، واكتشاف منافسين من خرائط جوجل.
 */

/** صف «حساباتي»: بنفس قراءات المنافسين عشان المقارنة تبقى عادلة */
function cmp_self(): ?array
{
    try { $st = db()->prepare('SELECT * FROM competitors WHERE workspace_id = ? AND is_self = 1 LIMIT 1'); $st->execute([(int)ws('id')]); return $st->fetch() ?: null; } catch (Throwable $e) { return null; }
}
/** ينشئ صف «حساباتي» من ملف الشركة (الموقع وروابط السوشيال) */
function cmp_self_create(): ?array
{
    if ($s = cmp_self()) return $s;
    $wid = (int)ws('id'); $k = function_exists('kb_get') ? kb_get($wid) : [];
    $url = trim((string)($k['contact']['website'] ?? '')); if ($url !== '' && !preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
    $links = implode("\n", array_slice(array_values(array_filter(array_map('trim', preg_split('/\s+/', (string)($k['contact']['social'] ?? ''))), function ($x) { return (bool)preg_match('~^https?://~i', $x); })), 0, 8));
    $name = trim((string)($k['company']['name'] ?? '')) ?: (string)ws('name');
    db()->prepare('INSERT INTO competitors (workspace_id, name, url, links, is_self) VALUES (?, ?, ?, ?, 1)')->execute([$wid, mb_substr($name, 0, 120), mb_substr($url ?: 'https://example.com', 0, 500), $links ?: null]);
    return cmp_self();
}

/** أرقام المقارنة لمنافس واحد (استعلامات خفيفة، مش التقارير الكاملة) */
function cmp_metrics(array $c, array $priceRows = []): array
{
    $pdo = db(); $cid = (int)$c['id']; $one = function ($sql, $args = []) use ($pdo) { $st = $pdo->prepare($sql); $st->execute($args); return $st->fetchColumn(); };
    $soc = function_exists('cs_card') ? cs_card($cid) : null;
    $posts28 = (int)$one('SELECT COUNT(*) FROM competitor_posts p JOIN competitor_social s ON s.id = p.social_id WHERE s.competitor_id = ? AND p.posted_at >= ?', [$cid, date('Y-m-d H:i:s', strtotime('-28 days'))]);
    $er = $one('SELECT AVG((COALESCE(p.likes,0) + COALESCE(p.comments,0)) / s.followers * 100) FROM competitor_posts p JOIN competitor_social s ON s.id = p.social_id WHERE s.competitor_id = ? AND s.followers > 0 AND p.posted_at >= ?', [$cid, date('Y-m-d H:i:s', strtotime('-30 days'))]);
    $ads = (int)$one('SELECT COUNT(*) FROM competitor_ads WHERE competitor_id = ? AND active = 1', [$cid]);
    $win = (int)$one('SELECT COUNT(*) FROM competitor_ads WHERE competitor_id = ? AND active = 1 AND COALESCE(started, DATE(first_seen)) <= ?', [$cid, date('Y-m-d', strtotime('-30 days'))]);
    $rt = $pdo->prepare('SELECT SUM(rating * reviews_count) / NULLIF(SUM(reviews_count), 0) a, SUM(reviews_count) n FROM competitor_places WHERE competitor_id = ? AND rating IS NOT NULL'); $rt->execute([$cid]); $rr = $rt->fetch() ?: [];
    $rv = $pdo->prepare('SELECT COUNT(*) n, SUM(r.reply IS NOT NULL AND r.reply <> "") y FROM competitor_reviews r JOIN competitor_places p ON p.id = r.place_row WHERE p.competitor_id = ? AND (r.at IS NULL OR r.at >= ?)'); $rv->execute([$cid, date('Y-m-d', strtotime('-90 days'))]); $rvr = $rv->fetch() ?: [];
    $site = $pdo->prepare('SELECT psi, b2b FROM competitor_site WHERE competitor_id = ?'); $site->execute([$cid]); $sr = $site->fetch() ?: []; $psi = json_decode((string)($sr['psi'] ?? ''), true) ?: []; $bb = json_decode((string)($sr['b2b'] ?? ''), true);
    $up = $pdo->prepare('SELECT COUNT(*) n, SUM(ok) y FROM competitor_site_days WHERE competitor_id = ? AND day >= ?'); $up->execute([$cid, date('Y-m-d', strtotime('-29 days'))]); $upr = $up->fetch() ?: [];
    // آخر نشاط: آخر منشور، آخر إعلان بدأ، آخر تغيير مسجّل
    $last = max(strtotime((string)$one('SELECT MAX(p.posted_at) FROM competitor_posts p JOIN competitor_social s ON s.id = p.social_id WHERE s.competitor_id = ?', [$cid])) ?: 0,
        strtotime((string)$one('SELECT MAX(COALESCE(started, DATE(first_seen))) FROM competitor_ads WHERE competitor_id = ?', [$cid])) ?: 0,
        strtotime((string)$one('SELECT MAX(created_at) FROM competitor_events WHERE competitor_id = ? AND kind NOT IN ("first", "down", "up")', [$cid])) ?: 0);
    $cheap = 0; $exp = 0; foreach ($priceRows as $r) { $h = $r['them'][$cid] ?? null; $mn = comp_price_num((string)$r['mine']); $n = $h ? comp_price_num((string)$h['price']) : null; if ($mn && $n !== null) { if ($n < $mn) $cheap++; elseif ($n > $mn) $exp++; } }
    return ['followers' => $soc['followers'] ?? null, 'g7' => $soc['g7'] ?? null, 'ppw' => $posts28 ? round($posts28 / 4, 1) : ($soc ? 0 : null), 'er' => $er !== null && $er !== false ? round((float)$er, 2) : null,
        'ads' => $ads, 'win' => $win, 'rating' => isset($rr['a']) && $rr['a'] !== null ? round((float)$rr['a'], 1) : null, 'reviews' => isset($rr['n']) && $rr['n'] !== null ? (int)$rr['n'] : null,
        'revReply' => !empty($rvr['n']) ? (int)round($rvr['y'] / $rvr['n'] * 100) : null, 'speed' => $psi['score'] ?? null, 'uptime' => !empty($upr['n']) ? round($upr['y'] / $upr['n'] * 100, 1) : null,
        'clients' => is_array($bb) ? count((array)($bb['clients'] ?? [])) : null, 'projects' => is_array($bb) ? count((array)($bb['projects'] ?? [])) : null, 'certs' => is_array($bb) ? count((array)($bb['certs'] ?? [])) + count((array)($bb['vendors'] ?? [])) : null, 'jobs' => is_array($bb) ? (int)($bb['jobsN'] ?? 0) : null,
        'cheaper' => $cheap, 'pricier' => $exp, 'last' => $last ?: null, 'lastDays' => $last ? (int)floor((time() - $last) / 86400) : null];
}

/** جدول «أنت قدامهم» + الترتيب في كل مؤشر */
function cmp_table(): array
{
    $comps = comp_list(); $self = null; $others = [];
    foreach ($comps as $c) { if (!empty($c['is_self'])) $self = $c; else $others[] = $c; }
    $prices = $others ? comp_price_table($others) : [];
    $cols = []; foreach (array_merge($self ? [$self] : [], $others) as $c) $cols[] = ['id' => (int)$c['id'], 'name' => (string)$c['name'], 'self' => (int)!empty($c['is_self']), 'm' => cmp_metrics($c, $prices)];
    // المؤشرات: الأعلى أحسن إلا اللي عليها low
    $defs = [['clients', t('عملاء معلنون على موقعه'), 'hi'], ['projects', t('مشاريع منشورة'), 'hi'], ['certs', t('شهادات واعتمادات'), 'hi'], ['jobs', t('وظائف مفتوحة'), 'hi'], ['followers', t('المتابعون'), 'hi'], ['g7', t('نمو المتابعين (7 أيام)'), 'hi'], ['ppw', t('منشور/أسبوع'), 'hi'], ['er', t('متوسط التفاعل %'), 'hi'], ['ads', t('إعلانات شغّالة'), 'hi'], ['win', t('إعلانات رابحة (30+ يوم)'), 'hi'],
        ['rating', t('تقييم خرائط جوجل'), 'hi'], ['reviews', t('عدد المراجعات'), 'hi'], ['revReply', t('نسبة الرد على المراجعات %'), 'hi'], ['speed', t('سرعة الموقع (من 100)'), 'hi'], ['uptime', t('توفر الموقع %'), 'hi']];
    $rows = [];
    foreach ($defs as $d) {
        $vals = []; foreach ($cols as $i => $c) if ($c['m'][$d[0]] !== null) $vals[$i] = (float)$c['m'][$d[0]];
        if (!$vals) continue; arsort($vals); $rank = []; $r = 0; $prev = null; $k = 0; foreach ($vals as $i => $v) { $k++; if ($v !== $prev) $r = $k; $rank[$i] = $r; $prev = $v; }
        $cells = []; foreach ($cols as $i => $c) $cells[] = ['v' => $c['m'][$d[0]], 'rank' => $rank[$i] ?? null, 'best' => isset($rank[$i]) && $rank[$i] === 1 && count($vals) > 1, 'worst' => isset($rank[$i]) && $rank[$i] === count($vals) && count($vals) > 1];
        $rows[] = ['key' => $d[0], 'label' => $d[1], 'cells' => $cells, 'n' => count($vals)];
    }
    $selfIdx = $self ? 0 : null; $sum = null;
    // الملخص على المؤشرات اللي ليك فيها بيانات بس (من غير بيانات = مش «الأخير»)
    if ($selfIdx !== null) { $first = 0; $last = 0; $of = 0; foreach ($rows as $r) { $rk = $r['cells'][$selfIdx]['rank']; if ($rk === null || $r['n'] < 2) continue; $of++; if ($rk === 1) $first++; elseif ($rk === $r['n']) $last++; } $sum = $of ? ['first' => $first, 'last' => $last, 'of' => $of] : null; }
    return ['cols' => array_map(function ($c) { return ['id' => $c['id'], 'name' => $c['name'], 'self' => $c['self'], 'last' => $c['m']['lastDays'], 'cheaper' => $c['m']['cheaper'], 'pricier' => $c['m']['pricier']]; }, $cols), 'rows' => $rows, 'self' => $selfIdx !== null, 'sum' => $sum];
}

/* ---------- 44: تنبيهات بقواعد (جرس + إيميل) ---------- */
function cmp_rules(?int $wid = null): array
{
    $r = json_decode((string)setting('cmp_rules_' . ($wid ?? (int)ws('id')), ''), true) ?: [];
    $d = ['price_drop' => ['on' => 1, 'pct' => 10], 'price_up' => ['on' => 0, 'pct' => 10], 'burst' => ['on' => 1, 'n' => 3], 'ads_start' => ['on' => 1], 'ad_new' => ['on' => 0], 'rating_drop' => ['on' => 1, 'd' => 0.2], 'rev_bad' => ['on' => 1], 'code_new' => ['on' => 1], 'offer_new' => ['on' => 1], 'page_new' => ['on' => 0], 'tech_new' => ['on' => 1], 'down' => ['on' => 0], 'email' => ['on' => 0], 'digest' => ['on' => 1]];
    $d += ['client_new' => ['on' => 1], 'project_new' => ['on' => 1], 'cert_new' => ['on' => 1], 'jobs_up' => ['on' => 1]]; // B2B
    foreach ($d as $k => $v) $d[$k] = array_merge($v, array_intersect_key((array)($r[$k] ?? []), $v));
    return $d;
}

/** يراجع الأحداث الجديدة من آخر مرة ويبعت تنبيه بالمطابق للقواعد. بيرجع عدد التنبيهات */
function cmp_alert_tick(): int
{
    $wid = (int)ws('id'); $R = cmp_rules($wid); $key = 'cmp_alert_last_' . $wid; $lastId = (int)setting($key, '0'); $pdo = db();
    if ($lastId === 0) { save_setting($key, (string)(int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM competitor_events WHERE workspace_id = ' . $wid)->fetchColumn()); return 0; } // أول تشغيل: منبعتش التاريخ القديم
    $st = $pdo->prepare('SELECT e.*, c.name cname, c.is_self FROM competitor_events e JOIN competitors c ON c.id = e.competitor_id WHERE e.workspace_id = ? AND e.id > ? ORDER BY e.id LIMIT 300'); $st->execute([$wid, $lastId]); $ev = $st->fetchAll();
    $out = [];
    foreach ($ev as $e) {
        $lastId = max($lastId, (int)$e['id']); if (!empty($e['is_self'])) continue; $k = $e['kind']; $d = (string)$e['detail'];
        $pct = preg_match('/\(([+-]?\d+)%\)/', $d, $m) ? abs((int)$m[1]) : null;
        $hit = ($k === 'price_down' && $R['price_drop']['on'] && ($pct === null || $pct >= $R['price_drop']['pct'])) || ($k === 'price_up' && $R['price_up']['on'] && ($pct === null || $pct >= $R['price_up']['pct']))
            || ($k === 'rating' && $R['rating_drop']['on'] && preg_match('/([\d.]+)\s*→\s*([\d.]+)/u', $d, $m) && ((float)$m[1] - (float)$m[2]) >= (float)$R['rating_drop']['d'] - 0.001)
            || (in_array($k, ['rev_bad', 'code_new', 'offer_new', 'page_new', 'tech_new', 'down', 'ad_new', 'client_new', 'project_new', 'cert_new', 'jobs_up'], true) && !empty($R[$k]['on']));
        if ($hit) $out[] = ['c' => (string)$e['cname'], 'cid' => (int)$e['competitor_id'], 't' => comp_ev_title($k, (string)$e['title']), 'd' => $d];
    }
    save_setting($key, (string)$lastId);
    // بدأ يعلن (كان 0 إعلان) وكثافة النشر: من البيانات مش من الأحداث
    foreach (comp_list() as $c) {
        if (!empty($c['is_self'])) continue; $cid = (int)$c['id'];
        $n = (int)$pdo->query('SELECT COUNT(*) FROM competitor_ads WHERE active = 1 AND competitor_id = ' . $cid)->fetchColumn(); $pk = 'cmp_ads_prev_' . $cid; $prev = setting($pk, null);
        if ($R['ads_start']['on'] && $prev !== null && (int)$prev === 0 && $n > 0) $out[] = ['c' => (string)$c['name'], 'cid' => $cid, 't' => t('بدأ يعلن: %s إعلان شغّال', $n), 'd' => ''];
        if ((string)$prev !== (string)$n) save_setting($pk, (string)$n);
        if ($R['burst']['on']) { $today = date('Y-m-d'); $bk = 'cmp_burst_' . $cid; $b = (int)$pdo->query("SELECT COUNT(*) FROM competitor_posts p JOIN competitor_social s ON s.id = p.social_id WHERE s.competitor_id = $cid AND p.posted_at >= '$today 00:00:00'")->fetchColumn();
            if ($b >= (int)$R['burst']['n'] && setting($bk, '') !== $today) { save_setting($bk, $today); $out[] = ['c' => (string)$c['name'], 'cid' => $cid, 't' => t('نشر %s منشورات اليوم', $b), 'd' => '']; } }
    }
    if (!$out) return 0;
    if (function_exists('crm_notify') && module_enabled('crm')) { $byC = []; foreach ($out as $o) $byC[$o['c']][] = $o['t']; foreach ($byC as $nm => $ts) crm_notify(null, null, 'competitor', '👀 ' . $nm . ': ' . $ts[0] . (count($ts) > 1 ? ' ' . t('و%s تنبيه آخر', count($ts) - 1) : '')); }
    if ($R['email']['on']) { $html = '<div dir="rtl" style="font-family:Tahoma,sans-serif"><h3>' . e(t('تنبيهات المنافسين')) . '</h3><ul>' . implode('', array_map(function ($o) { return '<li><b>' . e($o['c']) . '</b>: ' . e($o['t']) . ($o['d'] !== '' ? ' <span style="color:#64748b">(' . e(mb_substr($o['d'], 0, 160)) . ')</span>' : '') . '</li>'; }, array_slice($out, 0, 30))) . '</ul><p><a href="' . e(base_url() . '/' . url('competitors')) . '">' . e(t('افتح صفحة المنافسين')) . '</a></p></div>';
        foreach (cmp_recipients() as $to) { try { send_mail($to, t('تنبيهات المنافسين: %s تنبيه', count($out)), $html, null, $wid); } catch (Throwable $e) {} } }
    return count($out);
}
function cmp_recipients(): array { if (!function_exists('crm_members')) return []; return array_values(array_unique(array_filter(array_map(function ($m) { return in_array($m['role'] ?? '', ['owner', 'admin', 'manager'], true) ? trim((string)$m['email']) : ''; }, crm_members())))); }

/* ---------- 45: الملخص الأسبوعي بالإيميل ---------- */
function cmp_digest_html(): string
{
    $wid = (int)ws('id'); $pdo = db(); $since = date('Y-m-d H:i:s', strtotime('-7 days')); $rows = '';
    foreach (comp_list() as $c) {
        if (!empty($c['is_self'])) continue; $cid = (int)$c['id'];
        $st = $pdo->prepare('SELECT kind, title, detail FROM competitor_events WHERE competitor_id = ? AND created_at >= ? AND kind NOT IN ("first", "up") ORDER BY id DESC'); $st->execute([$cid, $since]); $ev = $st->fetchAll();
        $m = cmp_metrics($c); if (!$ev && $m['g7'] === null) continue;
        $rows .= '<h4 style="margin:16px 0 4px">' . e((string)$c['name']) . '</h4><p style="color:#475569;margin:0 0 6px">' . e(implode(' · ', array_filter([$m['followers'] !== null ? t('%s متابع', number_format((int)$m['followers'])) . ($m['g7'] ? ' (' . ($m['g7'] > 0 ? '+' : '') . number_format((int)$m['g7']) . ')' : '') : '', $m['ads'] ? t('%s إعلان شغّال', $m['ads']) : '', $m['rating'] !== null ? '★ ' . $m['rating'] : '', t('%s تغيير', count($ev))]))) . '</p>';
        if ($ev) $rows .= '<ul style="margin:0;padding-inline-start:18px">' . implode('', array_map(function ($e) { return '<li>' . e(comp_ev_title($e['kind'], (string)$e['title'])) . ($e['detail'] ? ' <span style="color:#64748b">' . e(mb_substr((string)$e['detail'], 0, 120)) . '</span>' : '') . '</li>'; }, array_slice($ev, 0, 6))) . (count($ev) > 6 ? '<li>' . e(t('و%s تغيير آخر', count($ev) - 6)) . '</li>' : '') . '</ul>';
    }
    if ($rows === '') return '';
    return '<div dir="rtl" style="font-family:Tahoma,sans-serif;max-width:640px"><h2>' . e(t('ملخص المنافسين الأسبوعي')) . '</h2>' . $rows . '<p style="margin-top:18px"><a href="' . e(base_url() . '/' . url('competitors')) . '">' . e(t('افتح صفحة المنافسين')) . '</a></p></div>';
}
function cmp_digest_tick(bool $force = false): bool
{
    $wid = (int)ws('id'); $R = cmp_rules($wid); if (!$force && !$R['digest']['on']) return false;
    $k = 'cmp_digest_at_' . $wid; if (!$force && (int)setting($k, '0') > time() - 7 * 86400 + 3600) return false;
    if ($force && (int)setting('cmp_digest_manual_' . $wid, '0') > time() - 600) return false; // إرسال يدوي: مرة كل 10 دقايق
    if (!$force && (int)setting('cmp_digest_try_' . $wid, '0') > time() - 3600) return false; save_setting('cmp_digest_try_' . $wid, (string)time()); // محاولة فاشلة/فاضية: نستنى ساعة
    $html = cmp_digest_html(); if ($html === '') return false;
    $n = 0; foreach (cmp_recipients() as $to) { try { if (send_mail($to, t('ملخص المنافسين الأسبوعي'), $html, null, $wid) !== false) $n++; } catch (Throwable $e) {} }
    if ($n) { save_setting($k, (string)time()); if ($force) save_setting('cmp_digest_manual_' . $wid, (string)time()); }
    return $n > 0;
}

/* ---------- 47: تقويم العروض (من سجل التغييرات) ---------- */
function cmp_seasons(): array
{
    $wid = (int)ws('id'); $st = db()->prepare('SELECT e.competitor_id, c.name, MONTH(e.created_at) m, COUNT(*) n FROM competitor_events e JOIN competitors c ON c.id = e.competitor_id WHERE e.workspace_id = ? AND c.is_self = 0 AND e.kind IN ("offer_new", "ad_new", "code_new", "price_down") GROUP BY e.competitor_id, c.name, MONTH(e.created_at)');
    $st->execute([$wid]); $out = []; foreach ($st->fetchAll() as $r) { $out[(int)$r['competitor_id']]['name'] = (string)$r['name']; $out[(int)$r['competitor_id']]['m'][(int)$r['m']] = (int)$r['n']; }
    $since = db()->prepare('SELECT MIN(created_at) FROM competitor_events WHERE workspace_id = ?'); $since->execute([$wid]);
    return ['rows' => array_values(array_map(function ($x) { $m = []; for ($i = 1; $i <= 12; $i++) $m[] = $x['m'][$i] ?? 0; return ['name' => $x['name'], 'm' => $m]; }, $out)), 'since' => ($s = $since->fetchColumn()) ? fmt_date($s, 'd/m/Y') : ''];
}

/* ---------- 49: المحفوظات ---------- */
function cmp_saved(): array
{
    $wid = (int)ws('id'); $pdo = db(); $out = [];
    $st = $pdo->prepare('SELECT v.*, c.name cname FROM competitor_saved v JOIN competitors c ON c.id = v.competitor_id WHERE v.workspace_id = ? ORDER BY v.id DESC LIMIT 100'); $st->execute([$wid]);
    foreach ($st->fetchAll() as $v) {
        if ($v['kind'] === 'post') { $q = $pdo->prepare('SELECT p.*, s.platform FROM competitor_posts p JOIN competitor_social s ON s.id = p.social_id WHERE p.id = ?'); $q->execute([(int)$v['ref_id']]); $x = $q->fetch(); if (!$x) continue;
            $out[] = ['id' => (int)$v['id'], 'kind' => 'post', 'cname' => (string)$v['cname'], 'platform' => (string)$x['platform'], 'text' => mb_substr((string)$x['caption'], 0, 400), 'url' => (string)$x['url'], 'thumb' => (string)$x['thumb'], 'meta' => trim(($x['likes'] !== null ? '♥ ' . number_format((int)$x['likes']) : '') . ($x['comments'] !== null ? ' · 💬 ' . number_format((int)$x['comments']) : '')), 'note' => (string)$v['note'], 'at' => fmt_date($v['created_at'], 'd/m')]; }
        else { $q = $pdo->prepare('SELECT * FROM competitor_ads WHERE id = ?'); $q->execute([(int)$v['ref_id']]); $x = $q->fetch(); if (!$x) continue;
            $out[] = ['id' => (int)$v['id'], 'kind' => 'ad', 'cname' => (string)$v['cname'], 'platform' => (string)$x['platforms'], 'text' => trim((string)$x['title'] . "\n" . mb_substr((string)$x['body'], 0, 400)), 'url' => 'https://www.facebook.com/ads/library/?id=' . rawurlencode((string)$x['ext_id']), 'thumb' => (string)$x['thumb'], 'meta' => (string)$x['cta'], 'note' => (string)$v['note'], 'at' => fmt_date($v['created_at'], 'd/m')]; }
    }
    return $out;
}
function cmp_saved_ids(): array { try { $st = db()->prepare('SELECT kind, ref_id FROM competitor_saved WHERE workspace_id = ?'); $st->execute([(int)ws('id')]); $o = ['post' => [], 'ad' => []]; foreach ($st->fetchAll() as $r) $o[$r['kind']][] = (int)$r['ref_id']; return $o; } catch (Throwable $e) { return ['post' => [], 'ad' => []]; } }
/** حفظ/إلغاء حفظ (بيتأكد إن العنصر تبع منافس من نفس الشركة) */
function cmp_save_toggle(string $kind, int $ref): bool
{
    $wid = (int)ws('id'); $pdo = db();
    $q = $kind === 'post' ? $pdo->prepare('SELECT s.competitor_id FROM competitor_posts p JOIN competitor_social s ON s.id = p.social_id WHERE p.id = ? AND s.workspace_id = ?') : $pdo->prepare('SELECT competitor_id FROM competitor_ads WHERE id = ? AND workspace_id = ?');
    $q->execute([$ref, $wid]); $cid = (int)$q->fetchColumn(); if (!$cid) return false;
    $ex = $pdo->prepare('DELETE FROM competitor_saved WHERE workspace_id = ? AND kind = ? AND ref_id = ?'); $ex->execute([$wid, $kind, $ref]);
    if ($ex->rowCount()) return false;
    $pdo->prepare('INSERT INTO competitor_saved (workspace_id, competitor_id, kind, ref_id) VALUES (?, ?, ?, ?)')->execute([$wid, $cid, $kind, $ref]); return true;
}

/* ---------- 50: اكتشاف منافسين من خرائط جوجل (نفس نشاطك في مدينتك) ---------- */
function cmp_disc_query(): string
{
    $k = function_exists('kb_get') ? kb_get((int)ws('id')) : []; $ind = trim((string)($k['company']['industry'] ?? '')); if ($ind === '') return '';
    $city = function_exists('cx') ? (string)cx('city') : ''; return trim((cmp_disc_mode() === 'web' ? t('شركات') . ' ' : '') . $ind . ($city !== '' ? ' ' . $city : ''));
}
/** طريقة الاكتشاف: web = بحث جوجل (للشركات B2B، الافتراضي) — maps = خرائط جوجل (للمحلات والفروع) */
function cmp_disc_mode(): string { $m = (string)setting('cmp_disc_mode_' . (int)ws('id'), 'web'); return in_array($m, ['web', 'maps'], true) ? $m : 'web'; }
/** مواقع مش شركات (أدلة، سوشيال، إعلانات مبوبة): مانعتبرهاش منافسين */
function cmp_disc_skip_host(string $h): bool
{
    return (bool)preg_match('/(^|\.)(google\.|youtube\.|facebook\.|instagram\.|linkedin\.|twitter\.|x\.com$|tiktok\.|snapchat\.|wikipedia\.|wikimedia\.|yellowpages|yelp\.|tripadvisor|bayut|haraj|opensooq|olx\.|dubizzle|indeed\.|glassdoor|bayt\.|zawya\.|argaam\.|amazon\.|noon\.|alibaba|aliexpress|daleeli|saudiyellow|kompass|dnb\.com|crunchbase|reddit\.|quora\.|pinterest\.|apple\.com|play\.google)/i', $h);
}
function cmp_disc_start(string $q = '', string $mode = ''): bool
{
    if (in_array($mode, ['web', 'maps'], true)) save_setting('cmp_disc_mode_' . (int)ws('id'), $mode); $mode = cmp_disc_mode();
    $q = trim($q) ?: cmp_disc_query(); if ($q === '' || !function_exists('cs_on') || !cs_on()) return false;
    if ($mode === 'web') {
        $cc = strtolower((string)(function_exists('ws_country') ? ws_country() : ''));
        $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode('apify~google-search-scraper') . '/runs?memory=1024', array_filter(['queries' => $q, 'resultsPerPage' => 30, 'maxPagesPerQuery' => 2, 'languageCode' => lang() === 'ar' ? 'ar' : 'en', 'countryCode' => preg_match('/^[a-z]{2}$/', $cc) ? $cc : null, 'mobileResults' => false]));
    } else $r = tnd_apify_req('POST', '/v2/acts/' . rawurlencode('compass~crawler-google-places') . '/runs?memory=1024', ['searchStringsArray' => [$q], 'maxCrawledPlacesPerSearch' => 25, 'language' => 'ar', 'maxReviews' => 0, 'maxImages' => 0]);
    if (empty($r['data']['id'])) return false; save_setting('cmp_disc_run_' . (int)ws('id'), json_encode(['id' => (string)$r['data']['id'], 'at' => time(), 'q' => $q, 'mode' => $mode], JSON_UNESCAPED_UNICODE)); return true;
}
function cmp_disc_pending(): bool { $r = json_decode((string)setting('cmp_disc_run_' . (int)ws('id'), ''), true); return !empty($r['id']) && (int)($r['at'] ?? 0) > time() - 1800; }
function cmp_disc_collect(): void
{
    $wid = (int)ws('id'); $key = 'cmp_disc_run_' . $wid; $run = json_decode((string)setting($key, ''), true); if (empty($run['id'])) return;
    $r = tnd_apify_req('GET', '/v2/actor-runs/' . rawurlencode($run['id'])); $st = (string)($r['data']['status'] ?? ''); if (in_array($st, ['READY', 'RUNNING'], true) && (int)$run['at'] > time() - 1800) return;
    save_setting($key, ''); if ($st !== 'SUCCEEDED') return;
    $items = tnd_apify_req('GET', '/v2/datasets/' . rawurlencode((string)$r['data']['defaultDatasetId']) . '/items?clean=1&limit=60');
    $known = []; foreach (comp_list() as $c) { $known[cg_norm((string)$c['name'])] = 1; $h = preg_replace('/^www\./', '', strtolower((string)parse_url((string)$c['url'], PHP_URL_HOST))); if ($h) $known[$h] = 1; foreach (cg_links($c) as $u) $known[$u] = 1; }
    $mine = function_exists('cw_my_domain') ? cw_my_domain($wid) : ''; $out = [];
    if (($run['mode'] ?? 'maps') === 'web') {
        // نتائج بحث جوجل: موقع لكل شركة (من غير الأدلة والسوشيال)، بالترتيب في النتائج
        $seenH = [];
        foreach ($items as $page) foreach ((array)($page['organicResults'] ?? []) as $x) {
            $u = (string)($x['url'] ?? ''); $h = preg_replace('/^www\./', '', strtolower((string)parse_url($u, PHP_URL_HOST))); if ($h === '' || isset($seenH[$h]) || cmp_disc_skip_host($h)) continue;
            $reg = function_exists('cw_regdomain') ? cw_regdomain($h) : $h; if (isset($known[$h]) || isset($known[$reg]) || ($mine && $reg === $mine)) continue; $seenH[$h] = 1;
            $t = trim(preg_split('/\s+[-|–—:]\s+/u', (string)($x['title'] ?? ''))[0] ?? ''); if ($t === '') $t = $h;
            if (isset($known[cg_norm($t)])) continue;
            $out[] = ['name' => mb_substr($t, 0, 120), 'rating' => null, 'reviews' => null, 'web' => (string)(parse_url($u, PHP_URL_SCHEME) ?: 'https') . '://' . (string)parse_url($u, PHP_URL_HOST), 'maps' => '', 'address' => mb_substr(trim((string)($x['description'] ?? '')), 0, 160)];
        }
        save_setting('cmp_disc_' . $wid, json_encode(['at' => date('Y-m-d H:i'), 'q' => $run['q'] ?? '', 'mode' => 'web', 'list' => array_slice($out, 0, 25)], JSON_UNESCAPED_UNICODE));
        return;
    }
    foreach ($items as $x) { $t = trim((string)($x['title'] ?? '')); if ($t === '') continue; $web = (string)($x['website'] ?? ''); $h = preg_replace('/^www\./', '', strtolower((string)parse_url($web, PHP_URL_HOST)));
        if (isset($known[cg_norm($t)]) || ($h && (isset($known[$h]) || ($mine && cw_regdomain($h) === $mine))) || isset($known[(string)($x['url'] ?? '')])) continue;
        $out[] = ['name' => mb_substr($t, 0, 120), 'rating' => isset($x['totalScore']) ? (float)$x['totalScore'] : null, 'reviews' => isset($x['reviewsCount']) ? (int)$x['reviewsCount'] : null, 'web' => $web, 'maps' => (string)($x['url'] ?? ''), 'address' => (string)($x['address'] ?? ''), 'cat' => (string)($x['categoryName'] ?? '')]; }
    usort($out, function ($a, $b) { return ($b['reviews'] ?? 0) <=> ($a['reviews'] ?? 0); });
    save_setting('cmp_disc_' . $wid, json_encode(['at' => date('Y-m-d H:i'), 'q' => $run['q'] ?? '', 'list' => array_slice($out, 0, 20)], JSON_UNESCAPED_UNICODE));
}
function cmp_disc(): array { $d = json_decode((string)setting('cmp_disc_' . (int)ws('id'), ''), true) ?: []; return ['mode' => cmp_disc_mode(), 'q' => (string)($d['q'] ?? ''), 'at' => (string)($d['at'] ?? ''), 'list' => (array)($d['list'] ?? []), 'pending' => cmp_disc_pending(), 'suggest' => cmp_disc_query()]; }

/** كل بيانات أقسام الصفحة الجديدة في طلب واحد */
function cmp_overview(): array
{
    return ['table' => cmp_table(), 'seasons' => cmp_seasons(), 'saved' => cmp_saved(), 'disc' => cmp_disc(), 'rules' => cmp_rules(), 'emails' => cmp_recipients(), 'apify' => function_exists('cs_on') && cs_on(), 'hasSelf' => (bool)cmp_self()];
}

/* ---------- 48: تصدير CSV ---------- */
function cmp_csv(): string
{
    $tb = cmp_table(); $f = fopen('php://temp', 'w+'); fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, array_map('cmp_cell', array_merge([t('المؤشر')], array_map(function ($c) { return $c['name'] . ($c['self'] ? ' (' . t('أنت') . ')' : ''); }, $tb['cols']))));
    foreach ($tb['rows'] as $r) fputcsv($f, array_map('cmp_cell', array_merge([$r['label']], array_map(function ($x) { return $x['v'] === null ? '' : $x['v'] . ($x['rank'] ? ' (#' . $x['rank'] . ')' : ''); }, $r['cells']))));
    fputcsv($f, array_map('cmp_cell', array_merge([t('آخر نشاط (أيام)')], array_map(function ($c) { return $c['last'] === null ? '' : $c['last']; }, $tb['cols']))));
    fputcsv($f, array_map('cmp_cell', array_merge([t('خدمات هم أرخص فيها')], array_map(function ($c) { return $c['self'] ? '' : $c['cheaper']; }, $tb['cols']))));
    fputcsv($f, []); fputcsv($f, array_map('cmp_cell', [t('المنافس'), t('التاريخ'), t('التغيير'), t('التفاصيل')]));
    $st = db()->prepare('SELECT e.*, c.name cname FROM competitor_events e JOIN competitors c ON c.id = e.competitor_id WHERE e.workspace_id = ? AND e.kind <> "first" ORDER BY e.id DESC LIMIT 1000'); $st->execute([(int)ws('id')]);
    foreach ($st->fetchAll() as $e) fputcsv($f, array_map('cmp_cell', [(string)$e['cname'], substr((string)$e['created_at'], 0, 16), comp_ev_title($e['kind'], (string)$e['title']), (string)$e['detail']]));
    rewind($f); $s = stream_get_contents($f); fclose($f); return $s;
}
