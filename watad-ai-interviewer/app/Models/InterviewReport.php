<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Recommendation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewReport extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'recommendation' => Recommendation::class,
        'overall_score'  => 'float',
        'strengths'      => 'array',
        'weaknesses'     => 'array',
        'generated_at'   => 'datetime',
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }
}
