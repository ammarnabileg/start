<?php
declare(strict_types=1);
namespace SociAI\Controllers\Api;
use SociAI\Core\{Auth, Request, Response};
use SociAI\Models\{Brand, Content};

class ContentApiController
{
    private Request $req; private Response $res; private Brand $brandModel; private Content $contentModel;
    public function __construct() { $this->req=new Request(); $this->res=new Response(); $this->brandModel=new Brand(); $this->contentModel=new Content(); }
    private function getBrand(string $id): array {
        $b=$this->brandModel->find($id); if(!$b){Response::error('Brand not found.',404);exit;}
        if(!$this->brandModel->userCanAccess($b['id'],Auth::id())){Response::error('Forbidden.',403);exit;} return $b;
    }
    public function index(array $p): void {
        $b=$this->getBrand($p['brandId']); $page=$this->req->getInt('page',1);
        $r=$this->contentModel->getByBrand($b['id'],$this->req->only(['status','language','content_type']),$page);
        Response::paginated($r);
    }
    public function store(array $p): void {
        $b=$this->getBrand($p['brandId']); $d=$this->req->all();
        $c=$this->contentModel->create(array_merge($d,['brand_id'=>$b['id'],'created_by'=>Auth::id()]));
        Response::success($c,'Content created.',201);
    }
    public function show(array $p): void {
        $b=$this->getBrand($p['brandId']); $c=$this->contentModel->find($p['id']);
        if(!$c||$c['brand_id']!=$b['id']){Response::error('Not found.',404);return;} Response::success($c);
    }
    public function update(array $p): void {
        $b=$this->getBrand($p['brandId']); $c=$this->contentModel->find($p['id']);
        if(!$c||$c['brand_id']!=$b['id']){Response::error('Not found.',404);return;}
        $this->contentModel->update($p['id'],$this->req->all()); Response::success($this->contentModel->find($p['id']));
    }
    public function delete(array $p): void {
        $b=$this->getBrand($p['brandId']); $this->contentModel->delete($p['id']); Response::success([],'Deleted.');
    }
    public function approve(array $p): void {
        $b=$this->getBrand($p['brandId']);
        if(!$this->brandModel->userCanAccess($b['id'],Auth::id(),'manager','admin','owner')){Response::error('Insufficient permissions.',403);return;}
        if($this->contentModel->approve($p['id'],Auth::id())) Response::success([],'Approved.'); else Response::error('Cannot approve.');
    }
}
