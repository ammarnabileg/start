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
            'name' => 'Zernio',
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
            'name' => t('الإيميل (SMTP)'),
            'single' => true,
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
            'name' => 'OpenRouter',
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
    ];
}

/** كل اتصالات خدمة معينة في الشركة (مفكوكة التشفير)، الأقدم الأول */
function connections(string $provider, ?int $workspaceId = null): array
{
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
