<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth, Request, Response};
use SociAI\Models\{Brand, Analytics};

class AnalyticsController
{
    private Response $response; private Brand $brandModel; private Analytics $analytics;
    public function __construct() {
        $this->response = new Response(); $this->brandModel = new Brand(); $this->analytics = new Analytics();
    }
    public function index(array $p): void {
        Auth::requireAuth(); $user = Auth::getCurrentUser();
        $brand = $this->getBrand($p['slug'],$user['id']);
        $period = (new \SociAI\Core\Request())->get('period','30d');
        $dash = $this->analytics->getDashboard($brand['id'],$period);
        $this->response->view('analytics.index',['brand'=>$brand,'dash'=>$dash,'period'=>$period,'user'=>$user,'pageTitle'=>'Analytics','layout'=>'app','activeBrand'=>$brand]);
    }
    public function platforms(array $p): void {
        Auth::requireAuth(); $user = Auth::getCurrentUser();
        $brand = $this->getBrand($p['slug'],$user['id']);
        $data = $this->analytics->getPlatformBreakdown($brand['id'], (new \SociAI\Core\Request())->get('period','30d'));
        (new Response())->success($data);
    }
    public function posts(array $p): void {
        Auth::requireAuth(); $user = Auth::getCurrentUser();
        $brand = $this->getBrand($p['slug'],$user['id']);
        $data = $this->analytics->getTopPosts($brand['id'], 10, (new \SociAI\Core\Request())->get('period','30d'));
        (new Response())->success($data);
    }
    private function getBrand(string $slug, string $userId): array {
        $brand = $this->brandModel->findBySlug($slug); if (!$brand) abort(404);
        if (!$this->brandModel->userCanAccess($brand['id'],$userId)) abort(403);
        return $brand;
    }
}
