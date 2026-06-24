<?php
namespace App\Modules\Jobs;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\Validator;

/**
 * Jobs controller. Authenticated actions are tenant-scoped and gated by the
 * jobs.* permissions; getPublicJobs is open and resolves the tenant from the
 * route/subdomain.
 */
class JobController
{
    private Auth $auth;
    private JobService $service;
    private Request $request;

    public function __construct(?Auth $auth = null, ?JobService $service = null, ?Request $request = null)
    {
        $this->auth = $auth ?? new Auth();
        $this->service = $service ?? new JobService();
        $this->request = $request ?? new Request();
    }

    /**
     * List the tenant's jobs.
     */
    public function index(array $params = []): void
    {
        $this->auth->requirePermission('jobs.view');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        $filters = [
            'status'     => $this->request->get('status'),
            'department' => $this->request->get('department'),
            'search'     => $this->request->get('search'),
        ];
        $filters = array_filter($filters, static fn($v) => $v !== null && $v !== '');

        $jobs = $this->service->getJobs($tenantId, $filters);

        if ($this->wantsJson()) {
            Response::success(['jobs' => $jobs]);
            return;
        }
        Response::view('hr.jobs.index', ['jobs' => $jobs]);
    }

    /**
     * Show a single job.
     */
    public function show(array $params = []): void
    {
        $this->auth->requirePermission('jobs.view');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $job = $this->service->getJob($id, $tenantId);
        if ($job === null) {
            Response::error('Job not found', 404);
            return;
        }

        if ($this->wantsJson()) {
            Response::success(['job' => $job]);
            return;
        }
        Response::view('hr.jobs.show', ['job' => $job]);
    }

    /**
     * Render the create-job form.
     */
    public function create(array $params = []): void
    {
        $this->auth->requirePermission('jobs.create');

        Response::view('hr.jobs.create', [
            'csrf_token' => Request::csrfToken(),
        ]);
    }

    /**
     * Persist a new job.
     */
    public function store(array $params = []): void
    {
        $this->auth->requirePermission('jobs.create');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $data = $this->collectJobInput();

            [$valid, $errors] = (new Validator())->validate($data, [
                'title'      => 'required|max:255',
                'job_type'   => 'in:full-time,part-time,contract,remote,internship',
                'salary_min' => 'numeric',
                'salary_max' => 'numeric',
            ]);
            if (!$valid) {
                Response::error('Validation failed', 422, $errors);
                return;
            }

            // Ensure the connection scopes the insert to this tenant.
            Database::instance()->setTenantId($tenantId);
            $data['tenant_id'] = $tenantId;

            $id = $this->service->createJob($data, (int) $this->auth->id());
            $job = $this->service->getJob($id, $tenantId);

            Response::success(['job' => $job], 'Job created', 201);
        } catch (\Throwable $e) {
            logger('Job store failed: ' . $e->getMessage(), 'error');
            Response::error('Could not create job', 500);
        }
    }

    /**
     * Render the edit-job form.
     */
    public function edit(array $params = []): void
    {
        $this->auth->requirePermission('jobs.edit');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $job = $this->service->getJob($id, $tenantId);
        if ($job === null) {
            Response::error('Job not found', 404);
            return;
        }

        Response::view('hr.jobs.edit', [
            'job'        => $job,
            'csrf_token' => Request::csrfToken(),
        ]);
    }

    /**
     * Update an existing job.
     */
    public function update(array $params = []): void
    {
        $this->auth->requirePermission('jobs.edit');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $id = (int) ($params['id'] ?? 0);
            $existing = $this->service->getJob($id, $tenantId);
            if ($existing === null) {
                Response::error('Job not found', 404);
                return;
            }

            $data = $this->collectJobInput();

            [$valid, $errors] = (new Validator())->validate($data, [
                'title'      => 'max:255',
                'job_type'   => 'in:full-time,part-time,contract,remote,internship',
                'salary_min' => 'numeric',
                'salary_max' => 'numeric',
            ]);
            if (!$valid) {
                Response::error('Validation failed', 422, $errors);
                return;
            }

            $this->service->updateJob($id, $data);
            $job = $this->service->getJob($id, $tenantId);

            Response::success(['job' => $job], 'Job updated');
        } catch (\Throwable $e) {
            logger('Job update failed: ' . $e->getMessage(), 'error');
            Response::error('Could not update job', 500);
        }
    }

    /**
     * Delete a job.
     */
    public function delete(array $params = []): void
    {
        $this->auth->requirePermission('jobs.delete');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $id = (int) ($params['id'] ?? 0);
            $existing = $this->service->getJob($id, $tenantId);
            if ($existing === null) {
                Response::error('Job not found', 404);
                return;
            }

            $this->service->deleteJob($id);
            Response::success(['id' => $id], 'Job deleted');
        } catch (\Throwable $e) {
            logger('Job delete failed: ' . $e->getMessage(), 'error');
            Response::error('Could not delete job', 500);
        }
    }

    /**
     * Publish a job.
     */
    public function publish(array $params = []): void
    {
        $this->auth->requirePermission('jobs.publish');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $id = (int) ($params['id'] ?? 0);
            $existing = $this->service->getJob($id, $tenantId);
            if ($existing === null) {
                Response::error('Job not found', 404);
                return;
            }

            $this->service->publishJob($id);
            $job = $this->service->getJob($id, $tenantId);

            Response::success(['job' => $job], 'Job published');
        } catch (\Throwable $e) {
            logger('Job publish failed: ' . $e->getMessage(), 'error');
            Response::error('Could not publish job', 500);
        }
    }

    /**
     * Build a job draft from a natural-language prompt via AI.
     */
    public function buildWithAI(array $params = []): void
    {
        $this->auth->requirePermission('jobs.create');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $prompt = trim((string) $this->request->input('prompt', ''));
            if ($prompt === '') {
                Response::error('A prompt is required', 422);
                return;
            }

            $draft = $this->service->buildJobWithAI($prompt, $tenantId);
            Response::success(['job' => $draft]);
        } catch (\Throwable $e) {
            logger('buildWithAI failed: ' . $e->getMessage(), 'error');
            Response::error('Could not build job', 500);
        }
    }

    /**
     * Public list of published jobs for a tenant resolved from the route
     * subdomain or ?subdomain=. No authentication required.
     */
    public function getPublicJobs(array $params = []): void
    {
        try {
            $tenant = $this->resolvePublicTenant($params);
            if ($tenant === null) {
                Response::error('Company not found', 404);
                return;
            }

            $jobs = $this->service->getRepository()->findPublished((int) $tenant['id']);

            // Public projection: drop internal AI fields.
            $public = array_map(static function (array $job): array {
                return [
                    'id'          => (int) $job['id'],
                    'title'       => $job['title'] ?? null,
                    'description' => $job['description'] ?? null,
                    'requirements'=> $job['requirements'] ?? null,
                    'department'  => $job['department'] ?? null,
                    'location'    => $job['location'] ?? null,
                    'job_type'    => $job['job_type'] ?? null,
                    'salary_min'  => $job['salary_min'] ?? null,
                    'salary_max'  => $job['salary_max'] ?? null,
                    'currency'    => $job['currency'] ?? null,
                    'created_at'  => $job['created_at'] ?? null,
                ];
            }, $jobs);

            $payload = [
                'company' => [
                    'id'        => (int) $tenant['id'],
                    'name'      => $tenant['name'] ?? null,
                    'subdomain' => $tenant['subdomain'] ?? null,
                ],
                'jobs' => $public,
            ];

            if ($this->wantsJson()) {
                Response::success($payload);
                return;
            }
            Response::view('career.jobs', $payload);
        } catch (\Throwable $e) {
            logger('getPublicJobs failed: ' . $e->getMessage(), 'error');
            Response::error('Could not load jobs', 500);
        }
    }

    /**
     * Gather job fields from the request.
     *
     * @return array<string,mixed>
     */
    private function collectJobInput(): array
    {
        $keys = [
            'title', 'description', 'requirements', 'department', 'location',
            'job_type', 'salary_min', 'salary_max', 'currency', 'avatar_id',
            'status', 'ai_criteria', 'question_bank',
        ];
        $data = [];
        foreach ($keys as $key) {
            $value = $this->request->input($key);
            if ($value !== null) {
                $data[$key] = $value;
            }
        }
        return $data;
    }

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
     * @param array<string,mixed> $params
     */
    private function resolvePublicTenant(array $params): ?array
    {
        $subdomain = $params['tenantSubdomain']
            ?? $params['subdomain']
            ?? $this->request->get('subdomain');

        if ($subdomain !== null && $subdomain !== '') {
            return Database::instance()->fetch(
                'SELECT * FROM tenants WHERE subdomain = :s LIMIT 1',
                [':s' => (string) $subdomain]
            );
        }

        $tenant = (new Tenant())->resolve();
        if ($tenant !== null) {
            return $tenant;
        }

        if (isset($params['tenantId']) && (int) $params['tenantId'] > 0) {
            return Database::instance()->fetch(
                'SELECT * FROM tenants WHERE id = :id LIMIT 1',
                [':id' => (int) $params['tenantId']]
            );
        }
        return null;
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
