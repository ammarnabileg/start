<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'action', 'model_type', 'model_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }

    public static function record(string $action, ?Model $model = null, array $oldValues = [], array $newValues = []): void
    {
        static::create([
            'tenant_id' => auth()->user()?->tenant_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
