<?php
namespace App\Modules\Company;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\Validator;

/**
 * Company controller. Protected actions operate on the authenticated user's
 * tenant; getCareerPage is public and resolves the tenant from ?subdomain=.
 */
class CompanyController
{
    private Auth $auth;
    private CompanyService $service;
    private Request $request;

    public function __construct(?Auth $auth = null, ?CompanyService $service = null, ?Request $request = null)
    {
        $this->auth = $auth ?? new Auth();
        $this->service = $service ?? new CompanyService();
        $this->request = $request ?? new Request();
    }

    /**
     * Show the current tenant's profile.
     */
    public function show(array $params = []): void
    {
        $this->auth->requireAuth();

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        $company = $this->service->getCompany($tenantId);
        if ($company === null) {
            Response::error('Company not found', 404);
            return;
        }

        if ($this->wantsJson()) {
            Response::success(['company' => $company]);
            return;
        }
        Response::view('company.profile', ['company' => $company]);
    }

    /**
     * Update the current tenant's profile (PUT). Requires settings.manage.
     */
    public function update(array $params = []): void
    {
        $this->auth->requirePermission('settings.manage');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $data = [
                'name'      => $this->request->input('name'),
                'subdomain' => $this->request->input('subdomain'),
                'plan'      => $this->request->input('plan'),
                'settings'  => $this->request->input('settings'),
            ];
            $data = array_filter($data, static fn($v) => $v !== null);

            [$valid, $errors] = (new Validator())->validate($data, [
                'name'      => 'max:255',
                'subdomain' => 'max:120|regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
            ]);
            if (!$valid) {
                Response::error('Validation failed', 422, $errors);
                return;
            }

            $company = $this->service->updateCompany($tenantId, $data);
            Response::success(['company' => $company], 'Company updated');
        } catch (\Throwable $e) {
            logger('Company update failed: ' . $e->getMessage(), 'error');
            Response::error('Could not update company', 500);
        }
    }

    /**
     * Public career page: career-page settings plus published jobs for a
     * tenant resolved from ?subdomain= or the current tenant context.
     */
    public function getCareerPage(array $params = []): void
    {
        try {
            $tenant = $this->resolvePublicTenant($params);
            if ($tenant === null) {
                Response::error('Company not found', 404);
                return;
            }

            $tenantId = (int) $tenant['id'];
            $settings = $this->service->getCareerPageSettings($tenantId);

            // Only expose career pages that have been published.
            if ((int) ($settings['is_published'] ?? 0) !== 1) {
                Response::error('Career page is not available', 404);
                return;
            }

            $jobs = $this->fetchPublishedJobs($tenantId);

            $payload = [
                'company' => [
                    'id'        => $tenantId,
                    'name'      => $tenant['name'] ?? null,
                    'subdomain' => $tenant['subdomain'] ?? null,
                ],
                'career_page' => $settings,
                'jobs'        => $jobs,
            ];

            if ($this->wantsJson()) {
                Response::success($payload);
                return;
            }
            Response::view('career.page', $payload);
        } catch (\Throwable $e) {
            logger('getCareerPage failed: ' . $e->getMessage(), 'error');
            Response::error('Could not load career page', 500);
        }
    }

    /**
     * Update the current tenant's career-page settings. Requires
     * settings.manage.
     */
    public function updateCareerPage(array $params = []): void
    {
        $this->auth->requirePermission('settings.manage');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $data = [
                'company_name'  => $this->request->input('company_name'),
                'logo_url'      => $this->request->input('logo_url'),
                'banner_url'    => $this->request->input('banner_url'),
                'primary_color' => $this->request->input('primary_color'),
                'description'   => $this->request->input('description'),
                'is_published'  => $this->request->input('is_published'),
            ];
            $data = array_filter($data, static fn($v) => $v !== null);

            $settings = $this->service->updateCareerPage($tenantId, $data);
            Response::success(['career_page' => $settings], 'Career page updated');
        } catch (\Throwable $e) {
            logger('updateCareerPage failed: ' . $e->getMessage(), 'error');
            Response::error('Could not update career page', 500);
        }
    }

    /**
     * Return the tenant settings JSON blob.
     */
    public function getSettings(array $params = []): void
    {
        $this->auth->requirePermission('settings.view');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        $company = $this->service->getCompany($tenantId);
        if ($company === null) {
            Response::error('Company not found', 404);
            return;
        }

        Response::success(['settings' => $company['settings'] ?? []]);
    }

    /**
     * Update the tenant settings JSON blob (merged into existing settings).
     */
    public function updateSettings(array $params = []): void
    {
        $this->auth->requirePermission('settings.manage');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $settings = $this->request->input('settings');
            if (is_string($settings)) {
                $decoded = json_decode($settings, true);
                $settings = is_array($decoded) ? $decoded : null;
            }
            if (!is_array($settings)) {
                Response::error('Settings must be an object', 422);
                return;
            }

            $company = $this->service->updateSettings($tenantId, $settings);
            Response::success(['settings' => $company['settings'] ?? []], 'Settings updated');
        } catch (\Throwable $e) {
            logger('updateSettings failed: ' . $e->getMessage(), 'error');
            Response::error('Could not update settings', 500);
        }
    }

    /**
     * Resolve the current authenticated tenant id (from the user or the active
     * tenant context).
     */
    private function currentTenantId(): ?int
    {
        $user = $this->auth->user();
        if ($user !== null && isset($user['tenant_id']) && $user['tenant_id'] !== null) {
            return (int) $user['tenant_id'];
        }
        $current = (new Tenant())->currentId();
        return $current !== null ? (int) $current : null;
    }

    /**
     * Resolve the tenant for the public career page from ?subdomain=, an
     * explicit param, or the active tenant context.
     *
     * @param array<string,mixed> $params
     */
    private function resolvePublicTenant(array $params): ?array
    {
        $subdomain = $params['subdomain']
            ?? $params['tenantSubdomain']
            ?? $this->request->get('subdomain');

        if ($subdomain !== null && $subdomain !== '') {
            return $this->service->getRepository()->findBySubdomain((string) $subdomain);
        }

        $tenant = (new Tenant())->resolve();
        if ($tenant !== null) {
            return $tenant;
        }

        $tenantId = $this->currentTenantId();
        if ($tenantId !== null) {
            return Database::instance()->fetch('SELECT * FROM tenants WHERE id = :id LIMIT 1', [':id' => $tenantId]);
        }
        return null;
    }

    /**
     * Published jobs for a tenant, lightweight projection for public display.
     */
    private function fetchPublishedJobs(int $tenantId): array
    {
        return Database::instance()->fetchAll(
            "SELECT id, title, department, location, job_type, salary_min, salary_max, currency, created_at
               FROM jobs
              WHERE tenant_id = :tid AND status = 'published'
              ORDER BY created_at DESC",
            [':tid' => $tenantId]
        );
    }

    private function wantsJson(): bool
    {
        if ($this->request->isAjax() || $this->request->bearerToken() !== null) {
            return true;
        }
        $accept = $this->request->header('Accept') ?? '';
        return stripos($accept, 'application/json') !== false;
    }
}
