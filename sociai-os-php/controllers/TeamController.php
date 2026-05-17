<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth,Request,Response};
use SociAI\Models\{Brand,User};

class TeamController
{
    private Request $req; private Response $res; private Brand $brandModel; private User $userModel;
    public function __construct(){ $this->req=new Request(); $this->res=new Response(); $this->brandModel=new Brand(); $this->userModel=new User(); }
    private function getBrand(string $slug,string $userId,string...$roles):array{ $b=$this->brandModel->findBySlug($slug); if(!$b)abort(404); if(!$this->brandModel->userCanAccess($b['id'],$userId,...$roles))abort(403); return $b; }
    public function index(array $p):void{ Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id']); $members=$this->brandModel->getTeamMembers($b['id']); $this->res->view('brands.team',['brand'=>$b,'members'=>$members,'user'=>$u,'pageTitle'=>'Team','layout'=>'app','activeBrand'=>$b,'csrf'=>Auth::csrfToken()]); }
    public function invite(array $p):void{ Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id'],'admin','owner'); $email=$this->req->post('email',''); $role=$this->req->post('role','viewer'); $invited=$this->userModel->findByEmail($email); if(!$invited){$this->res->error('User not found.');return;} $this->brandModel->addTeamMember($b['id'],$invited['id'],$role,$u['id']); $this->res->success([],'Member invited.'); }
    public function updateRole(array $p):void{ Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id'],'admin','owner'); $this->brandModel->updateMemberRole($b['id'],$p['userId'],$this->req->post('role','viewer')); $this->res->success([],'Role updated.'); }
    public function remove(array $p):void{ Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id'],'admin','owner'); $this->brandModel->removeTeamMember($b['id'],$p['userId']); $this->res->success([],'Member removed.'); }
}
