<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
}
