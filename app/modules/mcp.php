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
function mcp_tokens_delete_all(int $wid): int { $st = db()->prepare('DELETE FROM mcp_tokens WHERE workspace_id = ?'); $st->execute([$wid]); return $st->rowCount(); }
/** تغيير صلاحية توكن قائم (بدون تغيير قيمته). */
function mcp_token_set_scope(int $wid, int $id, string $scope): bool
{
    $scope = in_array($scope, ['read', 'write', 'publish', 'admin'], true) ? $scope : 'read';
    $st = db()->prepare('UPDATE mcp_tokens SET scopes = ? WHERE id = ? AND workspace_id = ?');
    $st->execute([$scope, $id, $wid]); return $st->rowCount() > 0;
}
/** إعادة توليد قيمة توكن قائم: بترجّع القيمة الجديدة (القديمة بتبطل فورًا)، أو '' لو مش موجود. */
function mcp_token_regen(int $wid, int $id): string
{
    $plain = 'wtd_' . bin2hex(random_bytes(24));
    $st = db()->prepare('UPDATE mcp_tokens SET token_hash = ?, last_used_at = NULL WHERE id = ? AND workspace_id = ?');
    $st->execute([hash('sha256', $plain), $id, $wid]);
    return $st->rowCount() > 0 ? $plain : '';
}

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
        ['name' => 'list_social_accounts', 'description' => 'List the social accounts connected to this workspace, with the platform, name, and id of each one.', 'inputSchema' => ['type' => 'object', 'properties' => new stdClass()]],
        ['name' => 'list_posts', 'description' => 'List recent posts and the status of each one: scheduled, published, draft, or failed.', 'inputSchema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string', 'enum' => ['', 'scheduled', 'published', 'draft', 'failed'], 'description' => 'Optional status filter'], 'limit' => ['type' => 'integer', 'description' => 'Max posts (1-50)']]]],
        ['name' => 'list_content_bank', 'description' => 'List the posts in the content bank. These are approved AI posts that are ready to schedule or publish.', 'inputSchema' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]]],
        ['name' => 'get_company_brief', 'description' => 'Get the company summary the platform keeps up to date and uses for every AI post. It covers the services, the audience, and the brand voice.', 'inputSchema' => ['type' => 'object', 'properties' => new stdClass()]],
        ['name' => 'list_content_plans', 'description' => 'List the AI content plans in this workspace, with the status of each plan and how many of its posts are ready.', 'inputSchema' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]]],
        ['name' => 'create_content_plan', 'description' => 'Start a new AI content plan. The platform writes a full plan from the company knowledge base (KB): topics, captions, and image and video prompts, and generates the images (and videos when video=true) into the content bank for review. It publishes nothing. Tips: call get_company_brief first so you know the company; if the user wants a specific visual style, ASK them for a reference image, add it via the media library, and pass its id in reference_media_ids. Large counts cost real money and take time — suggest batches.', 'inputSchema' => ['type' => 'object', 'required' => ['goal'], 'properties' => [
            'goal' => ['type' => 'string', 'description' => 'What the campaign should achieve'],
            'count' => ['type' => 'integer', 'description' => 'Number of posts (default 12)'],
            'language' => ['type' => 'string', 'enum' => ['ar', 'en', 'ar_en', 'en_ar'], 'description' => 'Content language (default ar)'],
            'dialect' => ['type' => 'string', 'description' => 'Arabic dialect or tone, such as "saudi" or "egyptian", or free text'],
            'audience' => ['type' => 'string', 'description' => 'Target audience (optional)'],
            'tone' => ['type' => 'string', 'description' => 'Tone of voice (optional)'],
            'video' => ['type' => 'boolean', 'description' => 'Also generate a video per post (slower, costs more)'],
            'reference_media_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Reference image ids (from list_media) to match the visual style'],
        ]]],
        ['name' => 'generate_plan_media', 'description' => 'Generate the real images (and videos) for every post of a content plan made by create_content_plan. Call it after the plan status is "review" (see list_content_plans). It queues real generation in the background using the workspace image/video provider; results appear on the plan for review and publish nothing. Costs real money; video is much slower. Needs a write token.', 'inputSchema' => ['type' => 'object', 'required' => ['plan_id'], 'properties' => ['plan_id' => ['type' => 'integer', 'description' => 'Content plan id'], 'media' => ['type' => 'string', 'enum' => ['image', 'video', 'both'], 'description' => 'What to generate (default image)']]]],
        ['name' => 'schedule_post', 'description' => 'Publish or schedule a post to the chosen accounts. This needs a token with publish permission. It runs right away with no approval step, and the platform records every post in its activity log.', 'inputSchema' => ['type' => 'object', 'required' => ['text', 'account_ids'], 'properties' => [
            'text' => ['type' => 'string', 'description' => 'Post text'],
            'account_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Target account ids (from list_social_accounts)'],
            'when' => ['type' => 'string', 'enum' => ['now', 'queue', 'schedule'], 'description' => 'now, queue, or schedule (default schedule)'],
            'time' => ['type' => 'string', 'description' => 'For schedule: local time YYYY-MM-DDTHH:MM:SS'],
        ]]],
        ['name' => 'publish_content_bank_item', 'description' => 'Publish or schedule a ready item from the content bank by its id. This needs a token with publish permission. It runs right away with no approval step, and the platform records it in its activity log.', 'inputSchema' => ['type' => 'object', 'required' => ['item_id', 'account_ids'], 'properties' => [
            'item_id' => ['type' => 'integer', 'description' => 'Content bank item id (from list_content_bank)'],
            'account_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
            'when' => ['type' => 'string', 'enum' => ['now', 'queue', 'schedule']],
            'time' => ['type' => 'string', 'description' => 'For schedule: local time YYYY-MM-DDTHH:MM:SS'],
        ]]],
        ['name' => 'launch_ad_campaign', 'description' => 'Launch a saved ad campaign so it goes live. This spends real money. It needs an admin token. Use list_ad_campaigns to find the id.', 'inputSchema' => ['type' => 'object', 'required' => ['campaign_id'], 'properties' => ['campaign_id' => ['type' => 'integer', 'description' => 'Ad build id']]]],
        ['name' => 'list_ad_campaigns', 'description' => 'List the ad campaigns saved in this workspace, with the id, name, platform, and state of each one.', 'inputSchema' => ['type' => 'object', 'properties' => new stdClass()]],
        ['name' => 'delete_post', 'description' => 'Permanently delete a scheduled or published post by its id. This cannot be undone. It needs an admin token.', 'inputSchema' => ['type' => 'object', 'required' => ['post_id'], 'properties' => ['post_id' => ['type' => 'string']]]],
        ['name' => 'delete_content_plan', 'description' => 'Permanently delete an AI content plan and its unsent drafts by its id. This cannot be undone. It needs an admin token.', 'inputSchema' => ['type' => 'object', 'required' => ['plan_id'], 'properties' => ['plan_id' => ['type' => 'integer']]]],
        ['name' => 'auto_launch_ad_campaign', 'description' => 'Launch a saved ad campaign automatically, but ONLY if its daily budget is within a cap. This spends real money and needs an admin token. Always ASK the user for the highest daily budget they allow, then pass it as max_daily_budget: if the campaign\'s budget is higher, nothing launches and you are told to adjust. Use list_ad_campaigns to find the id.', 'inputSchema' => ['type' => 'object', 'required' => ['campaign_id', 'max_daily_budget'], 'properties' => [
            'campaign_id' => ['type' => 'integer', 'description' => 'Ad build id (from list_ad_campaigns)'],
            'max_daily_budget' => ['type' => 'number', 'description' => 'Maximum daily budget the user allows, in the account currency (ask the user)'],
        ]]],
        ['name' => 'list_media', 'description' => 'List images in the workspace media library, with id and name. Use this to get reference-image ids for create_content_plan. If the user wants a specific visual style, ASK them to add a reference image to the media library first, then pass its id.', 'inputSchema' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer']]]],
        ['name' => 'get_media', 'description' => 'Fetch one image from the workspace media library by id and return it inline so it can be viewed. Find ids via list_media or a content plan.', 'inputSchema' => ['type' => 'object', 'required' => ['media_id'], 'properties' => ['media_id' => ['type' => 'integer', 'description' => 'Media library image id']]]],
        ['name' => 'analytics_summary', 'description' => 'Review social (organic) performance for a period and get stats plus concrete suggestions on what to improve. Returns impressions, reach, engagement and rate, follower growth, posts published, a messages summary, top posts, and a suggestions list. Read-only.', 'inputSchema' => ['type' => 'object', 'properties' => [
            'period' => ['type' => 'string', 'enum' => ['last_week', 'last_month', 'custom'], 'description' => 'Default last_week'],
            'from' => ['type' => 'string', 'description' => 'For custom: YYYY-MM-DD'],
            'to' => ['type' => 'string', 'description' => 'For custom: YYYY-MM-DD'],
        ]]],
        ['name' => 'ad_performance', 'description' => 'Review paid ad campaigns performance over a window and get what to improve. Returns per-campaign spend, impressions, clicks, CTR, results and state, totals per currency, and an actionable issues list (rejected, spend with no results, weak CTR, pending review). Read-only.', 'inputSchema' => ['type' => 'object', 'properties' => ['days' => ['type' => 'string', 'enum' => ['7', '30', '90'], 'description' => 'Window in days (default 7)']]]],
        ['name' => 'schedule_content_bank', 'description' => 'Schedule many ready content-bank items across accounts in one call — turning a generated batch into a live campaign. Pick "queue" to drop them into each account\'s posting queue, or "schedule" to spread them over days (per_day) starting start_date. Needs a token with publish permission. Run create_content_plan first and wait until items are ready (list_content_bank).', 'inputSchema' => ['type' => 'object', 'required' => ['account_ids'], 'properties' => [
            'account_ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Target account ids (from list_social_accounts)'],
            'when' => ['type' => 'string', 'enum' => ['queue', 'schedule'], 'description' => 'queue (default) or schedule'],
            'per_day' => ['type' => 'integer', 'description' => 'For schedule: posts per day (default 2)'],
            'start_date' => ['type' => 'string', 'description' => 'For schedule: YYYY-MM-DD (default tomorrow)'],
            'limit' => ['type' => 'integer', 'description' => 'Max items to schedule (default 50)'],
        ]]],
        ['name' => 'create_ad_campaign', 'description' => 'Build a full paid ad campaign DRAFT from a brief: the platform writes the strategy, ad sets with targeting, and ad copy, using the company knowledge base for context. It saves a draft only and spends nothing. Before calling, call get_company_brief to understand the company, and ASK the user for anything missing — the service/product, the daily budget, the target markets, and (if they want a specific look) a reference image. After it returns, the user reviews it in the panel and generates images/video; launching it (real spend) is a separate launch_ad_campaign call with an admin token.', 'inputSchema' => ['type' => 'object', 'required' => ['brief'], 'properties' => [
            'brief' => ['type' => 'string', 'description' => 'What to advertise and the objective'],
            'product' => ['type' => 'string', 'description' => 'The service or product name'],
            'budget' => ['type' => 'string', 'description' => 'Daily budget amount (ask the user)'],
            'markets' => ['type' => 'string', 'description' => 'Target countries/cities (ask the user)'],
            'platform' => ['type' => 'string', 'description' => 'Preferred platform, e.g. meta or linkedin (optional; uses the first connected ad account otherwise)'],
            'ad_sets' => ['type' => 'integer', 'description' => 'Number of ad sets (1-4, default 2)'],
            'goal_type' => ['type' => 'string', 'description' => 'Campaign objective if known (e.g. traffic, leads)'],
        ]]],
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
            $refs = array_values(array_filter(array_map('intval', (array)($args['reference_media_ids'] ?? []))));
            $in = ['goal' => $goal, 'count' => (int)($args['count'] ?? 12), 'lang_li' => $lang, 'lang_x' => $lang, 'lang_img' => $lang, 'lang_vo' => $lang,
                'video' => !empty($args['video']), 'refs' => $refs,
                'audience' => (string)($args['audience'] ?? ''), 'tone' => (string)($args['tone'] ?? '')];
            if (!empty($args['dialect'])) { $in['dialect'] = (string)$args['dialect']; $in['dialect_k'] = in_array($args['dialect'], ['msa', 'saudi', 'gulf', 'emirati', 'kuwaiti', 'egyptian', 'levantine', 'maghrebi', 'white'], true) ? $args['dialect'] : 'other'; if ($in['dialect_k'] === 'other') $in['dialect_other'] = (string)$args['dialect']; }
            try { $pid = cpl_create($in); return $text('Content plan started (id ' . $pid . '). The text generates in the background; when its status is "review" call generate_plan_media to create the images/videos. Use list_content_plans to check progress.'); }
            catch (Throwable $ex) { return $err($ex->getMessage()); }
        case 'generate_plan_media':
            if (!mcp_scope_allows($scopes, 'write')) return $err('This token is read-only. Create a token with "write" permission.');
            if (!function_exists('cpl_jobs_add') || !function_exists('cpl_plan')) return $err('Content planning is not available.');
            $pid = (int)($args['plan_id'] ?? 0); $plan = cpl_plan($pid); if (!$plan) return $err('Plan not found.');
            $media = in_array($args['media'] ?? 'image', ['image', 'video', 'both'], true) ? (string)$args['media'] : 'image';
            $q = db()->prepare('SELECT id FROM cp_items WHERE plan_id = ? AND linkedin IS NOT NULL'); $q->execute([$pid]); $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
            if (!$ids) return $err('No ready posts yet — wait until the plan status is "review".');
            try { $n = 0; if ($media === 'image' || $media === 'both') $n += cpl_jobs_add($pid, $ids, 'image'); if ($media === 'video' || $media === 'both') $n += cpl_jobs_add($pid, $ids, 'video'); return $text('Queued ' . $n . ' media job(s) for ' . count($ids) . ' post(s). They generate in the background; check list_content_plans.'); }
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

        case 'list_media':
            if (!function_exists('media_list')) return $text([]);
            $n = max(1, min(50, (int)($args['limit'] ?? 20)));
            $out = [];
            foreach (media_list((int)ws('id'), []) as $m) {
                if (function_exists('media_kind') && media_kind((string)($m['mime'] ?? '')) !== 'image') continue;
                $out[] = ['id' => (int)$m['id'], 'name' => (string)($m['name'] ?? $m['original_name'] ?? ''), 'kind' => 'image'];
                if (count($out) >= $n) break;
            }
            return $text($out);

        case 'get_media':
            if (!function_exists('media_get') || !function_exists('media_file_path')) return $err('Media not available.');
            $mid = (int)($args['media_id'] ?? 0); $m = media_get((int)ws('id'), $mid); if (!$m) return $err('Media not found.');
            $mime = (string)($m['mime'] ?? 'image/png'); if (strpos($mime, 'image/') !== 0) return $err('Not an image.');
            $path = media_file_path((int)ws('id'), $m); if (!is_file($path)) return $err('File missing.');
            if (filesize($path) > 8 * 1048576) return $err('Image too large to return inline.');
            return ['content' => [['type' => 'image', 'data' => base64_encode((string)file_get_contents($path)), 'mimeType' => $mime]]];

        case 'analytics_summary':
            if (!function_exists('rp_build')) return $err('Analytics not available.');
            $wid = (int)ws('id'); $tz = rp_tz($wid);
            $kind = in_array($args['period'] ?? '', ['last_week', 'last_month', 'custom'], true) ? (string)$args['period'] : 'last_week';
            $per = rp_period($kind, $tz, (string)($args['from'] ?? ''), (string)($args['to'] ?? ''));
            try { $R = rp_build($wid, $per, ''); } catch (Throwable $ex) { return $err($ex->getMessage()); }
            if (!empty($R['cur']['errors']['all'])) return $err('No publishing provider connected.');
            $ct = $R['cur']['totals']; $pt = $R['prev']['totals'];
            $chg = function ($c, $p) { if ($p <= 0) return $c > 0 ? 'new' : '0%'; return round(($c - $p) / $p * 100) . '%'; };
            $top = [];
            foreach (array_slice($R['cur']['top'] ?? [], 0, 5) as $pst) { $an = (array)($pst['analytics'] ?? []); $top[] = ['text' => mb_substr(preg_replace('/\s+/u', ' ', (string)($pst['content'] ?? '')), 0, 90), 'platform' => (string)($pst['platform'] ?? ''), 'impressions' => (int)($an['impressions'] ?? 0), 'engagement' => round(an_engagement($an))]; }
            $sugg = [];
            if (($ct['impressions'] ?? 0) < ($pt['impressions'] ?? 0) * 0.9) $sugg[] = 'الوصول نزل عن الفترة السابقة (' . $chg($ct['impressions'], $pt['impressions']) . ') — جرّب أوقات نشر مختلفة ومحتوى بصري أقوى.';
            if (($ct['rate'] ?? 0) < 1) $sugg[] = 'معدل التفاعل منخفض (' . number_format((float)($ct['rate'] ?? 0), 2) . '%) — ركّز على هوك أول سطر ودعوة واضحة للتفاعل.';
            if ((int)($ct['postCount'] ?? 0) < 8) $sugg[] = 'عدد المنشورات قليل (' . (int)($ct['postCount'] ?? 0) . ') — زوّد وتيرة النشر للثبات.';
            if (($R['cur']['fol']['gain'] ?? 0) <= 0) $sugg[] = 'المتابعون مش بيزيدوا — جرّب محتوى قابل للمشاركة وتعاونات.';
            if (!$sugg) $sugg[] = 'الأداء مستقر — واصل نفس الإيقاع وراقب أفضل المنشورات وكرّر نمطها.';
            return $text([
                'period' => ['from' => $per['from'], 'to' => $per['to']],
                'impressions' => (int)($ct['impressions'] ?? 0), 'impressions_change' => $chg($ct['impressions'], $pt['impressions']),
                'reach' => (int)($ct['reach'] ?? 0), 'reach_change' => $chg($ct['reach'], $pt['reach']),
                'engagement' => round(($ct['engagement'] ?? 0)), 'engagement_rate' => round((float)($ct['rate'] ?? 0), 2),
                'followers_gained' => round(($R['cur']['fol']['gain'] ?? 0)), 'posts_published' => (int)($ct['postCount'] ?? 0),
                'messages' => ['received' => (int)($R['cur']['inbox']['received'] ?? 0), 'sent' => (int)($R['cur']['inbox']['sent'] ?? 0)],
                'top_posts' => $top, 'suggestions' => $sugg,
            ]);

        case 'ad_performance':
            if (!function_exists('ads_campaigns')) return $err('Ads module not available.');
            $z = zernio_or_null($e); if (!$z) return $err('No publishing provider connected.');
            $days = in_array((string)($args['days'] ?? '7'), ['7', '30', '90'], true) ? (string)$args['days'] : '7';
            [$f, $t] = ads_date_range($days);
            try { $camps = ads_campaigns($z, ['fromDate' => $f, 'toDate' => $t, 'limit' => 100])['campaigns'] ?? []; }
            catch (Throwable $ex) { return $err($ex->getMessage()); }
            if (!$camps) return $text(['window_days' => (int)$days, 'campaigns' => [], 'note' => 'مفيش حملات شغالة في الفترة دي.']);
            $rows = []; $tot = []; $tI = 0; $tC = 0;
            foreach ($camps as $c) { $m = (array)($c['metrics'] ?? []); $cur = (string)($c['currency'] ?? ''); $res = ads_results($m);
                $rows[] = ['name' => (string)($c['campaignName'] ?? ''), 'platform' => (string)($c['platform'] ?? ''), 'state' => (string)($c['status'] ?? ''), 'currency' => $cur,
                    'spend' => round((float)($m['spend'] ?? 0), 2), 'impressions' => (int)($m['impressions'] ?? 0), 'clicks' => (int)($m['clicks'] ?? 0), 'ctr' => round((float)($m['ctr'] ?? 0), 2), 'results' => round($res, 1)];
                $tot[$cur]['spend'] = ($tot[$cur]['spend'] ?? 0) + (float)($m['spend'] ?? 0); $tot[$cur]['results'] = ($tot[$cur]['results'] ?? 0) + $res;
                $tI += (int)($m['impressions'] ?? 0); $tC += (int)($m['clicks'] ?? 0); }
            $avgCtr = $tI ? $tC / $tI * 100 : 0; $issues = [];
            foreach ($camps as $c) { $m = (array)($c['metrics'] ?? []); $n = (string)($c['campaignName'] ?? ''); $st = (string)($c['status'] ?? '');
                if ($st === 'rejected') $issues[] = "«$n» مرفوضة من المنصة — عدّل وأعد الإرسال.";
                elseif ((float)($m['spend'] ?? 0) > 0 && !ads_results($m)) $issues[] = "«$n» صرفت من غير نتائج — راجع الاستهداف والعرض.";
                elseif ((int)($m['impressions'] ?? 0) >= 1000 && $avgCtr && (float)($m['ctr'] ?? 0) < $avgCtr * 0.6) $issues[] = "«$n» نسبة ضغط ضعيفة (" . number_format((float)($m['ctr'] ?? 0), 2) . "%) — جرّب كرييتف/هوك جديد.";
                elseif ($st === 'pending_review') $issues[] = "«$n» تحت المراجعة لسه."; }
            if (!$issues) $issues[] = 'كل الحملات تمام — فكّر تزوّد ميزانية الأفضل أداءً.';
            return $text(['window_days' => (int)$days, 'totals_by_currency' => $tot, 'avg_ctr' => round($avgCtr, 2), 'campaigns' => $rows, 'what_to_improve' => $issues]);

        case 'schedule_content_bank':
            if (!mcp_scope_allows($scopes, 'publish')) return $err('Scheduling needs a token with "publish" permission.');
            if (!function_exists('cpl_items') || !function_exists('cpl_publish')) return $err('Content bank not available.');
            $z = zernio_or_null($e); if (!$z) return $err('No publishing provider connected.');
            $accs = array_values(array_filter(array_map('strval', (array)($args['account_ids'] ?? []))));
            if (!$accs) return $err('account_ids are required (from list_social_accounts).');
            $when = in_array($args['when'] ?? '', ['queue', 'schedule'], true) ? (string)$args['when'] : 'queue';
            $ready = cpl_items(0, ['approved']);
            $ids = array_values(array_map(function ($it) { return (int)$it['id']; }, $ready));
            $ids = array_slice($ids, 0, max(1, min(100, (int)($args['limit'] ?? 50))));
            if (!$ids) return $err('مفيش عناصر جاهزة في مخزن المحتوى. ابدأ خطة محتوى الأول بـ create_content_plan واستنى تجهز.');
            $times = [];
            if ($when === 'schedule') {
                $perDay = max(1, min(6, (int)($args['per_day'] ?? 2)));
                $start = preg_match('/^\d{4}-\d\d-\d\d$/', (string)($args['start_date'] ?? '')) ? (string)$args['start_date'] : gmdate('Y-m-d', time() + 86400);
                $slots = [10, 14, 18, 20, 12, 16]; $day = 0; $k = 0;
                foreach ($ids as $_) { $h = $slots[$k % $perDay] ?? 10; $times[] = gmdate('Y-m-d', strtotime($start) + $day * 86400) . sprintf('T%02d:00:00', $h); $k++; if ($k % $perDay === 0) $day++; }
            }
            return mcp_do_publish($tokenId, 'bank_publish', 'Bulk schedule ' . count($ids) . ' bank item(s) → ' . count($accs) . ' acct(s), ' . $when, ['item_ids' => $ids, 'accounts' => $accs, 'when' => $when, 'times' => $times], $text, $err);

        case 'auto_launch_ad_campaign':
            if (!mcp_scope_allows($scopes, 'admin')) return $err('Launching ad campaigns (real spend) needs an "admin" token.');
            if (!function_exists('ab_get') || !function_exists('ab_main_budget')) return $err('Ad builder not available.');
            $cid = (int)($args['campaign_id'] ?? 0);
            $cap = (float)($args['max_daily_budget'] ?? 0);
            if (!$cid) return $err('campaign_id is required (from list_ad_campaigns).');
            if ($cap <= 0) return $err('max_daily_budget is required — ASK the user for the highest daily budget they allow before launching, then pass it here.');
            $build = ab_get((int)ws('id'), $cid);
            if (!$build) return $err('Ad campaign not found.');
            $d = (array)($build['data'] ?? []);
            $budget = ab_main_budget($d); $curr = (string)($d['currency'] ?? '');
            if ($budget <= 0) return $err('مفيش ميزانية يومية محددة على الحملة. حدّد الميزانية في اللوحة الأول.');
            if ($budget > $cap) return $text('مش هطلقها: الميزانية اليومية ' . rtrim(rtrim(number_format($budget, 2), '0'), '.') . ' ' . $curr . ' أكبر من الحد اللي سمحت بيه (' . rtrim(rtrim(number_format($cap, 2), '0'), '.') . ' ' . $curr . '). اطلب من المستخدم يرفع الحد أو يقلّل ميزانية الحملة.');
            return mcp_do_admin($tokenId, 'ad_launch', 'Auto-launch ad build #' . $cid . ' (budget ' . $budget . ' ≤ cap ' . $cap . ' ' . $curr . ')', ['campaign_id' => $cid], $text, $err);

        case 'create_ad_campaign':
            if (!mcp_scope_allows($scopes, 'write')) return $err('Building ad campaigns needs a token with "write" permission.');
            if (!function_exists('ads_usable_accounts') || !function_exists('ab_default_data') || !function_exists('ai_chat')) return $err('Ad builder not available.');
            $z = zernio_or_null($e); if (!$z) return $err('No publishing provider connected.');
            $brief = trim((string)($args['brief'] ?? $args['goal'] ?? '')); if ($brief === '') return $err('brief is required: what to advertise and the objective. Ask the user for the service, budget, and markets if missing.');
            try { $targets = ads_usable_accounts($z, ads_connections($z)); } catch (Throwable $x) { $targets = []; }
            if (!$targets) return $err('مفيش حساب إعلانات مربوط. اربط حساب Meta/LinkedIn Ads من اللوحة أول، وبعدين اطلب مني أبني الحملة.');
            $pref = strtolower((string)($args['platform'] ?? '')); $pick = $targets[0];
            if ($pref !== '') foreach ($targets as $tt) { if (strpos(strtolower((string)($tt['conn']['platform'] ?? '')), $pref) !== false) { $pick = $tt; break; } }
            $c = $pick['conn']; $a = $pick['acc'];
            $platform = ab_platform_of((string)($c['platform'] ?? ''));
            $data = ab_default_data($platform, (string)$c['_id'], (string)($a['id'] ?? ''), (string)($a['currency'] ?? ''), (string)($a['name'] ?? ''));
            $goalIn = (string)($args['goal_type'] ?? ''); $caps = ab_caps($platform);
            if (in_array($goalIn, $caps['goals'] ?? [], true)) $data['campaign']['goal'] = $goalIn;
            $data['campaign']['budget']['amount'] = preg_replace('/[^0-9.]/', '', (string)($args['budget'] ?? ''));
            $product = trim((string)($args['product'] ?? '')); $markets = trim((string)($args['markets'] ?? ''));
            $data['campaign']['name'] = mb_substr($product !== '' ? $product : $brief, 0, 60) ?: ('حملة ' . date('d/m'));
            $nSets = max(1, min(4, (int)($args['ad_sets'] ?? 2)));
            $brand = function_exists('ab_brand_visual') ? ab_brand_visual() : '';
            $cc = $data['adSets'][0]['targeting']['countries'][0] ?? 'EG';
            $sys = 'أنت ميديا باير وكوبي رايتر وآرت دايركتور محترف. أخرج خطة حملة كـ JSON فقط بالشكل: {"adSets":[{"name":"اسم المجموعة","targeting":{"countries":["' . $cc . '"],"ageMin":25,"ageMax":45,"gender":"all","interests":[{"name":"اهتمام"}]},"ads":[{"headline":"عنوان قوي","body":"نص إعلاني بيّاع","cta":"LEARN_MORE","format":"image","art":"وصف بصري دقيق للصورة"}]}]}. اكتب ' . $nSets . ' مجموعات باستهداف مختلف، وإعلانين لكل مجموعة. لا تكتب أي شيء خارج JSON.';
            $user = "الطلب: $brief\nالمنتج/الخدمة: $product\nالأسواق المطلوبة: $markets\nالميزانية اليومية: " . $data['campaign']['budget']['amount'] . ' ' . $data['currency'] . ($brand !== '' ? "\nهوية الشركة: $brand" : '');
            try { $r = ai_chat([['role' => 'system', 'content' => $sys . ' ' . (function_exists('ai_country_note') ? ai_country_note() : '')], ['role' => 'user', 'content' => $user]], 2400, 'ads_builder'); $j = ab_json((string)$r['text']); }
            catch (Throwable $x) { $j = null; }
            $sets = [];
            foreach ((array)($j['adSets'] ?? []) as $i => $asr) {
                $set = ab_default_adset($cc, $i + 1);
                $set['name'] = mb_substr((string)($asr['name'] ?? ('مجموعة ' . ($i + 1))), 0, 80);
                if (!empty($asr['targeting']) && is_array($asr['targeting'])) { $tg = array_merge($set['targeting'], $asr['targeting']); $set['targeting'] = array_merge($set['targeting'], ab_normalize_targeting($tg)); }
                $ads = [];
                foreach ((array)($asr['ads'] ?? []) as $adr) { $ad = ab_default_ad(); $ad['headline'] = mb_substr((string)($adr['headline'] ?? ''), 0, 200); $ad['body'] = mb_substr((string)($adr['body'] ?? ''), 0, 600); $ad['cta'] = (string)($adr['cta'] ?? 'LEARN_MORE'); $ad['format'] = in_array($adr['format'] ?? '', ['image', 'video', 'carousel'], true) ? $adr['format'] : 'image'; if (!empty($adr['art'])) $ad['art'] = ['prompt' => mb_substr((string)$adr['art'], 0, 500)]; $ads[] = $ad; }
                if ($ads) $set['ads'] = $ads;
                $sets[] = $set;
            }
            if ($sets) $data['adSets'] = $sets;
            try { $nid = ab_create((int)ws('id'), $data, $data['campaign']['name']); }
            catch (Throwable $x) { return $err($x->getMessage()); }
            log_activity('ads.builder_ai', 'mcp_create');
            return $text('حملة مسودة اتعملت (id ' . $nid . ') على ' . $platform . ' / ' . (string)($a['name'] ?? '') . ' بـ ' . count($sets ?: $data['adSets']) . ' مجموعة. راجعها في اللوحة وولّد الصور/الفيديو لو حبيت، وبعدين أطلقها بـ launch_ad_campaign (محتاج توكن admin). لو عايز ستايل بصري معيّن للصور، اطلب من المستخدم ريفرانس.');
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
