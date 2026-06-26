<?php
declare(strict_types=1);

class ApiKeyManager
{
    public static function getOpenAIKey(?int $tenantId = null): ?string
    {
        $tid = $tenantId ?? (class_exists('Tenant') ? Tenant::id() : null);
        if (!$tid) return null;
        $row = Database::getInstance()->fetch(
            "SELECT openai_api_key FROM tenant_ai_settings WHERE tenant_id = ?", [$tid]
        );
        return $row['openai_api_key'] ?? null;
    }

    public static function getHeyGenKey(?int $tenantId = null): ?string
    {
        $tid = $tenantId ?? (class_exists('Tenant') ? Tenant::id() : null);
        if (!$tid) return null;
        $row = Database::getInstance()->fetch(
            "SELECT heygen_api_key FROM tenant_ai_settings WHERE tenant_id = ?", [$tid]
        );
        return $row['heygen_api_key'] ?? null;
    }

    public static function getOpenAIModel(?int $tenantId = null): string
    {
        $tid = $tenantId ?? (class_exists('Tenant') ? Tenant::id() : null);
        if (!$tid) return 'gpt-4o';
        $row = Database::getInstance()->fetch(
            "SELECT openai_model FROM tenant_ai_settings WHERE tenant_id = ?", [$tid]
        );
        return $row['openai_model'] ?? 'gpt-4o';
    }

    public static function validateOpenAI(string $key): bool
    {
        if (!$key || !str_starts_with($key, 'sk-')) return false;
        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$key}"],
        ]);
        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    public static function validateHeyGen(string $key): bool
    {
        if (!$key) return false;
        $ch = curl_init('https://api.heygen.com/v1/user/remaining_quota');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ["X-Api-Key: {$key}"],
        ]);
        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    public static function callOpenAI(array $messages, ?int $tenantId = null, array $options = []): ?array
    {
        $key   = self::getOpenAIKey($tenantId);
        $model = self::getOpenAIModel($tenantId);
        if (!$key) return null;

        $payload = array_merge([
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 2000,
        ], $options);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$key}",
                "Content-Type: application/json",
            ],
        ]);
        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$response) return null;
        $data = json_decode($response, true);

        // Log usage
        if (isset($data['usage']) && $tenantId) {
            try {
                $tid = $tenantId ?? (class_exists('Tenant') ? Tenant::id() : null);
                if ($tid) {
                    Database::getInstance()->insert('ai_usage_logs', [
                        'tenant_id'         => $tid,
                        'feature'           => $options['feature'] ?? 'general',
                        'provider'          => 'openai',
                        'model'             => $model,
                        'prompt_tokens'     => $data['usage']['prompt_tokens'] ?? 0,
                        'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                        'total_tokens'      => $data['usage']['total_tokens'] ?? 0,
                        'status'            => 'success',
                        'created_at'        => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Throwable) {}
        }

        return $data;
    }
}
