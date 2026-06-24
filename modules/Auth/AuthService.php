<?php
namespace App\Modules\Auth;

use App\Core\JWT;

/**
 * Authentication business logic: credential verification, JWT issuance,
 * password hashing and password-reset orchestration.
 */
class AuthService
{
    private AuthRepository $repository;
    private array $config;

    public function __construct(?AuthRepository $repository = null)
    {
        $this->repository = $repository ?? new AuthRepository();
        $this->config = require dirname(__DIR__, 2) . '/config/app.php';
    }

    /**
     * Verify credentials and return the user row on success, null on failure.
     * Inactive accounts are treated as invalid.
     */
    public function authenticate(string $email, string $password, ?int $tenantId = null): ?array
    {
        $user = $this->repository->findByEmail($email, $tenantId);
        if ($user === null) {
            return null;
        }
        if (($user['status'] ?? '') !== 'active') {
            return null;
        }
        if (!$this->verifyPassword($password, (string) $user['password_hash'])) {
            return null;
        }

        $this->repository->updateLastLogin((int) $user['id']);

        return $user;
    }

    /**
     * Issue a signed JWT for an authenticated user.
     */
    public function generateToken(array $user): string
    {
        $payload = [
            'sub'       => (int) $user['id'],
            'tenant_id' => isset($user['tenant_id']) && $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null,
            'email'     => $user['email'] ?? null,
            'is_super'  => (int) ($user['is_super_admin'] ?? 0),
        ];

        return JWT::sign(
            $payload,
            $this->config['jwt']['secret'],
            (int) $this->config['jwt']['expiry']
        );
    }

    /**
     * Hash a plaintext password using bcrypt.
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verify a plaintext password against a stored hash.
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }
        return password_verify($password, $hash);
    }

    /**
     * Generate and persist a password-reset token for the account that owns
     * $email. Returns the token, or null when no active account matches (so
     * callers can keep the response generic without leaking existence).
     */
    public function createPasswordReset(string $email): ?string
    {
        $user = $this->repository->findByEmail($email);
        if ($user === null || ($user['status'] ?? '') !== 'active') {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $this->repository->createPasswordReset((int) $user['id'], $token);

        return $token;
    }

    /**
     * Validate a reset token and apply a new password. Returns true on success.
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $reset = $this->repository->findPasswordReset($token);
        if ($reset === null) {
            return false;
        }

        $hash = $this->hashPassword($newPassword);
        $this->repository->updatePassword((int) $reset['user_id'], $hash);
        $this->repository->deletePasswordResetsForUser((int) $reset['user_id']);

        return true;
    }

    public function getRepository(): AuthRepository
    {
        return $this->repository;
    }
}
