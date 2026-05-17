<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../agents/CommunityAgent.php';

class CommunityController
{
    private \PDO $db;
    private Auth $auth;
    private Request $request;
    private Response $response;

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
        [$queue,] = $this->fetchCommentQueue($brandId,'pending',1,10);
        $this->response->view('community/index',['title'=>'Community - SociAI OS','commentStats'=>$this->getCommentStats($brandId),'dmStats'=>$this->getDMStats($brandId),'recentQueue'=>$queue,'brandId'=>$brandId]);
    }

    public function getQueue(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $status  = $this->request->get('status','pending');
        $platform= $this->request->get('platform','all');
        $page    = max(1,(int)$this->request->get('page',1));
        [$items,$total] = $this->fetchCommentQueue($brandId,$status,$page,25,$platform);
        $this->response->json(['success'=>true,'items'=>$items,'total'=>$total,'page'=>$page]);
    }

    public function reply(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $commentId = (int)$this->request->post('comment_id',0);
        $replyText = trim($this->request->post('reply_text',''));
        $useAI     = (bool)$this->request->post('use_ai',false);

        $comment = $this->getComment($commentId,$brandId);
        if (!$comment) { $this->response->json(['success'=>false,'error'=>'Comment not found'],404); return; }

        if ($useAI) {
            try {
                $agent = new CommunityAgent($brandId);
                $res   = $agent->autoReplyComment($comment['comment_text'],$this->getBrandVoice($brandId),$comment['platform']);
                $replyText = $res['reply'] ?? $replyText;
            } catch (\Throwable $e) { error_log('AI reply: '.$e->getMessage()); }
        }

        if (empty($replyText)) { $this->response->json(['success'=>false,'error'=>'Reply text required'],400); return; }

        $this->db->prepare('INSERT INTO community_replies (comment_id,brand_id,reply_text,replied_by,is_ai_generated,created_at) VALUES (?,?,?,?,?,NOW())')->execute([$commentId,$brandId,$replyText,$user['id'],$useAI?1:0]);
        $this->db->prepare('UPDATE community_comments SET status="replied",replied_at=NOW() WHERE id=?')->execute([$commentId]);
        $this->response->json(['success'=>true,'reply_text'=>$replyText,'is_ai'=>$useAI]);
    }

    public function bulkReply(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $ids     = array_filter(array_map('intval',(array)$this->request->post('comment_ids',[])));
        if (empty($ids)) { $this->response->json(['success'=>false,'error'=>'No IDs provided'],400); return; }
        $agent = new CommunityAgent($brandId);
        $bv    = $this->getBrandVoice($brandId);
        $res   = [];
        foreach ($ids as $id) {
            $comment = $this->getComment($id,$brandId);
            if (!$comment || $comment['status']==='replied') continue;
            try {
                $ai = $agent->autoReplyComment($comment['comment_text'],$bv,$comment['platform']);
                if (!empty($ai['reply'])) {
                    $this->db->prepare('INSERT INTO community_replies (comment_id,brand_id,reply_text,replied_by,is_ai_generated,created_at) VALUES (?,?,?,?,1,NOW())')->execute([$id,$brandId,$ai['reply'],$user['id']]);
                    $this->db->prepare('UPDATE community_comments SET status="replied",replied_at=NOW() WHERE id=?')->execute([$id]);
                    $res[] = ['comment_id'=>$id,'reply'=>$ai['reply'],'status'=>'success'];
                }
            } catch (\Throwable $e) { $res[] = ['comment_id'=>$id,'status'=>'failed','error'=>$e->getMessage()]; }
        }
        $this->response->json(['success'=>true,'results'=>$res,'replied_count'=>count(array_filter($res,fn($r)=>$r['status']==='success'))]);
    }

    public function markSpam(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $id      = (int)$this->request->post('comment_id',0);
        $comment = $this->getComment($id,$brandId);
        if (!$comment) { $this->response->json(['success'=>false,'error'=>'Not found'],404); return; }
        $this->db->prepare('UPDATE community_comments SET status="spam",flagged_by=?,flagged_at=NOW() WHERE id=?')->execute([$user['id'],$id]);
        $this->response->json(['success'=>true,'message'=>'Marked as spam.']);
    }

    public function qualifyLead(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $id      = (int)$this->request->post('comment_id',0);
        $comment = $this->getComment($id,$brandId);
        if (!$comment) { $this->response->json(['success'=>false,'error'=>'Not found'],404); return; }
        try {
            $agent  = new CommunityAgent($brandId);
            $result = $agent->qualifyLead($comment['comment_text']);
            if ($result['is_lead'] ?? false) {
                $this->db->prepare('INSERT INTO leads (brand_id,source_comment_id,platform,username,score,intent,created_at) VALUES (?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE score=VALUES(score),intent=VALUES(intent)')->execute([$brandId,$id,$comment['platform'],$comment['commenter_username']??'',$result['score']??0,$result['intent']??'']);
            }
            $this->response->json(['success'=>true,'qualification'=>$result]);
        } catch (\Throwable $e) { $this->response->json(['success'=>false,'error'=>$e->getMessage()],500); }
    }

    public function getDMs(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $status  = $this->request->get('status','unread');
        $platform= $this->request->get('platform','all');
        $page    = max(1,(int)$this->request->get('page',1));
        $perPage = 20; $offset = ($page-1)*$perPage;
        $where = ['brand_id=?']; $params = [$brandId];
        if ($status !== 'all') { $where[] = 'status=?'; $params[] = $status; }
        if ($platform !== 'all') { $where[] = 'platform=?'; $params[] = $platform; }
        $wc = implode(' AND ',$where);
        $cnt = $this->db->prepare("SELECT COUNT(*) FROM direct_messages WHERE {$wc}");
        $cnt->execute($params); $total = (int)$cnt->fetchColumn();
        $s = $this->db->prepare("SELECT id,platform,sender_username,message_text,status,is_lead,sentiment,created_at FROM direct_messages WHERE {$wc} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
        $s->execute($params);
        $this->response->json(['success'=>true,'dms'=>$s->fetchAll(\PDO::FETCH_ASSOC),'total'=>$total]);
    }

    public function replyDM(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $dmId    = (int)$this->request->post('dm_id',0);
        $reply   = trim($this->request->post('reply_text',''));
        $useAI   = (bool)$this->request->post('use_ai',false);
        $stmt    = $this->db->prepare('SELECT * FROM direct_messages WHERE id=? AND brand_id=? LIMIT 1');
        $stmt->execute([$dmId,$brandId]);
        $dm = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$dm) { $this->response->json(['success'=>false,'error'=>'DM not found'],404); return; }
        if ($useAI) {
            try {
                $agent = new CommunityAgent($brandId);
                $res   = $agent->handleDM($dm['message_text'],$dm['platform'],$this->getBrandContext($brandId));
                $reply = $res['reply'] ?? $reply;
            } catch (\Throwable $e) { error_log('DM AI: '.$e->getMessage()); }
        }
        if (empty($reply)) { $this->response->json(['success'=>false,'error'=>'Reply text required'],400); return; }
        $this->db->prepare('INSERT INTO dm_replies (dm_id,brand_id,reply_text,replied_by,is_ai_generated,created_at) VALUES (?,?,?,?,?,NOW())')->execute([$dmId,$brandId,$reply,$user['id'],$useAI?1:0]);
        $this->db->prepare('UPDATE direct_messages SET status="replied",replied_at=NOW() WHERE id=?')->execute([$dmId]);
        $this->response->json(['success'=>true,'reply_text'=>$reply,'is_ai'=>$useAI]);
    }

    private function fetchCommentQueue(int $brandId, string $status, int $page, int $perPage, string $platform='all'): array
    {
        $where = ['brand_id=?']; $params = [$brandId];
        if ($status !== 'all') { $where[] = 'status=?'; $params[] = $status; }
        if ($platform !== 'all') { $where[] = 'platform=?'; $params[] = $platform; }
        $wc = implode(' AND ',$where); $offset = ($page-1)*$perPage;
        $cnt = $this->db->prepare("SELECT COUNT(*) FROM community_comments WHERE {$wc}");
        $cnt->execute($params); $total = (int)$cnt->fetchColumn();
        $s = $this->db->prepare("SELECT id,platform,post_id,commenter_username,comment_text,status,sentiment,is_spam,is_lead,created_at FROM community_comments WHERE {$wc} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
        $s->execute($params);
        return [$s->fetchAll(\PDO::FETCH_ASSOC),$total];
    }

    private function getComment(int $id, int $brandId): array|false
    {
        $s = $this->db->prepare('SELECT * FROM community_comments WHERE id=? AND brand_id=? LIMIT 1');
        $s->execute([$id,$brandId]);
        return $s->fetch(\PDO::FETCH_ASSOC);
    }

    private function getCommentStats(int $brandId): array
    {
        $s = $this->db->prepare('SELECT COUNT(*) AS total,SUM(status="pending") AS pending,SUM(status="replied") AS replied,SUM(status="spam") AS spam,SUM(is_lead=1) AS leads FROM community_comments WHERE brand_id=?');
        $s->execute([$brandId]);
        return $s->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function getDMStats(int $brandId): array
    {
        $s = $this->db->prepare('SELECT COUNT(*) AS total,SUM(status="unread") AS unread,SUM(status="replied") AS replied,SUM(is_lead=1) AS leads FROM direct_messages WHERE brand_id=?');
        $s->execute([$brandId]);
        return $s->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function getBrandVoice(int $brandId): string
    {
        $s = $this->db->prepare("SELECT field_value FROM brand_strategy WHERE brand_id=? AND field_name='brand_voice' LIMIT 1");
        $s->execute([$brandId]);
        $r = $s->fetch(\PDO::FETCH_ASSOC);
        return $r ? $r['field_value'] : 'professional and friendly';
    }

    private function getBrandContext(int $brandId): array
    {
        $s = $this->db->prepare('SELECT field_name,field_value FROM brand_strategy WHERE brand_id=?');
        $s->execute([$brandId]);
        $ctx = [];
        foreach ($s->fetchAll(\PDO::FETCH_ASSOC) as $r) $ctx[$r['field_name']] = $r['field_value'];
        return $ctx;
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
