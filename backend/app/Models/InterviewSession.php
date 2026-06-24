<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterviewSession extends Model
{
    protected $fillable = [
        'application_id', 'invitation_link_id', 'type', 'status',
        'questions_asked', 'max_questions', 'duration_seconds',
        'started_at', 'completed_at', 'metadata', 'detected_language',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function application() { return $this->belongsTo(Application::class); }
    public function messages() { return $this->hasMany(InterviewMessage::class); }
    public function aiEvaluation() { return $this->hasOne(AiEvaluation::class); }
    public function feedback() { return $this->hasOne(FeedbackResponse::class); }
    public function invitationLink() { return $this->belongsTo(InvitationLink::class); }

    public function isComplete(): bool { return $this->status === 'completed'; }
    public function isInProgress(): bool { return $this->status === 'in_progress'; }

    public function getTranscript(): array
    {
        return $this->messages()->orderBy('created_at')->get()->toArray();
    }

    public function getRemainingQuestions(): int
    {
        return max(0, $this->max_questions - $this->questions_asked);
    }
}
