<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HumanInterviewEvaluation extends Model
{
    protected $fillable = [
        'human_interview_id', 'evaluated_by', 'technical_depth', 'problem_solving',
        'communication', 'culture_fit', 'takes_ownership', 'seniority_fit',
        'overall_rating', 'strengths', 'weaknesses', 'recommendation', 'notes',
    ];

    public function humanInterview() { return $this->belongsTo(HumanInterview::class); }
    public function evaluator() { return $this->belongsTo(User::class, 'evaluated_by'); }
}
