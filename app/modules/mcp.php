<?php
/**
 * خادم MCP: يخلي المستخدم يربط تطبيق ChatGPT أو Claude بالمنصة ويشغّل الشغل من هناك.
 * الصفحة p=mcp بتقدّم JSON-RPC (بروتوكول MCP). المصادقة توكن Bearer لكل شركة.
 * الإصدار الأول: أدوات قراءة + إنشاء خطة محتوى (آمن: مفيش نشر ولا صرف إعلانات ولا حذف).
 */

/** ينشئ توكن جديد (يرجّع النص الصريح مرة واحدة فقط؛ نخزّن الهاش بس) */
function mcp_token_create(int $wid, int $uid, string $name, string $scopes = 'read'): string
{
    $plain = 'wtd_' . bin2hex(random_bytes(24));
    db()->prepare('INSERT INTO mcp_tokens (workspace_id, user_id, name, token_hash, scopes) VALUES (?, ?, ?, ?, ?)')
        ->execute([$wid, $uid ?: null, mb_substr(trim($name), 0, 120) ?: 'MCP', hash('sha256', $plain), in_array($scopes, ['read', 'write', 'publish', 'admin'], true) ? $scopes : 'read']);
    return $plain;
}

/** صلاحيات متدرّجة: admin ⊇ publish ⊇ write ⊇ read */
function mcp_scope_allows(string $have, string $need): bool
{
    $rank = ['read' => 1, 'write' => 2, 'publish' => 3, 'admin' => 4];
    return ($rank[$have] ?? 1) >= ($rank[$need] ?? 9);
}

/** يحوّل توكن صريح لصفّه، ويحدّث آخر استخدام. null لو غير صالح */
function mcp_token_resolve(string $plain): ?array
{
    $plain = trim($plain); if ($plain === '') return null;
    $st = db()->prepare('SELECT * FROM mcp_tokens WHERE token_hash = ? LIMIT 1'); $st->execute([hash('sha256', $plain)]);
    $row = $st->fetch(); if (!$row) return null;
    if (ws_closed((int)$row['workspace_id'])) return null;
    try { db()->prepare('UPDATE mcp_tokens SET last_used_at = NOW() WHERE id = ?')->execute([$row['id']]); } catch (Throwable $e) {}
    return $row;
}

function mcp_tokens_list(int $wid): array
{
    $st = db()->prepare('SELECT id, name, scopes, last_used_at, created_at FROM mcp_tokens WHERE workspace_id = ? ORDER BY id DESC'); $st->execute([$wid]);
    return $st->fetchAll();
}
function mcp_token_delete(int $wid, int $id): void { db()->prepare('DELETE FROM mcp_tokens WHERE id = ? AND workspace_id = ?')->execute([$id, $wid]); }

/** يسجّل إجراء بانتظار موافقة المستخدم من داخل المنصة (لا يُنفَّذ تلقائيًا) */
function mcp_action_create(int $wid, ?int $tokenId, string $type, string $summary, array $payload): int
{
    db()->prepare('INSERT INTO mcp_actions (workspace_id, token_id, type, summary, payload) VALUES (?, ?, ?, ?, ?)')
        ->execute([$wid, $tokenId ?: null, $type, mb_substr($summary, 0, 400), json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    return (int)db()->lastInsertId();
}
function mcp_actions_pending(int $wid): array
{
    $st = db()->prepare("SELECT * FROM mcp_actions WHERE workspace_id = ? AND status = 'pending' ORDER BY id DESC"); $st->execute([$wid]); return $st->fetchAll();
}
function mcp_actions_recent(int $wid, int $n = 10): array
{
    $st = db()->prepare("SELECT * FROM mcp_actions WHERE workspace_id = ? AND status <> 'pending' ORDER BY id DESC LIMIT " . max(1, min(50, $n))); $st->execute([$wid]); return $st->fetchAll();
}

/** المستخدم يوافق أو يرفض إجراءً. الموافقة بتنفّذ النشر فعليًا عبر Zernio. */
function mcp_action_resolve(int $wid, int $id, bool $approve): array
{
    $st = db()->prepare("SELECT * FROM mcp_actions WHERE id = ? AND workspace_id = ? AND status = 'pending'"); $st->execute([$id, $wid]);
    $a = $st->fetch(); if (!$a) return ['ok' => false, 'error' => t('الإجراء غير موجود أو تم حسمه.')];
    if (!$approve) { db()->prepare("UPDATE mcp_actions SET status = 'rejected', resolved_at = NOW() WHERE id = ?")->execute([$id]); return ['ok' => true, 'status' => 'rejected']; }
    $payload = json_decode((string)$a['payload'], true) ?: [];
    try {
        $res = mcp_action_execute($wid, (string)$a['type'], $payload);
        db()->prepare("UPDATE mcp_actions SET status = 'done', result = ?, resolved_at = NOW() WHERE id = ?")->execute([mb_substr($res, 0, 600), $id]);
        return ['ok' => true, 'status' => 'done', 'result' => $res];
    } catch (Throwable $e) {
        db()->prepare("UPDATE mcp_actions SET status = 'failed', result = ?, resolved_at = NOW() WHERE id = ?")->execute([mb_substr($e->getMessage(), 0, 600), $id]);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/** ينشر فورًا (بصلاحية publish، بدون موافقة) ويسجّل العملية في سجل audit. */
function mcp_do_publish(int $tokenId, string $type, string $summary, array $payload, callable $text, callable $err): array
{
    $wid = (int)ws('id');
    try {
        $res = mcp_action_execute($wid, $type, $payload);
        db()->prepare("INSERT INTO mcp_actions (workspace_id, token_id, type, summary, payload, status, result, resolved_at) VALUES (?, ?, ?, ?, ?, 'done', ?, NOW())")
            ->execute([$wid, $tokenId ?: null, $type, mb_substr($summary, 0, 400), json_encode($payload, JSON_UNESCAPED_UNICODE), mb_substr($res, 0, 600)]);
        return $text($res);
    } catch (Throwable $e) {
        db()->prepare("INSERT INTO mcp_actions (workspace_id, token_id, type, summary, payload, status, result, resolved_at) VALUES (?, ?, ?, ?, ?, 'failed', ?, NOW())")
            ->execute([$wid, $tokenId ?: null, $type, mb_substr($summary, 0, 400), json_encode($payload, JSON_UNESCAPED_UNICODE), mb_substr($e->getMessage(), 0, 600)]);
        return $err($e->getMessage());
    }
}

/** ينفّذ الإجراء (نشر/جدولة). بيشتغل جوه سياق الشركة. */
function mcp_action_execute(int $wid, string $type, array $payload): string
{
    return ai_as_workspace($wid, function () use ($type, $payload) {
        $z = zernio_or_null($e); if (!$z) throw new RuntimeException(t('اربط مزود النشر أولًا.'));
        $accs = array_values(array_filter(array_map('strval', (array)($payload['accounts'] ?? []))));
        if (!$accs) throw new RuntimeException(t('لم تُحدَّد حسابات للنشر.'));
        $when = in_array($payload['when'] ?? '', ['now', 'queue', 'schedule'], true) ? $payload['when'] : 'schedule';
        if ($type === 'bank_publish') {
            $ids = array_values(array_filter(array_map('intval', (array)($payload['item_ids'] ?? []))));
            if (!$ids || !function_exists('cpl_publish')) throw new RuntimeException(t('عناصر غير صالحة.'));
            $times = (array)($payload['times'] ?? []); $r = cpl_publish($z, $ids, $accs, $when, array_values($times));
            $ok = count(array_filter($r, function ($x) { return !empty($x['ok']); })); return t('تم: %s منشور.', $ok);
        }
        // post: نص حر
        $text = trim((string)($payload['text'] ?? '')); if ($text === '') throw new RuntimeException(t('النص فارغ.'));
        $platforms = array_map(function ($id) { return ['accountId' => (string)$id]; }, $accs);
        $p = ['content' => $text, 'platforms' => $platforms, 'timezone' => site_timezone()];
        if ($when === 'now') $p['publishNow'] = true; elseif ($when === 'queue') $p['queuedFromProfile'] = current_profile_id($z); else { $at = (string)($payload['time'] ?? ''); if (!preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d$/', $at)) throw new RuntimeException(t('موعد غير صحيح.')); $p['scheduledFor'] = $at; }
        $z->createPost($p); return t('تم إرسال المنشور (%s).', $when);
    });
}

/** تعريفات الأدوات (الشكل اللي MCP بيطلبه) */
function mcp_tools(): array
{
    return [
        ['name' => 'list_social_accounts', 'description' => 'List the social media accounts connected to this workspace (platform, name, id).', 'inputSchema' => ['type' => 'object', 'properties' => new stdClass()]],
        ['name' => 'list_posts', 'description' => 'List recent posts with their status (scheduled, published, draft, failed).', 'inputSchema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string', 'enum' => ['', 'scheduled', 'published', 'draft', 'failed'], 'description' => 'Optional status filter'], 'limit' => ['type' => 'integer', 'description' => 'Max posts (1-50)']]]],
        ['name' => 'list_content_bank', 'description' => 'List ready-to-publish posts in the content bank (approved AI content waiting to be scheduled).', 'inputSchema' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]]],
        ['name' => 'get_company_brief', 'description' => 'Get the auto-generated company summary used as context for all AI content (services, audience, brand voice).', 'inputSchema' => ['type' => 'object', 'properties' => new stdClass()]],
        ['name' => 'list_content_plans', 'description' => 'List AI content plans in this workspace with their status and how many posts are ready.', 'inputSchema' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]]],
        ['name' => 'create_content_plan', 'description' => 'Start a new AI content plan. It writes a full plan (topics, captions, image/video prompts) from the company knowledge base. Nothing is published; drafts land in the content bank for review.', 'inputSchema' => ['type' => 'object', 'required' => ['goal'], 'properties' => [
            'goal' => ['type' => 'string', 'description' => 'Campaign goal / what the posts should achieve'],
            'count' => ['type' => 'integer', 'description' => 'Number of posts (default 12)'],
            'language' => ['type' => 'string', 'enum' => ['ar', 'en', 'ar_en', 'en_ar'], 'description' => 'Content language (default ar)'],
            'dialect' => ['type' => 'string', 'description' => 'Arabic dialect/tone, e.g. "saudi", "egyptian", or free text'],
            'audience' => ['type' => 'string', 'description' => 'Target audience (optional)'],
            'tone' => ['type' => 'string', 'description' => 'Tone of voice (optional)'],
        ]]],
        ['name' => 'schedule_post', 'description' => 'Request to publish or schedule a post. REQUIRES a token with "publish" permission, and the action is NOT executed until the workspace owner approves it inside the panel. Returns a pending-approval id.', 'inputSchema' => ['type' => 'object', 'required' => ['text', 'account_ids'], 'properties' => [
            'text' => ['type' => 'string', 'description' => 'Post text'],
            'account_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Target account ids (from list_social_accounts)'],
            'when' => ['type' => 'string', 'enum' => ['now', 'queue', 'schedule'], 'description' => 'now, queue, or schedule (default schedule)'],
            'time' => ['type' => 'string', 'description' => 'For schedule: local time YYYY-MM-DDTHH:MM:SS'],
        ]]],
        ['name' => 'publish_content_bank_item', 'description' => 'Request to publish/schedule a ready item from the content bank by its id. REQUIRES "publish" permission and the workspace owner must approve it in the panel before it runs.', 'inputSchema' => ['type' => 'object', 'required' => ['item_id', 'account_ids'], 'properties' => [
            'item_id' => ['type' => 'integer', 'description' => 'Content bank item id (from list_content_bank)'],
            'account_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
            'when' => ['type' => 'string', 'enum' => ['now', 'queue', 'schedule']],
            'time' => ['type' => 'string', 'description' => 'For schedule: local time YYYY-MM-DDTHH:MM:SS'],
        ]]],
        ['name' => 'launch_ad_campaign', 'description' => 'DANGER — spends real money. Launch a saved ad campaign build live on the platform. REQUIRES an "admin" token. Use list_ad_campaigns to get the id.', 'inputSchema' => ['type' => 'object', 'required' => ['campaign_id'], 'properties' => ['campaign_id' => ['type' => 'integer', 'description' => 'Ad build id']]]],
        ['name' => 'list_ad_campaigns', 'description' => 'List ad campaign builds in this workspace (id, name, platform, state).', 'inputSchema' => ['type' => 'object', 'properties' => new stdClass()]],
        ['name' => 'delete_post', 'description' => 'DANGER — permanent. Delete a scheduled/published post by id. REQUIRES an "admin" token.', 'inputSchema' => ['type' => 'object', 'required' => ['post_id'], 'properties' => ['post_id' => ['type' => 'string']]]],
        ['name' => 'delete_content_plan', 'description' => 'DANGER — permanent. Delete an AI content plan and its unsent drafts by id. REQUIRES an "admin" token.', 'inputSchema' => ['type' => 'object', 'required' => ['plan_id'], 'properties' => ['plan_id' => ['type' => 'integer']]]],
    ];
}

/** ينفّذ أداة ويرجّع نص النتيجة. بيشتغل جوه سياق الشركة (ai_as_workspace). */
function mcp_call(string $name, array $args, string $scopes, int $tokenId = 0): array
{
    $text = function ($s) { return ['content' => [['type' => 'text', 'text' => is_string($s) ? $s : json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]]]; };
    $err = function ($s) { return ['content' => [['type' => 'text', 'text' => $s]], 'isError' => true]; };
    switch ($name) {
        case 'list_social_accounts':
            $z = zernio_or_null($e); if (!$z) return $err('No publishing provider connected.');
            try { $out = []; foreach (acc_filter($z->accounts(zernio_profile())) as $a) $out[] = ['id' => (string)$a['_id'], 'platform' => (string)$a['platform'], 'name' => (string)($a['displayName'] ?? $a['username'] ?? $a['platform'])]; return $text($out); }
            catch (Throwable $ex) { return $err($ex->getMessage()); }
        case 'list_posts':
            $z = zernio_or_null($e); if (!$z) return $err('No publishing provider connected.');
            $q = ['limit' => max(1, min(50, (int)($args['limit'] ?? 20))), 'profileId' => zernio_profile()]; if (!empty($args['status'])) $q['status'] = (string)$args['status'];
            try { $ps = $z->posts($q)['posts'] ?? []; $out = array_map(function ($p) { return ['id' => (string)($p['_id'] ?? ''), 'status' => (string)($p['status'] ?? ''), 'scheduledFor' => (string)($p['scheduledFor'] ?? ''), 'text' => mb_substr((string)($p['content'] ?? ($p['caption'] ?? '')), 0, 200)]; }, $ps); return $text($out); }
            catch (Throwable $ex) { return $err($ex->getMessage()); }
        case 'list_content_bank':
            if (!function_exists('cpl_items')) return $text([]);
            $n = max(1, min(100, (int)($args['limit'] ?? 30))); $out = [];
            foreach (array_slice(cpl_items(0, ['approved']), 0, $n) as $it) $out[] = ['id' => (int)$it['id'], 'title' => (string)(($it['topic'] ?? [])['title'] ?? ''), 'linkedin' => mb_substr((string)$it['linkedin'], 0, 400), 'x' => mb_substr((string)$it['x'], 0, 280), 'media' => count($it['media_ids'] ?? [])];
            return $text($out);
        case 'get_company_brief':
            $wid = (int)ws('id'); $st = db()->prepare("SELECT content FROM kb_files WHERE workspace_id = ? AND method = 'auto_brief' LIMIT 1"); $st->execute([$wid]);
            $b = (string)$st->fetchColumn(); return $text($b !== '' ? $b : 'No company brief yet. Fill in the knowledge base first.');
        case 'list_content_plans':
            if (!function_exists('cpl_plans')) return $text([]);
            $out = []; foreach (cpl_plans() as $p) { $stt = cpl_status($p); $out[] = ['id' => (int)$p['id'], 'name' => (string)$p['name'], 'status' => (string)$p['status'], 'ready' => (int)($stt['ready'] ?? 0), 'total' => (int)($stt['total'] ?? 0)]; }
            return $text(array_slice($out, 0, max(1, min(50, (int)($args['limit'] ?? 20)))));
        case 'create_content_plan':
            if (!mcp_scope_allows($scopes, 'write')) return $err('This token is read-only. Create a token with "write" permission to start content plans.');
            if (!function_exists('cpl_create')) return $err('Content planning is not available.');
            $goal = trim((string)($args['goal'] ?? '')); if ($goal === '') return $err('goal is required.');
            $lang = in_array($args['language'] ?? '', ['ar', 'en', 'ar_en', 'en_ar'], true) ? $args['language'] : 'ar';
            $in = ['goal' => $goal, 'count' => (int)($args['count'] ?? 12), 'lang_li' => $lang, 'lang_x' => $lang, 'lang_img' => $lang, 'lang_vo' => $lang,
                'audience' => (string)($args['audience'] ?? ''), 'tone' => (string)($args['tone'] ?? '')];
            if (!empty($args['dialect'])) { $in['dialect'] = (string)$args['dialect']; $in['dialect_k'] = in_array($args['dialect'], ['msa', 'saudi', 'gulf', 'emirati', 'kuwaiti', 'egyptian', 'levantine', 'maghrebi', 'white'], true) ? $args['dialect'] : 'other'; if ($in['dialect_k'] === 'other') $in['dialect_other'] = (string)$args['dialect']; }
            try { $pid = cpl_create($in); return $text('Content plan started (id ' . $pid . '). It generates in the background; drafts will appear in the content bank for review. Use list_content_plans to check progress.'); }
            catch (Throwable $ex) { return $err($ex->getMessage()); }
        case 'schedule_post':
            if (!mcp_scope_allows($scopes, 'publish')) return $err('Publishing needs a token with "publish" permission (created by the workspace owner in the panel).');
            $txt = trim((string)($args['text'] ?? '')); $accs = array_values(array_filter(array_map('strval', (array)($args['account_ids'] ?? []))));
            if ($txt === '' || !$accs) return $err('text and account_ids are required.');
            $when = in_array($args['when'] ?? '', ['now', 'queue', 'schedule'], true) ? $args['when'] : 'schedule';
            $pl = ['text' => $txt, 'accounts' => $accs, 'when' => $when, 'time' => (string)($args['time'] ?? '')];
            return mcp_do_publish($tokenId, 'post', mb_substr($txt, 0, 120) . ' → ' . count($accs) . ' acct(s), ' . $when, $pl, $text, $err);
        case 'publish_content_bank_item':
            if (!mcp_scope_allows($scopes, 'publish')) return $err('Publishing needs a token with "publish" permission.');
            $iid = (int)($args['item_id'] ?? 0); $accs = array_values(array_filter(array_map('strval', (array)($args['account_ids'] ?? []))));
            if (!$iid || !$accs) return $err('item_id and account_ids are required.');
            if (!function_exists('cpl_item') || !($it = cpl_item($iid)) || $it['status'] !== 'approved') return $err('Content bank item not found or not ready.');
            $when = in_array($args['when'] ?? '', ['now', 'queue', 'schedule'], true) ? $args['when'] : 'schedule';
            $pl = ['item_ids' => [$iid], 'accounts' => $accs, 'when' => $when, 'times' => array_filter([(string)($args['time'] ?? '')])];
            return mcp_do_publish($tokenId, 'bank_publish', 'Bank #' . $iid . ' → ' . count($accs) . ' acct(s), ' . $when, $pl, $text, $err);
        case 'list_ad_campaigns':
            if (!function_exists('ab_list')) return $text([]);
            $out = []; foreach (ab_list((int)ws('id')) as $b) $out[] = ['id' => (int)$b['id'], 'name' => (string)($b['data']['campaign']['name'] ?? ($b['name'] ?? '')), 'platform' => (string)($b['data']['platform'] ?? ''), 'state' => (string)$b['state']];
            return $text($out);
        case 'launch_ad_campaign':
            if (!mcp_scope_allows($scopes, 'admin')) return $err('Launching ad campaigns (real spend) needs an "admin" token.');
            if (!function_exists('ab_get')) return $err('Ad builder not available.');
            $cid = (int)($args['campaign_id'] ?? 0); if (!ab_get((int)ws('id'), $cid)) return $err('Ad campaign not found.');
            return mcp_do_admin($tokenId, 'ad_launch', 'Launch ad build #' . $cid, ['campaign_id' => $cid], $text, $err);
        case 'delete_post':
            if (!mcp_scope_allows($scopes, 'admin')) return $err('Deleting posts needs an "admin" token.');
            $dp = trim((string)($args['post_id'] ?? '')); if ($dp === '') return $err('post_id is required.');
            return mcp_do_admin($tokenId, 'delete_post', 'Delete post ' . $dp, ['post_id' => $dp], $text, $err);
        case 'delete_content_plan':
            if (!mcp_scope_allows($scopes, 'admin')) return $err('Deleting content plans needs an "admin" token.');
            $dpl = (int)($args['plan_id'] ?? 0); if (!$dpl) return $err('plan_id is required.');
            return mcp_do_admin($tokenId, 'delete_plan', 'Delete content plan #' . $dpl, ['plan_id' => $dpl], $text, $err);
    }
    return $err('Unknown tool: ' . $name);
}

/** ينفّذ إجراء admin (صرف إعلانات/حذف) فورًا ويسجّله. */
function mcp_do_admin(int $tokenId, string $type, string $summary, array $payload, callable $text, callable $err): array
{
    $wid = (int)ws('id');
    try {
        $res = mcp_admin_execute($wid, $type, $payload);
        db()->prepare("INSERT INTO mcp_actions (workspace_id, token_id, type, summary, payload, status, result, resolved_at) VALUES (?, ?, ?, ?, ?, 'done', ?, NOW())")
            ->execute([$wid, $tokenId ?: null, $type, mb_substr($summary, 0, 400), json_encode($payload, JSON_UNESCAPED_UNICODE), mb_substr($res, 0, 600)]);
        return $text($res);
    } catch (Throwable $e) {
        db()->prepare("INSERT INTO mcp_actions (workspace_id, token_id, type, summary, payload, status, result, resolved_at) VALUES (?, ?, ?, ?, ?, 'failed', ?, NOW())")
            ->execute([$wid, $tokenId ?: null, $type, mb_substr($summary, 0, 400), json_encode($payload, JSON_UNESCAPED_UNICODE), mb_substr($e->getMessage(), 0, 600)]);
        return $err($e->getMessage());
    }
}

function mcp_admin_execute(int $wid, string $type, array $payload): string
{
    return ai_as_workspace($wid, function () use ($type, $payload, $wid) {
        if ($type === 'delete_post') {
            $z = zernio_or_null($e); if (!$z) throw new RuntimeException(t('اربط مزود النشر أولًا.'));
            $z->deletePost((string)$payload['post_id']); return t('تم حذف المنشور.');
        }
        if ($type === 'delete_plan') {
            $pid = (int)$payload['plan_id'];
            $st = db()->prepare('SELECT id FROM cp_plans WHERE id = ? AND workspace_id = ?'); $st->execute([$pid, $wid]); if (!$st->fetchColumn()) throw new RuntimeException(t('الخطة غير موجودة.'));
            db()->prepare('DELETE FROM cp_items WHERE plan_id = ? AND status IN ("pending","draft","rejected","approved")')->execute([$pid]);
            $c = db()->prepare('SELECT COUNT(*) FROM cp_items WHERE plan_id = ?'); $c->execute([$pid]);
            if (!(int)$c->fetchColumn()) db()->prepare('DELETE FROM cp_plans WHERE id = ?')->execute([$pid]); else db()->prepare('UPDATE cp_plans SET status = "done" WHERE id = ?')->execute([$pid]);
            return t('تم حذف خطة المحتوى.');
        }
        if ($type === 'ad_launch') {
            $z = zernio_or_null($e); if (!$z) throw new RuntimeException(t('اربط مزود النشر أولًا.'));
            $b = ab_get($wid, (int)$payload['campaign_id']); if (!$b) throw new RuntimeException(t('الحملة غير موجودة.'));
            $d = $b['data']; $plan = ab_plan($d); $pr = $b['progress'] ?: ['done' => [], 'ids' => [], 'log' => []];
            $done = (array)($pr['done'] ?? []); $ids = (array)($pr['ids'] ?? []); $ran = 0;
            foreach ($plan as $i => $step) { if (in_array($i, $done, true)) continue; [$ok, $warn, $ids, $raw] = ab_run_step($z, $d, $step, $ids); $done[] = $i; $ran++; }
            $pr['done'] = $done; $pr['ids'] = $ids; ab_set_progress($wid, (int)$b['id'], count($done) === count($plan) ? 'launched' : 'launching', $pr);
            return t('تم إطلاق الحملة (%s خطوة).', $ran);
        }
        throw new RuntimeException('unknown admin action');
    });
}
