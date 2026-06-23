<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HumanInterviewStatus;
use App\Enums\HumanInterviewType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class HumanInterview extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'type'             => HumanInterviewType::class,
        'status'           => HumanInterviewStatus::class,
        'scheduled_at'     => 'datetime',
        'aggregate_rating' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $i) => $i->public_id ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'application_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'template_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function panelists(): HasMany
    {
        return $this->hasMany(InterviewPanelist::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(InterviewEvaluation::class);
    }
}
