<?php
declare(strict_types=1);
namespace SociAI\Controllers\Api;
use SociAI\Core\{Auth, Response};
use SociAI\Models\{Brand, Analytics};

class AnalyticsApiController
{
    private Response $res; private Brand $brandModel; private Analytics $analytics;
    public function __construct() { $this->res=new Response(); $this->brandModel=new Brand(); $this->analytics=new Analytics(); }
    private function getBrand(string $id): array {
        $b=$this->brandModel->find($id); if(!$b){$this->res->error('Not found.',404);exit;}
        if(!$this->brandModel->userCanAccess($b['id'],Auth::id())){$this->res->error('Forbidden.',403);exit;} return $b;
    }
    public function dashboard(array $p): void {
        $b=$this->getBrand($p['brandId']); $period=(new \SociAI\Core\Request())->get('period','30d');
        $this->res->success($this->analytics->getDashboard($b['id'],$period));
    }
    public function platforms(array $p): void {
        $b=$this->getBrand($p['brandId']); $period=(new \SociAI\Core\Request())->get('period','30d');
        $this->res->success($this->analytics->getPlatformBreakdown($b['id'],$period));
    }
    public function topPosts(array $p): void {
        $b=$this->getBrand($p['brandId']); $req=new \SociAI\Core\Request();
        $this->res->success($this->analytics->getTopPosts($b['id'],$req->getInt('limit',10),$req->get('period','30d'),$req->get('metric','engagement_rate')));
    }
}
