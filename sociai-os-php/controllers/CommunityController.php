<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth,Request,Response,Database};
use SociAI\Models\Brand;

class CommunityController
{
    private Request $req; private Response $res; private Brand $brandModel; private Database $db;
    public function __construct(){ $this->req=new Request(); $this->res=new Response(); $this->brandModel=new Brand(); $this->db=Database::getInstance(); }
    private function getBrand(string $slug,string $uid):array{ $b=$this->brandModel->findBySlug($slug); if(!$b)abort(404); if(!$this->brandModel->userCanAccess($b['id'],$uid))abort(403); return $b; }
    public function index(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id']);
        $items=$this->db->fetchAll("SELECT * FROM community_interactions WHERE brand_id=? ORDER BY created_at DESC LIMIT 50",[$b['id']]);
        $this->res->view('community.index',['brand'=>$b,'interactions'=>$items,'user'=>$u,'pageTitle'=>'Community','layout'=>'app','activeBrand'=>$b,'csrf'=>Auth::csrfToken()]);
    }
    public function reply(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id']);
        $reply=$this->req->post('reply',''); $this->db->update('community_interactions',['actual_reply'=>$reply,'replied_by'=>$u['id'],'replied_at'=>date('Y-m-d H:i:s'),'status'=>'replied'],'id=?',[$p['id']]);
        $this->res->success([],'Reply saved.');
    }
    public function ignore(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id']);
        $this->db->update('community_interactions',['status'=>'ignored'],'id=?',[$p['id']]); $this->res->success([],'Ignored.');
    }
}
