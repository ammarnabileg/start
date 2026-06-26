<?php
declare(strict_types=1);

class OpenAIService
{
    private int $tenantId;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Send a chat completion request using the tenant's API key.
     *
     * @param array $messages  Array of {role, content} message objects.
     * @param array $options   Additional OpenAI payload options (model, max_tokens, etc.).
     * @return array|null      Full OpenAI response array, or null on error/missing key.
     */
    public function chat(array $messages, array $options = []): ?array
    {
        return ApiKeyManager::callOpenAI($messages, $this->tenantId, $options);
    }

    /**
     * Extract the text content from the first choice of a chat response.
     */
    public function getContent(array $response): ?string
    {
        return $response['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Extract and JSON-decode the content from a chat response.
     * Strips markdown code fences (```json … ``` or ``` … ```) if present.
     */
    public function getJSON(array $response): ?array
    {
        $content = $this->getContent($response);
        if ($content === null) {
            return null;
        }

        // Strip markdown code blocks: ```json\n…\n``` or ```\n…\n```
        $stripped = preg_replace('/^```(?:json)?\s*\n?(.*?)\n?```\s*$/s', '$1', trim($content));
        if ($stripped === null) {
            $stripped = $content;
        }

        $decoded = json_decode(trim($stripped), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
