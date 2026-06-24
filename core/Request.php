<?php
class Request {
    private array $input;

    public function __construct() {
        $body = file_get_contents('php://input');
        $json = $body ? json_decode($body, true) : null;
        $this->input = array_merge($_POST, $json ?? []);
    }

    public function method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }
    public function path(): string { $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH); return rtrim($p, '/') ?: '/'; }
    public function input(string $key, mixed $default = null): mixed { return $this->input[$key] ?? $default; }
    public function get(string $key, mixed $default = null): mixed { return $_GET[$key] ?? $default; }
    public function all(): array { return array_merge($_GET, $this->input); }
    public function only(array $keys): array { return array_intersect_key($this->all(), array_flip($keys)); }
    public function file(string $key): ?array { return $_FILES[$key] ?? null; }
    public function isAjax(): bool { return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'; }
    public function isJson(): bool { return str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json'); }
    public function ip(): string { return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
    public function userAgent(): string { return $_SERVER['HTTP_USER_AGENT'] ?? ''; }
    public function expectsJson(): bool { return $this->isAjax() || $this->isJson() || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'); }

    public function csrf(): string {
        if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    public function validateCsrf(): bool {
        $token = $this->input('_csrf') ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    public function validate(array $rules): array {
        $errors = [];
        $data = $this->all();
        foreach ($rules as $field => $ruleStr) {
            $parts = explode('|', $ruleStr);
            $value = $data[$field] ?? null;
            foreach ($parts as $rule) {
                [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);
                switch ($ruleName) {
                    case 'required': if ($value === null || $value === '') $errors[$field][] = "The {$field} field is required."; break;
                    case 'email': if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[$field][] = "Invalid email format."; break;
                    case 'min': if ($value !== null && strlen((string)$value) < (int)$param) $errors[$field][] = "Minimum {$param} characters required."; break;
                    case 'max': if ($value !== null && strlen((string)$value) > (int)$param) $errors[$field][] = "Maximum {$param} characters allowed."; break;
                    case 'numeric': if ($value !== null && !is_numeric($value)) $errors[$field][] = "Must be a number."; break;
                    case 'url': if ($value && !filter_var($value, FILTER_VALIDATE_URL)) $errors[$field][] = "Invalid URL format."; break;
                    case 'in': if ($value && !in_array($value, explode(',', $param))) $errors[$field][] = "Invalid value."; break;
                }
            }
        }
        return $errors;
    }
}
