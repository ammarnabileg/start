<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InterviewMode;
use App\Enums\InterviewStatus;
use App\Enums\Recommendation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Interview extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'mode'           => InterviewMode::class,
        'status'         => InterviewStatus::class,
        'recommendation' => Recommendation::class,
        'state'          => 'array',
        'overall_score'  => 'float',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $interview): void {
            $interview->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InterviewTemplate::class, 'template_id');
    }

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Avatar::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(InterviewInvitation::class, 'invitation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InterviewMessage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(InterviewEvent::class);
    }

    public function competencyScores(): HasMany
    {
        return $this->hasMany(CompetencyScore::class);
    }

    public function redFlags(): HasMany
    {
        return $this->hasMany(RedFlag::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(Recording::class);
    }

    public function sheetSyncs(): HasMany
    {
        return $this->hasMany(SheetSync::class);
    }

    public function behavioralAnalysis(): HasOne
    {
        return $this->hasOne(BehavioralAnalysis::class);
    }

    public function videoAnalysis(): HasOne
    {
        return $this->hasOne(VideoAnalysis::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(InterviewReport::class);
    }
}
