<?php
declare(strict_types=1);

class AuthService
{
    /**
     * Return the onboarding steps for a user's role/type, annotated with
     * completion and skip state.
     *
     * @return array<int, array{step: string, title: string, description: string, step_order: int, completed: bool, skipped: bool, completed_at: string|null, skipped_at: string|null}>
     */
    public static function getCurrentUserOnboarding(int $userId): array
    {
        $db = Database::getInstance();

        // Determine user type for step filtering
        $user = $db->fetch("SELECT is_super_admin FROM users WHERE id = ?", [$userId]);
        if (!$user) return [];

        $roles = $db->fetchAll(
            "SELECT r.slug FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?",
            [$userId]
        );
        $roleSlugs = array_column($roles, 'slug');

        if (!empty($user['is_super_admin']) || in_array('super_admin', $roleSlugs)) {
            $userType = 'super_admin';
        } elseif (in_array('candidate', $roleSlugs)) {
            $userType = 'candidate';
        } else {
            $userType = 'company';
        }

        $steps = $db->fetchAll(
            "SELECT slug, title, description, step_order, icon FROM onboarding_steps
             WHERE user_type = ? ORDER BY step_order ASC",
            [$userType]
        );

        if (!$steps) return [];

        $progress = $db->fetchAll(
            "SELECT step_slug, completed_at, skipped_at FROM user_onboarding WHERE user_id = ?",
            [$userId]
        );

        $progressMap = [];
        foreach ($progress as $p) {
            $progressMap[$p['step_slug']] = $p;
        }

        $result = [];
        foreach ($steps as $step) {
            $slug       = $step['slug'];
            $progEntry  = $progressMap[$slug] ?? null;
            $result[] = [
                'step'         => $slug,
                'title'        => $step['title'],
                'description'  => $step['description'],
                'step_order'   => (int)$step['step_order'],
                'icon'         => $step['icon'] ?? null,
                'completed'    => $progEntry && $progEntry['completed_at'] !== null,
                'skipped'      => $progEntry && $progEntry['skipped_at'] !== null,
                'completed_at' => $progEntry['completed_at'] ?? null,
                'skipped_at'   => $progEntry['skipped_at'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Mark an onboarding step as completed for the given user.
     */
    public static function completeOnboardingStep(int $userId, string $step): void
    {
        $db  = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $existing = $db->fetch(
            "SELECT id FROM user_onboarding WHERE user_id = ? AND step_slug = ?",
            [$userId, $step]
        );

        if ($existing) {
            $db->update(
                'user_onboarding',
                ['completed_at' => $now, 'skipped_at' => null, 'updated_at' => $now],
                ['user_id' => $userId, 'step_slug' => $step]
            );
        } else {
            $db->insert('user_onboarding', [
                'user_id'      => $userId,
                'step_slug'    => $step,
                'completed_at' => $now,
                'skipped_at'   => null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    /**
     * Mark an onboarding step as skipped for the given user.
     */
    public static function skipOnboardingStep(int $userId, string $step): void
    {
        $db  = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $existing = $db->fetch(
            "SELECT id FROM user_onboarding WHERE user_id = ? AND step_slug = ?",
            [$userId, $step]
        );

        if ($existing) {
            $db->update(
                'user_onboarding',
                ['skipped_at' => $now, 'updated_at' => $now],
                ['user_id' => $userId, 'step_slug' => $step]
            );
        } else {
            $db->insert('user_onboarding', [
                'user_id'      => $userId,
                'step_slug'    => $step,
                'completed_at' => null,
                'skipped_at'   => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    /**
     * Returns true when every onboarding step for the user's type is either
     * completed or skipped.
     */
    public static function isOnboardingComplete(int $userId): bool
    {
        $steps = self::getCurrentUserOnboarding($userId);
        if (!$steps) return true;

        foreach ($steps as $step) {
            if (!$step['completed'] && !$step['skipped']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Generate a password-reset token, persist it, and send the email.
     * Always returns true to prevent email enumeration.
     */
    public static function sendPasswordReset(string $email): bool
    {
        $db   = Database::getInstance();
        $user = $db->fetch(
            "SELECT id, first_name, email FROM users WHERE email = ? AND status = 'active'",
            [strtolower(trim($email))]
        );

        if (!$user) return true; // silent fail

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $now       = date('Y-m-d H:i:s');

        // Invalidate existing tokens for this email
        $db->query(
            "UPDATE password_resets SET used_at = ? WHERE email = ? AND used_at IS NULL",
            [$now, $user['email']]
        );

        $db->insert('password_resets', [
            'email'      => $user['email'],
            'token'      => $token,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        $resetUrl = (isset($_ENV['APP_URL']) ? rtrim($_ENV['APP_URL'], '/') : '') . '/reset-password/' . $token;

        // Send email via mail() — replace with a mailer service as needed
        $subject = 'Reset Your Password';
        $name    = htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8');
        $body    = "Hi {$name},\n\nClick the link below to reset your password. This link expires in 1 hour.\n\n{$resetUrl}\n\nIf you did not request this, ignore this email.\n";
        $headers = 'From: ' . ($_ENV['MAIL_FROM'] ?? 'noreply@example.com');

        @mail($user['email'], $subject, $body, $headers);

        return true;
    }

    /**
     * Validate the token, update the user's password, and mark the token used.
     */
    public static function processReset(string $token, string $password): bool
    {
        $db    = Database::getInstance();
        $reset = $db->fetch(
            "SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW()",
            [$token]
        );

        if (!$reset) return false;

        $user = $db->fetch(
            "SELECT id FROM users WHERE email = ? AND status = 'active'",
            [$reset['email']]
        );

        if (!$user) return false;

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $now  = date('Y-m-d H:i:s');

        $db->update('users', ['password_hash' => $hash, 'updated_at' => $now], ['id' => (int)$user['id']]);
        $db->update('password_resets', ['used_at' => $now], ['id' => (int)$reset['id']]);

        return true;
    }
}
