<?php
/**
 * InterviewController - HR-facing web controller for AI interviews.
 *
 * Renders the interviews list and the full evaluation report, and handles
 * scheduling a human (follow-up) interview. It follows the platform controller
 * convention: global namespace, static methods, the global Auth/Request/
 * Response/Database helpers, and rendering views through the app layout.
 *
 * Routes (wired by HRRouter / the front controller):
 *   GET  /ai-interviews            -> index()
 *   GET  /ai-interviews/{id}       -> report($id)
 *   POST /human-interviews         -> schedule()
 */
class InterviewController
{
    /**
     * List AI interviews for the current tenant (paginated, filterable).
     */
    public static function index(Request $request): void
    {
        $tenantId = self::tenantId();

        $filters = [
            'status'         => (string) $request->get('status', ''),
            'type'           => (string) $request->get('type', ''),
            'job_id'         => (int) $request->get('job_id', 0),
            'recommendation' => (string) $request->get('recommendation', ''),
            'search'         => (string) $request->get('q', ''),
            'page'           => (int) $request->get('page', 1),
            'per_page'       => 25,
        ];
        // Drop empty filters.
        $filters = array_filter($filters, static fn($v) => $v !== '' && $v !== 0);
        $filters['page']     = max(1, (int) $request->get('page', 1));
        $filters['per_page'] = 25;

        $repo = new \Modules\Interviews\InterviewRepository();
        $result = $repo->getForTenant($tenantId, $filters);

        $data = [
            'pageTitle'   => 'AI Interviews',
            'interviews'  => $result['data'],
            'pagination'  => [
                'total'    => $result['total'],
                'pages'    => $result['pages'],
                'page'     => $result['page'],
                'per_page' => $result['per_page'],
            ],
            'filters'     => $filters,
        ];

        // JSON for AJAX consumers; HTML view otherwise.
        if ($request->expectsJson()) {
            Response::paginated($result['data'], $data['pagination']);
        }

        self::render('hr/interviews/index', $data);
    }

    /**
     * Show the full evaluation report for one interview.
     */
    public static function report(int $id, ?Request $request = null): void
    {
        $tenantId = self::tenantId();
        $repo = new \Modules\Interviews\InterviewRepository();
        $report = $repo->getReportData($id, $tenantId);

        if ($report === null) {
            http_response_code(404);
            if ($request && $request->expectsJson()) {
                Response::error('Interview not found', 404);
            }
            self::render('hr/interviews/report', [
                'pageTitle' => 'Interview Report',
                'report'    => null,
                'notFound'  => true,
            ]);
            return;
        }

        // Lazily evaluate if the interview finished but has no stored evaluation.
        if (empty($report['evaluation']) && !empty($report['messages'])
            && ($report['status'] ?? '') === 'completed') {
            try {
                $service = new \Modules\Interviews\InterviewService();
                $report['evaluation'] = $service->getEvaluation($id);
            } catch (\Throwable $e) {
                // Leave evaluation empty; the view handles the missing state.
            }
        }

        if ($request && $request->expectsJson()) {
            Response::success($report);
        }

        self::render('hr/interviews/report', [
            'pageTitle' => 'Interview Report',
            'report'    => $report,
            'id'        => $id,
        ]);
    }

    /**
     * Schedule a human (follow-up) interview for an application.
     *
     * Expects: application_id, scheduled_at, type, duration_minutes,
     *          meeting_link|location, meeting_platform, notes.
     */
    public static function schedule(Request $request): void
    {
        $tenantId = self::tenantId();
        $userId   = self::userId();

        $applicationId = (int) $request->input('application_id', 0);
        $scheduledAt   = trim((string) $request->input('scheduled_at', ''));
        $type          = (string) $request->input('type', 'technical');
        $duration      = (int) $request->input('duration_minutes', 60);

        $errors = [];
        if ($applicationId <= 0) {
            $errors['application_id'] = 'An application is required.';
        }
        if ($scheduledAt === '' || strtotime($scheduledAt) === false) {
            $errors['scheduled_at'] = 'A valid date/time is required.';
        }
        $validTypes = ['technical', 'managerial', 'hr', 'final', 'panel'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'technical';
        }

        if (!empty($errors)) {
            if ($request->expectsJson()) {
                Response::error('Validation failed', 422, $errors);
            }
            $_SESSION['flash_error'] = reset($errors);
            Response::redirect('/human-interviews');
        }

        $db = \Database::getInstance();

        // Verify the application belongs to this tenant.
        $application = $db->fetch(
            'SELECT id, tenant_id FROM applications WHERE id = ? AND tenant_id = ? LIMIT 1',
            [$applicationId, $tenantId]
        );
        if (!$application) {
            if ($request->expectsJson()) {
                Response::error('Application not found', 404);
            }
            Response::redirect('/human-interviews');
        }

        $humanInterviewId = $db->insert('human_interviews', [
            'tenant_id'        => $tenantId,
            'application_id'   => $applicationId,
            'scheduled_at'     => date('Y-m-d H:i:s', strtotime($scheduledAt)),
            'duration_minutes' => $duration > 0 ? $duration : 60,
            'meeting_link'     => (string) $request->input('meeting_link', '') ?: null,
            'meeting_platform' => (string) $request->input('meeting_platform', '') ?: null,
            'location'         => (string) $request->input('location', '') ?: null,
            'type'             => $type,
            'status'           => 'scheduled',
            'notes'            => (string) $request->input('notes', '') ?: null,
            'created_by'       => $userId,
        ]);

        // Advance the pipeline stage to the appropriate interview phase.
        $stage = match ($type) {
            'managerial' => 'manager_interview',
            'final'      => 'final_review',
            default      => 'tech_interview',
        };
        $db->update('applications', ['stage' => $stage], ['id' => $applicationId]);

        if ($request->expectsJson()) {
            Response::success(
                ['human_interview_id' => $humanInterviewId, 'stage' => $stage],
                'Interview scheduled'
            );
        }

        $_SESSION['flash_success'] = 'Interview scheduled successfully.';
        Response::redirect('/human-interviews');
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /**
     * Render a view inside the app layout (mirrors HRRouter::render).
     */
    private static function render(string $view, array $data): void
    {
        $request = new Request();
        $data['request'] = $request;
        $data['user'] = class_exists('Auth') ? Auth::user() : null;
        extract($data);

        $viewFile = VIEWS_PATH . '/' . str_replace('.', '/', $view) . '.php';
        ob_start();
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo "<p class='p-8 text-gray-500'>View coming soon: {$view}</p>";
        }
        $content = ob_get_clean();

        $layout = VIEWS_PATH . '/layouts/app.php';
        if (file_exists($layout)) {
            require $layout;
        } else {
            echo $content;
        }
    }

    private static function tenantId(): int
    {
        $db = \Database::getInstance();
        $current = method_exists($db, 'getTenantId') ? $db->getTenantId() : null;
        if ($current) {
            return (int) $current;
        }
        $user = class_exists('Auth') ? Auth::user() : null;
        return (int) ($user['tenant_id'] ?? ($_SESSION['user']['tenant_id'] ?? 0));
    }

    private static function userId(): int
    {
        $user = class_exists('Auth') ? Auth::user() : null;
        return (int) ($user['id'] ?? ($_SESSION['user']['id'] ?? 0));
    }
}
