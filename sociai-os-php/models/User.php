<?php
class User
{
    private static Database $db;
    private static function db(): Database { return self::$db ??= Database::getInstance(); }

    public static function find(string $id): ?array
    {
        return self::db()->fetchOne("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return self::db()->fetchOne("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL", [$email]);
    }

    public static function create(array $data): string
    {
        $id = Security::generateUUID();
        self::db()->insert('users', array_merge($data, ['id'=>$id,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]));
        return $id;
    }

    public static function update(string $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return (bool)self::db()->update('users', $data, 'id = ?', [$id]);
    }

    public static function getBrands(string $userId): array
    {
        return self::db()->fetchAll("SELECT b.* FROM brands b LEFT JOIN team_members tm ON tm.brand_id = b.id AND tm.user_id = ? WHERE b.owner_id = ? OR tm.user_id = ? GROUP BY b.id ORDER BY b.created_at DESC", [$userId,$userId,$userId]);
    }
}
