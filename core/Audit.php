<?php
declare(strict_types=1);

class Audit
{
    public static function log(
        string $action,
        string $entityType = '',
        ?int $entityId = null,
        mixed $oldValues = null,
        mixed $newValues = null
    ): void {
        try {
            $db = Database::getInstance();
            $db->insert('audit_logs', [
                'tenant_id'   => class_exists('Tenant') ? Tenant::id() : null,
                'user_id'     => class_exists('Auth') ? Auth::id() : null,
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'old_values'  => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                'new_values'  => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Audit failures must never break the application
        }
    }
}
