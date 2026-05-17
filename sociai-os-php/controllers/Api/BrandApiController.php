<?php
declare(strict_types=1);
namespace SociAI\Controllers\Api;
use SociAI\Core\{Auth, Request, Response};
use SociAI\Models\{Brand, User};

class BrandApiController
{
    private Request $req; private Response $res; private Brand $model; private User $userModel;
    public function __construct() { $this->req=new Request(); $this->res=new Response(); $this->model=new Brand(); $this->userModel=new User(); }
    public function index(array $p): void { $this->res->success($this->userModel->getBrands(Auth::id())); }
    public function store(array $p): void {
        $d = $this->req->all();
        $b = $this->model->create(array_merge($d,['owner_id'=>Auth::id()]));
        $this->res->success($b,'Brand created.',201);
    }
    public function show(array $p): void {
        $b = $this->model->find($p['id']); if (!$b) { $this->res->error('Not found.',404); return; }
        if (!$this->model->userCanAccess($b['id'],Auth::id())) { $this->res->error('Forbidden.',403); return; }
        $this->res->success($b);
    }
    public function update(array $p): void {
        $b = $this->model->find($p['id']); if (!$b) { $this->res->error('Not found.',404); return; }
        if (!$this->model->userCanAccess($b['id'],Auth::id(),'admin','owner')) { $this->res->error('Forbidden.',403); return; }
        $this->model->update($p['id'],$this->req->all());
        $this->res->success($this->model->find($p['id']));
    }
    public function delete(array $p): void {
        $b = $this->model->find($p['id']); if (!$b) { $this->res->error('Not found.',404); return; }
        if (!$this->model->userCanAccess($b['id'],Auth::id(),'owner')) { $this->res->error('Forbidden.',403); return; }
        $this->model->delete($p['id']); $this->res->success([],'Deleted.');
    }
}
