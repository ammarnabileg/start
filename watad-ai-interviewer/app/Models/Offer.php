<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Offer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'status'       => OfferStatus::class,
        'salary'       => 'float',
        'start_date'   => 'date',
        'expires_at'   => 'datetime',
        'signed_at'    => 'datetime',
        'sent_at'      => 'datetime',
        'responded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $o) => $o->public_id ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'application_id');
    }
}
