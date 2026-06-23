<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\HumanInterviewType;
use App\Models\EvaluationTemplate;
use App\Models\HumanInterview;
use App\Models\JobApplication;

/**
 * Resolves the dynamic evaluation form for an interview (by job → department → type → default)
 * and aggregates panelist evaluations. See docs/21-hiring-workflow.md (Stage 2).
 */
class EvaluationService
{
    public function resolveTemplate(JobApplication $application, HumanInterviewType|string $type): ?EvaluationTemplate
    {
        $type = $type instanceof HumanInterviewType ? $type->value : $type;
        $base = EvaluationTemplate::with('criteria')->where('is_active', true);

        return (clone $base)->where('job_position_id', $application->job_position_id)->first()
            ?? (clone $base)->whereNotNull('department_id')
                ->where('department_id', $application->jobPosition?->department_id)->first()
            ?? (clone $base)->where('interview_type', $type)->first()
            ?? (clone $base)->where('is_default', true)->first();
    }

    /** Weighted/average aggregate of submitted panelist ratings; persisted on the interview. */
    public function aggregate(HumanInterview $interview): ?float
    {
        $ratings = $interview->evaluations()
            ->whereNotNull('submitted_at')
            ->whereNotNull('overall_rating')
            ->pluck('overall_rating');

        if ($ratings->isEmpty()) {
            return null;
        }

        $avg = round((float) $ratings->avg(), 2);
        $interview->forceFill(['aggregate_rating' => $avg])->save();

        return $avg;
    }
}
