<?php
declare(strict_types=1);
namespace SociAI\Controllers\Api;
use SociAI\Core\{Auth, AI, Request, Response, Database};
use SociAI\Models\Analytics;

class AIApiController
{
    private Request $req; private Response $res;
    public function __construct() { $this->req=new Request(); $this->res=new Response(); }
    public function generate(array $p): void {
        $params = $this->req->all();
        if (empty($params['brand_id'])) { Response::error('brand_id required.'); return; }
        $params['user_id'] = Auth::id();
        $agent = new \SociAI\Agents\ContentGeneratorAgent();
        try { $result = $agent->generate($params); Response::success($result['output'],'Content generated.'); }
        catch(\Throwable $e) { Response::error($e->getMessage()); }
    }
    public function generateImage(array $p): void {
        $prompt = $this->req->post('prompt','');
        if (!$prompt) { Response::error('prompt required.'); return; }
        try {
            $result = AI::generateImage($prompt,(int)$this->req->post('width',1024),(int)$this->req->post('height',1024));
            Response::success($result);
        } catch(\Throwable $e) { Response::error($e->getMessage()); }
    }
    public function viralScore(array $p): void {
        $m = $this->req->json() ?? $this->req->all();
        $score = (new Analytics())->calculateViralScore($m);
        Response::success(['viral_score'=>$score]);
    }
    public function taskStatus(array $p): void {
        $task = Database::getInstance()->fetchOne("SELECT * FROM agent_tasks WHERE id=?",$p['id']);
        if (!$task) { Response::error('Not found.',404); return; }
        foreach(['input_data','output_data'] as $f) { if(isset($task[$f])&&is_string($task[$f])) $task[$f]=json_decode($task[$f],true); }
        Response::success($task);
    }
}
