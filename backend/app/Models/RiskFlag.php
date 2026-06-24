<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskFlag extends Model
{
    protected $fillable = ['ai_evaluation_id', 'flag_type', 'severity', 'description', 'evidence'];

    public function evaluation() { return $this->belongsTo(AiEvaluation::class, 'ai_evaluation_id'); }

    const SEVERITIES = ['low', 'medium', 'high'];

    public function getSeverityIcon(): string
    {
        return match($this->severity) {
            'high' => '🔴',
            'medium' => '🟠',
            default => '🟡',
        };
    }
}
