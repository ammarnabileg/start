<?php
namespace App\Modules\TalentPool;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\Validator;

/**
 * HTTP controller for talent pools. All actions require authentication and a
 * talent_pool.* permission (view to read, manage to mutate).
 */
class TalentPoolController
{
    private TalentPoolService $service;
    private Auth $auth;
    private Request $request;
    private Database $db;

    public function __construct(?TalentPoolService $service = null)
    {
        $this->service = $service ?? new TalentPoolService();
        $this->auth = new Auth();
        $this->request = new Request();
        $this->db = Database::instance();
    }

    public function index(array $params = []): void
    {
        $this->auth->requirePermission('talent_pool.view');
        $tenantId = $this->tenantId();
        $pools = $this->service->getPools($tenantId);

        if ($this->wantsJson()) {
            Response::success(['pools' => $pools]);
            return;
        }
        Response::view('hr.talent-pool', ['pools' => $pools]);
    }

    public function show(array $params = []): void
    {
        $this->auth->requirePermission('talent_pool.view');
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();

        // Optional in-pool search.
        $query = (string) $this->request->get('q', '');
        $pool = $this->service->getPool($id, $tenantId);
        if ($pool === null) {
            Response::error('Talent pool not found', 404);
            return;
        }
        if ($query !== '') {
            $pool['candidates'] = $this->service->searchPool($id, $query);
            $pool['candidate_count'] = count($pool['candidates']);
            $pool['search'] = $query;
        }

        if ($this->wantsJson()) {
            Response::success(['pool' => $pool]);
            return;
        }
        Response::view('hr.talent-pool-show', ['pool' => $pool]);
    }

    public function create(array $params = []): void
    {
        $this->auth->requirePermission('talent_pool.manage');
        if ($this->wantsJson()) {
            Response::success(['form' => 'talent_pool_create']);
            return;
        }
        Response::view('hr.talent-pool-create', []);
    }

    public function store(array $params = []): void
    {
        $this->auth->requirePermission('talent_pool.manage');
        $tenantId = $this->tenantId();
        $data = $this->request->all();

        [$ok, $errors] = (new Validator())->validate($data, [
            'name' => 'required|max:180',
        ]);
        if (!$ok) {
            Response::error('Validation failed', 422, $errors);
            return;
        }

        $pool = $this->service->createPool([
            'tenant_id'   => $tenantId,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'created_by'  => $this->auth->id(),
        ]);

        Response::success(['pool' => $pool], 'Talent pool created', 201);
    }

    public function update(array $params = []): void
    {
        $this->auth->requirePermission('talent_pool.manage');
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();
        $data = $this->request->all();

        if (array_key_exists('name', $data)) {
            [$ok, $errors] = (new Validator())->validate($data, ['name' => 'required|max:180']);
            if (!$ok) {
                Response::error('Validation failed', 422, $errors);
                return;
            }
        }

        $pool = $this->service->updatePool($id, $tenantId, $data);
        if ($pool === null) {
            Response::error('Talent pool not found', 404);
            return;
        }
        Response::success(['pool' => $pool], 'Talent pool updated');
    }

    public function delete(array $params = []): void
    {
        $this->auth->requirePermission('talent_pool.manage');
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();

        $deleted = $this->service->deletePool($id, $tenantId);
        if (!$deleted) {
            Response::error('Talent pool not found', 404);
            return;
        }
        Response::success(['id' => $id], 'Talent pool deleted');
    }

    /**
     * Add a candidate to a pool. pool id from route, candidate_id from input.
     */
    public function addCandidate(array $params = []): void
    {
        $this->auth->requirePermission('talent_pool.manage');
        $tenantId = $this->tenantId();
        $poolId = (int) ($params['id'] ?? $this->request->input('pool_id', 0));
        $candidateId = (int) $this->request->input('candidate_id', 0);

        if ($poolId <= 0 || $candidateId <= 0) {
            Response::error('pool_id and candidate_id are required', 422);
            return;
        }
        if (!$this->ownsPool($poolId, $tenantId)) {
            Response::error('Talent pool not found', 404);
            return;
        }
        if (!$this->ownsCandidate($candidateId, $tenantId)) {
            Response::error('Candidate not found', 404);
            return;
        }

        $this->service->addCandidate($poolId, $candidateId);
        Response::success(['pool_id' => $poolId, 'candidate_id' => $candidateId], 'Candidate added to pool');
    }

    /**
     * Remove a candidate from a pool.
     */
    public function removeCandidate(array $params = []): void
    {
        $this->auth->requirePermission('talent_pool.manage');
        $tenantId = $this->tenantId();
        $poolId = (int) ($params['id'] ?? $this->request->input('pool_id', 0));
        $candidateId = (int) ($params['candidateId'] ?? $this->request->input('candidate_id', 0));

        if ($poolId <= 0 || $candidateId <= 0) {
            Response::error('pool_id and candidate_id are required', 422);
            return;
        }
        if (!$this->ownsPool($poolId, $tenantId)) {
            Response::error('Talent pool not found', 404);
            return;
        }

        $this->service->removeCandidate($poolId, $candidateId);
        Response::success(['pool_id' => $poolId, 'candidate_id' => $candidateId], 'Candidate removed from pool');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function ownsPool(int $poolId, int $tenantId): bool
    {
        return $this->db->fetch(
            'SELECT id FROM talent_pools WHERE id = :id AND tenant_id = :tid LIMIT 1',
            [':id' => $poolId, ':tid' => $tenantId]
        ) !== null;
    }

    private function ownsCandidate(int $candidateId, int $tenantId): bool
    {
        return $this->db->fetch(
            'SELECT id FROM candidates WHERE id = :id AND tenant_id = :tid LIMIT 1',
            [':id' => $candidateId, ':tid' => $tenantId]
        ) !== null;
    }

    private function tenantId(): int
    {
        (new Tenant())->resolve();
        $tenantId = (new Tenant())->currentId();
        if ($tenantId === null) {
            $user = $this->auth->user();
            $tenantId = $user && $user['tenant_id'] !== null ? (int) $user['tenant_id'] : 0;
        }
        if ($tenantId > 0) {
            $this->db->setTenantId($tenantId);
        }
        return (int) $tenantId;
    }

    private function wantsJson(): bool
    {
        return $this->request->isAjax()
            || str_contains((string) $this->request->header('Accept'), 'application/json')
            || $this->request->bearerToken() !== null;
    }
}
