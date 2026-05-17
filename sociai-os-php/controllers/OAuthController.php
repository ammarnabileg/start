<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth, Database, Request, Response};

class OAuthController
{
    private Request $req;

    public function __construct()
    {
        $this->req = new Request();
    }

    public function connect(array $p): void
    {
        Auth::requireAuth();
        $platform = $p['platform'] ?? '';
        $authUrl  = match($platform) {
            'linkedin' => 'https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id=' . (defined('LINKEDIN_CLIENT_ID') ? LINKEDIN_CLIENT_ID : '') . '&redirect_uri=' . urlencode(defined('LINKEDIN_REDIRECT_URI') ? LINKEDIN_REDIRECT_URI : '') . '&state=' . bin2hex(random_bytes(16)) . '&scope=r_liteprofile%20r_emailaddress%20w_member_social',
            'twitter'  => 'https://twitter.com/i/oauth2/authorize?response_type=code&client_id=' . (defined('TWITTER_API_KEY') ? TWITTER_API_KEY : '') . '&redirect_uri=' . urlencode(defined('TWITTER_REDIRECT_URI') ? TWITTER_REDIRECT_URI : '') . '&scope=tweet.read%20tweet.write%20users.read&state=' . bin2hex(random_bytes(16)) . '&code_challenge=challenge&code_challenge_method=plain',
            default    => null,
        };
        if (!$authUrl) {
            Response::flash('error', 'Platform not supported yet.');
            Response::redirect('/dashboard');
            return;
        }
        Response::redirect($authUrl);
    }

    public function callback(array $p): void
    {
        $platform = $p['platform'] ?? '';
        $code     = $this->req->get('code', '');
        if (!$code) {
            Response::flash('error', 'OAuth failed: no code received.');
            Response::redirect('/dashboard');
            return;
        }
        Response::flash('info', "OAuth for {$platform} callback received. Token exchange to be implemented.");
        Response::redirect('/dashboard');
    }

    public function disconnect(array $p): void
    {
        Auth::requireAuth();
        $accountId = $this->req->post('account_id', '');
        Database::getInstance()->update('platform_accounts', ['is_active' => 0], 'id=?', [$accountId]);
        Response::success([], 'Account disconnected.');
    }
}
