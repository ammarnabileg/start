<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\InterviewStatus;
use App\Enums\Recommendation;
use App\Events\InterviewCompleted;
use App\Models\Interview;
use App\Services\AI\BehavioralAnalyzer;
use App\Services\AI\RedFlagDetector;
use App\Services\AI\ScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Orchestrates the async analysis fan-out for a finished interview: scoring → behavioral →
 * red flags → overall score → recommendation (with code-enforced override rules) → report,
 * sheet push, and HR notification. Idempotent: safe to retry. See docs/08-scoring-and-analysis.md.
 */
class FinalizeInterview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 90];

    public function __construct(public int $interviewId) {}

    public function handle(
        ScoringService $scoring,
        BehavioralAnalyzer $behavioral,
        RedFlagDetector $redFlags,
    ): void {
        $interview = Interview::with(['candidate', 'jobPosition', 'template.competencies'])
            ->findOrFail($this->interviewId);

        try {
            $scoreResult = $scoring->score($interview);
            $behavioral->analyze($interview);
            $redFlags->detect($interview);

            if ($interview->mode->capturesVideo()) {
                // Video signals are produced asynchronously by the video-analysis worker and
                // arrive via webhook; see docs/10-video-behavioral-analysis.md.
                app(\App\Services\Video\VideoAnalysisService::class)->requestAnalysis($interview);
            }

            $overall        = $scoreResult['overall'];
            $recommendation = $this->resolveRecommendation($interview->fresh('redFlags'), $overall);

            $interview->update([
                'overall_score'  => $overall,
                'recommendation' => $recommendation,
                'status'         => InterviewStatus::Completed,
            ]);

            $this->markApplicationScreened($interview, $overall, $recommendation);

            GenerateReport::dispatch($interview->id);

            if (config('watad.sheets.enabled')) {
                PushToSheet::dispatch($interview->id);
            }

            $this->notify($interview, $overall);

            event(new InterviewCompleted($interview->id));
        } catch (Throwable $e) {
            $interview->update(['status' => InterviewStatus::Error]);
            report($e);
            throw $e; // allow the queue to retry
        }
    }

    /** Apply config-driven, code-enforced recommendation override rules on top of the score band. */
    private function resolveRecommendation(Interview $interview, float $overall): Recommendation
    {
        $rec       = Recommendation::fromScore($overall);
        $overrides = config('watad.scoring.overrides');
        $flags     = $interview->redFlags;

        $highFlags   = $flags->where('severity', 'high');
        $mediumFlags = $flags->where('severity', 'medium');

        // A confirmed high-severity fatal flag (e.g. fabricated experience) forces a reject.
        $fatalHigh = $highFlags
            ->whereIn('type', $overrides['fatal_flag_types'] ?? [])
            ->isNotEmpty();
        if ($fatalHigh) {
            return Recommendation::Reject;
        }

        if (($overrides['high_flag_downgrades_strong_hire'] ?? false)
            && $rec === Recommendation::StrongHire
            && $highFlags->isNotEmpty()) {
            $rec = $rec->downgrade();
        }

        if (($overrides['two_medium_flags_downgrade_hire'] ?? false)
            && $rec === Recommendation::Hire
            && $mediumFlags->count() >= 2) {
            $rec = $rec->downgrade();
        }

        return $rec;
    }

    /**
     * Surface the finished AI screening on the application timeline. The AI never advances the
     * application itself — it only records its score + recommendation so a human can decide.
     */
    private function markApplicationScreened(Interview $interview, float $overall, Recommendation $recommendation): void
    {
        $application = \App\Models\JobApplication::where('ai_interview_id', $interview->id)->first();
        if (! $application) {
            return;
        }

        app(\App\Services\Hiring\ApplicationWorkflow::class)->logActivity(
            $application,
            'ai_interview_completed',
            'AI screening completed — score '.round($overall, 1).'/100, recommendation: '.$recommendation->label(),
            null,
            ['overall_score' => $overall, 'recommendation' => $recommendation->value],
        );
    }

    private function notify(Interview $interview, float $overall): void
    {
        SendNotification::dispatch('interview_completed', $interview->id);

        if ($overall >= (float) config('watad.notifications.high_potential_threshold')) {
            SendNotification::dispatch('high_potential', $interview->id);
        }
    }
}
