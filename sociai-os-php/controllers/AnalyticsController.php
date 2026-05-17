<?php
declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

require_once __DIR__ . '/../agents/AnalyticsAgent.php';

class AnalyticsController
{
    private Database $db;
    private Request $request;

    public function __construct()
    {
        $this->db      = Database::getInstance();
        $this->request = new Request();
    }

    public function index(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $period  = $this->request->get('period', '30d');

        Response::view('analytics/index', [
            'title'   => 'Analytics - SociAI OS',
            'period'  => $period,
            'brandId' => $brandId,
            'csrf'    => Auth::csrfToken(),
        ]);
    }

    public function platformBreakdown(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $period  = $this->request->get('period', '30d');
        $days    = $this->periodToDays($period);

        $stmt = $this->db->prepare(
            'SELECT pa.platform,
                    COUNT(sp.id) AS post_count,
                    COALESCE(SUM(pa2.impressions),0) AS total_reach,
                    COALESCE(SUM(pa2.likes),0) AS total_likes,
                    COALESCE(SUM(pa2.comments),0) AS total_comments,
                    COALESCE(SUM(pa2.shares),0) AS total_shares,
                    COALESCE(AVG(pa2.engagement_rate),0) AS avg_engagement_rate
             FROM platform_accounts pa
             LEFT JOIN scheduled_posts sp ON sp.platform_account_id = pa.id
                 AND sp.status = "published"
                 AND sp.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             LEFT JOIN post_analytics pa2 ON pa2.scheduled_post_id = sp.id
             WHERE pa.brand_id = ? AND pa.is_active = 1
             GROUP BY pa.platform
             ORDER BY total_reach DESC'
        );
        $stmt->execute([$days, $brandId]);
        Response::json(['success' => true, 'breakdown' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'period' => $period]);
    }

    public function topPosts(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $period  = $this->request->get('period', '30d');
        $days    = $this->periodToDays($period);

        $stmt = $this->db->prepare(
            'SELECT cp.id, cp.topic, cp.body_text, cp.media_urls, cp.created_at,
                    cp.viral_score
             FROM content_pieces cp
             WHERE cp.brand_id = ?
               AND cp.approval_status = "published"
               AND cp.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY cp.viral_score DESC LIMIT 10'
        );
        $stmt->execute([$brandId, $days]);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($posts as &$p) {
            $p['media_urls'] = json_decode($p['media_urls'] ?? '[]', true);
        }
        unset($p);
        Response::json(['success' => true, 'posts' => $posts]);
    }

    public function exportReport(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $period  = $this->request->get('period', '30d');
        $format  = $this->request->get('format', 'json');

        $report = $this->getAggregateMetrics($brandId, $period);

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="analytics-' . $period . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Metric', 'Value']);
            foreach ($report as $k => $v) {
                fputcsv($out, [$k, (string)$v]);
            }
            fclose($out);
            exit;
        }
        Response::json(['success' => true, 'report' => $report, 'generated_at' => date('Y-m-d H:i:s')]);
    }

    private function getAggregateMetrics(string $brandId, string $period): array
    {
        $days = $this->periodToDays($period);
        $stmt = $this->db->prepare(
            'SELECT COUNT(cp.id) AS post_count,
                    COALESCE(AVG(cp.viral_score),0) AS avg_viral_score
             FROM content_pieces cp
             WHERE cp.brand_id = ?
               AND cp.approval_status = "published"
               AND cp.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([$brandId, $days]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function periodToDays(string $p): int
    {
        return match($p) {
            '7d'   => 7,
            '14d'  => 14,
            '30d'  => 30,
            '90d'  => 90,
            '180d' => 180,
            '365d' => 365,
            default => 30,
        };
    }

    private function getActiveBrandId(string $userId): string
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (string)$_SESSION['active_brand_id'];
        }
        $stmt = $this->db->prepare(
            'SELECT b.id FROM brands b
             INNER JOIN team_members tm ON tm.brand_id = b.id
             WHERE tm.user_id = ?
             ORDER BY tm.created_at ASC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['active_brand_id'] = $row['id'];
            return (string)$row['id'];
        }
        return '';
    }
}
