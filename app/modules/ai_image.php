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
function img_openai_generate(string $prompt, string $aspect = '1:1', int $n = 1): array
{
    $c = img_openai_conn(); if (!$c) throw new RuntimeException(t('اربط OpenAI أولًا من صفحة التكاملات.'));
    $wid = (int)ws('id'); $budget = function_exists('ai_budget') ? ai_budget($wid) : 0;
    if ($budget > 0 && function_exists('ai_month_spend') && ai_month_spend($wid) >= $budget) throw new RuntimeException(t('وصلت الشركة لحد استهلاك الذكاء الاصطناعي هذا الشهر ($%s). صاحب الشركة يقدر يرفعه من صفحة التكاملات.', number_format($budget, 2)));
    $model = img_openai_model(); $size = img_openai_size($aspect); $q = img_openai_quality(); $n = max(1, min(4, $n));
    @set_time_limit(180);
    $ch = curl_init(ai_base('openai') . '/images/generations');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 170, CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $c['credentials']['api_key']],
        CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'prompt' => mb_substr($prompt, 0, 30000), 'size' => $size, 'quality' => $q, 'n' => $n], JSON_UNESCAPED_UNICODE)]);
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
