<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BehavioralAnalysis extends Model
{
    protected $fillable = [
        'ai_evaluation_id', 'candidate_id', 'disc_profile', 'big_five',
        'growth_score', 'stress_score', 'leadership_style', 'learning_ability',
        'cultural_fit_notes',
    ];

    protected $casts = [
        'disc_profile' => 'array',
        'big_five' => 'array',
    ];

    public function evaluation() { return $this->belongsTo(AiEvaluation::class, 'ai_evaluation_id'); }
    public function candidate() { return $this->belongsTo(Candidate::class); }
}
