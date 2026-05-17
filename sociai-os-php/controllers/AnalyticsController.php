<?php
declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

require_once __DIR__ . '/../agents/AnalyticsAgent.php';

class AnalyticsController
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
        $period  = $this->request->get('period', '30d');

        $agent    = new AnalyticsAgent($brandId);
        $report   = $agent->generateReport($brandId, $period);
        $viral    = $agent->calculateViralScore($this->getAggregateMetrics($brandId, $period));
        $recs     = $agent->generateRecommendations($report);
        $weakness = $agent->identifyWeaknesses($brandId);

        $this->response->view('analytics/index', [
            'title'           => 'Analytics - SociAI OS',
            'report'          => $report,
            'viralScore'      => $viral,
            'recommendations' => $recs,
            'weaknesses'      => $weakness,
            'period'          => $period,
            'brandId'         => $brandId,
        ]);
    }

    public function platformBreakdown(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $period  = $this->request->get('period', '30d');
        $days    = $this->periodToDays($period);

        $stmt = $this->db->prepare(
            'SELECT cp.platform,COUNT(cp.id) AS post_count,
                    COALESCE(SUM(pm.impressions),0) AS total_reach,
                    COALESCE(SUM(pm.likes),0) AS total_likes,
                    COALESCE(SUM(pm.comments),0) AS total_comments,
                    COALESCE(SUM(pm.shares),0) AS total_shares,
                    COALESCE(AVG(pm.engagement_rate),0) AS avg_engagement_rate,
                    COALESCE(SUM(pm.link_clicks),0) AS total_link_clicks
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id=cp.id
             WHERE cp.brand_id=? AND cp.status="published" AND cp.published_at>=DATE_SUB(NOW(),INTERVAL ? DAY)
             GROUP BY cp.platform ORDER BY total_reach DESC'
        );
        $stmt->execute([$brandId, $days]);
        $this->response->json(['success' => true, 'breakdown' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'period' => $period]);
    }

    public function topPosts(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $period  = $this->request->get('period', '30d');
        $metric  = $this->request->get('metric', 'engagement_rate');
        $days    = $this->periodToDays($period);
        $allowed = ['impressions','likes','comments','shares','engagement_rate','saves','link_clicks'];
        if (!in_array($metric, $allowed, true)) $metric = 'engagement_rate';

        $stmt = $this->db->prepare(
            "SELECT cp.id,cp.platform,cp.content_text,cp.media_urls,cp.published_at,
                    pm.impressions,pm.likes,pm.comments,pm.shares,pm.engagement_rate,pm.saves,pm.link_clicks,
                    vs.overall_score AS viral_score
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id=cp.id
             LEFT JOIN viral_scores vs ON vs.content_post_id=cp.id
             WHERE cp.brand_id=? AND cp.status='published' AND cp.published_at>=DATE_SUB(NOW(),INTERVAL ? DAY)
             ORDER BY pm.{$metric} DESC LIMIT 10"
        );
        $stmt->execute([$brandId, $days]);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($posts as &$p) { $p['media_urls'] = json_decode($p['media_urls'] ?? '[]', true); }
        unset($p);
        $this->response->json(['success' => true, 'posts' => $posts, 'metric' => $metric]);
    }

    public function viralScores(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $period  = $this->request->get('period', '30d');
        $days    = $this->periodToDays($period);

        $stmt = $this->db->prepare(
            'SELECT vs.*,cp.platform,cp.content_text,cp.published_at
             FROM viral_scores vs
             INNER JOIN content_posts cp ON cp.id=vs.content_post_id
             WHERE vs.brand_id=? AND vs.created_at>=DATE_SUB(NOW(),INTERVAL ? DAY)
             ORDER BY vs.overall_score DESC LIMIT 20'
        );
        $stmt->execute([$brandId, $days]);
        $scores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $tStmt = $this->db->prepare('SELECT DATE(created_at) AS day,AVG(overall_score) AS avg_score FROM viral_scores WHERE brand_id=? AND content_post_id IS NULL AND created_at>=DATE_SUB(NOW(),INTERVAL ? DAY) GROUP BY DATE(created_at) ORDER BY day ASC');
        $tStmt->execute([$brandId, $days]);
        $this->response->json(['success' => true, 'scores' => $scores, 'trend' => $tStmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    public function sentiment(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $period  = $this->request->get('period', '30d');
        $agent   = new AnalyticsAgent($brandId);
        $this->response->json(['success' => true, 'sentiment' => $agent->analyzeSentiment($brandId, $period)]);
    }

    public function competitors(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $handles = array_filter(array_map('trim', explode(',', $this->request->get('handles', ''))));
        $platform= $this->request->get('platform', 'instagram');
        if (empty($handles)) { $this->response->json(['success'=>false,'error'=>'No handles provided'],400); return; }
        $agent   = new AnalyticsAgent($brandId);
        $this->response->json(['success' => true, 'benchmark' => $agent->benchmarkCompetitors($handles, $platform)]);
    }

    public function growthPrediction(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $content = $this->request->post('content', '');
        $platform= $this->request->post('platform', 'instagram');
        $agent   = new AnalyticsAgent($brandId);
        $this->response->json(['success' => true, 'prediction' => $agent->predictPerformance(['content'=>$content,'platform'=>$platform], $platform)]);
    }

    public function exportReport(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $period  = $this->request->get('period', '30d');
        $format  = $this->request->get('format', 'json');
        $agent   = new AnalyticsAgent($brandId);
        $report  = $agent->generateReport($brandId, $period);

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="analytics-'.$period.'.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Metric','Value']);
            array_walk_recursive($report, function($v,$k) use ($out) { fputcsv($out, [$k,(string)$v]); });
            fclose($out); exit;
        }
        $this->response->json(['success'=>true,'report'=>$report,'generated_at'=>date('Y-m-d H:i:s')]);
    }

    private function getAggregateMetrics(int $brandId, string $period): array
    {
        $days = $this->periodToDays($period);
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(pm.impressions),0) AS impressions,COALESCE(SUM(pm.likes),0) AS likes,COALESCE(SUM(pm.comments),0) AS comments,COALESCE(SUM(pm.shares),0) AS shares,COALESCE(AVG(pm.engagement_rate),0) AS engagement_rate,COUNT(cp.id) AS post_count FROM content_posts cp LEFT JOIN post_metrics pm ON pm.content_post_id=cp.id WHERE cp.brand_id=? AND cp.status="published" AND cp.published_at>=DATE_SUB(NOW(),INTERVAL ? DAY)');
        $stmt->execute([$brandId,$days]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function periodToDays(string $p): int
    {
        return match($p) { '7d'=>7,'14d'=>14,'30d'=>30,'90d'=>90,'180d'=>180,'365d'=>365,default=>30 };
    }

    private function getActiveBrandId(int $userId): int
    {
        if (!empty($_SESSION['active_brand_id'])) return (int)$_SESSION['active_brand_id'];
        $stmt = $this->db->prepare('SELECT b.id FROM brands b INNER JOIN brand_users bu ON bu.brand_id=b.id WHERE bu.user_id=? ORDER BY bu.created_at ASC LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : 0;
    }
}
