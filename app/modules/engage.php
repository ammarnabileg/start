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
