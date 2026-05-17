<?php
/**
 * SociAI OS - Authentication System
 * Session-based auth with JWT support, 2FA, CSRF, and full lifecycle.
 */

declare(strict_types=1);

namespace SociAI\Core;

use SociAI\Models\User;
use RuntimeException;

class Auth
{
    private static ?array $currentUser = null;

    // --------------------------------------------------------
    // Session Bootstrap
    // --------------------------------------------------------
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $cookieParams = [
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        session_set_cookie_params($cookieParams);
        session_name(SESSION_NAME);
        session_start();

        // Session fixation protection: regenerate ID periodically
        if (!isset($_SESSION['_created'])) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 1800) {
            // Rotate session ID every 30 minutes
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }

    // --------------------------------------------------------
    // Registration
    // --------------------------------------------------------
    public static function register(array $data): array
    {
        $db = Database::getInstance();

        // Validate required fields
        $errors = [];
        if (empty($data['email']) || !Security::validateEmail($data['email'])) {
            $errors['email'] = 'Valid email address is required.';
        }
        if (empty($data['username']) || !Security::validateUsername($data['username'])) {
            $errors['username'] = 'Username must be 3-64 chars (letters, numbers, _, -, .)';
        }
        if (empty($data['password'])) {
            $errors['password'] = 'Password is required.';
        } else {
            $pwErrors = Security::validatePassword($data['password']);
            if (!empty($pwErrors)) {
                $errors['password'] = implode(' ', $pwErrors);
            }
        }
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check uniqueness
        $existingEmail    = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$data['email']]);
        $existingUsername = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$data['username']]);

        if ($existingEmail) {
            return ['success' => false, 'errors' => ['email' => 'Email address already registered.']];
        }
        if ($existingUsername) {
            return ['success' => false, 'errors' => ['username' => 'Username already taken.']];
        }

        $userId = Security::generateUUID();
        $verificationToken = Security::generateToken(32);

        $db->insert('users', [
            'id'                => $userId,
            'email'             => strtolower(trim($data['email'])),
            'username'          => trim($data['username']),
            'full_name'         => trim($data['full_name'] ?? ''),
            'password_hash'     => self::hashPassword($data['password']),
            'preferred_language' => $data['language'] ?? 'en',
            'timezone'          => $data['timezone'] ?? 'UTC',
            'is_active'         => 1,
            'is_verified'       => 0,
        ]);

        // Store verification token in session/cache (simplified; use email in production)
        $_SESSION['verification_tokens'][$verificationToken] = [
            'user_id' => $userId,
            'expires' => time() + 86400,
        ];

        return [
            'success'            => true,
            'user_id'            => $userId,
            'verification_token' => $verificationToken,
        ];
    }

    // --------------------------------------------------------
    // Login
    // --------------------------------------------------------
    public static function login(string $email, string $password, bool $remember = false): array
    {
        $db = Database::getInstance();
        $ip = Security::getClientIp();

        // Rate limit by IP
        $rl = Security::rateLimit('login_' . $ip, ...RATE_LIMIT_LOGIN);
        if (!$rl['allowed']) {
            return [
                'success' => false,
                'message' => 'Too many login attempts. Try again later.',
                'reset_at' => $rl['reset_at'],
            ];
        }

        $user = $db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1",
            [strtolower(trim($email))]
        );

        $success = false;
        $failureReason = null;

        if (!$user) {
            $failureReason = 'user_not_found';
        } elseif (!$user['is_active']) {
            $failureReason = 'account_inactive';
        } elseif (!self::verifyPassword($password, $user['password_hash'])) {
            $failureReason = 'wrong_password';
        } else {
            $success = true;
        }

        // Log attempt
        $db->insert('login_history', [
            'id'             => Security::generateUUID(),
            'user_id'        => $user['id'] ?? null,
            'ip_address'     => $ip,
            'user_agent'     => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
            'success'        => $success ? 1 : 0,
            'failure_reason' => $failureReason,
        ]);

        if (!$success) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        // If 2FA enabled, return challenge
        if ($user['two_factor_enabled']) {
            $_SESSION['pending_2fa_user_id'] = $user['id'];
            return ['success' => true, 'requires_2fa' => true, 'user_id' => $user['id']];
        }

        // Clear rate limit on success
        Security::clearRateLimit('login_' . $ip);

        // Create session
        self::createSession($user, $remember);

        return ['success' => true, 'user' => self::sanitizeUser($user)];
    }

    // --------------------------------------------------------
    // 2FA Verification (TOTP)
    // --------------------------------------------------------
    public static function verify2FA(string $code): array
    {
        $userId = $_SESSION['pending_2fa_user_id'] ?? null;
        if (!$userId) {
            return ['success' => false, 'message' => '2FA session expired.'];
        }

        $db   = Database::getInstance();
        $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

        if (!$user || !$user['two_factor_secret']) {
            return ['success' => false, 'message' => 'Invalid 2FA session.'];
        }

        $secret = Security::decrypt($user['two_factor_secret']);
        if (!self::verifyTOTP($secret, $code)) {
            return ['success' => false, 'message' => 'Invalid 2FA code. Please try again.'];
        }

        unset($_SESSION['pending_2fa_user_id']);
        self::createSession($user);
        return ['success' => true, 'user' => self::sanitizeUser($user)];
    }

    // --------------------------------------------------------
    // Session Creation
    // --------------------------------------------------------
    private static function createSession(array $user, bool $remember = false): void
    {
        $db    = Database::getInstance();
        $token = Security::generateToken(48);
        $hash  = Security::hashToken($token);
        $ttl   = $remember ? 2592000 : SESSION_LIFETIME; // 30 days or default
        $sessionId = Security::generateUUID();

        $db->insert('user_sessions', [
            'id'         => $sessionId,
            'user_id'    => $user['id'],
            'token_hash' => $hash,
            'device_info'=> json_encode([
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'platform'   => $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '',
            ]),
            'ip_address' => Security::getClientIp(),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl),
        ]);

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['session_id_db'] = $sessionId;
        $_SESSION['logged_in']  = true;
        $_SESSION['login_time'] = time();

        // Also set a cookie for "remember me"
        if ($remember) {
            setcookie(
                'sociai_remember',
                $token,
                ['expires' => time() + $ttl, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]
            );
        }

        self::$currentUser = $user;
    }

    // --------------------------------------------------------
    // Logout
    // --------------------------------------------------------
    public static function logout(): void
    {
        $db = Database::getInstance();

        if (isset($_SESSION['session_id_db'])) {
            $db->delete('user_sessions', 'id = ?', [$_SESSION['session_id_db']]);
        }

        // Destroy session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Clear remember cookie
        setcookie('sociai_remember', '', time() - 3600, '/');
        self::$currentUser = null;
    }

    // --------------------------------------------------------
    // Get Current User
    // --------------------------------------------------------
    public static function getCurrentUser(): ?array
    {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            // Try remember-me cookie
            $token = $_COOKIE['sociai_remember'] ?? null;
            if ($token) {
                $user = self::getUserByRememberToken($token);
                if ($user) {
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['logged_in'] = true;
                    self::$currentUser = $user;
                    return $user;
                }
            }
            return null;
        }

        $db   = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL",
            [$userId]
        );

        if (!$user) {
            self::logout();
            return null;
        }

        self::$currentUser = $user;
        return $user;
    }

    private static function getUserByRememberToken(string $token): ?array
    {
        $db   = Database::getInstance();
        $hash = Security::hashToken($token);
        $session = $db->fetchOne(
            "SELECT us.*, u.* FROM user_sessions us
             JOIN users u ON u.id = us.user_id
             WHERE us.token_hash = ? AND us.expires_at > NOW() AND u.is_active = 1",
            [$hash]
        );
        return $session ?: null;
    }

    // --------------------------------------------------------
    // Auth Guards
    // --------------------------------------------------------
    public static function requireAuth(): void
    {
        if (!self::isLoggedIn()) {
            if (self::isApiRequest()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Authentication required.']);
                exit;
            }
            $return = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: /auth/login?return=' . $return);
            exit;
        }
    }

    public static function requireRole(string $brandId, string ...$roles): void
    {
        self::requireAuth();
        $user = self::getCurrentUser();
        if (!$user) {
            abort(401, 'Unauthorized');
        }
        $db   = Database::getInstance();
        $member = $db->fetchOne(
            "SELECT role FROM team_members WHERE brand_id = ? AND user_id = ?",
            [$brandId, $user['id']]
        );
        if (!$member || !in_array($member['role'], $roles, true)) {
            abort(403, 'Insufficient permissions.');
        }
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']) && !empty($_SESSION['logged_in']);
    }

    private static function isApiRequest(): bool
    {
        return str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/') ||
               ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';
    }

    // --------------------------------------------------------
    // Token (JWT-like, HMAC-signed)
    // --------------------------------------------------------
    public static function generateToken(array $payload, int $ttl = 3600): string
    {
        $header  = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload['exp'] = time() + $ttl;
        $payload['iat'] = time();
        $body    = base64url_encode(json_encode($payload));
        $sig     = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
        return "$header.$body.$sig";
    }

    public static function verifyToken(string $token): array|false
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        [$header, $body, $sig] = $parts;
        $expectedSig = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
        if (!hash_equals($expectedSig, $sig)) {
            return false;
        }
        $payload = json_decode(base64url_decode($body), true);
        if (!$payload || ($payload['exp'] ?? 0) < time()) {
            return false;
        }
        return $payload;
    }

    // --------------------------------------------------------
    // Password hashing
    // --------------------------------------------------------
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // --------------------------------------------------------
    // TOTP (Google Authenticator compatible)
    // --------------------------------------------------------
    public static function generateTOTPSecret(): string
    {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    public static function verifyTOTP(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }
        $timestamp = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (self::generateTOTPCode($secret, $timestamp + $i) === $code) {
                return true;
            }
        }
        return false;
    }

    private static function generateTOTPCode(string $secret, int $timestamp): string
    {
        $base32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $binary = '';
        $buffer = 0;
        $bitsLeft = 0;
        foreach (str_split($secret) as $char) {
            $pos = strpos($base32, $char);
            if ($pos === false) continue;
            $buffer   = ($buffer << 5) | $pos;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $binary   .= chr(($buffer >> ($bitsLeft - 8)) & 0xFF);
                $bitsLeft -= 8;
            }
        }
        $msg  = pack('N*', 0) . pack('N*', $timestamp);
        $hash = hash_hmac('sha1', $msg, $binary, true);
        $offset = ord($hash[19]) & 0xf;
        $code   = (
            ((ord($hash[$offset])     & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8)  |
             (ord($hash[$offset + 3]) & 0xff)
        ) % 1_000_000;
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    public static function getTOTPUri(string $secret, string $email, string $issuer = APP_NAME): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            urlencode($issuer), urlencode($email), $secret, urlencode($issuer)
        );
    }

    // --------------------------------------------------------
    // CSRF
    // --------------------------------------------------------
    public static function csrfToken(): string
    {
        return Security::generateCsrfToken();
    }

    public static function validateCsrf(?string $token = null): bool
    {
        $token = $token
            ?? $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$token) {
            return false;
        }
        return Security::validateCsrfToken($token);
    }

    // --------------------------------------------------------
    // Helpers
    // --------------------------------------------------------
    private static function sanitizeUser(array $user): array
    {
        unset($user['password_hash'], $user['two_factor_secret']);
        return $user;
    }

    public static function user(): ?array
    {
        return self::getCurrentUser();
    }

    public static function id(): ?string
    {
        return self::getCurrentUser()['id'] ?? null;
    }
}

// --------------------------------------------------------
// Base64url helpers (RFC 4648 §5)
// --------------------------------------------------------
if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}

// Abort helper
if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): void
    {
        http_response_code($code);
        if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message ?: "HTTP $code"]);
        } else {
            echo "<h1>$code</h1><p>" . htmlspecialchars($message) . "</p>";
        }
        exit;
    }
}
