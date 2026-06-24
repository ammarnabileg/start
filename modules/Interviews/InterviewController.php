<?php
namespace App\Modules\Interviews;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;

/**
 * HTTP controller for interviews.
 *
 * Public (token-based, NO auth): getRoom, startInterview, sendMessage,
 * getNextQuestion, complete — these are how a candidate takes the interview.
 *
 * Protected (auth + permission): index, show, create, store, report.
 */
class InterviewController
{
    private InterviewService $service;
    private Auth $auth;
    private Request $request;
    private Database $db;

    public function __construct(?InterviewService $service = null)
    {
        $this->service = $service ?? new InterviewService();
        $this->auth = new Auth();
        $this->request = new Request();
        $this->db = Database::instance();
    }

    // ------------------------------------------------------------------
    // Protected (HR) actions
    // ------------------------------------------------------------------

    public function index(array $params = []): void
    {
        $this->auth->requirePermission('interviews.view');
        $tenantId = $this->tenantId();

        $filters = array_filter([
            'type'   => $this->request->get('type'),
            'status' => $this->request->get('status'),
            'date'   => $this->request->get('date'),
        ], static fn($v) => $v !== null && $v !== '');

        $interviews = $this->service->getInterviews($tenantId, $filters);

        if ($this->wantsJson()) {
            Response::success(['interviews' => $interviews, 'filters' => $filters]);
            return;
        }
        Response::view('hr.interviews.index', ['interviews' => $interviews, 'filters' => $filters]);
    }

    public function show(array $params = []): void
    {
        $this->auth->requirePermission('interviews.view');
        $id = (int) ($params['id'] ?? 0);
        if (!$this->ownsInterview($id)) {
            Response::error('Interview not found', 404);
            return;
        }
        $report = $this->service->getReport($id);

        if ($this->wantsJson()) {
            Response::success($report);
            return;
        }
        Response::view('hr.interviews.show', ['report' => $report]);
    }

    /**
     * Render the interview setup form for an application id.
     */
    public function create(array $params = []): void
    {
        $this->auth->requirePermission('interviews.create');
        $applicationId = (int) ($params['id'] ?? $params['applicationId'] ?? $this->request->get('application_id', 0));

        if ($this->wantsJson()) {
            Response::success([
                'application_id' => $applicationId,
                'types'          => ['ai_text', 'ai_voice', 'ai_video'],
            ]);
            return;
        }
        Response::view('hr.interviews.create', ['application_id' => $applicationId]);
    }

    /**
     * Create an interview and "send" the invite email.
     */
    public function store(array $params = []): void
    {
        $this->auth->requirePermission('interviews.create');
        $tenantId = $this->tenantId();

        $applicationId = (int) $this->request->input('application_id', 0);
        $type = (string) $this->request->input('type', 'ai_text');

        if ($applicationId <= 0) {
            Response::error('application_id is required', 422);
            return;
        }

        // Ensure the application belongs to the current tenant.
        $application = $this->db->fetch(
            'SELECT a.* FROM applications a INNER JOIN jobs j ON j.id = a.job_id
                WHERE a.id = :id AND j.tenant_id = :tid LIMIT 1',
            [':id' => $applicationId, ':tid' => $tenantId]
        );
        if ($application === null) {
            Response::error('Application not found', 404);
            return;
        }

        $interview = $this->service->createInterview($applicationId, $type);

        // Move the application into the AI interview stage.
        $this->db->update('applications', ['pipeline_stage' => 'ai_interview'], ['id' => $applicationId]);

        // Build the invite (pretend-send via mailer).
        $invite = $this->service->generateInviteEmail((int) $interview['id']);
        $this->pretendSendEmail($invite['subject'] ?? '', $invite['body'] ?? '');

        Response::success([
            'interview' => $interview,
            'token'     => $interview['token'],
            'room_url'  => $invite['room_url'] ?? null,
            'invite'    => ['subject' => $invite['subject'] ?? '', 'sent' => true],
        ], 'Interview created and invite sent', 201);
    }

    /**
     * Authenticated interview report.
     */
    public function report(array $params = []): void
    {
        $this->auth->requirePermission('interviews.report');
        $id = (int) ($params['id'] ?? 0);
        if (!$this->ownsInterview($id)) {
            Response::error('Interview not found', 404);
            return;
        }
        $report = $this->service->getReport($id);

        if ($this->wantsJson()) {
            Response::success($report);
            return;
        }
        Response::view('hr.interviews.report', ['report' => $report]);
    }

    // ------------------------------------------------------------------
    // Public (token) actions — NO auth
    // ------------------------------------------------------------------

    /**
     * PUBLIC: interview room bootstrap data for the candidate.
     */
    public function getRoom(array $params = []): void
    {
        $token = (string) ($params['token'] ?? '');
        $repo = $this->service->getRepository();
        $interview = $repo->findByToken($token);
        if ($interview === null) {
            $this->publicError('Interview not found', 404);
            return;
        }

        $report = $this->service->getReport((int) $interview['id']);
        $job = $report['job'] ?? [];
        $context = $this->roomAvatar($job);

        $room = [
            'token'        => $token,
            'type'         => $interview['type'],
            'status'       => $interview['status'],
            'job_title'    => $job['title'] ?? null,
            'company'      => $report['interview']['company'] ?? ($this->companyName($job)),
            'avatar'       => $context,
            'messages'     => array_map(static function (array $m): array {
                return [
                    'role'      => $m['role'],
                    'content'   => $m['content'],
                    'timestamp' => $m['timestamp'] ?? null,
                ];
            }, $report['messages'] ?? []),
            'started_at'   => $interview['started_at'] ?? null,
            'completed_at' => $interview['completed_at'] ?? null,
        ];

        if ($this->wantsView()) {
            Response::view('interview.room', ['room' => $room]);
            return;
        }
        Response::success($room);
    }

    /**
     * PUBLIC: start the interview, returning the opening message.
     */
    public function startInterview(array $params = []): void
    {
        $token = (string) ($params['token'] ?? '');
        try {
            $result = $this->service->startInterview($token);
        } catch (\Throwable $e) {
            $this->publicError($e->getMessage(), 400);
            return;
        }
        Response::success($result);
    }

    /**
     * PUBLIC: candidate sends a message; returns the AI's reply.
     */
    public function sendMessage(array $params = []): void
    {
        $token = (string) ($params['token'] ?? '');
        $message = (string) $this->request->input('message', '');
        if (trim($message) === '') {
            $this->publicError('Message is required', 422);
            return;
        }
        try {
            $result = $this->service->processMessage($token, $message);
        } catch (\Throwable $e) {
            $this->publicError($e->getMessage(), 400);
            return;
        }
        Response::success($result);
    }

    /**
     * PUBLIC (GET): fetch the next AI question without sending a message.
     */
    public function getNextQuestion(array $params = []): void
    {
        $token = (string) ($params['token'] ?? '');
        try {
            $result = $this->service->getNextQuestion($token);
        } catch (\Throwable $e) {
            $this->publicError($e->getMessage(), 400);
            return;
        }
        Response::success($result);
    }

    /**
     * PUBLIC: complete the interview and trigger evaluation.
     */
    public function complete(array $params = []): void
    {
        $token = (string) ($params['token'] ?? '');
        try {
            $result = $this->service->completeInterview($token);
        } catch (\Throwable $e) {
            $this->publicError($e->getMessage(), 400);
            return;
        }
        Response::success($result, 'Interview completed');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $job
     */
    private function roomAvatar(array $job): ?array
    {
        if (empty($job['avatar_id'])) {
            return null;
        }
        $avatar = $this->db->fetch(
            'SELECT id, heygen_avatar_id, name, preview_url, voice_id, language FROM avatars WHERE id = :id LIMIT 1',
            [':id' => (int) $job['avatar_id']]
        );
        return $avatar ?: null;
    }

    /**
     * @param array<string,mixed> $job
     */
    private function companyName(array $job): ?string
    {
        if (empty($job['tenant_id'])) {
            return null;
        }
        $row = $this->db->fetch('SELECT name FROM tenants WHERE id = :id LIMIT 1', [':id' => (int) $job['tenant_id']]);
        return $row['name'] ?? null;
    }

    private function ownsInterview(int $interviewId): bool
    {
        if ($interviewId <= 0) {
            return false;
        }
        $tenantId = $this->tenantId();
        $row = $this->db->fetch(
            'SELECT i.id FROM interviews i
                INNER JOIN applications a ON a.id = i.application_id
                INNER JOIN jobs j ON j.id = a.job_id
                WHERE i.id = :id AND j.tenant_id = :tid LIMIT 1',
            [':id' => $interviewId, ':tid' => $tenantId]
        );
        return $row !== null;
    }

    private function pretendSendEmail(string $subject, string $body): void
    {
        // Real delivery is handled by a mailer service elsewhere; here we log so
        // the action is observable in non-production environments.
        logger('Interview invite email queued: ' . $subject, 'info');
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

    private function wantsView(): bool
    {
        return !$this->wantsJson();
    }

    private function publicError(string $message, int $status): void
    {
        Response::error($message, $status);
    }
}
