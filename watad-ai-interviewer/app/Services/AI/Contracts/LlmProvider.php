<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

use App\Services\AI\LlmResult;

/**
 * A reasoning provider (Claude, OpenAI, ...). Vendor SDKs live behind this interface so the
 * interview engine and analysis services are provider-agnostic. The active implementation is
 * chosen in config('watad.ai.provider') and resolved by LlmManager.
 *
 * $params shape (all optional unless noted):
 *   model         string   (required — injected by LlmManager from role)
 *   maxTokens     int
 *   system        array    list of system blocks: ['type'=>'text','text'=>..., 'cache_control'=>['type'=>'ephemeral']]
 *   messages      array    list of ['role'=>'user'|'assistant'|'system', 'content'=> string|array-of-blocks]
 *   tools         array    list of tool definitions (name, description, input_schema)
 *   thinking      array    e.g. ['type'=>'adaptive','display'=>'omitted']
 */
interface LlmProvider
{
    public function chat(array $params): LlmResult;

    /**
     * Streamed completion. Forwards text deltas to $onDelta as they arrive and returns the
     * assembled final result (including tool calls and usage).
     */
    public function stream(array $params, callable $onDelta): LlmResult;
}
