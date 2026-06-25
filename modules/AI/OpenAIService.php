<?php

class OpenAIService
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gpt-4o-mini')
    {
        $this->apiKey = $apiKey;
        $this->model  = $model;
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Send a chat completion request to OpenAI.
     *
     * @param  array<array{role:string,content:string}> $messages
     * @param  array<string,mixed>                      $options
     * @return array{content:string,usage:array{prompt_tokens:int,completion_tokens:int,total_tokens:int}}|null
     */
    public function chat(array $messages, array $options = []): ?array
    {
        if (!$this->hasKey()) {
            return null;
        }

        $payload = array_merge([
            'model'    => $this->model,
            'messages' => $messages,
        ], $options);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Length: ' . strlen($body),
                ]),
                'content'       => $body,
                'timeout'       => 120,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents('https://api.openai.com/v1/chat/completions', false, $context);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        if (!is_array($data) || isset($data['error'])) {
            return null;
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            return null;
        }

        $usage = $data['usage'] ?? [];

        return [
            'content' => $content,
            'usage'   => [
                'prompt_tokens'     => (int) ($usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'total_tokens'      => (int) ($usage['total_tokens'] ?? 0),
            ],
        ];
    }

    /**
     * Load API key and model from system_settings for the given tenant.
     */
    public static function forTenant(int $tenantId): self
    {
        $db = Database::getInstance();

        $apiKey = (string) ($db->fetchColumn(
            "SELECT value FROM system_settings WHERE tenant_id = ? AND `key` = 'openai_api_key' LIMIT 1",
            [$tenantId]
        ) ?? '');

        $model = (string) ($db->fetchColumn(
            "SELECT value FROM system_settings WHERE tenant_id = ? AND `key` = 'openai_model' LIMIT 1",
            [$tenantId]
        ) ?? '');

        if ($model === '') {
            $model = (string) ($db->fetchColumn(
                "SELECT value FROM system_settings WHERE tenant_id IS NULL AND `key` = 'openai_model' LIMIT 1"
            ) ?? '');
        }

        if ($model === '') {
            $model = 'gpt-4o-mini';
        }

        return new self($apiKey, $model);
    }

    /**
     * Record token usage in ai_usage_logs.
     *
     * @param array{prompt_tokens:int,completion_tokens:int,total_tokens:int} $usage
     */
    public function logUsage(
        int $tenantId,
        int $userId,
        string $feature,
        array $usage,
        ?int $jobId = null
    ): void {
        $db = Database::getInstance();

        $db->insert('ai_usage_logs', [
            'tenant_id'         => $tenantId,
            'user_id'           => $userId,
            'feature'           => $feature,
            'prompt_tokens'     => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
            'total_tokens'      => $usage['total_tokens'] ?? 0,
            'job_id'            => $jobId,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);
    }
}
