<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['value' => 'array'];

    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        return static::where('group', $group)->where('key', $key)->first()?->value ?? $default;
    }

    public static function put(string $group, string $key, mixed $value): void
    {
        static::updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
    }
}
