<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HiringPipeline extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_default' => 'boolean'];

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class, 'pipeline_id')->orderBy('position');
    }
}
