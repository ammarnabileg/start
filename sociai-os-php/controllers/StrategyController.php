<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth,Request,Response};
use SociAI\Models\Brand;

class StrategyController
{
    private Request $req; private Response $res; private Brand $brandModel;
    public function __construct(){ $this->req=new Request(); $this->res=new Response(); $this->brandModel=new Brand(); }
    private function getBrand(string $slug,string $uid):array{ $b=$this->brandModel->findBySlug($slug); if(!$b)abort(404); if(!$this->brandModel->userCanAccess($b['id'],$uid))abort(403); return $b; }
    public function index(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id']);
        $strats=$this->brandModel->getStrategies($b['id']); $active=$this->brandModel->getActiveStrategy($b['id']);
        $this->res->view('strategy.index',['brand'=>$b,'strategies'=>$strats,'active'=>$active,'user'=>$u,'pageTitle'=>'Strategy','layout'=>'app','activeBrand'=>$b,'csrf'=>Auth::csrfToken()]);
    }
    public function store(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id']);
        $id=$this->brandModel->createStrategy(['brand_id'=>$b['id'],'name'=>$this->req->post('name','New Strategy'),'brand_tone'=>$this->req->post('brand_tone',''),'content_pillars'=>array_filter(explode(',', $this->req->post('content_pillars',''))),'created_by'=>$u['id']]);
        Response::flash('success','Strategy created.'); $this->res->redirect('/brands/'.$p['slug'].'/strategy/'.$id);
    }
    public function show(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser(); $b=$this->getBrand($p['slug'],$u['id']);
        $s=\SociAI\Core\Database::getInstance()->fetchOne("SELECT * FROM marketing_strategies WHERE id=? AND brand_id=?",[$p['id'],$b['id']]); if(!$s)abort(404);
        $this->res->view('strategy.show',['brand'=>$b,'strategy'=>$s,'user'=>$u,'pageTitle'=>'Strategy','layout'=>'app','activeBrand'=>$b]);
    }
}
