<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecruitmentJob extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'department_id', 'avatar_id', 'created_by', 'title', 'title_ar',
        'seniority', 'salary_min', 'salary_max', 'currency', 'description', 'description_ar',
        'requirements', 'responsibilities', 'benefits', 'interview_type', 'interview_language',
        'max_questions', 'interview_duration', 'status', 'is_public', 'published_at',
        'expires_at', 'ai_settings',
    ];

    protected $casts = [
        'ai_settings' => 'array',
        'is_public' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    const SENIORITY_LEVELS = ['intern', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'];
    const INTERVIEW_TYPES = ['text', 'voice', 'video'];
    const STATUSES = ['draft', 'active', 'paused', 'archived'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function avatar() { return $this->belongsTo(Avatar::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function criteria() { return $this->hasMany(JobCriteria::class); }
    public function questionBank() { return $this->hasMany(QuestionBank::class); }
    public function applications() { return $this->hasMany(Application::class); }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isExpired(): bool { return $this->expires_at && $this->expires_at->isPast(); }

    public function getApplicationStats(): array
    {
        return [
            'total' => $this->applications()->count(),
            'ai_screening' => $this->applications()->where('pipeline_stage', 'ai_screening')->count(),
            'qualified' => $this->applications()->where('pipeline_stage', 'qualified')->count(),
            'hired' => $this->applications()->where('pipeline_stage', 'hired')->count(),
        ];
    }
}
