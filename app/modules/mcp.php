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
        ->execute([$wid, $uid ?: null, mb_substr(trim($name), 0, 120) ?: 'MCP', hash('sha256', $plain), in_array($scopes, ['read', 'write'], true) ? $scopes : 'read']);
    return $plain;
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
    ];
}

/** ينفّذ أداة ويرجّع نص النتيجة. بيشتغل جوه سياق الشركة (ai_as_workspace). */
function mcp_call(string $name, array $args, string $scopes): array
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
            if ($scopes !== 'write') return $err('This token is read-only. Create a token with "write" permission to start content plans.');
            if (!function_exists('cpl_create')) return $err('Content planning is not available.');
            $goal = trim((string)($args['goal'] ?? '')); if ($goal === '') return $err('goal is required.');
            $lang = in_array($args['language'] ?? '', ['ar', 'en', 'ar_en', 'en_ar'], true) ? $args['language'] : 'ar';
            $in = ['goal' => $goal, 'count' => (int)($args['count'] ?? 12), 'lang_li' => $lang, 'lang_x' => $lang, 'lang_img' => $lang, 'lang_vo' => $lang,
                'audience' => (string)($args['audience'] ?? ''), 'tone' => (string)($args['tone'] ?? '')];
            if (!empty($args['dialect'])) { $in['dialect'] = (string)$args['dialect']; $in['dialect_k'] = in_array($args['dialect'], ['msa', 'saudi', 'gulf', 'emirati', 'kuwaiti', 'egyptian', 'levantine', 'maghrebi', 'white'], true) ? $args['dialect'] : 'other'; if ($in['dialect_k'] === 'other') $in['dialect_other'] = (string)$args['dialect']; }
            try { $pid = cpl_create($in); return $text('Content plan started (id ' . $pid . '). It generates in the background; drafts will appear in the content bank for review. Use list_content_plans to check progress.'); }
            catch (Throwable $ex) { return $err($ex->getMessage()); }
    }
    return $err('Unknown tool: ' . $name);
}
