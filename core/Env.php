<?php
class Env {
    public static function load(string $path): void {
        if (!file_exists($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\"'");
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $val;
                putenv("{$key}={$val}");
            }
        }
    }
    public static function get(string $key, mixed $default = null): mixed {
        return $_ENV[$key] ?? $default;
    }
}
