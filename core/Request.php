<?php
namespace App\Core;

/**
 * HTTP request wrapper.
 */
class Request
{
    private ?array $jsonCache = null;

    public function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, $default = null)
    {
        $val = $_POST[$key] ?? null;
        if ($val === null) {
            $json = $this->json();
            $val = $json[$key] ?? null;
        }
        return $val ?? $default;
    }

    public function input(string $key, $default = null)
    {
        return $this->all()[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST, $this->json());
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function header(string $key): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        if (isset($_SERVER[$normalized])) {
            return $_SERVER[$normalized];
        }
        // Content-Type / Content-Length live without HTTP_ prefix.
        $alt = strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$alt] ?? null;
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool { return $this->method() === 'POST'; }
    public function isGet(): bool { return $this->method() === 'GET'; }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With') ?? '') === 'xmlhttprequest';
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', $_SERVER[$k])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');
        if ($header && preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public function json(): array
    {
        if ($this->jsonCache !== null) {
            return $this->jsonCache;
        }
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            $this->jsonCache = is_array($decoded) ? $decoded : [];
        } else {
            $this->jsonCache = [];
        }
        return $this->jsonCache;
    }

    public function validateCsrf(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $token = $_POST['csrf_token'] ?? $this->header('X-CSRF-Token') ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return $sessionToken !== '' && hash_equals($sessionToken, (string) $token);
    }

    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function uri(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }
}
