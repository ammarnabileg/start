<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoAnalysis extends Model
{
    protected $table = 'video_analyses';

    protected $guarded = ['id'];

    protected $casts = [
        'facial_expression' => 'array',
        'body_language'     => 'array',
        'timeline'          => 'array',
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }
}
