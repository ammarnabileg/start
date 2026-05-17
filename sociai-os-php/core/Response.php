<?php
class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $status = 200): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): never
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    public static function view(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $file = VIEWS_PATH . '/' . str_replace('.', '/', $template) . '.php';
        if (!file_exists($file)) {
            http_response_code(500);
            echo "View not found: {$template}";
            exit;
        }
        require $file;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        header("Location: {$url}", true, $status);
        exit;
    }

    public static function setHeader(string $key, string $value): void
    {
        header("{$key}: {$value}");
    }

    public static function notFound(string $message = 'Not Found'): never
    {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error($message, 403);
    }
}
