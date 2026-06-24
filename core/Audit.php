<?php
declare(strict_types=1);

/**
 * Audit - Records security-relevant actions to the audit_logs table.
 *
 * Captures the acting user, tenant, action verb, the affected resource,
 * before/after value snapshots, and request metadata (IP + user agent).
 */
class Audit
{
    /** Toggle to disable auditing (e.g. during installation/migrations). */
    protected static bool $enabled = true;

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Write an audit log entry.
     *
     * @param string   $action     Action verb, e.g. "user.login", "job.update".
     * @param string|null $resource Resource type, e.g. "job", "candidate".
     * @param int|null  $resourceId Affected resource primary key.
     * @param array     $oldValues  Snapshot before the change.
     * @param array     $newValues  Snapshot after the change.
     */
    public static function log(
        string $action,
        ?string $resource = null,
        ?int $resourceId = null,
        array $oldValues = [],
        array $newValues = []
    ): void {
        if (!self::$enabled) {
            return;
        }

        try {
            $userId = self::currentUserId();
            $tenantId = self::currentTenantId();

            // Resolve client metadata directly from the request environment
            // (no hard dependency on a particular Request API surface).
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            if (is_string($ip) && str_contains($ip, ',')) {
                $ip = trim(explode(',', $ip)[0]);
            }
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            Database::getInstance()->insert('audit_logs', [
                'tenant_id'     => $tenantId,
                'user_id'       => $userId,
                'action'        => $action,
                'resource_type' => $resource,
                'resource_id'   => $resourceId,
                'old_values'    => empty($oldValues) ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE),
                'new_values'    => empty($newValues) ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE),
                'ip_address'    => $ip,
                'user_agent'    => mb_substr($userAgent, 0, 500),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the primary request flow.
            self::fallbackLog($action, $e);
        }
    }

    /**
     * Convenience helper to log a created resource.
     */
    public static function created(string $resource, int $resourceId, array $values = []): void
    {
        self::log($resource . '.create', $resource, $resourceId, [], $values);
    }

    /**
     * Convenience helper to log an updated resource with a diff.
     */
    public static function updated(string $resource, int $resourceId, array $old, array $new): void
    {
        // Reduce noise: keep only changed keys.
        $changedOld = [];
        $changedNew = [];
        foreach ($new as $key => $value) {
            $before = $old[$key] ?? null;
            if ($before !== $value) {
                $changedOld[$key] = $before;
                $changedNew[$key] = $value;
            }
        }
        self::log($resource . '.update', $resource, $resourceId, $changedOld, $changedNew);
    }

    /**
     * Convenience helper to log a deleted resource.
     */
    public static function deleted(string $resource, int $resourceId, array $values = []): void
    {
        self::log($resource . '.delete', $resource, $resourceId, $values, []);
    }

    protected static function currentUserId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $id = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null);
        return $id !== null ? (int) $id : null;
    }

    protected static function currentTenantId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['tenant_id'])) {
            return (int) $_SESSION['tenant_id'];
        }
        $tenantId = $_SESSION['user']['tenant_id'] ?? null;
        return $tenantId !== null ? (int) $tenantId : null;
    }

    protected static function fallbackLog(string $action, \Throwable $e): void
    {
        $dir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] AUDIT-FAILED action=%s error=%s\n",
            date('Y-m-d H:i:s'),
            $action,
            $e->getMessage()
        );
        @file_put_contents($dir . '/audit-errors.log', $line, FILE_APPEND);
    }
}
