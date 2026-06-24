<?php
class Cache {
    private static string $path;

    public static function init(): void {
        static::$path = defined('ROOT_DIR') ? ROOT_DIR . '/storage/cache' : dirname(__DIR__) . '/storage/cache';
        if (!is_dir(static::$path)) mkdir(static::$path, 0755, true);
    }

    private static function file(string $key): string {
        if (!isset(static::$path)) static::init();
        return static::$path . '/' . md5($key) . '.cache';
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): bool {
        $data = ['expires' => time() + $ttl, 'value' => $value];
        return (bool)file_put_contents(static::file($key), serialize($data), LOCK_EX);
    }

    public static function get(string $key): mixed {
        $file = static::file($key);
        if (!file_exists($file)) return null;
        $data = unserialize(file_get_contents($file));
        if (!$data || $data['expires'] < time()) { unlink($file); return null; }
        return $data['value'];
    }

    public static function delete(string $key): bool {
        $file = static::file($key);
        return file_exists($file) ? unlink($file) : true;
    }

    public static function flush(): bool {
        if (!isset(static::$path)) static::init();
        foreach (glob(static::$path . '/*.cache') as $f) unlink($f);
        return true;
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed {
        $val = static::get($key);
        if ($val !== null) return $val;
        $val = $callback();
        static::set($key, $val, $ttl);
        return $val;
    }

    public static function tenantKey(string $key, ?int $tenantId = null): string {
        $tid = $tenantId ?? (Database::getInstance()->getTenantId() ?? 0);
        return "t{$tid}:{$key}";
    }
}
