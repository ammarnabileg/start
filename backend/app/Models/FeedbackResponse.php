<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackResponse extends Model
{
    protected $fillable = [
        'interview_session_id', 'candidate_id', 'rating', 'feedback', 'suggestions', 'submitted_at', 'expires_at',
    ];

    protected $casts = ['submitted_at' => 'datetime', 'expires_at' => 'datetime'];

    public function interviewSession() { return $this->belongsTo(InterviewSession::class); }
    public function candidate() { return $this->belongsTo(Candidate::class); }

    public function isExpired(): bool { return $this->expires_at && $this->expires_at->isPast(); }
    public function isSubmitted(): bool { return !is_null($this->submitted_at); }
}
