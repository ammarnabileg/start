<?php
/**
 * Auth + RBAC.
 *
 * Session schema:
 *   $_SESSION['crm_user'] = ['id'=>..,'name'=>..,'email'=>..,'role_id'=>..]
 *   $_SESSION['crm_perms'] = ['perm.key', ...]
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function auth_start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(CRM_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => CRM_SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }
}

function auth_login(string $email, string $password): bool {
    $user = db_one(
        "SELECT * FROM " . tbl('users') . " WHERE email = :e AND status = 'active' LIMIT 1",
        ['e' => strtolower(trim($email))]
    );
    if (!$user) return false;
    if (!password_verify($password, $user['password_hash'])) return false;

    if (password_needs_rehash($user['password_hash'], CRM_PASSWORD_ALGO)) {
        db_update(tbl('users'),
            ['password_hash' => password_hash($password, CRM_PASSWORD_ALGO)],
            'id = :id', ['id' => $user['id']]
        );
    }

    db_update(tbl('users'), ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
    auth_set_session($user);
    activity_log('login', 'user', $user['id'], null);
    return true;
}

function auth_logout(): void {
    auth_start_session();
    if (auth_user()) activity_log('logout', 'user', (int)auth_user()['id'], null);
    $_SESSION = [];
    session_destroy();
}

function auth_set_session(array $user): void {
    $_SESSION['crm_user'] = [
        'id'      => (int)$user['id'],
        'name'    => $user['name'],
        'email'   => $user['email'],
        'role_id' => (int)$user['role_id'],
    ];
    $_SESSION['crm_perms'] = auth_load_permissions((int)$user['role_id']);
    $_SESSION['crm_login_at'] = time();
}

function auth_load_permissions(int $roleId): array {
    $row = db_one('SELECT permissions FROM ' . tbl('roles') . ' WHERE id = :id', ['id' => $roleId]);
    if (!$row) return [];
    $perms = json_decode($row['permissions'] ?? '[]', true);
    return is_array($perms) ? $perms : [];
}

function auth_user(): ?array {
    return $_SESSION['crm_user'] ?? null;
}

function auth_id(): ?int {
    return auth_user()['id'] ?? null;
}

function auth_check(): bool {
    if (!auth_user()) return false;
    if (isset($_SESSION['crm_login_at']) && (time() - $_SESSION['crm_login_at']) > CRM_SESSION_LIFETIME) {
        auth_logout();
        return false;
    }
    return true;
}

function require_login(): void {
    auth_start_session();
    if (!auth_check()) {
        $next = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php?next=' . urlencode($next));
    }
}

function has_perm(string $perm): bool {
    $perms = $_SESSION['crm_perms'] ?? [];
    if (in_array('*', $perms, true)) return true;
    return in_array($perm, $perms, true);
}

function has_any_perm(array $perms): bool {
    foreach ($perms as $p) if (has_perm($p)) return true;
    return false;
}

function require_perm(string $perm): void {
    require_login();
    if (!has_perm($perm)) {
        http_response_code(403);
        include __DIR__ . '/forbidden.php';
        exit;
    }
}

/**
 * Scope filter: returns SQL fragment & params restricting rows by ownership
 * when the user only has `*.view.own` (not `*.view.all`).
 */
function scope_owned(string $entity, string $ownerColumn = 'owner_id'): array {
    $viewAll = has_perm("$entity.view.all") || has_perm("$entity.manage");
    if ($viewAll) return ['', []];
    return [" AND $ownerColumn = :owner_self ", ['owner_self' => auth_id()]];
}

function activity_log(string $action, ?string $entity, ?int $entityId, ?array $details): void {
    try {
        db_insert(tbl('activities'), [
            'user_id'     => auth_id(),
            'action'      => $action,
            'entity_type' => $entity,
            'entity_id'   => $entityId,
            'details'     => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) { /* swallow */ }
}
