<?php

declare(strict_types=1);

/**
 * REST API: /api/analytics
 *
 * Routes:
 *   GET /api/analytics/dashboard      → full analytics dashboard data
 *   GET /api/analytics/platforms      → per-platform breakdown
 *   GET /api/analytics/viral-scores   → viral score data
 *   GET /api/analytics/sentiment      → sentiment analysis
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../agents/AnalyticsAgent.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─── Auth ─────────────────────────────────────────────────────────────────────

function getAnalyticsApiUser(\PDO $db): ?array
{
    $auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        $token = trim($m[1]);
    }
    if (empty($token)) return null;

    $hash = hash('sha256', $token);
    $stmt = $db->prepare(
        'SELECT at.user_id, at.brand_id, u.role
         FROM api_tokens at
         INNER JOIN users u ON u.id = at.user_id
         WHERE at.token_hash = ? AND at.expires_at > NOW() AND at.is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($row) {
        $db->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?')->execute([$hash]);
    }
    return $row ?: null;
}

$db       = Database::getInstance();
$authUser = getAnalyticsApiUser($db);

if (!$authUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$brandId = (int) $authUser['brand_id'];
$uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri     = rtrim(preg_replace('#/+#', '/', $uri), '/');
$period  = $_GET['period'] ?? '30d';

// ─── Routes ──────────────────────────────────────────────────────────────────

try {
    $agent = new AnalyticsAgent($brandId);

    // GET /api/analytics/dashboard
    if (preg_match('#/api/analytics/dashboard#', $uri)) {
        $report      = $agent->generateReport($brandId, $period);
        $viralMetrics = $agent->calculateViralScore(getAggregateMetrics($db, $brandId, $period));
        $weaknesses  = $agent->identifyWeaknesses($brandId);
        $recs        = $agent->generateRecommendations($report);

        echo json_encode([
            'period'          => $period,
            'report'          => $report,
            'viral_score'     => $viralMetrics,
            'weaknesses'      => $weaknesses,
            'recommendations' => $recs,
            'generated_at'    => date('Y-m-d H:i:s'),
        ]);
        exit;
    }

    // GET /api/analytics/platforms
    if (preg_match('#/api/analytics/platforms#', $uri)) {
        $days = periodToDays($period);

        $stmt = $db->prepare(
            'SELECT cp.platform,
                    COUNT(cp.id) AS post_count,
                    COALESCE(SUM(pm.impressions),0) AS total_reach,
                    COALESCE(SUM(pm.likes),0) AS total_likes,
                    COALESCE(SUM(pm.comments),0) AS total_comments,
                    COALESCE(SUM(pm.shares),0) AS total_shares,
                    COALESCE(SUM(pm.saves),0) AS total_saves,
                    COALESCE(AVG(pm.engagement_rate),0) AS avg_engagement_rate,
                    COALESCE(SUM(pm.link_clicks),0) AS total_link_clicks
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id = cp.id
             WHERE cp.brand_id = ? AND cp.status = "published"
               AND cp.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY cp.platform
             ORDER BY total_reach DESC'
        );
        $stmt->execute([$brandId, $days]);
        $platforms = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Follower breakdown per platform
        $followerStmt = $db->prepare(
            'SELECT platform, SUM(follower_count) AS followers
             FROM platform_accounts WHERE brand_id = ? AND is_active = 1
             GROUP BY platform'
        );
        $followerStmt->execute([$brandId]);
        $followers = [];
        foreach ($followerStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $followers[$r['platform']] = (int) $r['followers'];
        }

        foreach ($platforms as &$p) {
            $p['followers'] = $followers[$p['platform']] ?? 0;
        }
        unset($p);

        echo json_encode(['platforms' => $platforms, 'period' => $period]);
        exit;
    }

    // GET /api/analytics/viral-scores
    if (preg_match('#/api/analytics/viral-scores#', $uri)) {
        $days = periodToDays($period);

        $stmt = $db->prepare(
            'SELECT vs.overall_score, vs.reach_score, vs.engagement_score, vs.shareability_score,
                    vs.sentiment_score, vs.timing_score, vs.hashtag_score, vs.visual_score,
                    vs.hook_score, vs.cta_score, vs.created_at,
                    cp.platform, cp.content_text, cp.published_at
             FROM viral_scores vs
             LEFT JOIN content_posts cp ON cp.id = vs.content_post_id
             WHERE vs.brand_id = ? AND vs.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY vs.overall_score DESC
             LIMIT 50'
        );
        $stmt->execute([$brandId, $days]);
        $scores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Trend data
        $trendStmt = $db->prepare(
            'SELECT DATE(created_at) AS day, AVG(overall_score) AS avg_score
             FROM viral_scores
             WHERE brand_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC'
        );
        $trendStmt->execute([$brandId, $days]);
        $trend = $trendStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Current brand viral score
        $currentScore = !empty($scores) ? $scores[0]['overall_score'] : 0;

        echo json_encode([
            'current_score' => round((float) $currentScore, 2),
            'scores'        => $scores,
            'trend'         => $trend,
            'period'        => $period,
        ]);
        exit;
    }

    // GET /api/analytics/sentiment
    if (preg_match('#/api/analytics/sentiment#', $uri)) {
        $sentiment = $agent->analyzeSentiment($brandId, $period);

        // Daily sentiment trend
        $days = periodToDays($period);
        $trendStmt = $db->prepare(
            'SELECT DATE(created_at) AS day, sentiment, COUNT(*) AS count
             FROM community_comments
             WHERE brand_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
               AND sentiment IS NOT NULL
             GROUP BY DATE(created_at), sentiment
             ORDER BY day ASC'
        );
        $trendStmt->execute([$brandId, $days]);
        $dailyTrend = $trendStmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'sentiment'    => $sentiment,
            'daily_trend'  => $dailyTrend,
            'period'       => $period,
        ]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);

} catch (\Throwable $e) {
    error_log('API /analytics error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getAggregateMetrics(\PDO $db, int $brandId, string $period): array
{
    $days = periodToDays($period);
    $stmt = $db->prepare(
        'SELECT COALESCE(SUM(pm.impressions),0) AS impressions,
                COALESCE(SUM(pm.likes),0) AS likes,
                COALESCE(SUM(pm.comments),0) AS comments,
                COALESCE(SUM(pm.shares),0) AS shares,
                COALESCE(AVG(pm.engagement_rate),0) AS engagement_rate
         FROM content_posts cp
         LEFT JOIN post_metrics pm ON pm.content_post_id = cp.id
         WHERE cp.brand_id = ? AND cp.status = "published"
           AND cp.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'
    );
    $stmt->execute([$brandId, $days]);
    return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
}

function periodToDays(string $period): int
{
    return match ($period) {
        '7d' => 7, '14d' => 14, '30d' => 30, '90d' => 90, '180d' => 180, '365d' => 365, default => 30,
    };
}
