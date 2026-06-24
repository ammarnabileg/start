<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'recruitment_job_id', 'candidate_id', 'candidate_cv_id',
        'status', 'pipeline_stage', 'cv_match_score', 'overall_score',
        'ai_recommendation', 'hr_notes', 'cv_analysis', 'assigned_to', 'applied_at',
    ];

    protected $casts = [
        'cv_analysis' => 'array',
        'applied_at' => 'datetime',
    ];

    const PIPELINE_STAGES = [
        'applied', 'ai_screening', 'qualified', 'disqualified',
        'tech_interview', 'manager_interview', 'final_review',
        'offer', 'hired', 'rejected', 'withdrawn',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function job() { return $this->belongsTo(RecruitmentJob::class, 'recruitment_job_id'); }
    public function candidate() { return $this->belongsTo(Candidate::class); }
    public function cv() { return $this->belongsTo(CandidateCv::class, 'candidate_cv_id'); }
    public function assignedTo() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function interviewSessions() { return $this->hasMany(InterviewSession::class); }
    public function aiEvaluation() { return $this->hasOne(AiEvaluation::class); }
    public function humanInterviews() { return $this->hasMany(HumanInterview::class); }
    public function offer() { return $this->hasOne(Offer::class); }
    public function invitationLinks() { return $this->hasMany(InvitationLink::class); }

    public function getLatestInterview() { return $this->interviewSessions()->latest()->first(); }

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
