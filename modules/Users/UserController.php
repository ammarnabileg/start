<?php
namespace App\Modules\Users;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\Validator;

/**
 * User management controller. Reads are gated by users.view, mutations by
 * users.manage. All actions operate within the authenticated tenant.
 */
class UserController
{
    private Auth $auth;
    private UserService $service;
    private Request $request;

    public function __construct(?Auth $auth = null, ?UserService $service = null, ?Request $request = null)
    {
        $this->auth = $auth ?? new Auth();
        $this->service = $service ?? new UserService();
        $this->request = $request ?? new Request();
    }

    /**
     * List the tenant's users.
     */
    public function index(array $params = []): void
    {
        $this->auth->requirePermission('users.view');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        $users = $this->service->getUsers($tenantId);

        if ($this->wantsJson()) {
            Response::success(['users' => $users]);
            return;
        }
        Response::view('hr.users.index', ['users' => $users]);
    }

    /**
     * Render the create-user form.
     */
    public function create(array $params = []): void
    {
        $this->auth->requirePermission('users.manage');

        Response::view('hr.users.create', [
            'csrf_token' => Request::csrfToken(),
        ]);
    }

    /**
     * Persist a new user.
     */
    public function store(array $params = []): void
    {
        $this->auth->requirePermission('users.manage');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $data = [
                'email'      => trim((string) $this->request->input('email', '')),
                'password'   => (string) $this->request->input('password', ''),
                'first_name' => $this->request->input('first_name'),
                'last_name'  => $this->request->input('last_name'),
                'status'     => $this->request->input('status', 'active'),
                'role_id'    => $this->request->input('role_id'),
            ];

            [$valid, $errors] = (new Validator())->validate($data, [
                'email'    => 'required|email',
                'password' => 'required|min:8',
                'status'   => 'in:active,inactive',
            ]);
            if (!$valid) {
                Response::error('Validation failed', 422, $errors);
                return;
            }

            // Email must be unique within the tenant (the global unique rule is
            // not sufficient for a multi-tenant users table).
            if ($this->service->getRepository()->findByEmail($data['email'], $tenantId) !== null) {
                Response::error('Validation failed', 422, ['email' => ['Email is already taken.']]);
                return;
            }

            // A supplied role must belong to this tenant (or be a system role).
            if (!empty($data['role_id']) && !$this->roleBelongsToTenant((int) $data['role_id'], $tenantId)) {
                Response::error('Invalid role for this tenant', 422);
                return;
            }

            $userId = $this->service->createUser($data, $tenantId);
            $user = $this->service->getUser($userId);

            Response::success(['user' => $user], 'User created', 201);
        } catch (\Throwable $e) {
            logger('User store failed: ' . $e->getMessage(), 'error');
            Response::error('Could not create user', 500);
        }
    }

    /**
     * Render the edit-user form.
     */
    public function edit(array $params = []): void
    {
        $this->auth->requirePermission('users.manage');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        $user = $this->loadTenantUser($id, $tenantId);
        if ($user === null) {
            Response::error('User not found', 404);
            return;
        }

        Response::view('hr.users.edit', [
            'user'       => $user,
            'csrf_token' => Request::csrfToken(),
        ]);
    }

    /**
     * Update an existing user.
     */
    public function update(array $params = []): void
    {
        $this->auth->requirePermission('users.manage');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $id = (int) ($params['id'] ?? 0);
            $user = $this->loadTenantUser($id, $tenantId);
            if ($user === null) {
                Response::error('User not found', 404);
                return;
            }

            $data = [
                'email'      => $this->request->input('email'),
                'password'   => $this->request->input('password'),
                'first_name' => $this->request->input('first_name'),
                'last_name'  => $this->request->input('last_name'),
                'status'     => $this->request->input('status'),
                'role_id'    => $this->request->input('role_id'),
            ];

            $rules = ['status' => 'in:active,inactive'];
            if ($data['email'] !== null && $data['email'] !== '') {
                $rules['email'] = 'email';
            }
            if (!empty($data['password'])) {
                $rules['password'] = 'min:8';
            }

            [$valid, $errors] = (new Validator())->validate($data, $rules);
            if (!$valid) {
                Response::error('Validation failed', 422, $errors);
                return;
            }

            // Tenant-scoped email-uniqueness check (ignoring the user itself).
            if ($data['email'] !== null && $data['email'] !== '') {
                $existing = $this->service->getRepository()->findByEmail(trim((string) $data['email']), $tenantId);
                if ($existing !== null && (int) $existing['id'] !== $id) {
                    Response::error('Validation failed', 422, ['email' => ['Email is already taken.']]);
                    return;
                }
            }

            if (!empty($data['role_id']) && !$this->roleBelongsToTenant((int) $data['role_id'], $tenantId)) {
                Response::error('Invalid role for this tenant', 422);
                return;
            }

            $this->service->updateUser($id, $data);
            $updated = $this->service->getUser($id);

            Response::success(['user' => $updated], 'User updated');
        } catch (\Throwable $e) {
            logger('User update failed: ' . $e->getMessage(), 'error');
            Response::error('Could not update user', 500);
        }
    }

    /**
     * Delete a user.
     */
    public function delete(array $params = []): void
    {
        $this->auth->requirePermission('users.manage');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $id = (int) ($params['id'] ?? 0);
            $user = $this->loadTenantUser($id, $tenantId);
            if ($user === null) {
                Response::error('User not found', 404);
                return;
            }

            // Prevent self-deletion to avoid locking out the acting admin.
            if ($id === $this->auth->id()) {
                Response::error('You cannot delete your own account', 422);
                return;
            }

            $this->service->deleteUser($id);
            Response::success(['id' => $id], 'User deleted');
        } catch (\Throwable $e) {
            logger('User delete failed: ' . $e->getMessage(), 'error');
            Response::error('Could not delete user', 500);
        }
    }

    /**
     * Assign / change a user's role.
     */
    public function updateRole(array $params = []): void
    {
        $this->auth->requirePermission('users.manage');

        $tenantId = $this->currentTenantId();
        if ($tenantId === null) {
            Response::error('No tenant context', 400);
            return;
        }

        try {
            $id = (int) ($params['id'] ?? 0);
            $user = $this->loadTenantUser($id, $tenantId);
            if ($user === null) {
                Response::error('User not found', 404);
                return;
            }

            $roleId = (int) $this->request->input('role_id', 0);
            if ($roleId <= 0) {
                Response::error('A valid role_id is required', 422);
                return;
            }
            if (!$this->roleBelongsToTenant($roleId, $tenantId)) {
                Response::error('Invalid role for this tenant', 422);
                return;
            }

            $this->service->assignRole($id, $roleId);
            $updated = $this->service->getUser($id);

            Response::success(['user' => $updated], 'Role assigned');
        } catch (\Throwable $e) {
            logger('updateRole failed: ' . $e->getMessage(), 'error');
            Response::error('Could not assign role', 500);
        }
    }

    /**
     * Load a user and confirm it belongs to the given tenant.
     */
    private function loadTenantUser(int $id, int $tenantId): ?array
    {
        $user = $this->service->getUser($id);
        if ($user === null || (int) ($user['tenant_id'] ?? 0) !== $tenantId) {
            return null;
        }
        return $user;
    }

    /**
     * A role is usable by a tenant if it is that tenant's role or a global
     * (tenant_id NULL) system role.
     */
    private function roleBelongsToTenant(int $roleId, int $tenantId): bool
    {
        $row = Database::instance()->fetch(
            'SELECT id FROM roles WHERE id = :id AND (tenant_id = :tid OR tenant_id IS NULL) LIMIT 1',
            [':id' => $roleId, ':tid' => $tenantId]
        );
        return $row !== null;
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

    private function wantsJson(): bool
    {
        if ($this->request->isAjax() || $this->request->bearerToken() !== null) {
            return true;
        }
        $accept = $this->request->header('Accept') ?? '';
        return stripos($accept, 'application/json') !== false;
    }
}
