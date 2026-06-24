<?php
namespace App\Modules\SuperAdmin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Modules\Auth\AuthService;

/**
 * Platform super-admin controller. Every action requires the
 * `platform.admin` permission. Route-handler methods receive the matched
 * route parameters as their first argument.
 */
class SuperAdminController
{
    private Auth $auth;
    private SuperAdminService $service;
    private Request $request;

    public function __construct(?Auth $auth = null, ?SuperAdminService $service = null, ?Request $request = null)
    {
        $this->auth = $auth ?? new Auth();
        $this->service = $service ?? new SuperAdminService();
        $this->request = $request ?? new Request();
    }

    /**
     * Platform dashboard with headline stats.
     */
    public function dashboard(array $params = []): void
    {
        $this->auth->requirePermission('platform.admin');

        $stats = $this->service->getPlatformStats();

        if ($this->wantsJson()) {
            Response::success(['stats' => $stats]);
            return;
        }
        Response::view('super-admin.dashboard', ['stats' => $stats]);
    }

    /**
     * List companies, filterable by status/plan/search.
     */
    public function companies(array $params = []): void
    {
        $this->auth->requirePermission('platform.admin');

        $filters = [
            'status' => $this->request->get('status'),
            'plan'   => $this->request->get('plan'),
            'search' => $this->request->get('search'),
        ];
        $filters = array_filter($filters, static fn($v) => $v !== null && $v !== '');

        $companies = $this->service->getCompanies($filters);

        if ($this->wantsJson()) {
            Response::success(['companies' => $companies]);
            return;
        }
        Response::view('super-admin.companies', ['companies' => $companies]);
    }

    /**
     * Provision a new company (tenant + roles + admin user + career page).
     */
    public function createCompany(array $params = []): void
    {
        $this->auth->requirePermission('platform.admin');

        try {
            $data = [
                'name'           => trim((string) $this->request->input('name', '')),
                'subdomain'      => strtolower(trim((string) $this->request->input('subdomain', ''))),
                'plan'           => $this->request->input('plan', 'free'),
                'status'         => $this->request->input('status', 'active'),
                'admin_email'    => trim((string) $this->request->input('admin_email', '')),
                'admin_password' => (string) $this->request->input('admin_password', ''),
                'admin_name'     => trim((string) $this->request->input('admin_name', '')),
            ];

            [$valid, $errors] = (new Validator())->validate($data, [
                'name'           => 'required|max:255',
                'subdomain'      => 'required|max:120|regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/|unique:tenants.subdomain',
                'plan'           => 'required',
                'status'         => 'in:active,inactive,suspended',
                'admin_email'    => 'required|email|unique:users.email',
                'admin_password' => 'required|min:8',
                'admin_name'     => 'required',
            ]);
            if (!$valid) {
                Response::error('Validation failed', 422, $errors);
                return;
            }

            $result = $this->service->createCompany($data);

            Response::success($result, 'Company created successfully', 201);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            logger('createCompany failed: ' . $e->getMessage(), 'error');
            Response::error('Could not create company', 500);
        }
    }

    /**
     * Toggle a company's active/inactive status. Id comes from the route.
     */
    public function toggleCompany(array $params = []): void
    {
        $this->auth->requirePermission('platform.admin');

        try {
            $id = (int) ($params['id'] ?? $this->request->input('id', 0));
            if ($id <= 0) {
                Response::error('Invalid company id', 422);
                return;
            }

            $status = $this->service->toggleCompanyStatus($id);

            Response::success(['id' => $id, 'status' => $status], 'Company status updated');
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            logger('toggleCompany failed: ' . $e->getMessage(), 'error');
            Response::error('Could not update company status', 500);
        }
    }

    /**
     * Impersonate a tenant's admin user: mint a JWT for them, write the web
     * session and redirect into the tenant dashboard. Guarded explicitly with
     * the `platform.admin` permission per the task contract.
     */
    public function impersonate(array $params = []): void
    {
        if (!$this->auth->can('platform.admin')) {
            Response::error('Forbidden: missing permission platform.admin', 403);
            return;
        }

        try {
            $tenantId = (int) ($params['id'] ?? $this->request->input('id', 0));
            if ($tenantId <= 0) {
                Response::error('Invalid company id', 422);
                return;
            }

            $db = Database::instance();
            // Pick the tenant admin: prefer a user holding the 'admin' role,
            // otherwise the earliest non-super-admin user in the tenant.
            $adminUser = $db->fetch(
                "SELECT u.*
                   FROM users u
                   JOIN user_roles ur ON ur.user_id = u.id
                   JOIN roles r ON r.id = ur.role_id
                  WHERE u.tenant_id = :tid AND u.is_super_admin = 0 AND r.name = 'admin'
                  ORDER BY u.id ASC
                  LIMIT 1",
                [':tid' => $tenantId]
            );
            if ($adminUser === null) {
                $adminUser = $db->fetch(
                    'SELECT * FROM users WHERE tenant_id = :tid AND is_super_admin = 0 ORDER BY id ASC LIMIT 1',
                    [':tid' => $tenantId]
                );
            }
            if ($adminUser === null) {
                Response::error('No admin user found for that company', 404);
                return;
            }

            // Issue a JWT for the impersonated user and install the session so
            // both API and web requests resolve as that user.
            $token = (new AuthService())->generateToken($adminUser);

            if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
                @session_start();
            }
            $_SESSION['user_id'] = (int) $adminUser['id'];
            $_SESSION['tenant_id'] = (int) $adminUser['tenant_id'];
            $_SESSION['jwt'] = $token;
            $_SESSION['impersonator_id'] = $this->auth->id();

            if ($this->wantsJson()) {
                Response::success([
                    'token'     => $token,
                    'tenant_id' => (int) $adminUser['tenant_id'],
                    'user_id'   => (int) $adminUser['id'],
                ], 'Impersonation session started');
                return;
            }

            Response::redirect('/dashboard');
        } catch (\Throwable $e) {
            logger('impersonate failed: ' . $e->getMessage(), 'error');
            Response::error('Could not start impersonation', 500);
        }
    }

    /**
     * AI usage analytics view. Period is read from ?period= (default 30d).
     */
    public function aiAnalytics(array $params = []): void
    {
        $this->auth->requirePermission('platform.admin');

        $period = (string) $this->request->get('period', '30d');
        $analytics = $this->service->getAIUsageAnalytics($period);

        if ($this->wantsJson()) {
            Response::success(['analytics' => $analytics]);
            return;
        }
        Response::view('super-admin.ai-analytics', ['analytics' => $analytics]);
    }

    /**
     * Render the maintenance terminal shell.
     */
    public function terminal(array $params = []): void
    {
        $this->auth->requirePermission('platform.admin');

        Response::view('super-admin.terminal', [
            'csrf_token' => Request::csrfToken(),
        ]);
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
