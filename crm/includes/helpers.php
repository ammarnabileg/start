<?php
/**
 * Generic helpers: escape, csrf, flash, redirect, validation.
 */

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string {
    return rtrim(CRM_BASE_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

function back(): void {
    $ref = $_SERVER['HTTP_REFERER'] ?? url('dashboard.php');
    header('Location: ' . $ref);
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION[CRM_CSRF_TOKEN_KEY])) {
        $_SESSION[CRM_CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CRM_CSRF_TOKEN_KEY];
}

function csrf_field(): string {
    return '<input type="hidden" name="' . CRM_CSRF_TOKEN_KEY . '" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $sent = $_POST[CRM_CSRF_TOKEN_KEY] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token() ?: '', (string)$sent)) {
        http_response_code(419);
        die('Invalid CSRF token.');
    }
}

function flash(string $type, string $message): void {
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array {
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function input(string $key, $default = null) {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function require_input(array $keys): array {
    $values = [];
    $missing = [];
    foreach ($keys as $k) {
        $v = trim((string)($_POST[$k] ?? ''));
        if ($v === '') $missing[] = $k;
        $values[$k] = $v;
    }
    if (!empty($missing)) {
        flash('error', 'حقول مطلوبة ناقصة: ' . implode(', ', $missing));
        back();
    }
    return $values;
}

function format_money($amount, string $currency = CRM_DEFAULT_CURRENCY): string {
    return number_format((float)$amount, 2) . ' ' . $currency;
}

function format_date(?string $datetime, string $fmt = 'Y-m-d H:i'): string {
    if (!$datetime) return '—';
    $ts = strtotime($datetime);
    return $ts ? date($fmt, $ts) : '—';
}

function time_ago(?string $datetime): string {
    if (!$datetime) return '—';
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'الآن';
    if ($diff < 3600) return floor($diff / 60) . ' د';
    if ($diff < 86400) return floor($diff / 3600) . ' س';
    if ($diff < 2592000) return floor($diff / 86400) . ' ي';
    return date('Y-m-d', $ts);
}

function paginate(int $total, int $perPage, int $page): array {
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'limit' => $perPage, 'total' => $total];
}

function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function pluck(array $rows, string $key): array {
    return array_map(fn($r) => $r[$key], $rows);
}
