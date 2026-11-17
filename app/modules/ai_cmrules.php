<?php
/**
 * ردود تلقائية بالذكاء الاصطناعي على التعليقات (بدل النص الثابت).
 * أتمتة Zernio بتقبل نص ثابت بس، فالقاعدة دي بتتخزن عندنا والمنصة بتشغلها:
 * بتقرا التعليقات الجديدة على منشورات الحساب، وترد رد عام من قاعدة معرفة الشركة (نفس مولّد «الرد الذكي»).
 * الجديد بس: أي تعليق اتكتب قبل تشغيل القاعدة مابيترّدش عليه. وكل تعليق بيترد عليه مرة واحدة (سجل مشترك مع الرد الذكي).
 */

function acr_all(int $wid): array
{
    $r = json_decode((string)setting('acr_' . $wid, ''), true);
    return is_array($r) ? array_values($r) : [];
}

function acr_save(int $wid, array $rules): void
{
    save_setting('acr_' . $wid, json_encode(array_values($rules), JSON_UNESCAPED_UNICODE));
}

function acr_get(int $wid, string $id): ?array
{
    foreach (acr_all($wid) as $r) if (($r['id'] ?? '') === $id) return $r;
    return null;
}

function acr_put(int $wid, array $rule): void
{
    $all = acr_all($wid); $hit = false;
    foreach ($all as $i => $r) if (($r['id'] ?? '') === $rule['id']) { $all[$i] = $rule; $hit = true; }
    if (!$hit) $all[] = $rule;
    acr_save($wid, $all);
}

function acr_delete(int $wid, string $id): void
{
    acr_save($wid, array_filter(acr_all($wid), function ($r) use ($id) { return ($r['id'] ?? '') !== $id; }));
}

function acr_is_id(string $id): bool { return strncmp($id, 'ai_', 3) === 0; }

/** نفس شكل أتمتة Zernio عشان تتعرض في نفس القائمة */
function acr_as_item(array $r): array
{
    return ['id' => $r['id'], '_id' => $r['id'], 'ai' => 1, 'name' => (string)$r['name'], 'platform' => (string)$r['plat'], 'accountId' => (string)$r['acc'],
        'accountName' => (string)($r['acc_name'] ?? ''), 'platformPostId' => (string)($r['post'] ?? ''), 'keywords' => (array)($r['kw'] ?? []), 'matchMode' => (string)($r['match'] ?? 'contains'),
        'isActive' => !empty($r['on']), 'trigger' => 'comment', 'commentReply' => '', 'instructions' => (string)($r['ins'] ?? ''),
        'stats' => ['triggered' => (int)($r['n'] ?? 0), 'dmsSent' => 0, 'dmsFailed' => 0], 'recentLogs' => array_reverse((array)($r['log'] ?? []))];
}

function acr_match(array $r, string $tx): bool
{
    $kw = array_filter(array_map('trim', (array)($r['kw'] ?? []))); if (!$kw) return true; // أي تعليق
    $t = mb_strtolower($tx); $m = (string)($r['match'] ?? 'contains');
    foreach ($kw as $k) {
        $k = mb_strtolower($k);
        if ($m === 'exact' && trim($t) === $k) return true;
        if ($m === 'word' && preg_match('/(^|[^\p{L}\p{N}])' . preg_quote($k, '/') . '([^\p{L}\p{N}]|$)/u', $t)) return true;
        if ($m === 'contains' && mb_strpos($t, $k) !== false) return true;
    }
    return false;
}

/** التشغيل: لحد $max رد في المرة (اللي فاضل بيتعمل في المرة الجاية) */
function acr_tick(int $wid, Zernio $z, int $max = 15): int
{
    $rules = array_values(array_filter(acr_all($wid), function ($r) { return !empty($r['on']); }));
    if (!$rules || !function_exists('ai_decide') || !function_exists('ai_reply_cfg')) return 0;
    $cfg = ai_reply_cfg($wid); $n = 0;
    $done = json_decode((string)setting('ai_replied_' . $wid, ''), true) ?: [];
    try { $posts = $z->commentedPosts(['limit' => 15, 'profileId' => zernio_profile()])['data'] ?? []; } catch (Throwable $e) { return 0; }
    $changed = [];
    foreach ($rules as $rule) {
        foreach ($posts as $p) {
            if ($n >= $max) break 2;
            $pid = (string)($p['id'] ?? ''); $acc = (string)($p['accountId'] ?? '');
            if ($pid === '' || $acc !== (string)$rule['acc']) continue;
            if (!empty($rule['post']) && !in_array((string)$rule['post'], [(string)($p['platformPostId'] ?? ''), $pid], true)) continue;
            try { $list = $z->comments($pid, $acc); } catch (Throwable $e) { continue; }
            foreach ((array)($list['comments'] ?? $list['data'] ?? []) as $c) {
                if ($n >= $max) break;
                $cid = (string)($c['id'] ?? ''); $tx = trim((string)($c['message'] ?? ''));
                if ($cid === '' || $tx === '' || !empty($c['from']['isOwner']) || !($c['canReply'] ?? true)) continue;
                $k = 'cm:' . $cid; if (isset($done[$k])) continue;
                // الجديد بس: التعليق لازم يكون اتكتب بعد ما القاعدة اتشغلت
                $ts = function_exists('ai_ts') ? (int)ai_ts($c['createdTime'] ?? $c['createdAt'] ?? null) : (int)strtotime((string)($c['createdTime'] ?? $c['createdAt'] ?? ''));
                if (!$ts || $ts < (int)($rule['since'] ?? time())) continue; // قديم على القاعدة دي بس، ممكن قاعدة تانية ترد
                if (array_filter((array)($c['replies'] ?? []), function ($r) { return !empty($r['from']['isOwner']); })) { $done[$k] = 1; continue; }
                if (!acr_match($rule, $tx)) continue;
                try {
                    $hist = trim((string)($rule['ins'] ?? '')) !== '' ? t('تعليمات صاحب الصفحة للرد: %s', (string)$rule['ins']) : '';
                    $d = ai_decide($wid, $cfg, $tx, 'comment', $hist);
                    $rt = function_exists('ai_sign') ? ai_sign($cfg, trim((string)$d['reply'], " \n\"'«»")) : trim((string)$d['reply']);
                    if (($d['action'] ?? '') === 'handoff' || $rt === '') { $done[$k] = 1; $rule['log'][] = ['at' => time(), 'who' => (string)($c['from']['name'] ?? ''), 'in' => mb_substr($tx, 0, 200), 'out' => '', 'st' => 'handoff']; $changed[$rule['id']] = $rule; continue; }
                    $z->replyComment($pid, $acc, $rt, $cid); $done[$k] = 1;
                    if (function_exists('ai_sent_mark')) ai_sent_mark($wid, $rt, 'p:' . $pid);
                    log_activity('ai.auto_comment', $cid);
                    $rule['n'] = (int)($rule['n'] ?? 0) + 1;
                    $rule['log'][] = ['at' => time(), 'who' => (string)($c['from']['name'] ?? ''), 'in' => mb_substr($tx, 0, 200), 'out' => mb_substr($rt, 0, 300), 'st' => 'sent'];
                    $rule['log'] = array_slice((array)$rule['log'], -30);
                    $changed[$rule['id']] = $rule; $n++;
                } catch (Throwable $e) { error_log('acr: ' . $e->getMessage()); $fk = 'cf:' . $cid; $done[$fk] = (int)($done[$fk] ?? 0) + 1; if ($done[$fk] >= 3) $done[$k] = 1; if ($done[$fk] > 1) { $fails = ($fails ?? 0) + 1; if ($fails >= 3) break 3; continue; } $rule['log'][] = ['at' => time(), 'who' => (string)($c['from']['name'] ?? ''), 'in' => mb_substr($tx, 0, 200), 'out' => mb_substr($e->getMessage(), 0, 200), 'st' => 'failed']; $rule['log'] = array_slice((array)$rule['log'], -30); $changed[$rule['id']] = $rule; $fails = ($fails ?? 0) + 1; if ($fails >= 3) break 3; }
            }
        }
    }
    save_setting('ai_replied_' . $wid, json_encode(array_slice($done, -1500, null, true)));
    if ($changed) { $all = acr_all($wid); foreach ($all as $i => $r) if (isset($changed[$r['id']])) { $cur = $changed[$r['id']]; $cur['on'] = $r['on']; $all[$i] = $cur; } acr_save($wid, $all); }
    return $n;
}
