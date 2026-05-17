<?php
declare(strict_types=1);
namespace SociAI\Controllers\Api;
use SociAI\Core\{Auth, Request, Response};
use SociAI\Models\{Brand, User};

class BrandApiController
{
    private Request $req; private Response $res; private Brand $model; private User $userModel;
    public function __construct() { $this->req=new Request(); $this->res=new Response(); $this->model=new Brand(); $this->userModel=new User(); }
    public function index(array $p): void { Response::success($this->userModel->getBrands(Auth::id())); }
    public function store(array $p): void {
        $d = $this->req->all();
        $b = $this->model->create(array_merge($d,['owner_id'=>Auth::id()]));
        Response::success($b,'Brand created.',201);
    }
    public function show(array $p): void {
        $b = $this->model->find($p['id']); if (!$b) { Response::error('Not found.',404); return; }
        if (!$this->model->userCanAccess($b['id'],Auth::id())) { Response::error('Forbidden.',403); return; }
        Response::success($b);
    }
    public function update(array $p): void {
        $b = $this->model->find($p['id']); if (!$b) { Response::error('Not found.',404); return; }
        if (!$this->model->userCanAccess($b['id'],Auth::id(),'admin','owner')) { Response::error('Forbidden.',403); return; }
        $this->model->update($p['id'],$this->req->all());
        Response::success($this->model->find($p['id']));
    }
    public function delete(array $p): void {
        $b = $this->model->find($p['id']); if (!$b) { Response::error('Not found.',404); return; }
        if (!$this->model->userCanAccess($b['id'],Auth::id(),'owner')) { Response::error('Forbidden.',403); return; }
        $this->model->delete($p['id']); Response::success([],'Deleted.');
    }
}
