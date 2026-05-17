<?php
declare(strict_types=1);
namespace SociAI\Controllers\Api;
use SociAI\Core\{Auth, Request, Response};

class AuthApiController
{
    private Request $req; private Response $res;
    public function __construct() { $this->req = new Request(); $this->res = new Response(); }

    public function login(array $p): void {
        $email    = $this->req->post('email','');
        $password = $this->req->post('password','');
        $result   = Auth::login($email, $password);
        if (!$result['success']) { Response::error($result['message'],401); return; }
        if (!empty($result['requires_2fa'])) { Response::json(['success'=>true,'requires_2fa'=>true],200); return; }
        // Issue JWT
        $user  = Auth::getCurrentUser();
        $token = Auth::generateToken(['sub'=>$user['id'],'email'=>$user['email']],SESSION_LIFETIME);
        Response::success(['token'=>$token,'user'=>(new \SociAI\Models\User())->sanitize($user)],'Login successful.');
    }
    public function register(array $p): void {
        $data   = $this->req->all();
        $result = Auth::register($data);
        if (!$result['success']) { Response::error('Validation failed.',422,$result['errors']??[]); return; }
        Response::success(['user_id'=>$result['user_id']],'Account created.',201);
    }
    public function refresh(array $p): void {
        $user  = Auth::getCurrentUser();
        $token = Auth::generateToken(['sub'=>$user['id'],'email'=>$user['email']],SESSION_LIFETIME);
        Response::success(['token'=>$token]);
    }
    public function logout(array $p): void { Auth::logout(); Response::success([],'Logged out.'); }
}
