<?php
/**
 * إدارة العملاء: الشركات المستهدفة من Apollo.io + كشف بيانات الأشخاص + الـ Leads الموحدة.
 * Apollo: البحث عن الشركات بيخصم 1 كريديت لكل صفحة، البحث عن الأشخاص مجاني ومابيرجعش إيميل ولا رقم،
 * والكشف (people/match) بيخصم كريديت، والرقم بيوصل بعدين على ويبهوك.
 */

class CrmException extends Exception {}

function crm_stages(): array
{
    return ['new' => t('جديد'), 'contacted' => t('اتكلمنا معاه'), 'qualified' => t('مهتم'), 'proposal' => t('عرض سعر'), 'won' => t('بقى عميل'), 'lost' => t('مش مناسب')];
}

function crm_sources(): array
{
    return ['reveal' => t('كشف من الشركات'), 'ads' => t('إعلانات'), 'whatsapp' => t('واتساب'), 'manual' => t('يدوي')];
}

function crm_sizes(): array
{
    return ['1,10' => '1–10', '11,50' => '11–50', '51,200' => '51–200', '201,500' => '201–500', '501,1000' => '501–1000', '1001,5000' => '1001–5000', '5001,1000000' => '5000+'];
}

/** أولوية صناع القرار في البحث عن الأشخاص */
function crm_seniorities(): array
{
    return ['owner', 'founder', 'c_suite', 'partner', 'vp', 'head', 'director', 'manager'];
}

function crm_has_key(): bool
{
    return crm_fake() !== null || connector_secret('apollo', 'api_key') !== '';
}

/** اختبارات بس: بديل لـ Apollo من مجلد الـ fixtures */
function crm_fake(): ?callable
{
    static $f = false;
    if ($f === false) {
        $d = getenv('UI_FIXTURES');
        $f = $d && is_file($d . '/crm_fake.php') ? require $d . '/crm_fake.php' : null;
    }
    return $f;
}

function crm_api(string $path, array $body, ?string $key = null): array
{
    if ($fake = crm_fake()) return $fake($path, $body);
    $key = $key ?? connector_secret('apollo', 'api_key');
    if ($key === '') throw new CrmException(t('اربط حساب Apollo.io من التكاملات الأول.'));
    $ch = curl_init('https://api.apollo.io/api/v1' . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Cache-Control: no-cache', 'x-api-key: ' . $key],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new CrmException(t('مش قادرين نوصل لـ Apollo: %s', $cerr));
    $j = json_decode((string)$raw, true);
    $msg = is_array($j) ? (string)($j['error'] ?? $j['message'] ?? '') : mb_substr((string)$raw, 0, 200);
    if ($code === 401) throw new CrmException(t('مفتاح Apollo غلط أو اتلغى.'));
    if ($code === 403) throw new CrmException(t('مفتاح Apollo مش مسموحله بالعملية دي. اعمل Master Key من إعدادات Apollo. (%s)', $msg));
    if ($code === 429) throw new CrmException(t('Apollo وقّف الطلبات مؤقتًا (كتير أو الكريديت خلص). جرب كمان شوية.'));
    if ($code >= 400) throw new CrmException(t('Apollo رد بخطأ (%s): %s', $code, $msg));
    return is_array($j) ? $j : [];
}

function crm_prefs(): array
{
    $wid = (int)ws('id');
    $st = db()->prepare('SELECT * FROM crm_prefs WHERE workspace_id = ?');
    $st->execute([$wid]);
    $r = $st->fetch();
    if (!$r) {
        db()->prepare('INSERT IGNORE INTO crm_prefs (workspace_id, hook_token) VALUES (?, ?)')->execute([$wid, bin2hex(random_bytes(16))]);
        $st->execute([$wid]);
        $r = $st->fetch();
    }
    $r['icp'] = json_decode((string)$r['icp'], true) ?: ['locations' => [], 'keywords' => '', 'sizes' => [], 'titles' => ''];
    return $r;
}

function crm_save_prefs(array $set): void
{
    $cols = [];
    $vals = [];
    foreach ($set as $k => $v) {
        if (!in_array($k, ['icp', 'daily_limit', 'next_page', 'batch', 'auto_assign', 'rr_last'], true)) continue;
        $cols[] = "$k = ?";
        $vals[] = $k === 'icp' ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
    }
    if (!$cols) return;
    $vals[] = (int)ws('id');
    db()->prepare('UPDATE crm_prefs SET ' . implode(', ', $cols) . ' WHERE workspace_id = ?')->execute($vals);
}

/** فلاتر العميل المثالي → جسم طلب Apollo */
function crm_icp_query(array $icp): array
{
    $q = [];
    $loc = array_values(array_filter(array_map('trim', (array)($icp['locations'] ?? []))));
    if ($loc) $q['organization_locations'] = $loc;
    $sizes = array_values(array_intersect((array)($icp['sizes'] ?? []), array_keys(crm_sizes())));
    if ($sizes) $q['organization_num_employees_ranges'] = $sizes;
    $kw = array_values(array_filter(array_map('trim', explode(',', (string)($icp['keywords'] ?? '')))));
    if ($kw) $q['q_organization_keyword_tags'] = $kw;
    return $q;
}

/** "تحديث": يجيب الصفحة اللي عليها الدور ويخزن الشركات. يرجع [جديد, اتكرر, اللفة رجعت للأول؟] */
function crm_refresh(array $prefs): array
{
    $page = max(1, (int)$prefs['next_page']);
    $body = crm_icp_query($prefs['icp']) + ['page' => $page, 'per_page' => 25];
    $j = crm_api('/mixed_companies/search', $body);
    $orgs = array_merge((array)($j['organizations'] ?? []), (array)($j['accounts'] ?? []));
    $wrapped = false;
    if (!$orgs && $page > 1) {
        // خلصنا النتايج: نرجع لأول صفحة
        $wrapped = true;
        $page = 1;
        $j = crm_api('/mixed_companies/search', ['page' => 1] + $body);
        $orgs = array_merge((array)($j['organizations'] ?? []), (array)($j['accounts'] ?? []));
    }
    $batch = (int)$prefs['batch'] + 1;
    $wid = (int)ws('id');
    $new = 0;
    $old = 0;
    $seen = [];
    $ins = db()->prepare('INSERT INTO crm_companies (workspace_id, ext_id, name, domain, industry, employees, city, country, logo, linkedin, phone, first_batch, last_batch)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE name = VALUES(name), domain = COALESCE(VALUES(domain), domain), industry = COALESCE(VALUES(industry), industry),
        employees = COALESCE(VALUES(employees), employees), city = COALESCE(VALUES(city), city), country = COALESCE(VALUES(country), country),
        logo = COALESCE(VALUES(logo), logo), linkedin = COALESCE(VALUES(linkedin), linkedin), phone = COALESCE(VALUES(phone), phone), last_batch = VALUES(last_batch)');
    foreach ($orgs as $o) {
        $id = (string)($o['organization_id'] ?? $o['id'] ?? '');
        if ($id === '' || isset($seen[$id]) || trim((string)($o['name'] ?? '')) === '') continue;
        $seen[$id] = 1;
        $ins->execute([$wid, mb_substr($id, 0, 64), mb_substr((string)$o['name'], 0, 255),
            ($o['primary_domain'] ?? '') ?: null, ($o['industry'] ?? '') ?: null,
            isset($o['estimated_num_employees']) ? (int)$o['estimated_num_employees'] : null,
            ($o['city'] ?? '') ?: null, ($o['country'] ?? '') ?: null, ($o['logo_url'] ?? '') ?: null, ($o['linkedin_url'] ?? '') ?: null,
            mb_substr((string)($o['primary_phone']['sanitized_number'] ?? $o['phone'] ?? ''), 0, 40) ?: null,
            $batch, $batch]);
        $ins->rowCount() === 1 ? $new++ : $old++;
    }
    crm_save_prefs(['batch' => $batch, 'next_page' => $page + 1]);
    return [$new, $old, $wrapped, $batch];
}

/** 5 أشخاص (صناع قرار) من الشركة. البحث ده مابيخصمش كريديت، وبيتخزن يوم */
function crm_company_people(array $co, string $titles = ''): array
{
    if ($co['people'] && $co['people_at'] && strtotime($co['people_at']) > time() - 86400) return json_decode($co['people'], true) ?: [];
    $body = ['organization_ids' => [$co['ext_id']], 'person_seniorities' => crm_seniorities(), 'page' => 1, 'per_page' => 5];
    $tt = array_values(array_filter(array_map('trim', explode(',', $titles))));
    if ($tt) $body['person_titles'] = $tt;
    $j = crm_api('/mixed_people/api_search', $body);
    $ppl = (array)($j['people'] ?? []);
    if (!$ppl && $tt) { unset($body['person_titles']); $ppl = (array)(crm_api('/mixed_people/api_search', $body)['people'] ?? []); }
    $out = [];
    foreach (array_slice($ppl, 0, 5) as $p) {
        if (empty($p['id'])) continue;
        $out[] = [
            'id' => (string)$p['id'],
            'name' => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? $p['last_name_obfuscated'] ?? '')),
            'title' => (string)($p['title'] ?? ''),
            'has_email' => !empty($p['has_email']),
            'has_phone' => !empty($p['has_direct_phone']) && $p['has_direct_phone'] !== 'No',
            'city' => (string)($p['city'] ?? ''),
        ];
    }
    db()->prepare('UPDATE crm_companies SET people = ?, people_at = NOW() WHERE id = ? AND workspace_id = ?')->execute([json_encode($out, JSON_UNESCAPED_UNICODE), (int)$co['id'], (int)ws('id')]);
    return $out;
}

function crm_reveals_today(int $uid): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM crm_reveals WHERE workspace_id = ? AND user_id = ? AND created_at >= ?');
    $st->execute([(int)ws('id'), $uid, date('Y-m-d 00:00:00')]);
    return (int)$st->fetchColumn();
}

function crm_hook_url(array $prefs): string
{
    return base_url() . '/index.php?' . http_build_query(['p' => 'crm_hook', 'w' => (int)ws('id'), 't' => $prefs['hook_token']]);
}

/** كشف شخص: إيميل الشغل على طول، والرقم بيوصل على الويبهوك. يضيفه للـ Leads */
function crm_reveal(array $co, string $pid, array $prefs): array
{
    $j = crm_api('/people/match', ['id' => $pid, 'reveal_personal_emails' => false, 'reveal_phone_number' => true, 'webhook_url' => crm_hook_url($prefs)]);
    $p = (array)($j['person'] ?? []);
    if (!$p) throw new CrmException(t('Apollo مالقاش بيانات للشخص ده.'));
    $uid = (int)(current_user()['id'] ?? 0);
    db()->prepare('INSERT INTO crm_reveals (workspace_id, user_id, person_ext_id) VALUES (?, ?, ?)')->execute([(int)ws('id'), $uid ?: null, mb_substr($pid, 0, 64)]);
    $phone = crm_pick_phone($p);
    $email = (string)($p['email'] ?? '');
    if (stripos($email, 'email_not_unlocked') !== false) $email = '';
    $lead = crm_lead_upsert([
        'source' => 'reveal', 'ext_id' => $pid, 'company_id' => (int)$co['id'], 'company' => $co['name'],
        'name' => trim((string)($p['name'] ?? (($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')))) ?: '—',
        'title' => (string)($p['title'] ?? ''), 'email' => $email, 'phone' => $phone,
        'phone_pending' => $phone === '' ? 1 : 0, 'linkedin' => (string)($p['linkedin_url'] ?? ''),
        'country' => (string)($p['country'] ?? $co['country'] ?? ''), 'owner_id' => $uid ?: null,
    ]);
    log_activity('crm.reveal', $pid . ' ' . $co['name']);
    return $lead;
}

function crm_pick_phone(array $p): string
{
    foreach ((array)($p['phone_numbers'] ?? []) as $n) {
        $v = (string)($n['sanitized_number'] ?? $n['raw_number'] ?? '');
        if ($v !== '') return mb_substr($v, 0, 40);
    }
    return '';
}

function crm_norm_phone(string $p): string
{
    $d = preg_replace('/[^0-9+]/', '', $p);
    return strlen(preg_replace('/\D/', '', $d)) >= 6 ? $d : '';
}

/** المسؤول التالي بالدور (لو التوزيع التلقائي شغال) */
function crm_next_owner(): ?int
{
    $prefs = crm_prefs();
    if (!(int)$prefs['auto_assign']) return null;
    $ids = array_map('intval', array_column(crm_members(), 'id'));
    if (!$ids) return null;
    $last = (int)$prefs['rr_last'];
    $next = $ids[0];
    foreach ($ids as $id) { if ($id > $last) { $next = $id; break; } }
    crm_save_prefs(['rr_last' => $next]);
    return $next;
}

/** أعضاء الشركة اللي يقدروا يشوفوا العملاء */
function crm_members(): array
{
    static $m = null;
    if ($m !== null) return $m;
    $st = db()->prepare("SELECT u.id, u.name, wu.role FROM workspace_users wu JOIN users u ON u.id = wu.user_id WHERE wu.workspace_id = ? AND wu.status = 'active' ORDER BY u.id");
    $st->execute([(int)ws('id')]);
    $m = [];
    foreach ($st->fetchAll() as $r) {
        if ($r['role'] === 'owner' || in_array('crm.view', role_permissions($r['role']), true)) $m[] = $r;
    }
    return $m;
}

/** إضافة Lead مع دمج المكرر (نفس الشخص أو الإيميل أو الرقم). يرجع الصف + merged */
function crm_lead_upsert(array $d): array
{
    $wid = (int)ws('id');
    $email = mb_strtolower(trim((string)($d['email'] ?? '')));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    $phone = crm_norm_phone((string)($d['phone'] ?? ''));
    $ext = (string)($d['ext_id'] ?? '');
    $conds = [];
    $args = [$wid];
    if ($ext !== '') { $conds[] = 'ext_id = ?'; $args[] = $ext; }
    if ($email !== '') { $conds[] = 'email = ?'; $args[] = $email; }
    if ($phone !== '') { $conds[] = 'phone = ?'; $args[] = $phone; }
    $found = null;
    if ($conds) {
        $st = db()->prepare('SELECT * FROM crm_leads WHERE workspace_id = ? AND (' . implode(' OR ', $conds) . ') ORDER BY id LIMIT 1');
        $st->execute($args);
        $found = $st->fetch() ?: null;
    }
    $f = [
        'company_id' => !empty($d['company_id']) ? (int)$d['company_id'] : null,
        'company' => mb_substr(trim((string)($d['company'] ?? '')), 0, 255) ?: null,
        'title' => mb_substr(trim((string)($d['title'] ?? '')), 0, 255) ?: null,
        'email' => $email ?: null, 'phone' => $phone ?: null,
        'linkedin' => mb_substr(trim((string)($d['linkedin'] ?? '')), 0, 500) ?: null,
        'country' => mb_substr(trim((string)($d['country'] ?? '')), 0, 120) ?: null,
    ];
    if ($found) {
        // الدمج: بنكمّل الخانات الفاضية بس، ومابنغيرش المرحلة ولا المسؤول
        $set = [];
        $vals = [];
        foreach ($f as $k => $v) { if ($v !== null && ($found[$k] === null || $found[$k] === '')) { $set[] = "$k = ?"; $vals[] = $v; } }
        if ($ext !== '' && !$found['ext_id']) { $set[] = 'ext_id = ?'; $vals[] = $ext; }
        if ($phone !== '') $set[] = 'phone_pending = 0';
        if ($set) { $vals[] = (int)$found['id']; db()->prepare('UPDATE crm_leads SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals); }
        return crm_lead((int)$found['id']) + ['merged' => true];
    }
    $src = isset(crm_sources()[$d['source'] ?? '']) ? $d['source'] : 'manual';
    $owner = array_key_exists('owner_id', $d) && $d['owner_id'] ? (int)$d['owner_id'] : crm_next_owner();
    $uid = (int)(current_user()['id'] ?? 0);
    db()->prepare('INSERT INTO crm_leads (workspace_id, source, ext_id, company_id, company, name, title, email, phone, phone_pending, linkedin, country, owner_id, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
        $wid, $src, $ext ?: null, $f['company_id'], $f['company'], mb_substr(trim((string)($d['name'] ?? '')), 0, 255) ?: '—',
        $f['title'], $f['email'], $f['phone'], (int)($d['phone_pending'] ?? 0), $f['linkedin'], $f['country'], $owner, $uid ?: null,
    ]);
    return crm_lead((int)db()->lastInsertId()) + ['merged' => false];
}

function crm_lead(int $id): array
{
    $st = db()->prepare('SELECT * FROM crm_leads WHERE id = ? AND workspace_id = ?');
    $st->execute([$id, (int)ws('id')]);
    return $st->fetch() ?: [];
}

/** يقدر يعدل الـ Lead ده؟ المدير الكل، والموظف بتوعه أو اللي من غير مسؤول */
function crm_can_edit(array $l): bool
{
    if (can('crm.manage')) return true;
    $uid = (int)(current_user()['id'] ?? 0);
    return can('crm.view') && ((int)$l['owner_id'] === $uid || !$l['owner_id']);
}

/** رقم واتساب نضيف (أرقام بس) */
function crm_wa_digits(?string $p): string
{
    return preg_replace('/\D/', '', (string)$p);
}

/* ---------- Leads الإعلانات ← الـ Leads الموحدة ---------- */

/** مرحلة lead_meta (صفحة الإعلانات) ⇄ مرحلة الـ CRM */
function crm_stage_from_ad(string $s): string
{
    return ['new' => 'new', 'contacted' => 'contacted', 'qualified' => 'qualified', 'converted' => 'won', 'lost' => 'lost'][$s] ?? 'new';
}

function crm_stage_to_ad(string $s): string
{
    return ['new' => 'new', 'contacted' => 'contacted', 'qualified' => 'qualified', 'proposal' => 'qualified', 'won' => 'converted', 'lost' => 'lost'][$s] ?? 'new';
}

/** خانات فورم الإعلان → بيانات Lead. أسماء الخانات بتختلف من فورم لفورم فبندور بالكلمات */
function crm_ad_lead_map(array $l): array
{
    $f = [];
    foreach ((array)($l['fields'] ?? []) as $k => $v) {
        if (is_array($v)) $v = implode(', ', array_filter(array_map('strval', $v)));
        $f[mb_strtolower(trim((string)$k))] = trim((string)$v);
    }
    $pick = function (array $keys) use ($f) {
        foreach ($keys as $k) if (($f[$k] ?? '') !== '') return $f[$k];
        foreach ($f as $k => $v) foreach ($keys as $want) if ($v !== '' && strpos($k, $want) !== false) return $v;
        return '';
    };
    $name = $pick(['full_name', 'name', 'الاسم']);
    if ($name === '') $name = trim($pick(['first_name']) . ' ' . $pick(['last_name']));
    $email = $pick(['email', 'work_email', 'البريد']);
    $phone = $pick(['phone_number', 'phone', 'mobile', 'whatsapp', 'رقم']);
    $known = ['full_name', 'name', 'first_name', 'last_name', 'email', 'work_email', 'phone_number', 'phone', 'mobile', 'company_name', 'company', 'job_title', 'country'];
    $extra = [];
    foreach ($f as $k => $v) if ($v !== '' && !in_array($k, $known, true) && $v !== $email && $v !== $phone && $v !== $name) $extra[] = $k . ': ' . $v;
    $form = (string)($l['formName'] ?? $l['formId'] ?? '');
    return [
        'source' => 'ads', 'ext_id' => mb_substr('ad:' . (string)($l['id'] ?? $l['leadId'] ?? ''), 0, 64),
        'name' => $name !== '' ? $name : ($email ?: $phone ?: '—'), 'email' => $email, 'phone' => $phone,
        'company' => $pick(['company_name', 'company', 'الشركة']), 'title' => $pick(['job_title', 'title']), 'country' => $pick(['country']),
        'note' => trim(($form !== '' ? t('من فورم: %s', $form) . "\n" : '') . implode("\n", $extra)),
        'created_at' => !empty($l['createdTime']) && strtotime((string)$l['createdTime']) ? date('Y-m-d H:i:s', strtotime((string)$l['createdTime'])) : null,
    ];
}

/** يضيف leads الإعلانات للـ Leads الموحدة. يرجع [جديد, اتدمج, موجود قبل كده] */
function crm_import_ad_leads(array $leads): array
{
    $wid = (int)ws('id');
    $ids = [];
    foreach ($leads as $l) { $id = (string)($l['id'] ?? $l['leadId'] ?? ''); if ($id !== '') $ids['ad:' . $id] = $l; }
    if (!$ids) return [0, 0, 0];
    $have = [];
    $st = db()->prepare('SELECT ext_id FROM crm_leads WHERE workspace_id = ? AND ext_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $st->execute(array_merge([$wid], array_keys($ids)));
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $e) $have[$e] = 1;
    // مراحل اتحطت قبل كده من صفحة الإعلانات
    $meta = [];
    try {
        $raw = array_map(function ($k) { return substr($k, 3); }, array_keys($ids));
        $st = db()->prepare('SELECT lead_id, stage, deal_value, note FROM lead_meta WHERE workspace_id = ? AND lead_id IN (' . implode(',', array_fill(0, count($raw), '?')) . ')');
        $st->execute(array_merge([$wid], $raw));
        foreach ($st->fetchAll() as $r) $meta['ad:' . $r['lead_id']] = $r;
    } catch (Throwable $t) { /* قبل التحديث */ }
    $new = $merged = $old = 0;
    foreach ($ids as $ext => $l) {
        if (isset($have[$ext])) { $old++; continue; }
        $d = crm_ad_lead_map($l);
        if ($d['email'] === '' && crm_norm_phone($d['phone']) === '') { $old++; continue; } // مفيش حاجة نتواصل بيها
        $r = crm_lead_upsert($d);
        if (!$r) continue;
        if (!empty($r['merged'])) { $merged++; continue; }
        $new++;
        $m = $meta[$ext] ?? null;
        db()->prepare('UPDATE crm_leads SET created_at = COALESCE(?, created_at), stage = ?, deal_value = COALESCE(?, deal_value), note = ? WHERE id = ?')->execute([
            $d['created_at'], $m ? crm_stage_from_ad((string)$m['stage']) : 'new', $m['deal_value'] ?? null,
            trim($d['note'] . (!empty($m['note']) ? "\n" . $m['note'] : '')) ?: null, (int)$r['id']]);
    }
    return [$new, $merged, $old];
}

/** سحب كل leads الإعلانات (قراءة بس). بيقف أول ما يلاقي صفحة كلها متسحبة قبل كده */
function crm_sync_ads(Zernio $z, int $maxPages = 5): array
{
    $tot = [0, 0, 0];
    $add = function (array $r) use (&$tot) { foreach ($r as $i => $n) $tot[$i] += $n; return $r; };
    $fxd = getenv('UI_FIXTURES');
    if ($fxd && is_file($fxd . '/leads.json')) {
        $add(crm_import_ad_leads((array)(json_decode((string)file_get_contents($fxd . '/leads.json'), true)['leads'] ?? [])));
    } else {
        foreach (ads_connections($z) as $c) {
            $pf = (string)($c['platform'] ?? '');
            if (!in_array($pf, ['facebook', 'metaads', 'linkedin', 'linkedinads'], true)) continue;
            $isLi = strpos($pf, 'linkedin') === 0;
            $accs = [null];
            if ($isLi) {
                try { $accs = array_map(function ($a) { return (string)$a['id']; }, array_values(array_filter(ads_ad_accounts($z, $c['_id']), 'ads_account_usable'))); } catch (ZernioException $ex) { $accs = []; }
            }
            foreach ($accs as $acc) {
                $cur = null;
                for ($i = 0; $i < $maxPages; $i++) {
                    $r = ads_leads($z, ['accountId' => $c['_id'], 'adAccountId' => $acc, 'platform' => $isLi ? 'linkedin' : 'meta', 'limit' => 100, 'cursor' => $cur]);
                    $page = (array)($r['leads'] ?? []);
                    [$n, $m, $o] = $add(crm_import_ad_leads($page));
                    $cur = ($r['pagination']['hasMore'] ?? false) ? ($r['pagination']['cursor'] ?? null) : null;
                    if (!$cur || ($page && $n === 0)) break;
                }
            }
        }
    }
    save_setting('crm_ads_sync_' . (int)ws('id'), date('Y-m-d H:i:s'));
    return $tot;
}

/** تغيير المرحلة في الـ CRM يتنقل لصفحة الإعلانات كمان */
function crm_push_ad_stage(array $l): void
{
    if (strpos((string)$l['ext_id'], 'ad:') !== 0) return;
    try {
        db()->prepare('INSERT INTO lead_meta (workspace_id, lead_id, stage, deal_value, updated_by) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE stage = VALUES(stage), deal_value = VALUES(deal_value), updated_by = VALUES(updated_by)')
            ->execute([(int)$l['workspace_id'], substr((string)$l['ext_id'], 3), crm_stage_to_ad((string)$l['stage']), $l['deal_value'], (int)(current_user()['id'] ?? 0) ?: null]);
    } catch (Throwable $t) { /* الجدول مش موجود */ }
}
