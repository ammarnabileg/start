<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterviewMessage extends Model
{
    protected $fillable = [
        'interview_session_id', 'role', 'content', 'message_type',
        'question_number', 'is_follow_up', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_follow_up' => 'boolean',
    ];

    public function session() { return $this->belongsTo(InterviewSession::class, 'interview_session_id'); }
}
