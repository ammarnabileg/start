<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class JobApplication extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'status'           => ApplicationStatus::class,
        'applied_at'       => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $application): void {
            $application->public_id ??= (string) Str::ulid();
            $application->applied_at ??= now();
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

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function aiInterview(): BelongsTo
    {
        return $this->belongsTo(Interview::class, 'ai_interview_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function humanInterviews(): HasMany
    {
        return $this->hasMany(HumanInterview::class, 'application_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(HiringDecision::class, 'application_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'application_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CandidateActivity::class, 'application_id');
    }
}
