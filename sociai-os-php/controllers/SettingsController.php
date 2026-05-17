<?php

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

class SettingsController
{
    private Database $db;
    private Request $request;

    private const PLATFORMS = ['instagram', 'twitter', 'linkedin', 'facebook', 'tiktok', 'youtube', 'threads', 'snapchat'];

    public function __construct()
    {
        $this->db      = Database::getInstance();
        $this->request = new Request();
    }

    public function index(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $fullUser           = $this->loadFullUser($user['id']);
        $connectedPlatforms = $this->loadConnectedPlatforms($brandId);
        $brandInfo          = $this->loadBrandInfo($brandId);

        Response::view('settings/index', [
            'title'              => 'Settings – SociAI OS',
            'user'               => $fullUser,
            'connectedPlatforms' => $connectedPlatforms,
            'brandInfo'          => $brandInfo,
            'platforms'          => self::PLATFORMS,
            'brandId'            => $brandId,
            'csrf'               => Auth::csrfToken(),
        ]);
    }

    public function updateProfile(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();

        $name     = trim($this->request->post('full_name', ''));
        $timezone = trim($this->request->post('timezone', 'UTC'));
        $bio      = trim($this->request->post('bio', ''));

        if (strlen($name) < 2) {
            Response::json(['success' => false, 'error' => 'Name must be at least 2 characters'], 422);
            return;
        }

        $fields = ['full_name = ?', 'updated_at = NOW()'];
        $params = [$name];

        if (!empty($timezone)) {
            $fields[] = 'timezone = ?';
            $params[]  = $timezone;
        }

        // Handle avatar upload
        if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $avatarUrl = $this->handleAvatarUpload($_FILES['avatar'], $user['id']);
            if ($avatarUrl) {
                $fields[] = 'avatar_url = ?';
                $params[] = $avatarUrl;
            }
        }

        $params[] = $user['id'];
        $this->db->prepare(
            'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?'
        )->execute($params);

        Response::json(['success' => true, 'message' => 'Profile updated successfully.']);
    }

    public function changePassword(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();

        $currentPassword = $this->request->post('current_password', '');
        $newPassword     = $this->request->post('new_password', '');
        $confirmPassword = $this->request->post('confirm_password', '');

        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            Response::json(['success' => false, 'error' => 'Current password is incorrect'], 422);
            return;
        }

        if (strlen($newPassword) < 8) {
            Response::json(['success' => false, 'error' => 'New password must be at least 8 characters'], 422);
            return;
        }

        if ($newPassword !== $confirmPassword) {
            Response::json(['success' => false, 'error' => 'Passwords do not match'], 422);
            return;
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->prepare(
            'UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$newHash, $user['id']]);

        Response::json(['success' => true, 'message' => 'Password changed successfully.']);
    }

    public function disconnectPlatform(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $platformAccountId = $this->request->post('platform_account_id', '');

        $stmt = $this->db->prepare(
            'SELECT id, platform FROM platform_accounts WHERE id = ? AND brand_id = ? LIMIT 1'
        );
        $stmt->execute([$platformAccountId, $brandId]);
        $account = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$account) {
            Response::json(['success' => false, 'error' => 'Platform account not found'], 404);
            return;
        }

        $this->db->prepare(
            'UPDATE platform_accounts
             SET is_active = 0, access_token_encrypted = NULL, refresh_token_encrypted = NULL, updated_at = NOW()
             WHERE id = ?'
        )->execute([$platformAccountId]);

        Response::json([
            'success' => true,
            'message' => ucfirst($account['platform']) . ' disconnected.',
        ]);
    }

    public function saveApiKeys(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        // Only owners can save API keys
        $stmt = $this->db->prepare(
            'SELECT role FROM team_members WHERE user_id = ? AND brand_id = ? LIMIT 1'
        );
        $stmt->execute([$user['id'], $brandId]);
        $bu = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$bu || $bu['role'] !== 'owner') {
            Response::json(['success' => false, 'error' => 'Only the brand owner can manage API keys'], 403);
            return;
        }

        $keys = [
            'openai_api_key'      => $this->request->post('openai_api_key', ''),
            'claude_api_key'      => $this->request->post('claude_api_key', ''),
            'twitter_client_id'   => $this->request->post('twitter_client_id', ''),
            'twitter_secret'      => $this->request->post('twitter_secret', ''),
            'instagram_client_id' => $this->request->post('instagram_client_id', ''),
            'instagram_secret'    => $this->request->post('instagram_secret', ''),
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

        Response::json(['success' => true, 'message' => 'API keys saved securely.']);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function loadFullUser(string $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, full_name, email, two_factor_enabled, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function loadConnectedPlatforms(string $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, platform, account_name, is_active, follower_count, token_expires_at, last_synced_at
             FROM platform_accounts WHERE brand_id = ? ORDER BY platform ASC'
        );
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function loadBrandInfo(string $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, industry, logo_url, created_at FROM brands WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$brandId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function handleAvatarUpload(array $file, string $userId): ?string
    {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
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

    private function encrypt(string $data, string $key): string
    {
        $iv     = random_bytes(16);
        $cipher = openssl_encrypt($data, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    private function getActiveBrandId(string $userId): string
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (string)$_SESSION['active_brand_id'];
        }
        $stmt = $this->db->prepare(
            'SELECT b.id FROM brands b
             INNER JOIN team_members tm ON tm.brand_id = b.id
             WHERE tm.user_id = ? ORDER BY tm.created_at ASC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['active_brand_id'] = $row['id'];
            return (string)$row['id'];
        }
        return '';
    }
}
