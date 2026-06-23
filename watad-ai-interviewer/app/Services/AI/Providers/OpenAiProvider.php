<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\LlmProvider;
use App\Services\AI\LlmResult;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI implementation (Chat Completions) behind the same interface, so switching providers is
 * a config change (config('watad.ai.provider') = 'openai'). Anthropic-style system blocks and
 * tools are translated to OpenAI's shape. Kept deliberately small; extend as needed.
 */
final class OpenAiProvider implements LlmProvider
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('watad.ai.openai_api_key');
    }

    public function chat(array $params): LlmResult
    {
        $messages = $this->translateMessages($params);

        $body = [
            'model'       => $params['model'],
            'max_tokens'  => $params['maxTokens'] ?? 1024,
            'messages'    => $messages,
        ];

        if (! empty($params['tools'])) {
            $body['tools'] = array_map(fn (array $t) => [
                'type'     => 'function',
                'function' => [
                    'name'        => $t['name'],
                    'description' => $t['description'] ?? '',
                    'parameters'  => $t['input_schema'] ?? ['type' => 'object'],
                ],
            ], $params['tools']);
        }

        $resp = Http::withToken($this->apiKey)
            ->withOptions(['curl' => [CURLOPT_SSL_VERSION => CURL_SSLVERSION_DEFAULT]])
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', $body)
            ->throw()
            ->json();

        return $this->normalize($resp);
    }

    public function stream(array $params, callable $onDelta): LlmResult
    {
        $result = $this->chat($params);
        if ($result->text !== '') {
            $onDelta($result->text);
        }
        return $result;
    }

    private function translateMessages(array $params): array
    {
        $messages = [];

        // Fold Anthropic system blocks into a single OpenAI system message.
        $system = collect($params['system'] ?? [])
            ->map(fn ($b) => is_array($b) ? ($b['text'] ?? '') : (string) $b)
            ->filter()
            ->implode("\n\n");
        if ($system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        foreach ($params['messages'] ?? [] as $m) {
            $content = $m['content'];
            if (is_array($content)) {
                $content = collect($content)->map(fn ($b) => $b['text'] ?? '')->implode("\n");
            }
            // OpenAI only accepts 'system' at position 0; mid-conversation operator nudges become 'user'.
            $role = ($m['role'] === 'system' && ! empty($messages)) ? 'user' : $m['role'];
            $messages[] = ['role' => $role, 'content' => $content];
        }

        return $messages;
    }

    private function normalize(array $resp): LlmResult
    {
        $choice = $resp['choices'][0] ?? [];
        $msg    = $choice['message'] ?? [];
        $text   = (string) ($msg['content'] ?? '');

        $tools = [];
        foreach ($msg['tool_calls'] ?? [] as $call) {
            $tools[] = [
                'id'    => (string) ($call['id'] ?? ''),
                'name'  => (string) ($call['function']['name'] ?? ''),
                'input' => json_decode($call['function']['arguments'] ?? '{}', true) ?: [],
            ];
        }

        return new LlmResult(
            text: $text,
            toolCalls: $tools,
            inputTokens: (int) ($resp['usage']['prompt_tokens'] ?? 0),
            outputTokens: (int) ($resp['usage']['completion_tokens'] ?? 0),
            cacheReadTokens: (int) ($resp['usage']['prompt_tokens_details']['cached_tokens'] ?? 0),
            stopReason: $choice['finish_reason'] ?? null,
        );
    }
}
