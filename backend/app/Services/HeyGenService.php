<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class HeyGenService
{
    private string $baseUrl = 'https://api.heygen.com/v1';
    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: config('services.heygen.api_key');
    }

    public function listAvatars(): array
    {
        $response = $this->request('GET', '/avatars');
        return $response['data']['avatars'] ?? [];
    }

    public function listVoices(string $language = 'ar'): array
    {
        $response = $this->request('GET', '/voices');
        $voices = $response['data']['voices'] ?? [];
        return array_filter($voices, fn($v) => str_contains(strtolower($v['language'] ?? ''), strtolower($language)));
    }

    public function createStreamingSession(string $avatarId, string $voiceId): array
    {
        return $this->request('POST', '/streaming.new', [
            'quality' => 'high',
            'avatar_id' => $avatarId,
            'voice' => ['voice_id' => $voiceId],
            'knowledge_base_id' => null,
        ]);
    }

    public function startSession(string $sessionId): array
    {
        return $this->request('POST', '/streaming.start', ['session_id' => $sessionId]);
    }

    public function sendTask(string $sessionId, string $text): array
    {
        return $this->request('POST', '/streaming.task', [
            'session_id' => $sessionId,
            'text' => $text,
            'task_type' => 'talk',
        ]);
    }

    public function stopSession(string $sessionId): array
    {
        return $this->request('POST', '/streaming.stop', ['session_id' => $sessionId]);
    }

    public function validateApiKey(): bool
    {
        try {
            $response = $this->request('GET', '/remaining_quota');
            return isset($response['data']);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $response = Http::withHeaders([
            'X-Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->{strtolower($method)}($this->baseUrl . $endpoint, $data);

        if ($response->failed()) {
            throw new \Exception("HeyGen API error: " . $response->body());
        }

        return $response->json();
    }
}
