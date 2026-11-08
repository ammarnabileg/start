<?php
/**
 * متابعة المنافسين (المرحلة 2: السوق): بنقرا موقع كل منافس يوميًا بنفس قارئ "املأ من موقعك"
 * ونقارن بالنسخة اللي فاتت: سعر طلع أو نزل، خدمة جديدة أو اتشالت، عرض جديد، الموقع وقع.
 * من غير ذكاء اصطناعي ومن غير أي API مدفوع. السوشيال مش بيتقري (فيسبوك وإنستجرام مش بيسمحوا).
 */

function comp_list(): array
{
    try { $st = db()->prepare('SELECT * FROM competitors WHERE workspace_id = ? ORDER BY name'); $st->execute([(int)ws('id')]); return $st->fetchAll(); } catch (Throwable $e) { return []; }
}

function comp_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM competitors WHERE id = ? AND workspace_id = ?'); $st->execute([$id, (int)ws('id')]); return $st->fetch() ?: null;
}

function comp_events(?int $cid = null, int $limit = 40): array
{
    $sql = 'SELECT e.*, c.name cname FROM competitor_events e JOIN competitors c ON c.id = e.competitor_id WHERE e.workspace_id = ?' . ($cid ? ' AND e.competitor_id = ?' : '') . ' ORDER BY e.id DESC LIMIT ' . max(1, min(200, $limit));
    try { $st = db()->prepare($sql); $st->execute($cid ? [(int)ws('id'), $cid] : [(int)ws('id')]); return $st->fetchAll(); } catch (Throwable $e) { return []; }
}

/** رقم السعر من نص زي "85 ريال" أو "SAR 1,250" (أرقام عربي كمان). null لو مفيش */
function comp_price_num(string $p): ?float
{
    $p = strtr($p, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٬' => ',', '٫' => '.']);
    if (!preg_match('/\d[\d,]*(?:\.\d+)?/', $p, $m)) return null;
    return (float)str_replace(',', '', $m[0]);
}

function comp_norm(string $s): string { return trim(preg_replace('/\s+/u', ' ', mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s)))); }

/** نسخة من موقع المنافس: الخدمات بأسعارها، العروض، المواعيد، التليفون */
function comp_snapshot(string $url): array
{
    $r = kb_import_site($url);
    if (!empty($r['err'])) return ['ok' => false, 'err' => (string)$r['err'], 'at' => time()];
    $S = $r['sections'] ?? []; $svc = [];
    foreach ((array)($S['services']['rows'] ?? []) as $row) { $n = trim((string)($row['name'] ?? '')); if ($n === '') continue; $svc[comp_norm($n)] = ['name' => $n, 'price' => trim((string)($row['price'] ?? ''))]; }
    return ['ok' => true, 'at' => time(), 'title' => (string)($S['company']['name'] ?? ''), 'services' => $svc, 'offers' => array_values((array)($r['offers'] ?? [])),
        'hours' => (string)($S['hours']['notes'] ?? ''), 'phone' => (string)($S['contact']['phone'] ?? ''), 'pages' => count((array)($r['pages'] ?? []))];
}

/** عنوان الحدث بلغة اللي بيقرا (بيتبني وقت العرض، مش وقت الحفظ، عشان مايتجمدش على لغة واحدة) */
function comp_ev_title(string $kind, string $subject): string
{
    switch ($kind) {
        case 'first': return t('أول قراءة للموقع');
        case 'down': return t('الموقع مش بيفتح');
        case 'up': return t('الموقع رجع يفتح');
        case 'svc_new': return t('خدمة جديدة: %s', $subject);
        case 'svc_gone': return t('شال خدمة: %s', $subject);
        case 'price_up': return t('رفع سعر %s', $subject);
        case 'price_down': return t('نزّل سعر %s', $subject);
        case 'price': return t('غيّر سعر %s', $subject);
        case 'offer_new': return t('عرض جديد');
        case 'offer_gone': return t('عرض خلص');
        case 'hours': return t('غيّر المواعيد');
        case 'soc_bio': return t('غيّر وصف حسابه على %s', $subject);
        case 'soc_name': return t('غيّر اسم حسابه على %s', $subject);
        case 'ad_new': return t('إعلان جديد: %s', $subject);
        case 'ad_stop': return t('أوقف إعلان: %s', $subject);
        case 'soc_link': return t('غيّر الرابط في حسابه على %s', $subject);
    }
    return $subject;
}
function comp_ev_detail(string $kind, ?string $detail): string
{
    $d = (string)$detail;
    if ($kind === 'first' && preg_match('/^(\d+)\|(\d+)$/', $d, $m)) return t('%s خدمة بسعر، %s عرض', $m[1], $m[2]);
    if ($kind === 'down' && $d !== '') return 'HTTP ' . $d;
    return $d;
}

/** الفرق بين نسختين → قايمة أحداث [kind, subject, detail]. subject = اسم الخدمة (من غير ترجمة) */
function comp_diff(array $old, array $new): array
{
    $ev = [];
    if (empty($old)) return [['first', '', count($new['services'] ?? []) . '|' . count($new['offers'] ?? [])]];
    if (empty($new['ok'])) return !empty($old['ok']) ? [['down', '', preg_match('/\d{3}/', (string)($new['err'] ?? ''), $hc) ? $hc[0] : '']] : [];
    if (empty($old['ok'])) $ev[] = ['up', '', ''];
    $o = (array)($old['services'] ?? []); $n = (array)($new['services'] ?? []);
    foreach ($n as $k => $s) {
        if (!isset($o[$k])) { $ev[] = ['svc_new', $s['name'], $s['price']]; continue; }
        if ($o[$k]['price'] === $s['price']) continue;
        $a = comp_price_num($o[$k]['price']); $b = comp_price_num($s['price']);
        $kind = ($a !== null && $b !== null) ? ($b > $a ? 'price_up' : ($b < $a ? 'price_down' : '')) : 'price';
        if ($kind === '') continue;
        $pct = ($a && $b !== null) ? ' (' . ($b > $a ? '+' : '') . round(($b - $a) / $a * 100) . '%)' : '';
        $ev[] = [$kind, $s['name'], $o[$k]['price'] . ' → ' . $s['price'] . $pct];
    }
    foreach ($o as $k => $s) if (!isset($n[$k])) $ev[] = ['svc_gone', $s['name'], $s['price']];
    $oo = array_flip(array_map('comp_norm', (array)($old['offers'] ?? [])));
    foreach ((array)($new['offers'] ?? []) as $x) if (!isset($oo[comp_norm($x)])) $ev[] = ['offer_new', '', mb_substr($x, 0, 160)];
    $no = array_flip(array_map('comp_norm', (array)($new['offers'] ?? [])));
    foreach ((array)($old['offers'] ?? []) as $x) if (!isset($no[comp_norm($x)])) $ev[] = ['offer_gone', '', mb_substr($x, 0, 160)];
    if (trim((string)($old['hours'] ?? '')) !== '' && trim((string)($new['hours'] ?? '')) !== '' && comp_norm($old['hours']) !== comp_norm($new['hours'])) $ev[] = ['hours', '', mb_substr($new['hours'], 0, 160)];
    return $ev;
}

/** يقرا موقع منافس ويسجل التغييرات. بيرجع عدد الأحداث */
function comp_check(array $c): int
{
    $old = json_decode((string)($c['snap'] ?? ''), true) ?: [];
    $new = comp_snapshot((string)$c['url']);
    // لو الموقع مش بيفتح بنحتفظ بآخر نسخة سليمة عشان المقارنة الجاية تبقى مع آخر بيانات حقيقية
    $keep = !empty($new['ok']) ? $new : (!empty($old) ? array_merge($old, ['ok' => false, 'err' => $new['err'] ?? '', 'at' => time()]) : $new);
    $ev = comp_diff($old, $new);
    $pdo = db(); $wid = (int)$c['workspace_id'];
    $pdo->prepare('UPDATE competitors SET snap = ?, last_check = NOW(), last_ok = ? WHERE id = ? AND workspace_id = ?')->execute([json_encode($keep, JSON_UNESCAPED_UNICODE), !empty($new['ok']) ? 1 : 0, (int)$c['id'], $wid]);
    $ins = $pdo->prepare('INSERT INTO competitor_events (workspace_id, competitor_id, kind, title, detail) VALUES (?, ?, ?, ?, ?)');
    foreach ($ev as $e) $ins->execute([$wid, (int)$c['id'], $e[0], mb_substr($e[1], 0, 255), mb_substr((string)$e[2], 0, 500)]);
    // تنبيه للمديرين بالتغييرات المهمة بس (مش أول قراءة)
    $important = array_filter($ev, function ($e) { return in_array($e[0], ['price_up', 'price_down', 'price', 'offer_new', 'svc_new', 'down'], true); });
    if ($important && function_exists('crm_notify') && module_enabled('crm')) {
        $first = array_values($important)[0];
        crm_notify(null, null, 'competitor', '👀 ' . $c['name'] . ': ' . comp_ev_title($first[0], $first[1]) . (count($important) > 1 ? ' ' . t('و%s تغيير تاني', count($important) - 1) : ''));
    }
    return count($ev);
}

/** التشغيل الدوري: كل منافس مرة في اليوم، لحد 10 في المرة */
function comp_tick(int $max = 10): int
{
    try {
        $st = db()->prepare('SELECT * FROM competitors WHERE workspace_id = ? AND (last_check IS NULL OR last_check < ?) ORDER BY last_check LIMIT ' . max(1, $max));
        $st->execute([(int)ws('id'), date('Y-m-d H:i:s', time() - 20 * 3600)]);
    } catch (Throwable $e) { return 0; }
    $n = 0; foreach ($st->fetchAll() as $c) { try { $n += comp_check($c); } catch (Throwable $e) {} }
    return $n;
}

/** مقارنة الأسعار: خدماتك (من ملف الشركة) قدام خدمات المنافسين بنفس الاسم أو اسم قريب */
function comp_price_table(array $comps): array
{
    $kb = function_exists('kb_get') ? kb_get((int)ws('id')) : []; $rows = [];
    foreach ((array)($kb['services']['rows'] ?? []) as $r) {
        $name = trim((string)($r['name'] ?? '')); if ($name === '') continue; $k = comp_norm($name);
        $row = ['name' => $name, 'mine' => trim((string)($r['price'] ?? '')), 'them' => []];
        foreach ($comps as $c) {
            $snap = json_decode((string)($c['snap'] ?? ''), true) ?: []; $hit = null;
            foreach ((array)($snap['services'] ?? []) as $sk => $s) {
                if ($sk === $k || (mb_strlen($k) >= 4 && mb_strlen($sk) >= 4 && (mb_strpos($sk, $k) !== false || mb_strpos($k, $sk) !== false))) { $hit = $s; break; }
            }
            $row['them'][(int)$c['id']] = $hit;
        }
        $rows[] = $row;
    }
    return $rows;
}
