<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewMessage extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_follow_up' => 'boolean',
        'meta'         => 'array',
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }
}
