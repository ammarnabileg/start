<?php
declare(strict_types=1);

/**
 * Tenant - Multi-tenant resolver.
 *
 * Resolves the active company (tenant) from, in priority order:
 *   1. The current session (set after login or impersonation).
 *   2. The request subdomain (e.g. acme.hireai.com -> slug "acme").
 *   3. A custom domain mapped to a tenant (tenants.domain).
 *
 * The resolved tenant id is propagated to the Database layer so that
 * tenant-scoped queries are automatically constrained.
 */
class Tenant
{
    /** Cached current tenant row, or false when explicitly none. */
    protected static array|false|null $current = null;

    /** Hostnames treated as the platform root (no tenant subdomain). */
    protected static array $rootDomains = ['localhost', '127.0.0.1'];

    /**
     * Configure base/root domains (e.g. ['hireai.com']) so subdomain
     * detection can strip them correctly.
     */
    public static function setRootDomains(array $domains): void
    {
        self::$rootDomains = array_map('strtolower', $domains);
    }

    /**
     * Resolve and return the current tenant (or null if none applies).
     */
    public static function resolve(): ?array
    {
        if (self::$current !== null) {
            return self::$current ?: null;
        }

        // 1) Session.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['tenant_id'])) {
            $tenant = self::findById((int) $_SESSION['tenant_id']);
            if ($tenant) {
                return self::apply($tenant);
            }
        }

        // 2) Subdomain.
        $host = self::host();
        $slug = self::extractSubdomain($host);
        if ($slug !== null && $slug !== '') {
            $tenant = self::findBySlug($slug);
            if ($tenant) {
                return self::apply($tenant);
            }
        }

        // 3) Custom domain.
        if ($host !== '') {
            $tenant = self::findByDomain($host);
            if ($tenant) {
                return self::apply($tenant);
            }
        }

        self::$current = false;
        Database::getInstance()->setTenantId(null);
        return null;
    }

    /**
     * Return the already-resolved tenant without re-resolving.
     */
    public static function getCurrent(): ?array
    {
        if (self::$current === null) {
            return self::resolve();
        }
        return self::$current ?: null;
    }

    public static function id(): ?int
    {
        $tenant = self::getCurrent();
        return $tenant['id'] ?? null;
    }

    /**
     * Force the active tenant by slug. Persists to session.
     */
    public static function setFromSlug(string $slug): bool
    {
        $tenant = self::findBySlug($slug);
        if (!$tenant) {
            return false;
        }
        self::persist($tenant);
        self::apply($tenant);
        return true;
    }

    /**
     * Force the active tenant by custom domain. Persists to session.
     */
    public static function setFromDomain(string $domain): bool
    {
        $tenant = self::findByDomain($domain);
        if (!$tenant) {
            return false;
        }
        self::persist($tenant);
        self::apply($tenant);
        return true;
    }

    /**
     * Force the active tenant by id (used by impersonation / super admin).
     */
    public static function setById(int $id): bool
    {
        $tenant = self::findById($id);
        if (!$tenant) {
            return false;
        }
        self::persist($tenant);
        self::apply($tenant);
        return true;
    }

    /**
     * Clear the active tenant (e.g. when a super admin exits a workspace).
     */
    public static function clear(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        unset($_SESSION['tenant_id'], $_SESSION['tenant_slug']);
        self::$current = false;
        Database::getInstance()->setTenantId(null);
    }

    /**
     * Override resolution in code (used by tests / installer).
     */
    public static function setCurrent(?array $tenant): void
    {
        if ($tenant === null) {
            self::$current = false;
            Database::getInstance()->setTenantId(null);
            return;
        }
        self::apply($tenant);
    }

    // ----- Internal -----------------------------------------------------

    protected static function apply(array $tenant): array
    {
        self::$current = $tenant;
        Database::getInstance()->setTenantId((int) $tenant['id']);
        return $tenant;
    }

    protected static function persist(array $tenant): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['tenant_id'] = (int) $tenant['id'];
        $_SESSION['tenant_slug'] = $tenant['slug'] ?? null;
    }

    protected static function host(): string
    {
        return strtolower($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
    }

    /**
     * Extract a tenant subdomain from a host, returning null when the host
     * is a root domain or has no subdomain.
     */
    protected static function extractSubdomain(string $host): ?string
    {
        if ($host === '') {
            return null;
        }

        // Strip port if present.
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        // Direct root domain match.
        if (in_array($host, self::$rootDomains, true)) {
            return null;
        }

        // IP addresses have no subdomain semantics.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        // If host ends with a known root domain, the prefix is the subdomain.
        foreach (self::$rootDomains as $root) {
            if ($root !== '' && str_ends_with($host, '.' . $root)) {
                $prefix = substr($host, 0, -1 * (strlen($root) + 1));
                $prefix = trim($prefix, '.');
                if ($prefix === '' || $prefix === 'www') {
                    return null;
                }
                // Use the left-most label as the tenant slug.
                return explode('.', $prefix)[0];
            }
        }

        // Fallback heuristic: a.b.tld -> "a" (3+ labels).
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $first = $parts[0];
            if ($first !== 'www') {
                return $first;
            }
        }

        return null;
    }

    protected static function findById(int $id): ?array
    {
        try {
            $row = Database::getInstance()->fetch(
                'SELECT * FROM `tenants` WHERE `id` = ? AND `status` != "deleted" LIMIT 1',
                [$id]
            );
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected static function findBySlug(string $slug): ?array
    {
        try {
            $row = Database::getInstance()->fetch(
                'SELECT * FROM `tenants` WHERE `slug` = ? AND `status` != "deleted" LIMIT 1',
                [$slug]
            );
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected static function findByDomain(string $domain): ?array
    {
        $domain = strtolower($domain);
        if (str_contains($domain, ':')) {
            $domain = explode(':', $domain)[0];
        }
        try {
            $row = Database::getInstance()->fetch(
                'SELECT * FROM `tenants` WHERE `domain` = ? AND `status` != "deleted" LIMIT 1',
                [$domain]
            );
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Decode the JSON settings column for a tenant row.
     */
    public static function settings(?array $tenant = null): array
    {
        $tenant = $tenant ?? self::getCurrent();
        if (!$tenant || empty($tenant['settings'])) {
            return [];
        }
        $decoded = json_decode((string) $tenant['settings'], true);
        return is_array($decoded) ? $decoded : [];
    }
}
