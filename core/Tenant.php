<?php
declare(strict_types=1);

class Tenant
{
    private static ?int   $tenantId   = null;
    private static ?array $tenantData = null;
    private static ?array $aiSettings = null;

    public static function set(int $tenantId): void
    {
        if (self::$tenantId === $tenantId) return;
        self::$tenantId   = $tenantId;
        self::$tenantData = null;
        self::$aiSettings = null;
        if (class_exists('Database', false)) {
            Database::getInstance()->setTenantId($tenantId);
        }
    }

    public static function id(): ?int { return self::$tenantId; }

    public static function current(): ?array
    {
        if (!self::$tenantId) return null;
        if (self::$tenantData) return self::$tenantData;
        self::$tenantData = Database::getInstance()->fetch(
            "SELECT * FROM tenants WHERE id = ?", [self::$tenantId]
        ) ?: null;
        return self::$tenantData;
    }

    public static function getSetting(string $key, mixed $default = null): mixed
    {
        if (!self::$tenantId) return $default;
        $val = Database::getInstance()->fetchColumn(
            "SELECT value FROM tenant_settings WHERE tenant_id = ? AND `key` = ?",
            [self::$tenantId, $key]
        );
        return $val !== false ? $val : $default;
    }

    public static function setSetting(string $key, mixed $value): void
    {
        if (!self::$tenantId) return;
        $db = Database::getInstance();
        $existing = $db->fetch(
            "SELECT id FROM tenant_settings WHERE tenant_id = ? AND `key` = ?",
            [self::$tenantId, $key]
        );
        if ($existing) {
            $db->update('tenant_settings', ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $existing['id']]);
        } else {
            $db->insert('tenant_settings', ['tenant_id' => self::$tenantId, 'key' => $key, 'value' => $value, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public static function getAiSettings(): ?array
    {
        if (!self::$tenantId) return null;
        if (self::$aiSettings !== null) return self::$aiSettings;
        self::$aiSettings = Database::getInstance()->fetch(
            "SELECT * FROM tenant_ai_settings WHERE tenant_id = ?",
            [self::$tenantId]
        ) ?: null;
        return self::$aiSettings;
    }

    public static function hasOpenAI(): bool
    {
        $ai = self::getAiSettings();
        return $ai && !empty($ai['openai_api_key']);
    }

    public static function hasHeyGen(): bool
    {
        $ai = self::getAiSettings();
        return $ai && !empty($ai['heygen_api_key']) && !empty($ai['enable_video_interviews']);
    }

    public static function getOpenAIKey(): ?string
    {
        $ai = self::getAiSettings();
        return $ai['openai_api_key'] ?? null;
    }

    public static function getHeyGenKey(): ?string
    {
        $ai = self::getAiSettings();
        return $ai['heygen_api_key'] ?? null;
    }

    public static function getOpenAIModel(): string
    {
        $ai = self::getAiSettings();
        return $ai['openai_model'] ?? 'gpt-4o';
    }

    public static function invalidateCache(): void
    {
        self::$tenantData = null;
        self::$aiSettings = null;
    }
}
