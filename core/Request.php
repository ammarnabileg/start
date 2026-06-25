<?php
class Request {
    private array $body = [];

    public function __construct() {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $raw = file_get_contents('php://input');
            $this->body = json_decode($raw ?: '{}', true) ?? [];
        } else {
            $this->body = $_POST;
        }
    }

    public function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }

    public function path(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return parse_url($uri, PHP_URL_PATH) ?: '/';
    }

    public function get(string $key, mixed $default = null): mixed {
        return $_GET[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed {
        return $this->body[$key] ?? $_POST[$key] ?? $default;
    }

    public function all(): array { return array_merge($_GET, $this->body); }

    public function only(array $keys): array {
        $result = [];
        foreach ($keys as $k) {
            $v = $this->input($k);
            if ($v !== null) $result[$k] = $v;
        }
        return $result;
    }

    public function isJson(): bool {
        return str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    }

    public function isAjax(): bool {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }

    public function csrf(): string {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf'];
    }

    public function verifyCsrf(): bool {
        $token = $this->input('_csrf') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return hash_equals($_SESSION['csrf'] ?? '', $token);
    }
}
