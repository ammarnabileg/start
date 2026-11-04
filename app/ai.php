<?php
/**
 * الذكاء الاصطناعي عن طريق OpenRouter.
 * التوفير: بنبدأ بالموديلات المجانية، ولو مش متاحة نطلع للأرخص فالأرخص،
 * وصاحب الشركة بيحدد أقصى مستوى مسموح (مجاني بس / رخيص / أي موديل).
 */

class AiException extends Exception
{
}

const AI_BASE = 'https://openrouter.ai/api/v1';

function ai_http(string $method, string $path, ?string $key, $body = null, int $timeout = 60): array
{
    $ch = curl_init(AI_BASE . $path);
    $headers = ['Accept: application/json', 'HTTP-Referer: ' . (function_exists('base_url') ? base_url() : 'https://localhost'), 'X-Title: ' . (string)setting('site_name', 'Panel')];
    if ($key) $headers[] = 'Authorization: Bearer ' . $key;
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $headers]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new AiException(t('تعذر الاتصال بـ OpenRouter: %s', $err), 0);
    $data = json_decode($raw, true) ?: [];
    if ($status >= 400) {
        $msg = $data['error']['message'] ?? $data['message'] ?? ('HTTP ' . $status);
        throw new AiException(is_string($msg) ? $msg : json_encode($msg), $status);
    }
    return $data;
}

/** معلومات المفتاح والرصيد */
function ai_key_info(string $key): array
{
    try {
        return ai_http('GET', '/key', $key)['data'] ?? [];
    } catch (AiException $e) {
        throw new Exception($e->getCode() === 401 ? t('مفتاح OpenRouter غير صحيح.') : $e->getMessage());
    }
}

/** قائمة الموديلات مترتبة من الأرخص للأغلى (المجاني الأول). بتتحفظ 6 ساعات. */
function ai_ranked_models(): array
{
    $file = ROOT_DIR . '/uploads/cache/openrouter_models2.json';
    if (is_file($file) && filemtime($file) > time() - 21600) {
        $c = json_decode((string)file_get_contents($file), true);
        if (is_array($c) && $c) return $c;
    }
    $list = ai_http('GET', '/models', null, null, 30)['data'] ?? [];
    $rank = [];
    foreach ($list as $m) {
        $id = (string)($m['id'] ?? '');
        $in = (float)($m['pricing']['prompt'] ?? 1);
        $out = (float)($m['pricing']['completion'] ?? 1);
        $ctx = (int)($m['context_length'] ?? 0);
        $mods = (array)($m['architecture']['input_modalities'] ?? ['text']);
        $outMods = (array)($m['architecture']['output_modalities'] ?? ['text']);
        // لازم يكون موديل نصوص بسياق كفاية للبيانات
        // لازم يكون موديل محادثة نصوص بس (مش صور ولا صوت) وبسياق كفاية للبيانات
        if ($id === '' || $ctx < 16000 || array_values($outMods) !== ['text'] || !in_array('text', $mods, true) || $in < 0 || $out < 0) continue;
        if (preg_match('#(summarize|embed|guard|moderation|rerank|legal)#i', $id) || substr($id, -6) === ':batch') continue;
        if (strpos($id, 'openrouter/auto') === 0) continue;
        $cost = $in * 3000 + $out * 800; // تكلفة تقريبية لسؤال عادي (3000 كلمة داخلة، 800 خارجة)
        $rank[] = ['id' => $id, 'name' => (string)($m['name'] ?? $id), 'cost' => $cost, 'free' => $cost == 0.0, 'ctx' => $ctx, 'in' => round($in * 1e6, 4), 'out' => round($out * 1e6, 4)];
    }
    usort($rank, function ($a, $b) {
        if ($a['cost'] == $b['cost']) return $b['ctx'] <=> $a['ctx'];
        return $a['cost'] <=> $b['cost'];
    });
    @mkdir(dirname($file), 0750, true);
    @file_put_contents($file, json_encode($rank));
    return $rank;
}

/** الموديلات المسموحة حسب المستوى اللي المالك اختاره */
function ai_allowed_models(string $tier): array
{
    $all = ai_ranked_models();
    $free = array_values(array_filter($all, function ($m) { return $m['free']; }));
    $paid = array_values(array_filter($all, function ($m) { return !$m['free']; }));
    if ($tier === 'free') return $free;
    if ($tier === 'cheap') {
        // الرخيص = أقل من نص سنت للسؤال
        return array_merge($free, array_values(array_filter($paid, function ($m) { return $m['cost'] <= 0.005; })));
    }
    return array_merge($free, $paid);
}

/** مزودين الذكاء الاصطناعي اللي البرنامج بيدعمهم */
function ai_provider_keys(): array
{
    return ['openrouter', 'anthropic', 'openai', 'gemini'];
}

/** مهام الذكاء الاصطناعي في المنصة. كل مهمة ليها موديل خاص لو التشغيل التلقائي مقفول. */
function ai_tasks(): array
{
    return [
        'assistant' => ['name' => t('المساعد الذكي'), 'desc' => t('الإجابة عن أسئلة الأداء والأرقام من بيانات الشركة.'), 'icon' => 'M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4z', 'hint' => t('يحتاج موديلًا قويًا في التحليل'), 'max' => 900],
        'compose' => ['name' => t('كتابة المنشورات'), 'desc' => t('كتابة منشور من فكرة، أو تحسين نص مكتوب.'), 'icon' => 'M4 20h4L18.5 9.5a2.1 2.1 0 00-4-4L4 16v4z', 'hint' => t('الموديلات السريعة كافية'), 'max' => 600],
        'reply' => ['name' => t('اقتراح الردود'), 'desc' => t('اقتراح رد على رسالة أو تعليق من سياق المحادثة.'), 'icon' => 'M4 5h16v10H8l-4 4z', 'hint' => t('الأرخص والأسرع هو الأنسب'), 'max' => 300],
    ];
}

/** الموديلات اللي المستخدم علّم عليها لكل مهمة: ['provider|model' => ['assistant', 'reply'], ...] */
function ai_picks(?int $wid = null): array
{
    $wid = $wid ?? (int)ws('id');
    $v = json_decode((string)setting('ai_picks_' . $wid, ''), true);
    return is_array($v) ? $v : [];
}

/**
 * كل الموديلات المتاحة من كل الاتصالات المفعّلة، حتى لو نفس الموديل متاح من أكتر من مزود.
 * كل صف: key, provider, model, name, in, out, cost (للسؤال), free
 */
function ai_catalog(): array
{
    $rows = [];
    foreach (ai_connections() as $c) {
        $p = $c['provider'];
        if ($p === 'openrouter') {
            try { $list = ai_ranked_models(); } catch (Throwable $e) { $list = []; }
            foreach ($list as $m) {
                $rows[] = ['key' => 'openrouter|' . $m['id'], 'provider' => $p, 'model' => $m['id'], 'name' => $m['name'], 'in' => $m['in'] ?? null, 'out' => $m['out'] ?? null, 'cost' => (float)$m['cost'], 'free' => !empty($m['free'])];
            }
            continue;
        }
        foreach ($c['settings']['models'] ?? [] as $m) {
            $pr = ai_model_price($m);
            $cost = ai_question_cost($pr);
            $rows[] = ['key' => $p . '|' . $m, 'provider' => $p, 'model' => $m, 'name' => $m, 'in' => $pr['in'] ?? null, 'out' => $pr['out'] ?? null, 'cost' => $cost, 'free' => false];
        }
    }
    usort($rows, function ($a, $b) {
        if (($a['cost'] === null) !== ($b['cost'] === null)) return $a['cost'] === null ? 1 : -1;
        return $a['cost'] <=> $b['cost'] ?: strcmp($a['key'], $b['key']);
    });
    return $rows;
}

/** الاتصالات اللي المهمة دي هتجربها بالترتيب */
function ai_task_chain(string $task): array
{
    $conns = ai_connections();
    if (ai_mode() !== 'manual') return $conns;
    $byP = [];
    foreach ($conns as $c) $byP[$c['provider']] = $c;
    $chain = [];
    foreach (ai_catalog() as $row) {
        if (!in_array($task, ai_picks()[$row['key']] ?? [], true) || !isset($byP[$row['provider']])) continue;
        $c = $byP[$row['provider']];
        if ($row['provider'] === 'openrouter') $c['settings']['or_model'] = $row['model'];
        else $c['settings']['model'] = $row['model'];
        $c['pick'] = $row;
        $chain[] = $c;
        if (count($chain) >= 6) break;
    }
    // مفيش موديل متعلّم عليه للمهمة دي (أو اتشال): نرجع للتلقائي بدل ما المهمة تقف
    return $chain ?: $conns;
}

/** طريقة اختيار المزود: auto = الأرخص فالأغلى، manual = المزود اللي اختاره المالك بس */
function ai_mode(?int $wid = null): string
{
    $wid = $wid ?? (int)ws('id');
    return setting('ai_mode_' . $wid, 'auto') === 'manual' ? 'manual' : 'auto';
}

/** تكلفة سؤال عادي بالدولار للاتصال ده بالموديل المختار. OpenRouter بيبدأ بالمجاني دايمًا = 0. null = مش معروف. */
function ai_connection_cost(array $c): ?float
{
    if (isset($c['pick'])) return $c['pick']['cost'];
    if ($c['provider'] === 'openrouter') return 0.0;
    $model = (string)($c['settings']['model'] ?? '') ?: ai_default_model($c['settings']['models'] ?? []);
    return ai_question_cost(ai_model_price($model));
}

/** للترتيب: المعروف سعره بالسعر، والمجهول في الآخر */
function ai_cost_score(array $c): float
{
    $cost = ai_connection_cost($c);
    return $cost === null ? 1000 + ai_model_rank((string)($c['settings']['model'] ?? '')) : $cost;
}

/** اتصالات الذكاء الاصطناعي المفعّلة بالترتيب اللي هتتجرب بيه */
function ai_connections(): array
{
    $list = [];
    foreach (ai_provider_keys() as $p) {
        $c = connector($p);
        if ($c && ($c['credentials']['api_key'] ?? '') !== '') { $c['provider'] = $p; $list[] = $c; }
    }
    usort($list, function ($a, $b) { return ai_cost_score($a) <=> ai_cost_score($b); });
    return $list;
}

function ai_connection(): ?array
{
    return ai_connections()[0] ?? null;
}

/** العنوان الأساسي لكل مزود. AI_MOCK_BASE للاختبار المحلي بس. */
function ai_base(string $p): string
{
    $mock = getenv('AI_MOCK_BASE');
    if ($mock) return rtrim($mock, '/') . '/' . $p;
    return ['anthropic' => 'https://api.anthropic.com/v1', 'openai' => 'https://api.openai.com/v1', 'gemini' => 'https://generativelanguage.googleapis.com/v1beta'][$p];
}

/** طلب HTTP لأي مزود */
function ai_req(string $p, string $method, string $path, string $key, $body = null, int $timeout = 90): array
{
    $headers = ['Accept: application/json'];
    $url = ai_base($p) . $path;
    if ($p === 'anthropic') { $headers[] = 'x-api-key: ' . $key; $headers[] = 'anthropic-version: 2023-06-01'; }
    elseif ($p === 'gemini') { $headers[] = 'x-goog-api-key: ' . $key; }
    else { $headers[] = 'Authorization: Bearer ' . $key; }
    $ch = curl_init($url);
    if ($body !== null) { $headers[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE)); }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $headers]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $name = ai_provider_name($p);
    if ($raw === false) throw new AiException(t('تعذر الاتصال بـ %s: %s', $name, $err), 0);
    $data = json_decode($raw, true) ?: [];
    if ($status >= 400) {
        $e = $data['error'] ?? $data;
        if (isset($e[0])) $e = $e[0]['error'] ?? $e[0];
        $msg = is_array($e) ? ($e['message'] ?? ('HTTP ' . $status)) : (string)$e;
        if (in_array($status, [401, 403], true) || stripos((string)$msg, 'api key') !== false && $status === 400) {
            throw new AiException(t('مفتاح %s غير صحيح أو ليس له صلاحية.', $name), 401);
        }
        throw new AiException(is_string($msg) ? $msg : json_encode($msg), $status);
    }
    return $data;
}

function ai_provider_name(string $p): string
{
    return ['openrouter' => 'OpenRouter', 'anthropic' => 'Anthropic (Claude)', 'openai' => 'OpenAI', 'gemini' => 'Google Gemini'][$p] ?? $p;
}

/** الموديلات المتاحة للمفتاح (للنصوص بس)، مترتبة بحيث الأرخص الأول */
function ai_list_models(string $p, string $key): array
{
    $ids = [];
    if ($p === 'gemini') {
        foreach (ai_req($p, 'GET', '/models?pageSize=1000', $key)['models'] ?? [] as $m) {
            if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) {
                $id = preg_replace('#^models/#', '', (string)$m['name']);
                if (strpos($id, 'gemini') === 0 && !preg_match('/image|tts|audio|live|embedding|vision|thinking-exp/i', $id)) $ids[] = $id;
            }
        }
    } else {
        foreach (ai_req($p, 'GET', '/models' . ($p === 'anthropic' ? '?limit=1000' : ''), $key)['data'] ?? [] as $m) {
            $id = (string)($m['id'] ?? '');
            if ($p === 'openai' && (!preg_match('/^(gpt-|o\d|chatgpt)/', $id) || preg_match('/audio|realtime|tts|transcribe|image|search|instruct|embedding|codex|moderation/i', $id))) continue;
            if ($id !== '') $ids[] = $id;
        }
    }
    $ids = array_values(array_unique($ids));
    usort($ids, function ($a, $b) {
        $ca = ai_question_cost(ai_model_price($a)); $cb = ai_question_cost(ai_model_price($b));
        if ($ca !== null && $cb !== null && $ca != $cb) return $ca <=> $cb;
        if (($ca === null) !== ($cb === null)) return $ca === null ? 1 : -1; // المعروف سعره الأول
        return ai_model_rank($a) <=> ai_model_rank($b) ?: strcmp($b, $a);
    });
    return array_slice($ids, 0, 60);
}

/**
 * الأسعار الحقيقية (دولار لكل مليون توكن) من كتالوج OpenRouter العام، اللي بينشر أسعار كل المزودين.
 * بتتحفظ 12 ساعة، ولو الكتالوج مش متاح بنستخدم آخر نسخة محفوظة.
 */
function ai_price_map(): array
{
    static $map = null;
    if ($map !== null) return $map;
    $file = ROOT_DIR . '/uploads/cache/ai_prices.json';
    $old = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    if ($old && filemtime($file) > time() - 43200) return $map = $old;
    try {
        $map = [];
        foreach (ai_http('GET', '/models', null, null, 20)['data'] ?? [] as $m) {
            $id = (string)($m['id'] ?? '');
            if ($id === '' || strpos($id, ':') !== false) continue; // :free و :batch نسخ تانية
            $in = (float)($m['pricing']['prompt'] ?? -1);
            $out = (float)($m['pricing']['completion'] ?? -1);
            if ($in < 0 || $out < 0) continue;
            $map[ai_price_key($id)] = ['in' => round($in * 1e6, 4), 'out' => round($out * 1e6, 4)];
        }
        if ($map) { @mkdir(dirname($file), 0750, true); @file_put_contents($file, json_encode($map)); }
        else $map = $old;
    } catch (Throwable $e) {
        $map = $old;
    }
    return $map;
}

/** توحيد اسم الموديل: anthropic/claude-haiku-5.5 = claude-haiku-5-5 */
function ai_price_key(string $id): string
{
    $id = strtolower($id);
    $id = preg_replace('#^(anthropic|openai|google|models)/#', '', $id);
    $id = preg_replace('/-(\d{8}|\d{4}-\d{2}-\d{2})$/', '', $id); // تاريخ الإصدار
    return str_replace('.', '-', $id);
}

/** سعر موديل مزود مباشر: ['in' => $, 'out' => $] لكل مليون توكن، أو null لو مش معروف */
function ai_model_price(string $model): ?array
{
    $map = ai_price_map();
    $k = ai_price_key($model);
    if (isset($map[$k])) return $map[$k];
    $k2 = preg_replace('/-(latest|preview(-\d+)?)$/', '', $k);
    return $map[$k2] ?? null;
}

/** تكلفة سؤال عادي بالدولار (3000 توكن داخلة و 800 خارجة)، أو null */
function ai_question_cost(?array $price): ?float
{
    return $price ? ($price['in'] * 3000 + $price['out'] * 800) / 1e6 : null;
}

function ai_fmt_cost(?float $c): string
{
    if ($c === null) return t('السعر غير معروف');
    if ($c == 0.0) return t('مجاني');
    return '$' . ($c < 0.001 ? rtrim(rtrim(number_format($c, 5), '0'), '.') : number_format($c, 4));
}

/** ترتيب تقريبي للتكلفة من الاسم (بيتستخدم بس لو السعر الحقيقي مش معروف): الموديلات الصغيرة الأول */
function ai_model_rank(string $id): int
{
    foreach (['flash-lite' => 0, 'nano' => 1, 'haiku' => 1, 'mini' => 2, 'flash' => 2, 'sonnet' => 4, 'pro' => 5, 'opus' => 6] as $k => $r) {
        if (ai_model_has($id, $k)) return $r;
    }
    return 3;
}

/** الكلمة موجودة كجزء مستقل من اسم الموديل (عشان "gemini" ماتتحسبش "mini") */
function ai_model_has(string $id, string $word): bool
{
    return (bool)preg_match('/(^|[-_.\/])' . preg_quote($word, '/') . '($|[-_.])/i', $id);
}

/** الموديل الافتراضي: أرخص واحد مناسب (مش nano/lite لأنهم ضعاف في العربي) */
function ai_default_model(array $models): string
{
    foreach (['haiku', 'mini', 'flash'] as $k) {
        foreach ($models as $m) if (ai_model_has($m, $k) && !ai_model_has($m, 'lite') && !ai_model_has($m, 'nano') && !preg_match('/preview|exp|latest/i', $m)) return $m;
    }
    return $models[0] ?? '';
}

/** يختبر المفتاح ويرجع الموديلات المتاحة */
function ai_test_key(string $p, string $key): array
{
    $models = ai_list_models($p, $key);
    if (!$models) throw new Exception(t('المفتاح يعمل، لكن لا توجد موديلات نصية متاحة له.'));
    return $models;
}

/** سؤال لمزود مباشر (Anthropic / OpenAI / Gemini) */
function ai_chat_direct(array $c, array $messages, int $maxTokens): array
{
    $p = $c['provider'];
    $key = $c['credentials']['api_key'];
    $model = (string)($c['settings']['model'] ?? '') ?: ai_default_model($c['settings']['models'] ?? []);
    if ($model === '') throw new AiException(t('اختر موديل لـ %s من صفحة التكاملات.', ai_provider_name($p)));
    if ($p === 'anthropic') {
        $system = implode("\n\n", array_column(array_filter($messages, function ($m) { return $m['role'] === 'system'; }), 'content'));
        $msgs = array_values(array_filter($messages, function ($m) { return $m['role'] !== 'system'; }));
        $body = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => $msgs];
        if ($system !== '') $body['system'] = $system;
        $r = ai_req($p, 'POST', '/messages', $key, $body);
        $text = '';
        foreach ($r['content'] ?? [] as $b) if (($b['type'] ?? '') === 'text') $text .= $b['text'];
        $u = $r['usage'] ?? [];
        $u['total_tokens'] = (int)($u['input_tokens'] ?? 0) + (int)($u['output_tokens'] ?? 0);
        return ['text' => trim($text), 'model' => (string)($r['model'] ?? $model), 'usage' => $u];
    }
    // OpenAI، و Gemini من خلال الواجهة المتوافقة مع OpenAI
    $path = $p === 'gemini' ? '/openai/chat/completions' : '/chat/completions';
    $body = ['model' => $model, 'messages' => $messages];
    $body[$p === 'openai' ? 'max_completion_tokens' : 'max_tokens'] = $p === 'openai' && preg_match('/^(o\d|gpt-5)/', $model) ? $maxTokens * 4 : $maxTokens;
    $r = ai_req($p, 'POST', $path, $key, $body);
    return ['text' => trim((string)($r['choices'][0]['message']['content'] ?? '')), 'model' => (string)($r['model'] ?? $model), 'usage' => $r['usage'] ?? []];
}

/**
 * يسأل الذكاء الاصطناعي: بيبدأ بالمزود الأساسي، ولو فشل بيجرب اللي بعده.
 * بيرجع: text, model, usage, provider
 */
function ai_chat(array $messages, int $maxTokens = 900, string $task = 'assistant'): array
{
    $conns = ai_task_chain($task);
    if (!$conns) throw new AiException(t('اربط مزود ذكاء اصطناعي الأول من صفحة التكاملات (OpenRouter أو Anthropic أو OpenAI أو Gemini).'));
    $errors = [];
    foreach ($conns as $c) {
        try {
            $r = $c['provider'] === 'openrouter' ? ai_chat_openrouter($c, $messages, $maxTokens) : ai_chat_direct($c, $messages, $maxTokens);
            if ($r['text'] === '') throw new AiException(t('الموديل رجع رد فاضي.'));
            $r['provider'] = $c['provider'];
            return $r;
        } catch (AiException $e) {
            $errors[] = ai_provider_name($c['provider']) . ': ' . $e->getMessage();
        }
    }
    throw new AiException(count($errors) === 1 ? $errors[0] : t('كل المزودين فشلوا: %s', implode(' | ', $errors)));
}

/**
 * OpenRouter: بيجرب كل مجموعة من 3 موديلات بالترتيب،
 * ولو المجموعة كلها فشلت بيطلع للمجموعة اللي بعدها.
 */
function ai_chat_openrouter(array $c, array $messages, int $maxTokens): array
{
    $tier = (string)($c['settings']['tier'] ?? 'cheap');
    $models = !empty($c['settings']['or_model']) ? [$c['settings']['or_model']] : array_column(ai_allowed_models($tier), 'id');
    if (!$models) throw new AiException(t('مفيش موديلات متاحة في المستوى ده. وسّع المستوى من صفحة التكاملات.'));
    $last = null;
    foreach (array_slice(array_chunk($models, 3), 0, 6) as $group) {
        try {
            $r = ai_http('POST', '/chat/completions', $c['credentials']['api_key'], [
                'models' => $group,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => 0.3,
            ], 90);
            $text = trim((string)($r['choices'][0]['message']['content'] ?? ''));
            if ($text === '') { $last = new AiException(t('الموديل رجع رد فاضي.')); continue; }
            return ['text' => $text, 'model' => (string)($r['model'] ?? $group[0]), 'usage' => $r['usage'] ?? []];
        } catch (AiException $e) {
            $last = $e;
            // مفتاح غلط أو رصيد خلص: مفيش فايدة نكمل
            if (in_array($e->getCode(), [401, 403], true)) break;
            if ($e->getCode() === 402 && $tier !== 'free') break;
        }
    }
    $code = $last ? $last->getCode() : 0;
    if ($code === 402) throw new AiException(t('رصيد OpenRouter خلص. اشحن الرصيد أو خلّي المستوى "مجاني بس".'));
    if ($code === 429) throw new AiException(t('الموديلات المجانية زحمة دلوقتي. جرب بعد دقيقة، أو اسمح بالموديلات الرخيصة.'));
    throw new AiException(t('المساعد مش قادر يرد دلوقتي: %s', $last ? $last->getMessage() : ''));
}

/** تحويل بسيط وآمن للـ Markdown اللي الموديل بيرجعه */
function ai_render(string $md): string
{
    $h = e($md);
    $h = preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $h);
    $lines = explode("\n", $h);
    $out = '';
    $inList = false;
    foreach ($lines as $l) {
        if (preg_match('/^\s*(?:[-*•]|\d+[.)])\s+(.*)$/u', $l, $m)) {
            if (!$inList) { $out .= '<ul class="list-disc ps-5 space-y-1 my-2">'; $inList = true; }
            $out .= '<li>' . $m[1] . '</li>';
            continue;
        }
        if ($inList) { $out .= '</ul>'; $inList = false; }
        if (preg_match('/^#{1,4}\s+(.*)$/u', $l, $m)) { $out .= '<p class="font-semibold mt-3">' . $m[1] . '</p>'; continue; }
        $out .= trim($l) === '' ? '<div class="h-2"></div>' : '<p>' . $l . '</p>';
    }
    if ($inList) $out .= '</ul>';
    return $out;
}
