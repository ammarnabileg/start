<?php
namespace App\Modules\SuperAdmin;

use App\Core\Database;
use App\Core\RBAC;

/**
 * Platform administration business logic: cross-tenant statistics, company
 * provisioning (with default roles + admin user), status toggling and AI
 * usage analytics.
 */
class SuperAdminService
{
    private CompanyRepository $repository;
    private Database $db;
    private RBAC $rbac;

    /**
     * Default roles created for every new company and the permission sets they
     * receive. Keyed by role name => list of permission names.
     */
    private const DEFAULT_ROLES = [
        'admin' => [
            'dashboard.view',
            'jobs.view', 'jobs.create', 'jobs.edit', 'jobs.delete', 'jobs.publish',
            'candidates.view', 'candidates.create', 'candidates.edit', 'candidates.delete', 'candidates.compare',
            'interviews.view', 'interviews.create', 'interviews.report',
            'pipeline.view', 'pipeline.manage',
            'offers.view', 'offers.create', 'offers.send',
            'talent_pool.view', 'talent_pool.manage',
            'avatars.view', 'avatars.manage',
            'users.view', 'users.manage',
            'roles.view', 'roles.manage',
            'settings.view', 'settings.manage',
            'ai.use', 'ai.analytics',
        ],
        'recruiter' => [
            'dashboard.view',
            'jobs.view', 'jobs.create', 'jobs.edit', 'jobs.publish',
            'candidates.view', 'candidates.create', 'candidates.edit', 'candidates.compare',
            'interviews.view', 'interviews.create', 'interviews.report',
            'pipeline.view', 'pipeline.manage',
            'offers.view', 'offers.create',
            'talent_pool.view', 'talent_pool.manage',
            'avatars.view',
            'ai.use',
        ],
        'hiring_manager' => [
            'dashboard.view',
            'jobs.view',
            'candidates.view', 'candidates.compare',
            'interviews.view', 'interviews.report',
            'pipeline.view',
            'offers.view',
            'talent_pool.view',
        ],
    ];

    public function __construct(?CompanyRepository $repository = null, ?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
        $this->repository = $repository ?? new CompanyRepository($this->db);
        $this->rbac = new RBAC($this->db);
    }

    /**
     * Aggregate headline platform metrics across all tenants.
     *
     * @return array<string,int|float>
     */
    public function getPlatformStats(): array
    {
        $totalCompanies = (int) ($this->db->fetch('SELECT COUNT(*) AS c FROM tenants')['c'] ?? 0);
        $activeCompanies = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS c FROM tenants WHERE status = 'active'"
        )['c'] ?? 0);
        $totalUsers = (int) ($this->db->fetch('SELECT COUNT(*) AS c FROM users')['c'] ?? 0);
        $totalJobs = (int) ($this->db->fetch('SELECT COUNT(*) AS c FROM jobs')['c'] ?? 0);
        $totalCandidates = (int) ($this->db->fetch('SELECT COUNT(*) AS c FROM candidates')['c'] ?? 0);
        $totalInterviews = (int) ($this->db->fetch('SELECT COUNT(*) AS c FROM interviews')['c'] ?? 0);

        $usage = $this->db->fetch(
            'SELECT COALESCE(SUM(tokens_used),0) AS tokens, COALESCE(SUM(cost),0) AS cost FROM ai_usage_logs'
        );

        return [
            'total_companies'  => $totalCompanies,
            'active_companies' => $activeCompanies,
            'total_users'      => $totalUsers,
            'total_jobs'       => $totalJobs,
            'total_candidates' => $totalCandidates,
            'total_interviews' => $totalInterviews,
            'total_ai_tokens'  => (int) ($usage['tokens'] ?? 0),
            'total_ai_cost'    => round((float) ($usage['cost'] ?? 0), 6),
        ];
    }

    /**
     * List companies with optional filters.
     *
     * @param array<string,mixed> $filters
     */
    public function getCompanies(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }

    public function getCompany(int $id): ?array
    {
        $company = $this->repository->findById($id);
        if ($company === null) {
            return null;
        }
        $company['stats'] = $this->repository->getStats($id);
        return $company;
    }

    /**
     * Provision a new company: create the tenant, its default roles, an admin
     * user and a career-page settings row, all within a single transaction.
     *
     * @param array<string,mixed> $data
     * @return array{tenant_id:int,admin_user_id:int,roles:array<string,int>}
     */
    public function createCompany(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $subdomain = strtolower(trim((string) ($data['subdomain'] ?? '')));
        $adminEmail = trim((string) ($data['admin_email'] ?? ''));
        $adminPassword = (string) ($data['admin_password'] ?? '');
        $adminName = trim((string) ($data['admin_name'] ?? ''));

        if ($name === '' || $subdomain === '') {
            throw new \InvalidArgumentException('Company name and subdomain are required.');
        }
        if ($adminEmail === '' || $adminPassword === '') {
            throw new \InvalidArgumentException('Admin email and password are required.');
        }

        // Guard against duplicate subdomains before opening a transaction.
        if ($this->repository->findBySubdomain($subdomain) !== null) {
            throw new \RuntimeException('Subdomain is already taken.');
        }

        // Split a full name into first/last for the users table.
        [$firstName, $lastName] = $this->splitName($adminName);

        $this->db->beginTransaction();
        try {
            $tenantId = $this->repository->create([
                'name'      => $name,
                'subdomain' => $subdomain,
                'plan'      => $data['plan'] ?? 'free',
                'status'    => $data['status'] ?? 'active',
                'settings'  => $data['settings'] ?? null,
            ]);

            // Create the per-tenant roles and capture their ids.
            $roleIds = [];
            foreach (self::DEFAULT_ROLES as $roleName => $permissions) {
                $roleIds[$roleName] = $this->rbac->createRole(
                    $tenantId,
                    $roleName,
                    $permissions,
                    $this->humanizeRole($roleName),
                    false
                );
            }

            // Create the tenant admin user (NOT a platform super admin).
            $adminUserId = $this->db->insert('users', [
                'tenant_id'      => $tenantId,
                'email'          => $adminEmail,
                'password_hash'  => password_hash($adminPassword, PASSWORD_BCRYPT),
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'status'         => 'active',
                'is_super_admin' => 0,
            ]);

            // Grant the admin role to the new user.
            if (isset($roleIds['admin'])) {
                $this->rbac->assignRole($adminUserId, $roleIds['admin']);
            }

            // Seed a career-page settings row for the tenant.
            $this->db->insert('career_page_settings', [
                'tenant_id'     => $tenantId,
                'company_name'  => $name,
                'primary_color' => '#7C3AED',
                'is_published'  => 0,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return [
            'tenant_id'     => $tenantId,
            'admin_user_id' => $adminUserId,
            'roles'         => $roleIds,
        ];
    }

    /**
     * Flip a company between active and inactive. Suspended tenants are
     * reactivated. Returns the new status.
     */
    public function toggleCompanyStatus(int $id): string
    {
        $company = $this->repository->findById($id);
        if ($company === null) {
            throw new \RuntimeException('Company not found.');
        }

        $newStatus = ($company['status'] === 'active') ? 'inactive' : 'active';
        $this->repository->updateStatus($id, $newStatus);

        return $newStatus;
    }

    /**
     * AI usage analytics grouped by day and by feature over a recent period.
     *
     * @param string $period One of '7d','30d','90d','365d' (defaults to 30d).
     * @return array{period:string,days:int,by_day:array,by_feature:array,totals:array}
     */
    public function getAIUsageAnalytics(string $period = '30d'): array
    {
        $days = $this->periodToDays($period);
        $params = [':days' => $days];

        $byDay = $this->db->fetchAll(
            'SELECT DATE(created_at) AS day,
                    COUNT(*) AS requests,
                    COALESCE(SUM(tokens_used),0) AS tokens,
                    COALESCE(SUM(cost),0) AS cost
               FROM ai_usage_logs
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
              GROUP BY DATE(created_at)
              ORDER BY day ASC',
            $params
        );

        $byFeature = $this->db->fetchAll(
            "SELECT COALESCE(feature,'unknown') AS feature,
                    COUNT(*) AS requests,
                    COALESCE(SUM(tokens_used),0) AS tokens,
                    COALESCE(SUM(cost),0) AS cost
               FROM ai_usage_logs
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
              GROUP BY feature
              ORDER BY tokens DESC",
            $params
        );

        $totals = $this->db->fetch(
            'SELECT COUNT(*) AS requests,
                    COALESCE(SUM(tokens_used),0) AS tokens,
                    COALESCE(SUM(cost),0) AS cost
               FROM ai_usage_logs
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)',
            $params
        ) ?? ['requests' => 0, 'tokens' => 0, 'cost' => 0];

        // Normalize numeric types for clean JSON output.
        $byDay = array_map([$this, 'normalizeUsageRow'], $byDay);
        $byFeature = array_map([$this, 'normalizeUsageRow'], $byFeature);

        return [
            'period'     => $period,
            'days'       => $days,
            'by_day'     => $byDay,
            'by_feature' => $byFeature,
            'totals'     => [
                'requests' => (int) ($totals['requests'] ?? 0),
                'tokens'   => (int) ($totals['tokens'] ?? 0),
                'cost'     => round((float) ($totals['cost'] ?? 0), 6),
            ],
        ];
    }

    public function getRepository(): CompanyRepository
    {
        return $this->repository;
    }

    private function normalizeUsageRow(array $row): array
    {
        if (array_key_exists('requests', $row)) {
            $row['requests'] = (int) $row['requests'];
        }
        if (array_key_exists('tokens', $row)) {
            $row['tokens'] = (int) $row['tokens'];
        }
        if (array_key_exists('cost', $row)) {
            $row['cost'] = round((float) $row['cost'], 6);
        }
        return $row;
    }

    private function periodToDays(string $period): int
    {
        return match ($period) {
            '7d'   => 7,
            '90d'  => 90,
            '365d' => 365,
            default => 30,
        };
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return [null, null];
        }
        $parts = preg_split('/\s+/', $fullName, 2);
        $first = $parts[0] ?? null;
        $last = $parts[1] ?? null;
        return [$first, $last];
    }

    private function humanizeRole(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }
}
