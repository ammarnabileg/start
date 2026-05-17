<?php
class Analytics
{
    private static function db(): Database { return Database::getInstance(); }

    public static function getDashboard(string $brandId, string $period = '30d'): array
    {
        $days = (int)filter_var($period, FILTER_SANITIZE_NUMBER_INT) ?: 30;
        $db = self::db();
        $since = date('Y-m-d', strtotime("-{$days} days"));
        $reach      = $db->fetchOne("SELECT SUM(reach) as total FROM post_analytics pa JOIN scheduled_posts sp ON sp.id = pa.scheduled_post_id JOIN content_pieces cp ON cp.id = sp.content_id WHERE cp.brand_id = ? AND pa.recorded_at >= ?", [$brandId,$since]);
        $engagement = $db->fetchOne("SELECT AVG(engagement_rate) as avg FROM post_analytics pa JOIN scheduled_posts sp ON sp.id = pa.scheduled_post_id JOIN content_pieces cp ON cp.id = sp.content_id WHERE cp.brand_id = ? AND pa.recorded_at >= ?", [$brandId,$since]);
        $viralScore = $db->fetchOne("SELECT AVG(viral_score) as avg FROM post_analytics pa JOIN scheduled_posts sp ON sp.id = pa.scheduled_post_id JOIN content_pieces cp ON cp.id = sp.content_id WHERE cp.brand_id = ?", [$brandId]);
        $topPosts   = self::getTopPosts($brandId, 5);
        return [
            'period'           => $period,
            'total_reach'      => (int)($reach['total'] ?? 0),
            'avg_engagement'   => round((float)($engagement['avg'] ?? 0), 4),
            'avg_viral_score'  => round((float)($viralScore['avg'] ?? 0), 2),
            'top_posts'        => $topPosts,
        ];
    }

    public static function getTopPosts(string $brandId, int $limit = 10): array
    {
        return self::db()->fetchAll(
            "SELECT cp.title, cp.content_type, sp.platform, pa.reach, pa.engagement_rate, pa.viral_score FROM post_analytics pa JOIN scheduled_posts sp ON sp.id = pa.scheduled_post_id JOIN content_pieces cp ON cp.id = sp.content_id WHERE cp.brand_id = ? ORDER BY pa.viral_score DESC LIMIT ?",
            [$brandId, $limit]
        );
    }

    public static function getPlatformBreakdown(string $brandId): array
    {
        return self::db()->fetchAll(
            "SELECT sp.platform, COUNT(*) as posts, AVG(pa.engagement_rate) as avg_eng, SUM(pa.reach) as total_reach FROM post_analytics pa JOIN scheduled_posts sp ON sp.id = pa.scheduled_post_id JOIN content_pieces cp ON cp.id = sp.content_id WHERE cp.brand_id = ? GROUP BY sp.platform ORDER BY avg_eng DESC",
            [$brandId]
        );
    }
}
