<?php
/**
 * SociAI OS - Base Platform Client
 * Abstract base for all social media platform API clients.
 */

declare(strict_types=1);

namespace SociAI\Platforms;

use RuntimeException;

class PlatformException extends RuntimeException {}

abstract class BasePlatform
{
    protected string $accessToken;
    protected string $accountId;

    private const DEFAULT_TIMEOUT = 30;
    private const MAX_RETRIES     = 2;

    public function __construct(string $accessToken, string $accountId)
    {
        $this->accessToken = $accessToken;
        $this->accountId   = $accountId;
    }

    // --------------------------------------------------------
    // Abstract platform methods
    // --------------------------------------------------------

    /**
     * Retrieve comments/mentions since a given time or ID.
     * @param string|null $since ISO timestamp or cursor/since_id
     * @return array<int, array<string, mixed>>
     */
    abstract public function getComments(?string $since = null): array;

    /**
     * Retrieve direct messages since a given time or cursor.
     * @param string|null $since ISO timestamp or cursor
     * @return array<int, array<string, mixed>>
     */
    abstract public function getDMs(?string $since = null): array;

    /**
     * Reply to a specific comment.
     * @param string $commentId Platform-specific comment ID
     * @param string $text      Reply text
     */
    abstract public function replyToComment(string $commentId, string $text): bool;

    /**
     * Reply to a DM conversation.
     * @param string $conversationId Platform-specific conversation/DM ID
     * @param string $text           Reply text
     */
    abstract public function replyToDM(string $conversationId, string $text): bool;

    /**
     * Publish a post/piece of content.
     * @param array<string, mixed> $content Platform-specific content data
     * @return array<string, mixed> Result including platform_post_id, url, etc.
     */
    abstract public function publishPost(array $content): array;

    // --------------------------------------------------------
    // HTTP helpers
    // --------------------------------------------------------

    /**
     * Perform a GET request with retry logic.
     * @param string               $url
     * @param array<string, mixed> $params  Query parameters
     * @param array<string, string> $headers Additional headers
     * @return array<string, mixed>
     */
    protected function httpGet(string $url, array $params = [], array $headers = []): array
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $allHeaders   = array_merge($this->defaultHeaders(), $headers);
        $headerLines  = $this->buildHeaderLines($allHeaders);

        $lastError = null;
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_HTTPGET        => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headerLines,
                CURLOPT_TIMEOUT        => self::DEFAULT_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
            ]);

            $raw  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err) {
                $lastError = "cURL error: {$err}";
                usleep(500_000 * $attempt);
                continue;
            }

            $decoded = json_decode((string)$raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $lastError = "JSON decode error: " . json_last_error_msg() . " | Raw: " . substr((string)$raw, 0, 200);
                break;
            }

            if ($code >= 500) {
                $lastError = "Server error {$code}: " . json_encode($decoded);
                usleep(1_000_000 * $attempt);
                continue;
            }

            if ($code === 429) {
                $retryAfter = (int)(($decoded['retry_after'] ?? ($decoded['error']['retry_after'] ?? 5)) ?: 5);
                $lastError  = "Rate limited (429). Retry after {$retryAfter}s.";
                usleep(min($retryAfter * 1_000_000, 30_000_000));
                continue;
            }

            if ($code >= 400) {
                throw new PlatformException(
                    static::class . " API GET error {$code}: " . json_encode($decoded),
                    $code
                );
            }

            return $decoded ?? [];
        }

        throw new PlatformException(
            static::class . " GET failed after " . self::MAX_RETRIES . " attempts. Last: " . $lastError
        );
    }

    /**
     * Perform a POST request with retry logic.
     * @param string               $url
     * @param array<string, mixed> $data    POST body data (JSON-encoded)
     * @param array<string, string> $headers Additional headers
     * @return array<string, mixed>
     */
    protected function httpPost(string $url, array $data, array $headers = []): array
    {
        $allHeaders  = array_merge($this->defaultHeaders(), $headers);
        $headerLines = $this->buildHeaderLines($allHeaders);
        $body        = json_encode($data, JSON_UNESCAPED_UNICODE);

        $lastError = null;
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headerLines,
                CURLOPT_TIMEOUT        => self::DEFAULT_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $raw  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err) {
                $lastError = "cURL error: {$err}";
                usleep(500_000 * $attempt);
                continue;
            }

            $decoded = json_decode((string)$raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $lastError = "JSON decode error: " . json_last_error_msg();
                break;
            }

            if ($code >= 500) {
                $lastError = "Server error {$code}: " . json_encode($decoded);
                usleep(1_000_000 * $attempt);
                continue;
            }

            if ($code === 429) {
                $retryAfter = (int)(($decoded['retry_after'] ?? 5) ?: 5);
                $lastError  = "Rate limited (429). Retry after {$retryAfter}s.";
                usleep(min($retryAfter * 1_000_000, 30_000_000));
                continue;
            }

            if ($code >= 400) {
                throw new PlatformException(
                    static::class . " API POST error {$code}: " . json_encode($decoded),
                    $code
                );
            }

            return $decoded ?? [];
        }

        throw new PlatformException(
            static::class . " POST failed after " . self::MAX_RETRIES . " attempts. Last: " . $lastError
        );
    }

    // --------------------------------------------------------
    // Internal helpers
    // --------------------------------------------------------

    /**
     * Default headers including Bearer auth token.
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Convert associative header array to curl header lines.
     * @param array<string, string> $headers
     * @return array<int, string>
     */
    private function buildHeaderLines(array $headers): array
    {
        $lines = [];
        foreach ($headers as $key => $value) {
            $lines[] = "{$key}: {$value}";
        }
        return $lines;
    }

    /**
     * Normalise a platform item into our standard interaction format.
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    protected function normaliseInteraction(
        string $externalId,
        string $type,
        string $authorName,
        string $authorId,
        string $content,
        string $createdAt,
        string $authorAvatar = '',
        string $postId       = '',
        string $postUrl      = '',
        array  $extra        = []
    ): array {
        return [
            'external_id'   => $externalId,
            'type'          => $type,
            'author_name'   => $authorName,
            'author_id'     => $authorId,
            'author_avatar' => $authorAvatar,
            'content'       => $content,
            'created_at'    => $createdAt,
            'post_id'       => $postId,
            'post_url'      => $postUrl,
            'platform_data' => $extra,
        ];
    }
}
