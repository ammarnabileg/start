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
            'description' => t('النشر والجدولة والرسائل والتعليقات على السوشيال ميديا.'),
            'docs' => 'https://zernio.com/dashboard/api-keys',
            'help' => t('هتلاقي المفتاح في zernio.com ← API Keys ← Create.'),
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
    ];
}

/** بيانات connector في الشركة الحالية (مفكوكة التشفير) أو null */
function connector(string $provider, ?int $workspaceId = null): ?array
{
    static $cache = [];
    $wid = $workspaceId ?? (int)ws('id');
    if (!$wid) {
        return null;
    }
    $k = $wid . ':' . $provider;
    if (!array_key_exists($k, $cache)) {
        $st = db()->prepare("SELECT * FROM connectors WHERE workspace_id = ? AND provider = ?");
        $st->execute([$wid, $provider]);
        $row = $st->fetch();
        if ($row) {
            $row['credentials'] = json_decode(decrypt_secret($row['credentials']), true) ?: [];
            $row['settings'] = json_decode((string)$row['settings'], true) ?: [];
        }
        $cache[$k] = $row ?: null;
    }
    return $cache[$k];
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

function save_connector(PDO $pdo, int $wid, string $provider, array $credentials, array $settings = [], string $info = ''): void
{
    $pdo->prepare("INSERT INTO connectors (workspace_id, provider, credentials, settings, info, checked_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE credentials = VALUES(credentials), settings = VALUES(settings), info = VALUES(info), checked_at = NOW()")
        ->execute([$wid, $provider, encrypt_secret(json_encode($credentials)), json_encode($settings), $info]);
}
