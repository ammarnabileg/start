<?php
declare(strict_types=1);

class Auth
{
    private static ?array $currentUser = null;

    public static function login(string $email, string $password): array|false
    {
        $db   = Database::getInstance();
        $user = $db->fetch(
            "SELECT u.*, t.name AS tenant_name, t.slug AS tenant_slug, t.status AS tenant_status
             FROM users u
             LEFT JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = ? AND u.status = 'active'",
            [strtolower(trim($email))]
        );
        if (!$user || !password_verify($password, $user['password_hash'])) return false;
        if ($user['tenant_id'] && $user['tenant_status'] === 'suspended') return false;

        $roles = $db->fetchAll(
            "SELECT r.slug FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?",
            [$user['id']]
        );
        $user['roles'] = array_column($roles, 'slug');

        $perms = $db->fetchAll(
            "SELECT DISTINCT p.slug FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?
             UNION
             SELECT p.slug FROM permissions p
             JOIN user_permissions up ON up.permission_id = p.id
             WHERE up.user_id = ? AND up.granted = 1",
            [$user['id'], $user['id']]
        );
        $user['permissions'] = array_column($perms, 'slug');

        if (!empty($user['is_super_admin']) || in_array('super_admin', $user['roles'])) {
            $user['type'] = 'super_admin';
        } elseif (in_array('candidate', $user['roles'])) {
            $user['type'] = 'candidate';
        } else {
            $user['type'] = 'company_user';
        }

        $user['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        unset($user['password_hash'], $user['remember_token']);

        $secret = $_ENV['JWT_SECRET'] ?? 'change-me';
        $expiry = (int)($_ENV['JWT_EXPIRY'] ?? 86400);
        $token  = JWT::encode(['sub' => $user['id'], 'type' => $user['type'], 'tid' => $user['tenant_id']], $secret, $expiry);

        $_SESSION['user']  = $user;
        $_SESSION['token'] = $token;
        self::$currentUser = $user;

        $db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user['id']]);
        if (class_exists('Tenant', false) && $user['tenant_id']) {
            Tenant::set((int)$user['tenant_id']);
        }

        return ['user' => $user, 'token' => $token];
    }

    public static function logout(): void
    {
        self::$currentUser = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        if (self::$currentUser) return true;
        if (!isset($_SESSION['user'], $_SESSION['token'])) return false;
        $secret = $_ENV['JWT_SECRET'] ?? 'change-me';
        if (!JWT::verify($_SESSION['token'], $secret)) { self::logout(); return false; }
        self::$currentUser = $_SESSION['user'];
        if (class_exists('Tenant', false) && !empty(self::$currentUser['tenant_id'])) {
            Tenant::set((int)self::$currentUser['tenant_id']);
        }
        return true;
    }

    public static function user(): ?array   { return self::check() ? self::$currentUser : null; }
    public static function id(): ?int       { return self::user() ? (int)self::user()['id'] : null; }
    public static function tenantId(): ?int { $u = self::user(); return $u ? ($u['tenant_id'] ? (int)$u['tenant_id'] : null) : null; }

    public static function isSuper(): bool
    {
        $u = self::user();
        if (!$u) return false;
        return $u['type'] === 'super_admin' || !empty($u['is_super_admin']) || in_array('super_admin', $u['roles'] ?? []);
    }

    public static function isCandidate(): bool
    {
        $u = self::user();
        return $u && $u['type'] === 'candidate';
    }

    public static function isCompanyUser(): bool
    {
        $u = self::user();
        return $u && $u['type'] === 'company_user';
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        if (!$u) return false;
        if (self::isSuper()) return true;
        return in_array($permission, $u['permissions'] ?? []);
    }

    public static function hasRole(string $role): bool
    {
        $u = self::user();
        if (!$u) return false;
        if (self::isSuper()) return true;
        return in_array($role, $u['roles'] ?? []);
    }

    public static function requireAuth(string $redirect = '/login'): void
    {
        if (!self::check()) {
            header("Location: {$redirect}"); exit;
        }
    }

    public static function requireRole(string $role, string $redirect = '/dashboard'): void
    {
        self::requireAuth();
        if (!self::hasRole($role) && !self::isSuper()) {
            header("Location: {$redirect}"); exit;
        }
    }

    public static function requirePermission(string $permission, string $redirect = '/dashboard'): void
    {
        self::requireAuth();
        if (!self::can($permission)) {
            http_response_code(403);
            require VIEWS_PATH . '/errors/403.php';
            exit;
        }
    }

    public static function refreshUser(): void
    {
        if (!self::check()) return;
        $userId = self::id();
        $db     = Database::getInstance();
        $user   = $db->fetch(
            "SELECT u.*, t.name AS tenant_name, t.slug AS tenant_slug FROM users u
             LEFT JOIN tenants t ON t.id = u.tenant_id WHERE u.id = ?",
            [$userId]
        );
        if (!$user) return;
        $roles = $db->fetchAll("SELECT r.slug FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?", [$userId]);
        $user['roles'] = array_column($roles, 'slug');
        $perms = $db->fetchAll(
            "SELECT DISTINCT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?",
            [$userId]
        );
        $user['permissions'] = array_column($perms, 'slug');
        $user['type']      = !empty($user['is_super_admin']) ? 'super_admin' : (in_array('candidate', $user['roles']) ? 'candidate' : 'company_user');
        $user['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        unset($user['password_hash']);
        $_SESSION['user']  = $user;
        self::$currentUser = $user;
    }
}
