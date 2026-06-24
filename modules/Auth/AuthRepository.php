<?php
namespace App\Modules\Auth;

use App\Core\Database;

/**
 * Data access for authentication concerns: user lookup, login bookkeeping and
 * password-reset token persistence.
 */
class AuthRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * Find a user by email. When a tenant id is supplied the lookup is scoped
     * to that tenant, but super admins (tenant_id NULL / is_super_admin) are
     * always matched so platform owners can sign in from any tenant context.
     */
    public function findByEmail(string $email, ?int $tenantId = null): ?array
    {
        $params = [':email' => $email];
        $sql = 'SELECT * FROM users WHERE email = :email';
        if ($tenantId !== null) {
            $sql .= ' AND (tenant_id = :tid OR is_super_admin = 1)';
            $params[':tid'] = $tenantId;
        }
        $sql .= ' LIMIT 1';

        return $this->db->fetch($sql, $params);
    }

    /**
     * Stamp the user's last_login_at to now.
     */
    public function updateLastLogin(int $userId): int
    {
        return $this->db->query(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id',
            [':id' => $userId]
        )->rowCount();
    }

    /**
     * Persist a password-reset token valid for one hour. The supporting table
     * is created lazily so the feature works on a fresh install without an
     * extra migration step.
     */
    public function createPasswordReset(int $userId, string $token): int
    {
        $this->ensurePasswordResetTable();

        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        return $this->db->insert('password_resets', [
            'user_id'    => $userId,
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Return the reset row for a token if it exists and has not expired.
     */
    public function findPasswordReset(string $token): ?array
    {
        $this->ensurePasswordResetTable();

        return $this->db->fetch(
            'SELECT * FROM password_resets WHERE token = :token AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            [':token' => $token]
        );
    }

    /**
     * Update a user's password hash directly.
     */
    public function updatePassword(int $userId, string $passwordHash): int
    {
        return $this->db->update('users', ['password_hash' => $passwordHash], ['id' => $userId]);
    }

    /**
     * Remove all reset tokens for a user (used after a successful reset).
     */
    public function deletePasswordResetsForUser(int $userId): int
    {
        $this->ensurePasswordResetTable();

        return $this->db->query(
            'DELETE FROM password_resets WHERE user_id = :uid',
            [':uid' => $userId]
        )->rowCount();
    }

    /**
     * Idempotently create the password_resets table.
     */
    private function ensurePasswordResetTable(): void
    {
        static $created = false;
        if ($created) {
            return;
        }
        $this->db->query(
            'CREATE TABLE IF NOT EXISTS password_resets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                token VARCHAR(128) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_pwreset_token (token),
                KEY idx_pwreset_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $created = true;
    }
}
