<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Interview;
use App\Services\AI\CvAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs CV analysis before the interview begins so the interviewer agent can target gaps and
 * verify claims (cv_analyses.topics_to_probe feeds the engine).
 */
class AnalyzeCv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 90];

    public function __construct(public int $interviewId) {}

    public function handle(CvAnalyzer $analyzer): void
    {
        $interview = Interview::with(['candidate', 'jobPosition'])->findOrFail($this->interviewId);

        if (! $interview->candidate || ! $interview->jobPosition) {
            return;
        }

        $analyzer->analyze($interview->candidate, $interview->jobPosition, $interview);
    }
}
