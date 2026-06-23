<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Streams an agent text delta to the candidate's interview room. */
class AgentMessageStreamed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $interviewPublicId,
        public string $delta,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('interview.'.$this->interviewPublicId);
    }

    public function broadcastAs(): string
    {
        return 'agent.delta';
    }

    public function broadcastWith(): array
    {
        return ['delta' => $this->delta];
    }
}
