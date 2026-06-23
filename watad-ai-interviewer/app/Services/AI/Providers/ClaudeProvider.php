<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use Anthropic\Client;
use App\Services\AI\Contracts\LlmProvider;
use App\Services\AI\LlmResult;

/**
 * Claude implementation backed by the official PHP SDK (anthropic-ai/sdk).
 *
 * SDK surface used (verified against the SDK docs):
 *   new Anthropic\Client(apiKey: ...)
 *   $client->messages->create(maxTokens:, messages:, model:, system:, tools:, thinking:)
 *   response: $message->content[]  with ->type ('text'|'tool_use'), ->text, ->id, ->name, ->input
 *             $message->usage->inputTokens / ->outputTokens / ->cacheReadInputTokens
 *             $message->stopReason
 */
final class ClaudeProvider implements LlmProvider
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(
            apiKey: (string) config('watad.ai.anthropic_api_key'),
        );
    }

    public function chat(array $params): LlmResult
    {
        $message = $this->client->messages->create(...$this->buildArgs($params));

        return $this->normalize($message);
    }

    public function stream(array $params, callable $onDelta): LlmResult
    {
        // The interview engine needs the complete final message (tool calls + text) to advance
        // its state machine deterministically, so we resolve the full message and emit the agent
        // text to the live channel. To upgrade to token-level streaming, call
        // $this->client->messages->createStream(...$this->buildArgs($params)) here and forward
        // each text delta to $onDelta — the SDK's stream event shape varies by version
        // (see docs/07-interview-engine-logic.md). The fallback below is always correct.
        $result = $this->chat($params);

        if ($result->text !== '') {
            $onDelta($result->text);
        }

        return $result;
    }

    /** Map our normalized $params to the SDK's named arguments. */
    private function buildArgs(array $params): array
    {
        $args = [
            'model'     => $params['model'],
            'maxTokens' => $params['maxTokens'] ?? 1024,
            'messages'  => $params['messages'] ?? [],
        ];

        if (! empty($params['system'])) {
            $args['system'] = $params['system'];
        }
        if (! empty($params['tools'])) {
            $args['tools'] = $params['tools'];
        }
        if (! empty($params['thinking'])) {
            $args['thinking'] = $params['thinking'];
        }

        return $args;
    }

    private function normalize(object $message): LlmResult
    {
        $text  = '';
        $tools = [];

        foreach (($message->content ?? []) as $block) {
            $type = $block->type ?? null;
            if ($type === 'text') {
                $text .= $block->text ?? '';
            } elseif ($type === 'tool_use') {
                $tools[] = [
                    'id'    => (string) ($block->id ?? ''),
                    'name'  => (string) ($block->name ?? ''),
                    'input' => $this->toArray($block->input ?? []),
                ];
            }
        }

        $usage = $message->usage ?? null;

        return new LlmResult(
            text: $text,
            toolCalls: $tools,
            inputTokens: (int) ($usage->inputTokens ?? 0),
            outputTokens: (int) ($usage->outputTokens ?? 0),
            cacheReadTokens: (int) ($usage->cacheReadInputTokens ?? 0),
            stopReason: $message->stopReason ?? null,
        );
    }

    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode(json_encode($value) ?: '[]', true);
        return is_array($decoded) ? $decoded : [];
    }
}
