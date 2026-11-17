<?php
/**
 * توليد الصور بـ OpenAI (موديل GPT للصور). لو OpenAI مربوط بيبقى هو مولّد الصور في المنصة كلها،
 * و Higgsfield بيفضل للفيديو بس. الصور بتتحفظ في نفس مكتبة الشركة (uploads/ai) زي ما كانت.
 */

/** اتصال OpenAI (من صفحة التكاملات) لو مربوط */
function img_openai_conn(): ?array
{
    $c = function_exists('connector') ? connector('openai') : null;
    return $c && (string)($c['credentials']['api_key'] ?? '') !== '' ? $c : null;
}

/** موديل الصور (قابل للتغيير من الإعدادات لو OpenAI نزّل موديل أحدث) */
function img_openai_model(): string { $m = trim((string)setting('img_openai_model', '')); return $m !== '' && preg_match('/^[\w.\-]+$/', $m) ? $m : 'gpt-image-1'; }
function img_openai_quality(): string { $q = (string)setting('img_openai_quality', 'medium'); return in_array($q, ['low', 'medium', 'high'], true) ? $q : 'medium'; }

/** المقاس من النسبة: مربع، طولي (بوست/ستوري)، عرضي */
function img_openai_size(string $aspect): string
{
    return in_array($aspect, ['4:5', '9:16', '2:3', '3:4'], true) ? '1024x1536' : (in_array($aspect, ['16:9', '3:2', '4:3'], true) ? '1536x1024' : '1024x1024');
}

/** تكلفة تقريبية للصورة الواحدة بالدولار (للتسجيل في استهلاك الذكاء الاصطناعي) */
function img_openai_cost(string $quality, string $size): float
{
    $sq = ['low' => 0.011, 'medium' => 0.042, 'high' => 0.167]; $rect = ['low' => 0.016, 'medium' => 0.063, 'high' => 0.25];
    return ($size === '1024x1024' ? $sq : $rect)[$quality] ?? 0.042;
}

/** حفظ صورة (بايتات) في مكتبة الشركة بنفس صيغة الأسماء */
function img_lib_save_bytes(int $wid, string $bytes, string $prompt): ?string
{
    $dir = hf_lib_dir($wid); if (!is_dir($dir)) @mkdir($dir, 0750, true);
    if (function_exists('img_trim_bands')) $bytes = img_trim_bands($bytes);
    $tmp = tempnam(sys_get_temp_dir(), 'oa'); file_put_contents($tmp, $bytes); $mime = (string)mime_content_type($tmp);
    $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext || filesize($tmp) > 25 * 1048576) { @unlink($tmp); return null; }
    $name = gmdate('Ymd-His') . '-' . substr(md5($prompt . microtime(true) . random_int(0, PHP_INT_MAX)), 0, 10) . '.' . $ext;
    rename($tmp, $dir . '/' . $name); @chmod($dir . '/' . $name, 0640);
    @file_put_contents($dir . '/' . $name . '.json', json_encode(['src' => '', 't' => time(), 'provider' => 'openai']));
    if (function_exists('media_sync_ai')) { try { media_sync_ai($wid); } catch (Throwable $e) {} }
    return $name;
}

/** يولّد صورة أو أكتر ويرجع أسماءها في المكتبة. بيرمي استثناء برسالة واضحة لو فشل */
function img_openai_generate(string $prompt, string $aspect = '1:1', int $n = 1, array $refs = [], string $quality = ''): array
{
    $c = img_openai_conn(); if (!$c) throw new RuntimeException(t('اربط OpenAI أولًا من صفحة التكاملات.'));
    $wid = (int)ws('id'); $budget = function_exists('ai_budget') ? ai_budget($wid) : 0;
    if ($budget > 0 && function_exists('ai_month_spend') && ai_month_spend($wid) >= $budget) throw new RuntimeException(t('وصلت الشركة لحد استهلاك الذكاء الاصطناعي هذا الشهر ($%s). صاحب الشركة يقدر يرفعه من صفحة التكاملات.', number_format($budget, 2)));
    $model = img_openai_model(); $size = img_openai_size($aspect); $q = in_array($quality, ['low', 'medium', 'high'], true) ? $quality : img_openai_quality(); $n = max(1, min(4, $n));
    @set_time_limit(180);
    $refs = array_slice(array_values(array_filter($refs, 'is_file')), 0, 4);
    if ($refs) {
        // صور مرجعية (منتج، لوجو، شخص): endpoint التعديل بياخد لحد كام صورة ويبني عليها
        $post = ['model' => $model, 'prompt' => mb_substr($prompt, 0, 30000), 'size' => $size, 'quality' => $q, 'n' => (string)$n];
        foreach ($refs as $i => $f) $post['image[' . $i . ']'] = new CURLFile($f, (string)(mime_content_type($f) ?: 'image/png'), basename($f));
        $ch = curl_init(ai_base('openai') . '/images/edits');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 170, CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $c['credentials']['api_key']], CURLOPT_POSTFIELDS => $post]);
    } else {
    $ch = curl_init(ai_base('openai') . '/images/generations');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 170, CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $c['credentials']['api_key']],
        CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'prompt' => mb_substr($prompt, 0, 30000), 'size' => $size, 'quality' => $q, 'n' => $n], JSON_UNESCAPED_UNICODE)]);
    }
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
    $j = json_decode($raw, true);
    if ($raw === '' && $cerr !== '') throw new RuntimeException(net_friendly('OpenAI', (string)$cerr));
    if ($code >= 400 || !is_array($j)) {
        $msg = (string)($j['error']['message'] ?? ('HTTP ' . $code)); $codeS = (string)($j['error']['code'] ?? '');
        if ($codeS === 'moderation_blocked' || stripos($msg, 'safety') !== false) throw new RuntimeException(t('رفض OpenAI الوصف لأنه مخالف لسياسة المحتوى. جرّب وصفًا مختلفًا.'));
        if ($code === 401) throw new RuntimeException(t('مفتاح OpenAI غير صحيح.'));
        if (stripos($msg, 'verif') !== false) throw new RuntimeException(t('OpenAI يطلب توثيق المؤسسة (Organization verification) لاستخدام موديل الصور. وثّقها من حسابك على OpenAI ثم جرّب مرة أخرى.'));
        throw new RuntimeException('OpenAI: ' . mb_substr($msg, 0, 300));
    }
    $names = [];
    foreach ((array)($j['data'] ?? []) as $d) {
        $bytes = !empty($d['b64_json']) ? base64_decode((string)$d['b64_json'], true) : null;
        if ($bytes === null && !empty($d['url'])) { $r = safe_fetch((string)$d['url'], ['max' => 25 * 1048576]); $bytes = empty($r['err']) ? (string)$r['body'] : null; }
        if ($bytes) { $nm = img_lib_save_bytes($wid, $bytes, $prompt); if ($nm) $names[] = $nm; }
    }
    if (!$names) throw new RuntimeException(t('لم يرجع OpenAI صورة. جرّب مرة أخرى.'));
    if (function_exists('ai_log_usage')) { try { ai_log_usage($wid, 'image', 'openai', $model, ['cost' => round(img_openai_cost($q, $size) * count($names), 4), 'prompt_tokens' => 0, 'completion_tokens' => 0]); } catch (Throwable $e) {} }
    return $names;
}


/* ---------- OpenRouter: موديلات بتطلّع صور (Gemini Image، GPT Image ...) بنفس مفتاح OpenRouter ---------- */

function img_or_conn(): ?array
{
    $c = function_exists('connector') ? connector('openrouter') : null;
    return $c && (string)($c['credentials']['api_key'] ?? '') !== '' ? $c : null;
}

/** موديلات OpenRouter اللي بتطلّع صور (من قايمة الموديلات، متخزنة 6 ساعات) */
function img_or_models(array $c): array
{
    $file = ROOT_DIR . '/uploads/cache/openrouter_img_models2_' . substr(sha1((string)$c['credentials']['api_key']), 0, 12) . '.json';
    if (is_file($file) && filemtime($file) > time() - 21600) { $j = json_decode((string)file_get_contents($file), true); if (is_array($j) && $j) return $j; }
    $ch = curl_init(ai_base('openrouter') . '/models');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $c['credentials']['api_key']]]);
    $j = json_decode((string)curl_exec($ch), true); curl_close($ch);
    $out = [];
    foreach ((array)($j['data'] ?? []) as $m) if (in_array('image', (array)($m['architecture']['output_modalities'] ?? []), true) && strpos((string)$m['id'], 'openrouter/') !== 0) $out[] = ['id' => (string)$m['id'], 'name' => (string)($m['name'] ?? $m['id']), 'img_in' => in_array('image', (array)($m['architecture']['input_modalities'] ?? []), true), 'usd' => img_or_price((array)($m['pricing'] ?? []))];
    if ($out) { @mkdir(dirname($file), 0750, true); @file_put_contents($file, json_encode($out)); }
    return $out;
}

/** الموديل المختار: من الإعدادات لو متحدد، وإلا الأشهر للصور */
function img_or_model(array $c): string
{
    $set = trim((string)setting('img_or_model_' . (int)ws('id'), ''));
    $list = img_or_models($c); $ids = array_column($list, 'id');
    if ($set !== '' && in_array($set, $ids, true)) return $set;
    foreach (['google/gemini-3.1-flash-image', 'google/gemini-nano-banana-2.1', 'google/gemini-2.5-flash-image', 'openai/gpt-5-image-mini', 'openai/gpt-5-image'] as $pref) if (in_array($pref, $ids, true)) return $pref;
    return $ids[0] ?? '';
}

/** يولّد صورة عبر OpenRouter (chat/completions + modalities) ويحفظها في المكتبة */
function img_or_generate(string $prompt, string $aspect = '1:1', array $refs = []): array
{
    $c = img_or_conn(); if (!$c) throw new RuntimeException(t('اربط OpenRouter أو OpenAI أولًا من صفحة التكاملات.'));
    $wid = (int)ws('id'); $budget = function_exists('ai_budget') ? ai_budget($wid) : 0;
    if ($budget > 0 && function_exists('ai_month_spend') && ai_month_spend($wid) >= $budget) throw new RuntimeException(t('وصلت الشركة لحد استهلاك الذكاء الاصطناعي هذا الشهر.'));
    $model = img_or_model($c); if ($model === '') throw new RuntimeException(t('لا يوجد موديل صور متاح على حساب OpenRouter.'));
    $parts = [['type' => 'text', 'text' => mb_substr($prompt, 0, 30000) . "\n\nAspect ratio: {$aspect}."]];
    foreach (array_slice(array_values(array_filter($refs, 'is_file')), 0, 4) as $f) { $mime = (string)(mime_content_type($f) ?: 'image/png'); $parts[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($f))]]; }
    @set_time_limit(180);
    $hdr = ['Content-Type: application/json', 'Authorization: Bearer ' . $c['credentials']['api_key'], 'HTTP-Referer: ' . (function_exists('base_url') ? base_url() : 'https://localhost'), 'X-Title: ' . (string)setting('site_name', 'Panel')];
    if (!$refs) {
        $ch = curl_init(ai_base('openrouter') . '/images');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 170, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_HTTPHEADER => $hdr,
            CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'prompt' => mb_substr($prompt, 0, 30000), 'aspect_ratio' => $aspect, 'n' => 1, 'output_format' => 'png'], JSON_UNESCAPED_UNICODE)]);
        $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); $j = json_decode($raw, true); $names = [];
        if ($code < 400 && is_array($j)) foreach ((array)($j['data'] ?? []) as $d) { $bytes = !empty($d['b64_json']) ? base64_decode((string)$d['b64_json'], true) : null; if ($bytes) { $nm = img_lib_save_bytes($wid, $bytes, $prompt); if ($nm) $names[] = $nm; } }
        if ($names) { if (function_exists('ai_log_usage')) { try { ai_log_usage($wid, 'image', 'openrouter', $model, ['cost' => (float)($j['usage']['cost'] ?? 0), 'prompt_tokens' => 0, 'completion_tokens' => 0]); } catch (Throwable $e) {} } return $names; }
        if ($code >= 400 && $code !== 404 && $code !== 405 && isset($j['error']['message'])) throw new RuntimeException('OpenRouter: ' . mb_substr((string)$j['error']['message'], 0, 300));
    }
    $ch = curl_init(ai_base('openrouter') . '/chat/completions');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 170, CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $c['credentials']['api_key'], 'HTTP-Referer: ' . (function_exists('base_url') ? base_url() : 'https://localhost'), 'X-Title: ' . (string)setting('site_name', 'Panel')],
        CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'modalities' => ['image', 'text'], 'messages' => [['role' => 'user', 'content' => $parts]], 'image_config' => ['aspect_ratio' => $aspect]], JSON_UNESCAPED_UNICODE)]);
    $raw = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
    if ($raw === '' && $cerr !== '') throw new RuntimeException(net_friendly('OpenRouter', (string)$cerr));
    $j = json_decode($raw, true);
    if ($code >= 400 || !is_array($j) || isset($j['error'])) throw new RuntimeException('OpenRouter: ' . mb_substr((string)($j['error']['message'] ?? ('HTTP ' . $code)), 0, 300));
    $names = [];
    foreach ((array)($j['choices'][0]['message']['images'] ?? []) as $im) {
        $u = (string)($im['image_url']['url'] ?? ''); $bytes = null;
        if (preg_match('~^data:image/[a-z+]+;base64,(.+)$~s', $u, $m)) $bytes = base64_decode($m[1], true);
        elseif (preg_match('~^https://~', $u)) { $r = safe_fetch($u, ['max' => 25 * 1048576]); $bytes = empty($r['err']) ? (string)$r['body'] : null; }
        if ($bytes) { $nm = img_lib_save_bytes($wid, $bytes, $prompt); if ($nm) $names[] = $nm; }
    }
    if (!$names) throw new RuntimeException(t('لم يرجع الموديل صورة (%s). جرّب مرة أخرى أو موديلًا آخر.', $model));
    if (function_exists('ai_log_usage')) { try { ai_log_usage($wid, 'image', 'openrouter', $model, ['cost' => (float)($j['usage']['cost'] ?? 0), 'prompt_tokens' => (int)($j['usage']['prompt_tokens'] ?? 0), 'completion_tokens' => (int)($j['usage']['completion_tokens'] ?? 0)]); } catch (Throwable $e) {} }
    return $names;
}

/** أي مزوّد صور متاح؟ OpenAI أولًا (أدق في الكتابة على الصور)، وإلا OpenRouter */
function img_provider(): string { return img_openai_conn() ? 'openai' : (img_or_conn() ? 'openrouter' : ''); }


/** توليد صورة بأي مزوّد متاح (OpenAI أو OpenRouter) */
function img_generate(string $prompt, string $aspect = '1:1', int $n = 1, array $refs = []): array
{
    if (img_provider() === 'openai') return img_openai_generate($prompt, $aspect, $n, $refs);
    $out = []; for ($i = 0; $i < max(1, min(2, $n)); $i++) $out = array_merge($out, img_or_generate($prompt, $aspect, $refs));
    return $out;
}

/* ---------- فيديو OpenRouter: غير متزامن (طلب ← متابعة ← تنزيل) ---------- */

function vid_or_models(array $c): array
{
    $file = ROOT_DIR . '/uploads/cache/openrouter_vid_models3_' . substr(sha1((string)$c['credentials']['api_key']), 0, 12) . '.json';
    if (is_file($file) && filemtime($file) > time() - 21600) { $j = json_decode((string)file_get_contents($file), true); if (is_array($j) && $j) return $j; }
    $ch = curl_init(ai_base('openrouter') . '/videos/models');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $c['credentials']['api_key']]]);
    $j = json_decode((string)curl_exec($ch), true); curl_close($ch);
    $out = [];
    foreach ((array)($j['data'] ?? []) as $m) if (!empty($m['id'])) $out[] = ['id' => (string)$m['id'], 'name' => (string)($m['name'] ?? $m['id']), 'durations' => array_values(array_filter(array_map('intval', (array)($m['supported_durations'] ?? [])), function ($d) { return $d > 0; })), 'ratios' => array_values((array)($m['supported_aspect_ratios'] ?? [])), 'usd_s' => vid_or_price((array)($m['pricing_skus'] ?? $m['pricing'] ?? [])), 'res' => vid_or_res_prices((array)($m['pricing_skus'] ?? $m['pricing'] ?? []), (array)($m['supported_resolutions'] ?? []))];
    if ($out) { @mkdir(dirname($file), 0750, true); @file_put_contents($file, json_encode($out)); }
    return $out;
}

function vid_or_model(array $c): string
{
    $set = trim((string)setting('vid_or_model_' . (int)ws('id'), '')); $ids = array_column(vid_or_models($c), 'id');
    if ($set !== '' && in_array($set, $ids, true)) return $set;
    foreach (['google/veo-3.1', 'bytedance/seedance-2.0', 'minimax/hailuo-3', 'alibaba/wan-2.7'] as $pref) if (in_array($pref, $ids, true)) return $pref;
    return $ids[0] ?? '';
}

/** مين يولّد الفيديو: OpenRouter لو المالك اختار له موديل (أو مفيش Higgsfield)، وإلا Higgsfield */
function vid_provider(): string
{
    $or = img_or_conn(); $hf = function_exists('connector') ? connector('higgsfield') : null;
    if ($or && (trim((string)setting('vid_or_model_' . (int)ws('id'), '')) !== '' || !$hf)) { try { if (vid_or_model($or) !== '') return 'openrouter'; } catch (Throwable $e) {} }
    return $hf ? 'higgsfield' : '';
}

function vid_or_req(array $c, string $method, string $path, ?array $body = null, bool $raw = false)
{
    $ch = curl_init(ai_base('openrouter') . $path);
    $h = ['Authorization: Bearer ' . $c['credentials']['api_key']]; if ($body !== null) { $h[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE)); }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => $raw ? 170 : 40, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_HTTPHEADER => $h, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3]);
    $out = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($raw) { if ($code >= 400 || $out === '') throw new RuntimeException('OpenRouter: HTTP ' . $code); return $out; }
    $j = json_decode($out, true); if ($code >= 400 || !is_array($j)) throw new RuntimeException('OpenRouter: ' . mb_substr((string)($j['error']['message'] ?? ('HTTP ' . $code)), 0, 300));
    return $j;
}

/** يبدأ توليد فيديو ويرجع رقم المهمة */
function vid_or_start(string $prompt, string $aspect = '9:16', int $duration = 0): string
{
    $c = img_or_conn(); if (!$c) throw new RuntimeException(t('اربط OpenRouter أولًا من صفحة التكاملات.'));
    $wid = (int)ws('id'); if (function_exists('ai_budget') && ai_budget($wid) > 0 && ai_month_spend($wid) >= ai_budget($wid)) throw new RuntimeException(t('وصلت الشركة لحد استهلاك الذكاء الاصطناعي هذا الشهر ($%s). صاحب الشركة يقدر يرفعه من صفحة التكاملات.', number_format(ai_budget($wid), 2)));
    $model = vid_or_model($c); if ($model === '') throw new RuntimeException(t('لا يوجد موديل فيديو متاح على حساب OpenRouter.'));
    $body = ['model' => $model, 'prompt' => mb_substr($prompt, 0, 4000), 'aspect_ratio' => $aspect];
    $res = trim((string)setting('vid_or_res_' . (int)ws('id'), ''));
    $info = array_values(array_filter(vid_or_models($c), function ($m) use ($model) { return $m['id'] === $model; }))[0] ?? [];
    if (!empty($info['ratios']) && !in_array($aspect, $info['ratios'], true)) unset($body['aspect_ratio']);
    if ($res !== '' && isset($info['res'][$res])) $body['resolution'] = $res;
    if ($duration > 0 && (empty($info['durations']) || in_array($duration, $info['durations'], true))) $body['duration'] = $duration;
    $r = vid_or_req($c, 'POST', '/videos', $body); $id = (string)($r['id'] ?? '');
    if ($id === '' || !preg_match('/^[\w-]{4,100}$/', $id)) throw new RuntimeException(t('لم يرجع OpenRouter رقم المهمة.'));
    return $id;
}

/** حالة المهمة: ['status' => pending|in_progress|completed|failed, 'names' => [...]] وبيحفظ الفيديو في المكتبة لما يخلص */
function vid_or_poll(string $id): array
{
    $c = img_or_conn(); if (!$c) throw new RuntimeException(t('اربط OpenRouter أولًا من صفحة التكاملات.'));
    $r = vid_or_req($c, 'GET', '/videos/' . rawurlencode($id)); $st = (string)($r['status'] ?? 'pending');
    if ($st !== 'completed') return ['status' => in_array($st, ['failed', 'cancelled', 'expired'], true) ? 'failed' : $st, 'error' => (string)($r['error'] ?? '')];
    $wid = (int)ws('id'); $names = [];
    foreach (array_slice((array)($r['unsigned_urls'] ?? $r['urls'] ?? []), 0, 2) as $i => $u) {
        $path = parse_url((string)$u, PHP_URL_PATH) . (($q = parse_url((string)$u, PHP_URL_QUERY)) ? '?' . $q : '');
        $bytes = strpos((string)$u, 'openrouter.ai/') !== false || strpos((string)$u, '/videos/') !== false ? vid_or_req($c, 'GET', preg_replace('~^.*?/videos/~', '/videos/', $path), null, true) : null;
        if (!$bytes) continue;
        $dir = hf_lib_dir($wid); if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $nm = gmdate('Ymd-His') . '-' . substr(md5($id . $i . microtime(true)), 0, 10) . '.mp4';
        file_put_contents($dir . '/' . $nm, $bytes); @chmod($dir . '/' . $nm, 0640);
        @file_put_contents($dir . '/' . $nm . '.json', json_encode(['src' => '', 't' => time(), 'provider' => 'openrouter']));
        $names[] = $nm;
    }
    if (function_exists('media_sync_ai')) { try { media_sync_ai($wid); } catch (Throwable $e) {} }
    if (function_exists('ai_log_usage')) { try { ai_log_usage($wid, 'video', 'openrouter', vid_or_model($c), ['cost' => (float)($r['usage']['cost'] ?? 0), 'prompt_tokens' => 0, 'completion_tokens' => 0]); } catch (Throwable $e) {} }
    return ['status' => $names ? 'completed' : 'failed', 'names' => $names, 'error' => $names ? '' : t('اكتمل الفيديو لكن تعذّر تنزيله.')];
}

/** سعر تقريبي للصورة الواحدة بالدولار: توكنز الصورة الناتجة (~1290 توكن للصورة 1024) أو سعر ثابت للصورة */
function img_or_price(array $p): ?float
{
    if (isset($p['image_output']) && (float)$p['image_output'] > 0) return round((float)$p['image_output'] * 1290, 4);
    if (isset($p['image']) && (float)$p['image'] > 0.001) return round((float)$p['image'], 4);
    return null;
}

/** سعر تقريبي للثانية بالدولار عند 720p (المنصة بتعلن الأسعار بأشكال مختلفة حسب الموديل) */
function vid_or_price(array $p): ?float
{
    $pick = function (array $keys) use ($p) { foreach ($keys as $k) if (isset($p[$k]) && (float)$p[$k] > 0) return (float)$p[$k]; return null; };
    if (($v = $pick(['duration_seconds_720p', 'duration_seconds_with_audio_720p', 'duration_seconds_768p', 'duration_seconds', 'duration_seconds_with_audio', 'duration_seconds_1080p'])) !== null) return round($v, 4);
    if (($v = $pick(['cents_per_video_output_second_720p', 'cents_per_video_output_second_768p', 'cents_per_video_output_second', 'cents_per_video_output_second_1080p', 'cents_per_video_output_second_480p'])) !== null) return round($v / 100, 4);
    // بالتوكنز: 1280×720 × 24 فريم ÷ 1024 ≈ 21,600 توكن للثانية
    if (($v = $pick(['video_tokens', 'video_tokens_without_audio'])) !== null) return round($v * 21600, 4);
    foreach ($p as $k => $v) if (preg_match('/second/', $k) && !preg_match('/long|reference|input/', $k) && (float)$v > 0) return round(strpos($k, 'cents') !== false ? (float)$v / 100 : (float)$v, 4);
    return null;
}

/** سعر الثانية لكل جودة يدعمها الموديل: ['480p' => 0.05, '720p' => 0.1, ...] (null لو مش معروف) */
function vid_or_res_prices(array $p, array $resList): array
{
    $px = ['360p' => [640, 360], '480p' => [854, 480], '720p' => [1280, 720], '768p' => [1366, 768], '1080p' => [1920, 1080], '1K' => [1024, 1024], '2K' => [2560, 1440], '4K' => [3840, 2160], '4k' => [3840, 2160]];
    $out = [];
    foreach ($resList as $r) {
        $r = (string)$r; $lk = strtolower($r); $v = null;
        foreach (['duration_seconds_' . $lk, 'duration_seconds_with_audio_' . $lk] as $k) if ($v === null && isset($p[$k]) && (float)$p[$k] > 0) $v = (float)$p[$k];
        if ($v === null && isset($p['cents_per_video_output_second_' . $lk]) && (float)$p['cents_per_video_output_second_' . $lk] > 0) $v = (float)$p['cents_per_video_output_second_' . $lk] / 100;
        if ($v === null && isset($px[$r]) && ($t = (float)($p['video_tokens'] ?? $p['video_tokens_without_audio'] ?? 0)) > 0) $v = $t * $px[$r][0] * $px[$r][1] * 24 / 1024;
        if ($v === null) foreach (['duration_seconds', 'duration_seconds_with_audio'] as $k) if ($v === null && isset($p[$k]) && (float)$p[$k] > 0) $v = (float)$p[$k]; // سعر واحد لكل الجودات
        $out[$r] = $v === null ? null : round($v, 4);
    }
    return $out;
}

/**
 * شبكة أمان: بعض الموديلات بترجع الصورة ومعاها شريط فاضي (أبيض أو لون واحد) على حرف.
 * لو الشريط أكتر من 6% من الصورة: نقصه، وبعدين نقص من المنتصف عشان نرجع لنفس نسبة الصورة الأصلية.
 */
function img_trim_bands(string $bytes): string
{
    if (!function_exists('imagecreatefromstring')) return $bytes;
    $im = @imagecreatefromstring($bytes); if (!$im) return $bytes;
    $w = imagesx($im); $h = imagesy($im); if ($w < 64 || $h < 64) { imagedestroy($im); return $bytes; }
    // عمود/صف «فاضي» = كل نقطة فيه قريبة من لون الركن وتقريبًا من غير تفاصيل
    $ref = imagecolorat($im, 0, 0); $rr = ($ref >> 16) & 255; $rg = ($ref >> 8) & 255; $rb = $ref & 255;
    $flat = function ($x0, $y0, $dx, $dy, $n) use ($im, $rr, $rg, $rb) {
        $bad = 0; $step = max(1, intdiv($n, 60));
        for ($i = 0; $i < $n; $i += $step) { $c = imagecolorat($im, $x0 + $dx * $i, $y0 + $dy * $i); if (abs((($c >> 16) & 255) - $rr) + abs((($c >> 8) & 255) - $rg) + abs(($c & 255) - $rb) > 40) $bad++; }
        return $bad <= 1;
    };
    $l = 0; while ($l < $w * 0.45 && $flat($l, 0, 0, 1, $h)) $l++;
    $r = 0; while ($r < $w * 0.45 && $flat($w - 1 - $r, 0, 0, 1, $h)) $r++;
    $t = 0; while ($t < $h * 0.45 && $flat(0, $t, 1, 0, $w)) $t++;
    $b = 0; while ($b < $h * 0.45 && $flat(0, $h - 1 - $b, 1, 0, $w)) $b++;
    $min = 0.06;
    if ($l < $w * $min) $l = 0; if ($r < $w * $min) $r = 0; if ($t < $h * $min) $t = 0; if ($b < $h * $min) $b = 0;
    if (!$l && !$r && !$t && !$b) { imagedestroy($im); return $bytes; }
    // 3 أو 4 حواف سادة = غالبًا عنصر في نص خلفية سادة (صورة منتج): مانلمسهاش
    if ((int)(bool)$l + (int)(bool)$r + (int)(bool)$t + (int)(bool)$b >= 3) { imagedestroy($im); return $bytes; }
    // لو نفس لون الشريط مالي جزء كبير من وسط الصورة، فهو جزء من التصميم مش شريط زيادة
    $same = 0; $tot = 0; for ($x = (int)($w * 0.3); $x < $w * 0.7; $x += max(1, intdiv($w, 80))) for ($y = (int)($h * 0.3); $y < $h * 0.7; $y += max(1, intdiv($h, 80))) { $c = imagecolorat($im, $x, $y); $tot++; if (abs((($c >> 16) & 255) - $rr) + abs((($c >> 8) & 255) - $rg) + abs(($c & 255) - $rb) <= 40) $same++; }
    if ($tot && $same / $tot > 0.35) { imagedestroy($im); return $bytes; }
    $cw = $w - $l - $r; $ch = $h - $t - $b; if ($cw < $w * 0.4 || $ch < $h * 0.4) { imagedestroy($im); return $bytes; }
    // نرجع لنسبة الصورة الأصلية بقص من المنتصف، وبعدين نكبّر لنفس المقاس
    $ratio = $w / $h; if ($cw / $ch > $ratio) { $nw = (int)round($ch * $ratio); $l += intdiv($cw - $nw, 2); $cw = $nw; } else { $nh = (int)round($cw / $ratio); $t += intdiv($ch - $nh, 2); $ch = $nh; }
    $out = imagecreatetruecolor($w, $h); imagecopyresampled($out, $im, 0, 0, $l, $t, $w, $h, $cw, $ch);
    ob_start(); imagepng($out); $png = (string)ob_get_clean(); imagedestroy($im); imagedestroy($out);
    return $png !== '' ? $png : $bytes;
}

