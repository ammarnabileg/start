<?php
namespace App\Core;

/**
 * Multi-tenant resolver. Resolves the active tenant from the request
 * subdomain or an explicit X-Tenant-ID header.
 */
class Tenant
{
    private static ?array $current = null;
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * Resolve and cache the tenant for the current request.
     */
    public function resolve(): ?array
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $headerId = $_SERVER['HTTP_X_TENANT_ID'] ?? null;
        if ($headerId !== null && is_numeric($headerId)) {
            $tenant = $this->db->fetch('SELECT * FROM tenants WHERE id = :id LIMIT 1', [':id' => (int) $headerId]);
            if ($tenant) {
                $this->setTenant($tenant);
                return $tenant;
            }
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $host = preg_replace('/:\d+$/', '', $host);
        $parts = explode('.', $host);
        // subdomain.domain.tld  => take first label if it looks like a tenant
        if (count($parts) >= 3) {
            $sub = $parts[0];
            if (!in_array($sub, ['www', 'app', 'api'], true)) {
                $tenant = $this->db->fetch('SELECT * FROM tenants WHERE subdomain = :s LIMIT 1', [':s' => $sub]);
                if ($tenant) {
                    $this->setTenant($tenant);
                    return $tenant;
                }
            }
        }

        return null;
    }

    public function getCurrent(): ?array
    {
        return self::$current;
    }

    public function setTenant(array $tenant): void
    {
        self::$current = $tenant;
        $this->db->setTenantId((int) $tenant['id']);
    }

    public function currentId(): ?int
    {
        return self::$current ? (int) self::$current['id'] : null;
    }

    /**
     * Whether the platform has been installed (an .env exists and a database
     * connection succeeds with at least the tenants table present).
     */
    public function isSetup(): bool
    {
        $envPath = dirname(__DIR__) . '/.env';
        if (!file_exists($envPath)) {
            return false;
        }
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'users'");
            return $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
