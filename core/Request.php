<?php
declare(strict_types=1);

class Request
{
    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        $path = $pos !== false ? substr($uri, 0, $pos) : $uri;
        return '/' . trim($path, '/') ?: '/';
    }

    public function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }
        return $method;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (isset($_POST[$key])) return $_POST[$key];
        if (isset($_GET[$key])) return $_GET[$key];
        $json = $this->json();
        return $json[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST, $this->json() ?? []);
    }

    public function json(): ?array
    {
        static $parsed = null;
        if ($parsed !== null) return $parsed;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $body   = file_get_contents('php://input');
            $parsed = json_decode($body, true) ?? [];
        } else {
            $parsed = [];
        }
        return $parsed;
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function header(string $key): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$key] ?? ($_SERVER[strtoupper(str_replace('-', '_', $key))] ?? null);
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) return trim(explode(',', $_SERVER[$k])[0]);
        }
        return '0.0.0.0';
    }

    public function isAjax(): bool
    {
        return ($this->header('X-Requested-With') === 'XMLHttpRequest')
            || str_contains($this->header('Accept') ?? '', 'application/json');
    }

    public function isPost(): bool   { return $this->method() === 'POST'; }
    public function isGet(): bool    { return $this->method() === 'GET'; }
    public function isDelete(): bool { return $this->method() === 'DELETE'; }
    public function isPut(): bool    { return $this->method() === 'PUT'; }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function bearerToken(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) return substr($auth, 7);
        return null;
    }
}
