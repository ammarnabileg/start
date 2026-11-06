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
    return ['reveal' => t('كشف من الشركات'), 'ads' => t('إعلانات'), 'whatsapp' => t('واتساب'), 'manual' => t('يدوي'), 'import' => t('مستورد'), 'inbox' => t('رسائل السوشيال'), 'comments' => t('تعليقات المنشورات'), 'form' => t('فورم الموقع')];
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
/* ---------- خطط المتابعة: خطوات (إيميل أوتوماتيك، واتساب/مكالمة/مهمة كمهام للمسؤول) ---------- */
function crm_plan_channels(): array
{
    return ['email' => ['✉️', t('إيميل (بيتبعت لوحده)')], 'wa' => ['💬', t('واتساب')], 'call' => ['📞', t('مكالمة')], 'task' => ['✅', t('مهمة')]];
}
function crm_plans(): array
{
    $st = db()->prepare('SELECT id, name, steps FROM crm_plans WHERE workspace_id = ? ORDER BY id');
    $st->execute([(int)ws('id')]);
    return array_map(function ($r) { return ['id' => (int)$r['id'], 'name' => $r['name'], 'steps' => json_decode((string)$r['steps'], true) ?: []]; }, $st->fetchAll());
}
function crm_plan(int $id): ?array { foreach (crm_plans() as $p) if ($p['id'] === $id) return $p; return null; }
function crm_plan_text(string $b, array $l): string
{
    $first = preg_split('/\s+/u', trim((string)$l['name']))[0] ?? '';
    $me = '';
    if (!empty($l['owner_id'])) { $st = db()->prepare('SELECT name FROM users WHERE id = ?'); $st->execute([(int)$l['owner_id']]); $me = (string)$st->fetchColumn(); }
    $site = (string)setting('crm_site_url_' . (int)ws('id'), '');
    return strtr($b, ['{name}' => $first, '{company}' => (string)$l['company'], '{me}' => $me ?: (string)(current_user()['name'] ?? ''), '{book}' => strpos($b, '{book}') !== false ? crm_book_url() : '',
        '{site}' => strpos($b, '{site}') !== false && $site !== '' && !empty($l['id']) ? crm_track_link($l, $site) : $site]);
}
/** الخطة الشغالة على عميل (لو فيه) */
function crm_lead_run(int $leadId): ?array
{
    $st = db()->prepare("SELECT * FROM crm_plan_runs WHERE workspace_id = ? AND lead_id = ? AND state IN ('wait','due') ORDER BY id DESC LIMIT 1");
    $st->execute([(int)ws('id'), $leadId]);
    $r = $st->fetch(); if (!$r) return null;
    $p = crm_plan((int)$r['plan_id']); if (!$p) return null;
    $s = $p['steps'][(int)$r['step']] ?? null;
    return ['id' => (int)$r['id'], 'plan' => $p['name'], 'step' => (int)$r['step'], 'n' => count($p['steps']), 'state' => $r['state'], 'due' => $r['due_at'], 'err' => (string)$r['last_error'],
        'ch' => $s['ch'] ?? '', 'text' => $s ? crm_plan_text((string)($s['text'] ?? ''), crm_lead((int)$r['lead_id'])) : '', 'subject' => (string)($s['subject'] ?? '')];
}
function crm_plan_start(int $leadId, int $planId): bool
{
    $p = crm_plan($planId); if (!$p || !$p['steps']) return false;
    db()->prepare("UPDATE crm_plan_runs SET state = 'stopped' WHERE workspace_id = ? AND lead_id = ? AND state IN ('wait','due')")->execute([(int)ws('id'), $leadId]);
    $due = date('Y-m-d H:i:s', time() + 86400 * max(0, (int)($p['steps'][0]['day'] ?? 0)));
    db()->prepare('INSERT INTO crm_plan_runs (workspace_id, lead_id, plan_id, due_at) VALUES (?, ?, ?, ?)')->execute([(int)ws('id'), $leadId, $planId, $due]);
    crm_act_add($leadId, 'note', '🔁 ' . t('بدأت خطة المتابعة: %s', $p['name']));
    crm_plan_tick(20, $leadId);
    return true;
}
/** بعد ما خطوة تخلص: الخطوة اللي بعدها، أو الخطة تخلص */
function crm_plan_advance(array $run, array $plan): void
{
    $next = (int)$run['step'] + 1;
    if ($next >= count($plan['steps'])) { db()->prepare("UPDATE crm_plan_runs SET state = 'done', step = ? WHERE id = ?")->execute([$next, (int)$run['id']]); crm_act_add((int)$run['lead_id'], 'note', '🔁 ' . t('خلصت خطة المتابعة: %s', $plan['name'])); return; }
    $due = date('Y-m-d H:i:s', max(time(), strtotime((string)$run['started_at']) + 86400 * max(0, (int)($plan['steps'][$next]['day'] ?? 0))));
    db()->prepare("UPDATE crm_plan_runs SET state = 'wait', step = ?, due_at = ?, last_error = NULL WHERE id = ?")->execute([$next, $due, (int)$run['id']]);
}
/** المحرّك: بيشتغل من الـ cron ومع فتح صفحة الـ Leads. الإيميل بيتبعت، والباقي بيتحول متابعة للمسؤول */
function crm_plan_tick(int $max = 50, ?int $leadId = null): int
{
    $wid = (int)ws('id');
    $q = "SELECT * FROM crm_plan_runs WHERE workspace_id = ? AND state = 'wait' AND due_at <= ?" . ($leadId ? ' AND lead_id = ' . (int)$leadId : '') . " ORDER BY due_at LIMIT " . (int)$max;
    $st = db()->prepare($q); $st->execute([$wid, date('Y-m-d H:i:s')]);
    $n = 0; $plans = [];
    foreach ($st->fetchAll() as $run) {
        for ($guard = 0; $guard < 10; $guard++) { // خطوات اليوم نفسه ورا بعض
            $plan = $plans[$run['plan_id']] = $plans[$run['plan_id']] ?? crm_plan((int)$run['plan_id']);
            $l = crm_lead((int)$run['lead_id']);
            if (!$plan || !$l || in_array($l['stage'], ['won', 'lost'], true)) { db()->prepare("UPDATE crm_plan_runs SET state = 'stopped' WHERE id = ?")->execute([(int)$run['id']]); break; }
            $s = $plan['steps'][(int)$run['step']] ?? null; if (!$s) { crm_plan_advance($run, $plan); break; }
            $txt = crm_plan_text((string)($s['text'] ?? ''), $l);
            if (($s['ch'] ?? '') === 'email' && !empty($l['email']) && function_exists('smtp_config') && smtp_config($wid)) {
                try {
                    crm_send_email($l, crm_plan_text((string)($s['subject'] ?? '') ?: $plan['name'], $l), $txt, '🔁');
                    crm_plan_advance($run, $plan); $n++;
                } catch (Throwable $ex) {
                    db()->prepare("UPDATE crm_plan_runs SET state = 'due', last_error = ? WHERE id = ?")->execute([mb_substr($ex->getMessage(), 0, 255), (int)$run['id']]);
                    crm_plan_mark_due($l, $plan, (int)$run['step'], $s); break;
                }
                $st2 = db()->prepare("SELECT * FROM crm_plan_runs WHERE id = ? AND state = 'wait' AND due_at <= ?"); $st2->execute([(int)$run['id'], date('Y-m-d H:i:s')]);
                $run = $st2->fetch(); if (!$run) break;
                continue;
            }
            // واتساب/مكالمة/مهمة (أو إيميل من غير SMTP): مهمة مستحقة للمسؤول
            db()->prepare("UPDATE crm_plan_runs SET state = 'due', last_error = ? WHERE id = ?")->execute([($s['ch'] ?? '') === 'email' ? t('مفيش SMTP أو مفيش إيميل للعميل، فبقت مهمة') : null, (int)$run['id']]);
            crm_plan_mark_due($l, $plan, (int)$run['step'], $s); $n++;
            break;
        }
    }
    return $n;
}
function crm_plan_mark_due(array $l, array $plan, int $step, array $s): void
{
    $ch = crm_plan_channels()[$s['ch'] ?? 'task'] ?? ['✅', ''];
    db()->prepare('UPDATE crm_leads SET next_at = IF(next_at IS NULL OR next_at > NOW(), NOW(), next_at), next_note = ? WHERE id = ?')
        ->execute([mb_substr('🔁 ' . $plan['name'] . ' · ' . ($step + 1) . '/' . count($plan['steps']) . ': ' . $ch[0] . ' ' . mb_substr(crm_plan_text((string)($s['text'] ?? ''), $l), 0, 120), 0, 255), (int)$l['id']]);
}
/** تنفيذ خطوة يدوي (واتساب/مكالمة/مهمة) */
function crm_plan_do(int $runId, bool $skip = false): void
{
    $st = db()->prepare("SELECT * FROM crm_plan_runs WHERE id = ? AND workspace_id = ? AND state IN ('due','wait')"); $st->execute([$runId, (int)ws('id')]);
    $run = $st->fetch(); if (!$run) return;
    $plan = crm_plan((int)$run['plan_id']); $l = crm_lead((int)$run['lead_id']); if (!$plan || !$l) return;
    $s = $plan['steps'][(int)$run['step']] ?? [];
    if (!$skip) {
        $k = ['wa' => 'wa', 'call' => 'call', 'email' => 'email'][$s['ch'] ?? ''] ?? 'note';
        crm_act_add((int)$l['id'], $k, '🔁 ' . crm_plan_text((string)($s['text'] ?? ''), $l));
        db()->prepare('UPDATE crm_leads SET last_contact_at = NOW(), next_at = NULL, next_note = NULL WHERE id = ?')->execute([(int)$l['id']]);
        if ($l['stage'] === 'new' && $k !== 'note') db()->prepare("UPDATE crm_leads SET stage = 'contacted' WHERE id = ?")->execute([(int)$l['id']]);
    }
    crm_plan_advance($run, $plan);
    crm_plan_tick(5, (int)$l['id']);
}
function crm_plan_stop(int $leadId): void
{
    db()->prepare("UPDATE crm_plan_runs SET state = 'stopped' WHERE workspace_id = ? AND lead_id = ? AND state IN ('wait','due')")->execute([(int)ws('id'), $leadId]);
    crm_act_add($leadId, 'note', '🔁 ' . t('اتوقفت خطة المتابعة'));
}
/** كل الخطط الشغالة: [lead_id => "2/4"] للعرض في القايمة */
function crm_runs_map(): array
{
    $st = db()->prepare("SELECT r.lead_id, r.step, r.state, p.steps FROM crm_plan_runs r JOIN crm_plans p ON p.id = r.plan_id WHERE r.workspace_id = ? AND r.state IN ('wait','due')");
    $st->execute([(int)ws('id')]);
    $o = []; foreach ($st->fetchAll() as $r) $o[(int)$r['lead_id']] = ((int)$r['step'] + 1) . '/' . count(json_decode((string)$r['steps'], true) ?: []) . ($r['state'] === 'due' ? '!' : '');
    return $o;
}

/* ---------- الصفقات + تاريخ الفوز + تقارير الفريق ---------- */
/** تاريخ "بقى عميل" بيتظبط لوحده بعد أي تعديل (أي مكان غيّر المرحلة) */
function crm_sync_won(): void
{
    $wid = (int)ws('id'); if (!$wid) return;
    try {
        db()->prepare("UPDATE crm_leads SET won_at = NOW() WHERE workspace_id = ? AND stage = 'won' AND won_at IS NULL")->execute([$wid]);
        db()->prepare("UPDATE crm_leads SET won_at = NULL WHERE workspace_id = ? AND stage <> 'won' AND won_at IS NOT NULL")->execute([$wid]);
    } catch (Throwable $e) {}
}
function crm_deal_statuses(): array { return ['open' => t('مفتوحة'), 'won' => t('اتقفلت بنجاح'), 'lost' => t('خسرناها')]; }
function crm_deals(int $leadId): array
{
    $st = db()->prepare('SELECT id, title, value, status, close_at, closed_at FROM crm_deals WHERE workspace_id = ? AND lead_id = ? ORDER BY status = \'open\' DESC, id DESC');
    $st->execute([(int)ws('id'), $leadId]);
    return array_map(function ($d) { $d['id'] = (int)$d['id']; $d['value'] = (float)$d['value']; return $d; }, $st->fetchAll());
}
/** قيمة الصفقات المفتوحة على العميل بتتحسب في deal_value عشان القمع والتقييم يفضلوا شغالين */
function crm_deals_rollup(int $leadId): void
{
    $st = db()->prepare("SELECT COUNT(*), COALESCE(SUM(CASE WHEN status <> 'lost' THEN value END), 0) FROM crm_deals WHERE workspace_id = ? AND lead_id = ?");
    $st->execute([(int)ws('id'), $leadId]); [$n, $sum] = $st->fetch(PDO::FETCH_NUM);
    if ((int)$n) db()->prepare('UPDATE crm_leads SET deal_value = ? WHERE id = ?')->execute([(float)$sum, $leadId]);
}
/** فترة التقرير */
function crm_period(string $p): array
{
    $tz = new DateTimeZone(site_timezone()); $n = new DateTime('now', $tz);
    switch ($p) {
        case 'last_month': $a = (new DateTime('first day of last month', $tz))->setTime(0, 0); $b = (new DateTime('first day of this month', $tz))->setTime(0, 0); break;
        case '7d': $a = (clone $n)->modify('-7 days'); $b = (clone $n)->modify('+1 minute'); break;
        case '30d': $a = (clone $n)->modify('-30 days'); $b = (clone $n)->modify('+1 minute'); break;
        default: $a = (new DateTime('first day of this month', $tz))->setTime(0, 0); $b = (new DateTime('first day of next month', $tz))->setTime(0, 0);
    }
    $a->setTimezone(new DateTimeZone(date_default_timezone_get())); $b->setTimezone(new DateTimeZone(date_default_timezone_get()));
    return [$a->format('Y-m-d H:i:s'), $b->format('Y-m-d H:i:s')];
}
/** أداء كل موظف في فترة: عملاء جداله، تواصل، مكالمات ردت، عملاء كسبهم، قيمة، أول رد، كريديت Apollo */
function crm_team_report(string $from, string $to): array
{
    $wid = (int)ws('id'); crm_sync_won();
    $rows = [];
    $blank = ['leads' => 0, 'contacts' => 0, 'calls' => 0, 'answered' => 0, 'won' => 0, 'value' => 0.0, 'resp' => [], 'reveals' => 0, 'open' => 0, 'overdue' => 0];
    $rows[0] = ['id' => 0, 'name' => t('من غير مسؤول')] + $blank;
    foreach (crm_members() as $m) $rows[(int)$m['id']] = ['id' => (int)$m['id'], 'name' => $m['name'], 'leads' => 0, 'contacts' => 0, 'calls' => 0, 'answered' => 0, 'won' => 0, 'value' => 0.0, 'resp' => [], 'reveals' => 0, 'open' => 0, 'overdue' => 0];
    $q = function ($sql, $args) { $st = db()->prepare($sql); $st->execute($args); return $st->fetchAll(PDO::FETCH_NUM); };
    foreach ($q('SELECT COALESCE(owner_id, 0) o, COUNT(*) FROM crm_leads WHERE workspace_id = ? AND created_at >= ? AND created_at < ? GROUP BY o', [$wid, $from, $to]) as [$u, $n]) if (isset($rows[$u])) $rows[$u]['leads'] = (int)$n;
    foreach ($q("SELECT user_id, kind, COUNT(*), SUM(body LIKE ?) FROM crm_acts WHERE workspace_id = ? AND created_at >= ? AND created_at < ? AND kind IN ('call','wa','email','meet') AND user_id IS NOT NULL GROUP BY user_id, kind", ['[' . crm_call_outcomes()['answered'] . ']%', $wid, $from, $to]) as [$u, $k, $n, $ans]) {
        if (!isset($rows[$u])) continue; $rows[$u]['contacts'] += (int)$n; if ($k === 'call') { $rows[$u]['calls'] += (int)$n; $rows[$u]['answered'] += (int)$ans; }
    }
    foreach ($q("SELECT COALESCE(owner_id, 0) o, COUNT(*), COALESCE(SUM(deal_value), 0) FROM crm_leads WHERE workspace_id = ? AND stage = 'won' AND won_at >= ? AND won_at < ? GROUP BY o", [$wid, $from, $to]) as [$u, $n, $v]) if (isset($rows[$u])) { $rows[$u]['won'] = (int)$n; $rows[$u]['value'] = (float)$v; }
    // صفقات اتقفلت بنجاح على عملاء ما اتحسبوش فوق (عميل قديم اشترى تاني)
    foreach ($q("SELECT COALESCE(d.owner_id, 0) o, COALESCE(SUM(d.value), 0) FROM crm_deals d JOIN crm_leads l ON l.id = d.lead_id WHERE d.workspace_id = ? AND d.status = 'won' AND d.closed_at >= ? AND d.closed_at < ? AND NOT (l.stage = 'won' AND l.won_at >= ? AND l.won_at < ?) GROUP BY o", [$wid, $from, $to, $from, $to]) as [$u, $v]) if (isset($rows[$u])) $rows[$u]['value'] += (float)$v;
    // سرعة أول رد: من وقت ما العميل اتضاف لحد أول تواصل
    foreach ($q("SELECT COALESCE(l.owner_id, 0), TIMESTAMPDIFF(MINUTE, l.created_at, MIN(a.created_at)) FROM crm_leads l JOIN crm_acts a ON a.lead_id = l.id AND a.kind IN ('call','wa','email','meet') WHERE l.workspace_id = ? AND l.created_at >= ? AND l.created_at < ? GROUP BY l.id, l.owner_id", [$wid, $from, $to]) as [$u, $mins]) if (isset($rows[$u]) && $mins !== null) $rows[$u]['resp'][] = max(0, (int)$mins);
    foreach ($q('SELECT user_id, COUNT(*) FROM crm_reveals WHERE workspace_id = ? AND created_at >= ? AND created_at < ? GROUP BY user_id', [$wid, $from, $to]) as [$u, $n]) if (isset($rows[$u])) $rows[$u]['reveals'] = (int)$n;
    foreach ($q("SELECT COALESCE(owner_id, 0) o, COUNT(*), SUM(next_at IS NOT NULL AND next_at < NOW()) FROM crm_leads WHERE workspace_id = ? AND stage NOT IN ('won','lost') GROUP BY o", [$wid]) as [$u, $n, $od]) if (isset($rows[$u])) { $rows[$u]['open'] = (int)$n; $rows[$u]['overdue'] = (int)$od; }
    $any = false; foreach ($rows[0] as $k => $v) if (is_numeric($v) && $k !== 'id' && $v) $any = true;
    if (!$any) unset($rows[0]);
    foreach ($rows as &$r) { sort($r['resp']); $c = count($r['resp']); $r['resp'] = $c ? $r['resp'][intdiv($c, 2)] : null; } unset($r); // الوسيط مش المتوسط عشان حالة واحدة متبوظش الرقم
    return array_values($rows);
}
/** الأهداف الشهرية: {team:{won,value,contacts}, users:{id:{...}}} */
function crm_goals(string $ym): array
{
    $g = json_decode((string)setting('crm_goals_' . (int)ws('id') . '_' . $ym, ''), true);
    return is_array($g) ? $g + ['team' => [], 'users' => []] : ['team' => [], 'users' => []];
}
function fmt_minutes(?int $m): string
{
    if ($m === null) return '—';
    if ($m < 60) return t('%s دقيقة', $m);
    if ($m < 1440) return t('%s ساعة', round($m / 60, 1));
    return t('%s يوم', round($m / 1440, 1));
}

/* ---------- إيميل من المنصة + تتبع الفتح والضغط (بكسل صغير + روابط بتعدّي علينا) ---------- */
function crm_send_email(array $l, string $subject, string $text, string $via = ''): array
{
    $wid = (int)ws('id');
    if (empty($l['email'])) throw new CrmException(t('العميل ده مالوش إيميل.'));
    if (!smtp_config($wid)) throw new CrmException(t('اربط SMTP من التكاملات الأول (Gmail ينفع ببلاش).'));
    $subject = mb_substr(trim($subject), 0, 255) ?: t('رسالة من %s', (string)(ws('name') ?? ''));
    $tok = bin2hex(random_bytes(16));
    $base = base_url() . '/index.php?p=crm_t&k=' . $tok;
    // الروابط بتتحول عشان نعرف إنه ضغط، وبعدها بيروح للرابط الأصلي
    $html = preg_replace_callback('~https?://[^\s<>"\']+~u', function ($m) use ($base) { return '<a href="' . e($base . '&u=' . rawurlencode($m[0])) . '">' . e($m[0]) . '</a>'; }, nl2br(e($text)));
    $html = '<div style="font-family:Tahoma,Arial,sans-serif;font-size:15px;line-height:1.7" dir="auto">' . $html . '</div><img src="' . e($base . '&o=1') . '" width="1" height="1" alt="" style="display:block;border:0">';
    send_mail((string)$l['email'], $subject, $html, null, $wid);
    db()->prepare('INSERT INTO crm_mails (workspace_id, lead_id, user_id, token, subject) VALUES (?, ?, ?, ?, ?)')->execute([$wid, (int)$l['id'], (int)(current_user()['id'] ?? 0) ?: null, $tok, $subject]);
    crm_act_add((int)$l['id'], 'email', ($via !== '' ? $via . ' ' : '') . '“' . $subject . '”' . "\n" . mb_substr($text, 0, 1500));
    db()->prepare('UPDATE crm_leads SET last_contact_at = NOW() WHERE id = ?')->execute([(int)$l['id']]);
    if ($l['stage'] === 'new') db()->prepare("UPDATE crm_leads SET stage = 'contacted' WHERE id = ?")->execute([(int)$l['id']]);
    return ['token' => $tok];
}
function crm_mails(int $leadId): array
{
    $st = db()->prepare('SELECT subject, opens, clicks, opened_at, clicked_at, created_at FROM crm_mails WHERE workspace_id = ? AND lead_id = ? ORDER BY id DESC LIMIT 20');
    $st->execute([(int)ws('id'), $leadId]);
    return $st->fetchAll();
}
/** نتايج المكالمة؛ "مارد" و"مشغول" بيحطوا محاولة تانية لوحدهم */
function crm_call_outcomes(): array
{
    return ['answered' => t('رد'), 'no_answer' => t('مارد'), 'busy' => t('مشغول/قفل'), 'wrong' => t('رقم غلط'), 'later' => t('قال كلّمني بعدين')];
}

/* ---------- ذكاء العملاء بالحسابات (من غير AI ومن غير كريديت) ---------- */
/** تقييم 0–100 وأسبابه: بيانات تواصل + أهمية الوظيفة + المصدر + التفاعل + المرحلة − البرود */
function crm_score(array $l, int $acts = 0, int $now = 0): array
{
    $now = $now ?: time(); $s = 0; $why = [];
    if (in_array($l['stage'], ['won', 'lost'], true)) return [0, []];
    if (!empty($l['phone'])) { $s += 15; $why[] = t('عنده رقم'); }
    if (!empty($l['email'])) { $s += in_array((string)($l['email_status'] ?? ''), ['verified', 'likely to engage'], true) ? 14 : 9; $why[] = t('عنده إيميل'); }
    $ti = mb_strtolower((string)$l['title']);
    if (preg_match('/owner|founder|ceo|chief|president|partner|مالك|مؤسس|صاحب|رئيس تنفيذي|المدير العام|شريك/u', $ti)) { $s += 15; $why[] = t('صاحب قرار'); }
    elseif (preg_match('/director|head|vp|manager|gm|مدير|رئيس/u', $ti)) { $s += 9; $why[] = t('مدير'); }
    $src = ['form' => [16, t('طلب بنفسه من الموقع')], 'inbox' => [12, t('كلّمك على السوشيال')], 'comments' => [10, t('علّق على منشورك')], 'ads' => [12, t('سجّل في إعلان')], 'whatsapp' => [12, t('كلّمك واتساب')], 'reveal' => [4, ''], 'manual' => [6, ''], 'import' => [2, '']][$l['source']] ?? [0, ''];
    $s += $src[0]; if ($src[1] !== '') $why[] = $src[1];
    $s += ['new' => 0, 'contacted' => 6, 'qualified' => 14, 'proposal' => 22][$l['stage']] ?? 0;
    if ((float)($l['deal_value'] ?? 0) > 0) { $s += 5; $why[] = t('ليه قيمة صفقة'); }
    if ($acts >= 3) { $s += 6; $why[] = t('تواصل كتير'); }
    $last = strtotime((string)($l['last_contact_at'] ?: $l['created_at']));
    $age = ($now - $last) / 86400;
    if ($age <= 3) $s += 8; elseif ($age > 14) { $s -= 12; $why[] = t('بارد من أكتر من أسبوعين'); }
    return [max(0, min(100, $s)), $why];
}
/** ملخص سطر واحد + أحسن وقت + أول رسالة مقترحة (قوالب حسب المصدر والحالة) */
function crm_intel(array $l, array $acts): array
{
    $srcs = crm_sources(); $now = time();
    $days = max(0, (int)floor(($now - strtotime((string)$l['created_at'])) / 86400));
    $contacts = array_filter($acts, function ($a) { return in_array($a['kind'], ['call', 'wa', 'email', 'meet'], true); });
    $lastC = $contacts ? reset($contacts)['created_at'] : null;
    $parts = [t('جه من %s', $srcs[$l['source']] ?? $l['source']) . ' ' . ($days ? t('من %s يوم', $days) : t('النهارده'))];
    $parts[] = $contacts ? t('%s تواصل، آخرهم %s', count($contacts), fmt_date($lastC, 'd/m')) : t('محدش كلّمه لسه');
    // الإنسان بيكتب إمتى؟ ساعات الرسايل اللي جاية منه (الفورم/السوشيال/وقت ما اتضاف)
    $hours = [];
    foreach ($acts as $a) if ($a['kind'] === 'note' && preg_match('/^(“|🌐)/u', (string)$a['body'])) $hours[] = (int)fmt_date($a['created_at'], 'G');
    if (in_array($l['source'], ['form', 'inbox', 'comments', 'ads', 'whatsapp'], true)) $hours[] = (int)fmt_date($l['created_at'], 'G');
    $best = '';
    if ($hours) {
        $c = array_count_values($hours); arsort($c); $h = (int)array_key_first($c);
        $best = t('حوالي %s (الوقت اللي بيكتب فيه)', fmt_hour($h));
    } elseif (($g = crm_best_hour_global()) !== null) $best = t('حوالي %s (أغلب عملاءك بيتفاعلوا الساعة دي)', fmt_hour($g));
    // أول رسالة: بتتبني من آخر حاجة كتبها + المصدر + الوظيفة
    $first = preg_split('/\s+/u', trim((string)$l['name']))[0] ?? '';
    $said = '';
    foreach (array_reverse($acts) as $a) if ($a['kind'] === 'note' && preg_match('/“(.+?)”/us', (string)$a['body'], $mm)) { $said = mb_substr(trim(preg_replace(['/\+?\d[\d\s\-]{6,}\d/u', '/[A-Z0-9._%+\-]+@\S+/iu', '/\s{2,}/u'], ['', '', ' '], $mm[1]), " \t\n.,:"), 0, 80); if (mb_strlen($said) < 3) $said = ''; break; }
    $co = trim((string)$l['company']);
    switch ($l['source']) {
        case 'form': $msg = $said !== '' ? t('أهلاً %s، وصلنا طلبك على الموقع بخصوص "%s". إمتى يناسبك نتكلم 5 دقايق؟', $first, $said) : t('أهلاً %s، شكرًا إنك تواصلت معانا من الموقع. محتاج تفاصيل عن إيه بالظبط؟', $first); break;
        case 'comments': $msg = t('أهلاً %s، شفت تعليقك على منشورنا%s. حابب أبعتلك التفاصيل والأسعار هنا؟', $first, $said !== '' ? ' ("' . $said . '")' : ''); break;
        case 'inbox': case 'whatsapp': $msg = $said !== '' ? t('أهلاً %s، بخصوص رسالتك "%s"، عندي التفاصيل كلها. ينفع أكلمك دقيقتين؟', $first, $said) : t('أهلاً %s، شكرًا لرسالتك! ينفع أكلمك دقيقتين أشرحلك؟', $first); break;
        case 'ads': $msg = t('أهلاً %s، شكرًا إنك سجلت معانا في الإعلان. إمتى يناسبك أكلمك أشرحلك العرض؟', $first); break;
        case 'import': $msg = t('أهلاً %s، بقالنا فترة ماتكلمناش. عندنا جديد ممكن يفيدك%s. ينفع أبعتلك التفاصيل؟', $first, $co !== '' ? ' في ' . $co : ''); break;
        default:
            $msg = preg_match('/owner|founder|ceo|مالك|مؤسس|صاحب/iu', (string)$l['title'])
                ? t('أهلاً %s، أنا متابع شغل %s وعندي فكرة ممكن توفر عليكم وقت وفلوس. يناسبك مكالمة 10 دقايق الأسبوع ده؟', $first, $co ?: t('شركتكم'))
                : t('أهلاً %s، بشتغل مع شركات زي %s ونقدر نساعدكم في %s. ينفع أبعتلك تفاصيل؟', $first, $co ?: t('شركتكم'), (string)$l['title'] !== '' ? t('شغل %s', $l['title']) : t('الشغل اليومي'));
    }
    return ['summary' => implode(' · ', $parts), 'best' => $best, 'first' => $msg];
}
function fmt_hour(int $h): string { $ap = $h < 12 ? t('ص') : t('م'); $x = $h % 12 ?: 12; return $x . ' ' . $ap; }
/** أكتر ساعة بيكتب فيها عملاء الشركة كلهم (متخزنة يوم) */
function crm_best_hour_global(): ?int
{
    $k = 'crm_best_hour_' . (int)ws('id'); $c = explode('|', (string)setting($k, ''));
    if (count($c) === 2 && (int)$c[0] > time() - 86400) return $c[1] === '' ? null : (int)$c[1];
    $st = db()->prepare("SELECT created_at FROM crm_leads WHERE workspace_id = ? AND source IN ('form','inbox','comments','ads','whatsapp') ORDER BY id DESC LIMIT 500");
    $st->execute([(int)ws('id')]);
    $h = []; foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $d) $h[] = (int)fmt_date($d, 'G');
    $best = null; if (count($h) >= 5) { $cnt = array_count_values($h); arsort($cnt); $best = (int)array_key_first($cnt); }
    save_setting($k, time() . '|' . ($best === null ? '' : $best));
    return $best;
}

/* ---------- تتبع زيارات العملاء لموقعك (مجاني: كود صغير على موقعك + روابط متتبعة) ---------- */
/** توكن العميل في الروابط: id.توقيع (مايتخمنش ومايتغيرش) */
function crm_lead_token(int $wid, int $leadId): string
{
    $sec = (string)setting('crm_form_token_' . $wid, '') ?: (string)setting('cron_token', 'x');
    return $leadId . '.' . substr(hash_hmac('sha256', $wid . ':' . $leadId, $sec), 0, 12);
}
function crm_lead_from_token(int $wid, string $tok): int
{
    if (!preg_match('/^(\d{1,10})\.([0-9a-f]{12})$/', $tok, $m)) return 0;
    return hash_equals(crm_lead_token($wid, (int)$m[1]), $tok) ? (int)$m[1] : 0;
}
function crm_track_cfg(int $wid): array
{
    $c = json_decode((string)setting('crm_track_cfg_' . $wid, ''), true) ?: [];
    return $c + ['hot' => 'price, pricing, plans, اسعار, أسعار, الباقات, checkout, cart', 'wa' => '', 'btn' => 1, 'color' => '#4f46e5', 'label' => ''];
}
/** تسجيل زيارة (مرة لكل صفحة في الساعة). الصفحات "السخنة" (الأسعار…) بتعمل متابعة "كلّمه دلوقتي" */
function crm_track_visit(int $wid, int $leadId, string $url): void
{
    $url = mb_substr(preg_replace('/([?&])ap=[^&#]*&?/', '$1', $url), 0, 500);
    $st = db()->prepare('SELECT COUNT(*) FROM crm_visits WHERE workspace_id = ? AND lead_id = ? AND url = ? AND created_at > ?');
    $st->execute([$wid, $leadId, $url, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int)$st->fetchColumn()) return;
    $hot = 0;
    foreach (array_filter(array_map('trim', explode(',', mb_strtolower(crm_track_cfg($wid)['hot'])))) as $w) if ($w !== '' && mb_strpos(mb_strtolower(rawurldecode($url)), $w) !== false) { $hot = 1; break; }
    db()->prepare('INSERT INTO crm_visits (workspace_id, lead_id, url, hot) VALUES (?, ?, ?, ?)')->execute([$wid, $leadId, $url, $hot]);
    ai_as_workspace($wid, function () use ($leadId, $url, $hot) {
        $l = crm_lead($leadId); if (!$l) return;
        if ($hot) {
            crm_act_add($leadId, 'note', '🔥 ' . t('فتح صفحة مهمة على موقعك') . "\n" . $url);
            db()->prepare('UPDATE crm_leads SET next_at = IF(next_at IS NULL OR next_at > NOW(), NOW(), next_at), next_note = ? WHERE id = ?')->execute([t('🔥 بيتفرج على صفحة الأسعار دلوقتي، كلّمه'), $leadId]);
        }
    });
}
function crm_visits(int $leadId): array
{
    $st = db()->prepare('SELECT url, hot, created_at FROM crm_visits WHERE workspace_id = ? AND lead_id = ? ORDER BY id DESC LIMIT 15');
    $st->execute([(int)ws('id'), $leadId]);
    return $st->fetchAll();
}
/** رابط متتبع لعميل: بيروح لموقعك ومعاه ?ap=توكن عشان كود الموقع يعرفه */
function crm_track_link(array $l, string $url): string
{
    $wid = (int)ws('id'); crm_form_url();
    return base_url() . '/index.php?' . http_build_query(['p' => 'crm_go', 'w' => $wid, 'k' => crm_lead_token($wid, (int)$l['id']), 'u' => $url]);
}
function crm_site_script(): string
{
    $wid = (int)ws('id'); crm_form_url();
    return '<script src="' . e(base_url() . '/index.php?' . http_build_query(['p' => 'crm_js', 'w' => $wid, 't' => (string)setting('crm_form_token_' . $wid, '')])) . '" async></script>';
}

/** "هاتلي ناس شبه أحسن عملائي": أكتر مسميات ودول ومجالات وأحجام متكررة في اللي كسبتهم (حسابات بس) */
function crm_lookalike(): array
{
    $wid = (int)ws('id');
    $pick = function ($stages) use ($wid) {
        $st = db()->prepare("SELECT l.title, l.country, c.industry, c.employees FROM crm_leads l LEFT JOIN crm_companies c ON c.id = l.company_id WHERE l.workspace_id = ? AND l.stage IN (" . implode(',', array_fill(0, count($stages), '?')) . ") LIMIT 500");
        $st->execute(array_merge([$wid], $stages)); return $st->fetchAll();
    };
    $rows = $pick(['won']); $basis = 'won';
    if (count($rows) < 3) { $rows = $pick(['won', 'proposal', 'qualified']); $basis = 'warm'; }
    if (count($rows) < 2) return ['ok' => false];
    $top = function (array $vals, int $n, int $min = 1) { $c = array_count_values(array_filter($vals, 'strlen')); arsort($c); return array_keys(array_filter(array_slice($c, 0, $n, true), function ($x) use ($min) { return $x >= $min; })); };
    // المسمى: بنوحّد الكلمات الشائعة عشان "Owner & Founder" و"Owner" يتحسبوا واحد
    $titles = array_map(function ($t) { $t = mb_strtolower(trim((string)$t)); foreach (['owner' => 'Owner', 'founder' => 'Founder', 'ceo' => 'CEO', 'marketing' => 'Marketing Manager', 'sales' => 'Sales Manager', 'operations' => 'Operations Manager', 'general manager' => 'General Manager'] as $k => $v) if (strpos($t, $k) !== false) return $v; return $t !== '' ? ucwords($t) : ''; }, array_column($rows, 'title'));
    $sizes = []; foreach (array_column($rows, 'employees') as $e) { $e = (int)$e; if (!$e) continue; foreach (crm_sizes() as $k => $_) { [$a, $b] = array_map('intval', explode(',', $k)); if ($e >= $a && $e <= $b) { $sizes[] = $k; break; } } }
    return ['ok' => true, 'basis' => $basis, 'n' => count($rows), 'filters' => [
        'titles' => implode(', ', $top($titles, 3)), 'locations' => implode(', ', $top(array_column($rows, 'country'), 3)),
        'keywords' => implode(' ', $top(array_column($rows, 'industry'), 1)), 'sizes' => $top($sizes, 2), 'seniorities' => [], 'domains' => '', 'rev_min' => '', 'rev_max' => '']];
}

/* ---------- حجز المواعيد ---------- */
function crm_book_cfg(): array
{
    $c = json_decode((string)setting('crm_book_cfg_' . (int)ws('id'), ''), true) ?: [];
    return $c + ['title' => '', 'days' => [0, 1, 2, 3, 4], 'from' => '10:00', 'to' => '17:00', 'len' => 30, 'ahead' => 14, 'notice' => 3, 'color' => '#4f46e5'];
}
function crm_book_url(): string { crm_form_url(); $wid = (int)ws('id'); return base_url() . '/index.php?' . http_build_query(['p' => 'crm_book', 'w' => $wid, 't' => (string)setting('crm_form_token_' . $wid, '')]); }
/** المواعيد الفاضية: [Y-m-d => ['Y-m-d H:i', ...]] بتوقيت الشركة، من غير المحجوز ومن غير اللي قرّب أوي */
function crm_book_slots(array $c): array
{
    $tz = new DateTimeZone(site_timezone()); $srv = new DateTimeZone(date_default_timezone_get());
    $len = max(15, min(240, (int)$c['len'])); $now = new DateTime('now', $tz); $min = (clone $now)->modify('+' . max(0, (int)$c['notice']) . ' hours');
    $st = db()->prepare('SELECT start_at FROM crm_bookings WHERE workspace_id = ? AND start_at >= ?'); $st->execute([(int)ws('id'), date('Y-m-d H:i:s')]);
    $taken = []; foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $s) $taken[(new DateTime($s, $srv))->setTimezone($tz)->format('Y-m-d H:i')] = 1;
    $out = [];
    for ($i = 0; $i <= max(1, min(60, (int)$c['ahead'])); $i++) {
        $d = (clone $now)->setTime(0, 0)->modify("+$i days");
        if (!in_array((int)$d->format('w'), array_map('intval', (array)$c['days']), true)) continue;
        [$fh, $fm] = array_map('intval', explode(':', (string)$c['from']) + [0, 0]); [$th, $tm] = array_map('intval', explode(':', (string)$c['to']) + [0, 0]);
        $t = (clone $d)->setTime($fh, $fm); $end = (clone $d)->setTime($th, $tm);
        for (; $t < $end; $t->modify("+$len minutes")) { $k = $t->format('Y-m-d H:i'); if ($t > $min && !isset($taken[$k])) $out[$d->format('Y-m-d')][] = $k; }
    }
    return $out;
}
function fmt_date_loc(DateTime $d, string $what): string
{
    if (!is_rtl()) return $d->format($what);
    $days = ['Sun' => 'الأحد', 'Mon' => 'الاتنين', 'Tue' => 'التلات', 'Wed' => 'الأربع', 'Thu' => 'الخميس', 'Fri' => 'الجمعة', 'Sat' => 'السبت'];
    $mons = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    return $what === 'D' ? $days[$d->format('D')] : $mons[(int)$d->format('n') - 1];
}

/** رابط فورم الموقع (بيتعمل التوكن أول مرة) */
function crm_form_url(bool $regen = false): string
{
    $wid = (int)ws('id'); $t = (string)setting('crm_form_token_' . $wid, '');
    if ($t === '' || $regen) { $t = bin2hex(random_bytes(16)); save_setting('crm_form_token_' . $wid, $t); }
    return base_url() . '/index.php?' . http_build_query(['p' => 'lead_form', 'w' => $wid, 't' => $t]);
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
