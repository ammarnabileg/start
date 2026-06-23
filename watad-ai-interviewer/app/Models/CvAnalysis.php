<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvAnalysis extends Model
{
    protected $table = 'cv_analyses';

    protected $guarded = ['id'];

    protected $casts = [
        'extracted'       => 'array',
        'highlights'      => 'array',
        'gaps'            => 'array',
        'topics_to_probe' => 'array',
        'jd_match_score'  => 'float',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
