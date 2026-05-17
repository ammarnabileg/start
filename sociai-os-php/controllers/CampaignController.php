<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth,Request,Response,Database,Security};
use SociAI\Models\Brand;

class CampaignController
{
    private Request $req; private Response $res; private Brand $brandModel; private Database $db;
    public function __construct(){ $this->req=new Request(); $this->res=new Response(); $this->brandModel=new Brand(); $this->db=Database::getInstance(); }
    private function getBrand(string $slug,string $uid,string...$r):array{ $b=$this->brandModel->findBySlug($slug); if(!$b)abort(404); if(!$this->brandModel->userCanAccess($b['id'],$uid,...$r))abort(403); return $b; }
    public function index(array $p):void{ Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id']); $camps=$this->db->fetchAll("SELECT * FROM campaigns WHERE brand_id=? ORDER BY created_at DESC",[$b['id']]); $this->res->view('campaigns.index',['brand'=>$b,'campaigns'=>$camps,'user'=>$u,'pageTitle'=>'Campaigns','layout'=>'app','activeBrand'=>$b,'csrf'=>Auth::csrfToken()]); }
    public function create(array $p):void{ Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id'],'manager','admin','owner'); $this->res->view('campaigns.create',['brand'=>$b,'user'=>$u,'pageTitle'=>'New Campaign','layout'=>'app','activeBrand'=>$b,'csrf'=>Auth::csrfToken()]); }
    public function store(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id'],'manager','admin','owner');
        $id=Security::generateUUID(); $this->db->insert('campaigns',['id'=>$id,'brand_id'=>$b['id'],'name'=>$this->req->post('name'),'description'=>$this->req->post('description',''),'goal'=>$this->req->post('goal',''),'status'=>'draft','created_by'=>$u['id'],'start_date'=>$this->req->post('start_date')||null,'end_date'=>$this->req->post('end_date')||null]);
        Response::flash('success','Campaign created!'); $this->res->redirect('/brands/'.$p['slug'].'/campaigns/'.$id);
    }
    public function show(array $p):void{ Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id']); $c=$this->db->fetchOne("SELECT * FROM campaigns WHERE id=? AND brand_id=?",[$p['id'],$b['id']]); if(!$c)abort(404); $this->res->view('campaigns.show',['brand'=>$b,'campaign'=>$c,'user'=>$u,'pageTitle'=>$c['name'],'layout'=>'app','activeBrand'=>$b,'csrf'=>Auth::csrfToken()]); }
    public function update(array $p):void{ Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id'],'manager','admin','owner'); $this->db->update('campaigns',['name'=>$this->req->post('name'),'status'=>$this->req->post('status','draft')],'id=? AND brand_id=?',[$p['id'],$b['id']]); Response::flash('success','Campaign updated.'); $this->res->redirect('/brands/'.$p['slug'].'/campaigns/'.$p['id']); }
}
