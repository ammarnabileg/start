<?php
namespace App\Modules\HeyGen;

/**
 * Thin HTTP client for the HeyGen API (avatars, voices, streaming sessions and
 * video generation).
 *
 * Design contract: methods NEVER throw on a network/HTTP failure. Instead they
 * return a structured ['error' => string] (plus 'data' where useful) so the UI
 * can degrade gracefully. When no API key is configured every call returns
 * ['error' => 'HeyGen API key not configured', 'data' => []].
 */
class HeyGenService
{
    private string $apiKey;
    private string $base;
    private int $timeout;

    public function __construct(?array $config = null)
    {
        if ($config === null) {
            $app = function_exists('config') ? config('app') : require dirname(__DIR__, 2) . '/config/app.php';
            $config = $app['heygen'] ?? [];
        }
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->base = rtrim((string) ($config['base'] ?? 'https://api.heygen.com'), '/');
        $this->timeout = (int) ($config['timeout'] ?? 10);
    }

    /**
     * List available avatars.
     *
     * @return array<string,mixed>
     */
    public function listAvatars(): array
    {
        return $this->request('GET', '/v2/avatars');
    }

    /**
     * Fetch a single avatar by id. The v2 list endpoint is the documented
     * source of truth, so we resolve from it to keep this robust across API
     * shape changes.
     *
     * @return array<string,mixed>
     */
    public function getAvatar(string $avatarId): array
    {
        if ($this->apiKey === '') {
            return $this->noKeyResponse();
        }
        $response = $this->request('GET', '/v2/avatars');
        if (isset($response['error'])) {
            return $response;
        }

        $avatars = $response['data']['avatars']
            ?? $response['data']
            ?? [];
        if (is_array($avatars)) {
            foreach ($avatars as $avatar) {
                if (!is_array($avatar)) {
                    continue;
                }
                $id = $avatar['avatar_id'] ?? $avatar['id'] ?? null;
                if ((string) $id === $avatarId) {
                    return ['data' => $avatar];
                }
            }
        }
        return ['error' => 'Avatar not found', 'data' => []];
    }

    /**
     * List available voices.
     *
     * @return array<string,mixed>
     */
    public function listVoices(): array
    {
        return $this->request('GET', '/v2/voices');
    }

    /**
     * Create a new realtime streaming session for an avatar+voice.
     *
     * @return array<string,mixed>
     */
    public function createStreamingSession(string $avatarId, string $voiceId): array
    {
        return $this->request('POST', '/v1/streaming.new', [
            'quality'      => 'high',
            'avatar_name'  => $avatarId,
            'voice'        => ['voice_id' => $voiceId],
        ]);
    }

    /**
     * Push text to an active streaming session for the avatar to speak.
     *
     * @return array<string,mixed>
     */
    public function startStreamingSession(string $sessionId, string $text): array
    {
        return $this->request('POST', '/v1/streaming.task', [
            'session_id' => $sessionId,
            'text'       => $text,
            'task_type'  => 'repeat',
        ]);
    }

    /**
     * Stop a streaming session.
     *
     * @return array<string,mixed>
     */
    public function stopStreamingSession(string $sessionId): array
    {
        return $this->request('POST', '/v1/streaming.stop', [
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Generate a pre-rendered video of the avatar speaking a script.
     *
     * @return array<string,mixed>
     */
    public function generateVideo(string $avatarId, string $voiceId, string $script): array
    {
        return $this->request('POST', '/v2/video/generate', [
            'video_inputs' => [[
                'character' => [
                    'type'         => 'avatar',
                    'avatar_id'    => $avatarId,
                    'avatar_style' => 'normal',
                ],
                'voice' => [
                    'type'     => 'text',
                    'input_text' => $script,
                    'voice_id' => $voiceId,
                ],
            ]],
            'dimension' => ['width' => 1280, 'height' => 720],
        ]);
    }

    /**
     * Poll the status of a generated video.
     *
     * @return array<string,mixed>
     */
    public function getVideoStatus(string $videoId): array
    {
        return $this->request('GET', '/v1/video_status.get?video_id=' . rawurlencode($videoId));
    }

    // ------------------------------------------------------------------
    // Low-level transport
    // ------------------------------------------------------------------

    /**
     * Perform an HTTP request against the HeyGen API.
     *
     * @param string                    $method GET|POST|...
     * @param string                    $path   leading-slash path (may include query string)
     * @param array<string,mixed>|null  $body   JSON body for write requests
     * @return array<string,mixed>              decoded response, or ['error'=>..]
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        if ($this->apiKey === '') {
            return $this->noKeyResponse();
        }

        $url = $this->base . $path;
        $ch = curl_init();

        $headers = [
            'X-Api-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($body !== null) {
            $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $options[CURLOPT_POSTFIELDS] = $encoded === false ? '{}' : $encoded;
        }

        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            logger('HeyGen request failed (' . $errno . '): ' . $err, 'error');
            return ['error' => 'HeyGen request failed: ' . ($err !== '' ? $err : 'network error'), 'data' => []];
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            // Non-JSON body (e.g. an HTML error page).
            if ($status >= 400) {
                return ['error' => 'HeyGen returned HTTP ' . $status, 'data' => []];
            }
            return ['data' => $raw];
        }

        if ($status >= 400) {
            $message = $this->extractErrorMessage($decoded) ?? ('HeyGen returned HTTP ' . $status);
            return ['error' => $message, 'status' => $status, 'data' => $decoded['data'] ?? []];
        }

        // HeyGen wraps payloads in {code, message, data}; some endpoints set an
        // error flag even on HTTP 200.
        if (isset($decoded['error']) && $decoded['error'] !== null && $decoded['error'] !== false) {
            return ['error' => $this->extractErrorMessage($decoded) ?? 'HeyGen error', 'data' => $decoded['data'] ?? []];
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $decoded
     */
    private function extractErrorMessage(array $decoded): ?string
    {
        if (isset($decoded['message']) && is_string($decoded['message']) && $decoded['message'] !== '') {
            return $decoded['message'];
        }
        if (isset($decoded['error'])) {
            $error = $decoded['error'];
            if (is_string($error) && $error !== '') {
                return $error;
            }
            if (is_array($error)) {
                if (!empty($error['message']) && is_string($error['message'])) {
                    return $error['message'];
                }
                $encoded = json_encode($error);
                if ($encoded !== false) {
                    return $encoded;
                }
            }
        }
        return null;
    }

    /**
     * @return array{error:string,data:array}
     */
    private function noKeyResponse(): array
    {
        return ['error' => 'HeyGen API key not configured', 'data' => []];
    }
}
