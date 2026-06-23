<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Interview;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Records and dispatches a notification (email/WhatsApp/in-app). The scaffold records every
 * notification and sends email when configured; WhatsApp uses the Cloud API adapter.
 * Events: invitation | reminder | completion | new_candidate | interview_completed | high_potential.
 */
class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $event,
        public int $interviewId,
        public string $channel = 'email',
    ) {}

    public function handle(): void
    {
        $interview = Interview::with(['candidate', 'jobPosition'])->find($this->interviewId);
        if (! $interview) {
            return;
        }

        [$recipient, $subject, $body] = $this->compose($interview);

        $notification = Notification::create([
            'channel'         => $this->channel,
            'event'           => $this->event,
            'recipient'       => $recipient,
            'notifiable_type' => Interview::class,
            'notifiable_id'   => $interview->id,
            'payload'         => ['subject' => $subject, 'body' => $body],
            'status'          => 'queued',
        ]);

        try {
            if ($this->channel === 'email' && $recipient) {
                Mail::raw($body, fn ($m) => $m->to($recipient)->subject($subject));
            } else {
                // WhatsApp Cloud API adapter would post here; logged for the scaffold.
                Log::info('Notification dispatched', ['event' => $this->event, 'to' => $recipient]);
            }
            $notification->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $notification->update(['status' => 'failed', 'error' => substr($e->getMessage(), 0, 500)]);
            report($e);
        }
    }

    /** @return array{0:?string,1:string,2:string} */
    private function compose(Interview $interview): array
    {
        $candidate = $interview->candidate;
        $position  = $interview->jobPosition?->title;

        return match ($this->event) {
            'high_potential' => [
                config('mail.from.address'),
                "High-potential candidate: {$candidate?->full_name}",
                "{$candidate?->full_name} scored {$interview->overall_score}/100 for {$position} ({$interview->recommendation?->value}).",
            ],
            'interview_completed' => [
                config('mail.from.address'),
                "Interview completed: {$candidate?->full_name}",
                "{$candidate?->full_name} completed the AI interview for {$position}. Score: {$interview->overall_score}/100.",
            ],
            'completion' => [
                $candidate?->email,
                'Thanks for completing your Watad interview',
                "Hi {$candidate?->full_name}, thank you for completing your interview for {$position}. Our team will be in touch.",
            ],
            default => [
                $candidate?->email,
                'Watad interview',
                'Update regarding your Watad interview.',
            ],
        };
    }
}
