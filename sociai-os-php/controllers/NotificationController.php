<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth,Request,Response};
use SociAI\Models\User;

class NotificationController
{
    private Response $res; private User $userModel;
    public function __construct(){ $this->res=new Response(); $this->userModel=new User(); }
    public function index(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser();
        $notifs=$this->userModel->getNotifications($u['id']);
        if((new Request())->isAjax()){ $this->res->success($notifs); return; }
        $this->res->view('notifications.index',['notifications'=>$notifs,'user'=>$u,'pageTitle'=>'Notifications','layout'=>'app','notifCount'=>0]);
    }
    public function markRead(array $p):void{
        Auth::requireAuth(); $u=Auth::getCurrentUser();
        $ids=(new Request())->post('ids',null);
        $this->userModel->markNotificationsRead($u['id'],$ids);
        $this->res->success([],'Marked as read.');
    }
}
