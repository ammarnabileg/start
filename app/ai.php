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
    $file = ROOT_DIR . '/uploads/cache/openrouter_models.json';
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
        if (preg_match('#(summarize|embed|guard|moderation|rerank|legal)#i', $id)) continue;
        if (strpos($id, 'openrouter/auto') === 0) continue;
        $cost = $in * 3000 + $out * 800; // تكلفة تقريبية لسؤال عادي (3000 كلمة داخلة، 800 خارجة)
        $rank[] = ['id' => $id, 'name' => (string)($m['name'] ?? $id), 'cost' => $cost, 'free' => $cost == 0.0, 'ctx' => $ctx];
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

function ai_connection(): ?array
{
    $c = connector('openrouter');
    return ($c && ($c['credentials']['api_key'] ?? '') !== '') ? $c : null;
}

/**
 * يسأل الموديل. بيجرب كل مجموعة من 3 موديلات بالترتيب (OpenRouter بيحاول التلاتة)،
 * ولو المجموعة كلها فشلت بيطلع للمجموعة اللي بعدها.
 * بيرجع: text, model, usage
 */
function ai_chat(array $messages, int $maxTokens = 900): array
{
    $c = ai_connection();
    if (!$c) throw new AiException(t('اربط OpenRouter الأول من صفحة التكاملات.'));
    $tier = (string)($c['settings']['tier'] ?? 'cheap');
    $models = array_column(ai_allowed_models($tier), 'id');
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
