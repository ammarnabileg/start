<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Models\Recording;
use App\Services\Video\VideoAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound provider webhooks (avatar session/recording events, async video-analysis results).
 * Signature verification is enforced by the VerifyWebhookSignature middleware (see docs/13).
 */
class WebhookController extends Controller
{
    /** Tavus/HeyGen session + recording lifecycle events. */
    public function avatar(Request $request, string $provider): JsonResponse
    {
        $sessionId = $request->input('session_id') ?? $request->input('conversation_id');
        $interview = Interview::whereJsonContains('state->provider_session_id', $sessionId)->first();

        if ($interview && $url = $request->input('recording_url')) {
            Recording::updateOrCreate(
                ['interview_id' => $interview->id, 'kind' => 'video'],
                ['provider' => $provider, 'url' => $url, 'status' => 'ready'],
            );
        }

        return response()->json(['ok' => true]);
    }

    /** Async results from the video-analysis worker. */
    public function videoAnalysis(Request $request, VideoAnalysisService $service): JsonResponse
    {
        $interview = Interview::where('public_id', $request->input('interview_id'))->firstOrFail();
        $service->ingest($interview, $request->all());

        return response()->json(['ok' => true]);
    }
}
