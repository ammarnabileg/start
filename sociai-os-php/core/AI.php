<?php
/**
 * SociAI OS - AI API Client
 * Unified interface to Anthropic Claude, OpenAI GPT, DALL-E, Stability, Whisper.
 */

declare(strict_types=1);

namespace SociAI\Core;

use RuntimeException;

class AIException extends RuntimeException {}

class AI
{
    private const MAX_RETRIES     = 3;
    private const RETRY_DELAY_MS  = 1000; // 1 second base
    private const DEFAULT_TIMEOUT = 90;

    // --------------------------------------------------------
    // Claude (Anthropic)
    // --------------------------------------------------------
    public static function callClaude(
        string $prompt,
        string $systemPrompt = '',
        int    $maxTokens    = 1024,
        float  $temperature  = 0.7,
        string $model        = ''
    ): array {
        $apiKey = ANTHROPIC_API_KEY;
        if (empty($apiKey)) {
            throw new AIException("Anthropic API key not configured.");
        }

        $model = $model ?: ANTHROPIC_MODEL;

        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'temperature'=> $temperature,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        $response = self::httpPost(
            ANTHROPIC_API_URL,
            $payload,
            [
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ]
        );

        if (!isset($response['content'][0]['text'])) {
            throw new AIException("Unexpected Claude response: " . json_encode($response));
        }

        $inputTokens  = $response['usage']['input_tokens']  ?? 0;
        $outputTokens = $response['usage']['output_tokens'] ?? 0;

        return [
            'text'         => $response['content'][0]['text'],
            'input_tokens' => $inputTokens,
            'output_tokens'=> $outputTokens,
            'cost_usd'     => self::calculateCost($model, $inputTokens, $outputTokens),
            'model'        => $response['model'] ?? $model,
            'stop_reason'  => $response['stop_reason'] ?? null,
        ];
    }

    // --------------------------------------------------------
    // OpenAI GPT
    // --------------------------------------------------------
    public static function callOpenAI(
        string $prompt,
        string $systemPrompt = '',
        int    $maxTokens    = 1024,
        float  $temperature  = 0.7,
        string $model        = ''
    ): array {
        $apiKey = OPENAI_API_KEY;
        if (empty($apiKey)) {
            throw new AIException("OpenAI API key not configured.");
        }

        $model    = $model ?: OPENAI_MODEL;
        $messages = [];
        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
        ];

        $response = self::httpPost(
            'https://api.openai.com/v1/chat/completions',
            $payload,
            ['Authorization' => 'Bearer ' . $apiKey]
        );

        $text = $response['choices'][0]['message']['content'] ?? '';
        $inputTokens  = $response['usage']['prompt_tokens']     ?? 0;
        $outputTokens = $response['usage']['completion_tokens'] ?? 0;

        return [
            'text'         => $text,
            'input_tokens' => $inputTokens,
            'output_tokens'=> $outputTokens,
            'cost_usd'     => self::calculateCost($model, $inputTokens, $outputTokens),
            'model'        => $response['model'] ?? $model,
            'finish_reason'=> $response['choices'][0]['finish_reason'] ?? null,
        ];
    }

    // --------------------------------------------------------
    // Auto-select best available AI
    // --------------------------------------------------------
    public static function generate(
        string $prompt,
        string $systemPrompt = '',
        int    $maxTokens    = 1024,
        float  $temperature  = 0.7
    ): array {
        if (!empty(ANTHROPIC_API_KEY)) {
            return self::callClaude($prompt, $systemPrompt, $maxTokens, $temperature);
        }
        if (!empty(OPENAI_API_KEY)) {
            return self::callOpenAI($prompt, $systemPrompt, $maxTokens, $temperature);
        }
        throw new AIException("No AI API key configured. Set ANTHROPIC_API_KEY or OPENAI_API_KEY.");
    }

    // --------------------------------------------------------
    // Image Generation
    // --------------------------------------------------------
    public static function generateImage(
        string $prompt,
        int    $width  = 1024,
        int    $height = 1024,
        string $style  = 'vivid'
    ): array {
        // Try DALL-E 3 first, fall back to Stability
        if (!empty(OPENAI_API_KEY)) {
            return self::generateImageDallE($prompt, $width, $height, $style);
        }
        if (!empty(STABILITY_API_KEY)) {
            return self::generateImageStability($prompt, $width, $height);
        }
        throw new AIException("No image generation API key configured.");
    }

    private static function generateImageDallE(string $prompt, int $width, int $height, string $style): array
    {
        // DALL-E 3 supports 1024x1024, 1024x1792, 1792x1024
        $size = match (true) {
            $height > $width  => '1024x1792',
            $width  > $height => '1792x1024',
            default           => '1024x1024',
        };

        $response = self::httpPost(
            'https://api.openai.com/v1/images/generations',
            [
                'model'           => 'dall-e-3',
                'prompt'          => $prompt,
                'size'            => $size,
                'style'           => $style,
                'quality'         => 'hd',
                'response_format' => 'url',
                'n'               => 1,
            ],
            ['Authorization' => 'Bearer ' . OPENAI_API_KEY]
        );

        return [
            'url'      => $response['data'][0]['url'] ?? '',
            'revised_prompt' => $response['data'][0]['revised_prompt'] ?? $prompt,
            'provider' => 'dalle3',
        ];
    }

    private static function generateImageStability(string $prompt, int $width, int $height): array
    {
        $response = self::httpPost(
            'https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image',
            [
                'text_prompts' => [['text' => $prompt, 'weight' => 1]],
                'cfg_scale'    => 7,
                'height'       => $height,
                'width'        => $width,
                'samples'      => 1,
                'steps'        => 30,
            ],
            [
                'Authorization' => 'Bearer ' . STABILITY_API_KEY,
                'Accept'        => 'application/json',
            ]
        );

        $b64 = $response['artifacts'][0]['base64'] ?? '';
        // Save to temp file
        $tmpPath = CACHE_PATH . '/' . Security::generateToken(16) . '.png';
        file_put_contents($tmpPath, base64_decode($b64));

        return [
            'url'      => $tmpPath,
            'provider' => 'stability',
            'base64'   => $b64,
        ];
    }

    // --------------------------------------------------------
    // Audio Transcription (Whisper)
    // --------------------------------------------------------
    public static function transcribeAudio(string $audioPath, string $language = 'en'): array
    {
        if (empty(OPENAI_API_KEY)) {
            throw new AIException("OpenAI API key required for Whisper transcription.");
        }
        if (!file_exists($audioPath)) {
            throw new AIException("Audio file not found: {$audioPath}");
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.openai.com/v1/audio/transcriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . OPENAI_API_KEY],
            CURLOPT_POSTFIELDS     => [
                'file'     => new \CURLFile($audioPath),
                'model'    => 'whisper-1',
                'language' => $language,
                'response_format' => 'verbose_json',
            ],
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) throw new AIException("Whisper curl error: {$err}");

        $response = json_decode($raw, true);
        if ($code !== 200) {
            throw new AIException("Whisper API error {$code}: " . json_encode($response));
        }

        return [
            'text'     => $response['text'] ?? '',
            'language' => $response['language'] ?? $language,
            'duration' => $response['duration'] ?? null,
            'segments' => $response['segments'] ?? [],
        ];
    }

    // --------------------------------------------------------
    // Text Embeddings
    // --------------------------------------------------------
    public static function embedText(string $text, string $model = 'text-embedding-3-small'): array
    {
        if (empty(OPENAI_API_KEY)) {
            throw new AIException("OpenAI API key required for embeddings.");
        }
        $response = self::httpPost(
            'https://api.openai.com/v1/embeddings',
            ['input' => $text, 'model' => $model],
            ['Authorization' => 'Bearer ' . OPENAI_API_KEY]
        );
        return [
            'embedding'     => $response['data'][0]['embedding'] ?? [],
            'tokens_used'   => $response['usage']['total_tokens'] ?? 0,
        ];
    }

    // --------------------------------------------------------
    // Cost calculation
    // --------------------------------------------------------
    public static function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        // Pricing per 1M tokens (USD), updated May 2025
        $pricing = [
            // Anthropic
            'claude-opus-4-5'         => ['in' => 15.00,  'out' => 75.00],
            'claude-sonnet-4-6'       => ['in' => 3.00,   'out' => 15.00],
            'claude-haiku-3-5'        => ['in' => 0.80,   'out' => 4.00],
            // OpenAI
            'gpt-4o'                  => ['in' => 5.00,   'out' => 15.00],
            'gpt-4o-mini'             => ['in' => 0.15,   'out' => 0.60],
            'gpt-4-turbo'             => ['in' => 10.00,  'out' => 30.00],
            'gpt-3.5-turbo'           => ['in' => 0.50,   'out' => 1.50],
            // Embeddings
            'text-embedding-3-small'  => ['in' => 0.02,   'out' => 0],
            'text-embedding-3-large'  => ['in' => 0.13,   'out' => 0],
        ];

        // Find best match (model strings may include version suffixes)
        $rates = null;
        foreach ($pricing as $key => $rate) {
            if (str_starts_with($model, $key) || $model === $key) {
                $rates = $rate;
                break;
            }
        }

        if (!$rates) {
            // Default fallback pricing
            $rates = ['in' => 5.00, 'out' => 15.00];
        }

        $inputCost  = ($inputTokens  / 1_000_000) * $rates['in'];
        $outputCost = ($outputTokens / 1_000_000) * $rates['out'];
        return round($inputCost + $outputCost, 8);
    }

    // --------------------------------------------------------
    // Log AI task to DB
    // --------------------------------------------------------
    public static function logTask(
        string  $agentType,
        string  $taskName,
        array   $input,
        array   $output,
        ?string $brandId = null,
        int     $tokens  = 0,
        float   $cost    = 0.0
    ): string {
        $db = Database::getInstance();
        $id = Security::generateUUID();
        $db->insert('agent_tasks', [
            'id'          => $id,
            'brand_id'    => $brandId,
            'agent_type'  => $agentType,
            'task_name'   => $taskName,
            'input_data'  => json_encode($input),
            'output_data' => json_encode($output),
            'status'      => 'completed',
            'progress'    => 100,
            'tokens_used' => $tokens,
            'cost_usd'    => $cost,
            'started_at'  => date('Y-m-d H:i:s'),
            'completed_at'=> date('Y-m-d H:i:s'),
        ]);
        return $id;
    }

    // --------------------------------------------------------
    // HTTP helper with retry logic
    // --------------------------------------------------------
    private static function httpPost(string $url, array $payload, array $headers = []): array
    {
        $defaultHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $mergedHeaders  = array_merge($defaultHeaders, $headers);
        $headerLines    = array_map(
            fn($k, $v) => "{$k}: {$v}",
            array_keys($mergedHeaders),
            array_values($mergedHeaders)
        );

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $lastError    = null;
        $lastResponse = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::DEFAULT_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => $headerLines,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $raw    = curl_exec($ch);
            $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err    = curl_error($ch);
            curl_close($ch);

            if ($err) {
                $lastError = "cURL error: {$err}";
            } else {
                $decoded = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $lastError = "JSON decode error: " . json_last_error_msg();
                } elseif ($code >= 500) {
                    // Server error — retry
                    $lastError = "Server error {$code}: " . json_encode($decoded);
                } elseif ($code === 429) {
                    // Rate limit — retry with back-off
                    $retryAfter = (int)($decoded['error']['retry_after'] ?? ($attempt * 2));
                    $lastError = "Rate limited (429). Retry after {$retryAfter}s.";
                    usleep(min($retryAfter * 1_000_000, 60_000_000));
                } elseif ($code >= 400) {
                    // Client error — do not retry
                    throw new AIException("AI API client error {$code}: " . json_encode($decoded));
                } else {
                    return $decoded;
                }
            }

            if ($attempt < self::MAX_RETRIES) {
                usleep(self::RETRY_DELAY_MS * 1000 * $attempt);
            }
        }

        throw new AIException("AI API failed after " . self::MAX_RETRIES . " attempts. Last error: " . $lastError);
    }

    // --------------------------------------------------------
    // Streaming (SSE) support for Claude
    // --------------------------------------------------------
    public static function streamClaude(
        string   $prompt,
        string   $systemPrompt,
        callable $onChunk,
        int      $maxTokens = 2048
    ): void {
        if (empty(ANTHROPIC_API_KEY)) {
            throw new AIException("Anthropic API key not configured.");
        }

        $payload = json_encode([
            'model'      => ANTHROPIC_MODEL,
            'max_tokens' => $maxTokens,
            'stream'     => true,
            'system'     => $systemPrompt,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        $ch = curl_init();
        $buffer = '';
        curl_setopt_array($ch, [
            CURLOPT_URL        => ANTHROPIC_API_URL,
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT    => 120,
            CURLOPT_HTTPHEADER => [
                'x-api-key: '       . ANTHROPIC_API_KEY,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$buffer, $onChunk) {
                $buffer .= $data;
                $lines = explode("\n", $buffer);
                $buffer = array_pop($lines);
                foreach ($lines as $line) {
                    if (str_starts_with($line, 'data: ')) {
                        $json = json_decode(substr($line, 6), true);
                        if ($json && isset($json['delta']['text'])) {
                            $onChunk($json['delta']['text']);
                        }
                    }
                }
                return strlen($data);
            },
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
