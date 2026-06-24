<?php
class Response {
    public static function json(array $data, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): never {
        static::json(['ok' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): never {
        static::json(['ok' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    public static function paginated(array $data, array $meta): never {
        static::json(['ok' => true, 'data' => $data, 'meta' => $meta]);
    }

    public static function redirect(string $url, int $status = 302): never {
        http_response_code($status);
        header("Location: {$url}");
        exit;
    }

    public static function view(string $template, array $data = [], int $status = 200): void {
        http_response_code($status);
        extract($data);
        $viewPath = defined('VIEWS_PATH') ? VIEWS_PATH : dirname(__DIR__) . '/views';
        $file = $viewPath . '/' . str_replace('.', '/', $template) . '.php';
        if (file_exists($file)) require $file;
        else echo "View not found: {$template}";
    }
}
