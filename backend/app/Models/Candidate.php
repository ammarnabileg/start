<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'email', 'phone', 'country', 'city',
        'years_experience', 'target_salary', 'target_salary_currency',
        'linkedin_url', 'portfolio_url', 'bio', 'preferred_language',
        'skills', 'languages', 'is_active', 'last_activity_at',
    ];

    protected $casts = [
        'skills' => 'array',
        'languages' => 'array',
        'is_active' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function cvs() { return $this->hasMany(CandidateCv::class); }
    public function primaryCv() { return $this->hasOne(CandidateCv::class)->where('is_primary', true); }
    public function applications() { return $this->hasMany(Application::class); }
    public function talentPools() { return $this->belongsToMany(TalentPool::class, 'talent_pool_candidates')->withPivot('notes', 'added_by')->withTimestamps(); }
    public function skillScores() { return $this->hasMany(SkillScore::class); }
    public function behavioralAnalyses() { return $this->hasMany(BehavioralAnalysis::class); }

    public function getAverageSkillScores(): array
    {
        return $this->skillScores()
            ->selectRaw('skill_key, skill_name, skill_name_ar, AVG(score) as avg_score, AVG(weight) as weight')
            ->groupBy('skill_key', 'skill_name', 'skill_name_ar')
            ->get()
            ->toArray();
    }
}
