<?php
declare(strict_types=1);
namespace SociAI\Controllers\Api;
use SociAI\Core\{Request, Response};
use SociAI\Models\Analytics;

class TrendApiController
{
    public function index(array $p): void {
        $req = new Request();
        $trends = (new Analytics())->getActiveTrends($req->get('platform',''),$req->getInt('limit',20));
        (new Response())->success($trends);
    }
}
