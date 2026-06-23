<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TalentPool extends Model
{
    protected $guarded = ['id'];

    public function candidates(): BelongsToMany
    {
        return $this->belongsToMany(Candidate::class, 'talent_pool_candidate')
            ->withPivot(['added_by', 'note', 'added_at']);
    }
}
