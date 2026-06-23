<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvaluationRecommendation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewEvaluation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'recommendation'  => EvaluationRecommendation::class,
        'overall_rating'  => 'float',
        'strengths'       => 'array',
        'weaknesses'      => 'array',
        'criteria_scores' => 'array',
        'submitted_at'    => 'datetime',
    ];

    public function humanInterview(): BelongsTo
    {
        return $this->belongsTo(HumanInterview::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
