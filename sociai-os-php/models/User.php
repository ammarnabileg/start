<?php
declare(strict_types=1);
namespace SociAI\Models;
use SociAI\Core\Database;

class User
{
    private static function db(): Database { return Database::getInstance(); }

    public static function find(string $id): ?array
    {
        return self::db()->fetchOne("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$id]) ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        return self::db()->fetchOne("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL", [$email]) ?: null;
    }

    public static function create(array $data): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
        self::db()->insert('users', array_merge($data, [
            'id'         => $id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));
        return $id;
    }

    public static function update(string $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return (bool)self::db()->update('users', $data, 'id = ?', [$id]);
    }

    public static function getBrands(string $userId): array
    {
        return self::db()->fetchAll(
            "SELECT b.* FROM brands b
             LEFT JOIN team_members tm ON tm.brand_id = b.id AND tm.user_id = ?
             WHERE b.owner_id = ? OR tm.user_id = ?
             GROUP BY b.id
             ORDER BY b.created_at DESC",
            [$userId, $userId, $userId]
        );
    }

    // Instance-compatible wrappers

    public function sanitize(array $user): array
    {
        unset($user['password_hash'], $user['two_factor_secret']);
        return $user;
    }

    public function getSessions(string $userId): array
    {
        return self::db()->fetchAll(
            "SELECT id, device_info, ip_address, expires_at, created_at
             FROM user_sessions WHERE user_id = ? AND expires_at > NOW()
             ORDER BY created_at DESC",
            [$userId]
        );
    }

    public function getLoginHistory(string $userId): array
    {
        // login_history table may or may not exist; return safely
        try {
            return self::db()->fetchAll(
                "SELECT ip_address, user_agent, success, failure_reason, created_at
                 FROM login_history WHERE user_id = ?
                 ORDER BY created_at DESC LIMIT 20",
                [$userId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getNotifications(string $userId): array
    {
        return self::db()->fetchAll(
            "SELECT id, type, title, message, action_url, is_read, read_at, created_at
             FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC LIMIT 50",
            [$userId]
        );
    }

    public function markNotificationsRead(string $userId, mixed $ids): void
    {
        if ($ids === null) {
            self::db()->query(
                "UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0",
                [$userId]
            );
        } else {
            $ids = array_filter(array_map('intval', (array)$ids));
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                self::db()->query(
                    "UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND id IN ({$ph})",
                    array_merge([$userId], $ids)
                );
            }
        }
    }

    public function enable2FA(string $userId, string $secret): void
    {
        self::update($userId, ['two_factor_enabled' => 1, 'two_factor_secret' => $secret]);
    }

    public function disable2FA(string $userId): void
    {
        self::update($userId, ['two_factor_enabled' => 0, 'two_factor_secret' => null]);
    }
}
