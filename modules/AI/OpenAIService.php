<?php
declare(strict_types=1);

namespace Modules\AI;

use RuntimeException;

/**
 * OpenAIService - Thin, dependency-free client for the OpenAI Chat
 * Completions API using raw cURL.
 *
 * Responsibilities:
 *   - chat():     standard chat completion, returns content + token usage + cost
 *   - chatJson(): forces JSON-mode output and decodes it to an array
 *   - complete(): convenience single-prompt completion returning a string
 *   - logUsage(): persists token usage / cost to the ai_usage_logs table
 *
 * Pricing is computed locally from a per-model price table so the platform
 * can attribute spend per tenant/feature without an extra API round-trip.
 */
class OpenAIService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.openai.com/v1';

    /** Request timeout in seconds. */
    private int $timeout = 120;

    /** Number of automatic retries on transient (429 / 5xx) failures. */
    private int $maxRetries = 2;

    /**
     * Per-model pricing in USD per 1,000 tokens: [input, output].
     * Used for local cost attribution. Unknown models fall back to gpt-4o.
     */
    private array $pricing = [
        'gpt-4o'          => [0.0025, 0.0100],
        'gpt-4o-2024-08-06' => [0.0025, 0.0100],
        'gpt-4o-mini'     => [0.00015, 0.00060],
        'gpt-4-turbo'     => [0.0100, 0.0300],
        'gpt-4'           => [0.0300, 0.0600],
        'gpt-4.1'         => [0.0020, 0.0080],
        'gpt-4.1-mini'    => [0.0004, 0.0016],
        'gpt-3.5-turbo'   => [0.0005, 0.0015],
    ];

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        // Priority: explicit arg → tenant key from DB → platform ENV fallback
        $this->apiKey = $apiKey
            ?? (class_exists('ApiKeyManager') ? \ApiKeyManager::getTenantOpenAIKey() : null)
            ?? ($_ENV['OPENAI_API_KEY'] ?? '');

        $this->model = $model
            ?? (class_exists('ApiKeyManager') ? \ApiKeyManager::getTenantOpenAIModel() : null)
            ?? ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o');
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Standard chat completion.
     *
     * @param array $messages OpenAI-format messages [['role'=>..,'content'=>..], ...]
     * @param array $options  Overrides: model, temperature, max_tokens, top_p,
     *                         frequency_penalty, presence_penalty, response_format,
     *                         seed, stop.
     *
     * @return array{content:string, tokens:array, cost:float, model:string, finish_reason:string, raw:array}
     */
    public function chat(array $messages, array $options = []): array
    {
        $model = (string) ($options['model'] ?? $this->model);

        $payload = [
            'model'    => $model,
            'messages' => $this->sanitizeMessages($messages),
        ];

        // Optional generation parameters.
        $passthrough = [
            'temperature', 'max_tokens', 'top_p', 'frequency_penalty',
            'presence_penalty', 'response_format', 'seed', 'stop', 'n',
            'tools', 'tool_choice',
        ];
        foreach ($passthrough as $key) {
            if (array_key_exists($key, $options)) {
                $payload[$key] = $options[$key];
            }
        }

        if (!isset($payload['temperature'])) {
            $payload['temperature'] = 0.7;
        }

        $response = $this->makeRequest('/chat/completions', $payload);

        $choice  = $response['choices'][0] ?? [];
        $content = (string) ($choice['message']['content'] ?? '');
        $usage   = $response['usage'] ?? [];

        $tokens = [
            'prompt'     => (int) ($usage['prompt_tokens'] ?? 0),
            'completion' => (int) ($usage['completion_tokens'] ?? 0),
            'total'      => (int) ($usage['total_tokens'] ?? 0),
        ];

        return [
            'content'       => $content,
            'tokens'        => $tokens,
            'cost'          => $this->calculateCost($model, $tokens['prompt'], $tokens['completion']),
            'model'         => $model,
            'finish_reason' => (string) ($choice['finish_reason'] ?? ''),
            'raw'           => $response,
        ];
    }

    /**
     * Force JSON-mode output and decode it.
     *
     * When a $schema is supplied, a JSON Schema is enforced via the
     * structured-outputs response_format (json_schema). Otherwise the looser
     * json_object mode is used and the model is instructed in the system
     * prompt to return JSON.
     *
     * @param array $messages OpenAI-format messages.
     * @param array $schema   Optional JSON schema (properties array or a full schema).
     * @param array $options  Same overrides as chat().
     *
     * @return array{data:array, tokens:array, cost:float, model:string, raw:array}
     */
    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        $messages = $this->sanitizeMessages($messages);

        if (!empty($schema)) {
            $options['response_format'] = [
                'type'        => 'json_schema',
                'json_schema' => $this->normalizeSchema($schema),
            ];
        } else {
            $options['response_format'] = ['type' => 'json_object'];
            // json_object mode requires the word "json" to appear in the prompt.
            $messages = $this->ensureJsonHint($messages);
        }

        // Deterministic output is preferable for structured analysis.
        if (!isset($options['temperature'])) {
            $options['temperature'] = 0.2;
        }
        if (!isset($options['max_tokens'])) {
            $options['max_tokens'] = 4096;
        }

        $result = $this->chat($messages, $options);
        $data   = $this->decodeJson($result['content']);

        return [
            'data'   => $data,
            'tokens' => $result['tokens'],
            'cost'   => $result['cost'],
            'model'  => $result['model'],
            'raw'    => $result['raw'],
        ];
    }

    /**
     * Simple single-prompt completion. Returns the assistant text.
     *
     * @param array $options Supports an extra 'system' override plus chat() options.
     */
    public function complete(string $prompt, array $options = []): string
    {
        $system = (string) ($options['system'] ?? 'You are a helpful assistant.');
        unset($options['system']);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt],
        ];

        return $this->chat($messages, $options)['content'];
    }

    /**
     * Persist a usage record to ai_usage_logs.
     *
     * @param array $usage ['model'?, 'prompt'|'prompt_tokens', 'completion'|'completion_tokens',
     *                       'total'|'total_tokens', 'cost'|'cost_usd']
     */
    public function logUsage(
        int $tenantId,
        int $userId,
        string $feature,
        array $usage,
        string $refType = '',
        int $refId = 0
    ): void {
        try {
            if (!class_exists('\Database')) {
                return; // Database layer not available (e.g. AI module used standalone).
            }
            $db = \Database::getInstance();

            $prompt     = (int) ($usage['prompt'] ?? $usage['prompt_tokens'] ?? 0);
            $completion = (int) ($usage['completion'] ?? $usage['completion_tokens'] ?? 0);
            $total      = (int) ($usage['total'] ?? $usage['total_tokens'] ?? ($prompt + $completion));
            $cost       = (float) ($usage['cost'] ?? $usage['cost_usd'] ?? 0.0);

            $db->insert('ai_usage_logs', [
                'tenant_id'         => $tenantId > 0 ? $tenantId : null,
                'user_id'           => $userId > 0 ? $userId : null,
                'feature'           => $feature,
                'model'             => (string) ($usage['model'] ?? $this->model),
                'prompt_tokens'     => $prompt,
                'completion_tokens' => $completion,
                'total_tokens'      => $total,
                'cost_usd'          => round($cost, 6),
                'reference_type'    => $refType !== '' ? $refType : null,
                'reference_id'      => $refId > 0 ? $refId : null,
            ]);
        } catch (\Throwable $e) {
            // Usage logging must never break the primary AI flow.
            $this->errorLog('logUsage failed: ' . $e->getMessage());
        }
    }

    /**
     * Compute USD cost for a request from the local price table.
     */
    public function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $prices = $this->pricing[$model] ?? null;
        if ($prices === null) {
            // Match by family prefix, else fall back to gpt-4o.
            foreach ($this->pricing as $name => $p) {
                if (str_starts_with($model, $name)) {
                    $prices = $p;
                    break;
                }
            }
            $prices = $prices ?? $this->pricing['gpt-4o'];
        }

        $cost = ($promptTokens / 1000.0) * $prices[0]
              + ($completionTokens / 1000.0) * $prices[1];

        return round($cost, 6);
    }

    /**
     * Perform a cURL request against the OpenAI API with retry on transient
     * errors. Returns the decoded JSON body.
     *
     * @throws RuntimeException on hard failure.
     */
    private function makeRequest(string $endpoint, array $data): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured (OPENAI_API_KEY).');
        }

        $url  = $this->baseUrl . $endpoint;
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Failed to encode OpenAI request payload: ' . json_last_error_msg());
        }

        $attempt = 0;
        $lastError = '';

        while ($attempt <= $this->maxRetries) {
            $attempt++;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                ],
            ]);

            $raw      = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            // Network / transport failure.
            if ($raw === false) {
                $lastError = 'cURL error: ' . $curlErr;
                if ($this->shouldRetry($attempt)) {
                    $this->backoff($attempt);
                    continue;
                }
                throw new RuntimeException($lastError);
            }

            $decoded = json_decode((string) $raw, true);

            if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
                return $decoded;
            }

            // Extract API error message when present.
            $apiMessage = is_array($decoded) ? ($decoded['error']['message'] ?? '') : '';
            $lastError  = sprintf(
                'OpenAI API error (HTTP %d): %s',
                $httpCode,
                $apiMessage !== '' ? $apiMessage : substr((string) $raw, 0, 500)
            );

            // Retry on rate-limit / server errors.
            if (($httpCode === 429 || $httpCode >= 500) && $this->shouldRetry($attempt)) {
                $this->backoff($attempt);
                continue;
            }

            $this->errorLog($lastError);
            throw new RuntimeException($lastError);
        }

        throw new RuntimeException($lastError !== '' ? $lastError : 'OpenAI request failed.');
    }

    private function shouldRetry(int $attempt): bool
    {
        return $attempt <= $this->maxRetries;
    }

    private function backoff(int $attempt): void
    {
        // Exponential backoff: 0.5s, 1s, 2s ...
        usleep((int) (500000 * (2 ** ($attempt - 1))));
    }

    /**
     * Decode a JSON string that may be wrapped in markdown code fences or
     * contain leading/trailing prose. Returns [] when unparseable.
     */
    public function decodeJson(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        // Strip ```json ... ``` fences.
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', (string) $content);
            $content = trim((string) $content);
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Best-effort: extract the first {...} or [...] block.
        if (preg_match('/(\{.*\}|\[.*\])/s', $content, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $this->errorLog('Failed to decode JSON from model output: ' . substr($content, 0, 300));
        return [];
    }

    /**
     * Ensure every message has a valid role and string content.
     */
    private function sanitizeMessages(array $messages): array
    {
        $clean = [];
        foreach ($messages as $msg) {
            if (!isset($msg['role'], $msg['content'])) {
                continue;
            }
            $content = $msg['content'];
            if (is_array($content)) {
                // Allow vision/multimodal content arrays through unchanged.
                $clean[] = ['role' => $msg['role'], 'content' => $content];
                continue;
            }
            $clean[] = [
                'role'    => (string) $msg['role'],
                'content' => (string) $content,
            ];
        }
        return $clean;
    }

    /**
     * Guarantee the word "json" appears for json_object mode.
     */
    private function ensureJsonHint(array $messages): array
    {
        foreach ($messages as $msg) {
            if (stripos((string) ($msg['content'] ?? ''), 'json') !== false) {
                return $messages;
            }
        }
        array_unshift($messages, [
            'role'    => 'system',
            'content' => 'Respond with valid JSON only.',
        ]);
        return $messages;
    }

    /**
     * Normalize a schema input into the structured-outputs json_schema shape.
     */
    private function normalizeSchema(array $schema): array
    {
        // Already a full {name, schema} definition.
        if (isset($schema['schema']) && isset($schema['name'])) {
            $schema['strict'] = $schema['strict'] ?? true;
            return $schema;
        }

        // A bare JSON-schema object (has "type" or "properties").
        if (isset($schema['type']) || isset($schema['properties'])) {
            return [
                'name'   => 'response',
                'strict' => false,
                'schema' => $schema,
            ];
        }

        // A plain map of property => type; wrap it.
        return [
            'name'   => 'response',
            'strict' => false,
            'schema' => [
                'type'       => 'object',
                'properties' => $schema,
            ],
        ];
    }

    private function errorLog(string $message): void
    {
        $logDir = $_ENV['APP_LOG_PATH'] ?? (defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : sys_get_temp_dir());
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        @file_put_contents(
            rtrim($logDir, '/\\') . '/openai.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}
