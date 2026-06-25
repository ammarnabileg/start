<?php
class Auth {
    private static ?array $user = null;

    public static function login(string $email, string $password): array|false {
        $db   = Database::getInstance();
        $user = $db->fetch(
            "SELECT u.*, t.slug as tenant_slug, t.name as tenant_name
             FROM users u
             LEFT JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = ? AND u.status = 'active'",
            [$email]
        );
        if (!$user || !password_verify($password, $user['password_hash'])) return false;

        // Load roles and permissions
        $roles = $db->fetchAll(
            "SELECT r.slug FROM roles r
             JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ?", [$user['id']]
        );
        $user['roles'] = array_column($roles, 'slug');

        $perms = $db->fetchAll(
            "SELECT DISTINCT p.slug FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = ?", [$user['id']]
        );
        $user['permissions'] = array_column($perms, 'slug');

        // Determine type
        if ($user['is_super_admin'] || in_array('super_admin', $user['roles'])) {
            $user['type'] = 'super_admin';
        } elseif (in_array('candidate', $user['roles'])) {
            $user['type'] = 'candidate';
        } else {
            $user['type'] = 'hr';
        }

        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        self::$user = $user;
        $db->query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
        return $user;
    }

    public static function logout(): void {
        self::$user = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool {
        if (self::$user) return true;
        if (!isset($_SESSION['user'])) return false;
        self::$user = $_SESSION['user'];
        return true;
    }

    public static function user(): ?array { return self::check() ? self::$user : null; }

    public static function id(): ?int { return self::check() ? (int)(self::$user['id'] ?? null) : null; }

    public static function tenantId(): ?int {
        return self::check() ? ((int)(self::$user['tenant_id'] ?? 0) ?: null) : null;
    }

    public static function can(string $permission): bool {
        $u = self::user();
        if (!$u) return false;
        if ($u['is_super_admin'] ?? false) return true;
        if (in_array('super_admin', $u['roles'] ?? [])) return true;
        if (in_array('company_owner', $u['roles'] ?? [])) return true;
        return in_array($permission, $u['permissions'] ?? []);
    }

    public static function isSuper(): bool {
        $u = self::user();
        return $u && (($u['is_super_admin'] ?? false) || ($u['type'] ?? '') === 'super_admin');
    }

    public static function isCandidate(): bool {
        $u = self::user();
        return $u && ($u['type'] ?? '') === 'candidate';
    }

    public static function isHR(): bool {
        $u = self::user();
        return $u && ($u['type'] ?? '') === 'hr';
    }

    public static function requireAuth(string $redirect = '/login'): void {
        if (!self::check()) { header("Location: {$redirect}"); exit; }
    }

    public static function requireSuper(): void {
        self::requireAuth();
        if (!self::isSuper()) { self::deny(); }
    }

    public static function requireHR(): void {
        self::requireAuth();
        if (!self::isHR() && !self::isSuper()) { header('Location: /login'); exit; }
    }

    public static function requirePermission(string $permission): void {
        self::requireAuth();
        if (!self::can($permission)) { self::deny(); }
    }

    private static function deny(): void {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Permission denied']);
            exit;
        }
        http_response_code(403);
        header('Location: /403');
        exit;
    }

    /** Refresh the session user data (e.g. after role change) */
    public static function refresh(): void {
        if (!self::check()) return;
        $uid = (int)self::$user['id'];
        $db  = Database::getInstance();
        $user = $db->fetch(
            "SELECT u.*, t.slug as tenant_slug, t.name as tenant_name
             FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id
             WHERE u.id = ?", [$uid]
        );
        if (!$user) return;
        $roles = $db->fetchAll("SELECT r.slug FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?",[$uid]);
        $user['roles'] = array_column($roles,'slug');
        $perms = $db->fetchAll("SELECT DISTINCT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id JOIN user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=?",[$uid]);
        $user['permissions'] = array_column($perms,'slug');
        $user['type'] = $user['is_super_admin'] ? 'super_admin' : (in_array('candidate',$user['roles']) ? 'candidate' : 'hr');
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        self::$user = $user;
    }
}
