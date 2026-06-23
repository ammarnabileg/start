<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\LlmProvider;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\OpenAiProvider;
use Illuminate\Contracts\Container\Container;

/**
 * Entry point all business code uses to talk to an LLM. Resolves the active provider and the
 * right model + token budget for a workload "role" (conversation | analysis | cv) from
 * config/watad.php, so call sites never reference a model string or a vendor SDK.
 */
final class LlmManager
{
    public function __construct(private readonly Container $app) {}

    public function provider(): LlmProvider
    {
        return $this->app->make(
            config('watad.ai.provider') === 'openai' ? OpenAiProvider::class : ClaudeProvider::class
        );
    }

    public function model(string $role): string
    {
        $provider = config('watad.ai.provider');
        return config("watad.ai.models.$provider.$role")
            ?? config("watad.ai.models.$provider.analysis");
    }

    public function maxTokens(string $role): int
    {
        return (int) (config("watad.ai.max_tokens.$role") ?? 2048);
    }

    public function chat(string $role, array $params): LlmResult
    {
        return $this->provider()->chat($this->prepare($role, $params));
    }

    public function stream(string $role, array $params, callable $onDelta): LlmResult
    {
        return $this->provider()->stream($this->prepare($role, $params), $onDelta);
    }

    /** Convenience for analysis agents: run a chat and decode the JSON object from the reply. */
    public function json(string $role, array $params): array
    {
        return $this->chat($role, $params)->json();
    }

    private function prepare(string $role, array $params): array
    {
        $params['model']     ??= $this->model($role);
        $params['maxTokens'] ??= $this->maxTokens($role);
        return $params;
    }
}
