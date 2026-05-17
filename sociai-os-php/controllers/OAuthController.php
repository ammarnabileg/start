<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth,Request,Response};
use SociAI\Models\Brand;

class OAuthController
{
    private Request $req; private Response $res; private Brand $brandModel;
    public function __construct(){ $this->req=new Request(); $this->res=new Response(); $this->brandModel=new Brand(); }
    public function connect(array $p):void{
        Auth::requireAuth();
        $platform=$p['platform']??'';
        // Build OAuth URL per platform
        $authUrl = match($platform){
            'linkedin'  => 'https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id='.LINKEDIN_CLIENT_ID.'&redirect_uri='.urlencode(LINKEDIN_REDIRECT_URI).'&state='.bin2hex(random_bytes(16)).'&scope=r_liteprofile%20r_emailaddress%20w_member_social',
            'twitter'   => 'https://twitter.com/i/oauth2/authorize?response_type=code&client_id='.TWITTER_API_KEY.'&redirect_uri='.urlencode(TWITTER_REDIRECT_URI).'&scope=tweet.read%20tweet.write%20users.read&state='.bin2hex(random_bytes(16)).'&code_challenge=challenge&code_challenge_method=plain',
            default     => null,
        };
        if(!$authUrl){ Response::flash('error','Platform not supported yet.'); $this->res->back(); return; }
        $this->res->redirect($authUrl);
    }
    public function callback(array $p):void{
        $platform=$p['platform']??''; $code=$this->req->get('code','');
        if(!$code){ Response::flash('error','OAuth failed: no code received.'); $this->res->redirect('/dashboard'); return; }
        // Token exchange would go here per platform
        Response::flash('info',"OAuth for {$platform} callback received. Token exchange to be implemented with actual credentials.");
        $this->res->redirect('/dashboard');
    }
    public function disconnect(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser();
        $accountId=$this->req->post('account_id','');
        \SociAI\Core\Database::getInstance()->update('platform_accounts',['is_active'=>0],'id=?',[$accountId]);
        $this->res->success([],'Account disconnected.');
    }
}
