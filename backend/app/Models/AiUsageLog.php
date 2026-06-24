<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'feature', 'model', 'input_tokens',
        'output_tokens', 'total_tokens', 'cost_usd', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function user() { return $this->belongsTo(User::class); }

    public static function track(string $feature, int $inputTokens, int $outputTokens, string $model = 'gpt-4o', ?int $tenantId = null, ?int $userId = null): void
    {
        $totalTokens = $inputTokens + $outputTokens;
        $costPer1k = match(true) {
            str_contains($model, 'gpt-4o') => 0.005,
            str_contains($model, 'gpt-4') => 0.03,
            default => 0.002,
        };

        static::create([
            'tenant_id' => $tenantId ?? auth()->user()?->tenant_id,
            'user_id' => $userId ?? auth()->id(),
            'feature' => $feature,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'cost_usd' => ($totalTokens / 1000) * $costPer1k,
        ]);
    }
}
