<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobPosition extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'responsibilities' => 'array',
        'requirements'     => 'array',
        'salary_min'       => 'float',
        'salary_max'       => 'float',
        'is_remote'        => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function defaultTemplate(): BelongsTo
    {
        return $this->belongsTo(InterviewTemplate::class, 'default_template_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(HiringPipeline::class, 'pipeline_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(InterviewInvitation::class);
    }
}
