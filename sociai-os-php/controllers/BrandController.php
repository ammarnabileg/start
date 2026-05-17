<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth, Request, Response};
use SociAI\Models\{Brand, User};

class BrandController
{
    private Request $request; private Response $response;
    private Brand $brandModel; private User $userModel;
    public function __construct() {
        $this->request = new Request(); $this->response = new Response();
        $this->brandModel = new Brand(); $this->userModel = new User();
    }
    public function index(array $p): void {
        Auth::requireAuth(); $user = Auth::getCurrentUser();
        $brands = $this->userModel->getBrands($user['id']);
        $this->response->view('brands.index', ['brands'=>$brands,'user'=>$user,'pageTitle'=>'Brands','layout'=>'app','csrf'=>Auth::csrfToken()]);
    }
    public function create(array $p): void {
        Auth::requireAuth(); $user = Auth::getCurrentUser();
        $this->response->view('brands.create', ['user'=>$user,'pageTitle'=>'Create Brand','layout'=>'app','csrf'=>Auth::csrfToken()]);
    }
    public function store(array $p): void {
        Auth::requireAuth(); $user = Auth::getCurrentUser();
        $errors = $this->request->validate(['name'=>'required|min:2|max:100']);
        if (!empty($errors)) { Response::flash('error','Name is required.'); $this->response->redirect('/brands/create'); return; }
        $brand = $this->brandModel->create(['owner_id'=>$user['id'],'name'=>$this->request->post('name'),'description'=>$this->request->post('description',''),'industry'=>$this->request->post('industry','')]);
        Response::flash('success','Brand created!'); $this->response->redirect('/brands/'.$brand['slug']);
    }
    public function show(array $p): void {
        Auth::requireAuth(); $user = Auth::getCurrentUser();
        $brand = $this->brandModel->findBySlug($p['slug']); if (!$brand) abort(404);
        if (!$this->brandModel->userCanAccess($brand['id'],$user['id'])) abort(403);
        $stats = $this->brandModel->getStats($brand['id']);
        $platforms = $this->brandModel->getPlatformAccounts($brand['id']);
        $this->response->view('brands.show', ['brand'=>$brand,'stats'=>$stats,'platforms'=>$platforms,'user'=>$user,'pageTitle'=>$brand['name'],'layout'=>'app','activeBrand'=>$brand,'csrf'=>Auth::csrfToken()]);
    }
    public function edit(array $p): void {
        Auth::requireAuth(); $user = Auth::getCurrentUser();
        $brand = $this->brandModel->findBySlug($p['slug']); if (!$brand) abort(404);
        if (!$this->brandModel->userCanAccess($brand['id'],$user['id'],'admin','owner')) abort(403);
        $this->response->view('brands.edit', ['brand'=>$brand,'user'=>$user,'pageTitle'=>'Edit '.$brand['name'],'layout'=>'app','activeBrand'=>$brand,'csrf'=>Auth::csrfToken()]);
    }
    public function update(array $p): void {
        Auth::requireAuth(); $user = Auth::getCurrentUser();
        $brand = $this->brandModel->findBySlug($p['slug']); if (!$brand) abort(404);
        if (!$this->brandModel->userCanAccess($brand['id'],$user['id'],'admin','owner')) abort(403);
        $this->brandModel->update($brand['id'],['name'=>$this->request->post('name',$brand['name']),'description'=>$this->request->post('description',''),'industry'=>$this->request->post('industry','')]);
        Response::flash('success','Brand updated.'); $this->response->redirect('/brands/'.$p['slug']);
    }
    public function delete(array $p): void {
        Auth::requireAuth(); $user = Auth::getCurrentUser();
        $brand = $this->brandModel->findBySlug($p['slug']); if (!$brand) abort(404);
        if (!$this->brandModel->userCanAccess($brand['id'],$user['id'],'owner')) abort(403);
        $this->brandModel->delete($brand['id']);
        Response::flash('success','Brand deleted.'); $this->response->redirect('/brands');
    }
}
