<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiEvaluation extends Model
{
    protected $fillable = [
        'application_id', 'interview_session_id', 'overall_score',
        'recommendation', 'executive_summary', 'strengths', 'weaknesses',
        'missing_skills', 'criteria_scores', 'raw_response', 'tokens_used', 'evaluated_at',
    ];

    protected $casts = [
        'criteria_scores' => 'array',
        'raw_response' => 'array',
        'evaluated_at' => 'datetime',
    ];

    public function application() { return $this->belongsTo(Application::class); }
    public function interviewSession() { return $this->belongsTo(InterviewSession::class); }
    public function skillScores() { return $this->hasMany(SkillScore::class); }
    public function behavioralAnalysis() { return $this->hasOne(BehavioralAnalysis::class); }
    public function riskFlags() { return $this->hasMany(RiskFlag::class); }

    public function getRecommendationLabel(): string
    {
        return match(true) {
            $this->overall_score >= 82 => 'strong_recommendation',
            $this->overall_score >= 68 => 'suitable',
            $this->overall_score >= 50 => 'possible_fit',
            default => 'not_recommended',
        };
    }
}
