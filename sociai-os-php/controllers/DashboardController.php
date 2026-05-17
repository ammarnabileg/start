<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class DashboardController
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

        $metrics        = $this->loadMetrics($brandId);
        $agentStatus    = $this->loadAgentStatuses($brandId);
        $recentPosts    = $this->loadRecentPosts($brandId);
        $platformHealth = $this->loadPlatformHealth($brandId);
        $trendingAlerts = $this->loadTrendingAlerts($brandId);
        $viralScore     = $this->loadViralScore($brandId);

        $this->response->view('dashboard/index', [
            'title'          => 'Dashboard - SociAI OS',
            'user'           => $user,
            'brandId'        => $brandId,
            'metrics'        => $metrics,
            'agentStatus'    => $agentStatus,
            'recentPosts'    => $recentPosts,
            'platformHealth' => $platformHealth,
            'trendingAlerts' => $trendingAlerts,
            'viralScore'     => $viralScore,
        ]);
    }

    private function loadMetrics(int $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(pm.impressions),0) AS total_reach,
                    COALESCE(SUM(pm.engagement_count),0) AS total_engagement,
                    COALESCE(AVG(pm.engagement_rate),0) AS avg_engagement_rate
             FROM post_metrics pm
             INNER JOIN content_posts cp ON pm.content_post_id=cp.id
             WHERE cp.brand_id=? AND pm.recorded_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)'
        );
        $stmt->execute([$brandId]);
        $agg = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $fStmt = $this->db->prepare('SELECT COALESCE(SUM(follower_count),0) AS total_followers FROM platform_accounts WHERE brand_id=? AND is_active=1');
        $fStmt->execute([$brandId]);
        $fData = $fStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $gStmt = $this->db->prepare(
            'SELECT (SELECT COALESCE(SUM(follower_count),0) FROM follower_snapshots WHERE brand_id=? AND snapshot_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)) AS curr,
                    (SELECT COALESCE(SUM(follower_count),0) FROM follower_snapshots WHERE brand_id=? AND snapshot_date<DATE_SUB(CURDATE(),INTERVAL 30 DAY) AND snapshot_date>=DATE_SUB(CURDATE(),INTERVAL 60 DAY)) AS prev'
        );
        $gStmt->execute([$brandId, $brandId]);
        $gData = $gStmt->fetch(\PDO::FETCH_ASSOC) ?: ['curr'=>0,'prev'=>0];
        $growthPct = $gData['prev'] > 0 ? round((($gData['curr']-$gData['prev'])/$gData['prev'])*100,2) : 0.0;

        $vsStmt = $this->db->prepare('SELECT overall_score FROM viral_scores WHERE brand_id=? ORDER BY created_at DESC LIMIT 1');
        $vsStmt->execute([$brandId]);
        $vsRow = $vsStmt->fetch(\PDO::FETCH_ASSOC);

        $pStmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM content_posts WHERE brand_id=? AND status="published" AND published_at>=DATE_FORMAT(NOW(),"%Y-%m-01")');
        $pStmt->execute([$brandId]);
        $pData = $pStmt->fetch(\PDO::FETCH_ASSOC);

        $qStmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM content_posts WHERE brand_id=? AND status="scheduled" AND scheduled_at>NOW()');
        $qStmt->execute([$brandId]);
        $qData = $qStmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'reach'              => (int)($agg['total_reach'] ?? 0),
            'engagement'         => (int)($agg['total_engagement'] ?? 0),
            'avg_engagement_rate'=> round((float)($agg['avg_engagement_rate'] ?? 0),2),
            'total_followers'    => (int)($fData['total_followers'] ?? 0),
            'follower_growth_pct'=> $growthPct,
            'viral_score'        => $vsRow ? round((float)$vsRow['overall_score'],1) : 0.0,
            'posts_published'    => (int)($pData['cnt'] ?? 0),
            'posts_scheduled'    => (int)($qData['cnt'] ?? 0),
        ];
    }

    private function loadAgentStatuses(int $brandId): array
    {
        $stmt = $this->db->prepare('SELECT agent_type,status,last_run_at,task_count,error_count FROM agent_status WHERE brand_id=? ORDER BY agent_type');
        $stmt->execute([$brandId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $defaults = [
            ['agent_type'=>'copywriting','status'=>'idle','last_run_at'=>null,'task_count'=>0,'error_count'=>0],
            ['agent_type'=>'strategy','status'=>'idle','last_run_at'=>null,'task_count'=>0,'error_count'=>0],
            ['agent_type'=>'analytics','status'=>'idle','last_run_at'=>null,'task_count'=>0,'error_count'=>0],
            ['agent_type'=>'community','status'=>'idle','last_run_at'=>null,'task_count'=>0,'error_count'=>0],
            ['agent_type'=>'research','status'=>'idle','last_run_at'=>null,'task_count'=>0,'error_count'=>0],
            ['agent_type'=>'publishing','status'=>'idle','last_run_at'=>null,'task_count'=>0,'error_count'=>0],
        ];
        if (empty($rows)) return $defaults;
        $indexed = [];
        foreach ($rows as $r) $indexed[$r['agent_type']] = $r;
        $result = [];
        foreach ($defaults as $d) $result[] = $indexed[$d['agent_type']] ?? $d;
        return $result;
    }

    private function loadRecentPosts(int $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT cp.id,cp.platform,cp.content_text,cp.status,cp.published_at,cp.scheduled_at,cp.media_urls,cp.hashtags,
                    COALESCE(pm.impressions,0) AS impressions,COALESCE(pm.likes,0) AS likes,COALESCE(pm.comments,0) AS comments,COALESCE(pm.shares,0) AS shares,COALESCE(pm.engagement_rate,0) AS engagement_rate
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id=cp.id
             WHERE cp.brand_id=? AND cp.status IN ("published","scheduled")
             ORDER BY COALESCE(cp.published_at,cp.scheduled_at) DESC LIMIT 10'
        );
        $stmt->execute([$brandId]);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($posts as &$p) { $p['media_urls']=json_decode($p['media_urls']??'[]',true); $p['hashtags']=json_decode($p['hashtags']??'[]',true); }
        unset($p);
        return $posts;
    }

    private function loadPlatformHealth(int $brandId): array
    {
        $stmt = $this->db->prepare('SELECT pa.platform,pa.username,pa.is_active,pa.token_expires_at,pa.follower_count,pa.last_sync_at,pa.sync_errors FROM platform_accounts pa WHERE pa.brand_id=? ORDER BY pa.platform');
        $stmt->execute([$brandId]);
        $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($accounts as &$acc) {
            $acc['token_status'] = 'valid';
            if ($acc['token_expires_at']) {
                $exp = strtotime($acc['token_expires_at']);
                if ($exp < time()) $acc['token_status'] = 'expired';
                elseif ($exp < time()+86400*3) $acc['token_status'] = 'expiring_soon';
            }
            $acc['health'] = ($acc['is_active'] && $acc['token_status']=== 'valid' && (int)$acc['sync_errors']=== 0) ? 'healthy' : 'warning';
        }
        unset($acc);
        return $accounts;
    }

    private function loadTrendingAlerts(int $brandId): array
    {
        $stmt = $this->db->prepare('SELECT id,alert_type,message,platform,severity,is_read,created_at FROM trend_alerts WHERE brand_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR) ORDER BY severity DESC,created_at DESC LIMIT 5');
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function loadViralScore(int $brandId): array
    {
        $stmt = $this->db->prepare('SELECT overall_score,reach_score,engagement_score,shareability_score,sentiment_score,timing_score,hashtag_score,visual_score,hook_score,cta_score,created_at FROM viral_scores WHERE brand_id=? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$brandId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: ['overall_score'=>0,'reach_score'=>0,'engagement_score'=>0,'shareability_score'=>0,'sentiment_score'=>0,'timing_score'=>0,'hashtag_score'=>0,'visual_score'=>0,'hook_score'=>0,'cta_score'=>0,'created_at'=>null];
    }

    private function getActiveBrandId(int $userId): int
    {
        if (!empty($_SESSION['active_brand_id'])) return (int)$_SESSION['active_brand_id'];
        $stmt = $this->db->prepare('SELECT b.id FROM brands b INNER JOIN brand_users bu ON bu.brand_id=b.id WHERE bu.user_id=? ORDER BY bu.created_at ASC LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) { $_SESSION['active_brand_id'] = (int)$row['id']; return (int)$row['id']; }
        return 0;
    }
}
