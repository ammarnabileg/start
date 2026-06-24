<?php
namespace App\Modules\Candidates;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\Validator;

/**
 * HTTP controller for candidates. Most actions require authentication and a
 * candidates.* permission; publicApply() is intentionally public so candidates
 * can apply to a job without an account.
 */
class CandidateController
{
    private CandidateService $service;
    private Auth $auth;
    private Request $request;
    private Database $db;

    public function __construct(?CandidateService $service = null)
    {
        $this->service = $service ?? new CandidateService();
        $this->auth = new Auth();
        $this->request = new Request();
        $this->db = Database::instance();
    }

    /**
     * List candidates (HTML view or JSON for AJAX/API).
     */
    public function index(array $params = []): void
    {
        $this->auth->requirePermission('candidates.view');
        $tenantId = $this->tenantId();

        $filters = array_filter([
            'search' => $this->request->get('search'),
            'status' => $this->request->get('status'),
            'job_id' => $this->request->get('job_id'),
        ], static fn($v) => $v !== null && $v !== '');

        $candidates = $this->service->getCandidates($tenantId, $filters);

        if ($this->wantsJson()) {
            Response::success(['candidates' => $candidates, 'filters' => $filters]);
            return;
        }
        Response::view('hr.candidates.index', [
            'candidates' => $candidates,
            'filters'    => $filters,
        ]);
    }

    /**
     * 360-degree candidate profile.
     */
    public function show(array $params = []): void
    {
        $this->auth->requirePermission('candidates.view');
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();

        $candidate = $this->service->getCandidate($id, $tenantId);
        if ($candidate === null) {
            $this->notFound('Candidate not found');
            return;
        }
        $profile = $this->service->getFullProfile($id);

        if ($this->wantsJson()) {
            Response::success($profile);
            return;
        }
        Response::view('hr.candidates.show', ['candidate' => $profile]);
    }

    /**
     * Render the create form.
     */
    public function create(array $params = []): void
    {
        $this->auth->requirePermission('candidates.create');
        if ($this->wantsJson()) {
            Response::success(['form' => 'candidate_create']);
            return;
        }
        Response::view('hr.candidates.create', []);
    }

    /**
     * Persist a new candidate.
     */
    public function store(array $params = []): void
    {
        $this->auth->requirePermission('candidates.create');
        $tenantId = $this->tenantId();
        $data = $this->request->all();

        [$ok, $errors] = (new Validator())->validate($data, [
            'email'      => 'required|email',
            'first_name' => 'max:120',
            'last_name'  => 'max:120',
        ]);
        if (!$ok) {
            Response::error('Validation failed', 422, $errors);
            return;
        }

        $candidate = $this->service->createCandidate([
            'tenant_id'    => $tenantId,
            'email'        => $data['email'],
            'first_name'   => $data['first_name'] ?? null,
            'last_name'    => $data['last_name'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'cv_url'       => $data['cv_url'] ?? null,
            'cv_text'      => $data['cv_text'] ?? null,
            'status'       => $data['status'] ?? 'new',
        ]);

        Response::success(['candidate' => $candidate], 'Candidate created', 201);
    }

    /**
     * Update an existing candidate.
     */
    public function update(array $params = []): void
    {
        $this->auth->requirePermission('candidates.edit');
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();

        $existing = $this->service->getCandidate($id, $tenantId);
        if ($existing === null) {
            $this->notFound('Candidate not found');
            return;
        }

        $data = $this->request->all();
        $allowed = ['email', 'first_name', 'last_name', 'phone', 'linkedin_url', 'cv_url', 'cv_text', 'status'];
        $update = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (isset($update['email'])) {
            [$ok, $errors] = (new Validator())->validate($update, ['email' => 'email']);
            if (!$ok) {
                Response::error('Validation failed', 422, $errors);
                return;
            }
        }
        if (empty($update)) {
            Response::error('No updatable fields supplied', 422);
            return;
        }

        $candidate = $this->service->updateCandidate($id, $update);
        Response::success(['candidate' => $candidate], 'Candidate updated');
    }

    /**
     * Add a candidate to a talent pool (pool_id from input).
     */
    public function addToTalentPool(array $params = []): void
    {
        $this->auth->requirePermission('candidates.edit');
        $tenantId = $this->tenantId();
        $candidateId = (int) ($params['id'] ?? $this->request->input('candidate_id', 0));
        $poolId = (int) $this->request->input('pool_id', 0);

        if ($candidateId <= 0 || $poolId <= 0) {
            Response::error('candidate_id and pool_id are required', 422);
            return;
        }

        $candidate = $this->service->getCandidate($candidateId, $tenantId);
        if ($candidate === null) {
            $this->notFound('Candidate not found');
            return;
        }
        // Ensure the pool belongs to the same tenant.
        $pool = $this->db->fetch(
            'SELECT * FROM talent_pools WHERE id = :id AND tenant_id = :tid LIMIT 1',
            [':id' => $poolId, ':tid' => $tenantId]
        );
        if ($pool === null) {
            $this->notFound('Talent pool not found');
            return;
        }

        $this->db->query(
            'INSERT IGNORE INTO talent_pool_candidates (pool_id, candidate_id) VALUES (:pid, :cid)',
            [':pid' => $poolId, ':cid' => $candidateId]
        );

        Response::success([
            'pool_id'      => $poolId,
            'candidate_id' => $candidateId,
        ], 'Candidate added to talent pool');
    }

    /**
     * Side-by-side comparison of multiple candidates.
     */
    public function compare(array $params = []): void
    {
        $this->auth->requirePermission('candidates.compare');
        $tenantId = $this->tenantId();

        $ids = $this->request->input('candidate_ids', []);
        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)), static fn($v) => $v !== '');
        }
        $ids = array_values(array_unique(array_map('intval', (array) $ids)));
        $ids = array_filter($ids, static fn($v) => $v > 0);

        if (count($ids) < 2) {
            Response::error('Provide at least two candidate_ids to compare', 422);
            return;
        }

        $comparison = [];
        foreach ($ids as $id) {
            $candidate = $this->service->getCandidate($id, $tenantId);
            if ($candidate === null) {
                continue;
            }
            $comparison[] = $this->service->getFullProfile($id);
        }

        if ($this->wantsJson()) {
            Response::success(['candidates' => $comparison]);
            return;
        }
        Response::view('hr.candidates.compare', ['candidates' => $comparison]);
    }

    /**
     * PUBLIC: a candidate applies to a job. No authentication required.
     *
     * Resolves the job (and therefore the tenant), creates-or-finds the
     * candidate by email within that tenant, optionally stores an uploaded CV,
     * and creates an application at pipeline_stage 'applied'.
     */
    public function publicApply(array $params = []): void
    {
        $jobId = (int) ($params['jobId'] ?? $params['job_id'] ?? 0);
        if ($jobId <= 0) {
            Response::error('Invalid job', 404);
            return;
        }

        $job = $this->db->fetch('SELECT * FROM jobs WHERE id = :id LIMIT 1', [':id' => $jobId]);
        if ($job === null) {
            Response::error('Job not found', 404);
            return;
        }
        if (($job['status'] ?? '') !== 'published') {
            Response::error('This job is not accepting applications', 403);
            return;
        }
        $tenantId = (int) $job['tenant_id'];

        $data = $this->request->all();
        [$ok, $errors] = (new Validator())->validate($data, [
            'email'      => 'required|email',
            'first_name' => 'max:120',
            'last_name'  => 'max:120',
        ]);
        if (!$ok) {
            Response::error('Validation failed', 422, $errors);
            return;
        }

        $email = (string) $data['email'];
        $repo = $this->service->getRepository();

        // Find or create the candidate within the job's tenant.
        $candidate = $repo->findByEmail($email, $tenantId);

        // Handle optional CV upload.
        $cvUrl = null;
        $cvText = $data['cv_text'] ?? null;
        $file = $this->request->file('cv');
        if ($file !== null && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
            $stored = $this->storeUpload($file, $tenantId);
            if (isset($stored['error'])) {
                Response::error($stored['error'], 422);
                return;
            }
            $cvUrl = $stored['url'];
        }

        if ($candidate === null) {
            $candidateId = $repo->create([
                'tenant_id'    => $tenantId,
                'email'        => $email,
                'first_name'   => $data['first_name'] ?? null,
                'last_name'    => $data['last_name'] ?? null,
                'phone'        => $data['phone'] ?? null,
                'linkedin_url' => $data['linkedin_url'] ?? null,
                'cv_url'       => $cvUrl,
                'cv_text'      => $cvText,
                'status'       => 'new',
            ]);
        } else {
            $candidateId = (int) $candidate['id'];
            // Update CV details / contact info if newly supplied.
            $patch = [];
            if ($cvUrl !== null) {
                $patch['cv_url'] = $cvUrl;
            }
            if (!empty($cvText)) {
                $patch['cv_text'] = $cvText;
            }
            foreach (['first_name', 'last_name', 'phone', 'linkedin_url'] as $field) {
                if (empty($candidate[$field]) && !empty($data[$field])) {
                    $patch[$field] = $data[$field];
                }
            }
            if (!empty($patch)) {
                $repo->update($candidateId, $patch);
            }
        }

        // Avoid duplicate applications for the same job.
        $existingApp = $this->db->fetch(
            'SELECT * FROM applications WHERE job_id = :jid AND candidate_id = :cid LIMIT 1',
            [':jid' => $jobId, ':cid' => $candidateId]
        );
        if ($existingApp !== null) {
            $applicationId = (int) $existingApp['id'];
        } else {
            $applicationId = $this->db->insert('applications', [
                'tenant_id'      => $tenantId,
                'job_id'         => $jobId,
                'candidate_id'   => $candidateId,
                'status'         => 'applied',
                'stage'          => 'applied',
                'current_stage'  => 'applied',
                'pipeline_stage' => 'applied',
            ]);
        }

        Response::success([
            'application_id' => $applicationId,
            'candidate_id'   => $candidateId,
            'job_id'         => $jobId,
            'job_title'      => $job['title'] ?? null,
        ], 'Application submitted successfully', 201);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Store an uploaded CV file into storage/uploads. Returns ['url'=>..] or
     * ['error'=>..].
     *
     * @param array<string,mixed> $file
     * @return array<string,string>
     */
    private function storeUpload(array $file, int $tenantId): array
    {
        $config = function_exists('config') ? config('app') : require dirname(__DIR__, 2) . '/config/app.php';
        $uploadDir = $config['storage']['uploads'] ?? (dirname(__DIR__, 2) . '/storage/uploads');

        $maxBytes = 10 * 1024 * 1024; // 10 MB
        if (($file['size'] ?? 0) > $maxBytes) {
            return ['error' => 'CV file is too large (max 10MB)'];
        }

        $originalName = (string) ($file['name'] ?? 'cv');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'rtf', 'txt', 'odt'];
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            return ['error' => 'Unsupported CV file type'];
        }

        $targetDir = rtrim($uploadDir, '/') . '/cv/' . $tenantId;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return ['error' => 'Unable to store uploaded file'];
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = $targetDir . '/' . $filename;

        $tmp = (string) ($file['tmp_name'] ?? '');
        $moved = is_uploaded_file($tmp)
            ? @move_uploaded_file($tmp, $targetPath)
            : @rename($tmp, $targetPath);
        if (!$moved) {
            return ['error' => 'Failed to save uploaded file'];
        }

        // Public-facing relative URL under /storage/uploads.
        $url = '/storage/uploads/cv/' . $tenantId . '/' . $filename;
        return ['url' => $url, 'path' => $targetPath];
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

    private function notFound(string $message): void
    {
        if ($this->wantsJson()) {
            Response::error($message, 404);
            return;
        }
        Response::error($message, 404);
    }
}
