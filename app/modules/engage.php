<?php
/** أدوات التفاعل والفريق (#39 #40 #41 #43 #45 #46 #49) — كلها من غير ذكاء اصطناعي */

/* ---------- #46 صلاحيات لكل حساب ---------- */
function acc_perm_map(): array
{
    return json_decode((string)setting('acc_perm_' . (int)ws('id'), ''), true) ?: [];
}
/** null = كل الحسابات. المالك ومين عنده team.manage مش بيتقيّدوا */
function acc_allowed(?int $uid = null): ?array
{
    $uid = $uid ?? (int)(current_user()['id'] ?? 0);
    if ($uid === (int)(current_user()['id'] ?? 0) && (is_ws_owner() || can('team.manage'))) return null;
    $m = acc_perm_map();
    return isset($m[$uid]) && is_array($m[$uid]) && $m[$uid] ? array_values(array_map('strval', $m[$uid])) : null;
}
function acc_filter(array $accounts): array
{
    $al = acc_allowed(); if ($al === null) return $accounts;
    return array_values(array_filter($accounts, function ($a) use ($al) { return in_array((string)($a['_id'] ?? ''), $al, true); }));
}

/* ---------- #43 سؤال السعر ---------- */
function cm_is_price_q(string $t): bool
{
    return (bool)preg_match('/(بكام|بكم|كام السعر|السعر|سعره|سعرها|الاسعار|الأسعار|كم سعر|التكلفة|تكلفة|price|how much|cost|\bhm\b)/iu', $t);
}

/* ---------- #39 إخفاء تلقائي للشتايم والسبام ---------- */
function cm_spam_cfg(): array
{
    $c = json_decode((string)setting('cm_spam_' . (int)ws('id'), ''), true) ?: [];
    return $c + ['on' => 0, 'links' => 1, 'words' => ''];
}
function cm_spam_words(array $c): array
{
    $base = ['حرامي', 'نصاب', 'نصابين', 'زبالة', 'خول', 'شرموط', 'متناك', 'telegram', 'تليجرام', 'واتساب الربح', 'اربح', 'crypto', 'bitcoin', 'فوركس', 'forex', 'followers', 'متابعين مجانا', 'click here'];
    $own = array_filter(array_map(function ($x) { return mb_strtolower(trim($x)); }, preg_split('/[,،\n]/u', (string)$c['words'])), 'strlen');
    return array_values(array_unique(array_merge($base, $own)));
}
function cm_is_bad(string $t, array $c): string
{
    $l = mb_strtolower($t);
    foreach (cm_spam_words($c) as $w) if ($w !== '' && mb_strpos($l, $w) !== false) return 'word';
    if (!empty($c['links']) && preg_match('~(https?://|www\.|bit\.ly|t\.me/|wa\.me/)~i', $t)) return 'link';
    return '';
}
/** بيلف على آخر المنشورات اللي عليها تعليقات ويخفي المطابق. بيرجع عدد اللي اتخفى */
function cm_autohide_tick($z, int $maxPosts = 8): int
{
    $c = cm_spam_cfg(); if (empty($c['on'])) return 0;
    $wid = (int)ws('id'); $done = json_decode((string)setting('cm_spam_done_' . $wid, '[]'), true) ?: []; $n = 0;
    $r = $z->commentedPosts(['limit' => $maxPosts, 'profileId' => zernio_profile()]);
    foreach ((array)($r['data'] ?? []) as $p) {
        $cs = (array)($z->comments((string)$p['id'], (string)$p['accountId'])['comments'] ?? []);
        $walk = function ($list) use (&$walk, $z, $p, $c, &$done, &$n) {
            foreach ($list as $cm) {
                $id = (string)($cm['id'] ?? '');
                if ($id !== '' && empty($cm['from']['isOwner']) && empty($cm['isHidden']) && !in_array($id, $done, true) && ($why = cm_is_bad((string)($cm['message'] ?? ''), $c)) !== '') {
                    try { $z->hideComment((string)$p['id'], $id, (string)$p['accountId']); $n++; log_activity('comment.autohide', $id . ' ' . $why); } catch (Throwable $e) {}
                    $done[] = $id;
                }
                $walk((array)($cm['replies'] ?? []));
            }
        };
        $walk($cs);
    }
    save_setting('cm_spam_done_' . $wid, json_encode(array_slice($done, -2000)));
    return $n;
}

/* ---------- #40 ردود محفوظة لكل منشور ---------- */
function post_replies(string $postId): array
{
    return json_decode((string)setting('post_rep_' . (int)ws('id') . '_' . md5($postId), '[]'), true) ?: [];
}
function post_replies_save(string $postId, array $list): void
{
    $list = array_values(array_slice(array_filter(array_map(function ($x) { return mb_substr(trim((string)$x), 0, 1000); }, $list), 'strlen'), 0, 12));
    save_setting('post_rep_' . (int)ws('id') . '_' . md5($postId), json_encode($list, JSON_UNESCAPED_UNICODE));
}

/* ---------- #41 أكتر الناس تفاعلًا ---------- */
function cm_fans(array $commentsByPost): array
{
    $by = [];
    foreach ($commentsByPost as $pk => $list) {
        $walk = function ($l) use (&$walk, &$by, $pk) {
            foreach ($l as $c) {
                $f = (array)($c['from'] ?? []);
                if (empty($f['isOwner'])) {
                    $k = (string)($f['id'] ?? $f['username'] ?? $f['name'] ?? ''); if ($k === '') continue;
                    $by[$k] = $by[$k] ?? ['key' => $k, 'name' => (string)($f['name'] ?? $f['username'] ?? ''), 'pic' => (string)($f['picture'] ?? ''), 'n' => 0, 'posts' => [], 'price' => 0, 'last' => '', 'pf' => (string)($c['platform'] ?? '')];
                    $by[$k]['n']++; $by[$k]['posts'][$pk] = 1; if (cm_is_price_q((string)($c['message'] ?? ''))) $by[$k]['price']++;
                    if ((string)($c['message'] ?? '') !== '') $by[$k]['last'] = mb_substr((string)$c['message'], 0, 200);
                }
                $walk((array)($c['replies'] ?? []));
            }
        };
        $walk($list);
    }
    foreach ($by as &$x) { $x['posts'] = count($x['posts']); $x['score'] = $x['n'] + $x['posts'] * 2 + $x['price'] * 3; } unset($x);
    usort($by, function ($a, $b) { return $b['score'] <=> $a['score']; });
    return array_slice($by, 0, 30);
}

/* ---------- #45 كوبونات متتبعة ---------- */
function coupon_list(): array
{
    try { $st = db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM crm_leads l WHERE l.workspace_id = c.workspace_id AND l.coupon = c.code) leads, (SELECT COUNT(*) FROM crm_leads l WHERE l.workspace_id = c.workspace_id AND l.coupon = c.code AND l.stage = \'won\') won FROM post_coupons c WHERE c.workspace_id = ? ORDER BY c.id DESC LIMIT 50'); $st->execute([(int)ws('id')]); return $st->fetchAll(); } catch (Throwable $e) { return []; }
}
function coupon_make(string $code, string $label): ?string
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $code)); if ($code === '') $code = 'C' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    $code = substr($code, 0, 24);
    try { db()->prepare('INSERT INTO post_coupons (workspace_id, code, label) VALUES (?, ?, ?)')->execute([(int)ws('id'), $code, mb_substr($label, 0, 160)]); } catch (Throwable $e) { return null; }
    return $code;
}
/** لو النص فيه كود من أكواد الشركة، بيرجع الكود */
function coupon_detect(string $text): string
{
    if ($text === '' || !preg_match_all('/[A-Za-z0-9_-]{3,24}/', $text, $m)) return '';
    static $codes = null;
    if ($codes === null) { try { $st = db()->prepare('SELECT code FROM post_coupons WHERE workspace_id = ?'); $st->execute([(int)ws('id')]); $codes = array_column($st->fetchAll(), 'code'); } catch (Throwable $e) { $codes = []; } }
    foreach ($m[0] as $w) if (in_array(strtoupper($w), $codes, true)) return strtoupper($w);
    return '';
}
function coupon_attach(int $leadId, string $code): void
{
    if ($code === '') return;
    db()->prepare('UPDATE crm_leads SET coupon = ? WHERE id = ? AND workspace_id = ? AND (coupon IS NULL OR coupon = \'\')')->execute([$code, $leadId, (int)ws('id')]);
}

/* ---------- #49 أهداف نشر أسبوعية لكل حساب ---------- */
function post_goals(): array
{
    $g = json_decode((string)setting('post_goals_' . (int)ws('id'), ''), true) ?: [];
    return array_filter(array_map('intval', $g), function ($n) { return $n > 0; });
}

/* ---------- #51 فورم ذكي: أسئلة تأهيل ---------- */
function cap_budgets(array $cfg): array
{
    $l = array_values(array_filter(array_map('trim', preg_split('/[,،\n]/u', (string)($cfg['budgets'] ?? ''))), 'strlen'));
    return $l ?: [t('أقل من 5,000'), t('5,000 – 20,000'), t('أكتر من 20,000')];
}
function cap_whens(): array
{
    return ['now' => t('دلوقتي / الأسبوع ده'), 'month' => t('خلال شهر'), 'later' => t('بعدين، بستكشف')];
}

/* ---------- #54 الإحالة ---------- */
function cap_ref_link(int $leadId): string
{
    $wid = (int)ws('id'); return crm_form_url() . '&ref=' . rawurlencode(crm_lead_token($wid, $leadId));
}

/* ---------- #52 صفحات الهبوط ---------- */
function lp_list(?int $wid = null): array
{
    return json_decode((string)setting('crm_lp_' . ($wid ?? (int)ws('id')), '[]'), true) ?: [];
}
function lp_url(string $slug): string
{
    $wid = (int)ws('id'); crm_form_url();
    return base_url() . '/index.php?' . http_build_query(['p' => 'lp', 'w' => $wid, 's' => $slug]);
}

/* ---------- #57 لينك واتساب متتبع ---------- */
function cap_wa_link(string $phone, string $msg, string $label): array
{
    $ph = preg_replace('/\D/', '', $phone); if (strlen($ph) < 8) return [];
    $lk = crm_link_make('https://wa.me/' . $ph . '?wa=' . md5($msg . $label), $label);
    $url = 'https://wa.me/' . $ph . '?text=' . rawurlencode(trim($msg) . ' #' . $lk['code']);
    db()->prepare('UPDATE crm_links SET url = ? WHERE workspace_id = ? AND code = ?')->execute([$url, (int)ws('id'), $lk['code']]);
    return ['code' => $lk['code'], 'short' => crm_link_short($lk['code']), 'url' => $url];
}
/** لو الرسالة فيها #كود من الروابط المتتبعة، بيرجعه */
function cap_link_code_in(string $text): string
{
    if (!preg_match_all('/#([A-Za-z0-9]{7})\b/', $text, $m)) return '';
    $st = db()->prepare('SELECT code FROM crm_links WHERE workspace_id = ? AND code = ? LIMIT 1');
    foreach ($m[1] as $c) { $st->execute([(int)ws('id'), $c]); if ($st->fetchColumn()) return $c; }
    return '';
}

/* ---------- #60 Google Sheets (CSV منشور) ---------- */
function cap_sheet_cfg(): array
{
    return (json_decode((string)setting('crm_sheet_' . (int)ws('id'), ''), true) ?: []) + ['url' => '', 'seen' => 0, 'last' => '', 'err' => ''];
}
/** بيسحب الصفوف الجديدة بس (بعد آخر صف اتشاف). بيرجع عدد الـ Leads الجديدة */
function cap_sheet_tick(): int
{
    $c = cap_sheet_cfg(); if ($c['url'] === '') return 0;
    if (!preg_match('~^https://docs\.google\.com/spreadsheets/~', $c['url'])) return 0;
    $u = $c['url'];
    if (preg_match('~/spreadsheets/d/(?:e/)?([\w-]+)~', $u) && strpos($u, 'output=csv') === false && strpos($u, 'format=csv') === false) {
        if (preg_match('~/d/e/([\w-]+)~', $u, $m)) $u = 'https://docs.google.com/spreadsheets/d/e/' . $m[1] . '/pub?output=csv';
        elseif (preg_match('~/d/([\w-]+)~', $u, $m)) $u = 'https://docs.google.com/spreadsheets/d/' . $m[1] . '/export?format=csv' . (preg_match('/gid=(\d+)/', $c['url'], $g) ? '&gid=' . $g[1] : '');
    }
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'follow_location' => 1, 'max_redirects' => 4, 'header' => "User-Agent: AIPush-CRM\r\n"]]);
    $csv = @file_get_contents($u, false, $ctx);
    $save = function ($c) { save_setting('crm_sheet_' . (int)ws('id'), json_encode($c, JSON_UNESCAPED_UNICODE)); };
    if ($csv === false || stripos(substr($csv, 0, 200), '<html') !== false) { $c['err'] = t('مش قادرين نقرا الشيت. اتأكد إنه منشور (File ← Share ← Publish to web ← CSV).'); $save($c); return 0; }
    $n = cap_sheet_import($csv, $c); $save($c);
    return $n;
}
/** بيحوّل CSV لـ Leads من بعد الصف رقم $c['seen'] ويحدّث العداد */
function cap_sheet_import(string $csv, array &$c): int
{
    $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)));
    $head = array_map(function ($h) { return mb_strtolower(preg_replace('/[\s_\-]+/u', '', (string)$h)); }, array_shift($rows) ?: []);
    $col = function (array $keys) use ($head) { foreach ($head as $i => $h) foreach ($keys as $k) if ($h !== '' && mb_strpos($h, $k) !== false) return $i; return null; };
    $ix = ['name' => $col(['name', 'الاسم', 'اسم']), 'phone' => $col(['phone', 'mobile', 'whatsapp', 'رقم', 'موبايل', 'جوال', 'هاتف']), 'email' => $col(['email', 'mail', 'ايميل', 'إيميل', 'البريد']), 'company' => $col(['company', 'شركة', 'الشركة']), 'msg' => $col(['message', 'notes', 'رسالة', 'ملاحظات', 'تفاصيل'])];
    $n = 0; $total = count($rows);
    foreach (array_slice($rows, (int)$c['seen']) as $r) {
        $g = function ($k) use ($r, $ix) { return $ix[$k] === null ? '' : trim((string)($r[$ix[$k]] ?? '')); };
        if ($g('phone') === '' && $g('email') === '') continue;
        $l = crm_lead_upsert(['source' => 'import', 'name' => $g('name') ?: ($g('email') ?: $g('phone')), 'phone' => $g('phone'), 'email' => $g('email'), 'company' => $g('company'), 'owner_id' => null]);
        if ($l && empty($l['merged'])) { $n++; crm_act_add((int)$l['id'], 'note', '📄 Google Sheets' . ($g('msg') !== '' ? ': “' . mb_substr($g('msg'), 0, 500) . '”' : '')); }
    }
    $c['seen'] = $total; $c['last'] = date('Y-m-d H:i:s'); $c['err'] = '';
    return $n;
}

/* ---------- #66 مراحل قابلة للتعديل ---------- */
function crm_stage_palette(): array
{
    // الأسماء الكاملة مكتوبة هنا عشان Tailwind يلقطها
    return ['sky' => 'bg-sky-100 text-sky-700', 'violet' => 'bg-violet-100 text-violet-700', 'amber' => 'bg-amber-100 text-amber-800', 'orange' => 'bg-orange-100 text-orange-800', 'lime' => 'bg-lime-100 text-lime-800', 'ink' => 'bg-ink-100 text-ink-500',
        'rose' => 'bg-rose-100 text-rose-700', 'teal' => 'bg-teal-100 text-teal-700', 'fuchsia' => 'bg-fuchsia-100 text-fuchsia-700', 'cyan' => 'bg-cyan-100 text-cyan-700', 'emerald' => 'bg-emerald-100 text-emerald-700', 'indigo' => 'bg-indigo-100 text-indigo-700'];
}
function crm_stage_cfg(): array
{
    return (json_decode((string)setting('crm_stages_' . (int)ws('id'), ''), true) ?: []) + ['labels' => [], 'colors' => [], 'custom' => []];
}
/** [key => class] لكل المراحل بالترتيب */
function crm_stage_colors(): array
{
    $c = crm_stage_cfg(); $pal = crm_stage_palette();
    $def = ['new' => 'sky', 'contacted' => 'violet', 'qualified' => 'amber', 'proposal' => 'orange', 'won' => 'lime', 'lost' => 'ink'];
    foreach ((array)$c['custom'] as $x) $def[$x['key']] = $x['color'] ?? 'teal';
    $out = []; foreach (crm_stages() as $k => $_) $out[$k] = $pal[$c['colors'][$k] ?? $def[$k] ?? 'teal'] ?? $pal['teal'];
    return $out;
}

/* ---------- #65 أكتر من Pipeline ---------- */
function crm_pipelines(): array
{
    $l = json_decode((string)setting('crm_pipes_' . (int)ws('id'), ''), true) ?: [];
    $o = ['sales' => t('المبيعات')]; foreach ($l as $x) if (!empty($x['key']) && $x['key'] !== 'sales') $o[$x['key']] = $x['label']; elseif (($x['key'] ?? '') === 'sales' && !empty($x['label'])) $o['sales'] = $x['label'];
    return $o;
}

/* ---------- #64 حقول مخصصة ---------- */
function crm_custom_fields(): array
{
    return json_decode((string)setting('crm_fields_' . (int)ws('id'), ''), true) ?: [];
}
function crm_custom_clean(array $in): array
{
    $o = [];
    foreach (crm_custom_fields() as $f) {
        $v = trim((string)($in[$f['key']] ?? '')); if ($v === '') continue;
        if ($f['type'] === 'number') $v = (string)(float)str_replace(',', '.', $v);
        if ($f['type'] === 'select' && !in_array($v, (array)$f['opts'], true)) continue;
        $o[$f['key']] = mb_substr($v, 0, 200);
    }
    return $o;
}

/* ---------- #62 تاجات أوتوماتيك بقواعد ---------- */
function crm_tag_rules(): array
{
    return json_decode((string)setting('crm_tagrules_' . (int)ws('id'), ''), true) ?: [];
}
/** بيرجع التاجات الجديدة اللي اتضافت */
function crm_auto_tag(array $l): array
{
    $rules = crm_tag_rules(); if (!$rules) return [];
    $cf = json_decode((string)($l['custom'] ?? ''), true) ?: [];
    $fields = ['name' => (string)$l['name'], 'company' => (string)$l['company'], 'title' => (string)$l['title'], 'country' => (string)$l['country'], 'note' => (string)$l['note'], 'email' => (string)$l['email'], 'phone' => (string)$l['phone'], 'custom' => implode(' ', $cf)];
    $have = array_filter(array_map('trim', explode(',', (string)$l['tags']))); $add = [];
    foreach ($rules as $r) {
        $w = mb_strtolower(trim((string)($r['has'] ?? ''))); $tg = mb_substr(trim((string)($r['tag'] ?? '')), 0, 30); if ($w === '' || $tg === '') continue;
        $hay = ($r['field'] ?? 'any') === 'any' ? implode(' ', $fields) : ($fields[$r['field']] ?? '');
        if ($r['field'] === 'phone' && preg_match('/^\+?\d+$/', $w)) { $ok = strpos(preg_replace('/\D/', '', $hay), ltrim($w, '+')) === 0; } else $ok = mb_strpos(mb_strtolower($hay), $w) !== false;
        if ($ok && !in_array($tg, $have, true) && !in_array($tg, $add, true)) $add[] = $tg;
    }
    if ($add) db()->prepare('UPDATE crm_leads SET tags = ? WHERE id = ?')->execute([mb_substr(implode(', ', array_slice(array_merge($have, $add), 0, 12)), 0, 255), (int)$l['id']]);
    return $add;
}

/* ---------- #63 دمج يدوي ---------- */
/** $keep يفضل، $drop يتمسح بعد ما كل حاجته تتنقل. $pick: [field => 'keep'|'drop'] */
function crm_merge_leads(int $keep, int $drop, array $pick): bool
{
    $wid = (int)ws('id'); $a = crm_lead($keep); $b = crm_lead($drop); if (!$a || !$b || $keep === $drop) return false;
    $set = []; $vals = [];
    foreach (['name', 'company', 'title', 'email', 'phone', 'linkedin', 'country', 'stage', 'deal_value', 'owner_id', 'note', 'pipeline'] as $f) {
        if (!array_key_exists($f, $a)) continue;
        $use = ($pick[$f] ?? '') === 'drop' ? $b[$f] : ($a[$f] === null || $a[$f] === '' ? $b[$f] : $a[$f]);
        if ($use !== $a[$f]) { $set[] = "$f = ?"; $vals[] = $use; }
    }
    $tags = implode(', ', array_slice(array_unique(array_filter(array_map('trim', array_merge(explode(',', (string)$a['tags']), explode(',', (string)$b['tags']))))), 0, 12)); $set[] = 'tags = ?'; $vals[] = $tags ?: null;
    $cf = (json_decode((string)($b['custom'] ?? ''), true) ?: []) + []; $cf = array_merge($cf, json_decode((string)($a['custom'] ?? ''), true) ?: []);
    if (array_key_exists('custom', $a)) { $set[] = 'custom = ?'; $vals[] = $cf ? json_encode($cf, JSON_UNESCAPED_UNICODE) : null; }
    foreach (['ext_id', 'src_url', 'link_code', 'coupon', 'referred_by', 'q_budget', 'q_when'] as $f) if (array_key_exists($f, $a) && ($a[$f] === null || $a[$f] === '') && $b[$f] !== null && $b[$f] !== '') { $set[] = "$f = ?"; $vals[] = $b[$f]; }
    $pdo = db(); $pdo->beginTransaction();
    try {
        if (array_key_exists('ext_id', $b) && $b['ext_id']) $pdo->prepare('UPDATE crm_leads SET ext_id = NULL WHERE id = ?')->execute([$drop]); // unique لو موجود
        $vals[] = $keep; $pdo->prepare('UPDATE crm_leads SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
        foreach (['crm_acts', 'crm_deals', 'crm_plan_runs', 'crm_bookings', 'crm_visits', 'crm_mails', 'crm_notifs'] as $tb) { try { $pdo->prepare("UPDATE $tb SET lead_id = ? WHERE lead_id = ? AND workspace_id = ?")->execute([$keep, $drop, $wid]); } catch (Throwable $e) {} }
        $pdo->prepare('UPDATE crm_leads SET referred_by = ? WHERE referred_by = ? AND workspace_id = ?')->execute([$keep, $drop, $wid]);
        $pdo->prepare('DELETE FROM crm_leads WHERE id = ? AND workspace_id = ?')->execute([$drop, $wid]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    crm_act_add($keep, 'note', '🔗 ' . t('اتدمج معاه: %s', (string)$b['name']));
    if (function_exists('crm_deals_rollup')) crm_deals_rollup($keep);
    return true;
}

/* ---------- #78 #79 #80 قواعد خطط المتابعة ---------- */
function crm_plan_rules(): array
{
    $r = (json_decode((string)setting('crm_plan_rules_' . (int)ws('id'), ''), true) ?: []) + ['hours' => 0, 'days' => [0, 1, 2, 3, 4], 'from' => '09:00', 'to' => '18:00', 'stop_reply' => 0, 'na_n' => 0, 'na_plan' => 0];
    $r['days'] = array_map('intval', (array)$r['days']);
    return $r;
}
/** لو دلوقتي برّه ساعات الشغل: أول ميعاد شغل جاي (بتوقيت السيرفر). جوّه الساعات: null */
function crm_plan_next_open(?int $ts = null): ?string
{
    $r = crm_plan_rules(); if (empty($r['hours']) || !$r['days']) return null;
    $tz = new DateTimeZone(site_timezone()); $d = new DateTime('@' . ($ts ?? time())); $d->setTimezone($tz);
    $in = function (DateTime $x) use ($r) { $hm = $x->format('H:i'); return in_array((int)$x->format('w'), $r['days'], true) && $hm >= $r['from'] && $hm < $r['to']; };
    if ($in($d)) return null;
    for ($i = 0; $i < 8; $i++) {
        $c = (clone $d)->modify("+$i day"); [$h, $m] = array_map('intval', explode(':', $r['from'])); $c->setTime($h, $m);
        if ($c > $d && in_array((int)$c->format('w'), $r['days'], true)) return $c->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }
    return null;
}
/** #80 العميل رد: وقّف خطته وخلّي الموظف يرد */
function crm_plan_on_reply(int $leadId, string $where): bool
{
    $r = crm_plan_rules(); if (empty($r['stop_reply'])) return false;
    $st = db()->prepare("SELECT id FROM crm_plan_runs WHERE workspace_id = ? AND lead_id = ? AND state IN ('wait','due') AND started_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE) LIMIT 1"); $st->execute([(int)ws('id'), $leadId]);
    if (!$st->fetchColumn()) return false;
    crm_plan_stop($leadId);
    db()->prepare('UPDATE crm_leads SET next_at = NOW(), next_note = ? WHERE id = ?')->execute([mb_substr('💬 ' . t('العميل رد (%s)، الخطة وقفت. رد عليه.', $where), 0, 255), $leadId]);
    return true;
}
/** #78 بعد نتيجة مكالمة */
function crm_on_call_outcome(array $l, string $oc): string
{
    if ($oc === 'answered') { db()->prepare('UPDATE crm_leads SET no_answer = 0 WHERE id = ?')->execute([(int)$l['id']]); return ''; }
    if ($oc !== 'no_answer') return '';
    $n = (int)($l['no_answer'] ?? 0) + 1; db()->prepare('UPDATE crm_leads SET no_answer = ? WHERE id = ?')->execute([$n, (int)$l['id']]);
    $r = crm_plan_rules(); if ($r['na_n'] < 1 || $n < $r['na_n'] || !$r['na_plan'] || !($p = crm_plan((int)$r['na_plan']))) return '';
    db()->prepare("UPDATE crm_plan_runs SET state = 'stopped' WHERE workspace_id = ? AND lead_id = ? AND state IN ('wait','due')")->execute([(int)ws('id'), (int)$l['id']]);
    db()->prepare('UPDATE crm_leads SET no_answer = 0 WHERE id = ?')->execute([(int)$l['id']]);
    crm_act_add((int)$l['id'], 'note', '🔁 ' . t('مردّش %s مرات، اتنقل لخطة: %s', $n, $p['name']));
    crm_plan_start((int)$l['id'], (int)$p['id']);
    return $p['name'];
}


/* ---------- #83 مهام مستقلة (أو مربوطة بعميل) ---------- */
function crm_tasks_open(int $uid, int $limit = 30): array
{
    $st = db()->prepare("SELECT t.*, l.name lead_name FROM crm_tasks t LEFT JOIN crm_leads l ON l.id = t.lead_id WHERE t.workspace_id = ? AND (t.user_id = ? OR t.user_id IS NULL) AND t.done_at IS NULL ORDER BY (t.due_at IS NULL), t.due_at, t.id DESC LIMIT " . (int)$limit);
    $st->execute([(int)ws('id'), $uid]);
    return array_map(function ($r) { return ['id' => (int)$r['id'], 'title' => $r['title'], 'due' => $r['due_at'] ? fmt_date($r['due_at'], 'Y-m-d\TH:i') : '', 'lead' => (int)$r['lead_id'], 'lead_name' => (string)($r['lead_name'] ?? '')]; }, $st->fetchAll());
}

/* ---------- #84 تذكير يومي بالإيميل لكل موظف بمتابعاته ومهامه ---------- */
function crm_daily_digest(): int
{
    $wid = (int)ws('id'); if (!function_exists('smtp_config') || !smtp_config($wid)) return 0;
    $tz = new DateTimeZone(site_timezone()); $now = new DateTime('now', $tz);
    if ((int)$now->format('G') < 8) return 0; // بيتبعت الصبح بس
    $day = $now->format('Y-m-d'); if (setting('crm_digest_day_' . $wid, '') === $day) return 0;
    save_setting('crm_digest_day_' . $wid, $day);
    $end = (clone $now)->setTime(23, 59, 59)->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    $n = 0;
    foreach (crm_members() as $m) {
        $uid = (int)$m['id']; if (empty($m['email'])) continue;
        $st = db()->prepare("SELECT id, name, next_note, next_at FROM crm_leads WHERE workspace_id = ? AND owner_id = ? AND next_at IS NOT NULL AND next_at <= ? AND stage NOT IN ('won','lost') ORDER BY next_at LIMIT 30");
        $st->execute([$wid, $uid, $end]); $leads = $st->fetchAll();
        $st = db()->prepare("SELECT title, due_at FROM crm_tasks WHERE workspace_id = ? AND user_id = ? AND done_at IS NULL AND (due_at IS NULL OR due_at <= ?) ORDER BY due_at LIMIT 30");
        $st->execute([$wid, $uid, $end]); $tasks = $st->fetchAll();
        if (!$leads && !$tasks) continue;
        $li = ''; foreach ($leads as $l) $li .= '<li><a href="' . e(url('crm_leads') . '#lead-' . (int)$l['id']) . '">' . e($l['name']) . '</a>' . ($l['next_note'] ? ' — ' . e($l['next_note']) : '') . ' <small style="color:#64748b">' . e(fmt_date($l['next_at'], 'h:i A')) . '</small></li>';
        $ti = ''; foreach ($tasks as $t) $ti .= '<li>' . e($t['title']) . ($t['due_at'] ? ' <small style="color:#64748b">' . e(fmt_date($t['due_at'], 'd/m h:i A')) . '</small>' : '') . '</li>';
        $html = '<div style="font-family:Tahoma,Arial,sans-serif;font-size:14px;line-height:1.8" dir="' . (is_rtl() ? 'rtl' : 'ltr') . '"><p>' . e(t('صباح الخير %s، ده شغلك النهارده:', preg_split('/\s+/u', (string)$m['name'])[0] ?? '')) . '</p>'
            . ($li ? '<b>' . e(t('متابعات العملاء (%s)', count($leads))) . '</b><ul>' . $li . '</ul>' : '') . ($ti ? '<b>' . e(t('مهام (%s)', count($tasks))) . '</b><ul>' . $ti . '</ul>' : '')
            . '<p><a href="' . e(url('crm_leads') . '#due') . '" style="display:inline-block;background:#2557e0;color:#fff;padding:8px 16px;border-radius:8px;text-decoration:none">' . e(t('افتح شغل النهارده')) . '</a></p></div>';
        try { send_mail((string)$m['email'], t('شغلك النهارده: %s متابعة و%s مهمة', count($leads), count($tasks)), $html, null, $wid); $n++; } catch (Throwable $e) {}
    }
    return $n;
}

/* ---------- #88 منشن @ في ملاحظات العميل ---------- */
function crm_mentions(string $body, int $leadId): int
{
    if (strpos($body, '@') === false) return 0; $n = 0; $me = (int)(current_user()['id'] ?? 0);
    $lead = crm_lead($leadId);
    foreach (crm_members() as $m) {
        $first = preg_split('/\s+/u', trim((string)$m['name']))[0] ?? ''; if ($first === '' || (int)$m['id'] === $me) continue;
        if (preg_match('/@' . preg_quote($first, '/') . '(?![\p{L}\d_])/u', $body)) { crm_notify((int)$m['id'], $leadId, 'mention', t('%s عمل لك منشن على %s: %s', (string)(current_user()['name'] ?? ''), (string)($lead['name'] ?? ''), mb_substr($body, 0, 160))); $n++; }
    }
    return $n;
}

/* ---------- #92 #93 الإعلانات ← العملاء: تكلفة العميل والعائد لكل حملة ---------- */
function crm_report_campaigns(string $from, string $to): array
{
    $wid = (int)ws('id'); $camps = []; $err = '';
    $fxd = getenv('UI_FIXTURES');
    try {
        if ($fxd && is_file($fxd . '/ads.json')) $camps = (array)(json_decode((string)file_get_contents($fxd . '/ads.json'), true)['campaigns'] ?? []);
        elseif (module_enabled('ads') && ($z = zernio_or_null())) $camps = (array)(ads_campaigns($z, ['fromDate' => substr($from, 0, 10), 'toDate' => substr($to, 0, 10), 'limit' => 100])['campaigns'] ?? []);
    } catch (Throwable $e) { $err = $e->getMessage(); }
    $st = db()->prepare("SELECT ad_campaign c, COUNT(*) n, SUM(stage = 'won') w, COALESCE(SUM(CASE WHEN stage = 'won' THEN deal_value END), 0) v FROM crm_leads WHERE workspace_id = ? AND ad_campaign IS NOT NULL AND created_at BETWEEN ? AND ? GROUP BY ad_campaign");
    $st->execute([$wid, $from, $to]); $by = []; foreach ($st->fetchAll() as $r) $by[(string)$r['c']] = $r;
    $out = [];
    foreach ($camps as $c) {
        $id = (string)($c['platformCampaignId'] ?? $c['id'] ?? ''); $sp = (float)($c['metrics']['spend'] ?? $c['spend'] ?? 0); $l = $by[$id] ?? ['n' => 0, 'w' => 0, 'v' => 0];
        if ($sp <= 0 && !(int)$l['n']) continue;
        $cb = (array)($c['campaignBudget'] ?? []);
        $out[] = ['id' => $id, 'name' => (string)($c['campaignName'] ?? $c['name'] ?? $id), 'pf' => (string)($c['platform'] ?? ''), 'cur' => (string)($c['currency'] ?? ''), 'spend' => $sp, 'leads' => (int)$l['n'], 'won' => (int)$l['w'], 'value' => (float)$l['v'],
            'status' => (string)($c['status'] ?? ''), 'budget' => (float)($cb['amount'] ?? 0), 'btype' => (string)($cb['type'] ?? ''),
            'cpl' => (int)$l['n'] ? $sp / (int)$l['n'] : null, 'cpw' => (int)$l['w'] ? $sp / (int)$l['w'] : null, 'roi' => $sp > 0 ? ((float)$l['v'] - $sp) / $sp * 100 : null];
    }
    usort($out, function ($a, $b) { return $b['spend'] <=> $a['spend']; });
    return ['rows' => $out, 'err' => $err];
}

/* ---------- #94 سرعة الدورة: كام يوم من جديد لحد بقى عميل ---------- */
function crm_cycle(string $from, string $to): array
{
    $st = db()->prepare("SELECT source, DATEDIFF(won_at, created_at) d FROM crm_leads WHERE workspace_id = ? AND stage = 'won' AND won_at BETWEEN ? AND ? AND won_at >= created_at");
    $st->execute([(int)ws('id'), $from, $to]); $all = []; $by = [];
    foreach ($st->fetchAll() as $r) { $all[] = (int)$r['d']; $by[$r['source']][] = (int)$r['d']; }
    $med = function (array $a) { if (!$a) return null; sort($a); $n = count($a); return $n % 2 ? $a[intdiv($n, 2)] : ($a[$n / 2 - 1] + $a[$n / 2]) / 2; };
    $src = []; foreach ($by as $k => $a) $src[] = ['src' => $k, 'n' => count($a), 'avg' => array_sum($a) / count($a), 'med' => $med($a)];
    usort($src, function ($a, $b) { return $a['avg'] <=> $b['avg']; });
    return ['n' => count($all), 'avg' => $all ? array_sum($all) / count($all) : null, 'med' => $med($all), 'min' => $all ? min($all) : null, 'max' => $all ? max($all) : null, 'src' => $src];
}

/* ---------- #97 العمولات ---------- */
function crm_comm_cfg(): array
{
    return (json_decode((string)setting('crm_comm_' . (int)ws('id'), ''), true) ?: []) + ['def' => 0, 'users' => []];
}
function crm_comm_rate(int $uid): float { $c = crm_comm_cfg(); return (float)($c['users'][$uid] ?? $c['def']); }

/* ---------- #100 تقرير شهري تلقائي للمدير بالإيميل (أول يوم في الشهر عن الشهر اللي فات) ---------- */
function crm_monthly_report(bool $force = false): int
{
    $wid = (int)ws('id'); if (!function_exists('smtp_config') || !smtp_config($wid)) return 0;
    $tz = new DateTimeZone(site_timezone()); $now = new DateTime('now', $tz); $key = $now->format('Y-m');
    if (!$force && ((int)$now->format('j') !== 1 || (int)$now->format('G') < 8 || setting('crm_monthly_' . $wid, '') === $key)) return 0;
    save_setting('crm_monthly_' . $wid, $key);
    [$from, $to] = crm_period('last_month'); $rows = crm_team_report($from, $to);
    $tot = ['leads' => 0, 'won' => 0, 'value' => 0, 'contacts' => 0]; foreach ($rows as $r) foreach ($tot as $k => $_) $tot[$k] += $r[$k];
    $cy = crm_cycle($from, $to); $cp = crm_report_campaigns($from, $to)['rows'];
    usort($rows, function ($a, $b) { return $b['value'] <=> $a['value'] ?: $b['won'] <=> $a['won']; });
    $mName = (new DateTime('first day of last month', $tz))->format('m/Y');
    $tr = ''; foreach (array_slice($rows, 0, 10) as $i => $r) $tr .= '<tr><td>' . ($i + 1) . '</td><td>' . e($r['name']) . '</td><td>' . (int)$r['leads'] . '</td><td>' . (int)$r['contacts'] . '</td><td>' . (int)$r['won'] . '</td><td>' . e(number_format($r['value'])) . '</td></tr>';
    $ctr = ''; foreach (array_slice($cp, 0, 8) as $c) $ctr .= '<tr><td>' . e($c['name']) . '</td><td>' . e(number_format($c['spend'], 2)) . '</td><td>' . $c['leads'] . '</td><td>' . ($c['cpl'] !== null ? e(number_format($c['cpl'], 2)) : '—') . '</td><td>' . ($c['roi'] !== null ? round($c['roi']) . '%' : '—') . '</td></tr>';
    $th = 'style="text-align:start;padding:6px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:600"';
    $html = '<div style="font-family:Tahoma,Arial,sans-serif;font-size:14px;line-height:1.7;color:#0f172a" dir="' . (is_rtl() ? 'rtl' : 'ltr') . '"><h2 style="margin:0 0 8px">' . e(t('تقرير المبيعات لشهر %s', $mName)) . '</h2>'
        . '<p>' . e(t('عملاء جداد: %s · بقوا عملاء: %s · قيمة المبيعات: %s · مرات التواصل: %s', $tot['leads'], $tot['won'], number_format($tot['value']), $tot['contacts'])) . ($cy['avg'] !== null ? '<br>' . e(t('متوسط الدورة: %s يوم من أول تواصل لحد البيع', round($cy['avg'], 1))) : '') . '</p>'
        . ($tr ? '<h3>' . e(t('الفريق')) . '</h3><table style="border-collapse:collapse;width:100%"><tr><th ' . $th . '>#</th><th ' . $th . '>' . e(t('الموظف')) . '</th><th ' . $th . '>' . e(t('عملاء')) . '</th><th ' . $th . '>' . e(t('تواصل')) . '</th><th ' . $th . '>' . e(t('بقوا عملاء')) . '</th><th ' . $th . '>' . e(t('القيمة')) . '</th></tr>' . $tr . '</table>' : '')
        . ($ctr ? '<h3>' . e(t('الحملات الإعلانية')) . '</h3><table style="border-collapse:collapse;width:100%"><tr><th ' . $th . '>' . e(t('الحملة')) . '</th><th ' . $th . '>' . e(t('الصرف')) . '</th><th ' . $th . '>' . e(t('عملاء')) . '</th><th ' . $th . '>' . e(t('تكلفة العميل')) . '</th><th ' . $th . '>' . e(t('العائد')) . '</th></tr>' . $ctr . '</table>' : '')
        . '<p><a href="' . e(url('crm_reports', ['period' => 'last_month'])) . '">' . e(t('افتح التقرير الكامل')) . '</a></p></div>';
    $n = 0;
    foreach (crm_members() as $m) if ((($m['role'] ?? '') === 'owner' || (function_exists('role_permissions') && in_array('crm.manage', role_permissions((string)($m['role'] ?? '')), true))) && !empty($m['email'])) { try { send_mail((string)$m['email'], t('تقرير المبيعات لشهر %s', $mName), $html, null, $wid); $n++; } catch (Throwable $e) {} }
    return $n;
}


/* ---------- نقل الميزانية من الحملات الخسرانة للكسبانة (قواعد ثابتة، والتنفيذ بموافقتك) ---------- */
function crm_bshift_cfg(): array
{
    return (json_decode((string)setting('crm_bshift_' . (int)ws('id'), ''), true) ?: []) + ['step' => 20, 'min_spend' => 50, 'win_roi' => 30, 'lose_roi' => -50, 'cap' => 0];
}
/** بيرجع [changes, notes]. change: id, pf, name, cur, from, to, why */
function crm_bshift_plan(array $rows, ?array $cfg = null): array
{
    $c = $cfg ?? crm_bshift_cfg(); $step = max(5, min(50, (float)$c['step'])) / 100; $min = max(0, (float)$c['min_spend']);
    $changes = []; $notes = [];
    $by = []; foreach ($rows as $r) $by[$r['cur'] ?: '-'][] = $r;
    foreach ($by as $cur => $list) {
        $ok = array_values(array_filter($list, function ($r) { return $r['btype'] === 'daily' && $r['budget'] > 0 && in_array(strtolower($r['status']), ['active', 'enabled', ''], true); }));
        $losers = []; $winners = [];
        foreach ($ok as $r) {
            if ($r['spend'] < $min) { $notes[] = ['name' => $r['name'], 'why' => t('لسه صارفة %s بس، محتاجة بيانات أكتر قبل ما نحكم عليها', number_format($r['spend'], 2))]; continue; }
            if ($r['leads'] === 0 || ($r['won'] === 0 && $r['roi'] !== null && $r['roi'] <= (float)$c['lose_roi'])) $losers[] = $r;
            elseif ($r['won'] >= 1 && $r['roi'] !== null && $r['roi'] >= (float)$c['win_roi']) $winners[] = $r;
        }
        foreach (array_filter($list, function ($r) { return $r['btype'] !== 'daily' || $r['budget'] <= 0; }) as $r) if ($r['spend'] >= $min) $notes[] = ['name' => $r['name'], 'why' => t('الميزانية مش على مستوى الحملة (أو مش يومية)، فمش بنعدّلها من هنا')];
        if (!$losers || !$winners) { if ($losers && !$winners) foreach ($losers as $r) $notes[] = ['name' => $r['name'], 'why' => t('حملة خسرانة، بس مفيش حملة كسبانة بنفس العملة ننقل لها. فكّر توقفها من صفحة الإعلانات.')]; continue; }
        $freed = 0;
        foreach ($losers as $r) { $to = max(1, round($r['budget'] * (1 - $step), 2)); $freed += $r['budget'] - $to;
            $changes[] = ['id' => $r['id'], 'pf' => $r['pf'], 'name' => $r['name'], 'cur' => $cur, 'from' => $r['budget'], 'to' => $to, 'why' => $r['leads'] === 0 ? t('صرفت %s ومجابتش ولا عميل', number_format($r['spend'], 2)) : t('العائد %s%% ومحدش اشترى', round($r['roi']))]; }
        $wsum = array_sum(array_map(function ($r) { return max(1, $r['roi']); }, $winners));
        foreach ($winners as $r) { $add = min($r['budget'] * $step, $freed * max(1, $r['roi']) / $wsum); if ($add < 0.5) continue;
            $changes[] = ['id' => $r['id'], 'pf' => $r['pf'], 'name' => $r['name'], 'cur' => $cur, 'from' => $r['budget'], 'to' => round($r['budget'] + $add, 2), 'why' => t('العائد +%s%% و%s بيعة، تكلفة العميل %s', round($r['roi']), $r['won'], $r['cpl'] !== null ? number_format($r['cpl'], 2) : '—')]; }
        // سقف إجمالي الميزانية اليومية (لو محدد): الزيادة بتتقص لحد ما الإجمالي مايعديش السقف
        if ((float)$c['cap'] > 0) {
            $total = array_sum(array_map(function ($r) { return $r['budget']; }, $ok)); foreach ($changes as $ch) if ($ch['cur'] === $cur) $total += $ch['to'] - $ch['from'];
            if ($total > (float)$c['cap']) { $over = $total - (float)$c['cap']; foreach ($changes as &$ch) if ($ch['cur'] === $cur && $ch['to'] > $ch['from'] && $over > 0) { $cut = min($over, $ch['to'] - $ch['from']); $ch['to'] = round($ch['to'] - $cut, 2); $over -= $cut; } unset($ch); }
        }
    }
    return [array_values(array_filter($changes, function ($ch) { return abs($ch['to'] - $ch['from']) >= 0.5; })), $notes];
}
function crm_bshift_log(): array { return json_decode((string)setting('crm_bshift_log_' . (int)ws('id'), '[]'), true) ?: []; }
/** تنفيذ التغييرات (أو التراجع). في وضع الاختبار (UI_FIXTURES) مابيكلمش Zernio */
function crm_bshift_apply(array $changes, string $kind = 'apply'): array
{
    $ok = []; $fail = []; $fxd = getenv('UI_FIXTURES'); $z = $fxd ? null : zernio_or_null();
    foreach ($changes as $ch) {
        try {
            if ($fxd) file_put_contents($fxd . '/../budget_calls.log', date('c') . ' PUT ' . $ch['id'] . ' ' . $ch['pf'] . ' ' . $ch['to'] . "\n", FILE_APPEND);
            elseif ($z) ads_update_campaign_budget($z, (string)$ch['id'], (string)$ch['pf'], (float)$ch['to'], 'daily');
            else throw new Exception(t('اربط Zernio الأول.'));
            $ok[] = $ch; log_activity('ads.budget_shift', $ch['id'] . ' ' . $ch['from'] . '->' . $ch['to']);
        } catch (Throwable $e) { $fail[] = $ch['name'] . ': ' . $e->getMessage(); }
    }
    if ($ok) { $log = crm_bshift_log(); array_unshift($log, ['at' => date('Y-m-d H:i:s'), 'by' => (string)(current_user()['name'] ?? ''), 'kind' => $kind, 'items' => $ok]); save_setting('crm_bshift_log_' . (int)ws('id'), json_encode(array_slice($log, 0, 20), JSON_UNESCAPED_UNICODE)); }
    return [$ok, $fail];
}
