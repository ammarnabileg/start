<?php
/**
 * الـ Connectors: الخدمات الخارجية اللي كل شركة بتربطها بمفاتيحها.
 * عشان تضيف خدمة جديدة بعدين: ضيف عنصر هنا بنفس الشكل، وهتظهر لوحدها في صفحة Connectors.
 * المفاتيح السرية بتتخزن مشفرة.
 */

function connector_registry(): array
{
    return [
        'zernio' => [
            'name' => 'Zernio', 'category' => 'social',
            'logo' => 'Z',
            'description' => t('النشر والجدولة والرسائل والتعليقات على السوشيال ميديا.'),
            'docs' => '',
            'help' => t('حط مفتاح الـ API اللي استلمته من مزود الخدمة.'),
            'color' => '#111827',
            'fields' => [
                ['key' => 'api_key', 'label' => 'API Key', 'secret' => true, 'placeholder' => 'sk_...'],
            ],
            // بيتأكد إن المفاتيح شغالة قبل الحفظ. يرجع وصف قصير أو يرمي Exception
            'test' => function (array $c): string {
                $profiles = (new Zernio($c['api_key']))->profiles();
                return count($profiles) . ' Profile';
            },
        ],
        'smtp' => [
            'name' => t('الإيميل (SMTP)'), 'category' => 'email',
            'owner_only' => true, 'single' => true,
            'logo' => '@',
            'description' => t('سيرفر إيميل خاص بالشركة دي لإرسال التقارير والتنبيهات. لو مش موجود، بيستخدم إيميل المنصة.'),
            'docs' => '',
            'help' => t('بيانات الإيميل من الاستضافة (cPanel ← Email Accounts) أو Gmail / Outlook. البرنامج بيجرب الدخول قبل الحفظ.'),
            'color' => '#0F766E',
            'fields' => [
                ['key' => 'host', 'label' => t('سيرفر SMTP'), 'placeholder' => 'mail.yourdomain.com'],
                ['key' => 'port', 'label' => t('البورت'), 'placeholder' => '465', 'default' => '465'],
                ['key' => 'secure', 'label' => t('التشفير'), 'type' => 'select', 'options' => ['ssl' => 'SSL (465)', 'tls' => 'TLS (587)', 'none' => t('من غير تشفير (25)')], 'default' => 'ssl'],
                ['key' => 'user', 'label' => t('اسم المستخدم'), 'placeholder' => 'reports@yourdomain.com'],
                ['key' => 'pass', 'label' => t('كلمة السر'), 'secret' => true],
                ['key' => 'from', 'label' => t('إيميل المرسل'), 'placeholder' => 'reports@yourdomain.com', 'optional' => true],
                ['key' => 'from_name', 'label' => t('اسم المرسل'), 'optional' => true],
            ],
            'test' => function (array $c): string {
                smtp_check(['host' => $c['host'], 'port' => (int)$c['port'], 'secure' => $c['secure'], 'user' => $c['user'], 'pass' => $c['pass']]);
                return t('الدخول شغال · %s', $c['host']);
            },
        ],
        'openrouter' => [
            'name' => 'OpenRouter', 'category' => 'ai',
            'single' => true,
            'logo' => 'AI',
            'description' => t('الذكاء الاصطناعي للمساعد الذكي. بيبدأ بالموديلات المجانية وبعدها الأرخص.'),
            'docs' => 'https://openrouter.ai/settings/keys',
            'help' => t('هتلاقي المفتاح في openrouter.ai ← Keys.'),
            'color' => '#6467F2',
            'fields' => [
                ['key' => 'api_key', 'label' => 'API Key', 'secret' => true, 'placeholder' => 'sk-or-...'],
            ],
            'test' => function (array $c): string {
                $info = ai_key_info($c['api_key']);
                $left = $info['limit_remaining'] ?? null;
                return $left === null ? t('شغال') : t('الرصيد المتبقي: $%s', number_format((float)$left, 2));
            },
        ],
        'anthropic' => [
            'name' => 'Anthropic (Claude)', 'category' => 'ai', 'single' => true, 'ai_models' => true,
            'logo' => 'A', 'color' => '#D97757',
            'description' => t('موديلات Claude للمساعد الذكي. الافتراضي Haiku لأنه الأسرع والأرخص.'),
            'docs' => 'https://console.anthropic.com/settings/keys',
            'help' => t('المفتاح من console.anthropic.com ← API Keys. يتطلب رصيدًا مدفوعًا.'),
            'fields' => [['key' => 'api_key', 'label' => 'API Key', 'secret' => true, 'placeholder' => 'sk-ant-...']],
            'test' => function (array $c): string {
                return t('%s موديل متاح', count(ai_test_key('anthropic', $c['api_key'])));
            },
        ],
        'openai' => [
            'name' => 'OpenAI', 'category' => 'ai', 'single' => true, 'ai_models' => true,
            'logo' => 'O', 'color' => '#10A37F',
            'description' => t('موديلات GPT للمساعد الذكي. الافتراضي موديل mini لأنه الأرخص.'),
            'docs' => 'https://platform.openai.com/api-keys',
            'help' => t('المفتاح من platform.openai.com ← API keys. يتطلب رصيدًا مدفوعًا.'),
            'fields' => [['key' => 'api_key', 'label' => 'API Key', 'secret' => true, 'placeholder' => 'sk-...']],
            'test' => function (array $c): string {
                return t('%s موديل متاح', count(ai_test_key('openai', $c['api_key'])));
            },
        ],
        'gemini' => [
            'name' => 'Google Gemini', 'category' => 'ai', 'single' => true, 'ai_models' => true,
            'logo' => 'G', 'color' => '#4285F4',
            'description' => t('موديلات Gemini للمساعد الذكي. فيه حصة مجانية يومية.'),
            'docs' => 'https://aistudio.google.com/apikey',
            'help' => t('المفتاح من aistudio.google.com ← Get API key. الخطة المجانية كافية للاستخدام الخفيف.'),
            'fields' => [['key' => 'api_key', 'label' => 'API Key', 'secret' => true, 'placeholder' => 'AIza...']],
            'test' => function (array $c): string {
                return t('%s موديل متاح', count(ai_test_key('gemini', $c['api_key'])));
            },
        ],
        'higgsfield' => [
            'name' => 'Higgsfield', 'category' => 'creative', 'single' => true,
            'logo' => 'H', 'color' => '#0B0B0F',
            'description' => t('توليد الصور بالذكاء الاصطناعي من وصف مكتوب، وإضافتها للمنشور مباشرة.'),
            'docs' => 'https://cloud.higgsfield.ai/api-keys',
            'help' => t('من Higgsfield ← API Keys: انسخ Key ID و Key Secret. التوليد يستهلك من رصيدك هناك.'),
            'fields' => [
                ['key' => 'key_id', 'label' => 'Key ID', 'placeholder' => 'xxxxxxxx-xxxx-...'],
                ['key' => 'key_secret', 'label' => 'Key Secret', 'secret' => true],
                ['key' => 'model', 'label' => t('مسار الموديل'), 'placeholder' => '/higgsfield-ai/soul/v2/standard', 'default' => '/higgsfield-ai/soul/v2/standard', 'optional' => true],
                ['key' => 'video_model', 'label' => t('مسار موديل الفيديو'), 'placeholder' => '/higgsfield/cinema-studio/4.0', 'default' => '/higgsfield/cinema-studio/4.0', 'optional' => true],
                ['key' => 'style', 'label' => t('ستايل ثابت للشركة'), 'placeholder' => t('مثلاً: ألوان أزرق وأبيض، إضاءة طبيعية، تصوير منتجات احترافي'), 'optional' => true],
            ],
            'test' => function (array $c): string {
                hf_request($c, 'GET', '/models/usage?' . http_build_query(['start' => gmdate('Y-m-d', time() - 86400 * 7), 'end' => gmdate('Y-m-d'), 'timeframe' => 'day']));
                return t('المفتاح يعمل');
            },
        ],
    ];
}

/** كل اتصالات خدمة معينة في الشركة (مفكوكة التشفير)، الأقدم الأول */
function connections(string $provider, ?int $workspaceId = null, bool $includeDisabled = false): array
{
    if (!$includeDisabled) {
        return array_values(array_filter(connections($provider, $workspaceId, true), function ($c) { return empty($c['settings']['disabled']); }));
    }
    static $cache = [];
    $wid = $workspaceId ?? (int)ws('id');
    if (!$wid) {
        return [];
    }
    $k = $wid . ':' . $provider;
    if (!array_key_exists($k, $cache)) {
        $st = db()->prepare("SELECT * FROM connectors WHERE workspace_id = ? AND provider = ? ORDER BY id");
        $st->execute([$wid, $provider]);
        $rows = $st->fetchAll();
        foreach ($rows as &$row) {
            $row['credentials'] = json_decode(decrypt_secret($row['credentials']), true) ?: [];
            $row['settings'] = json_decode((string)$row['settings'], true) ?: [];
            $row['label'] = (string)($row['label'] ?? '') !== '' ? $row['label'] : t('اتصال %s', $row['id']);
        }
        unset($row);
        $cache[$k] = $rows;
    }
    return $cache[$k];
}

/** أول اتصال (الأساسي) للخدمة، أو null */
function connector(string $provider, ?int $workspaceId = null): ?array
{
    $all = connections($provider, $workspaceId);
    return $all[0] ?? null;
}

function connector_secret(string $provider, string $field): string
{
    $c = connector($provider);
    return (string)($c['credentials'][$field] ?? '');
}

function connector_setting(string $provider, string $field)
{
    $c = connector($provider);
    return $c['settings'][$field] ?? null;
}

/** يضيف اتصال جديد، أو يعدّل اتصال موجود لو اتبعت $id */
function save_connector(PDO $pdo, int $wid, string $provider, array $credentials, array $settings = [], string $info = '', ?string $label = null, ?int $id = null): int
{
    $enc = encrypt_secret(json_encode($credentials));
    if ($id) {
        $pdo->prepare("UPDATE connectors SET credentials = ?, settings = ?, info = ?, label = COALESCE(?, label), checked_at = NOW() WHERE id = ? AND workspace_id = ?")
            ->execute([$enc, json_encode($settings), $info, $label, $id, $wid]);
        return $id;
    }
    $pdo->prepare("INSERT INTO connectors (workspace_id, provider, credentials, settings, info, label, checked_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")
        ->execute([$wid, $provider, $enc, json_encode($settings), $info, $label]);
    return (int)$pdo->lastInsertId();
}

/* ---------- Higgsfield ---------- */

function hf_base(): string
{
    $mock = getenv('AI_MOCK_BASE');
    return $mock ? rtrim($mock, '/') . '/higgsfield' : 'https://api.higgsfield.ai';
}

/** طلب لـ Higgsfield. $path ممكن يبقى رابط كامل (status_url) بس لازم يكون على نفس الدومين */
function hf_request(array $c, string $method, string $path, ?array $body = null): array
{
    $url = strpos($path, 'https://') === 0 || strpos($path, 'http://') === 0 ? $path : hf_base() . $path;
    if (strpos($url, hf_base() . '/') !== 0) throw new Exception('Invalid Higgsfield URL');
    $ch = curl_init($url);
    $h = ['Accept: application/json', 'Authorization: Key ' . $c['key_id'] . ':' . $c['key_secret']];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE)); }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $h]);
    $raw = curl_exec($ch); $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($raw === false) throw new Exception(t('تعذر الاتصال بـ %s: %s', 'Higgsfield', $err));
    $d = json_decode($raw, true) ?: [];
    if ($st === 401 || $st === 403) throw new Exception(t('مفتاح %s غير صحيح أو ليس له صلاحية.', 'Higgsfield'));
    if ($st >= 400) {
        $m = $d['detail'] ?? $d['message'] ?? ('HTTP ' . $st);
        throw new Exception(is_string($m) ? $m : json_encode($m, JSON_UNESCAPED_UNICODE));
    }
    return $d;
}

/** مكتبة الصور المولدة للشركة: uploads/ai/{wid}/ */
function hf_lib_dir(int $wid): string
{
    return ROOT_DIR . '/uploads/ai/' . $wid;
}

/** بيحفظ صورة من رابط في مكتبة الشركة ويرجع اسمها، أو null */
function hf_lib_save(int $wid, string $url): ?string
{
    $dir = hf_lib_dir($wid);
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $tmp = tempnam(sys_get_temp_dir(), 'hf');
    $ch = curl_init($url); $fp = fopen($tmp, 'wb');
    curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3]);
    $ok = curl_exec($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400; curl_close($ch); fclose($fp);
    $mime = $ok ? (string)mime_content_type($tmp) : '';
    $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'video/mp4' => 'mp4'][$mime] ?? null;
    if (!$ok || !$ext || filesize($tmp) > ($ext === 'mp4' ? 200 : 25) * 1048576) { @unlink($tmp); return null; }
    $name = gmdate('Ymd-His') . '-' . substr(md5($url), 0, 10) . '.' . $ext;
    rename($tmp, $dir . '/' . $name);
    // رابط Higgsfield الأصلي (صالح 7 أيام) عشان تحويل الصورة لفيديو
    @file_put_contents($dir . '/' . $name . '.json', json_encode(['src' => $url, 't' => time()]));
    if (function_exists('media_sync_ai')) { try { media_sync_ai($wid); } catch (Throwable $e) {} }
    return $name;
}

/** آخر صور المكتبة (الأحدث الأول) */
function hf_lib_list(int $wid, int $limit = 24): array
{
    $files = glob(hf_lib_dir($wid) . '/*.{png,jpg,webp,mp4}', GLOB_BRACE) ?: [];
    rsort($files);
    return array_map('basename', array_slice($files, 0, $limit));
}

function hf_lib_valid(string $name): bool
{
    return (bool)preg_match('/^\d{8}-\d{6}-[a-f0-9]{10}\.(png|jpg|webp|mp4)$/', $name);
}
