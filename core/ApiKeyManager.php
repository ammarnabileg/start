<?php
declare(strict_types=1);

/**
 * ApiKeyManager - Per-tenant API key storage with AES-256-CBC encryption.
 *
 * Each company stores its OWN OpenAI and HeyGen keys in the tenants table.
 * Keys are encrypted at rest using the platform APP_KEY so the database
 * alone is not enough to extract them.
 *
 * Usage:
 *   $key = ApiKeyManager::getTenantOpenAIKey();   // current tenant
 *   ApiKeyManager::saveTenantKey($tid, 'openai', 'sk-...');
 */
class ApiKeyManager
{
    private const CIPHER = 'AES-256-CBC';
    private const PREFIX = 'enc1:';

    // ── Encrypt / Decrypt ────────────────────────────────────────────────

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') return '';
        $key = self::derivedKey();
        $iv  = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $enc = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return self::PREFIX . base64_encode($iv . $enc);
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === '' || !str_starts_with($stored, self::PREFIX)) {
            return $stored; // not encrypted (legacy plain text) — return as-is
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false) return '';
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv    = substr($raw, 0, $ivLen);
        $enc   = substr($raw, $ivLen);
        $key   = self::derivedKey();
        $plain = openssl_decrypt($enc, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $plain !== false ? $plain : '';
    }

    // ── Tenant key getters ───────────────────────────────────────────────

    /**
     * Return the active tenant's OpenAI API key (decrypted).
     * STRICT — returns ONLY the tenant's own key, never falls back to ENV.
     * Returns '' if no tenant key is configured.
     */
    public static function getTenantOpenAIKeyStrict(?int $tenantId = null): string
    {
        $row = self::tenantRow($tenantId);
        if ($row && !empty($row['openai_api_key'])) {
            return self::decrypt($row['openai_api_key']);
        }
        return '';
    }

    /**
     * Check whether the current tenant has a non-empty OpenAI key stored.
     */
    public static function hasTenantOpenAIKey(?int $tenantId = null): bool
    {
        return self::getTenantOpenAIKeyStrict($tenantId) !== '';
    }

    /**
     * Check whether the current tenant has a non-empty HeyGen key stored.
     */
    public static function hasTenantHeyGenKey(?int $tenantId = null): bool
    {
        return self::getTenantHeyGenKeyStrict($tenantId) !== '';
    }

    /**
     * Return the active tenant's OpenAI API key (decrypted).
     * With ENV fallback — used only in legacy/backward-compat contexts.
     * Prefer getTenantOpenAIKeyStrict() for new code.
     */
    public static function getTenantOpenAIKey(?int $tenantId = null): string
    {
        $strict = self::getTenantOpenAIKeyStrict($tenantId);
        if ($strict !== '') return $strict;
        // Legacy fallback: allow platform ENV key so existing integrations keep working
        return $_ENV['OPENAI_API_KEY'] ?? '';
    }

    /**
     * Return the active tenant's preferred OpenAI model.
     * Falls back to ENV / gpt-4o.
     */
    public static function getTenantOpenAIModel(?int $tenantId = null): string
    {
        $row = self::tenantRow($tenantId);
        if ($row && !empty($row['openai_model'])) {
            return $row['openai_model'];
        }
        return $_ENV['OPENAI_MODEL'] ?? 'gpt-4o';
    }

    /**
     * Return the active tenant's HeyGen API key (decrypted).
     * STRICT — returns ONLY the tenant's own key, never falls back to ENV.
     */
    public static function getTenantHeyGenKeyStrict(?int $tenantId = null): string
    {
        $row = self::tenantRow($tenantId);
        if ($row && !empty($row['heygen_api_key'])) {
            return self::decrypt($row['heygen_api_key']);
        }
        return '';
    }

    /**
     * Return the active tenant's HeyGen API key (decrypted).
     * With ENV fallback — legacy compat only.
     */
    public static function getTenantHeyGenKey(?int $tenantId = null): string
    {
        $strict = self::getTenantHeyGenKeyStrict($tenantId);
        if ($strict !== '') return $strict;
        return $_ENV['HEYGEN_API_KEY'] ?? '';
    }

    // ── Platform key getters (super admin context only) ──────────────────────

    /** Platform-level OpenAI key from ENV — never use for per-tenant calls. */
    public static function getPlatformOpenAIKey(): string
    {
        return $_ENV['OPENAI_API_KEY'] ?? '';
    }

    /** Platform-level HeyGen key from ENV — never use for per-tenant calls. */
    public static function getPlatformHeyGenKey(): string
    {
        return $_ENV['HEYGEN_API_KEY'] ?? '';
    }

    // ── Tenant key setters ───────────────────────────────────────────────

    /**
     * Securely wipe all API keys for a tenant (call when deleting a company).
     * Sets both encrypted key columns to NULL and logs the wipe in audit_logs.
     */
    public static function wipeTenantKeys(int $tenantId): void
    {
        try {
            $db = Database::getInstance();
            $db->query(
                "UPDATE tenants SET openai_api_key = NULL, heygen_api_key = NULL, updated_at = NOW() WHERE id = ?",
                [$tenantId]
            );
            // Audit trail
            try {
                $db->insert('audit_logs', [
                    'tenant_id'  => $tenantId,
                    'user_id'    => null,
                    'action'     => 'api_keys_wiped',
                    'target'     => 'tenant',
                    'target_id'  => $tenantId,
                    'meta'       => json_encode(['reason' => 'tenant_deleted']),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable) {}
            self::clearCache();
        } catch (\Throwable) {}
    }

    /**
     * Save an API key for a tenant.
     *
     * @param string $service  'openai' | 'heygen' | 'openai_model'
     */
    public static function saveTenantKey(int $tenantId, string $service, string $value): bool
    {
        $db = Database::getInstance();
        $column = match($service) {
            'openai'       => 'openai_api_key',
            'heygen'       => 'heygen_api_key',
            'openai_model' => 'openai_model',
            default        => null,
        };
        if ($column === null) return false;

        // Model is stored plain; keys are encrypted
        $stored = in_array($service, ['openai', 'heygen'], true)
            ? self::encrypt($value)
            : $value;

        $db->query(
            "UPDATE tenants SET `{$column}` = ?, updated_at = NOW() WHERE id = ?",
            [$stored, $tenantId]
        );
        return true;
    }

    /**
     * Test an OpenAI key (makes a cheap /models call).
     */
    public static function testOpenAIKey(string $key): array
    {
        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$key}"],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ok = ($code === 200);
        return ['ok' => $ok, 'message' => $ok ? 'Connection successful' : 'Invalid API key'];
    }

    /**
     * Test a HeyGen key.
     */
    public static function testHeyGenKey(string $key): array
    {
        $ch = curl_init('https://api.heygen.com/v2/avatars');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["X-Api-Key: {$key}"],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ok = ($code === 200);
        return ['ok' => $ok, 'message' => $ok ? 'Connection successful' : 'Invalid API key'];
    }

    // ── Internals ────────────────────────────────────────────────────────

    private static function derivedKey(): string
    {
        $appKey = $_ENV['APP_KEY'] ?? $_ENV['JWT_SECRET'] ?? 'changeme-32-byte-fallback-secret';
        return hash('sha256', $appKey, true); // always 32 bytes
    }

    private static ?array $cachedRow = null;

    private static function tenantRow(?int $tenantId): ?array
    {
        if ($tenantId !== null) {
            try {
                return Database::getInstance()->fetch(
                    'SELECT openai_api_key, heygen_api_key, openai_model FROM tenants WHERE id = ?',
                    [$tenantId]
                ) ?: null;
            } catch (\Throwable) {
                return null;
            }
        }

        // Use currently active tenant from Auth session
        if (self::$cachedRow !== null) return self::$cachedRow;

        try {
            $user = Auth::user();
            $tid  = $user['tenant_id'] ?? null;
            if (!$tid) return null;
            self::$cachedRow = Database::getInstance()->fetch(
                'SELECT openai_api_key, heygen_api_key, openai_model FROM tenants WHERE id = ?',
                [$tid]
            ) ?: null;
            return self::$cachedRow;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Clear in-memory cache (call after saving new keys). */
    public static function clearCache(): void
    {
        self::$cachedRow = null;
    }
}
