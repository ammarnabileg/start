<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateCompetency extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'weight'     => 'float',
        'is_enabled' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(InterviewTemplate::class, 'template_id');
    }
}
