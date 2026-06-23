<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetencyScore extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'score'      => 'float',
        'weight'     => 'float',
        'confidence' => 'float',
        'evidence'   => 'array',
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }
}
