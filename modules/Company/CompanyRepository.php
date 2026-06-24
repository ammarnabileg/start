<?php
namespace App\Modules\Company;

use App\Core\Database;

/**
 * Tenant-facing company data access: the tenant's own profile and its public
 * career-page settings.
 */
class CompanyRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * Fetch a tenant by id.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM tenants WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    public function findBySubdomain(string $subdomain): ?array
    {
        return $this->db->fetch('SELECT * FROM tenants WHERE subdomain = :s LIMIT 1', [':s' => $subdomain]);
    }

    /**
     * Update tenant columns. Returns affected row count.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        return $this->db->update('tenants', $data, ['id' => $id]);
    }

    /**
     * Career-page settings for a tenant (or null if not yet configured).
     */
    public function getCareerPage(int $tenantId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM career_page_settings WHERE tenant_id = :tid LIMIT 1',
            [':tid' => $tenantId]
        );
    }

    /**
     * Upsert career-page settings: insert when missing, otherwise update only
     * the supplied columns. Returns the resulting row.
     *
     * @param array<string,mixed> $data
     */
    public function updateCareerPage(int $tenantId, array $data): array
    {
        $allowed = ['company_name', 'logo_url', 'banner_url', 'primary_color', 'description', 'is_published'];
        $clean = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $clean[$col] = $col === 'is_published' ? (int) (bool) $data[$col] : $data[$col];
            }
        }

        $existing = $this->getCareerPage($tenantId);
        if ($existing === null) {
            $insert = array_merge(['tenant_id' => $tenantId], $clean);
            // Insert directly with tenant_id explicit so the auto-injection is a
            // no-op even if a different tenant is active on the connection.
            $this->db->insert('career_page_settings', $insert);
        } elseif (!empty($clean)) {
            $this->db->update('career_page_settings', $clean, ['tenant_id' => $tenantId]);
        }

        return $this->getCareerPage($tenantId) ?? array_merge(['tenant_id' => $tenantId], $clean);
    }
}
