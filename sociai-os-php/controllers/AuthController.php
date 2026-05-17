<?php

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

class AuthController
{
    private Database $db;
    private Request $request;

    public function __construct()
    {
        $this->db      = Database::getInstance();
        $this->request = new Request();
    }

    public function showLogin(): void
    {
        if (Auth::getCurrentUser()) {
            Response::redirect('/dashboard');
            return;
        }
        $data = [
            'title'   => 'Login - SociAI OS',
            'csrf'    => Auth::csrfToken(),
        ];
        Response::view('auth/login', $data);
    }

    public function showRegister(): void
    {
        if (Auth::getCurrentUser()) {
            Response::redirect('/dashboard');
            return;
        }
        Response::view('auth/register', [
            'title' => 'Create Account - SociAI OS',
            'csrf'  => Auth::csrfToken(),
        ]);
    }

    public function login(): void
    {
        $email    = trim($this->request->post('email', ''));
        $password = $this->request->post('password', '');
        $remember = (bool)$this->request->post('remember', false);

        if (empty($email) || empty($password)) {
            Response::flash('error', 'Email and password are required.');
            Response::redirect('/auth/login');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::flash('error', 'Invalid email address.');
            Response::redirect('/auth/login');
        }

        $stmt = $this->db->prepare(
            'SELECT id,email,full_name,password_hash,is_active,two_factor_enabled,two_factor_secret
             FROM users WHERE email=? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::flash('error', 'Invalid email or password.');
            Response::redirect('/auth/login');
        }

        if (!(bool)$user['is_active']) {
            Response::flash('error', 'Your account has been deactivated.');
            Response::redirect('/auth/login');
        }

        if ((bool)$user['two_factor_enabled']) {
            $_SESSION['pending_2fa_user_id'] = $user['id'];
            Response::redirect('/auth/2fa');
        }

        $this->establishSession($user);
        Response::redirect('/dashboard');
    }

    public function register(): void
    {
        $fullName = trim($this->request->post('full_name', ''));
        $username = trim($this->request->post('username', ''));
        $email    = trim($this->request->post('email', ''));
        $pass     = $this->request->post('password', '');

        $errors = [];
        if (strlen($fullName) < 2)                        $errors[] = 'Full name must be at least 2 characters.';
        if (!preg_match('/^[a-zA-Z0-9_.\-]{3,64}$/', $username)) $errors[] = 'Username must be 3-64 alphanumeric characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))   $errors[] = 'Invalid email address.';
        if (strlen($pass) < 8)                            $errors[] = 'Password must be at least 8 characters.';
        if (!preg_match('/[A-Z]/', $pass))                $errors[] = 'Password must have an uppercase letter.';
        if (!preg_match('/[0-9]/', $pass))                $errors[] = 'Password must have a number.';

        if (!empty($errors)) {
            Response::flash('error', implode(' ', $errors));
            Response::redirect('/auth/register');
        }

        $dup = $this->db->prepare('SELECT id FROM users WHERE email=? OR username=? LIMIT 1');
        $dup->execute([$email, $username]);
        if ($dup->fetch()) {
            Response::flash('error', 'Email or username already in use.');
            Response::redirect('/auth/register');
        }

        $this->db->beginTransaction();
        try {
            $algo = defined('PASSWORD_ARGON2ID') && defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13')
                  ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
            $hash   = password_hash($pass, $algo);
            $userId = $this->generateUuid();
            $this->db->prepare(
                'INSERT INTO users (id,full_name,username,email,password_hash,is_active,created_at) VALUES (?,?,?,?,?,1,NOW())'
            )->execute([$userId, $fullName, $username, $email, $hash]);

            $brandId = $this->generateUuid();
            $slug    = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $username)) . '-' . rand(100, 999);
            $this->db->prepare(
                'INSERT INTO brands (id,name,slug,owner_id,created_at) VALUES (?,?,?,?,NOW())'
            )->execute([$brandId, $fullName . "'s Brand", $slug, $userId]);

            $this->db->prepare(
                'INSERT INTO team_members (id,brand_id,user_id,role,created_at) VALUES (UUID(),?,?,?,NOW())'
            )->execute([$brandId, $userId, 'owner']);

            $this->db->commit();
            Response::flash('success', 'Account created! Please sign in.');
            Response::redirect('/auth/login');
        } catch (\Throwable $e) {
            $this->db->rollback();
            error_log('Registration failed: ' . $e->getMessage());
            Response::flash('error', 'Registration failed: ' . $e->getMessage());
            Response::redirect('/auth/register');
        }
    }

    private function generateUuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public function logout(): void
    {
        session_unset();
        session_destroy();
        Response::redirect('/auth/login');
    }

    public function show2FA(): void
    {
        if (empty($_SESSION['pending_2fa_user_id'])) {
            Response::redirect('/auth/login');
            return;
        }
        Response::view('auth/2fa', [
            'title' => 'Two-Factor Auth - SociAI OS',
            'csrf'  => Auth::csrfToken(),
        ]);
    }

    public function verify2FA(): void
    {
        if (empty($_SESSION['pending_2fa_user_id'])) {
            Response::redirect('/auth/login');
            return;
        }

        $userId = $_SESSION['pending_2fa_user_id'];
        $code   = trim($this->request->post('code', ''));

        if (!preg_match('/^\d{6}$/', $code)) {
            Response::flash('error', 'Please enter a valid 6-digit code.');
            Response::redirect('/auth/2fa');
            return;
        }

        $stmt = $this->db->prepare('SELECT id,email,full_name,two_factor_secret FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !$this->verifyTOTP((string)$user['two_factor_secret'], $code)) {
            Response::flash('error', 'Invalid or expired code.');
            Response::redirect('/auth/2fa');
            return;
        }

        unset($_SESSION['pending_2fa_user_id']);
        $this->establishSession($user);
        Response::redirect('/dashboard');
    }

    public function oauthConnect(string $platform): void
    {
        Auth::requireAuth();
        $config = $this->getOAuthConfig($platform);
        if (!$config) {
            Response::redirect('/settings/platforms?error=unsupported_platform');
            return;
        }
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state']    = $state;
        $_SESSION['oauth_platform'] = $platform;

        $params = http_build_query([
            'client_id'     => $config['client_id'],
            'redirect_uri'  => $config['redirect_uri'],
            'scope'         => $config['scope'],
            'state'         => $state,
            'response_type' => 'code',
        ]);
        Response::redirect($config['auth_url'] . '?' . $params);
    }

    public function oauthCallback(string $platform): void
    {
        Auth::requireAuth();
        $code  = $this->request->get('code', '');
        $state = $this->request->get('state', '');

        if (empty($code) || $state !== ($_SESSION['oauth_state'] ?? '')) {
            Response::redirect('/settings/platforms?error=oauth_failed');
            return;
        }
        unset($_SESSION['oauth_state'], $_SESSION['oauth_platform']);

        $config = $this->getOAuthConfig($platform);
        if (!$config) {
            Response::redirect('/settings/platforms?error=unsupported_platform');
            return;
        }

        try {
            $tokens  = $this->exchangeOAuthCode($code, $config);
            $profile = $this->fetchOAuthProfile($platform, $tokens);
            $user    = Auth::getCurrentUser();

            $encToken   = $this->encryptToken($tokens['access_token']);
            $encRefresh = !empty($tokens['refresh_token']) ? $this->encryptToken($tokens['refresh_token']) : null;
            $expiresAt  = isset($tokens['expires_in']) ? date('Y-m-d H:i:s', time() + (int)$tokens['expires_in']) : null;

            $activeBrandId = $_SESSION['active_brand_id'] ?? '';
            $check = $this->db->prepare(
                'SELECT id FROM platform_accounts WHERE brand_id=? AND platform=? AND account_id=? LIMIT 1'
            );
            $check->execute([$activeBrandId, $platform, $profile['id']]);
            $existing = $check->fetch();

            if ($existing) {
                $this->db->prepare(
                    'UPDATE platform_accounts SET access_token_encrypted=?,refresh_token_encrypted=?,token_expires_at=?,account_name=?,updated_at=NOW() WHERE id=?'
                )->execute([$encToken, $encRefresh, $expiresAt, $profile['username'] ?? '', $existing['id']]);
            } else {
                $newAccountId = $this->generateUuid();
                $this->db->prepare(
                    'INSERT INTO platform_accounts (id,brand_id,platform,account_id,account_name,access_token_encrypted,refresh_token_encrypted,token_expires_at,is_active,created_at)
                     VALUES (?,?,?,?,?,?,?,?,1,NOW())'
                )->execute([$newAccountId, $_SESSION['active_brand_id'] ?? '', $platform, $profile['id'], $profile['username'] ?? '', $encToken, $encRefresh, $expiresAt]);
            }

            $this->logAudit($user['id'], 'oauth_connect', 'platform_account', $newAccountId ?? '', ['platform' => $platform]);
            Response::redirect('/settings/platforms?success=connected');
        } catch (\Throwable $e) {
            error_log("OAuth callback failed for {$platform}: " . $e->getMessage());
            Response::redirect('/settings/platforms?error=oauth_failed');
        }
    }

    // =========================================================================
    private function establishSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['full_name'] ?? $user['username'] ?? '';
        $_SESSION['user_role'] = 'owner';
        $_SESSION['logged_in'] = true;
        $_SESSION['login_at']  = time();
    }

    private function verifyTOTP(string $secret, string $code): bool
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secretDecoded = '';
        $buffer = 0; $bitsLeft = 0;
        foreach (str_split(strtoupper($secret)) as $c) {
            $val = strpos($chars, $c);
            if ($val === false) continue;
            $buffer   = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) { $bitsLeft -= 8; $secretDecoded .= chr(($buffer >> $bitsLeft) & 0xFF); }
        }
        $timeStep = (int)floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            $t    = pack('N*', 0) . pack('N*', $timeStep + $i);
            $hash = hash_hmac('sha1', $t, $secretDecoded, true);
            $off  = ord($hash[19]) & 0x0F;
            $otp  = (((ord($hash[$off])&0x7F)<<24)|((ord($hash[$off+1])&0xFF)<<16)|((ord($hash[$off+2])&0xFF)<<8)|(ord($hash[$off+3])&0xFF)) % 1000000;
            if (str_pad((string)$otp, 6, '0', STR_PAD_LEFT) === $code) return true;
        }
        return false;
    }

    private function getOAuthConfig(string $platform): ?array
    {
        $base = defined('APP_URL') ? APP_URL : '';
        $map = [
            'twitter'  => ['client_id'=>defined('TWITTER_CLIENT_ID')?TWITTER_CLIENT_ID:'', 'client_secret'=>defined('TWITTER_CLIENT_SECRET')?TWITTER_CLIENT_SECRET:'', 'auth_url'=>'https://twitter.com/i/oauth2/authorize', 'token_url'=>'https://api.twitter.com/2/oauth2/token', 'redirect_uri'=>$base.'/oauth/callback/twitter', 'scope'=>'tweet.read tweet.write users.read offline.access'],
            'instagram'=> ['client_id'=>defined('INSTAGRAM_CLIENT_ID')?INSTAGRAM_CLIENT_ID:'', 'client_secret'=>defined('INSTAGRAM_CLIENT_SECRET')?INSTAGRAM_CLIENT_SECRET:'', 'auth_url'=>'https://api.instagram.com/oauth/authorize', 'token_url'=>'https://api.instagram.com/oauth/access_token', 'redirect_uri'=>$base.'/oauth/callback/instagram', 'scope'=>'user_profile,user_media'],
            'linkedin' => ['client_id'=>defined('LINKEDIN_CLIENT_ID')?LINKEDIN_CLIENT_ID:'', 'client_secret'=>defined('LINKEDIN_CLIENT_SECRET')?LINKEDIN_CLIENT_SECRET:'', 'auth_url'=>'https://www.linkedin.com/oauth/v2/authorization', 'token_url'=>'https://www.linkedin.com/oauth/v2/accessToken', 'redirect_uri'=>$base.'/oauth/callback/linkedin', 'scope'=>'r_liteprofile r_emailaddress w_member_social'],
            'facebook' => ['client_id'=>defined('FACEBOOK_APP_ID')?FACEBOOK_APP_ID:'', 'client_secret'=>defined('FACEBOOK_APP_SECRET')?FACEBOOK_APP_SECRET:'', 'auth_url'=>'https://www.facebook.com/v18.0/dialog/oauth', 'token_url'=>'https://graph.facebook.com/v18.0/oauth/access_token', 'redirect_uri'=>$base.'/oauth/callback/facebook', 'scope'=>'pages_manage_posts,pages_read_engagement'],
            'tiktok'   => ['client_id'=>defined('TIKTOK_CLIENT_ID')?TIKTOK_CLIENT_ID:'', 'client_secret'=>defined('TIKTOK_CLIENT_SECRET')?TIKTOK_CLIENT_SECRET:'', 'auth_url'=>'https://www.tiktok.com/v2/auth/authorize', 'token_url'=>'https://open.tiktokapis.com/v2/oauth/token/', 'redirect_uri'=>$base.'/oauth/callback/tiktok', 'scope'=>'user.info.basic,video.list,video.upload'],
        ];
        return $map[$platform] ?? null;
    }

    private function exchangeOAuthCode(string $code, array $config): array
    {
        $ch = curl_init($config['token_url']);
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30,
            CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$config['redirect_uri'],'client_id'=>$config['client_id'],'client_secret'=>$config['client_secret']]),
            CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
        $body = curl_exec($ch); $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code2 !== 200) throw new \RuntimeException("OAuth token exchange failed HTTP {$code2}");
        $data = json_decode((string)$body, true);
        if (empty($data['access_token'])) throw new \RuntimeException('No access_token in OAuth response');
        return $data;
    }

    private function fetchOAuthProfile(string $platform, array $tokens): array
    {
        $urls = ['twitter'=>'https://api.twitter.com/2/users/me','instagram'=>'https://graph.instagram.com/me?fields=id,username','linkedin'=>'https://api.linkedin.com/v2/me','facebook'=>'https://graph.facebook.com/me','tiktok'=>'https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name'];
        $url = $urls[$platform] ?? null;
        if (!$url) return ['id'=>uniqid('',true),'username'=>''];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tokens['access_token']]]);
        $body = curl_exec($ch); curl_close($ch);
        $data = json_decode((string)$body, true) ?: [];
        return ['id'=>$data['id']??$data['data']['user']['open_id']??uniqid('',true),'username'=>$data['username']??$data['localizedFirstName']??$data['name']??'']; 
    }

    private function encryptToken(string $token): string
    {
        $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default-key';
        $iv  = random_bytes(16);
        return base64_encode($iv . openssl_encrypt($token, 'AES-256-CBC', hash('sha256',$key,true), OPENSSL_RAW_DATA, $iv));
    }

    private function logAudit(string $userId, string $action, string $resType, string $resId, array $meta): void
    {
        try {
            $this->db->prepare('INSERT INTO audit_log (user_id,action,entity_type,entity_id,new_values,ip_address,created_at) VALUES (?,?,?,?,?,?,NOW())')
                ->execute([$userId,$action,$resType,$resId,json_encode($meta),$this->getClientIp()]);
        } catch (\Throwable $e) { error_log('Audit log: '.$e->getMessage()); }
    }

    private function getClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) { $ip = trim(explode(',',$_SERVER[$k])[0]); if (filter_var($ip,FILTER_VALIDATE_IP)) return $ip; }
        }
        return '0.0.0.0';
    }
}
