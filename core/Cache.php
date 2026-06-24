<?php
namespace App\Core;

/**
 * Simple file-based cache.
 */
class Cache
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (dirname(__DIR__) . '/storage/cache');
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    public function get(string $key, $default = null)
    {
        $file = $this->path($key);
        if (!file_exists($file)) {
            return $default;
        }
        $raw = file_get_contents($file);
        $data = $raw !== false ? @unserialize($raw) : false;
        if (!is_array($data) || !array_key_exists('expires', $data)) {
            return $default;
        }
        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            @unlink($file);
            return $default;
        }
        return $data['value'];
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $file = $this->path($key);
        $payload = serialize([
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value'   => $value,
        ]);
        return file_put_contents($file, $payload, LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->path($key);
        return file_exists($file) ? @unlink($file) : true;
    }

    public function clear(): void
    {
        foreach (glob($this->dir . '/*.cache') ?: [] as $f) {
            @unlink($f);
        }
    }

    public function remember(string $key, int $ttl, callable $callback)
    {
        $sentinel = '__cache_miss__';
        $value = $this->get($key, $sentinel);
        if ($value !== $sentinel) {
            return $value;
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    private function path(string $key): string
    {
        return $this->dir . '/' . sha1($key) . '.cache';
    }
}
