<?php

namespace App\Modules\AI;

use App\Core\Database;
use Throwable;

/**
 * Real OpenAI integration over cURL (no SDK).
 *
 * Every public method degrades gracefully when the API key is not configured,
 * returning safe empty/default values so the rest of the platform keeps working
 * in a keyless demo environment. All API traffic is logged to ai_usage_logs.
 */
class OpenAIService
{
    private string $apiKey;
    private string $model;
    private string $base;

    /**
     * Ambient tenant/user context applied to usage logging. Set via setContext()
     * so callers do not have to thread ids through every method call.
     *
     * @var array{tenant_id?:?int,user_id?:?int}
     */
    public static array $context = ['tenant_id' => null, 'user_id' => null];

    public function __construct(?array $openaiConfig = null)
    {
        $cfg = $openaiConfig ?? $this->loadConfig();
        $this->apiKey = (string) ($cfg['api_key'] ?? '');
        $this->model  = (string) ($cfg['model'] ?? 'gpt-4-turbo-preview');
        $this->base   = rtrim((string) ($cfg['base'] ?? 'https://api.openai.com/v1'), '/');
    }

    /**
     * Resolve the openai config block, preferring the global config() helper and
     * falling back to a direct require if the helper is unavailable.
     */
    private function loadConfig(): array
    {
        if (function_exists('config')) {
            $app = config('app');
            if (is_array($app) && isset($app['openai']) && is_array($app['openai'])) {
                return $app['openai'];
            }
        }
        $fallback = '/home/user/start/.claude/worktrees/agent-aee360cb781fcff81/config/app.php';
        if (is_file($fallback)) {
            $app = require $fallback;
            if (is_array($app) && isset($app['openai']) && is_array($app['openai'])) {
                return $app['openai'];
            }
        }
        return ['api_key' => '', 'model' => 'gpt-4-turbo-preview', 'base' => 'https://api.openai.com/v1'];
    }

    /**
     * Set the ambient tenant/user context used when writing usage logs.
     */
    public static function setContext(array $ctx): void
    {
        self::$context['tenant_id'] = isset($ctx['tenant_id']) ? (int) $ctx['tenant_id'] : self::$context['tenant_id'];
        self::$context['user_id']   = isset($ctx['user_id']) ? (int) $ctx['user_id'] : self::$context['user_id'];
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Chat completion.
     *
     * @param array<int,array{role:string,content:string}> $messages
     * @param array<string,mixed> $options Extra body params + optional 'feature'.
     * @return array{content:string,usage:array{prompt_tokens:int,completion_tokens:int,total_tokens:int},raw:array}
     */
    public function chat(array $messages, array $options = []): array
    {
        $feature = (string) ($options['feature'] ?? 'chat');
        unset($options['feature']);

        $body = array_merge([
            'model'       => $this->model,
            'messages'    => array_values($messages),
            'temperature' => 0.7,
            'max_tokens'  => 1200,
        ], $options);

        $decoded = $this->request('/chat/completions', $body);

        $this->trackUsage($feature, $decoded);

        $content = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        $usage   = $decoded['usage'] ?? [];

        return [
            'content' => $content,
            'usage'   => [
                'prompt_tokens'     => (int) ($usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'total_tokens'      => (int) ($usage['total_tokens'] ?? 0),
            ],
            'raw'     => $decoded,
        ];
    }

    /**
     * Chat completion that forces a strict JSON object response and decodes it.
     *
     * Never throws: malformed output is best-effort recovered, and a hard failure
     * yields an empty array so callers can fall back to deterministic logic.
     *
     * @param array<int,array{role:string,content:string}> $messages
     * @param array<string,mixed>|null $schema Optional hint appended to the system
     *        instruction describing the desired shape.
     * @return array<string,mixed>
     */
    public function chatJSON(array $messages, ?array $schema = null): array
    {
        $feature = 'chat_json';
        $options = [];
        foreach ($messages as $k => $m) {
            // Allow callers to smuggle a feature via a non-numeric key; otherwise ignore.
            if ($k === 'feature') {
                $feature = (string) $m;
                unset($messages[$k]);
            }
        }

        $jsonInstruction = 'You must respond with a single, strictly valid JSON object and nothing else. '
            . 'Do not wrap it in Markdown code fences. Do not include commentary before or after the JSON.';
        if ($schema !== null) {
            $jsonInstruction .= ' The JSON must match this shape (keys and types): '
                . json_encode($schema, JSON_UNESCAPED_SLASHES);
        }

        $prepared = $this->ensureSystemInstruction(array_values($messages), $jsonInstruction);

        $body = [
            'model'           => $this->model,
            'messages'        => $prepared,
            'temperature'     => 0.4,
            'max_tokens'      => 2000,
            'response_format' => ['type' => 'json_object'],
        ];

        $decoded = $this->request('/chat/completions', $body);
        $this->trackUsage($feature, $decoded);

        $content = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            return [];
        }

        return $this->parseJsonLoose($content);
    }

    /**
     * Streaming chat completion. Invokes $callback($chunkText) for each delta.
     * Best-effort: on any failure $callback('') is called once and the method
     * returns without throwing.
     *
     * @param array<int,array{role:string,content:string}> $messages
     * @param array<string,mixed> $options
     */
    public function stream(array $messages, callable $callback, array $options = []): void
    {
        $feature = (string) ($options['feature'] ?? 'chat_stream');
        unset($options['feature']);

        if (!$this->isConfigured()) {
            // Degrade: emit the configured-fallback text so a live UI shows something.
            $callback('(AI is not configured. Please set OPENAI_API_KEY.)');
            return;
        }

        $body = array_merge([
            'model'       => $this->model,
            'messages'    => array_values($messages),
            'temperature' => 0.7,
            'max_tokens'  => 1200,
            'stream'      => true,
        ], $options);

        $buffer = '';
        $handled = false;

        $write = function ($ch, string $data) use (&$buffer, &$handled, $callback): int {
            $buffer .= $data;
            // SSE frames are separated by a blank line; process complete lines.
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);
                if ($line === '' || strncmp($line, 'data:', 5) !== 0) {
                    continue;
                }
                $payload = trim(substr($line, 5));
                if ($payload === '' || $payload === '[DONE]') {
                    continue;
                }
                $json = json_decode($payload, true);
                if (is_array($json)) {
                    $chunk = $json['choices'][0]['delta']['content'] ?? null;
                    if (is_string($chunk) && $chunk !== '') {
                        $handled = true;
                        $callback($chunk);
                    }
                }
            }
            return strlen($data);
        };

        try {
            $ch = curl_init($this->base . '/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($body),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                    'Accept: text/event-stream',
                ],
                CURLOPT_WRITEFUNCTION  => $write,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_RETURNTRANSFER => false,
            ]);
            curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($err !== '' && !$handled) {
                $callback('');
            }
        } catch (Throwable $e) {
            if (!$handled) {
                $callback('');
            }
        }
    }

    /**
     * Create an embedding vector for a piece of text.
     *
     * @return array<int,float> Empty when unconfigured or on error.
     */
    public function embed(string $text): array
    {
        if (!$this->isConfigured()) {
            return [];
        }
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $body = [
            'model' => 'text-embedding-3-small',
            'input' => mb_substr($text, 0, 8000),
        ];
        $decoded = $this->request('/embeddings', $body);
        $this->trackUsage('embedding', $decoded);

        $vector = $decoded['data'][0]['embedding'] ?? null;
        if (!is_array($vector)) {
            return [];
        }
        return array_map(static fn($v) => (float) $v, $vector);
    }

    /**
     * Record token usage + estimated cost. Failures here never break the caller.
     */
    public function trackUsage(string $feature, array $response): void
    {
        try {
            $usage  = $response['usage'] ?? [];
            $prompt = (int) ($usage['prompt_tokens'] ?? 0);
            $comp   = (int) ($usage['completion_tokens'] ?? 0);
            $total  = (int) ($usage['total_tokens'] ?? ($prompt + $comp));

            $model = (string) ($response['model'] ?? $this->model);
            $cost  = $this->estimateCost($model, $prompt, $comp, $total);

            $db = Database::instance();
            $db->insert('ai_usage_logs', [
                'tenant_id'   => self::$context['tenant_id'],
                'user_id'     => self::$context['user_id'],
                'feature'     => $feature,
                'model'       => $model,
                'tokens_used' => $total,
                'cost'        => $cost,
            ]);
        } catch (Throwable $e) {
            // Logging must never interrupt an AI call.
            if (function_exists('logger')) {
                logger('ai_usage_logs insert failed: ' . $e->getMessage(), 'warning');
            }
        }
    }

    /**
     * Estimate USD cost from a small per-1K-token price map keyed by model substring.
     */
    private function estimateCost(string $model, int $promptTokens, int $completionTokens, int $totalTokens): float
    {
        // [inputPer1K, outputPer1K]
        $prices = [
            'gpt-4o-mini'           => [0.00015, 0.0006],
            'gpt-4o'                => [0.005, 0.015],
            'gpt-4-turbo'           => [0.01, 0.03],
            'gpt-4'                 => [0.03, 0.06],
            'gpt-3.5'               => [0.0005, 0.0015],
            'text-embedding-3-small' => [0.00002, 0.0],
            'text-embedding'        => [0.0001, 0.0],
        ];

        $in = 0.002;
        $out = 0.002;
        $lower = strtolower($model);
        foreach ($prices as $needle => [$pin, $pout]) {
            if (str_contains($lower, $needle)) {
                $in = $pin;
                $out = $pout;
                break;
            }
        }

        if ($promptTokens > 0 || $completionTokens > 0) {
            $cost = ($promptTokens / 1000.0) * $in + ($completionTokens / 1000.0) * $out;
        } else {
            // Only a total is known: blend the two rates.
            $cost = ($totalTokens / 1000.0) * (($in + $out) / 2.0);
        }

        return round($cost, 6);
    }

    /**
     * Ensure the message list starts with (or augments) a system instruction.
     *
     * @param array<int,array{role:string,content:string}> $messages
     * @return array<int,array{role:string,content:string}>
     */
    private function ensureSystemInstruction(array $messages, string $instruction): array
    {
        foreach ($messages as $i => $m) {
            if (($m['role'] ?? '') === 'system') {
                $messages[$i]['content'] = trim((string) $m['content']) . "\n\n" . $instruction;
                return array_values($messages);
            }
        }
        array_unshift($messages, ['role' => 'system', 'content' => $instruction]);
        return array_values($messages);
    }

    /**
     * Tolerant JSON parsing: try as-is, strip code fences, then extract the first
     * balanced {...} block. Returns [] when nothing usable is found.
     *
     * @return array<string,mixed>
     */
    private function parseJsonLoose(string $content): array
    {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Strip Markdown code fences if present.
        $stripped = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($content));
        if (is_string($stripped)) {
            $decoded = json_decode(trim($stripped), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Extract the first balanced object.
        $start = strpos($content, '{');
        if ($start !== false) {
            $depth = 0;
            $len = strlen($content);
            for ($i = $start; $i < $len; $i++) {
                $chr = $content[$i];
                if ($chr === '{') {
                    $depth++;
                } elseif ($chr === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $candidate = substr($content, $start, $i - $start + 1);
                        $decoded = json_decode($candidate, true);
                        if (is_array($decoded)) {
                            return $decoded;
                        }
                        break;
                    }
                }
            }
        }

        return [];
    }

    /**
     * Low-level request with retry/backoff. Returns the decoded response array.
     * When the API key is empty, returns a structured fallback so chat-style
     * callers degrade gracefully instead of erroring.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function request(string $path, array $body, bool $stream = false): array
    {
        if (!$this->isConfigured()) {
            return $this->fallbackResponse($path);
        }

        $url     = $this->base . $path;
        $payload = json_encode($body);
        $attempts = 0;
        $maxAttempts = 3;
        $lastError = '';

        while ($attempts < $maxAttempts) {
            $attempts++;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);

            $raw      = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            // Transport-level failure: retry with backoff.
            if ($raw === false || $curlErr !== '') {
                $lastError = $curlErr !== '' ? $curlErr : 'unknown cURL error';
                $this->backoff($attempts, $maxAttempts);
                continue;
            }

            // Retry on rate-limit / server errors.
            if ($httpCode === 429 || $httpCode >= 500) {
                $lastError = 'HTTP ' . $httpCode . ': ' . substr((string) $raw, 0, 300);
                $this->backoff($attempts, $maxAttempts);
                continue;
            }

            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                $lastError = 'Non-JSON response: ' . substr((string) $raw, 0, 300);
                // A 2xx with malformed body is not retryable in a useful way.
                break;
            }

            if ($httpCode >= 400) {
                $msg = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
                if (function_exists('logger')) {
                    logger('OpenAI API error (' . $httpCode . '): ' . $msg, 'error');
                }
                // 4xx (other than 429) is a client error; do not retry.
                return $this->fallbackResponse($path);
            }

            return $decoded;
        }

        if ($lastError !== '' && function_exists('logger')) {
            logger('OpenAI request failed after ' . $attempts . ' attempts: ' . $lastError, 'error');
        }

        return $this->fallbackResponse($path);
    }

    private function backoff(int $attempt, int $maxAttempts): void
    {
        if ($attempt < $maxAttempts) {
            // Exponential backoff: 1s, then 2s.
            sleep($attempt);
        }
    }

    /**
     * Structured safe fallback so chat/embedding callers degrade gracefully.
     *
     * @return array<string,mixed>
     */
    private function fallbackResponse(string $path): array
    {
        if (str_contains($path, 'embeddings')) {
            return ['data' => [['embedding' => []]], 'usage' => ['total_tokens' => 0]];
        }
        return [
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => '(AI is not configured. Please set OPENAI_API_KEY.)'],
            ]],
            'usage'   => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            'model'   => $this->model,
        ];
    }
}
