<?php

declare(strict_types=1);

/**
 * REST API: /api/auth
 *
 * Routes:
 *   POST /api/auth/login     → issue API token
 *   POST /api/auth/register  → create account + issue token
 *   POST /api/auth/logout    → revoke current token
 *   POST /api/auth/refresh   → refresh / extend token
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db  = Database::getInstance();
$uri = rtrim(preg_replace('#/+#', '/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');
$body = json_decode(file_get_contents('php://input'), true) ?: [];

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getClientIpForAuth(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function generateApiToken(\PDO $db, int $userId, int $brandId, string $clientName = 'api'): array
{
    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 90)); // 90 days

    $stmt = $db->prepare(
        'INSERT INTO api_tokens (user_id, brand_id, token_hash, client_name, expires_at, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, 1, NOW())'
    );
    $stmt->execute([$userId, $brandId, $tokenHash, $clientName, $expiresAt]);
    $tokenId = (int) $db->lastInsertId();

    return [
        'token_id'   => $tokenId,
        'token'      => $rawToken,
        'expires_at' => $expiresAt,
        'type'       => 'Bearer',
    ];
}

function getBrandForUser(\PDO $db, int $userId): ?array
{
    $stmt = $db->prepare(
        'SELECT b.id, b.name FROM brands b
         INNER JOIN brand_users bu ON bu.brand_id = b.id
         WHERE bu.user_id = ? ORDER BY bu.created_at ASC LIMIT 1'
    );
    $stmt->execute([$userId]);
    return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
}

function getCurrentTokenData(\PDO $db): ?array
{
    $auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        $token = trim($m[1]);
    }
    if (empty($token)) return null;

    $hash = hash('sha256', $token);
    $stmt = $db->prepare(
        'SELECT id, user_id, brand_id, expires_at FROM api_tokens
         WHERE token_hash = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$hash]);
    return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
}

// ─── Routes ──────────────────────────────────────────────────────────────────

try {

    // POST /api/auth/login
    if (preg_match('#/api/auth/login$#', $uri)) {
        $email    = trim($body['email']    ?? '');
        $password = $body['password']      ?? '';
        $clientName = $body['client_name'] ?? 'api';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
            http_response_code(422);
            echo json_encode(['error' => 'Valid email and password are required']);
            exit;
        }

        $stmt = $db->prepare(
            'SELECT id, email, password_hash, name, role, is_active, two_fa_enabled,
                    failed_login_attempts, locked_until
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            if ($user) {
                $attempts = (int) $user['failed_login_attempts'] + 1;
                $lock     = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                $db->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?')
                   ->execute([$attempts, $lock, $user['id']]);
            }
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            exit;
        }

        if (!(bool) $user['is_active']) {
            http_response_code(403);
            echo json_encode(['error' => 'Account deactivated']);
            exit;
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $minutes = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            http_response_code(429);
            echo json_encode(['error' => "Account locked. Try in {$minutes} minutes"]);
            exit;
        }

        $db->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?'
        )->execute([$user['id']]);

        $brand = getBrandForUser($db, (int) $user['id']);
        if (!$brand) {
            http_response_code(404);
            echo json_encode(['error' => 'No brand found for this user']);
            exit;
        }

        $tokenData = generateApiToken($db, (int) $user['id'], (int) $brand['id'], $clientName);

        // Log
        $db->prepare(
            'INSERT INTO audit_logs (user_id, action, resource_type, resource_id, meta, ip_address, created_at)
             VALUES (?, "api_login", "user", ?, ?, ?, NOW())'
        )->execute([$user['id'], $user['id'], json_encode(['client' => $clientName]), getClientIpForAuth()]);

        echo json_encode([
            'user' => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
            'brand'       => $brand,
            'token'       => $tokenData,
            'requires_2fa'=> (bool) $user['two_fa_enabled'],
        ]);
        exit;
    }

    // POST /api/auth/register
    if (preg_match('#/api/auth/register$#', $uri)) {
        $name            = trim($body['name']            ?? '');
        $email           = trim($body['email']           ?? '');
        $password        = $body['password']             ?? '';
        $confirmPassword = $body['confirm_password']     ?? '';
        $brandName       = trim($body['brand_name']      ?? '');
        $industry        = trim($body['industry']        ?? '');

        $errors = [];
        if (strlen($name) < 2)                  $errors[] = 'Name must be at least 2 characters';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email';
        if (strlen($password) < 8)               $errors[] = 'Password must be at least 8 characters';
        if (!preg_match('/[A-Z]/', $password))   $errors[] = 'Password must contain uppercase letter';
        if (!preg_match('/[0-9]/', $password))   $errors[] = 'Password must contain a number';
        if ($password !== $confirmPassword)       $errors[] = 'Passwords do not match';
        if (strlen($brandName) < 2)              $errors[] = 'Brand name required';

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            exit;
        }

        // Duplicate check
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered']);
            exit;
        }

        $db->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]);

            $db->prepare('INSERT INTO users (name, email, password_hash, role, is_active, created_at) VALUES (?, ?, ?, "owner", 1, NOW())')
               ->execute([$name, $email, $hash]);
            $userId = (int) $db->lastInsertId();

            $db->prepare('INSERT INTO brands (name, industry, owner_id, created_at) VALUES (?, ?, ?, NOW())')
               ->execute([$brandName, $industry, $userId]);
            $brandId = (int) $db->lastInsertId();

            $db->prepare('INSERT INTO brand_users (brand_id, user_id, role) VALUES (?, ?, "owner")')
               ->execute([$brandId, $userId]);

            $db->commit();

            $tokenData = generateApiToken($db, $userId, $brandId, 'api');

            http_response_code(201);
            echo json_encode([
                'user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'owner'],
                'brand'=> ['id' => $brandId, 'name' => $brandName],
                'token'=> $tokenData,
            ]);

        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('API register error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Registration failed']);
        }
        exit;
    }

    // POST /api/auth/logout
    if (preg_match('#/api/auth/logout$#', $uri)) {
        $tokenData = getCurrentTokenData($db);

        if (!$tokenData) {
            http_response_code(401);
            echo json_encode(['error' => 'No active token']);
            exit;
        }

        $db->prepare('UPDATE api_tokens SET is_active = 0, revoked_at = NOW() WHERE id = ?')
           ->execute([$tokenData['id']]);

        echo json_encode(['message' => 'Logged out successfully']);
        exit;
    }

    // POST /api/auth/refresh
    if (preg_match('#/api/auth/refresh$#', $uri)) {
        $tokenData = getCurrentTokenData($db);

        if (!$tokenData) {
            http_response_code(401);
            echo json_encode(['error' => 'No active token found']);
            exit;
        }

        if (strtotime($tokenData['expires_at']) < time()) {
            http_response_code(401);
            echo json_encode(['error' => 'Token has expired. Please log in again.']);
            exit;
        }

        // Revoke old token and issue new one
        $db->prepare('UPDATE api_tokens SET is_active = 0, revoked_at = NOW() WHERE id = ?')
           ->execute([$tokenData['id']]);

        $newToken = generateApiToken($db, (int) $tokenData['user_id'], (int) $tokenData['brand_id'], 'api');

        echo json_encode([
            'token'   => $newToken,
            'message' => 'Token refreshed',
        ]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);

} catch (\Throwable $e) {
    error_log('API /auth error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
