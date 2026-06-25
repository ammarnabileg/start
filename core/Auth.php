<?php
class Auth {
    private static ?array $currentUser = null;

    public static function login(string $email, string $password, ?string $tenantSlug = null): array|false {
        $db = Database::getInstance();
        $user = null;

        if ($tenantSlug) {
            $tenant = $db->fetch("SELECT id FROM tenants WHERE slug = ? AND status = 'active'", [$tenantSlug]);
            if (!$tenant) return false;
            $user = $db->fetch("SELECT u.*, t.name as tenant_name, t.slug as tenant_slug FROM users u
                LEFT JOIN tenants t ON t.id = u.tenant_id
                WHERE u.email = ? AND u.tenant_id = ? AND u.status = 'active'", [$email, $tenant['id']]);
        } else {
            $user = $db->fetch("SELECT u.*, t.name as tenant_name, t.slug as tenant_slug FROM users u
                LEFT JOIN tenants t ON t.id = u.tenant_id
                WHERE u.email = ? AND u.status = 'active'", [$email]);
        }

        if (!$user || !password_verify($password, $user['password_hash'])) return false;

        // Load roles and permissions
        $roles = $db->fetchAll("SELECT r.slug FROM roles r
            JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?", [$user['id']]);
        $user['roles'] = array_column($roles, 'slug');

        $permissions = $db->fetchAll("SELECT DISTINCT p.slug FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?", [$user['id']]);
        $user['permissions'] = array_column($permissions, 'slug');

        // Compute a virtual 'type' field from DB flags + roles (schema has no type column)
        if (!empty($user['is_super_admin']) || in_array('super_admin', $user['roles'])) {
            $user['type'] = 'super_admin';
        } elseif (in_array('candidate', $user['roles'])) {
            $user['type'] = 'candidate';
        } else {
            $user['type'] = 'company_user';
        }

        // Compute full_name for convenience
        $user['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        unset($user['password_hash'], $user['remember_token'], $user['two_fa_secret']);

        $secret = $_ENV['JWT_SECRET'] ?? 'default-secret';
        $expiry = (int)($_ENV['JWT_EXPIRY'] ?? 86400);
        $token = JWT::encode(['sub' => $user['id'], 'type' => $user['type'], 'tid' => $user['tenant_id']], $secret, $expiry);

        $_SESSION['user'] = $user;
        $_SESSION['token'] = $token;
        self::$currentUser = $user;

        // Update last login
        $db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

        return ['user' => $user, 'token' => $token];
    }

    public static function logout(): void {
        self::$currentUser = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool {
        if (self::$currentUser) return true;
        if (!isset($_SESSION['user'], $_SESSION['token'])) return false;
        $secret = $_ENV['JWT_SECRET'] ?? 'default-secret';
        if (!JWT::verify($_SESSION['token'], $secret)) {
            self::logout();
            return false;
        }
        self::$currentUser = $_SESSION['user'];
        return true;
    }

    public static function user(): ?array { return self::check() ? self::$currentUser : null; }

    public static function can(string $permission): bool {
        $user = self::user();
        if (!$user) return false;
        if (!empty($user['is_super_admin']) || in_array('super_admin', $user['roles'] ?? [])) return true;
        return in_array($permission, $user['permissions'] ?? []);
    }

    public static function isSuper(): bool {
        $user = self::user();
        if (!$user) return false;
        return $user['type'] === 'super_admin'
            || !empty($user['is_super_admin'])
            || in_array('super_admin', $user['roles'] ?? []);
    }

    public static function isTenantUser(): bool {
        $user = self::user();
        return $user && $user['type'] === 'company_user';
    }

    public static function isCandidate(): bool {
        $user = self::user();
        return $user && $user['type'] === 'candidate';
    }

    public static function requireAuth(string $redirect = '/login'): void {
        if (!self::check()) {
            header("Location: {$redirect}"); exit;
        }
    }

    public static function requireSuper(): void {
        self::requireAuth();
        if (!self::isSuper()) { header('Location: /unauthorized'); exit; }
    }

    public static function requirePermission(string $permission): void {
        self::requireAuth();
        if (!self::can($permission)) { header('Location: /unauthorized'); exit; }
    }

    public static function hasRole(string $role): bool {
        $user = self::user();
        return $user && in_array($role, $user['roles'] ?? []);
    }
}
