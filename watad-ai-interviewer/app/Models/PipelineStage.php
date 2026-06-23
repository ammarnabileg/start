<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineStage extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected $casts = ['is_terminal' => 'boolean'];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(HiringPipeline::class, 'pipeline_id');
    }
}
