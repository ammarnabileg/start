<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'years_experience' => 'float',
        'expected_salary'  => 'float',
        'consent_at'       => 'datetime',
    ];

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function cvAnalyses(): HasMany
    {
        return $this->hasMany(CvAnalysis::class);
    }

    public function latestCvAnalysis(): HasOne
    {
        return $this->hasOne(CvAnalysis::class)->latestOfMany();
    }

    /* ---- v2: master profile + applications ---- */

    public function portalUser(): HasOne
    {
        return $this->hasOne(CandidateUser::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CandidateDocument::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CandidateNote::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CandidateActivity::class)->latest('occurred_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'candidate_tag');
    }

    public function talentPools(): BelongsToMany
    {
        return $this->belongsToMany(TalentPool::class, 'talent_pool_candidate')
            ->withPivot(['added_by', 'note', 'added_at']);
    }
}
