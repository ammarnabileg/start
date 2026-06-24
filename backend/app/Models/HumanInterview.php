<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HumanInterview extends Model
{
    protected $fillable = [
        'application_id', 'scheduled_by', 'interviewers', 'meeting_url',
        'location', 'type', 'status', 'scheduled_at', 'duration_minutes', 'notes',
    ];

    protected $casts = [
        'interviewers' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public function application() { return $this->belongsTo(Application::class); }
    public function scheduledBy() { return $this->belongsTo(User::class, 'scheduled_by'); }
    public function evaluations() { return $this->hasMany(HumanInterviewEvaluation::class); }
}
