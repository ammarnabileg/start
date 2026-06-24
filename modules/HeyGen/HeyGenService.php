<?php
declare(strict_types=1);

namespace Modules\HeyGen;

use RuntimeException;

/**
 * HeyGenService - Integration with the HeyGen Interactive Avatar (streaming)
 * and asset APIs using raw cURL.
 *
 * Used to power video interviews: a streaming session is created for an avatar,
 * text is pushed for the avatar to speak in real time, and the session is
 * closed when the interview ends. Also lists available avatars/voices and
 * validates the configured API key.
 *
 * Endpoints (HeyGen v1 streaming / v2 assets):
 *   POST /v1/streaming.new        create a session
 *   POST /v1/streaming.start      start streaming on a session
 *   POST /v1/streaming.task       send text for the avatar to speak
 *   POST /v1/streaming.stop       stop/close a session
 *   GET  /v2/avatars              list avatars
 *   GET  /v2/voices               list voices
 */
class HeyGenService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.heygen.com';
    private int $timeout = 60;

    public function __construct(?string $apiKey = null)
    {
        // Priority: explicit arg → tenant key from DB → platform ENV fallback
        $this->apiKey = $apiKey
            ?? (class_exists('ApiKeyManager') ? \ApiKeyManager::getTenantHeyGenKey() : null)
            ?? ($_ENV['HEYGEN_API_KEY'] ?? '');
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Create a new interactive streaming session for an avatar.
     *
     * @return array{session_id:string, access_token:string, url:string,
     *               realtime_endpoint:string, ice_servers:array, raw:array}
     */
    public function createStreamingSession(string $avatarId, string $voiceId, array $options = []): array
    {
        $payload = [
            'quality'        => (string) ($options['quality'] ?? 'high'),
            'avatar_name'    => $avatarId,
            'version'        => (string) ($options['version'] ?? 'v2'),
            'video_encoding' => (string) ($options['video_encoding'] ?? 'H264'),
        ];

        if ($voiceId !== '') {
            $payload['voice'] = [
                'voice_id' => $voiceId,
                'rate'     => (float) ($options['rate'] ?? 1.0),
            ];
        }
        if (isset($options['language'])) {
            $payload['language'] = (string) $options['language'];
        }

        $response = $this->request('POST', '/v1/streaming.new', $payload);
        $data = $response['data'] ?? $response;

        $session = [
            'session_id'        => (string) ($data['session_id'] ?? ''),
            'access_token'      => (string) ($data['access_token'] ?? ''),
            'url'               => (string) ($data['url'] ?? ($data['realtime_endpoint'] ?? '')),
            'realtime_endpoint' => (string) ($data['realtime_endpoint'] ?? ''),
            'ice_servers'       => $data['ice_servers2'] ?? ($data['ice_servers'] ?? []),
            'raw'               => $data,
        ];

        if ($session['session_id'] === '') {
            throw new RuntimeException('HeyGen did not return a session id.');
        }

        // Begin streaming so the session is ready to receive tasks.
        if (!empty($options['auto_start']) || ($options['auto_start'] ?? true)) {
            try {
                $this->startSession($session['session_id']);
            } catch (\Throwable $e) {
                // Non-fatal: the client can also start the session itself.
                $this->errorLog('streaming.start failed: ' . $e->getMessage());
            }
        }

        return $session;
    }

    /**
     * Start streaming on an existing session.
     */
    public function startSession(string $sessionId): bool
    {
        $response = $this->request('POST', '/v1/streaming.start', ['session_id' => $sessionId]);
        return $this->isOk($response);
    }

    /**
     * Send text for the avatar to speak.
     *
     * @param string $taskType "repeat" (speak verbatim) or "talk".
     * @return array{task_id:string, duration_ms:int, raw:array}
     */
    public function sendText(string $sessionId, string $text, string $taskType = 'repeat'): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('Cannot send empty text to HeyGen avatar.');
        }

        $response = $this->request('POST', '/v1/streaming.task', [
            'session_id' => $sessionId,
            'text'       => $text,
            'task_type'  => $taskType,
        ]);

        $data = $response['data'] ?? $response;
        return [
            'task_id'     => (string) ($data['task_id'] ?? ''),
            'duration_ms' => (int) ($data['duration_ms'] ?? 0),
            'raw'         => $data,
        ];
    }

    /**
     * Close (stop) a streaming session.
     */
    public function closeSession(string $sessionId): bool
    {
        if ($sessionId === '') {
            return false;
        }
        try {
            $response = $this->request('POST', '/v1/streaming.stop', ['session_id' => $sessionId]);
            return $this->isOk($response);
        } catch (\Throwable $e) {
            $this->errorLog('streaming.stop failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send an interrupt to stop the avatar's current speech.
     */
    public function interrupt(string $sessionId): bool
    {
        try {
            $response = $this->request('POST', '/v1/streaming.interrupt', ['session_id' => $sessionId]);
            return $this->isOk($response);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * List available avatars.
     *
     * @return array<int, array{avatar_id:string, name:string, gender:string, preview_image:string}>
     */
    public function listAvatars(): array
    {
        $response = $this->request('GET', '/v2/avatars');
        $data = $response['data'] ?? $response;

        $avatars = [];
        foreach (($data['avatars'] ?? []) as $a) {
            $avatars[] = [
                'avatar_id'     => (string) ($a['avatar_id'] ?? ($a['id'] ?? '')),
                'name'          => (string) ($a['avatar_name'] ?? ($a['name'] ?? '')),
                'gender'        => (string) ($a['gender'] ?? 'neutral'),
                'preview_image' => (string) ($a['preview_image_url'] ?? ($a['preview_image'] ?? '')),
            ];
        }

        // Include interactive (streaming-capable) avatars when present.
        foreach (($data['interactive_avatars'] ?? []) as $a) {
            $avatars[] = [
                'avatar_id'     => (string) ($a['avatar_id'] ?? ''),
                'name'          => (string) ($a['avatar_name'] ?? ($a['name'] ?? '')),
                'gender'        => (string) ($a['gender'] ?? 'neutral'),
                'preview_image' => (string) ($a['preview_image_url'] ?? ''),
            ];
        }

        return $avatars;
    }

    /**
     * List available voices.
     *
     * @return array<int, array{voice_id:string, name:string, language:string, gender:string, preview_audio:string}>
     */
    public function listVoices(): array
    {
        $response = $this->request('GET', '/v2/voices');
        $data = $response['data'] ?? $response;

        $voices = [];
        foreach (($data['voices'] ?? []) as $v) {
            $voices[] = [
                'voice_id'      => (string) ($v['voice_id'] ?? ($v['id'] ?? '')),
                'name'          => (string) ($v['name'] ?? ''),
                'language'      => (string) ($v['language'] ?? ''),
                'gender'        => (string) ($v['gender'] ?? 'neutral'),
                'preview_audio' => (string) ($v['preview_audio'] ?? ($v['sample'] ?? '')),
            ];
        }
        return $voices;
    }

    /**
     * Validate the configured API key by hitting a cheap authenticated endpoint.
     */
    public function validateApiKey(): bool
    {
        if ($this->apiKey === '') {
            return false;
        }
        try {
            $response = $this->request('GET', '/v2/voices');
            return $this->isOk($response) || isset($response['data']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ==================================================================
    // HTTP
    // ==================================================================

    /**
     * Perform a cURL request against the HeyGen API. Returns decoded JSON.
     *
     * @throws RuntimeException on transport or API error.
     */
    private function request(string $method, string $endpoint, ?array $payload = null): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('HeyGen API key is not configured (HEYGEN_API_KEY).');
        }

        $url = $this->baseUrl . $endpoint;
        $ch  = curl_init($url);

        $headers = [
            'Accept: application/json',
            'X-Api-Key: ' . $this->apiKey,
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ];

        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                throw new RuntimeException('Failed to encode HeyGen payload: ' . json_last_error_msg());
            }
            $opts[CURLOPT_POSTFIELDS] = $body;
            $headers[] = 'Content-Type: application/json';
        }

        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('HeyGen cURL error: ' . $curlErr);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            if ($httpCode >= 200 && $httpCode < 300) {
                return [];
            }
            throw new RuntimeException(sprintf('HeyGen API error (HTTP %d): %s', $httpCode, substr((string) $raw, 0, 300)));
        }

        // HeyGen signals errors either via HTTP status or a non-100 "code".
        $code = $decoded['code'] ?? null;
        $apiError = $decoded['error'] ?? ($decoded['message'] ?? null);

        if (($httpCode < 200 || $httpCode >= 300) || ($code !== null && $code !== 100 && $apiError)) {
            $message = is_array($apiError) ? ($apiError['message'] ?? json_encode($apiError)) : (string) ($apiError ?? 'Unknown error');
            throw new RuntimeException(sprintf('HeyGen API error (HTTP %d, code %s): %s', $httpCode, (string) $code, $message));
        }

        return $decoded;
    }

    private function isOk(array $response): bool
    {
        if (array_key_exists('code', $response)) {
            return (int) $response['code'] === 100;
        }
        // Absence of an explicit error is treated as success.
        return !isset($response['error']);
    }

    private function errorLog(string $message): void
    {
        $logDir = $_ENV['APP_LOG_PATH'] ?? (defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : sys_get_temp_dir());
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        @file_put_contents(
            rtrim($logDir, '/\\') . '/heygen.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}
