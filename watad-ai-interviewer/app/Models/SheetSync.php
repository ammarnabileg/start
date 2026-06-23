<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SheetSync extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['synced_at' => 'datetime'];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }
}
