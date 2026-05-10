<?php
/**
 * REST API bootstrap.
 * Token-based auth via Authorization: Bearer crm_xxx...
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function api_authenticate(): array {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        api_error(401, 'missing_token');
    }
    $token = trim($m[1]);
    $hash = hash('sha256', $token);
    $row = db_one('
        SELECT t.*, u.id AS uid, u.role_id, u.status, r.permissions
        FROM ' . tbl('api_tokens') . ' t
        JOIN ' . tbl('users') . ' u ON u.id = t.user_id
        JOIN ' . tbl('roles') . ' r ON r.id = u.role_id
        WHERE t.token_hash = :h AND t.revoked_at IS NULL AND u.status = "active"
        LIMIT 1
    ', ['h' => $hash]);
    if (!$row) api_error(401, 'invalid_token');

    db_update(tbl('api_tokens'), ['last_used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);

    $perms = json_decode($row['permissions'], true) ?: [];
    $_SESSION['crm_user'] = ['id' => (int)$row['uid'], 'name' => '', 'email' => '', 'role_id' => (int)$row['role_id']];
    $_SESSION['crm_perms'] = $perms;

    if (!has_perm('api.use') && !in_array('*', $perms, true)) {
        api_error(403, 'api_use_denied');
    }
    return $row;
}

function api_error(int $code, string $message, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['error' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function api_ok($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return $_POST;
    $j = json_decode($raw, true);
    return is_array($j) ? $j : $_POST;
}
