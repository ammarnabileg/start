<?php
/**
 * SociAI OS - HTTP Request Abstraction
 */

declare(strict_types=1);

namespace SociAI\Core;

class Request
{
    private array  $jsonBody  = [];
    private bool   $jsonParsed = false;

    // --------------------------------------------------------
    // GET parameters
    // --------------------------------------------------------
    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
    }

    // --------------------------------------------------------
    // POST parameters
    // --------------------------------------------------------
    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $this->jsonBody()[$key] ?? $default;
    }

    public function postInt(string $key, int $default = 0): int
    {
        $val = $this->post($key);
        return $val !== null ? (int)$val : $default;
    }

    // --------------------------------------------------------
    // JSON body
    // --------------------------------------------------------
    public function json(string $key = null, mixed $default = null): mixed
    {
        $body = $this->jsonBody();
        if ($key === null) {
            return $body;
        }
        return $body[$key] ?? $default;
    }

    private function jsonBody(): array
    {
        if (!$this->jsonParsed) {
            $this->jsonParsed = true;
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $raw = file_get_contents('php://input');
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $this->jsonBody = $decoded;
                    }
                }
            }
        }
        return $this->jsonBody;
    }

    // --------------------------------------------------------
    // All input (GET + POST + JSON merged)
    // --------------------------------------------------------
    public function all(): array
    {
        return array_merge($_GET, $_POST, $this->jsonBody());
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return isset($_GET[$key]) || isset($_POST[$key]) || isset($this->jsonBody()[$key]);
    }

    public function filled(string $key): bool
    {
        $val = $this->post($key) ?? $this->get($key);
        return $val !== null && $val !== '';
    }

    // --------------------------------------------------------
    // File uploads
    // --------------------------------------------------------
    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    // --------------------------------------------------------
    // Headers
    // --------------------------------------------------------
    public function header(string $key): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$serverKey] ?? $_SERVER[$key] ?? null;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization') ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    // --------------------------------------------------------
    // Request meta
    // --------------------------------------------------------
    public function ip(): string
    {
        return Security::getClientIp();
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        return strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    }

    public function fullUrl(): string
    {
        $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
    }

    public function isAjax(): bool
    {
        return ($this->header('X-Requested-With') === 'XMLHttpRequest')
            || str_contains($this->header('Accept') ?? '', 'application/json');
    }

    public function isPost(): bool  { return $this->method() === 'POST';   }
    public function isGet(): bool   { return $this->method() === 'GET';    }
    public function isPut(): bool   { return $this->method() === 'PUT';    }
    public function isDelete(): bool { return $this->method() === 'DELETE'; }
    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    }

    public function userAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
    }

    // --------------------------------------------------------
    // Validation (simple, chainable)
    // --------------------------------------------------------
    public function validate(array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $ruleList = is_array($rule) ? $rule : explode('|', $rule);
            $value    = $this->post($field) ?? $this->get($field) ?? null;

            foreach ($ruleList as $r) {
                [$ruleName, $ruleParam] = array_pad(explode(':', $r, 2), 2, null);
                $error = $this->applyRule($field, $value, $ruleName, $ruleParam);
                if ($error) {
                    $errors[$field][] = $error;
                    break; // Stop at first error per field
                }
            }
        }
        return $errors;
    }

    private function applyRule(string $field, mixed $value, string $rule, ?string $param): ?string
    {
        return match ($rule) {
            'required' => (empty($value) && $value !== '0')
                ? ucfirst($field) . ' is required.'
                : null,
            'email'    => ($value && !Security::validateEmail($value))
                ? ucfirst($field) . ' must be a valid email.'
                : null,
            'url'      => ($value && !Security::validateUrl($value))
                ? ucfirst($field) . ' must be a valid URL.'
                : null,
            'min'      => (strlen((string)$value) < (int)$param)
                ? ucfirst($field) . " must be at least {$param} characters."
                : null,
            'max'      => (strlen((string)$value) > (int)$param)
                ? ucfirst($field) . " must not exceed {$param} characters."
                : null,
            'numeric'  => ($value !== null && !is_numeric($value))
                ? ucfirst($field) . ' must be a number.'
                : null,
            'in'       => ($value && !in_array($value, explode(',', (string)$param), true))
                ? ucfirst($field) . " must be one of: {$param}."
                : null,
            'uuid'     => ($value && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value))
                ? ucfirst($field) . ' must be a valid UUID.'
                : null,
            default    => null,
        };
    }
}
