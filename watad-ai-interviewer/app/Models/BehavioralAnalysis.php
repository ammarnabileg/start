<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehavioralAnalysis extends Model
{
    protected $table = 'behavioral_analyses';

    protected $guarded = ['id'];

    protected $casts = [
        'disc'                  => 'array',
        'big_five'              => 'array',
        'risk_indicators'       => 'array',
        'integrity_indicators'  => 'array',
        'growth_mindset_score'  => 'float',
        'stress_handling_score' => 'float',
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }
}
