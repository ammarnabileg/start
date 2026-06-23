<?php

declare(strict_types=1);

namespace App\Services\Video\Providers;

use App\Models\Avatar;
use App\Models\Interview;
use App\Services\Video\Contracts\AvatarProvider;
use Illuminate\Support\Facades\Http;

/**
 * Tavus Conversational Video Interface adapter. Real-time avatar with built-in turn-taking and
 * interruptions. Requires TAVUS_API_KEY + a configured replica/persona (avatar.video_replica_id).
 *
 * Endpoints are illustrative of the integration contract — verify against current Tavus API docs
 * before production use. See docs/09-video-interview-architecture.md.
 */
class TavusProvider implements AvatarProvider
{
    private string $base = 'https://tavusapi.com/v2';

    private function http()
    {
        return Http::withHeaders(['x-api-key' => (string) config('watad.video.tavus.api_key')])
            ->acceptJson();
    }

    public function createSession(Interview $interview, Avatar $avatar): array
    {
        $resp = $this->http()->post("{$this->base}/conversations", [
            'replica_id'      => $avatar->video_replica_id,
            'conversation_name' => "watad-{$interview->public_id}",
            'callback_url'    => route('api.webhooks.avatar', 'tavus'),
            'properties'      => ['max_call_duration' => ($interview->template->max_duration_min ?? 25) * 60],
        ])->throw()->json();

        return [
            'room_url'   => $resp['conversation_url'] ?? '',
            'token'      => $resp['conversation_id'] ?? '',
            'session_id' => $resp['conversation_id'] ?? '',
        ];
    }

    public function speak(string $providerSessionId, string $text): void
    {
        // Tavus drives the conversation via its own LLM/echo modes; in "echo" mode the engine
        // sends the exact text for the avatar to speak.
        $this->http()->post("{$this->base}/conversations/{$providerSessionId}/echo", ['text' => $text]);
    }

    public function interrupt(string $providerSessionId): void
    {
        $this->http()->post("{$this->base}/conversations/{$providerSessionId}/interrupt");
    }

    public function endSession(string $providerSessionId): void
    {
        $this->http()->post("{$this->base}/conversations/{$providerSessionId}/end");
    }
}
