<?php
declare(strict_types=1);

/**
 * api/v1/interviews.php - Interview room REST API (public, token-authenticated).
 *
 * Powers the candidate-facing AI interview experience. Every endpoint is keyed
 * by the application's interview_link_token (validated against the applications
 * table); no login is required, but invalid/expired/used tokens are rejected.
 *
 * Routes (relative to /api/v1):
 *   POST /interviews/{token}/start     -> begin the session, return opening message
 *   POST /interviews/{token}/message   -> {message} process answer, return next turn
 *   GET  /interviews/{token}/status    -> current interview state
 *   POST /interviews/{token}/complete  -> force-complete (e.g. on page close)
 *   POST /interviews/{token}/feedback  -> {rating, feedback, suggestions}
 */
class InterviewApi
{
    private Request $request;
    /** @var object Global \Database singleton. */
    private $db;
    private \Modules\Interviews\InterviewService $service;
    private \Modules\Interviews\InterviewRepository $repo;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->db      = \Database::getInstance();
        $this->service = new \Modules\Interviews\InterviewService();
        $this->repo    = new \Modules\Interviews\InterviewRepository();
    }

    /**
     * @param string[] $segments e.g. ['interviews', '{token}', 'start']
     */
    public function dispatch(array $segments, string $method): void
    {
        $token  = (string) ($segments[1] ?? '');
        $action = (string) ($segments[2] ?? '');

        if ($token === '') {
            Response::error('Missing interview token.', 400);
        }

        // Validate the token once; all actions need a valid application.
        $application = $this->service->validateToken($token);
        if ($application === null) {
            Response::error('This interview link is invalid or has expired.', 404);
        }

        switch ($action) {
            case 'start':
                $this->requireMethod($method, 'POST');
                $this->start($application);
                break;

            case 'message':
                $this->requireMethod($method, 'POST');
                $this->message($application);
                break;

            case 'status':
                $this->requireMethod($method, 'GET');
                $this->status($application);
                break;

            case 'complete':
                $this->requireMethod($method, 'POST');
                $this->complete($application);
                break;

            case 'feedback':
                $this->requireMethod($method, 'POST');
                $this->feedback($application);
                break;

            default:
                Response::error('Unknown interview action.', 404);
        }
    }

    // ==================================================================
    // Endpoints
    // ==================================================================

    private function start(array $application): void
    {
        $applicationId = (int) $application['id'];

        // If a completed interview already exists, do not allow a restart.
        $existing = $this->repo->findByApplicationId($applicationId);
        if ($existing && $existing['status'] === 'completed') {
            Response::error('This interview has already been completed.', 409);
        }

        $type = (string) ($application['interview_type'] ?? 'text');
        $result = $this->service->createInterview($applicationId, $type);

        Response::success([
            'interview_id'   => $result['interview_id'],
            'message'        => $result['message'],
            'type'           => $result['type'],
            'status'         => $result['status'],
            'candidate_name' => $application['candidate_name'] ?? '',
            'job_title'      => $application['job_title'] ?? '',
            'max_questions'  => (int) ($application['max_questions'] ?? 12),
            'duration'       => (int) ($application['interview_duration'] ?? 20),
        ], 'Interview started');
    }

    private function message(array $application): void
    {
        $message = trim((string) $this->request->input('message', ''));
        if ($message === '') {
            Response::error('Message cannot be empty.', 422, ['message' => 'A message is required.']);
        }
        if (mb_strlen($message) > 8000) {
            $message = mb_substr($message, 0, 8000);
        }

        $interview = $this->repo->findByApplicationId((int) $application['id']);
        if ($interview === null) {
            // Auto-start if the candidate posts before an explicit start.
            $created = $this->service->createInterview((int) $application['id'], (string) ($application['interview_type'] ?? 'text'));
            $interview = $this->repo->findById($created['interview_id']);
        }
        if ($interview === null) {
            Response::error('Interview could not be initialized.', 500);
        }
        if (($interview['status'] ?? '') === 'completed') {
            Response::error('This interview has already been completed.', 409);
        }

        $result = $this->service->processMessage((int) $interview['id'], $message);

        Response::success([
            'message'         => $result['message'],
            'is_question'     => $result['is_question'],
            'is_followup'     => $result['is_followup'],
            'is_closing'      => $result['is_closing'],
            'completed'       => $result['completed'],
            'questions_asked' => $result['questions_asked'],
            'language'        => $result['language'],
        ]);
    }

    private function status(array $application): void
    {
        $interview = $this->repo->findByApplicationId((int) $application['id']);

        if ($interview === null) {
            Response::success([
                'state'           => 'not_started',
                'status'          => 'pending',
                'messages'        => [],
                'questions_asked' => 0,
                'max_questions'   => (int) ($application['max_questions'] ?? 12),
            ]);
        }

        $messages = $this->repo->getMessages((int) $interview['id']);
        $transcript = array_map(static function ($m) {
            return [
                'role'       => $m['role'],
                'content'    => $m['content'],
                'is_question' => (bool) $m['is_question'],
                'created_at' => $m['created_at'] ?? null,
            ];
        }, $messages);

        $questionsAsked = 0;
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'ai' && (int) ($m['is_question'] ?? 0) === 1 && (int) ($m['is_followup'] ?? 0) === 0) {
                $questionsAsked++;
            }
        }

        Response::success([
            'state'           => $interview['status'] === 'completed' ? 'completed' : 'in_progress',
            'status'          => $interview['status'],
            'interview_id'    => (int) $interview['id'],
            'type'            => $interview['type'],
            'language'        => $interview['language_detected'],
            'messages'        => $transcript,
            'questions_asked' => $questionsAsked,
            'max_questions'   => (int) ($application['max_questions'] ?? 12),
            'started_at'      => $interview['started_at'],
            'completed_at'    => $interview['completed_at'],
        ]);
    }

    private function complete(array $application): void
    {
        $interview = $this->repo->findByApplicationId((int) $application['id']);
        if ($interview === null) {
            Response::error('No interview to complete.', 404);
        }
        if (($interview['status'] ?? '') === 'completed') {
            Response::success(['status' => 'completed'], 'Interview already completed');
        }

        $messages = $this->repo->getMessages((int) $interview['id']);

        // Only run the (costly) evaluation if there is a real conversation;
        // otherwise just mark abandoned.
        $candidateTurns = 0;
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'candidate') {
                $candidateTurns++;
            }
        }

        if ($candidateTurns >= 1) {
            $this->service->completeInterview((int) $interview['id']);
            Response::success(['status' => 'completed'], 'Interview completed and evaluated');
        }

        $this->repo->updateStatus((int) $interview['id'], 'abandoned');
        Response::success(['status' => 'abandoned'], 'Interview closed');
    }

    private function feedback(array $application): void
    {
        $interview = $this->repo->findByApplicationId((int) $application['id']);
        if ($interview === null) {
            Response::error('No interview found for feedback.', 404);
        }

        $rating = (int) $this->request->input('rating', 0);
        if ($rating < 1 || $rating > 5) {
            Response::error('Rating must be between 1 and 5.', 422, ['rating' => 'Rating must be 1-5.']);
        }

        $feedback    = trim((string) $this->request->input('feedback', ''));
        $suggestions = trim((string) $this->request->input('suggestions', ''));

        // Upsert: interview_feedback has a UNIQUE key on interview_id.
        $existing = $this->db->fetch(
            'SELECT id FROM interview_feedback WHERE interview_id = ? LIMIT 1',
            [(int) $interview['id']]
        );

        if ($existing) {
            $this->db->update('interview_feedback', [
                'rating'      => $rating,
                'feedback'    => $feedback !== '' ? $feedback : null,
                'suggestions' => $suggestions !== '' ? $suggestions : null,
            ], ['id' => (int) $existing['id']]);
        } else {
            $this->db->insert('interview_feedback', [
                'interview_id' => (int) $interview['id'],
                'candidate_id' => (int) $application['candidate_id'],
                'rating'       => $rating,
                'feedback'     => $feedback !== '' ? $feedback : null,
                'suggestions'  => $suggestions !== '' ? $suggestions : null,
            ]);
        }

        Response::success(null, 'Thank you for your feedback');
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function requireMethod(string $actual, string $expected): void
    {
        if (strtoupper($actual) !== strtoupper($expected)) {
            Response::error('Method not allowed. Expected ' . $expected . '.', 405);
        }
    }
}
