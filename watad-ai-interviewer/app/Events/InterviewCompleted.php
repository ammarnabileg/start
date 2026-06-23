<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Interview;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Broadcast to the HR dashboard when an interview is finalized. */
class InterviewCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $interviewId) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('hr.dashboard');
    }

    public function broadcastWith(): array
    {
        $interview = Interview::with('candidate', 'jobPosition')->find($this->interviewId);

        return [
            'interview_id'   => $interview?->public_id,
            'candidate'      => $interview?->candidate?->full_name,
            'position'       => $interview?->jobPosition?->title,
            'overall_score'  => $interview?->overall_score,
            'recommendation' => $interview?->recommendation?->value,
        ];
    }
}
