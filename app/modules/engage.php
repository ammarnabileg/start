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
