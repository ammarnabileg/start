<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class PlatformsController
{
    private \PDO $db;
    private Auth $auth;
    private Request $request;
    private Response $response;

    private const SUPPORTED_PLATFORMS = [
        'instagram' => ['name' => 'Instagram', 'color' => '#E1306C', 'icon' => 'instagram'],
        'twitter'   => ['name' => 'X (Twitter)', 'color' => '#000000', 'icon' => 'twitter'],
        'linkedin'  => ['name' => 'LinkedIn', 'color' => '#0A66C2', 'icon' => 'linkedin'],
        'facebook'  => ['name' => 'Facebook', 'color' => '#1877F2', 'icon' => 'facebook'],
        'tiktok'    => ['name' => 'TikTok', 'color' => '#010101', 'icon' => 'tiktok'],
        'youtube'   => ['name' => 'YouTube', 'color' => '#FF0000', 'icon' => 'youtube'],
        'threads'   => ['name' => 'Threads', 'color' => '#000000', 'icon' => 'threads'],
        'snapchat'  => ['name' => 'Snapchat', 'color' => '#FFFC00', 'icon' => 'snapchat'],
    ];

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

        $connected = $this->loadConnectedAccounts($brandId);

        // Merge platform config with connection status
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
                'key'             => $key,
                'is_connected'    => $connectedAccount !== null,
                'account'         => $connectedAccount,
            ]);
        }

        $this->response->view('platforms/index', [
            'title'     => 'Platforms – SociAI OS',
            'platforms' => $platforms,
            'brandId'   => $brandId,
        ]);
    }

    public function connect(string $platform): void
    {
        $this->auth->requireAuth();

        if (!array_key_exists($platform, self::SUPPORTED_PLATFORMS)) {
            $this->response->json(['success' => false, 'error' => 'Unsupported platform'], 400);
            return;
        }

        $this->response->redirect('/oauth/connect/' . $platform);
    }

    public function callback(string $platform): void
    {
        // Delegated to AuthController::oauthCallback
        $this->auth->requireAuth();

        require_once __DIR__ . '/AuthController.php';
        $controller = new AuthController();
        $controller->oauthCallback($platform);
    }

    public function disconnect(): void
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

        // Revoke token from platform (best-effort)
        $this->revokeOAuthToken($account);

        $this->db->prepare(
            'UPDATE platform_accounts
             SET is_active = 0, access_token = NULL, refresh_token = NULL, disconnected_at = NOW(), updated_at = NOW()
             WHERE id = ?'
        )->execute([$platformAccountId]);

        $this->response->json([
            'success' => true,
            'message' => ucfirst($account['platform']) . ' account disconnected.',
        ]);
    }

    public function testConnection(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $platformAccountId = (int) $this->request->post('platform_account_id', 0);

        $stmt = $this->db->prepare(
            'SELECT id, platform, access_token, username FROM platform_accounts
             WHERE id = ? AND brand_id = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$platformAccountId, $brandId]);
        $account = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$account) {
            $this->response->json(['success' => false, 'error' => 'Account not found or inactive'], 404);
            return;
        }

        $token  = $this->decryptToken($account['access_token'] ?? '');
        $result = $this->pingPlatformAPI($account['platform'], $token);

        $this->db->prepare(
            'UPDATE platform_accounts SET last_sync_at = NOW(), sync_errors = ? WHERE id = ?'
        )->execute([$result['success'] ? 0 : ($account['sync_errors'] + 1), $account['id']]);

        $this->response->json([
            'success'  => $result['success'],
            'platform' => $account['platform'],
            'username' => $account['username'],
            'message'  => $result['message'] ?? '',
        ]);
    }

    public function getMetrics(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $platformAccountId = (int) $this->request->get('platform_account_id', 0);
        $period = $this->request->get('period', '30d');
        $days   = $this->periodToDays($period);

        if ($platformAccountId > 0) {
            $stmt = $this->db->prepare(
                'SELECT * FROM platform_accounts WHERE id = ? AND brand_id = ? LIMIT 1'
            );
            $stmt->execute([$platformAccountId, $brandId]);
            $account = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$account) {
                $this->response->json(['success' => false, 'error' => 'Account not found'], 404);
                return;
            }
        }

        $stmt = $this->db->prepare(
            'SELECT cp.platform,
                    COUNT(cp.id) AS posts,
                    COALESCE(SUM(pm.impressions),0) AS reach,
                    COALESCE(SUM(pm.likes),0) AS likes,
                    COALESCE(SUM(pm.comments),0) AS comments,
                    COALESCE(SUM(pm.shares),0) AS shares,
                    COALESCE(AVG(pm.engagement_rate),0) AS avg_engagement
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id = cp.id
             WHERE cp.brand_id = ?
               AND cp.status = "published"
               AND cp.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY cp.platform'
        );
        $stmt->execute([$brandId, $days]);
        $metrics = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Follower growth
        $growthStmt = $this->db->prepare(
            'SELECT platform, SUM(follower_count) AS followers, DATE(snapshot_date) AS day
             FROM follower_snapshots
             WHERE brand_id = ? AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY platform, DATE(snapshot_date)
             ORDER BY day ASC'
        );
        $growthStmt->execute([$brandId, $days]);
        $growth = $growthStmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->response->json([
            'success' => true,
            'metrics' => $metrics,
            'growth'  => $growth,
            'period'  => $period,
        ]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function loadConnectedAccounts(int $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, platform, username, is_active, follower_count, following_count,
                    token_expires_at, last_sync_at, sync_errors
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

    private function revokeOAuthToken(array $account): void
    {
        // Best-effort token revocation — ignore errors
        try {
            $token = $this->decryptToken($account['access_token'] ?? '');
            if (empty($token)) return;

            $revokeUrls = [
                'twitter' => 'https://api.twitter.com/2/oauth2/revoke',
            ];

            if (isset($revokeUrls[$account['platform']])) {
                $ch = curl_init($revokeUrls[$account['platform']]);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POSTFIELDS     => 'token=' . urlencode($token),
                    CURLOPT_TIMEOUT        => 5,
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
        } catch (\Throwable $e) {
            // Ignore
        }
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

    private function periodToDays(string $period): int
    {
        return match ($period) {
            '7d'  => 7, '14d' => 14, '30d' => 30, '90d' => 90, default => 30,
        };
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
