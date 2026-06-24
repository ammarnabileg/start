<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionBank extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'recruitment_job_id', 'question', 'question_ar',
        'skill_category', 'difficulty', 'language', 'ideal_answer_hints', 'is_follow_up',
    ];

    protected $casts = ['is_follow_up' => 'boolean'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function job() { return $this->belongsTo(RecruitmentJob::class, 'recruitment_job_id'); }
}
