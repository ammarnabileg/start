<?php
class Response {
    public static function json(mixed $data, int $code = 200): never {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK'): never {
        self::json(['ok' => true, 'data' => $data, 'message' => $message]);
    }

    public static function error(string $message, int $code = 400, mixed $errors = null): never {
        self::json(['ok' => false, 'message' => $message, 'errors' => $errors], $code);
    }

    public static function paginated(array $data, int $total, int $page, int $perPage): never {
        self::json([
            'ok'        => true,
            'data'      => $data,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / max(1, $perPage)),
        ]);
    }

    public static function redirect(string $url, int $code = 302): never {
        http_response_code($code);
        header("Location: {$url}");
        exit;
    }

    public static function notFound(string $msg = 'Not found'): never {
        self::error($msg, 404);
    }
}
