<?php

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

class AuthController
{
    private \PDO $db;
    private Auth $auth;
    private Request $request;
    private Response $response;

    public function __construct()
    {
        $this->db       = Database::getInstance();
        $this->auth     = new Auth();
        $this->request  = new Request();
        $this->response = new Response();
    }

    public function showLogin(): void
    {
        if ($this->auth->getCurrentUser()) {
            $this->response->redirect('/dashboard');
            return;
        }
        $data = [
            'title'   => 'Login - SociAI OS',
            'error'   => $_SESSION['login_error'] ?? null,
            'success' => $_SESSION['login_success'] ?? null,
        ];
        unset($_SESSION['login_error'], $_SESSION['login_success']);
        $this->response->view('auth/login', $data);
    }

    public function showRegister(): void
    {
        if ($this->auth->getCurrentUser()) {
            $this->response->redirect('/dashboard');
            return;
        }
        $this->response->view('auth/register', [
            'title' => 'Create Account - SociAI OS',
            'error' => $_SESSION['register_error'] ?? null,
        ]);
        unset($_SESSION['register_error']);
    }

    public function login(): void
    {
        $email    = trim($this->request->post('email', ''));
        $password = $this->request->post('password', '');
        $remember = (bool)$this->request->post('remember', false);

        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Email and password are required.';
            $this->response->redirect('/login');
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['login_error'] = 'Invalid email address.';
            $this->response->redirect('/login');
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT id,email,password_hash,name,role,is_active,two_fa_enabled,two_fa_secret,failed_login_attempts,locked_until
             FROM users WHERE email=? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            if ($user) {
                $attempts = (int)$user['failed_login_attempts'] + 1;
                $lock = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                $this->db->prepare('UPDATE users SET failed_login_attempts=?,locked_until=? WHERE id=?')
                    ->execute([$attempts, $lock, $user['id']]);
            }
            $_SESSION['login_error'] = 'Invalid email or password.';
            $this->response->redirect('/login');
            return;
        }

        if (!(bool)$user['is_active']) {
            $_SESSION['login_error'] = 'Your account has been deactivated.';
            $this->response->redirect('/login');
            return;
        }

        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $min = ceil((strtotime($user['locked_until']) - time()) / 60);
            $_SESSION['login_error'] = "Account locked. Try in {$min} minutes.";
            $this->response->redirect('/login');
            return;
        }

        $this->db->prepare(
            'UPDATE users SET failed_login_attempts=0,locked_until=NULL,last_login_at=NOW() WHERE id=?'
        )->execute([$user['id']]);

        if ((bool)$user['two_fa_enabled']) {
            $_SESSION['pending_2fa_user_id'] = $user['id'];
            $_SESSION['pending_2fa_remember'] = $remember;
            $this->response->redirect('/2fa');
            return;
        }

        $this->establishSession($user, $remember);
        $this->logAudit($user['id'], 'login', 'user', $user['id'], ['ip' => $this->getClientIp()]);
        $this->response->redirect('/dashboard');
    }

    public function register(): void
    {
        $name    = trim($this->request->post('name', ''));
        $email   = trim($this->request->post('email', ''));
        $pass    = $this->request->post('password', '');
        $confirm = $this->request->post('confirm_password', '');
        $brand   = trim($this->request->post('brand_name', ''));
        $industry= trim($this->request->post('industry', ''));

        $errors = [];
        if (strlen($name) < 2) $errors[] = 'Name must be at least 2 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
        if (!preg_match('/[A-Z]/', $pass)) $errors[] = 'Password must have an uppercase letter.';
        if (!preg_match('/[0-9]/', $pass)) $errors[] = 'Password must have a number.';
        if ($pass !== $confirm) $errors[] = 'Passwords do not match.';
        if (strlen($brand) < 2) $errors[] = 'Brand name is required.';

        if (!empty($errors)) {
            $_SESSION['register_error'] = implode(' ', $errors);
            $this->response->redirect('/register');
            return;
        }

        $dup = $this->db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $dup->execute([$email]);
        if ($dup->fetch()) {
            $_SESSION['register_error'] = 'An account with this email already exists.';
            $this->response->redirect('/register');
            return;
        }

        $this->db->beginTransaction();
        try {
            $hash = password_hash($pass, PASSWORD_ARGON2ID, ['memory_cost'=>65536,'time_cost'=>4,'threads'=>2]);
            $this->db->prepare('INSERT INTO users (name,email,password_hash,role,is_active,created_at) VALUES (?,?,?,?,1,NOW())')
                ->execute([$name, $email, $hash, 'owner']);
            $userId = (int)$this->db->lastInsertId();

            $this->db->prepare('INSERT INTO brands (name,industry,owner_id,created_at) VALUES (?,?,?,NOW())')
                ->execute([$brand, $industry, $userId]);
            $brandId = (int)$this->db->lastInsertId();

            $this->db->prepare('INSERT INTO brand_users (brand_id,user_id,role) VALUES (?,?,?)')
                ->execute([$brandId, $userId, 'owner']);

            $this->db->commit();
            $_SESSION['login_success'] = 'Account created! Please sign in.';
            $this->logAudit($userId, 'register', 'user', $userId, ['brand_id' => $brandId]);
            $this->response->redirect('/login');
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('Registration failed: ' . $e->getMessage());
            $_SESSION['register_error'] = 'Registration failed. Please try again.';
            $this->response->redirect('/register');
        }
    }

    public function logout(): void
    {
        $user = $this->auth->getCurrentUser();
        if ($user) {
            $this->logAudit($user['id'], 'logout', 'user', $user['id'], []);
            $this->db->prepare('UPDATE users SET remember_token=NULL WHERE id=?')->execute([$user['id']]);
        }
        session_unset();
        session_destroy();
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        $this->response->redirect('/login');
    }

    public function show2FA(): void
    {
        if (empty($_SESSION['pending_2fa_user_id'])) {
            $this->response->redirect('/login');
            return;
        }
        $this->response->view('auth/2fa', [
            'title' => 'Two-Factor Auth - SociAI OS',
            'error' => $_SESSION['2fa_error'] ?? null,
        ]);
        unset($_SESSION['2fa_error']);
    }

    public function verify2FA(): void
    {
        if (empty($_SESSION['pending_2fa_user_id'])) {
            $this->response->redirect('/login');
            return;
        }

        $userId = (int)$_SESSION['pending_2fa_user_id'];
        $code   = trim($this->request->post('code', ''));

        if (!preg_match('/^\d{6}$/', $code)) {
            $_SESSION['2fa_error'] = 'Please enter a valid 6-digit code.';
            $this->response->redirect('/2fa');
            return;
        }

        $stmt = $this->db->prepare('SELECT id,email,name,role,two_fa_secret FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || !$this->verifyTOTP($user['two_fa_secret'], $code)) {
            $_SESSION['2fa_error'] = 'Invalid or expired code.';
            $this->response->redirect('/2fa');
            return;
        }

        $remember = $_SESSION['pending_2fa_remember'] ?? false;
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_remember']);
        $this->establishSession($user, (bool)$remember);
        $this->logAudit($userId, '2fa_verified', 'user', $userId, []);
        $this->response->redirect('/dashboard');
    }

    public function oauthConnect(string $platform): void
    {
        $this->auth->requireAuth();
        $config = $this->getOAuthConfig($platform);
        if (!$config) {
            $this->response->redirect('/settings/platforms?error=unsupported_platform');
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
        $this->response->redirect($config['auth_url'] . '?' . $params);
    }

    public function oauthCallback(string $platform): void
    {
        $this->auth->requireAuth();
        $code  = $this->request->get('code', '');
        $state = $this->request->get('state', '');

        if (empty($code) || $state !== ($_SESSION['oauth_state'] ?? '')) {
            $this->response->redirect('/settings/platforms?error=oauth_failed');
            return;
        }
        unset($_SESSION['oauth_state'], $_SESSION['oauth_platform']);

        $config = $this->getOAuthConfig($platform);
        if (!$config) {
            $this->response->redirect('/settings/platforms?error=unsupported_platform');
            return;
        }

        try {
            $tokens  = $this->exchangeOAuthCode($code, $config);
            $profile = $this->fetchOAuthProfile($platform, $tokens);
            $user    = $this->auth->getCurrentUser();

            $encToken   = $this->encryptToken($tokens['access_token']);
            $encRefresh = !empty($tokens['refresh_token']) ? $this->encryptToken($tokens['refresh_token']) : null;
            $expiresAt  = isset($tokens['expires_in']) ? date('Y-m-d H:i:s', time() + (int)$tokens['expires_in']) : null;

            $check = $this->db->prepare(
                'SELECT id FROM platform_accounts WHERE user_id=? AND platform=? AND platform_user_id=? LIMIT 1'
            );
            $check->execute([$user['id'], $platform, $profile['id']]);
            $existing = $check->fetch();

            if ($existing) {
                $this->db->prepare(
                    'UPDATE platform_accounts SET access_token=?,refresh_token=?,token_expires_at=?,username=?,updated_at=NOW() WHERE id=?'
                )->execute([$encToken, $encRefresh, $expiresAt, $profile['username'] ?? '', $existing['id']]);
            } else {
                $this->db->prepare(
                    'INSERT INTO platform_accounts (user_id,platform,platform_user_id,username,access_token,refresh_token,token_expires_at,created_at)
                     VALUES (?,?,?,?,?,?,?,NOW())'
                )->execute([$user['id'], $platform, $profile['id'], $profile['username'] ?? '', $encToken, $encRefresh, $expiresAt]);
            }

            $this->logAudit($user['id'], 'oauth_connect', 'platform_account', 0, ['platform' => $platform]);
            $this->response->redirect('/settings/platforms?success=connected');
        } catch (\Throwable $e) {
            error_log("OAuth callback failed for {$platform}: " . $e->getMessage());
            $this->response->redirect('/settings/platforms?error=oauth_failed');
        }
    }

    // =========================================================================
    private function establishSession(array $user, bool $remember): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_at']  = time();

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            $this->db->prepare(
                'UPDATE users SET remember_token=?,remember_token_expires_at=DATE_ADD(NOW(),INTERVAL 30 DAY) WHERE id=?'
            )->execute([$hash, $user['id']]);
            setcookie('remember_token', $user['id'] . ':' . $token, time() + 86400*30, '/', '', true, true);
        }
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

    private function logAudit(int $userId, string $action, string $resType, int $resId, array $meta): void
    {
        try {
            $this->db->prepare('INSERT INTO audit_logs (user_id,action,resource_type,resource_id,meta,ip_address,created_at) VALUES (?,?,?,?,?,?,NOW())')
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
