<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth,Request,Response};
use SociAI\Models\{Brand,Content};

class CalendarController
{
    private Response $res; private Brand $brandModel; private Content $contentModel;
    public function __construct(){ $this->res=new Response(); $this->brandModel=new Brand(); $this->contentModel=new Content(); }
    public function index(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser();
        $b=$this->brandModel->findBySlug($p['slug']); if(!$b)abort(404);
        if(!$this->brandModel->userCanAccess($b['id'],$u['id']))abort(403);
        $req=new Request();
        $start=$req->get('start',date('Y-m-01')); $end=$req->get('end',date('Y-m-t'));
        $calendar=$this->contentModel->getCalendar($b['id'],$start,$end);
        if($req->isAjax()){ $this->res->success($calendar); return; }
        $this->res->view('calendar.index',['brand'=>$b,'calendar'=>$calendar,'start'=>$start,'end'=>$end,'user'=>$u,'pageTitle'=>'Content Calendar','layout'=>'app','activeBrand'=>$b,'csrf'=>Auth::csrfToken()]);
    }
}
