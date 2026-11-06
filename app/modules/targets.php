<?php
/**
 * الشركات المستهدفة. 3 مصادر بتضيف شركات:
 *  manual  : المستخدم بيكتب دومين الشركة، أو اسمها لو مالهاش موقع
 *  inbound : شركات العملاء اللي كلمونا من جوه المنصة (رسائل، تعليقات، فورم، إعلانات...)
 *  ai      : الذكاء الاصطناعي بيحلل قاعدة المعرفة ويطلّع مواصفات العميل المثالي، وApollo بيرجّع شركات حقيقية
 * (الشركات القديمة من Apollo مالهاش مصدر = apollo)
 */

function tg_sources(): array
{
    return ['manual' => t('أضفتها'), 'inbound' => t('تواصلت معنا'), 'ai' => t('اقتراح الذكاء الاصطناعي'), 'apollo' => 'Apollo'];
}

/** دومين من إيميل شغل (مش جيميل وأمثاله) */
function tg_email_domain(?string $email): string
{
    $d = strtolower((string)substr(strrchr((string)$email, '@') ?: '', 1));
    if ($d === '' || preg_match('/^(gmail|googlemail|hotmail|outlook|live|yahoo|icloud|me|msn|aol|proton|protonmail|yandex|mail|gmx)\./', $d)) return '';
    return $d;
}

/** الشركات اللي العملاء جايين منها من جوه المنصة ومش متسجلة لسه: بتتعمل وبتتربط بالعملاء. بيرجع عدد الشركات الجديدة */
function tg_sync_inbound(int $max = 300): int
{
    $wid = (int)ws('id'); $pdo = db();
    $st = $pdo->prepare("SELECT id, company, email FROM crm_leads WHERE workspace_id = ? AND company_id IS NULL AND ((company IS NOT NULL AND TRIM(company) <> '') OR email LIKE '%@%') ORDER BY id DESC LIMIT " . max(1, $max));
    $st->execute([$wid]);
    $find = $pdo->prepare('SELECT id FROM crm_companies WHERE workspace_id = ? AND (ext_id = ? OR (? <> \'\' AND domain = ?) OR LOWER(name) = LOWER(?)) LIMIT 1');
    $ins = $pdo->prepare('INSERT INTO crm_companies (workspace_id, ext_id, name, domain, source, first_batch, last_batch) VALUES (?, ?, ?, ?, \'inbound\', 0, 0)');
    $link = $pdo->prepare('UPDATE crm_leads SET company_id = ? WHERE id = ? AND workspace_id = ?');
    $new = 0;
    foreach ($st->fetchAll() as $l) {
        $name = trim((string)$l['company']); $dom = tg_email_domain($l['email']);
        if ($name === '' && $dom === '') continue;
        if ($name === '') $name = $dom;
        $ext = $dom !== '' ? 'dom:' . $dom : 'name:' . md5(mb_strtolower($name));
        $find->execute([$wid, $ext, $dom, $dom, $name]); $cid = (int)$find->fetchColumn();
        if (!$cid) { $ins->execute([$wid, $ext, mb_substr($name, 0, 255), $dom ?: null]); $cid = (int)$pdo->lastInsertId(); $new++; }
        $link->execute([$cid, (int)$l['id'], $wid]);
    }
    return $new;
}

/** إضافة يدوية: دومين (Apollo بيكمّل البيانات لو مربوط) أو اسم شركة مالهاش موقع. بيرجع ['id' => , 'dup' => bool] */
function tg_add(string $in): array
{
    $wid = (int)ws('id'); $in = trim($in);
    if ($in === '') throw new RuntimeException(t('اكتب دومين الشركة أو اسمها.'));
    $d = strtolower(preg_replace('#[/?\#].*$#', '', preg_replace('#^(https?://)?(www\.)?#i', '', $in)));
    if (preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $d)) {
        $st = db()->prepare('SELECT id FROM crm_companies WHERE workspace_id = ? AND (domain = ? OR ext_id = ?) LIMIT 1'); $st->execute([$wid, $d, 'dom:' . $d]);
        if ($id = (int)$st->fetchColumn()) return ['id' => $id, 'dup' => true];
        $o = [];
        if (function_exists('crm_has_key') && crm_has_key()) { try { $o = (array)(crm_api('/organizations/enrich', ['domain' => $d], null, 'GET')['organization'] ?? []); } catch (Throwable $e) { $o = []; } }
        $ext = !empty($o['id']) ? (string)$o['id'] : 'dom:' . $d;
        $st = db()->prepare('SELECT id FROM crm_companies WHERE workspace_id = ? AND ext_id = ? LIMIT 1'); $st->execute([$wid, $ext]);
        if ($id = (int)$st->fetchColumn()) return ['id' => $id, 'dup' => true];
        db()->prepare('INSERT INTO crm_companies (workspace_id, ext_id, name, domain, industry, employees, city, country, logo, linkedin, phone, source, first_batch, last_batch) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'manual\', 0, 0)')
            ->execute([$wid, mb_substr($ext, 0, 64), mb_substr((string)($o['name'] ?? $d), 0, 255), $d, ($o['industry'] ?? '') ?: null, isset($o['estimated_num_employees']) ? (int)$o['estimated_num_employees'] : null,
                ($o['city'] ?? '') ?: null, ($o['country'] ?? '') ?: null, ($o['logo_url'] ?? '') ?: null, ($o['linkedin_url'] ?? '') ?: null, mb_substr((string)($o['primary_phone']['sanitized_number'] ?? ''), 0, 40) ?: null]);
        return ['id' => (int)db()->lastInsertId(), 'dup' => false];
    }
    $name = mb_substr(preg_replace('/\s+/u', ' ', $in), 0, 255);
    $st = db()->prepare('SELECT id FROM crm_companies WHERE workspace_id = ? AND LOWER(name) = LOWER(?) LIMIT 1'); $st->execute([$wid, $name]);
    if ($id = (int)$st->fetchColumn()) return ['id' => $id, 'dup' => true];
    db()->prepare('INSERT INTO crm_companies (workspace_id, ext_id, name, source, first_batch, last_batch) VALUES (?, ?, ?, \'manual\', 0, 0)')->execute([$wid, 'name:' . md5(mb_strtolower($name)), $name]);
    return ['id' => (int)db()->lastInsertId(), 'dup' => false];
}

/** ملخص قاعدة المعرفة اللي الذكاء الاصطناعي بيحلله */
function tg_kb_brief(): string
{
    $kb = function_exists('kb_get') ? kb_get((int)ws('id')) : []; $c = (array)($kb['company'] ?? []); $out = [];
    foreach (['name' => 'Company', 'industry' => 'Industry', 'tagline' => 'Tagline', 'about' => 'About', 'service_area' => 'Service area'] as $k => $l) if (trim((string)($c[$k] ?? '')) !== '') $out[] = $l . ': ' . mb_substr(trim((string)$c[$k]), 0, 400);
    $sv = array_filter(array_map(function ($r) { return trim((string)($r['name'] ?? '')); }, (array)($kb['services']['rows'] ?? [])));
    if ($sv) $out[] = 'Services: ' . implode('، ', array_slice($sv, 0, 20));
    foreach ((array)($kb['audience'] ?? []) as $k => $v) if (is_string($v) && trim($v) !== '') $out[] = 'Audience ' . $k . ': ' . mb_substr(trim($v), 0, 400);
    if (!empty($kb['frame_ref']['category'])) $out[] = 'Category: ' . mb_substr((string)$kb['frame_ref']['category'], 0, 200);
    $out[] = 'Country: ' . (function_exists('cx_name') ? cx_name() : '');
    return implode("\n", $out);
}

/**
 * اقتراح شركات: الذكاء الاصطناعي بيطلّع مواصفات العميل المثالي من قاعدة المعرفة، وApollo بيرجّع شركات حقيقية بالمواصفات دي.
 * بيرجع ['added' => n, 'icp' => [...]]
 */
function tg_ai_suggest(int $page = 1): array
{
    if (!function_exists('crm_has_key') || !crm_has_key()) throw new RuntimeException(t('اقتراح الشركات محتاج Apollo مربوط من التكاملات.'));
    if (!function_exists('ai_connection') || !ai_connection()) throw new RuntimeException(t('اقتراح الشركات محتاج مزود ذكاء اصطناعي مربوط من التكاملات.'));
    $brief = tg_kb_brief();
    if (mb_strlen($brief) < 60) throw new RuntimeException(t('قاعدة المعرفة ناقصة. كمّل "عن الشركة" و"الخدمات" و"الجمهور المستهدف" الأول.'));
    $wid = (int)ws('id');
    $icp = json_decode((string)setting('tg_icp_' . $wid, ''), true);
    if (!is_array($icp) || ($icp['kb'] ?? '') !== md5($brief)) {
        $r = ai_chat([['role' => 'system', 'content' => 'TARGET_ICP_JSON. You are a B2B sales analyst. From the company profile, describe the companies that would BUY from this company (its ideal customers, not its competitors). Return ONLY JSON: {"keywords":["3-6 short English industry keywords for Apollo company search"],"locations":["countries or cities in English"],"sizes":["Apollo ranges like 11,50 or 51,200"],"why":"one short Arabic sentence"}'],
            ['role' => 'user', 'content' => $brief]], 400, 'crm');
        $txt = (string)($r['text'] ?? ''); if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
        $j = json_decode($txt, true);
        if (!is_array($j) || empty($j['keywords'])) throw new RuntimeException(t('الذكاء الاصطناعي مارجّعش مواصفات واضحة. جرّب تاني.'));
        $icp = ['keywords' => implode(', ', array_slice(array_map('strval', (array)$j['keywords']), 0, 6)), 'locations' => array_slice(array_map('strval', (array)($j['locations'] ?? [])), 0, 5),
            'sizes' => array_slice(array_map('strval', (array)($j['sizes'] ?? [])), 0, 4), 'why' => mb_substr((string)($j['why'] ?? ''), 0, 240), 'kb' => md5($brief), 'page' => 1];
        log_activity('ai.targets', $icp['keywords']);
    }
    $prefs = crm_prefs(); $batch = (int)$prefs['batch'] + 1;
    [$new, , , $b] = crm_refresh(['icp' => $icp, 'next_page' => max(1, (int)($icp['page'] ?? 1)), 'batch' => $prefs['batch']] + $prefs);
    // كل شركة رجعت في الاقتراح ومالهاش مصدر بتتعلّم "اقتراح الذكاء الاصطناعي" (اللي المستخدم شالها بتفضل مخفية)
    $u = db()->prepare("UPDATE crm_companies SET source = 'ai' WHERE workspace_id = ? AND last_batch = ? AND source IS NULL"); $u->execute([$wid, (int)$b]);
    $new = max((int)$new, $u->rowCount());
    $icp['page'] = (int)($icp['page'] ?? 1) + 1; save_setting('tg_icp_' . $wid, json_encode($icp, JSON_UNESCAPED_UNICODE));
    return ['added' => (int)$new, 'icp' => $icp];
}

/** كل الشركات المستهدفة مع عدد الأشخاص المعروفين وبياناتهم */
function tg_list(): array
{
    $st = db()->prepare("SELECT c.*, COUNT(l.id) people_n, SUM(l.phone IS NOT NULL AND l.phone <> '') phones, SUM(l.email IS NOT NULL AND l.email <> '') emails,
        SUM(l.stage = 'won') won_n, SUM(l.stage IN ('contacted','qualified')) warm_n, COALESCE(SUM(l.deal_value), 0) deal_sum, MAX(l.last_contact_at) last_at
        FROM crm_companies c LEFT JOIN crm_leads l ON l.company_id = c.id AND l.workspace_id = c.workspace_id
        WHERE c.workspace_id = ? AND c.hidden = 0 GROUP BY c.id ORDER BY c.id DESC LIMIT 1000");
    $st->execute([(int)ws('id')]); return $st->fetchAll();
}

/** أهم الأشخاص في الشركة من Apollo (من غير كشف)، 5 في الصفحة */
function tg_people(array $co, int $page = 1): array
{
    $dom = (string)($co['domain'] ?? ''); $ext = (string)$co['ext_id'];
    if (strpos($ext, 'name:') === 0 && $dom === '') return ['people' => [], 'more' => false, 'reason' => 'nodomain'];
    $body = $dom !== '' && (strpos($ext, 'dom:') === 0 || strpos($ext, 'name:') === 0) ? ['q_organization_domains_list' => [$dom]] : ['organization_ids' => [$ext]];
    $body += ['person_seniorities' => crm_seniorities(), 'page' => max(1, $page), 'per_page' => 5];
    $j = crm_api('/mixed_people/api_search', $body); $out = [];
    foreach ((array)($j['people'] ?? []) as $p) {
        if (empty($p['id'])) continue;
        $out[] = ['id' => (string)$p['id'], 'name' => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? $p['last_name_obfuscated'] ?? '')), 'title' => (string)($p['title'] ?? ''),
            'has_email' => !empty($p['has_email']), 'has_phone' => !empty($p['has_direct_phone']) && $p['has_direct_phone'] !== 'No'];
    }
    $total = (int)($j['total_entries'] ?? $j['pagination']['total_entries'] ?? 0);
    return ['people' => $out, 'more' => $total ? $page * 5 < $total : count($out) === 5];
}

/**
 * كل شيتات ملف Excel (.xlsx) بالترتيب: [['name' => اسم الشيت, 'rows' => صفوف]]. مكان كل عمود محفوظ (الخانة الفاضية بتفضل فاضية).
 * من غير مكتبات: الملف zip فيه XML. الأرقام الكبيرة (زي أرقام الموبايل) بترجع أرقام كاملة مش 9.6E+11.
 */
function xlsx_sheets(string $path, int $max = 5000): array
{
    $z = new ZipArchive(); if ($z->open($path) !== true) throw new RuntimeException(t('الملف ده مش Excel (.xlsx).'));
    $shared = [];
    if (($ss = $z->getFromName('xl/sharedStrings.xml')) !== false && preg_match_all('#<si>(.*?)</si>#s', $ss, $m)) {
        foreach ($m[1] as $si) { preg_match_all('#<t[^>]*>(.*?)</t>#s', $si, $tt); $shared[] = html_entity_decode(implode('', $tt[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'); }
    }
    $list = []; $wb = $z->getFromName('xl/workbook.xml'); $rel = (string)$z->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb !== false && preg_match_all('#<sheet ([^>]*)/?>#', $wb, $sm)) foreach ($sm[1] as $attr) {
        $nm = preg_match('/name="([^"]*)"/', $attr, $x) ? html_entity_decode($x[1], ENT_QUOTES | ENT_XML1, 'UTF-8') : 'Sheet';
        // ترتيب الخصائص في Relationship بيختلف من برنامج لبرنامج (Id قبل Target أو بعده)
        $file = null; if (preg_match('/r:id="([^"]+)"/', $attr, $x) && preg_match('#<Relationship\b[^>]*\bId="' . preg_quote($x[1], '#') . '"[^>]*>#', $rel, $re) && preg_match('/\bTarget="([^"]+)"/', $re[0], $rm)) $file = 'xl/' . ltrim(preg_replace('#^/?xl/#', '', $rm[1]), '/');
        if ($file) $list[] = [$nm, $file];
    }
    if (!$list) $list = [['Sheet1', 'xl/worksheets/sheet1.xml']];
    $col = function (string $ref): int { $n = 0; foreach (str_split(preg_replace('/\d+/', '', $ref)) as $ch) $n = $n * 26 + (ord(strtoupper($ch)) - 64); return max(0, $n - 1); };
    $out = []; $total = 0;
    foreach (array_slice($list, 0, 30) as [$nm, $file]) {
        $x = $z->getFromName($file); if ($x === false) continue; $rows = [];
        preg_match_all('#<row[^>]*>(.*?)</row>#s', $x, $rr);
        foreach ($rr[1] as $row) {
            preg_match_all('#<c([^>]*?)(?:/>|>(.*?)</c>)#s', $row, $cells, PREG_SET_ORDER); $vals = []; $i = 0;
            foreach ($cells as $c) {
                if (preg_match('/r="([A-Z]+\d+)"/', $c[1], $ref)) $i = $col($ref[1]);
                $in = $c[2] ?? ''; $v = '';
                if (preg_match('#<v>(.*?)</v>#s', $in, $vm)) $v = $vm[1]; elseif (preg_match_all('#<t[^>]*>(.*?)</t>#s', $in, $tm)) $v = implode('', $tm[1]);
                if (strpos($c[1], 't="s"') !== false) $v = $shared[(int)$v] ?? '';
                else { $v = html_entity_decode($v, ENT_QUOTES | ENT_XML1, 'UTF-8'); if (is_numeric($v) && stripos($v, 'e') !== false) $v = number_format((float)$v, 0, '.', ''); elseif (preg_match('/^\d+\.0+$/', $v)) $v = preg_replace('/\.0+$/', '', $v); }
                $vals[$i] = trim((string)$v); $i++;
            }
            if (!$vals || implode('', $vals) === '') continue;
            $line = []; for ($k = 0, $n = max(array_keys($vals)); $k <= $n; $k++) $line[] = $vals[$k] ?? '';
            $rows[] = $line; if (++$total >= $max) break;
        }
        if ($rows) $out[] = ['name' => $nm, 'rows' => $rows];
        if ($total >= $max) break;
    }
    $z->close();
    if (!$out) throw new RuntimeException(t('مالقيناش شيت في الملف.'));
    return $out;
}

/**
 * العملاء المستهدفين = كل حد اتعامل معانا: بعت رسالة، علّق، اتسجل في فورم أو حجز، جه من حملة أو إعلان، أو ضفناه بنفسنا (يدوي، استيراد، Apollo، جهات اتصال واتساب).
 * الفورم والحجز والاستيراد وApollo بيتسجلوا وقتها. هنا بنجمع الباقي من Zernio: المحادثات، والتعليقات، وجهات اتصال واتساب، وعملاء الإعلانات.
 * مابنعملش متابعة (next_at) للي بيتجمعوا هنا عشان المهام ماتتغرقش. بيرجع عدد الجداد.
 */
function tg_capture_all(Zernio $z, int $max = 300): int
{
    $wid = (int)ws('id'); $new = 0;
    $labels = function_exists('ai_labels') ? ai_labels($wid) : [];
    $st = db()->prepare("SELECT ext_id FROM crm_leads WHERE workspace_id = ? AND ext_id IS NOT NULL"); $st->execute([$wid]); $have = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    $spam = function ($lb) { return $lb && in_array($lb['category'] ?? '', ['spam'], true); };
    $add = function (array $d) use (&$new, &$have) { if (isset($have[$d['ext']])) return; $l = crm_from_social($d + ['owner_id' => null]); $have[$d['ext']] = 1; if ($l && empty($l['merged'])) $new++; };
    // الرسائل: كل المحادثات
    try { $conv = $z->conversations(['limit' => 100, 'profileId' => zernio_profile()])['data'] ?? []; } catch (Throwable $e) { $conv = []; }
    foreach ($conv as $c) {
        if ($new >= $max) break; $id = (string)($c['id'] ?? ''); if ($id === '' || $spam($labels[$id] ?? null)) continue;
        $pf = (string)($c['platform'] ?? ''); $m = $c['lastMessage'] ?? ''; $m = trim(is_array($m) ? (string)($m['text'] ?? $m['message'] ?? '') : (string)$m);
        $add(['source' => 'inbox', 'ext' => 'ib:' . $pf . ':' . (string)($c['participantId'] ?? $id), 'name' => (string)($c['participantName'] ?? $c['participantUsername'] ?? ''), 'text' => mb_substr($m, 0, 300),
            'where' => platform_label($pf), 'url' => url('inbox', ['c' => $id, 'a' => (string)($c['accountId'] ?? '')])]);
    }
    // التعليقات: كل اللي علّقوا على آخر 10 منشورات
    try { $posts = array_slice($z->commentedPosts(['limit' => 20, 'profileId' => zernio_profile()])['data'] ?? [], 0, 10); } catch (Throwable $e) { $posts = []; }
    foreach ($posts as $p) {
        if ($new >= $max) break;
        try { $cs = $z->comments((string)$p['id'], (string)($p['accountId'] ?? ''))['comments'] ?? []; } catch (Throwable $e) { continue; }
        $flat = []; $walk = function ($l) use (&$walk, &$flat) { foreach ($l as $c) { $flat[] = $c; $walk($c['replies'] ?? []); } }; $walk($cs);
        foreach ($flat as $c) {
            $from = $c['from'] ?? []; if (!empty($from['isOwner']) || $spam($labels['c:' . ($c['id'] ?? '')] ?? null)) continue;
            $pf = (string)($c['platform'] ?? $p['platform'] ?? ''); $nm = (string)($from['name'] ?? $from['username'] ?? ''); if ($nm === '' && empty($from['id'])) continue;
            $add(['source' => 'comments', 'ext' => 'cm:' . $pf . ':' . (string)($from['id'] ?? $from['username'] ?? $nm), 'name' => $nm, 'text' => mb_substr(trim((string)($c['message'] ?? '')), 0, 300),
                'where' => platform_label($pf), 'url' => url('comments', ['post' => (string)$p['id'], 'a' => (string)($p['accountId'] ?? '')])]);
        }
    }
    // جهات اتصال واتساب (ضفناهم بنفسنا)
    if (function_exists('wa_contacts')) {
        try { $wc = wa_contacts($z, ['limit' => 200])['contacts'] ?? []; } catch (Throwable $e) { $wc = []; }
        foreach ($wc as $c) {
            if ($new >= $max) break; $ph = (string)($c['phone'] ?? $c['phoneNumber'] ?? $c['waId'] ?? ''); if ($ph === '') continue;
            $ext = 'wa:' . preg_replace('/\D/', '', $ph); if (isset($have[$ext])) continue;
            $l = crm_lead_upsert(['source' => 'whatsapp', 'ext_id' => $ext, 'name' => (string)($c['name'] ?? $c['profileName'] ?? ''), 'phone' => $ph, 'owner_id' => null]);
            $have[$ext] = 1; if ($l && empty($l['merged'])) $new++;
        }
    }
    // عملاء الإعلانات (فورم الإعلانات)
    if (function_exists('crm_sync_ads')) { try { $r = crm_sync_ads($z, 2); $new += (int)($r['new'] ?? $r[0] ?? 0); } catch (Throwable $e) {} }
    save_setting('tg_sync_' . $wid, date('Y-m-d H:i:s') . '|' . $new);
    if ($new) log_activity('crm.capture_all', $new . ' new');
    return $new;
}

/**
 * تكميل بيانات شركة: Apollo بالدومين (المجال، الحجم، المكان، اللوجو، لينكدإن، التليفون)، ولو مفيش: من موقع الشركة نفسه
 * (الاسم، اللوجو، الوصف). بيكمّل الفاضي بس، وبيسجل إنه اتجرب عشان مايتكررش. بيرجع الشركة بعد التحديث.
 */
function tg_enrich(array $co): array
{
    $wid = (int)ws('id'); $dom = trim((string)$co['domain']); $set = []; $v = [];
    $fill = function (string $col, $val) use ($co, &$set, &$v) { if ($val === null || $val === '' || (isset($co[$col]) && $co[$col] !== null && $co[$col] !== '')) return; $set[] = "$col = ?"; $v[] = $val; };
    if ($dom !== '' && function_exists('crm_has_key') && crm_has_key()) {
        try { $o = (array)(crm_api('/organizations/enrich', ['domain' => $dom], null, 'GET')['organization'] ?? []); } catch (Throwable $e) { $o = []; }
        if ($o) {
            if (($co['name'] === $dom || $co['name'] === '' ) && !empty($o['name'])) { $set[] = 'name = ?'; $v[] = mb_substr((string)$o['name'], 0, 255); }
            $fill('industry', $o['industry'] ?? null); $fill('employees', isset($o['estimated_num_employees']) ? (int)$o['estimated_num_employees'] : null);
            $fill('city', $o['city'] ?? null); $fill('country', $o['country'] ?? null); $fill('logo', $o['logo_url'] ?? null); $fill('linkedin', $o['linkedin_url'] ?? null);
            $fill('phone', mb_substr((string)($o['primary_phone']['sanitized_number'] ?? $o['phone'] ?? ''), 0, 40)); $fill('about', mb_substr(trim((string)($o['short_description'] ?? '')), 0, 500));
            if (!empty($o['id']) && strpos((string)$co['ext_id'], 'dom:') === 0) { $set[] = 'ext_id = ?'; $v[] = mb_substr((string)$o['id'], 0, 64); }
        }
    }
    if ($dom !== '' && (empty($co['logo']) || empty($co['about'])) && !in_array('logo = ?', $set, true) && function_exists('safe_fetch')) {
        $r = safe_fetch('https://' . $dom, ['max' => 300000, 'accept' => '~html~i', 'timeout' => 8]);
        if (!empty($r['body'])) { $h = (string)$r['body'];
            $meta = function ($n) use ($h) { return preg_match('~<meta[^>]+(?:property|name)=["\']' . preg_quote($n, '~') . '["\'][^>]*content=["\']([^"\']+)~i', $h, $m) || preg_match('~<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . preg_quote($n, '~') . '["\']~i', $h, $m) ? trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')) : ''; };
            $img = $meta('og:image'); if ($img === '' && preg_match('~<link[^>]+rel=["\'](?:apple-touch-icon|icon)["\'][^>]*href=["\']([^"\']+)~i', $h, $m)) $img = $m[1];
            if ($img !== '' && !preg_match('~^https?://~i', $img)) $img = 'https://' . $dom . '/' . ltrim($img, '/');
            if ($img !== '' && preg_match('~^https://~i', $img)) $fill('logo', mb_substr($img, 0, 500));
            $fill('about', mb_substr($meta('og:description') ?: $meta('description'), 0, 500));
            $sn = $meta('og:site_name'); if ($sn !== '' && $co['name'] === $dom) { $set[] = 'name = ?'; $v[] = mb_substr($sn, 0, 255); }
        }
    }
    $set[] = 'enriched_at = NOW()'; $v[] = (int)$co['id']; $v[] = $wid;
    db()->prepare('UPDATE crm_companies SET ' . implode(', ', $set) . ' WHERE id = ? AND workspace_id = ?')->execute($v);
    $st = db()->prepare('SELECT * FROM crm_companies WHERE id = ? AND workspace_id = ?'); $st->execute([(int)$co['id'], $wid]); return $st->fetch() ?: $co;
}

/** تكميل تلقائي للشركات اللي ليها دومين ومالهاش بيانات ومااتجربتش قبل كده. بيرجع عدد اللي اتكمّلت */
function tg_enrich_due(int $max = 8): int
{
    $st = db()->prepare("SELECT * FROM crm_companies WHERE workspace_id = ? AND hidden = 0 AND domain IS NOT NULL AND domain <> '' AND enriched_at IS NULL AND (industry IS NULL OR logo IS NULL) ORDER BY id DESC LIMIT " . max(1, $max));
    $st->execute([(int)ws('id')]); $n = 0;
    foreach ($st->fetchAll() as $co) { try { $after = tg_enrich($co); if ($after['industry'] !== $co['industry'] || $after['logo'] !== $co['logo'] || $after['name'] !== $co['name']) $n++; } catch (Throwable $e) {} }
    return $n;
}

/** حالات الشركة المستهدفة حسب أشخاصها: [الاسم، لون] */
function tg_statuses(): array
{
    return ['won' => [t('عميل'), 'bg-lime-100 text-lime-800'], 'warm' => [t('مهتمة'), 'bg-amber-50 text-amber-800'], 'talk' => [t('اتواصلنا'), 'bg-brand-50 text-brand-700'], 'new' => [t('جديدة'), 'bg-ink-100 text-ink-600']];
}
function tg_status(array $r): string
{
    if ((int)$r['won_n']) return 'won';
    if ((int)$r['warm_n']) return 'warm';
    return (int)$r['people_n'] ? 'talk' : 'new';
}
