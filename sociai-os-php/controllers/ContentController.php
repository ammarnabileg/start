<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../agents/CopywritingAgent.php';

class ContentController
{
    private \PDO $db;
    private Auth $auth;
    private Request $request;
    private Response $response;

    private const PLATFORMS = ['instagram','twitter','linkedin','facebook','tiktok','youtube','threads','snapchat'];
    private const STATUSES  = ['draft','pending_approval','approved','scheduled','published','rejected'];

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
        $filter  = $this->request->get('status','all');
        $platform= $this->request->get('platform','all');
        $page    = max(1,(int)$this->request->get('page',1));
        [$posts,$total] = $this->fetchPosts($brandId,$filter,$platform,$page,20);
        $this->response->view('content/index',['title'=>'Content - SociAI OS','posts'=>$posts,'total'=>$total,'page'=>$page,'perPage'=>20,'filter'=>$filter,'platform'=>$platform,'platforms'=>self::PLATFORMS,'statuses'=>self::STATUSES,'brandId'=>$brandId]);
    }

    public function create(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $this->response->view('content/create',['title'=>'Create Content - SociAI OS','platforms'=>self::PLATFORMS,'brandId'=>$brandId]);
    }

    public function generate(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $platform    = $this->request->post('platform','instagram');
        $topic       = trim($this->request->post('topic',''));
        $style       = $this->request->post('style','professional');
        $language    = $this->request->post('language','english');
        $contentType = $this->request->post('content_type','caption');
        if (empty($topic)) { $this->response->json(['success'=>false,'error'=>'Topic required'],400); return; }
        try {
            $agent = new CopywritingAgent($brandId);
            $ctx   = $this->getBrandContext($brandId);
            $result = match($contentType) {
                'caption'       => ['text' => $agent->generateCaption($platform,$topic,$style,$language,$ctx)],
                'linkedin_post' => ['text' => $agent->generateLinkedInPost($topic,$style,$ctx)],
                'thread'        => ['thread' => $agent->generateThread($topic,7,$style)],
                'script'        => ['text' => $agent->generateScript('short_video',60,'',$ctx)],
                'hooks'         => ['hooks' => $agent->generateHooks($topic,5,$style)],
                'cta'           => ['text' => $agent->generateCTA('engagement',$platform,$style)],
                'ad_copy'       => ['text' => $agent->generateAdCopy($topic,'general',$platform,$style)],
                'carousel'      => ['slides' => $agent->generateCarouselText($topic,5,$style)],
                default         => ['text' => $agent->generateCaption($platform,$topic,$style,$language,$ctx)],
            };
            $this->response->json(['success'=>true,'generated'=>$result]);
        } catch (\Throwable $e) {
            error_log('Content generate: '.$e->getMessage());
            $this->response->json(['success'=>false,'error'=>$e->getMessage()],500);
        }
    }

    public function store(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $data    = $this->validatePostData();
        if (isset($data['error'])) { $this->response->json(['success'=>false,'error'=>$data['error']],422); return; }
        $mediaUrls = $this->handleMediaUpload($brandId);
        $stmt = $this->db->prepare('INSERT INTO content_posts (brand_id,platform,content_text,content_type,hashtags,media_urls,status,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$brandId,$data['platform'],$data['content_text'],$data['content_type'],json_encode($data['hashtags']),json_encode($mediaUrls),$data['status'],$user['id']]);
        $this->response->json(['success'=>true,'post_id'=>(int)$this->db->lastInsertId(),'message'=>'Content saved.']);
    }

    public function edit(int $id): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $post    = $this->getPost($id,$brandId);
        if (!$post) { $this->response->view('errors/404',['title'=>'404']); return; }
        $post['media_urls'] = json_decode($post['media_urls']??'[]',true);
        $post['hashtags']   = json_decode($post['hashtags']??'[]',true);
        $this->response->view('content/edit',['title'=>'Edit Content - SociAI OS','post'=>$post,'platforms'=>self::PLATFORMS]);
    }

    public function update(int $id): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $post    = $this->getPost($id,$brandId);
        if (!$post) { $this->response->json(['success'=>false,'error'=>'Not found'],404); return; }
        if ($post['status']==='published') { $this->response->json(['success'=>false,'error'=>'Cannot edit published'],422); return; }
        $data = $this->validatePostData();
        if (isset($data['error'])) { $this->response->json(['success'=>false,'error'=>$data['error']],422); return; }
        $this->db->prepare('UPDATE content_posts SET platform=?,content_text=?,content_type=?,hashtags=?,status=?,updated_at=NOW() WHERE id=? AND brand_id=?')->execute([$data['platform'],$data['content_text'],$data['content_type'],json_encode($data['hashtags']),$data['status'],$id,$brandId]);
        $this->response->json(['success'=>true,'message'=>'Updated.']);
    }

    public function delete(int $id): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $post    = $this->getPost($id,$brandId);
        if (!$post) { $this->response->json(['success'=>false,'error'=>'Not found'],404); return; }
        if ($post['status']==='published') { $this->response->json(['success'=>false,'error'=>'Cannot delete published'],422); return; }
        $this->db->prepare('UPDATE content_posts SET status="deleted",deleted_at=NOW() WHERE id=? AND brand_id=?')->execute([$id,$brandId]);
        $this->response->json(['success'=>true,'message'=>'Deleted.']);
    }

    public function approve(int $id): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        if (!$this->userCanApprove($user)) { $this->response->json(['success'=>false,'error'=>'Insufficient permissions'],403); return; }
        $post = $this->getPost($id,$brandId);
        if (!$post||$post['status']!=='pending_approval') { $this->response->json(['success'=>false,'error'=>'Not pending approval'],404); return; }
        $this->db->prepare('UPDATE content_posts SET status="approved",approved_by=?,approved_at=NOW(),updated_at=NOW() WHERE id=? AND brand_id=?')->execute([$user['id'],$id,$brandId]);
        $this->response->json(['success'=>true,'message'=>'Approved.']);
    }

    public function reject(int $id): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        if (!$this->userCanApprove($user)) { $this->response->json(['success'=>false,'error'=>'Insufficient permissions'],403); return; }
        $reason = trim($this->request->post('reason',''));
        $post   = $this->getPost($id,$brandId);
        if (!$post) { $this->response->json(['success'=>false,'error'=>'Not found'],404); return; }
        $this->db->prepare('UPDATE content_posts SET status="rejected",rejection_reason=?,reviewed_by=?,updated_at=NOW() WHERE id=? AND brand_id=?')->execute([$reason,$user['id'],$id,$brandId]);
        $this->response->json(['success'=>true,'message'=>'Rejected.']);
    }

    public function schedule(int $id): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $at      = $this->request->post('scheduled_at','');
        $accs    = $this->request->post('platform_account_ids',[]);
        if (empty($at)||strtotime($at)<time()) { $this->response->json(['success'=>false,'error'=>'Invalid schedule time'],422); return; }
        $post = $this->getPost($id,$brandId);
        if (!$post||!in_array($post['status'],['approved','draft'],true)) { $this->response->json(['success'=>false,'error'=>'Not schedulable'],404); return; }
        $this->db->prepare('UPDATE content_posts SET status="scheduled",scheduled_at=?,platform_account_ids=?,updated_at=NOW() WHERE id=? AND brand_id=?')->execute([$at,json_encode((array)$accs),$id,$brandId]);
        $this->response->json(['success'=>true,'scheduled_at'=>$at,'message'=>'Scheduled.']);
    }

    public function calendar(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $month   = $this->request->get('month',date('Y-m'));
        [$year,$mon] = explode('-',$month.'-0');
        $start = sprintf('%04d-%02d-01',(int)$year,(int)$mon);
        $end   = date('Y-m-t',strtotime($start));
        $stmt  = $this->db->prepare('SELECT id,platform,content_type,content_text,status,scheduled_at,published_at FROM content_posts WHERE brand_id=? AND ((scheduled_at BETWEEN ? AND ?) OR (published_at BETWEEN ? AND ?)) AND status NOT IN ("deleted") ORDER BY COALESCE(scheduled_at,published_at) ASC');
        $stmt->execute([$brandId,$start,$end,$start,$end]);
        $this->response->view('content/calendar',['title'=>'Calendar - SociAI OS','posts'=>$stmt->fetchAll(\PDO::FETCH_ASSOC),'month'=>$month,'start'=>$start,'end'=>$end]);
    }

    public function bulkApprove(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        if (!$this->userCanApprove($user)) { $this->response->json(['success'=>false,'error'=>'Insufficient permissions'],403); return; }
        $ids = array_filter(array_map('intval',(array)$this->request->post('ids',[])));
        if (empty($ids)) { $this->response->json(['success'=>false,'error'=>'No IDs provided'],400); return; }
        $ph = implode(',',array_fill(0,count($ids),'?'));
        $stmt = $this->db->prepare("UPDATE content_posts SET status='approved',approved_by=?,approved_at=NOW(),updated_at=NOW() WHERE id IN ({$ph}) AND brand_id=? AND status='pending_approval'");
        $stmt->execute(array_merge([$user['id']],$ids,[$brandId]));
        $this->response->json(['success'=>true,'approved_count'=>$stmt->rowCount()]);
    }

    private function fetchPosts(int $brandId, string $filter, string $platform, int $page, int $perPage): array
    {
        $where = ['cp.brand_id=?',"cp.status!='deleted'"]; $params = [$brandId];
        if ($filter!=='all'&&in_array($filter,self::STATUSES,true)) { $where[] = 'cp.status=?'; $params[] = $filter; }
        if ($platform!=='all'&&in_array($platform,self::PLATFORMS,true)) { $where[] = 'cp.platform=?'; $params[] = $platform; }
        $wc = implode(' AND ',$where); $offset = ($page-1)*$perPage;
        $cnt = $this->db->prepare("SELECT COUNT(*) FROM content_posts cp WHERE {$wc}");
        $cnt->execute($params); $total = (int)$cnt->fetchColumn();
        $s = $this->db->prepare("SELECT cp.id,cp.platform,cp.content_type,cp.content_text,cp.status,cp.scheduled_at,cp.published_at,cp.created_at,cp.media_urls,cp.hashtags,COALESCE(pm.impressions,0) AS impressions,COALESCE(pm.likes,0) AS likes,COALESCE(pm.engagement_rate,0) AS engagement_rate,u.name AS created_by_name FROM content_posts cp LEFT JOIN post_metrics pm ON pm.content_post_id=cp.id LEFT JOIN users u ON u.id=cp.created_by WHERE {$wc} ORDER BY cp.created_at DESC LIMIT {$perPage} OFFSET {$offset}");
        $s->execute($params);
        $posts = $s->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($posts as &$p) { $p['media_urls']=json_decode($p['media_urls']??'[]',true); $p['hashtags']=json_decode($p['hashtags']??'[]',true); }
        unset($p);
        return [$posts,$total];
    }

    private function validatePostData(): array
    {
        $platform = $this->request->post('platform','');
        $text     = trim($this->request->post('content_text',''));
        $type     = $this->request->post('content_type','post');
        $status   = $this->request->post('status','draft');
        $htags    = $this->request->post('hashtags','');
        if (!in_array($platform,self::PLATFORMS,true)) return ['error'=>'Invalid platform'];
        if (strlen($text)<2) return ['error'=>'Content text required'];
        if (strlen($text)>5000) return ['error'=>'Text too long (max 5000)'];
        if (!in_array($status,['draft','pending_approval'],true)) $status = 'draft';
        $hashtags = [];
        if (!empty($htags)) { preg_match_all('/#?\w+/',$htags,$m); $hashtags=array_map(fn($h)=>ltrim($h,'#'),$m[0]); }
        return ['platform'=>$platform,'content_text'=>$text,'content_type'=>$type,'status'=>$status,'hashtags'=>$hashtags];
    }

    private function handleMediaUpload(int $brandId): array
    {
        $urls = [];
        if (empty($_FILES['media'])) return $urls;
        $dir = __DIR__.'/../storage/uploads/media/'.$brandId.'/';
        if (!is_dir($dir)) mkdir($dir,0755,true);
        $files = $_FILES['media'];
        $allowed = ['jpg','jpeg','png','gif','webp','mp4','mov'];
        $list = is_array($files['name']) ? $files['name'] : [$files['name']];
        foreach (array_keys($list) as $i) {
            $name = is_array($files['name'])?$files['name'][$i]:$files['name'];
            $tmp  = is_array($files['tmp_name'])?$files['tmp_name'][$i]:$files['tmp_name'];
            $size = is_array($files['size'])?$files['size'][$i]:$files['size'];
            $err  = is_array($files['error'])?$files['error'][$i]:$files['error'];
            if ($err!==UPLOAD_ERR_OK||$size>50*1024*1024) continue;
            $ext = strtolower(pathinfo($name,PATHINFO_EXTENSION));
            if (!in_array($ext,$allowed,true)) continue;
            $fname = uniqid('media_',true).'.'.$ext;
            if (move_uploaded_file($tmp,$dir.$fname)) $urls[] = '/storage/uploads/media/'.$brandId.'/'.$fname;
        }
        return $urls;
    }

    private function getPost(int $id, int $brandId): array|false
    {
        $s = $this->db->prepare('SELECT * FROM content_posts WHERE id=? AND brand_id=? AND status!="deleted" LIMIT 1');
        $s->execute([$id,$brandId]);
        return $s->fetch(\PDO::FETCH_ASSOC);
    }

    private function userCanApprove(array $user): bool
    {
        return in_array($user['role'],['owner','admin','editor'],true);
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
