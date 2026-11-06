<?php
/**
 * إدارة العملاء: الشركات المستهدفة من Apollo.io + كشف بيانات الأشخاص + الـ Leads الموحدة.
 * Apollo: البحث عن الشركات بيخصم 1 كريديت لكل صفحة، البحث عن الأشخاص مجاني ومابيرجعش إيميل ولا رقم،
 * والكشف (people/match) بيخصم كريديت، والرقم بيوصل بعدين على ويبهوك.
 */

class CrmException extends Exception {}
/** العملية مش متاحة في خطة Apollo الحالية (المفتاح سليم) */
class CrmPlanException extends CrmException {}

function crm_stages(): array
{
    return ['new' => t('جديد'), 'contacted' => t('اتكلمنا معاه'), 'qualified' => t('مهتم'), 'proposal' => t('عرض سعر'), 'won' => t('بقى عميل'), 'lost' => t('مش مناسب')];
}

function crm_sources(): array
{
    return ['reveal' => t('كشف من الشركات'), 'ads' => t('إعلانات'), 'whatsapp' => t('واتساب'), 'manual' => t('يدوي'), 'import' => t('مستورد'), 'inbox' => t('رسائل السوشيال'), 'comments' => t('تعليقات المنشورات')];
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

function crm_api(string $path, array $body, ?string $key = null, string $method = 'POST'): array
{
    if ($fake = crm_fake()) return $fake($path, $body, $method);
    $key = $key ?? connector_secret('apollo', 'api_key');
    if ($key === '') throw new CrmException(t('اربط حساب Apollo.io من التكاملات الأول.'));
    $ch = curl_init('https://api.apollo.io/api/v1' . $path . ($method === 'GET' && $body ? '?' . http_build_query($body) : ''));
    $opt = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Cache-Control: no-cache', 'x-api-key: ' . $key],
    ];
    if ($method === 'POST') { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE); }
    curl_setopt_array($ch, $opt);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new CrmException(t('مش قادرين نوصل لـ Apollo: %s', $cerr));
    $j = json_decode((string)$raw, true);
    $msg = is_array($j) ? (string)($j['error'] ?? $j['message'] ?? '') : mb_substr((string)$raw, 0, 200);
    if ($code === 401) throw new CrmException(t('مفتاح Apollo غلط أو اتلغى.'));
    if ($code === 403 || ($code >= 400 && preg_match('/free plan|not accessible|API_INACCESSIBLE|upgrade your plan/i', $msg))) {
        // الخطة مش بتسمح (زي الخطة المجانية) — غير إن المفتاح غلط
        if (preg_match('/plan|upgrade|INACCESSIBLE|not accessible/i', $msg)) throw new CrmPlanException(t('العملية دي مش متاحة في خطة Apollo بتاعتك (%s).', $path));
        throw new CrmException(t('مفتاح Apollo مش مسموحله بالعملية دي. اعمل Master Key من إعدادات Apollo. (%s)', $msg));
    }
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
    $fetch = function (int $pg) use ($prefs) {
        $q = crm_icp_query($prefs['icp']);
        try {
            $j = crm_api('/mixed_companies/search', $q + ['page' => $pg, 'per_page' => 25]);
            save_setting('crm_plan_' . (int)ws('id'), 'full');
            return array_merge((array)($j['organizations'] ?? []), (array)($j['accounts'] ?? []));
        } catch (CrmPlanException $e) {
            // الخطة المجانية: البحث عن الشركات مقفول، فبندور على صناع قرار بنفس الفلاتر ونطلع شركاتهم
            save_setting('crm_plan_' . (int)ws('id'), 'free');
            try {
                $j = crm_api('/mixed_people/api_search', $q + ['person_seniorities' => crm_seniorities(), 'page' => $pg, 'per_page' => 100]);
            } catch (CrmPlanException $e2) {
                throw new CrmPlanException(t('خطة Apollo بتاعتك مش بتسمح بالبحث عن الشركات ولا الأشخاص من الـ API. ضيف الشركات بالدومين من الخانة اللي على الجنب، أو رقّي الخطة من apollo.io/pricing.'));
            }
            $orgs = [];
            foreach ((array)($j['people'] ?? []) as $pp) {
                $o = (array)($pp['organization'] ?? []);
                $oid = (string)($pp['organization_id'] ?? $o['id'] ?? '');
                if ($oid === '' || trim((string)($o['name'] ?? '')) === '') continue;
                $orgs[$oid] = ['id' => $oid] + $o;
            }
            return array_values($orgs);
        }
    };
    $orgs = $fetch($page);
    $wrapped = false;
    if (!$orgs && $page > 1) {
        // خلصنا النتايج: نرجع لأول صفحة
        $wrapped = true;
        $page = 1;
        $orgs = $fetch(1);
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
    $body = strpos((string)$co['ext_id'], 'dom:') === 0
        ? ['q_organization_domains_list' => [substr((string)$co['ext_id'], 4)], 'person_seniorities' => crm_seniorities(), 'page' => 1, 'per_page' => 5]
        : ['organization_ids' => [$co['ext_id']], 'person_seniorities' => crm_seniorities(), 'page' => 1, 'per_page' => 5];
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
    if (!empty($lead['id'])) db()->prepare('UPDATE crm_leads SET email_status = ?, enriched_at = NOW() WHERE id = ?')->execute([mb_substr((string)($p['email_status'] ?? ''), 0, 20) ?: null, (int)$lead['id']]);
    log_activity('crm.reveal', $pid . ' ' . $co['name']);
    return $lead;
}

/** بحث أشخاص مباشر (Prospecting) بفلاتر. مابيصرفش كريديت: الأسماء والبيانات مخفية لحد الكشف */
function crm_people_search(array $f, int $page = 1): array
{
    $list = function ($s) { return array_values(array_filter(array_map('trim', is_array($s) ? $s : explode(',', (string)$s)))); };
    $b = ['page' => max(1, min(100, $page)), 'per_page' => 25];
    if ($t = $list($f['titles'] ?? '')) { $b['person_titles'] = $t; $b['include_similar_titles'] = true; }
    if ($s = array_values(array_intersect($list($f['seniorities'] ?? []), crm_seniorities()))) $b['person_seniorities'] = $s;
    if ($l = $list($f['locations'] ?? '')) $b['person_locations'] = $l;
    if ($z = array_values(array_intersect($list($f['sizes'] ?? []), array_keys(crm_sizes())))) $b['organization_num_employees_ranges'] = $z;
    if (($k = trim((string)($f['keywords'] ?? ''))) !== '') $b['q_keywords'] = mb_substr($k, 0, 200);
    if ($d = $list($f['domains'] ?? '')) $b['q_organization_domains_list'] = array_slice($d, 0, 50);
    $rmin = (int)preg_replace('/\D/', '', (string)($f['rev_min'] ?? '')); $rmax = (int)preg_replace('/\D/', '', (string)($f['rev_max'] ?? ''));
    if ($rmin || $rmax) $b['revenue_range'] = array_filter(['min' => $rmin ?: null, 'max' => $rmax ?: null]);
    if (count($b) <= 2) throw new CrmException(t('اختار فلتر واحد على الأقل (مسمى وظيفي أو دولة أو مجال).'));
    $j = crm_api('/mixed_people/api_search', $b);
    $out = [];
    foreach ((array)($j['people'] ?? []) as $p) {
        if (empty($p['id'])) continue;
        $o = (array)($p['organization'] ?? []);
        $out[] = ['id' => (string)$p['id'], 'name' => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? $p['last_name_obfuscated'] ?? '')),
            'title' => (string)($p['title'] ?? ''), 'company' => (string)($o['name'] ?? ''), 'industry' => (string)($o['industry'] ?? ''),
            'size' => (int)($o['estimated_num_employees'] ?? 0), 'city' => trim(implode('، ', array_filter([(string)($p['city'] ?? $o['city'] ?? ''), (string)($p['country'] ?? $o['country'] ?? '')]))),
            'country' => (string)($p['country'] ?? $o['country'] ?? ''),
            'has_email' => !empty($p['has_email']), 'has_phone' => !empty($p['has_direct_phone']) && $p['has_direct_phone'] !== 'No'];
    }
    $total = (int)($j['total_entries'] ?? $j['pagination']['total_entries'] ?? 0);
    return ['people' => $out, 'total' => $total, 'page' => $b['page']];
}

/** كمّل بيانات Lead موجود من Apollo (بالإيميل أو الاسم + الشركة). بيملا الفاضي بس */
function crm_enrich_lead(array $l, array $prefs): array
{
    $parts = preg_split('/\s+/u', trim((string)$l['name']), 2);
    $b = ['reveal_personal_emails' => false];
    if (!empty($l['email'])) $b['email'] = $l['email'];
    if (!empty($l['ext_id']) && !preg_match('/^(ib|cm|ad|dom):/', (string)$l['ext_id'])) $b['id'] = $l['ext_id'];
    if (empty($b['email']) && empty($b['id'])) {
        if (empty($l['company'])) throw new CrmException(t('محتاجين إيميل العميل أو اسم شركته عشان Apollo يلاقيه.'));
        $b['first_name'] = $parts[0] ?? ''; $b['last_name'] = $parts[1] ?? ''; $b['organization_name'] = $l['company'];
    }
    if (empty($l['phone'])) { $b['reveal_phone_number'] = true; $b['webhook_url'] = crm_hook_url($prefs); }
    $p = (array)(crm_api('/people/match', $b)['person'] ?? []);
    if (!$p) throw new CrmException(t('Apollo مالقاش بيانات للشخص ده.'));
    $email = (string)($p['email'] ?? ''); if (stripos($email, 'not_unlocked') !== false) $email = '';
    $fill = ['title' => (string)($p['title'] ?? ''), 'linkedin' => (string)($p['linkedin_url'] ?? ''), 'company' => (string)($p['organization']['name'] ?? ''),
        'email' => mb_strtolower($email), 'country' => (string)($p['country'] ?? '')];
    $phone = crm_pick_phone($p);
    if ($phone !== '') $fill['phone'] = crm_norm_phone($phone, $fill['country'] ?: (string)$l['country']);
    $set = []; $vals = []; $got = [];
    foreach ($fill as $k => $v) if ($v !== '' && (string)($l[$k] ?? '') === '') { $set[] = "$k = ?"; $vals[] = mb_substr($v, 0, 255); $got[] = $k; }
    if (empty($l['ext_id']) && !empty($p['id'])) { $set[] = 'ext_id = ?'; $vals[] = mb_substr((string)$p['id'], 0, 64); }
    $pending = empty($l['phone']) && $phone === '' && !empty($b['reveal_phone_number']);
    if ($pending) $set[] = 'phone_pending = 1';
    $set[] = 'enriched_at = NOW()';
    if (!empty($p['email_status'])) { $set[] = 'email_status = ?'; $vals[] = mb_substr((string)$p['email_status'], 0, 20); }
    $vals[] = (int)$l['id']; db()->prepare('UPDATE crm_leads SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
    $uid = (int)(current_user()['id'] ?? 0);
    db()->prepare('INSERT INTO crm_reveals (workspace_id, user_id, person_ext_id) VALUES (?, ?, ?)')->execute([(int)ws('id'), $uid ?: null, mb_substr((string)($p['id'] ?? 'enrich'), 0, 64)]);
    log_activity('crm.enrich', (string)$l['id']);
    return ['got' => $got, 'pending' => $pending];
}

/** Sequences بتاعة Apollo (رسايل إيميل متتابعة). محتاجة Master Key */
function crm_sequences(): array
{
    $j = crm_api('/emailer_campaigns/search', ['per_page' => 50]);
    $out = [];
    foreach ((array)($j['emailer_campaigns'] ?? []) as $c) if (!empty($c['id'])) $out[] = ['id' => (string)$c['id'], 'name' => (string)($c['name'] ?? $c['id']), 'active' => !empty($c['active'])];
    $acc = [];
    foreach ((array)(crm_api('/email_accounts', [], null, 'GET')['email_accounts'] ?? []) as $a) if (!empty($a['id'])) $acc[] = ['id' => (string)$a['id'], 'email' => (string)($a['email'] ?? '')];
    return ['seqs' => $out, 'accounts' => $acc];
}
function crm_add_to_sequence(array $l, string $seq, string $acc, string $seqName = ''): void
{
    if (empty($l['email'])) throw new CrmException(t('العميل ده مالوش إيميل، والـ Sequence بيبعت إيميلات.'));
    $parts = preg_split('/\s+/u', trim((string)$l['name']), 2);
    $c = (array)(crm_api('/contacts', ['first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '', 'email' => $l['email'], 'organization_name' => (string)$l['company'],
        'title' => (string)$l['title'], 'run_dedupe' => true])['contact'] ?? []);
    if (empty($c['id'])) throw new CrmException(t('Apollo ماقبلش يضيف العميل كجهة اتصال.'));
    crm_api('/emailer_campaigns/' . rawurlencode($seq) . '/add_contact_ids', ['emailer_campaign_id' => $seq, 'contact_ids' => [(string)$c['id']], 'send_email_from_email_account_id' => $acc]);
    crm_act_add((int)$l['id'], 'email', t('اتضاف لـ Sequence: %s', $seqName ?: $seq));
    if ($l['stage'] === 'new') db()->prepare("UPDATE crm_leads SET stage = 'contacted' WHERE id = ?")->execute([(int)$l['id']]);
    log_activity('crm.sequence', $l['id'] . ' ' . $seq);
}

/** القوايم = تاج على الـ Lead (بيتضاف من غير ما يمسح التاجات القديمة) */
function crm_add_tag(int $leadId, string $tag): void
{
    $tag = mb_substr(trim(str_replace(',', ' ', $tag)), 0, 30); if ($tag === '') return;
    $st = db()->prepare('SELECT tags FROM crm_leads WHERE id = ? AND workspace_id = ?'); $st->execute([$leadId, (int)ws('id')]);
    $cur = array_filter(array_map('trim', explode(',', (string)$st->fetchColumn())));
    if (in_array($tag, $cur, true)) return;
    $cur[] = $tag;
    db()->prepare('UPDATE crm_leads SET tags = ? WHERE id = ?')->execute([implode(', ', array_slice($cur, 0, 8)), $leadId]);
}
/** أسماء القوايم (التاجات) الموجودة */
function crm_tag_names(): array
{
    $st = db()->prepare("SELECT tags FROM crm_leads WHERE workspace_id = ? AND tags IS NOT NULL AND tags <> '' LIMIT 3000"); $st->execute([(int)ws('id')]);
    $all = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $t) foreach (explode(',', $t) as $x) { $x = trim($x); if ($x !== '') $all[$x] = ($all[$x] ?? 0) + 1; }
    arsort($all);
    return array_slice(array_keys($all), 0, 50);
}
/** حالة الإيميل من Apollo بكلام مفهوم */
function crm_email_statuses(): array
{
    return ['verified' => [t('إيميل متأكد منه'), 'bg-lime-100 text-lime-800'], 'likely to engage' => [t('إيميل متأكد منه'), 'bg-lime-100 text-lime-800'],
        'guessed' => [t('إيميل متخمّن'), 'bg-amber-100 text-amber-800'], 'extrapolated' => [t('إيميل متخمّن'), 'bg-amber-100 text-amber-800'],
        'unavailable' => [t('الإيميل مش متاح'), 'bg-red-100 text-red-700'], 'bounced' => [t('الإيميل بيرجع'), 'bg-red-100 text-red-700'], 'invalid' => [t('الإيميل غلط'), 'bg-red-100 text-red-700']];
}

/** البحث المحفوظ: بيتعاد ويطلّع الأشخاص الجداد اللي ماشفناهمش قبل كده (مابيصرفش كريديت) */
function crm_searches(): array
{
    $st = db()->prepare('SELECT id, name, filters, new_count, total, run_at FROM crm_searches WHERE workspace_id = ? ORDER BY id DESC LIMIT 30');
    $st->execute([(int)ws('id')]);
    return array_map(function ($r) { $r['filters'] = json_decode((string)$r['filters'], true) ?: []; $r['id'] = (int)$r['id']; $r['new_count'] = (int)$r['new_count']; return $r; }, $st->fetchAll());
}
function crm_search_run(int $id, bool $firstRun = false): array
{
    $st = db()->prepare('SELECT * FROM crm_searches WHERE id = ? AND workspace_id = ?'); $st->execute([$id, (int)ws('id')]);
    $s = $st->fetch(); if (!$s) throw new CrmException(t('البحث ده مش موجود.'));
    $seen = array_flip((array)json_decode((string)$s['seen'], true));
    $ids = []; $total = 0;
    for ($pg = 1; $pg <= 2; $pg++) {
        $r = crm_people_search(json_decode((string)$s['filters'], true) ?: [], $pg);
        $total = $r['total'] ?: $total;
        foreach ($r['people'] as $p) $ids[] = $p['id'];
        if (count($r['people']) < 25) break;
    }
    $new = $firstRun ? [] : array_values(array_filter($ids, function ($x) use ($seen) { return !isset($seen[$x]); }));
    $allSeen = array_slice(array_values(array_unique(array_merge(array_keys($seen), $ids))), -3000);
    $keepNew = array_slice(array_values(array_unique(array_merge($new, (array)json_decode((string)$s['new_ids'], true)))), 0, 200);
    db()->prepare('UPDATE crm_searches SET seen = ?, new_ids = ?, new_count = ?, total = ?, run_at = NOW() WHERE id = ?')
        ->execute([json_encode($allSeen), json_encode($keepNew), count($keepNew), $total, $id]);
    return ['new' => count($new), 'total' => $total];
}
/** للـ cron: كل البحوث المحفوظة اللي عدّى عليها يوم */
function crm_searches_daily(): int
{
    if (!crm_has_key()) return 0;
    $st = db()->prepare('SELECT id FROM crm_searches WHERE workspace_id = ? AND (run_at IS NULL OR run_at < ?) ORDER BY run_at LIMIT 10');
    $st->execute([(int)ws('id'), date('Y-m-d H:i:s', time() - 20 * 3600)]);
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) { try { $n += crm_search_run((int)$id)['new']; } catch (CrmPlanException $e) { break; } catch (Throwable $e) {} }
    return $n;
}

function crm_pick_phone(array $p): string
{
    foreach ((array)($p['phone_numbers'] ?? []) as $n) {
        $v = (string)($n['sanitized_number'] ?? $n['raw_number'] ?? '');
        if ($v !== '') return mb_substr($v, 0, 40);
    }
    return '';
}

/** الرقم بالصيغة الدولية. الدولة: اللي جاية مع العميل، وإلا دولة الشركة من الإعدادات */
function crm_norm_phone(string $p, ?string $country = null): string
{
    return crm_intl_phone($p, crm_country_code($country) ?: ws_country());
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
    $phone = crm_norm_phone((string)($d['phone'] ?? ''), (string)($d['country'] ?? ''));
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

/** يطلّع أرقام وإيميلات من نص (رسالة أو تعليق)، والأرقام العربي بتتحول */
function crm_extract_contacts(string $txt): array
{
    $txt = strtr($txt, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
    $email = preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $txt, $m) ? mb_strtolower($m[0]) : '';
    $phone = '';
    if (preg_match_all('/(?<![\w\/])\+?\d[\d\s\-().]{6,18}\d(?!\w)/', $txt, $mm)) {
        foreach ($mm[0] as $c) { $d = preg_replace('/\D/', '', $c); if (strlen($d) >= 8 && strlen($d) <= 15) { $phone = (strpos(trim($c), '+') === 0 ? '+' : '') . $d; break; } }
    }
    return ['phone' => $phone, 'email' => $email];
}
/** عميل من رسالة أو تعليق: بيتعرف بنفس الشخص على نفس المنصة، فمش بيتكرر */
function crm_from_social(array $d): array
{
    $src = ($d['source'] ?? '') === 'comments' ? 'comments' : 'inbox';
    $ext = mb_substr((string)($d['ext'] ?? ''), 0, 190);
    $found = crm_contacts_merge(crm_extract_contacts((string)($d['text'] ?? '')), $d);
    $l = crm_lead_upsert(['source' => $src, 'ext_id' => $ext, 'name' => $d['name'] ?? '', 'phone' => $found['phone'], 'email' => $found['email'],
        'owner_id' => $d['owner_id'] ?? null]);
    if (!$l) return [];
    $url = mb_substr(trim((string)($d['url'] ?? '')), 0, 500);
    if ($url !== '' && empty($l['src_url'])) db()->prepare('UPDATE crm_leads SET src_url = ? WHERE id = ?')->execute([$url, (int)$l['id']]);
    $txt = trim((string)($d['text'] ?? ''));
    if ($txt !== '') crm_act_add((int)$l['id'], 'note', '“' . mb_substr($txt, 0, 600) . '”' . (!empty($d['where']) ? ' — ' . $d['where'] : ''));
    return crm_lead((int)$l['id']) + ['merged' => !empty($l['merged'])];
}
function crm_contacts_merge(array $auto, array $d): array
{
    $p = trim((string)($d['phone'] ?? '')); $e = trim((string)($d['email'] ?? ''));
    return ['phone' => $p !== '' ? $p : $auto['phone'], 'email' => $e !== '' ? $e : $auto['email']];
}
/** الـ Lead المربوط بمحادثة/شخص (لو موجود) */
function crm_lead_by_ext(string $ext): ?array
{
    if ($ext === '' || !module_enabled('crm')) return null;
    try { $st = db()->prepare('SELECT id, name, stage FROM crm_leads WHERE workspace_id = ? AND ext_id = ? LIMIT 1'); $st->execute([(int)ws('id'), $ext]); return $st->fetch() ?: null; } catch (Throwable $e) { return null; }
}

/** كلمات بتدل على اهتمام بالشراء (من غير ذكاء اصطناعي) */
function crm_buy_words(string $t): bool
{
    return (bool)preg_match('/(بكام|بكم|السعر|سعر|الاسعار|الأسعار|اسعار|أسعار|تكلفة|عرض سعر|عاوز اشتري|عايز اشتري|اطلب|أطلب|حجز|احجز|متاح|التوصيل|price|cost|how much|order|buy|book)/iu', $t);
}
/**
 * عملاء مقترحين من المحادثات والتعليقات: نية شراء من الذكاء الاصطناعي، أو كتب رقمه/إيميله، أو سأل عن السعر.
 * اللي موجودين في الـ Leads بيتشالوا. GETs بس.
 */
function crm_suggestions(Zernio $z, int $maxPosts = 6): array
{
    $wid = (int)ws('id');
    $labels = function_exists('ai_labels') ? ai_labels($wid) : [];
    $st = db()->prepare("SELECT ext_id FROM crm_leads WHERE workspace_id = ? AND ext_id IS NOT NULL AND (ext_id LIKE 'ib:%' OR ext_id LIKE 'cm:%')");
    $st->execute([$wid]);
    $have = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    $out = [];
    $score = function (?array $lb, string $txt, array $fc): array {
        $why = [];
        if ($lb && (int)$lb['intent'] === 1) $why[] = 'ai';
        if ($fc['phone'] !== '' || $fc['email'] !== '') $why[] = 'contact';
        if (crm_buy_words($txt)) $why[] = 'price';
        if ($lb && in_array($lb['category'], ['spam', 'complaint'], true)) $why = [];
        return $why;
    };
    try {
        $conv = $z->conversations(['limit' => 100, 'profileId' => zernio_profile()])['data'] ?? [];
    } catch (Throwable $e) { $conv = []; }
    foreach ($conv as $c) {
        $id = (string)($c['id'] ?? ''); if ($id === '') continue;
        $pf = (string)($c['platform'] ?? '');
        $ext = 'ib:' . $pf . ':' . (string)($c['participantId'] ?? $id);
        if (isset($have[$ext])) continue;
        $m = $c['lastMessage'] ?? ''; $m = trim(is_array($m) ? (string)($m['text'] ?? $m['message'] ?? '') : (string)$m);
        $lb = $labels[$id] ?? null; $fc = crm_extract_contacts($m);
        $why = $score($lb, $m, $fc); if (!$why) continue;
        $out[] = ['source' => 'inbox', 'ext' => $ext, 'name' => (string)($c['participantName'] ?? $c['participantUsername'] ?? ''), 'text' => mb_substr($m, 0, 300), 'pf' => $pf,
            'where' => platform_label($pf), 'url' => url('inbox', ['c' => $id, 'a' => (string)($c['accountId'] ?? '')]), 'phone' => $fc['phone'], 'email' => $fc['email'], 'why' => $why,
            'summary' => (string)($lb['summary'] ?? ''), 'at' => (string)($c['updatedTime'] ?? $c['lastMessageAt'] ?? '')];
    }
    try {
        $posts = array_slice($z->commentedPosts(['limit' => 20, 'profileId' => zernio_profile()])['data'] ?? [], 0, $maxPosts);
    } catch (Throwable $e) { $posts = []; }
    $seen = [];
    foreach ($posts as $p) {
        try { $cs = $z->comments((string)$p['id'], (string)($p['accountId'] ?? ''))['comments'] ?? []; } catch (Throwable $e) { continue; }
        $flat = []; $walk = function ($l) use (&$walk, &$flat) { foreach ($l as $c) { $flat[] = $c; $walk($c['replies'] ?? []); } }; $walk($cs);
        foreach ($flat as $c) {
            $from = $c['from'] ?? []; if (!empty($from['isOwner'])) continue;
            $pf = (string)($c['platform'] ?? $p['platform'] ?? '');
            $nm = (string)($from['name'] ?? $from['username'] ?? '');
            $ext = 'cm:' . $pf . ':' . (string)($from['id'] ?? $from['username'] ?? $nm);
            if (isset($have[$ext]) || isset($seen[$ext])) continue;
            $txt = trim((string)($c['message'] ?? '')); $lb = $labels['c:' . ($c['id'] ?? '')] ?? null; $fc = crm_extract_contacts($txt);
            $why = $score($lb, $txt, $fc); if (!$why) continue;
            $seen[$ext] = 1;
            $out[] = ['source' => 'comments', 'ext' => $ext, 'name' => $nm, 'text' => mb_substr($txt, 0, 300), 'pf' => $pf, 'where' => t('تعليق'),
                'url' => url('comments', ['post' => (string)$p['id'], 'a' => (string)($p['accountId'] ?? '')]), 'phone' => $fc['phone'], 'email' => $fc['email'], 'why' => $why,
                'summary' => (string)($lb['summary'] ?? ''), 'at' => (string)($c['createdTime'] ?? '')];
        }
    }
    // الأقوى الأول: نية شراء + بيانات تواصل
    usort($out, function ($a, $b) { $w = function ($x) { return (in_array('ai', $x['why'], true) ? 4 : 0) + (in_array('contact', $x['why'], true) ? 2 : 0) + (in_array('price', $x['why'], true) ? 1 : 0); }; return $w($b) <=> $w($a) ?: strcmp($b['at'], $a['at']); });
    return array_slice($out, 0, 80);
}

/**
 * الالتقاط التلقائي (من الـ cron أو يدوي): المقترحين اللي عندهم إشارة قوية بس (نية شراء أو كتبوا رقم/إيميل)
 * بيتضافوا للـ Leads، بيتوزعوا على الفريق، وبتتحطلهم متابعة النهارده. سؤال السعر لوحده مش كفاية.
 */
function crm_auto_capture_on(int $wid): bool { return setting('crm_auto_capture_' . $wid, '0') === '1'; }
function crm_auto_capture(Zernio $z): int
{
    $n = 0;
    foreach (crm_suggestions($z) as $it) {
        if (!array_intersect($it['why'], ['ai', 'contact'])) continue;
        $l = crm_from_social($it + ['owner_id' => null]);
        if (!$l || $l['merged']) continue;
        db()->prepare('UPDATE crm_leads SET next_at = NOW(), next_note = ? WHERE id = ? AND next_at IS NULL')->execute([mb_substr(t('اتضاف أوتوماتيك من %s، كلّمه', $it['where']), 0, 255), (int)$l['id']]);
        $n++;
        if ($n >= 30) break;
    }
    if ($n) log_activity('crm.auto_capture', $n . ' new');
    save_setting('crm_auto_last_' . (int)ws('id'), date('Y-m-d H:i:s') . '|' . $n);
    return $n;
}
/** كام Lead جه من كل منشور (من التعليقات) */
function crm_leads_per_post(): array
{
    $st = db()->prepare("SELECT src_url FROM crm_leads WHERE workspace_id = ? AND source = 'comments' AND src_url IS NOT NULL");
    $st->execute([(int)ws('id')]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $u) { parse_str((string)parse_url($u, PHP_URL_QUERY), $q); if (!empty($q['post'])) $out[(string)$q['post']] = ($out[(string)$q['post']] ?? 0) + 1; }
    return $out;
}

/** سكربت زرار "ضيفه Lead" المشترك بين الرسائل والتعليقات */
function to_lead_script(): void
{
    echo '<script>window.TOLEAD = ' . json_encode(['url' => url('crm_leads'), 'csrf' => csrf_token(), 'added' => t('اتضاف للـ Leads ✓'), 'merged' => t('كان موجود، ربطناه بنفس العميل'), 'open' => t('افتح'), 'err' => t('حصل خطأ، جرب تاني')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>'
        . '<script src="assets/to-lead.js?v=' . e(APP_VERSION) . '"></script>';
}

/** أنواع التواصل في سجل العميل */
function crm_act_kinds(): array
{
    return ['call' => ['📞', t('مكالمة')], 'wa' => ['💬', t('واتساب')], 'email' => ['✉️', t('إيميل')], 'meet' => ['🤝', t('اجتماع')], 'note' => ['📝', t('ملاحظة')], 'stage' => ['➜', t('تغيير مرحلة')]];
}
function crm_act_add(int $leadId, string $kind, string $body = ''): void
{
    if (!isset(crm_act_kinds()[$kind])) return;
    db()->prepare('INSERT INTO crm_acts (workspace_id, lead_id, user_id, kind, body) VALUES (?, ?, ?, ?, ?)')
        ->execute([(int)ws('id'), $leadId, (int)(current_user()['id'] ?? 0) ?: null, $kind, mb_substr(trim($body), 0, 2000) ?: null]);
}
function crm_acts(int $leadId): array
{
    $st = db()->prepare('SELECT a.kind, a.body, a.created_at, u.name AS who FROM crm_acts a LEFT JOIN users u ON u.id = a.user_id WHERE a.workspace_id = ? AND a.lead_id = ? ORDER BY a.id DESC LIMIT 100');
    $st->execute([(int)ws('id'), $leadId]);
    return $st->fetchAll();
}
/** قوالب رسائل التواصل؛ {name} {company} {me} بتتبدل */
function crm_templates(): array
{
    $t = json_decode((string)setting('crm_tpl_' . (int)ws('id')), true);
    if (is_array($t) && $t) return $t;
    return [
        ['n' => t('أول تواصل'), 'b' => t('أهلاً {name}، معاك {me}. شفت شغل {company} وحابب أعرض عليك حاجة ممكن تفيدكم. ينفع نتكلم دقيقتين؟')],
        ['n' => t('متابعة'), 'b' => t('أهلاً {name}، بتابع مع حضرتك بخصوص كلامنا اللي فات. لسه الموضوع في بالك؟')],
        ['n' => t('عرض سعر'), 'b' => t('أهلاً {name}، بعتلك عرض السعر. لو عندك أي سؤال أنا موجود.')],
    ];
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
        if ($d['email'] === '' && crm_norm_phone($d['phone'], $d['country']) === '') { $old++; continue; } // مفيش حاجة نتواصل بيها
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

/* ---------- كود الدولة للأرقام ---------- */

/** اسم الدولة (عربي/إنجليزي/كود) → كود ISO. فاضي لو مش معروف */
function crm_country_code(?string $name): string
{
    static $map = null;
    $n = mb_strtolower(trim((string)$name));
    if ($n === '') return '';
    if ($map === null) {
        $map = [];
        foreach (countries() as $code => $c) {
            $map[mb_strtolower($code)] = $code;
            foreach (['ar', 'en'] as $k) if (!empty($c[$k])) $map[mb_strtolower($c[$k])] = $code;
        }
        $map += ['ksa' => 'SA', 'saudi' => 'SA', 'kingdom of saudi arabia' => 'SA', 'السعوديه' => 'SA', 'المملكة العربية السعودية' => 'SA',
            'united arab emirates' => 'AE', 'emirates' => 'AE', 'الامارات' => 'AE', 'uk' => 'GB', 'united kingdom' => 'GB', 'usa' => 'US', 'united states' => 'US',
            'جمهورية مصر العربية' => 'EG', 'مصر العربية' => 'EG'];
    }
    return $map[$n] ?? '';
}

/** يحوّل الرقم للصيغة الدولية (+كود). $iso = الدولة لو الرقم محلي */
function crm_intl_phone(string $raw, string $iso): string
{
    $raw = strtr(trim($raw), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
    $plus = strpos(ltrim($raw), '+') === 0;
    $d = preg_replace('/\D/', '', $raw);
    if (strlen($d) < 6) return '';
    if ($plus) return '+' . $d;
    if (strpos($d, '00') === 0) return '+' . substr($d, 2);
    if ($d[0] === '0') $iso = crm_guess_country($d, $iso);
    $dial = ltrim((string)(countries()[$iso]['dial'] ?? ''), '+');
    if ($dial === '') return $d;
    // مكتوب بالكود من غير + (مثلاً 966501234567)
    if ($d[0] !== '0' && strpos($d, $dial) === 0 && strlen($d) - strlen($dial) >= 8) return '+' . $d;
    // رقم دولة تانية بالكود من غير + (11 رقم أو أكتر ومش بادئ بصفر). الصين أرقامها المحلية 11 رقم فمستثناة
    if ($d[0] !== '0' && strlen($d) >= 11 && $iso !== 'CN') {
        static $codes = null;
        if ($codes === null) { $codes = array_flip(array_map(function ($c) { return ltrim((string)$c['dial'], '+'); }, countries())); }
        for ($k = 3; $k >= 1; $k--) if (isset($codes[substr($d, 0, $k)]) && strlen($d) - $k >= 8) return '+' . $d;
    }
    // الصفر اللي في أول الرقم المحلي بيتشال (ماعدا إيطاليا وسان مارينو والفاتيكان)
    if ($d[0] === '0' && !in_array($iso, ['IT', 'SM', 'VA'], true)) $d = substr($d, 1);
    $full = $dial . $d;
    return strlen($full) >= 8 && strlen($full) <= 15 ? '+' . $full : $d;
}

/** يصلّح الأرقام المحلية المتخزنة قبل كده (مرة واحدة لكل رقم) */
function crm_fix_stored_phones(): int
{
    $wid = (int)ws('id');
    $st = db()->prepare("SELECT id, phone, country FROM crm_leads WHERE workspace_id = ? AND phone IS NOT NULL AND phone NOT LIKE '+%' LIMIT 500");
    $st->execute([$wid]);
    $n = 0;
    $up = db()->prepare('UPDATE crm_leads SET phone = ? WHERE id = ?');
    foreach ($st->fetchAll() as $r) {
        $p = crm_intl_phone((string)$r['phone'], crm_country_code($r['country']) ?: ws_country());
        if ($p !== '' && $p !== $r['phone'] && $p[0] === '+') { $up->execute([$p, (int)$r['id']]); $n++; }
    }
    return $n;
}

/** رقم محلي (بادئ بصفر) شكله مش شكل أرقام الدولة الافتراضية؟ نخمّن من شكل الموبايل.
    السعودية والإمارات نفس الشكل (05 و10 أرقام)، فلو مش الإمارات بنعتبره سعودي. */
function crm_guess_country(string $d, string $iso): string
{
    $mob = ['EG' => '/^01[0125]\d{8}$/', 'SA' => '/^05\d{8}$/', 'AE' => '/^05[024568]\d{7}$/', 'JO' => '/^07[789]\d{7}$/', 'MA' => '/^0[67]\d{8}$/'];
    if (!isset($mob[$iso]) || preg_match($mob[$iso], $d)) return $iso;
    foreach (['EG', 'SA', 'JO', 'MA'] as $c) if ($c !== $iso && preg_match($mob[$c], $d)) return $iso === 'AE' && $c === 'SA' ? 'AE' : $c;
    return $iso;
}


/** إضافة شركات بالدومين (بتشتغل مع أي خطة): بنحاول نكمّل بياناتها من Apollo، ولو مش متاح بنحفظها بالدومين بس */
function crm_add_domains(array $domains): array
{
    $wid = (int)ws('id');
    $prefs = crm_prefs();
    $batch = (int)$prefs['batch'] + 1;
    $added = 0; $dup = 0; $bad = [];
    foreach (array_slice($domains, 0, 25) as $d) {
        $d = strtolower(trim((string)$d));
        $d = preg_replace('#^(https?://)?(www\.)?#', '', $d);
        $d = preg_replace('#[/?\#].*$#', '', $d);
        if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $d)) { if ($d !== '') $bad[] = $d; continue; }
        $o = [];
        try { $o = (array)(crm_api('/organizations/enrich', ['domain' => $d], null, 'GET')['organization'] ?? []); } catch (CrmException $e) { $o = []; }
        $ext = !empty($o['id']) ? (string)$o['id'] : 'dom:' . $d;
        $st = db()->prepare('SELECT id FROM crm_companies WHERE workspace_id = ? AND (ext_id = ? OR ext_id = ? OR domain = ?) LIMIT 1');
        $st->execute([$wid, $ext, 'dom:' . $d, $d]);
        if ($st->fetchColumn()) { $dup++; continue; }
        db()->prepare('INSERT INTO crm_companies (workspace_id, ext_id, name, domain, industry, employees, city, country, logo, linkedin, first_batch, last_batch) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$wid, mb_substr($ext, 0, 64), mb_substr((string)($o['name'] ?? $d), 0, 255), $d, ($o['industry'] ?? '') ?: null,
                isset($o['estimated_num_employees']) ? (int)$o['estimated_num_employees'] : null, ($o['city'] ?? '') ?: null, ($o['country'] ?? '') ?: null,
                ($o['logo_url'] ?? '') ?: null, ($o['linkedin_url'] ?? '') ?: null, $batch, $batch]);
        $added++;
    }
    if ($added) crm_save_prefs(['batch' => $batch]);
    return [$added, $dup, $bad];
}
