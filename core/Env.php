<?php
class Env {
    private static bool $loaded = false;
    public static function load(string $path): void {
        if (self::$loaded || !file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key); $value = trim($value);
            if (strlen($value) >= 2) {
                $f = $value[0]; $l = $value[-1];
                if (($f === '"' && $l === '"') || ($f === "'" && $l === "'")) $value = substr($value, 1, -1);
            }
            $_ENV[$key] = $value; $_SERVER[$key] = $value; putenv("{$key}={$value}");
        }
        self::$loaded = true;
    }
    public static function get(string $key, mixed $default = null): mixed { return $_ENV[$key] ?? $default; }
    public static function bool(string $key, bool $default = false): bool { return in_array(strtolower($_ENV[$key] ?? ($default?'true':'false')), ['true','1','yes','on'], true); }
    public static function int(string $key, int $default = 0): int { return (int)($_ENV[$key] ?? $default); }
}
