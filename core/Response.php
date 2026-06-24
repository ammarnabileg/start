<?php
namespace App\Core;

/**
 * HTTP response helper. JSON responses follow the platform contract:
 *   success: {"success": true, "data": ...}
 *   error:   {"success": false, "error": "..."}
 */
class Response
{
    public static function json($data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function success($data = null, string $message = '', int $status = 200): void
    {
        $payload = ['success' => true, 'data' => $data];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        self::json($payload, $status);
    }

    public static function error(string $message, int $status = 400, $details = null): void
    {
        $payload = ['success' => false, 'error' => $message];
        if ($details !== null) {
            $payload['details'] = $details;
        }
        self::json($payload, $status);
    }

    public static function redirect(string $url, int $status = 302): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Location: ' . $url);
        }
        exit;
    }

    /**
     * Render a PHP view. $template is relative to /views without extension.
     * Variables in $data are extracted into the template scope.
     */
    public static function view(string $template, array $data = []): void
    {
        $base = dirname(__DIR__) . '/views/';
        $path = $base . str_replace('.', '/', $template) . '.php';
        if (!file_exists($path)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($template);
            return;
        }
        extract($data, EXTR_SKIP);
        $__viewData = $data;
        include $path;
    }

    /**
     * Render a view into a string (used by layouts to embed content).
     */
    public static function render(string $template, array $data = []): string
    {
        ob_start();
        self::view($template, $data);
        return ob_get_clean() ?: '';
    }
}
