<?php

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

class PlatformsController
{
    private Database $db;
    private Request $request;

    private const SUPPORTED_PLATFORMS = [
        'instagram' => ['name' => 'Instagram',   'color' => '#E1306C', 'icon' => 'instagram'],
        'twitter'   => ['name' => 'X (Twitter)', 'color' => '#000000', 'icon' => 'twitter'],
        'linkedin'  => ['name' => 'LinkedIn',    'color' => '#0A66C2', 'icon' => 'linkedin'],
        'facebook'  => ['name' => 'Facebook',    'color' => '#1877F2', 'icon' => 'facebook'],
        'tiktok'    => ['name' => 'TikTok',      'color' => '#010101', 'icon' => 'tiktok'],
        'youtube'   => ['name' => 'YouTube',     'color' => '#FF0000', 'icon' => 'youtube'],
        'threads'   => ['name' => 'Threads',     'color' => '#000000', 'icon' => 'threads'],
        'snapchat'  => ['name' => 'Snapchat',    'color' => '#FFFC00', 'icon' => 'snapchat'],
    ];

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

        $connected = $this->loadConnectedAccounts($brandId);

        $platforms = [];
        foreach (self::SUPPORTED_PLATFORMS as $key => $info) {
            $connectedAccount = null;
            foreach ($connected as $acc) {
                if ($acc['platform'] === $key) {
                    $connectedAccount = $acc;
                    break;
                }
            }
            $platforms[$key] = array_merge($info, [
                'key'          => $key,
                'is_connected' => $connectedAccount !== null,
                'account'      => $connectedAccount,
            ]);
        }

        Response::view('platforms/index', [
            'title'     => 'Platforms – SociAI OS',
            'platforms' => $platforms,
            'brandId'   => $brandId,
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function connect(string $platform): void
    {
        Auth::requireAuth();
        if (!array_key_exists($platform, self::SUPPORTED_PLATFORMS)) {
            Response::json(['success' => false, 'error' => 'Unsupported platform'], 400);
            return;
        }
        Response::redirect('/oauth/connect/' . $platform);
    }

    public function disconnect(): void
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
            'message' => ucfirst($account['platform']) . ' account disconnected.',
        ]);
    }

    public function testConnection(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $platformAccountId = $this->request->post('platform_account_id', '');

        $stmt = $this->db->prepare(
            'SELECT id, platform, access_token_encrypted, account_name
             FROM platform_accounts WHERE id = ? AND brand_id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$platformAccountId, $brandId]);
        $account = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$account) {
            Response::json(['success' => false, 'error' => 'Account not found or inactive'], 404);
            return;
        }

        $token  = $this->decryptToken($account['access_token_encrypted'] ?? '');
        $result = $this->pingPlatformAPI($account['platform'], $token);

        $this->db->prepare(
            'UPDATE platform_accounts SET last_synced_at = NOW() WHERE id = ?'
        )->execute([$account['id']]);

        Response::json([
            'success'  => $result['success'],
            'platform' => $account['platform'],
            'username' => $account['account_name'],
            'message'  => $result['message'] ?? '',
        ]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function loadConnectedAccounts(string $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, platform, account_name, is_active, follower_count,
                    token_expires_at, last_synced_at
             FROM platform_accounts WHERE brand_id = ? ORDER BY platform ASC'
        );
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function pingPlatformAPI(string $platform, string $token): array
    {
        $endpoints = [
            'twitter'   => 'https://api.twitter.com/2/users/me',
            'instagram' => 'https://graph.instagram.com/me?fields=id,username',
            'linkedin'  => 'https://api.linkedin.com/v2/me',
            'facebook'  => 'https://graph.facebook.com/me',
            'tiktok'    => 'https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name',
        ];

        $url = $endpoints[$platform] ?? null;
        if (!$url || empty($token)) {
            return ['success' => false, 'message' => 'No endpoint or token'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode === 200,
            'message' => $httpCode === 200 ? 'Connection healthy' : "HTTP {$httpCode}",
        ];
    }

    private function decryptToken(string $encrypted): string
    {
        if (empty($encrypted)) return '';
        try {
            $key  = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default-key-change-me';
            $data = base64_decode($encrypted);
            $iv   = substr($data, 0, 16);
            $text = substr($data, 16);
            return (string) openssl_decrypt($text, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
        } catch (\Throwable $e) {
            return '';
        }
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
