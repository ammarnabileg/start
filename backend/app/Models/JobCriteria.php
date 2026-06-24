<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobCriteria extends Model
{
    protected $fillable = [
        'recruitment_job_id', 'criterion', 'criterion_ar', 'weight', 'target_score', 'description', 'order',
    ];

    public function job() { return $this->belongsTo(RecruitmentJob::class, 'recruitment_job_id'); }
}
