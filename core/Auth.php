<?php
namespace App\Core;

use RuntimeException;

/**
 * Authentication facade. Combines JWT (stateless API) and PHP session
 * (web) so the platform works for both API clients and server-rendered views.
 */
class Auth
{
    private Database $db;
    private RBAC $rbac;
    private array $config;
    private ?array $currentUser = null;

    public function __construct(?Database $db = null, ?array $config = null)
    {
        $this->db = $db ?? Database::instance();
        $this->rbac = new RBAC($this->db);
        $this->config = $config ?? require dirname(__DIR__) . '/config/app.php';
    }

    /**
     * Validate credentials and return a signed JWT, or false on failure.
     */
    public function login(string $email, string $password, ?int $tenantId = null)
    {
        $params = [':email' => $email];
        $sql = 'SELECT * FROM users WHERE email = :email';
        if ($tenantId !== null) {
            $sql .= ' AND (tenant_id = :tid OR is_super_admin = 1)';
            $params[':tid'] = $tenantId;
        }
        $sql .= ' LIMIT 1';
        $user = $this->db->fetch($sql, $params);

        if (!$user || $user['status'] !== 'active') {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        $this->db->query('UPDATE users SET last_login_at = NOW() WHERE id = :id', [':id' => $user['id']]);

        $token = JWT::sign([
            'sub'       => (int) $user['id'],
            'tenant_id' => $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null,
            'email'     => $user['email'],
            'is_super'  => (int) $user['is_super_admin'],
        ], $this->config['jwt']['secret'], $this->config['jwt']['expiry']);

        $this->startSession();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['tenant_id'] = $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null;
        $_SESSION['jwt'] = $token;
        $this->currentUser = $user;

        return $token;
    }

    public function logout(): void
    {
        $this->startSession();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->currentUser = null;
    }

    /**
     * Authenticated if a valid session user OR a valid bearer token exists.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user(): ?array
    {
        if ($this->currentUser !== null) {
            return $this->currentUser;
        }
        $userId = $this->resolveUserId();
        if ($userId === null) {
            return null;
        }
        $user = $this->db->fetch('SELECT * FROM users WHERE id = :id LIMIT 1', [':id' => $userId]);
        $this->currentUser = $user ?: null;
        return $this->currentUser;
    }

    public function id(): ?int
    {
        $u = $this->user();
        return $u ? (int) $u['id'] : null;
    }

    public function can(string $permission): bool
    {
        $u = $this->user();
        if (!$u) {
            return false;
        }
        if ((int) ($u['is_super_admin'] ?? 0) === 1) {
            return true;
        }
        return $this->rbac->can((int) $u['id'], $permission);
    }

    public function hasRole(string $role): bool
    {
        $u = $this->user();
        if (!$u) {
            return false;
        }
        foreach ($this->rbac->getUserRoles((int) $u['id']) as $r) {
            if ($r['name'] === $role) {
                return true;
            }
        }
        return false;
    }

    public function requireAuth(): void
    {
        if (!$this->check()) {
            Response::error('Unauthorized', 401);
            exit;
        }
    }

    public function requirePermission(string $permission): void
    {
        $this->requireAuth();
        if (!$this->can($permission)) {
            Response::error('Forbidden: missing permission ' . $permission, 403);
            exit;
        }
    }

    private function resolveUserId(): ?int
    {
        // Bearer token first (API).
        $token = (new Request())->bearerToken();
        if ($token) {
            $payload = JWT::verify($token, $this->config['jwt']['secret']);
            if ($payload && isset($payload['sub'])) {
                return (int) $payload['sub'];
            }
        }
        // Session fallback (web).
        $this->startSession();
        if (!empty($_SESSION['jwt'])) {
            $payload = JWT::verify($_SESSION['jwt'], $this->config['jwt']['secret']);
            if ($payload && isset($payload['sub'])) {
                return (int) $payload['sub'];
            }
        }
        if (!empty($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }
        return null;
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
            @session_start();
        }
    }
}
