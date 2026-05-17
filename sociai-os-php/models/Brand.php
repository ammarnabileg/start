<?php
declare(strict_types=1);
namespace SociAI\Models;
class Brand
{
    private static function db(): Database { return Database::getInstance(); }

    public static function find(string $id): ?array
    {
        return self::db()->fetchOne("SELECT * FROM brands WHERE id = ? AND deleted_at IS NULL", [$id]);
    }

    public static function create(array $data): string
    {
        $id = Security::generateUUID();
        self::db()->insert('brands', array_merge($data, ['id'=>$id,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]));
        return $id;
    }

    public static function getPlatformAccounts(string $brandId): array
    {
        return self::db()->fetchAll("SELECT * FROM platform_accounts WHERE brand_id = ? AND is_active = 1", [$brandId]);
    }

    public static function getActiveStrategy(string $brandId): ?array
    {
        return self::db()->fetchOne("SELECT * FROM marketing_strategies WHERE brand_id = ? ORDER BY created_at DESC LIMIT 1", [$brandId]);
    }

    public static function getStats(string $brandId): array
    {
        $db = self::db();
        return [
            'total_content'    => $db->fetchOne("SELECT COUNT(*) as c FROM content_pieces WHERE brand_id = ? AND deleted_at IS NULL", [$brandId])['c'] ?? 0,
            'scheduled'        => $db->fetchOne("SELECT COUNT(*) as c FROM scheduled_posts sp JOIN content_pieces c ON c.id = sp.content_id WHERE c.brand_id = ? AND sp.status = 'scheduled'", [$brandId])['c'] ?? 0,
            'published'        => $db->fetchOne("SELECT COUNT(*) as c FROM scheduled_posts sp JOIN content_pieces c ON c.id = sp.content_id WHERE c.brand_id = ? AND sp.status = 'published'", [$brandId])['c'] ?? 0,
            'platform_accounts'=> $db->fetchOne("SELECT COUNT(*) as c FROM platform_accounts WHERE brand_id = ? AND is_active = 1", [$brandId])['c'] ?? 0,
        ];
    }
}
