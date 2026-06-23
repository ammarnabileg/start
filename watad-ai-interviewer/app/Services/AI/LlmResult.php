<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Provider-agnostic result of an LLM call. Both ClaudeProvider and OpenAiProvider
 * normalize their responses into this shape so business code never touches a vendor SDK type.
 */
final class LlmResult
{
    /**
     * @param list<array{id:string,name:string,input:array<string,mixed>}> $toolCalls
     */
    public function __construct(
        public readonly string $text,
        public readonly array $toolCalls = [],
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $cacheReadTokens = 0,
        public readonly ?string $stopReason = null,
    ) {}

    public function toolCall(string $name): ?array
    {
        foreach ($this->toolCalls as $call) {
            if ($call['name'] === $name) {
                return $call;
            }
        }
        return null;
    }

    public function hasToolCall(string $name): bool
    {
        return $this->toolCall($name) !== null;
    }

    public function wasRefused(): bool
    {
        return $this->stopReason === 'refusal';
    }

    /** Best-effort decode of the first JSON object found in the text (for analysis agents). */
    public function json(): array
    {
        $text = trim($this->text);
        // strip ```json fences if present
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text) ?? $text;
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return [];
        }
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($decoded) ? $decoded : [];
    }
}
