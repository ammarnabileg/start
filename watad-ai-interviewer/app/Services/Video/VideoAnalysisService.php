<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Models\Interview;
use App\Models\VideoAnalysis;
use Illuminate\Support\Facades\Log;

/**
 * Coordinates the behavioral video-analysis pipeline (gaze, expression, prosody, liveness).
 * The heavy CV/ML inference runs on a separate worker (or managed vision API) and reports back
 * via POST /api/webhooks/video-analysis → ingest(). See docs/10-video-behavioral-analysis.md.
 *
 * NOTE: requires GPU/vision infrastructure; not exercisable in a bare checkout.
 */
class VideoAnalysisService
{
    /** Enqueue analysis once the interview recording is available. */
    public function requestAnalysis(Interview $interview): void
    {
        $recording = $interview->recordings()->where('kind', 'video')->latest()->first();

        if (! $recording) {
            Log::info('Video analysis requested but no recording yet', ['interview' => $interview->id]);
            return;
        }

        // In production: dispatch a job to the video-analysis worker with the recording URL +
        // transcript ms_offsets; the worker posts results to the webhook below.
        VideoAnalysis::firstOrCreate(
            ['interview_id' => $interview->id],
            ['provider' => config('watad.video.provider')],
        );

        Log::info('Video analysis enqueued', [
            'interview' => $interview->id,
            'recording' => $recording->url,
        ]);
    }

    /** Persist results posted by the (HMAC-verified) video-analysis worker. */
    public function ingest(Interview $interview, array $payload): VideoAnalysis
    {
        return VideoAnalysis::updateOrCreate(
            ['interview_id' => $interview->id],
            [
                'eye_contact_score'             => $payload['eye_contact'] ?? null,
                'facial_expression'             => $payload['facial_expression'] ?? null,
                'engagement_score'              => $payload['engagement'] ?? null,
                'confidence_score'              => $payload['confidence'] ?? null,
                'nervousness_score'             => $payload['nervousness'] ?? null,
                'energy_score'                  => $payload['energy'] ?? null,
                'attention_score'               => $payload['attention'] ?? null,
                'professional_appearance_score' => $payload['professional_appearance'] ?? null,
                'speaking_pace_wpm'             => $payload['speaking_pace_wpm'] ?? null,
                'body_language'                 => $payload['body_language'] ?? null,
                'authenticity_score'            => $payload['authenticity'] ?? null,
                'timeline'                      => $payload['timeline'] ?? null,
                'provider'                      => $payload['provider'] ?? config('watad.video.provider'),
            ],
        );
    }
}
