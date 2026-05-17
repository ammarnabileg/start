<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class TeamController
{
    private \PDO $db;
    private Auth $auth;
    private Request $request;
    private Response $response;

    private const ROLES = ['owner','admin','editor','viewer','analyst'];
    private const PERMISSIONS = [
        'owner'   => ['all'],
        'admin'   => ['content.manage','analytics.view','team.manage','settings.view','campaigns.manage','community.manage'],
        'editor'  => ['content.create','content.edit','content.approve','campaigns.view','community.reply'],
        'viewer'  => ['content.view','analytics.view','campaigns.view'],
        'analyst' => ['analytics.view','analytics.export','content.view'],
    ];

    public function __construct()
    {
        $this->db       = Database::getInstance();
        $this->auth     = new Auth();
        $this->request  = new Request();
        $this->response = new Response();
    }

    public function index(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $members = $this->loadTeamMembers($brandId);
        $pending = $this->loadPendingInvites($brandId);
        $this->response->view('team/index',['title'=>'Team - SociAI OS','members'=>$members,'pending'=>$pending,'roles'=>self::ROLES,'user'=>$user]);
    }

    public function invite(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        if (!$this->userIsAdmin($user,$brandId)) { $this->response->json(['success'=>false,'error'=>'Insufficient permissions'],403); return; }
        $email = trim($this->request->post('email',''));
        $role  = $this->request->post('role','viewer');
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $this->response->json(['success'=>false,'error'=>'Invalid email'],422); return; }
        if (!in_array($role,self::ROLES,true)||$role==='owner') { $this->response->json(['success'=>false,'error'=>'Invalid role'],422); return; }
        $s = $this->db->prepare('SELECT u.id FROM users u INNER JOIN brand_users bu ON bu.user_id=u.id WHERE u.email=? AND bu.brand_id=? LIMIT 1');
        $s->execute([$email,$brandId]);
        if ($s->fetch()) { $this->response->json(['success'=>false,'error'=>'Already a member'],422); return; }
        $s2 = $this->db->prepare('SELECT id FROM team_invites WHERE email=? AND brand_id=? AND status="pending" LIMIT 1');
        $s2->execute([$email,$brandId]);
        if ($s2->fetch()) { $this->response->json(['success'=>false,'error'=>'Invite already sent'],422); return; }
        $token = bin2hex(random_bytes(32));
        $this->db->prepare('INSERT INTO team_invites (brand_id,email,role,token,invited_by,expires_at,status,created_at) VALUES (?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY),"pending",NOW())')->execute([$brandId,$email,$role,hash('sha256',$token),$user['id']]);
        $inviteUrl = (defined('APP_URL')?APP_URL:'').'/invite/accept?token='.$token;
        $this->response->json(['success'=>true,'message'=>'Invitation sent to '.$email,'invite_url'=>$inviteUrl]);
    }

    public function updateRole(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        if (!$this->userIsAdmin($user,$brandId)) { $this->response->json(['success'=>false,'error'=>'Insufficient permissions'],403); return; }
        $memberId = (int)$this->request->post('member_id',0);
        $newRole  = $this->request->post('role','');
        if (!in_array($newRole,self::ROLES,true)||$newRole==='owner') { $this->response->json(['success'=>false,'error'=>'Invalid role'],422); return; }
        $s = $this->db->prepare('SELECT role FROM brand_users WHERE user_id=? AND brand_id=? LIMIT 1');
        $s->execute([$memberId,$brandId]);
        $m = $s->fetch(\PDO::FETCH_ASSOC);
        if (!$m) { $this->response->json(['success'=>false,'error'=>'Member not found'],404); return; }
        if ($m['role']==='owner') { $this->response->json(['success'=>false,'error'=>'Cannot change owner role'],422); return; }
        $this->db->prepare('UPDATE brand_users SET role=?,updated_at=NOW() WHERE user_id=? AND brand_id=?')->execute([$newRole,$memberId,$brandId]);
        $this->response->json(['success'=>true,'message'=>'Role updated.']);
    }

    public function removeMember(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        if (!$this->userIsAdmin($user,$brandId)) { $this->response->json(['success'=>false,'error'=>'Insufficient permissions'],403); return; }
        $memberId = (int)$this->request->post('member_id',0);
        if ($memberId===$user['id']) { $this->response->json(['success'=>false,'error'=>'Cannot remove yourself'],422); return; }
        $s = $this->db->prepare('SELECT role FROM brand_users WHERE user_id=? AND brand_id=? LIMIT 1');
        $s->execute([$memberId,$brandId]);
        $m = $s->fetch(\PDO::FETCH_ASSOC);
        if (!$m) { $this->response->json(['success'=>false,'error'=>'Not found'],404); return; }
        if ($m['role']==='owner') { $this->response->json(['success'=>false,'error'=>'Cannot remove owner'],422); return; }
        $this->db->prepare('DELETE FROM brand_users WHERE user_id=? AND brand_id=?')->execute([$memberId,$brandId]);
        $this->response->json(['success'=>true,'message'=>'Member removed.']);
    }

    public function getPermissions(): void
    {
        $this->auth->requireAuth();
        $role = $this->request->get('role','');
        if (!array_key_exists($role,self::PERMISSIONS)) { $this->response->json(['success'=>false,'error'=>'Unknown role'],400); return; }
        $this->response->json(['success'=>true,'role'=>$role,'permissions'=>self::PERMISSIONS[$role]]);
    }

    public function getApprovalQueue(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $s = $this->db->prepare('SELECT cp.id,cp.platform,cp.content_type,cp.content_text,cp.created_at,u.name AS created_by_name FROM content_posts cp LEFT JOIN users u ON u.id=cp.created_by WHERE cp.brand_id=? AND cp.status="pending_approval" ORDER BY cp.created_at ASC');
        $s->execute([$brandId]);
        $queue = $s->fetchAll(\PDO::FETCH_ASSOC);
        $this->response->json(['success'=>true,'queue'=>$queue,'count'=>count($queue)]);
    }

    private function loadTeamMembers(int $brandId): array
    {
        $s = $this->db->prepare('SELECT u.id,u.name,u.email,bu.role,bu.created_at AS joined_at,u.last_login_at,u.is_active FROM brand_users bu INNER JOIN users u ON u.id=bu.user_id WHERE bu.brand_id=? ORDER BY bu.created_at ASC');
        $s->execute([$brandId]);
        return $s->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function loadPendingInvites(int $brandId): array
    {
        $s = $this->db->prepare('SELECT id,email,role,expires_at,created_at FROM team_invites WHERE brand_id=? AND status="pending" AND expires_at>NOW() ORDER BY created_at DESC');
        $s->execute([$brandId]);
        return $s->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function userIsAdmin(array $user, int $brandId): bool
    {
        $s = $this->db->prepare('SELECT role FROM brand_users WHERE user_id=? AND brand_id=? LIMIT 1');
        $s->execute([$user['id'],$brandId]);
        $r = $s->fetch(\PDO::FETCH_ASSOC);
        return $r && in_array($r['role'],['owner','admin'],true);
    }

    private function getActiveBrandId(int $userId): int
    {
        if (!empty($_SESSION['active_brand_id'])) return (int)$_SESSION['active_brand_id'];
        $s = $this->db->prepare('SELECT b.id FROM brands b INNER JOIN brand_users bu ON bu.brand_id=b.id WHERE bu.user_id=? ORDER BY bu.created_at ASC LIMIT 1');
        $s->execute([$userId]);
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : 0;
    }
}
