<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InterviewMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewTemplate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'mode'      => InterviewMode::class,
        'config'    => 'array',
        'is_active' => 'boolean',
    ];

    public function competencies(): HasMany
    {
        return $this->hasMany(TemplateCompetency::class, 'template_id');
    }

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Avatar::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
