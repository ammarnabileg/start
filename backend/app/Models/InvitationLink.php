<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvitationLink extends Model
{
    protected $fillable = [
        'application_id', 'token', 'interview_type', 'expires_at', 'used_at', 'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function application() { return $this->belongsTo(Application::class); }
    public function interviewSessions() { return $this->hasMany(InterviewSession::class); }

    public function isValid(): bool
    {
        return $this->is_active && $this->expires_at->isFuture() && is_null($this->used_at);
    }

    public function markUsed(): void
    {
        $this->update(['used_at' => now(), 'is_active' => false]);
    }
}
