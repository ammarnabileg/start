<?php
declare(strict_types=1);

class Cache
{
    private static string $dir = '';

    private static function dir(): string
    {
        if (!self::$dir) {
            self::$dir = (defined('STORAGE_PATH') ? STORAGE_PATH : sys_get_temp_dir()) . '/cache';
            if (!is_dir(self::$dir)) mkdir(self::$dir, 0755, true);
        }
        return self::$dir;
    }

    private static function path(string $key): string
    {
        return self::dir() . '/' . md5($key) . '.cache';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $path = self::path($key);
        if (!file_exists($path)) return $default;
        $raw  = file_get_contents($path);
        $data = unserialize($raw);
        if (!is_array($data) || $data['expires'] < time()) {
            @unlink($path);
            return $default;
        }
        return $data['value'];
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): void
    {
        file_put_contents(self::path($key), serialize(['value' => $value, 'expires' => time() + $ttl]), LOCK_EX);
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) return $cached;
        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function delete(string $key): void
    {
        @unlink(self::path($key));
    }

    public static function clear(): void
    {
        foreach (glob(self::dir() . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }
}
