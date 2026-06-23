<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Interview;

/**
 * Shared helpers for analysis agents: a turn-numbered transcript (so the model can cite seqs as
 * evidence) plus the standard analysis context (job + CV summary + transcript).
 */
trait BuildsTranscript
{
    protected function numberedTranscript(Interview $interview): string
    {
        return $interview->messages()
            ->where('role', '!=', 'system')
            ->orderBy('seq')
            ->get()
            ->map(fn ($m) => "[{$m->seq}] ".strtoupper($m->role).': '.$m->content)
            ->implode("\n");
    }

    protected function analysisContext(Interview $interview): string
    {
        $job = $interview->jobPosition;
        $cv  = $interview->candidate?->latestCvAnalysis;

        $jobText = "JOB: {$job?->title} ({$job?->seniority})\nRequirements: "
            .json_encode($job?->requirements);

        $cvText = $cv
            ? "\n\nCV ANALYSIS:\n".($cv->summary ?? '').' (JD match: '.($cv->jd_match_score ?? 'n/a').')'
            : '';

        return $jobText.$cvText."\n\nTRANSCRIPT (turn numbers in brackets):\n"
            .$this->numberedTranscript($interview);
    }
}
