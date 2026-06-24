<?php
namespace App\Modules\Company;

/**
 * Company business logic for tenant self-service: profile, settings and the
 * public career page.
 */
class CompanyService
{
    private CompanyRepository $repository;

    public function __construct(?CompanyRepository $repository = null)
    {
        $this->repository = $repository ?? new CompanyRepository();
    }

    /**
     * Return a tenant profile with its JSON settings decoded.
     */
    public function getCompany(int $id): ?array
    {
        $company = $this->repository->findById($id);
        if ($company === null) {
            return null;
        }
        $company['settings'] = $this->decodeSettings($company['settings'] ?? null);
        return $company;
    }

    public function getCompanyBySubdomain(string $subdomain): ?array
    {
        $company = $this->repository->findBySubdomain($subdomain);
        if ($company === null) {
            return null;
        }
        $company['settings'] = $this->decodeSettings($company['settings'] ?? null);
        return $company;
    }

    /**
     * Update mutable tenant profile fields. Only whitelisted columns are
     * applied; settings are merged and re-encoded to JSON.
     *
     * @param array<string,mixed> $data
     */
    public function updateCompany(int $id, array $data): ?array
    {
        $update = [];
        foreach (['name', 'subdomain', 'plan'] as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $update[$col] = $data[$col];
            }
        }

        if (array_key_exists('settings', $data) && is_array($data['settings'])) {
            $current = $this->repository->findById($id);
            $existing = $this->decodeSettings($current['settings'] ?? null);
            $merged = array_merge($existing, $data['settings']);
            $update['settings'] = json_encode($merged);
        }

        if (!empty($update)) {
            $this->repository->update($id, $update);
        }

        return $this->getCompany($id);
    }

    /**
     * Replace the tenant settings JSON blob wholesale (merging by default).
     *
     * @param array<string,mixed> $settings
     */
    public function updateSettings(int $id, array $settings, bool $merge = true): ?array
    {
        $value = $settings;
        if ($merge) {
            $current = $this->repository->findById($id);
            $existing = $this->decodeSettings($current['settings'] ?? null);
            $value = array_merge($existing, $settings);
        }
        $this->repository->update($id, ['settings' => json_encode($value)]);

        return $this->getCompany($id);
    }

    /**
     * Career-page settings for a tenant, with sane defaults when unset.
     */
    public function getCareerPageSettings(int $tenantId): array
    {
        $row = $this->repository->getCareerPage($tenantId);
        if ($row === null) {
            return [
                'tenant_id'     => $tenantId,
                'company_name'  => null,
                'logo_url'      => null,
                'banner_url'    => null,
                'primary_color' => '#7C3AED',
                'description'   => null,
                'is_published'  => 0,
            ];
        }
        $row['is_published'] = (int) $row['is_published'];
        return $row;
    }

    /**
     * Upsert career-page settings.
     *
     * @param array<string,mixed> $data
     */
    public function updateCareerPage(int $tenantId, array $data): array
    {
        $row = $this->repository->updateCareerPage($tenantId, $data);
        if (isset($row['is_published'])) {
            $row['is_published'] = (int) $row['is_published'];
        }
        return $row;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeSettings($settings): array
    {
        if (is_array($settings)) {
            return $settings;
        }
        if (is_string($settings) && $settings !== '') {
            $decoded = json_decode($settings, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    public function getRepository(): CompanyRepository
    {
        return $this->repository;
    }
}
