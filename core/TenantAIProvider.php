<?php
declare(strict_types=1);

/**
 * TenantAIProvider — per-tenant AI provider abstraction layer.
 *
 * Centralises ALL access to AI services so that:
 *  - Every call uses the CURRENT TENANT'S keys only (never platform ENV keys).
 *  - A clear, consistent error is returned when keys are missing.
 *  - Future providers (Anthropic, Gemini, …) can be added without touching
 *    individual API endpoints or service classes.
 *
 * Usage:
 *   TenantAIProvider::requireOpenAI();          // throws if not configured
 *   $ai  = TenantAIProvider::openai();          // OpenAIService with tenant key
 *   $hg  = TenantAIProvider::heygen();          // HeyGenService with tenant key
 *   $ok  = TenantAIProvider::hasOpenAI();       // bool
 *   $all = TenantAIProvider::status();          // array for API/UI responses
 */
class TenantAIProvider
{
    // ── Provider identifiers (extend here for future providers) ─────────────
    public const OPENAI   = 'openai';
    public const HEYGEN   = 'heygen';
    // public const ANTHROPIC = 'anthropic';  // future
    // public const GEMINI    = 'gemini';      // future

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Returns true if the current tenant has a working key for $provider.
     */
    public static function has(string $provider): bool
    {
        return match ($provider) {
            self::OPENAI  => self::hasOpenAI(),
            self::HEYGEN  => self::hasHeyGen(),
            default       => false,
        };
    }

    public static function hasOpenAI(): bool
    {
        return ApiKeyManager::hasTenantOpenAIKey();
    }

    public static function hasHeyGen(): bool
    {
        return ApiKeyManager::hasTenantHeyGenKey();
    }

    /**
     * Throws a structured JSON error response if the provider is not configured.
     * Call at the start of any AI API endpoint.
     *
     * @throws never  (exits via Response::error)
     */
    public static function require(string $provider): void
    {
        if (!self::has($provider)) {
            $label = match ($provider) {
                self::OPENAI => 'OpenAI',
                self::HEYGEN => 'HeyGen',
                default      => $provider,
            };
            Response::error(
                "AI feature unavailable: {$label} API key not configured. " .
                "Go to Settings → Integrations to add your company's {$label} key.",
                402,
                ['provider' => $provider, 'code' => 'ai_key_missing']
            );
            exit;
        }
    }

    public static function requireOpenAI(): void { self::require(self::OPENAI); }
    public static function requireHeyGen(): void  { self::require(self::HEYGEN); }

    /**
     * Return a configured OpenAIService using the CURRENT TENANT'S key only.
     * Call requireOpenAI() first if you want an early error response.
     */
    public static function openai(): OpenAIService
    {
        $key   = ApiKeyManager::getTenantOpenAIKeyStrict();
        $model = ApiKeyManager::getTenantOpenAIModel();
        return new OpenAIService($key ?: null, $model ?: null);
    }

    /**
     * Return a configured HeyGenService using the CURRENT TENANT'S key only.
     */
    public static function heygen(): HeyGenService
    {
        $key = ApiKeyManager::getTenantHeyGenKeyStrict();
        return new HeyGenService($key ?: null);
    }

    /**
     * Return a status array for all providers — safe to expose in API/UI.
     * Never includes actual key values.
     */
    public static function status(?int $tenantId = null): array
    {
        $row = self::tenantRow($tenantId);
        return [
            self::OPENAI => [
                'configured' => !empty($row['openai_api_key']) && ApiKeyManager::decrypt($row['openai_api_key']) !== '',
                'model'      => $row['openai_model'] ?? 'gpt-4o',
                'label'      => 'OpenAI',
            ],
            self::HEYGEN => [
                'configured' => !empty($row['heygen_api_key']) && ApiKeyManager::decrypt($row['heygen_api_key']) !== '',
                'label'      => 'HeyGen',
            ],
        ];
    }

    /** True when BOTH OpenAI and HeyGen are configured (full AI feature set). */
    public static function isFullyConfigured(): bool
    {
        return self::hasOpenAI() && self::hasHeyGen();
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private static function tenantRow(?int $tenantId): array
    {
        try {
            $db  = Database::getInstance();
            $tid = $tenantId ?? (Auth::user()['tenant_id'] ?? null);
            if (!$tid) return [];
            return $db->fetch(
                'SELECT openai_api_key, heygen_api_key, openai_model FROM tenants WHERE id = ?',
                [(int) $tid]
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
