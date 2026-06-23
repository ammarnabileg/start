<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InterviewInvitation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'expires_at'   => 'datetime',
        'opened_at'    => 'datetime',
        'reminded_at'  => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invitation): void {
            $invitation->token ??= Str::random(40);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function isUsable(): bool
    {
        return ! in_array($this->status, ['completed', 'expired', 'cancelled'], true)
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InterviewTemplate::class, 'template_id');
    }

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Avatar::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
