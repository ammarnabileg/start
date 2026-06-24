<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("system_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'type' => $type]);
        Cache::forget("system_setting_{$key}");
    }

    public static function isInstalled(): bool
    {
        try {
            return (bool) static::get('is_installed', false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getAll(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }
}
