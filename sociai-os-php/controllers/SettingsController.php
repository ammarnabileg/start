<?php

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

class SettingsController
{
    private Database $db;
    private Auth $auth;
    private Request $request;
    private Response $response;

    private const PLATFORMS = ['instagram','twitter','linkedin','facebook','tiktok','youtube','threads','snapchat'];

    public function __construct()
    {
        $this->db       = Database::getInstance();
        $this->auth     = new Auth();
        $this->request  = new Request();
        $this->response = new Response();
    }

    public function index(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $fullUser          = $this->loadFullUser($user['id']);
        $connectedPlatforms = $this->loadConnectedPlatforms($brandId);
        $brandInfo         = $this->loadBrandInfo($brandId);

        $this->response->view('settings/index', [
            'title'              => 'Settings – SociAI OS',
            'user'               => $fullUser,
            'connectedPlatforms' => $connectedPlatforms,
            'brandInfo'          => $brandInfo,
            'platforms'          => self::PLATFORMS,
            'brandId'            => $brandId,
        ]);
    }

    public function updateProfile(): void
    {
        $this->auth->requireAuth();
        $user = $this->auth->getCurrentUser();

        $name     = trim($this->request->post('name', ''));
        $timezone = trim($this->request->post('timezone', 'UTC'));
        $locale   = trim($this->request->post('locale', 'en'));
        $bio      = trim($this->request->post('bio', ''));

        if (strlen($name) < 2) {
            $this->response->json(['success' => false, 'error' => 'Name must be at least 2 characters'], 422);
            return;
        }

        // Handle avatar upload
        $avatarUrl = null;
        if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $avatarUrl = $this->handleAvatarUpload($_FILES['avatar'], $user['id']);
        }

        $fields = ['name = ?', 'timezone = ?', 'locale = ?', 'bio = ?', 'updated_at = NOW()'];
        $params = [$name, $timezone, $locale, $bio];

        if ($avatarUrl) {
            $fields[] = 'avatar_url = ?';
            $params[] = $avatarUrl;
        }

        $params[] = $user['id'];
        $this->db->prepare(
            'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?'
        )->execute($params);

        $this->response->json(['success' => true, 'message' => 'Profile updated successfully.']);
    }

    public function changePassword(): void
    {
        $this->auth->requireAuth();
        $user = $this->auth->getCurrentUser();

        $currentPassword = $this->request->post('current_password', '');
        $newPassword     = $this->request->post('new_password', '');
        $confirmPassword = $this->request->post('confirm_password', '');

        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            $this->response->json(['success' => false, 'error' => 'Current password is incorrect'], 422);
            return;
        }

        if (strlen($newPassword) < 8) {
            $this->response->json(['success' => false, 'error' => 'New password must be at least 8 characters'], 422);
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->response->json(['success' => false, 'error' => 'Passwords do not match'], 422);
            return;
        }

        $newHash = password_hash($newPassword, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]);

        $this->db->prepare(
            'UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$newHash, $user['id']]);

        $this->response->json(['success' => true, 'message' => 'Password changed successfully.']);
    }

    public function setup2FA(): void
    {
        $this->auth->requireAuth();
        $user = $this->auth->getCurrentUser();

        // Generate TOTP secret
        $secret = $this->generateTOTPSecret();

        // Store pending secret (not activated until verified)
        $this->db->prepare(
            'UPDATE users SET two_fa_pending_secret = ? WHERE id = ?'
        )->execute([$secret, $user['id']]);

        $appName  = defined('APP_NAME') ? APP_NAME : 'SociAI OS';
        $otpauthUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($appName),
            rawurlencode($user['email']),
            $secret,
            rawurlencode($appName)
        );

        $this->response->json([
            'success'    => true,
            'secret'     => $secret,
            'qr_url'     => 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($otpauthUrl) . '&size=200x200',
            'otpauth_url'=> $otpauthUrl,
        ]);
    }

    public function verify2FA(): void
    {
        $this->auth->requireAuth();
        $user = $this->auth->getCurrentUser();

        $code = trim($this->request->post('code', ''));

        $stmt = $this->db->prepare('SELECT two_fa_pending_secret FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || empty($row['two_fa_pending_secret'])) {
            $this->response->json(['success' => false, 'error' => '2FA setup not started'], 400);
            return;
        }

        if (!$this->verifyTOTP($row['two_fa_pending_secret'], $code)) {
            $this->response->json(['success' => false, 'error' => 'Invalid code'], 422);
            return;
        }

        // Activate 2FA
        $recoveryCodes = $this->generateRecoveryCodes();

        $this->db->prepare(
            'UPDATE users SET two_fa_enabled = 1, two_fa_secret = ?, two_fa_pending_secret = NULL,
             two_fa_recovery_codes = ?, updated_at = NOW() WHERE id = ?'
        )->execute([
            $row['two_fa_pending_secret'],
            json_encode(array_map(fn($c) => password_hash($c, PASSWORD_BCRYPT), $recoveryCodes)),
            $user['id'],
        ]);

        $this->response->json([
            'success'        => true,
            'message'        => 'Two-factor authentication enabled.',
            'recovery_codes' => $recoveryCodes, // Show once to user
        ]);
    }

    public function connectPlatform(): void
    {
        $this->auth->requireAuth();
        $platform = $this->request->post('platform', '');

        if (!in_array($platform, self::PLATFORMS, true)) {
            $this->response->json(['success' => false, 'error' => 'Unsupported platform'], 400);
            return;
        }

        // Redirect to OAuth (via AuthController::oauthConnect)
        $this->response->json([
            'success'      => true,
            'redirect_url' => '/oauth/connect/' . $platform,
        ]);
    }

    public function disconnectPlatform(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $platformAccountId = (int) $this->request->post('platform_account_id', 0);

        $stmt = $this->db->prepare(
            'SELECT id, platform FROM platform_accounts WHERE id = ? AND brand_id = ? LIMIT 1'
        );
        $stmt->execute([$platformAccountId, $brandId]);
        $account = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$account) {
            $this->response->json(['success' => false, 'error' => 'Platform account not found'], 404);
            return;
        }

        $this->db->prepare(
            'UPDATE platform_accounts SET is_active = 0, access_token = NULL, refresh_token = NULL, updated_at = NOW()
             WHERE id = ?'
        )->execute([$platformAccountId]);

        $this->response->json([
            'success'  => true,
            'message'  => ucfirst($account['platform']) . ' disconnected.',
        ]);
    }

    public function saveApiKeys(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        // Only owners can save API keys
        $stmt = $this->db->prepare('SELECT role FROM brand_users WHERE user_id = ? AND brand_id = ? LIMIT 1');
        $stmt->execute([$user['id'], $brandId]);
        $bu = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$bu || $bu['role'] !== 'owner') {
            $this->response->json(['success' => false, 'error' => 'Only the brand owner can manage API keys'], 403);
            return;
        }

        $keys = [
            'openai_api_key'     => $this->request->post('openai_api_key', ''),
            'claude_api_key'     => $this->request->post('claude_api_key', ''),
            'stability_api_key'  => $this->request->post('stability_api_key', ''),
            'twitter_client_id'  => $this->request->post('twitter_client_id', ''),
            'twitter_secret'     => $this->request->post('twitter_secret', ''),
            'instagram_client_id'=> $this->request->post('instagram_client_id', ''),
            'instagram_secret'   => $this->request->post('instagram_secret', ''),
        ];

        $encKey = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default-key';

        foreach ($keys as $keyName => $keyValue) {
            if (empty(trim($keyValue))) continue;

            $encrypted = $this->encrypt($keyValue, $encKey);

            $this->db->prepare(
                'INSERT INTO api_keys (brand_id, key_name, key_value, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE key_value = VALUES(key_value), updated_at = NOW()'
            )->execute([$brandId, $keyName, $encrypted]);
        }

        $this->response->json(['success' => true, 'message' => 'API keys saved securely.']);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function loadFullUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, role, timezone, locale, bio, avatar_url, two_fa_enabled, last_login_at, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function loadConnectedPlatforms(int $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, platform, username, is_active, follower_count, token_expires_at, last_sync_at
             FROM platform_accounts WHERE brand_id = ? ORDER BY platform ASC'
        );
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function loadBrandInfo(int $brandId): array
    {
        $stmt = $this->db->prepare('SELECT id, name, industry, logo_url, website, created_at FROM brands WHERE id = ? LIMIT 1');
        $stmt->execute([$brandId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function handleAvatarUpload(array $file, int $userId): ?string
    {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true) || $file['size'] > 5 * 1024 * 1024) return null;

        $dir = __DIR__ . '/../storage/uploads/avatars/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            return '/storage/uploads/avatars/' . $filename;
        }
        return null;
    }

    private function generateTOTPSecret(): string
    {
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }

    private function verifyTOTP(string $secret, string $code): bool
    {
        $secretDecoded = $this->base32Decode($secret);
        $timeStep      = (int) floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            $t    = pack('N*', 0) . pack('N*', $timeStep + $i);
            $hash = hash_hmac('sha1', $t, $secretDecoded, true);
            $offset = ord($hash[19]) & 0x0F;
            $otp = (
                ((ord($hash[$offset])   & 0x7F) << 24) |
                ((ord($hash[$offset+1]) & 0xFF) << 16) |
                ((ord($hash[$offset+2]) & 0xFF) << 8)  |
                (ord($hash[$offset+3])  & 0xFF)
            ) % 1000000;

            if (str_pad((string) $otp, 6, '0', STR_PAD_LEFT) === $code) {
                return true;
            }
        }
        return false;
    }

    private function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input    = strtoupper($input);
        $output   = '';
        $buffer   = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < strlen($input); $i++) {
            $val = strpos($alphabet, $input[$i]);
            if ($val === false) continue;
            $buffer   = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output   .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }

    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5))) . '-' . strtoupper(bin2hex(random_bytes(5)));
        }
        return $codes;
    }

    private function encrypt(string $data, string $key): string
    {
        $iv     = random_bytes(16);
        $cipher = openssl_encrypt($data, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    private function getActiveBrandId(int $userId): int
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (int) $_SESSION['active_brand_id'];
        }
        $stmt = $this->db->prepare(
            'SELECT b.id FROM brands b INNER JOIN brand_users bu ON bu.brand_id = b.id WHERE bu.user_id = ? ORDER BY bu.created_at ASC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : 0;
    }
}
