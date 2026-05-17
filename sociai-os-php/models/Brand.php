<?php
declare(strict_types=1);
namespace SociAI\Models;
use SociAI\Core\Database;

class Brand
{
    private static function db(): Database { return Database::getInstance(); }

    // Static helpers
    public static function find(string $id): ?array
    {
        return self::db()->fetchOne("SELECT * FROM brands WHERE id = ? AND deleted_at IS NULL", [$id]) ?: null;
    }

    public static function create(array $data): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
        $slug = $data['slug'] ?? strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $data['name'] ?? 'brand')) . '-' . rand(100, 999);
        self::db()->insert('brands', array_merge($data, [
            'id'         => $id,
            'slug'       => $slug,
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));
        return $id;
    }

    public static function getPlatformAccounts(string $brandId): array
    {
        return self::db()->fetchAll(
            "SELECT * FROM platform_accounts WHERE brand_id = ? AND is_active = 1",
            [$brandId]
        );
    }

    public static function getActiveStrategy(string $brandId): ?array
    {
        return self::db()->fetchOne(
            "SELECT * FROM marketing_strategies WHERE brand_id = ? ORDER BY created_at DESC LIMIT 1",
            [$brandId]
        ) ?: null;
    }

    public static function getStats(string $brandId): array
    {
        $db = self::db();
        return [
            'total_content'     => $db->fetchOne("SELECT COUNT(*) as c FROM content_pieces WHERE brand_id = ?", [$brandId])['c'] ?? 0,
            'scheduled'         => $db->fetchOne("SELECT COUNT(*) as c FROM scheduled_posts sp JOIN content_pieces c ON c.id = sp.content_id WHERE c.brand_id = ? AND sp.status = 'scheduled'", [$brandId])['c'] ?? 0,
            'published'         => $db->fetchOne("SELECT COUNT(*) as c FROM scheduled_posts sp JOIN content_pieces c ON c.id = sp.content_id WHERE c.brand_id = ? AND sp.status = 'published'", [$brandId])['c'] ?? 0,
            'platform_accounts' => $db->fetchOne("SELECT COUNT(*) as c FROM platform_accounts WHERE brand_id = ? AND is_active = 1", [$brandId])['c'] ?? 0,
        ];
    }

    // Instance-compatible wrappers (for controllers that do new Brand())
    public function findBySlug(string $slug): ?array
    {
        return self::db()->fetchOne("SELECT * FROM brands WHERE slug = ? AND deleted_at IS NULL LIMIT 1", [$slug]) ?: null;
    }

    public function findById(string $id): ?array
    {
        return self::find($id);
    }

    public function userCanAccess(string $brandId, string $userId, string ...$roles): bool
    {
        $row = self::db()->fetchOne(
            "SELECT role FROM team_members WHERE brand_id = ? AND user_id = ? LIMIT 1",
            [$brandId, $userId]
        );
        if (!$row) return false;
        if (empty($roles)) return true;
        return in_array($row['role'], $roles, true);
    }

    public function update(string $brandId, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return (bool)self::db()->update('brands', $data, 'id = ?', [$brandId]);
    }

    public function delete(string $brandId): bool
    {
        return (bool)self::db()->update('brands', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$brandId]);
    }
}
