<?php

declare(strict_types=1);

namespace App\Services\Video\Providers;

use App\Models\Avatar;
use App\Models\Interview;
use App\Services\Video\Contracts\AvatarProvider;
use Illuminate\Support\Facades\Http;

/**
 * HeyGen Interactive Avatar adapter (LiveKit transport). The engine sends text → the streaming
 * avatar speaks with lip-sync. Requires HEYGEN_API_KEY + an interactive avatar id
 * (avatar.video_replica_id). Endpoints illustrate the integration contract; verify against the
 * current HeyGen Streaming API before production. See docs/09.
 */
class HeyGenProvider implements AvatarProvider
{
    private string $base = 'https://api.heygen.com/v1';

    private function http()
    {
        return Http::withHeaders(['x-api-key' => (string) config('watad.video.heygen.api_key')])
            ->acceptJson();
    }

    public function createSession(Interview $interview, Avatar $avatar): array
    {
        $resp = $this->http()->post("{$this->base}/streaming.new", [
            'avatar_id' => $avatar->video_replica_id,
            'voice'     => ['voice_id' => $avatar->voice_id],
            'quality'   => 'high',
        ])->throw()->json('data');

        return [
            'room_url'   => $resp['url'] ?? '',          // LiveKit room URL
            'token'      => $resp['access_token'] ?? '', // LiveKit token
            'session_id' => $resp['session_id'] ?? '',
        ];
    }

    public function speak(string $providerSessionId, string $text): void
    {
        $this->http()->post("{$this->base}/streaming.task", [
            'session_id' => $providerSessionId,
            'text'       => $text,
            'task_type'  => 'repeat',
        ]);
    }

    public function interrupt(string $providerSessionId): void
    {
        $this->http()->post("{$this->base}/streaming.interrupt", ['session_id' => $providerSessionId]);
    }

    public function endSession(string $providerSessionId): void
    {
        $this->http()->post("{$this->base}/streaming.stop", ['session_id' => $providerSessionId]);
    }
}
