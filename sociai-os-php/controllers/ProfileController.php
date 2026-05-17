<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth,Request,Response};
use SociAI\Models\User;

class ProfileController
{
    private Request $req; private Response $res; private User $userModel;
    public function __construct(){ $this->req=new Request(); $this->res=new Response(); $this->userModel=new User(); }
    public function show(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser();
        $sessions=$this->userModel->getSessions($u['id']); $history=$this->userModel->getLoginHistory($u['id']);
        $this->res->view('profile.show',['user'=>$this->userModel->sanitize($u),'sessions'=>$sessions,'history'=>$history,'pageTitle'=>'Profile','layout'=>'app','csrf'=>Auth::csrfToken()]);
    }
    public function update(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser();
        $this->userModel->update($u['id'],['full_name'=>$this->req->post('full_name',$u['full_name']),'timezone'=>$this->req->post('timezone','UTC'),'preferred_language'=>$this->req->post('preferred_language','en')]);
        if($this->req->isAjax()){ (new Response())->success([],'Profile updated.'); return; }
        Response::flash('success','Profile updated.'); $this->res->redirect('/profile');
    }
    public function changePassword(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser();
        $current=$this->req->post('current_password',''); $new=$this->req->post('new_password','');
        if(!Auth::verifyPassword($current,$u['password_hash'])){ $this->res->error('Current password is incorrect.'); return; }
        $errs=\SociAI\Core\Security::validatePassword($new); if(!empty($errs)){ $this->res->error(implode(' ',$errs)); return; }
        $this->userModel->update($u['id'],['password'=>$new]);
        $this->res->success([],'Password changed.');
    }
    public function enable2FA(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser();
        $code=$this->req->post('code',''); $secret=$this->req->post('secret','');
        if(!Auth::verifyTOTP($secret,$code)){ $this->res->error('Invalid code.'); return; }
        $this->userModel->enable2FA($u['id'],$secret); $this->res->success([],'2FA enabled.');
    }
    public function disable2FA(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser();
        $this->userModel->disable2FA($u['id']); $this->res->success([],'2FA disabled.');
    }
    public function sessions(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser();
        $this->res->success($this->userModel->getSessions($u['id']));
    }
}
