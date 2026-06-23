<?php

declare(strict_types=1);

namespace App\Services\Video\Contracts;

use App\Models\Avatar;
use App\Models\Interview;

/**
 * Abstraction over real-time AI-avatar providers (Tavus, HeyGen, OpenAI Realtime + LiveKit).
 * Keeps vendor APIs at the edge so the InterviewEngine (the "brain") is unchanged across modes.
 * See docs/09-video-interview-architecture.md.
 *
 * NOTE: implementations require paid provider accounts + a WebRTC/LiveKit deployment and are not
 * exercisable in a bare checkout. The interfaces and adapters define the integration contract.
 */
interface AvatarProvider
{
    /**
     * Provision a live avatar session/room.
     *
     * @return array{room_url:string, token:string, session_id:string}
     */
    public function createSession(Interview $interview, Avatar $avatar): array;

    /** Push agent text → the avatar speaks it (lip-synced). */
    public function speak(string $providerSessionId, string $text): void;

    /** Barge-in: stop the avatar mid-utterance. */
    public function interrupt(string $providerSessionId): void;

    /** End the session and return a recording reference (resolved later via webhook). */
    public function endSession(string $providerSessionId): void;
}
