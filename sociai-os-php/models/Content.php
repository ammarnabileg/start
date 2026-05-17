<?php
declare(strict_types=1);
namespace SociAI\Models;
class Content
{
    private static function db(): Database { return Database::getInstance(); }

    public static function find(string $id): ?array
    {
        return self::db()->fetchOne("SELECT * FROM content_pieces WHERE id = ? AND deleted_at IS NULL", [$id]);
    }

    public static function getByBrand(string $brandId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM content_pieces WHERE brand_id = ? AND deleted_at IS NULL";
        $params = [$brandId];
        if (!empty($filters['status'])) { $sql .= " AND approval_status = ?"; $params[] = $filters['status']; }
        if (!empty($filters['platform'])) { $sql .= " AND JSON_CONTAINS(platform_variants, ?, '$')"; $params[] = '"'.$filters['platform'].'"'; }
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit; $params[] = $offset;
        return self::db()->fetchAll($sql, $params);
    }

    public static function create(array $data): string
    {
        $id = Security::generateUUID();
        self::db()->insert('content_pieces', array_merge($data, ['id'=>$id,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]));
        return $id;
    }

    public static function approve(string $id, string $approverId): bool
    {
        return (bool)self::db()->update('content_pieces', ['approval_status'=>'approved','updated_at'=>date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public static function reject(string $id, string $reason): bool
    {
        return (bool)self::db()->update('content_pieces', ['approval_status'=>'rejected','updated_at'=>date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public static function getCalendar(string $brandId, string $startDate, string $endDate): array
    {
        return self::db()->fetchAll(
            "SELECT cp.*, sp.scheduled_at, sp.platform, sp.status as publish_status FROM content_pieces cp JOIN scheduled_posts sp ON sp.content_id = cp.id WHERE cp.brand_id = ? AND sp.scheduled_at BETWEEN ? AND ? ORDER BY sp.scheduled_at ASC",
            [$brandId, $startDate, $endDate]
        );
    }

    public static function getStats(string $brandId): array
    {
        $db = self::db();
        $statuses = ['draft','pending_review','approved','published'];
        $stats = [];
        foreach ($statuses as $s) {
            $stats[$s] = $db->fetchOne("SELECT COUNT(*) as c FROM content_pieces WHERE brand_id = ? AND approval_status = ? AND deleted_at IS NULL", [$brandId,$s])['c'] ?? 0;
        }
        $stats['avg_viral_score'] = $db->fetchOne("SELECT AVG(viral_score) as avg FROM content_pieces WHERE brand_id = ? AND viral_score IS NOT NULL", [$brandId])['avg'] ?? 0;
        return $stats;
    }
}
