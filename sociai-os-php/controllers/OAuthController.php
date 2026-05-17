<?php
/**
 * SociAI OS - OAuth Controller
 * Full OAuth 2.0 flow for all supported social platforms.
 */

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response, Security};

class OAuthController
{
    private Request  $req;
    private Database $db;

    private const SUPPORTED_PLATFORMS = [
        'meta', 'facebook', 'instagram', 'twitter', 'linkedin', 'tiktok', 'youtube',
    ];

    public function __construct()
    {
        $this->req = new Request();
        $this->db  = Database::getInstance();
    }

    // --------------------------------------------------------
    // Connect — initiate OAuth redirect
    // --------------------------------------------------------

    public function connect(array $p): void
    {
        Auth::requireAuth();
        $platform = strtolower($p['platform'] ?? '');

        if (!in_array($platform, self::SUPPORTED_PLATFORMS, true)) {
            Response::flash('error', "Platform '{$platform}' is not supported.");
            Response::redirect('/dashboard/settings');
            return;
        }

        // Generate CSRF state for OAuth
        $state = Security::generateToken(16);
        $_SESSION['oauth_state']    = $state;
        $_SESSION['oauth_platform'] = $platform;

        $authUrl = match ($platform) {
            'meta', 'facebook', 'instagram' => $this->metaAuthUrl($state),
            'twitter'   => $this->twitterAuthUrl($state),
            'linkedin'  => $this->linkedInAuthUrl($state),
            'tiktok'    => $this->tikTokAuthUrl($state),
            'youtube'   => $this->youtubeAuthUrl($state),
            default     => null,
        };

        if (!$authUrl) {
            Response::flash('error', "OAuth for '{$platform}' is not configured. Check your .env credentials.");
            Response::redirect('/dashboard/settings');
            return;
        }

        Response::redirect($authUrl);
    }

    // --------------------------------------------------------
    // Callback — exchange code for token
    // --------------------------------------------------------

    public function callback(array $p): void
    {
        $platform = strtolower($p['platform'] ?? '');
        $code     = $this->req->get('code', '');
        $state    = $this->req->get('state', '');
        $error    = $this->req->get('error', '');

        if ($error) {
            Response::flash('error', "OAuth denied: " . ($this->req->get('error_description', $error)));
            Response::redirect('/dashboard/settings');
            return;
        }

        if (empty($code)) {
            Response::flash('error', "OAuth failed: no code received.");
            Response::redirect('/dashboard/settings');
            return;
        }

        // Validate state to prevent CSRF
        $storedState = $_SESSION['oauth_state'] ?? '';
        if (empty($storedState) || !hash_equals($storedState, $state)) {
            Response::flash('error', "OAuth state mismatch — possible CSRF attempt.");
            Response::redirect('/dashboard/settings');
            return;
        }

        // Require authenticated user
        if (!Auth::isLoggedIn()) {
            Response::flash('error', "You must be logged in to connect a platform.");
            Response::redirect('/auth/login');
            return;
        }

        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        if (empty($brandId)) {
            Response::flash('error', "No active brand found. Please create a brand first.");
            Response::redirect('/brands/create');
            return;
        }

        unset($_SESSION['oauth_state'], $_SESSION['oauth_platform']);

        try {
            $tokenData = match ($platform) {
                'meta', 'facebook', 'instagram' => $this->metaExchangeCode($code),
                'twitter'   => $this->twitterExchangeCode($code),
                'linkedin'  => $this->linkedInExchangeCode($code),
                'tiktok'    => $this->tikTokExchangeCode($code),
                'youtube'   => $this->youtubeExchangeCode($code),
                default     => null,
            };

            if (!$tokenData || empty($tokenData['access_token'])) {
                Response::flash('error', "Failed to obtain access token from {$platform}.");
                Response::redirect('/dashboard/settings');
                return;
            }

            $this->storePlatformAccount($brandId, $platform, $tokenData);

            Response::flash('success', ucfirst($platform) . " account connected successfully!");
        } catch (\Throwable $e) {
            error_log("[OAuthController] callback error ({$platform}): " . $e->getMessage());
            Response::flash('error', "OAuth error: " . $e->getMessage());
        }

        Response::redirect('/dashboard/settings');
    }

    // --------------------------------------------------------
    // Disconnect
    // --------------------------------------------------------

    public function disconnect(array $p): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $accountId = $this->req->post('account_id', '');
        if (empty($accountId)) {
            Response::json(['success' => false, 'error' => 'account_id required'], 400);
            return;
        }

        $account = $this->db->fetchOne(
            "SELECT id, platform FROM platform_accounts WHERE id = ? AND brand_id = ? LIMIT 1",
            [$accountId, $brandId]
        );

        if (!$account) {
            Response::json(['success' => false, 'error' => 'Account not found'], 404);
            return;
        }

        $this->db->update(
            'platform_accounts',
            [
                'is_active'                => 0,
                'access_token_encrypted'   => null,
                'refresh_token_encrypted'  => null,
                'updated_at'               => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [$accountId]
        );

        $platform = ucfirst($account['platform']);
        Response::json(['success' => true, 'message' => "{$platform} account disconnected."]);
    }

    // --------------------------------------------------------
    // Meta / Facebook / Instagram OAuth
    // --------------------------------------------------------

    private function metaAuthUrl(string $state): string
    {
        if (empty(META_APP_ID)) {
            return '';
        }
        $scope    = 'pages_show_list,pages_read_engagement,pages_manage_posts,instagram_basic,instagram_manage_comments,instagram_manage_insights,pages_messaging';
        return 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
            'client_id'    => META_APP_ID,
            'redirect_uri' => META_REDIRECT_URI,
            'scope'        => $scope,
            'state'        => $state,
            'response_type'=> 'code',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function metaExchangeCode(string $code): array
    {
        $response = $this->httpPost('https://graph.facebook.com/v19.0/oauth/access_token', [
            'client_id'     => META_APP_ID,
            'client_secret' => META_APP_SECRET,
            'redirect_uri'  => META_REDIRECT_URI,
            'code'          => $code,
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException("Meta token exchange failed: " . json_encode($response));
        }

        // Get long-lived token
        $longLived = $this->httpPost('https://graph.facebook.com/v19.0/oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => META_APP_ID,
            'client_secret'     => META_APP_SECRET,
            'fb_exchange_token' => $response['access_token'],
        ]);

        $accessToken = $longLived['access_token'] ?? $response['access_token'];
        $expiresIn   = $longLived['expires_in']   ?? ($response['expires_in'] ?? 5184000); // 60 days default

        // Get user/page info
        $profile = $this->httpGet('https://graph.facebook.com/v19.0/me?fields=id,name,picture', [], [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);

        // Get Facebook pages
        $pages = $this->httpGet('https://graph.facebook.com/v19.0/me/accounts', [], [
            'Authorization' => 'Bearer ' . $accessToken,
        ]);

        return [
            'access_token'   => $accessToken,
            'expires_in'     => (int)$expiresIn,
            'account_id'     => $profile['id']   ?? '',
            'account_name'   => $profile['name']  ?? '',
            'avatar_url'     => $profile['picture']['data']['url'] ?? '',
            'pages'          => $pages['data'] ?? [],
            'platform_type'  => 'facebook',
        ];
    }

    // --------------------------------------------------------
    // Twitter OAuth 2.0 PKCE
    // --------------------------------------------------------

    private function twitterAuthUrl(string $state): string
    {
        if (empty(TWITTER_CLIENT_ID)) {
            return '';
        }
        $verifier = Security::generateToken(32);
        $_SESSION['oauth_verifier'] = $verifier;

        return 'https://twitter.com/i/oauth2/authorize?' . http_build_query([
            'response_type'         => 'code',
            'client_id'             => TWITTER_CLIENT_ID,
            'redirect_uri'          => TWITTER_REDIRECT_URI,
            'scope'                 => 'tweet.read tweet.write users.read dm.read dm.write offline.access',
            'state'                 => $state,
            'code_challenge'        => $verifier,
            'code_challenge_method' => 'plain',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function twitterExchangeCode(string $code): array
    {
        $verifier = $_SESSION['oauth_verifier'] ?? '';
        unset($_SESSION['oauth_verifier']);

        $response = $this->httpPost('https://api.twitter.com/2/oauth2/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => TWITTER_REDIRECT_URI,
            'client_id'     => TWITTER_CLIENT_ID,
            'code_verifier' => $verifier,
        ], [
            'Authorization' => 'Basic ' . base64_encode(TWITTER_CLIENT_ID . ':' . TWITTER_CLIENT_SECRET),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException("Twitter token exchange failed: " . json_encode($response));
        }

        // Get user info
        $profile = $this->httpGet('https://api.twitter.com/2/users/me?user.fields=name,username,profile_image_url,public_metrics', [], [
            'Authorization' => 'Bearer ' . $response['access_token'],
        ]);

        $userData = $profile['data'] ?? [];

        return [
            'access_token'   => $response['access_token'],
            'refresh_token'  => $response['refresh_token'] ?? '',
            'expires_in'     => $response['expires_in']    ?? 7200,
            'account_id'     => $userData['id']            ?? '',
            'account_name'   => $userData['name']          ?? ($userData['username'] ?? ''),
            'avatar_url'     => $userData['profile_image_url'] ?? '',
            'follower_count' => $userData['public_metrics']['followers_count'] ?? 0,
        ];
    }

    // --------------------------------------------------------
    // LinkedIn OAuth 2.0
    // --------------------------------------------------------

    private function linkedInAuthUrl(string $state): string
    {
        if (empty(LINKEDIN_CLIENT_ID)) {
            return '';
        }
        return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => LINKEDIN_CLIENT_ID,
            'redirect_uri'  => LINKEDIN_REDIRECT_URI,
            'state'         => $state,
            'scope'         => 'r_liteprofile r_emailaddress w_member_social r_organization_social rw_organization_admin w_organization_social',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function linkedInExchangeCode(string $code): array
    {
        $response = $this->httpPost('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => LINKEDIN_CLIENT_ID,
            'client_secret' => LINKEDIN_CLIENT_SECRET,
            'redirect_uri'  => LINKEDIN_REDIRECT_URI,
        ], ['Content-Type' => 'application/x-www-form-urlencoded']);

        if (empty($response['access_token'])) {
            throw new \RuntimeException("LinkedIn token exchange failed: " . json_encode($response));
        }

        // Get profile
        $profile = $this->httpGet('https://api.linkedin.com/v2/me?projection=(id,firstName,lastName,profilePicture(displayImage~:playableStreams))', [], [
            'Authorization' => 'Bearer ' . $response['access_token'],
        ]);

        $firstName = $profile['firstName']['localized']['en_US'] ?? '';
        $lastName  = $profile['lastName']['localized']['en_US']  ?? '';

        return [
            'access_token'  => $response['access_token'],
            'expires_in'    => $response['expires_in'] ?? 5183944,
            'account_id'    => $profile['id']          ?? '',
            'account_name'  => trim("{$firstName} {$lastName}"),
            'avatar_url'    => '',
        ];
    }

    // --------------------------------------------------------
    // TikTok OAuth 2.0
    // --------------------------------------------------------

    private function tikTokAuthUrl(string $state): string
    {
        if (empty(TIKTOK_CLIENT_KEY)) {
            return '';
        }
        return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
            'client_key'    => TIKTOK_CLIENT_KEY,
            'response_type' => 'code',
            'scope'         => 'user.info.basic,video.list,video.publish,comment.list,comment.write',
            'redirect_uri'  => TIKTOK_REDIRECT_URI,
            'state'         => $state,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tikTokExchangeCode(string $code): array
    {
        $response = $this->httpPost('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key'    => TIKTOK_CLIENT_KEY,
            'client_secret' => TIKTOK_CLIENT_SECRET,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => TIKTOK_REDIRECT_URI,
        ], ['Content-Type' => 'application/x-www-form-urlencoded']);

        $data = $response['data'] ?? $response;
        if (empty($data['access_token'])) {
            throw new \RuntimeException("TikTok token exchange failed: " . json_encode($response));
        }

        // Get user info
        $userInfo = $this->httpPost('https://open.tiktokapis.com/v2/user/info/?fields=open_id,union_id,avatar_url,display_name,follower_count', [], [
            'Authorization' => 'Bearer ' . $data['access_token'],
        ]);

        $userData = $userInfo['data']['user'] ?? [];

        return [
            'access_token'   => $data['access_token'],
            'refresh_token'  => $data['refresh_token']  ?? '',
            'expires_in'     => $data['expires_in']     ?? 86400,
            'account_id'     => $userData['open_id']     ?? '',
            'account_name'   => $userData['display_name'] ?? 'TikTok User',
            'avatar_url'     => $userData['avatar_url']   ?? '',
            'follower_count' => $userData['follower_count'] ?? 0,
        ];
    }

    // --------------------------------------------------------
    // YouTube / Google OAuth 2.0
    // --------------------------------------------------------

    private function youtubeAuthUrl(string $state): string
    {
        if (empty(GOOGLE_CLIENT_ID)) {
            return '';
        }
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => YOUTUBE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/youtube https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.force-ssl',
            'state'         => $state,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function youtubeExchangeCode(string $code): array
    {
        $response = $this->httpPost('https://oauth2.googleapis.com/token', [
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => YOUTUBE_REDIRECT_URI,
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException("YouTube/Google token exchange failed: " . json_encode($response));
        }

        // Get channel info
        $channelInfo = $this->httpGet(
            'https://www.googleapis.com/youtube/v3/channels?part=snippet,statistics&mine=true',
            [],
            ['Authorization' => 'Bearer ' . $response['access_token']]
        );

        $channel = $channelInfo['items'][0] ?? [];
        $snippet = $channel['snippet']    ?? [];
        $stats   = $channel['statistics'] ?? [];

        return [
            'access_token'   => $response['access_token'],
            'refresh_token'  => $response['refresh_token'] ?? '',
            'expires_in'     => $response['expires_in']    ?? 3600,
            'account_id'     => $channel['id']             ?? '',
            'account_name'   => $snippet['title']          ?? 'YouTube Channel',
            'avatar_url'     => $snippet['thumbnails']['default']['url'] ?? '',
            'follower_count' => (int)($stats['subscriberCount'] ?? 0),
        ];
    }

    // --------------------------------------------------------
    // Store platform account in DB
    // --------------------------------------------------------

    /**
     * @param array<string, mixed> $tokenData
     */
    private function storePlatformAccount(string $brandId, string $platform, array $tokenData): void
    {
        $accessToken  = $tokenData['access_token']  ?? '';
        $refreshToken = $tokenData['refresh_token']  ?? '';
        $expiresIn    = (int)($tokenData['expires_in'] ?? 3600);
        $accountId    = $tokenData['account_id']     ?? '';
        $accountName  = $tokenData['account_name']   ?? '';
        $avatarUrl    = $tokenData['avatar_url']      ?? '';
        $followerCount = (int)($tokenData['follower_count'] ?? 0);

        $tokenExpires = date('Y-m-d H:i:s', time() + $expiresIn);

        // Encrypt tokens
        $encryptedAccess  = !empty($accessToken)  ? Security::encrypt($accessToken)  : null;
        $encryptedRefresh = !empty($refreshToken) ? Security::encrypt($refreshToken) : null;

        // Normalise platform name for DB ENUM
        $dbPlatform = match ($platform) {
            'meta'  => 'facebook',
            default => $platform,
        };

        // Extra data (e.g. IG user ID, pages, etc.)
        $extra = [];
        if (!empty($tokenData['pages'])) {
            $extra['pages'] = $tokenData['pages'];
        }

        // Check if account already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM platform_accounts WHERE brand_id = ? AND platform = ? AND account_id = ? LIMIT 1",
            [$brandId, $dbPlatform, $accountId]
        );

        if ($existing) {
            $this->db->update('platform_accounts', [
                'access_token_encrypted'  => $encryptedAccess,
                'refresh_token_encrypted' => $encryptedRefresh,
                'token_expires_at'        => $tokenExpires,
                'account_name'            => $accountName,
                'avatar_url'              => $avatarUrl,
                'follower_count'          => $followerCount,
                'extra_data'              => json_encode($extra),
                'is_active'               => 1,
                'updated_at'              => date('Y-m-d H:i:s'),
            ], 'id = ?', [$existing['id']]);
        } else {
            if (empty($accountId)) {
                $accountId = Security::generateToken(8);
            }
            $this->db->insert('platform_accounts', [
                'id'                        => Security::generateUUID(),
                'brand_id'                  => $brandId,
                'platform'                  => $dbPlatform,
                'account_id'                => $accountId,
                'account_name'              => $accountName,
                'access_token_encrypted'    => $encryptedAccess,
                'refresh_token_encrypted'   => $encryptedRefresh,
                'token_expires_at'          => $tokenExpires,
                'avatar_url'                => $avatarUrl,
                'follower_count'            => $followerCount,
                'extra_data'                => json_encode($extra),
                'is_active'                 => 1,
                'created_at'                => date('Y-m-d H:i:s'),
                'updated_at'                => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // --------------------------------------------------------
    // HTTP helpers
    // --------------------------------------------------------

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function httpPost(string $url, array $data, array $headers = []): array
    {
        $contentType = $headers['Content-Type'] ?? 'application/json';
        $body = str_contains($contentType, 'x-www-form-urlencoded')
            ? http_build_query($data)
            : json_encode($data);

        $defaultHeaders = [
            'Content-Type' => $contentType,
            'Accept'       => 'application/json',
        ];
        $mergedHeaders = array_merge($defaultHeaders, $headers);
        $lines = array_map(fn($k, $v) => "{$k}: {$v}", array_keys($mergedHeaders), array_values($mergedHeaders));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $lines,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException("OAuth HTTP POST error: {$err}");
        }
        return json_decode((string)$raw, true) ?? [];
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function httpGet(string $url, array $params = [], array $headers = []): array
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $defaultHeaders = ['Accept' => 'application/json'];
        $mergedHeaders  = array_merge($defaultHeaders, $headers);
        $lines = array_map(fn($k, $v) => "{$k}: {$v}", array_keys($mergedHeaders), array_values($mergedHeaders));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPGET        => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $lines,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException("OAuth HTTP GET error: {$err}");
        }
        return json_decode((string)$raw, true) ?? [];
    }

    private function getActiveBrandId(string $userId): string
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (string)$_SESSION['active_brand_id'];
        }
        $row = $this->db->fetchOne(
            "SELECT b.id FROM brands b
             INNER JOIN team_members tm ON tm.brand_id = b.id
             WHERE tm.user_id = ? ORDER BY tm.created_at ASC LIMIT 1",
            [$userId]
        );
        if ($row) {
            $_SESSION['active_brand_id'] = $row['id'];
            return (string)$row['id'];
        }
        return '';
    }
}
