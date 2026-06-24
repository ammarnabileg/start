<?php
declare(strict_types=1);

namespace Modules\Interviews;

use Modules\AI\InterviewConductor;
use Modules\AI\InterviewEvaluator;
use RuntimeException;

/**
 * InterviewService - Business logic orchestrating the AI interview lifecycle:
 * creation, per-message processing, completion + evaluation, and the
 * generation/validation of candidate interview links.
 *
 * It coordinates the InterviewRepository (persistence) with the AI module
 * (InterviewConductor for live turns, InterviewEvaluator for the final report)
 * and keeps the applications pipeline in sync.
 */
class InterviewService
{
    /** @var object Database singleton (global \Database). */
    private $db;
    private InterviewRepository $repo;
    private InterviewConductor $conductor;
    private InterviewEvaluator $evaluator;

    public function __construct(
        $db = null,
        ?InterviewRepository $repo = null,
        ?InterviewConductor $conductor = null,
        ?InterviewEvaluator $evaluator = null
    ) {
        $this->db        = $db ?? \Database::getInstance();
        $this->repo      = $repo ?? new InterviewRepository($this->db);
        $this->conductor = $conductor ?? new InterviewConductor();
        $this->evaluator = $evaluator ?? new InterviewEvaluator();
    }

    /**
     * Create (or reuse) an interview for an application and produce the AI's
     * opening message.
     *
     * @return array{interview_id:int, message:string, status:string, type:string}
     */
    public function createInterview(int $applicationId, string $type = 'text'): array
    {
        $application = $this->getApplication($applicationId);
        if ($application === null) {
            throw new RuntimeException('Application not found.');
        }

        $tenantId = (int) $application['tenant_id'];
        $job       = $this->getJob((int) $application['job_id']);
        $candidate = $this->getCandidate((int) $application['candidate_id']);
        $criteria  = $this->getCriteria((int) $application['job_id']);
        $questionBank = $this->getQuestionBank($tenantId, (int) $application['job_id']);

        // Reuse an existing, not-yet-completed interview if present.
        $existing = $this->repo->findByApplicationId($applicationId);
        if ($existing && in_array($existing['status'], ['pending', 'in_progress'], true)) {
            $interviewId = (int) $existing['id'];
            $messages = $this->repo->getMessages($interviewId);
            if (!empty($messages)) {
                // Resume: return the latest AI message.
                $last = $this->lastAiMessage($messages);
                return [
                    'interview_id' => $interviewId,
                    'message'      => $last,
                    'status'       => (string) $existing['status'],
                    'type'         => (string) $existing['type'],
                ];
            }
            $interview = $existing;
        } else {
            $interviewType = $type !== '' ? $type : (string) ($job['interview_type'] ?? 'text');
            $interviewId = $this->repo->create([
                'tenant_id'      => $tenantId,
                'application_id' => $applicationId,
                'type'           => $interviewType,
                'status'         => 'pending',
            ]);
            $interview = $this->repo->findById($interviewId);
        }

        // Mark started and generate the opening message.
        $this->repo->markStarted($interviewId);
        $interview = $this->repo->findById($interviewId) ?? $interview;

        $opening = $this->conductor->startInterview($interview, $job, $candidate, $criteria, $questionBank);

        $this->repo->saveMessage($interviewId, 'ai', $opening, [
            'is_question' => true,
            'is_followup' => false,
            'skill_assessed' => '',
        ]);

        // Move the application into AI screening if it is still at "applied".
        if (($application['stage'] ?? '') === 'applied') {
            $this->updateApplicationStage($applicationId, 'ai_screening');
        }

        return [
            'interview_id' => $interviewId,
            'message'      => $opening,
            'status'       => 'in_progress',
            'type'         => (string) $interview['type'],
        ];
    }

    /**
     * Process a candidate message: persist it, ask the conductor for the next
     * AI turn, persist that, and report whether the interview has completed.
     *
     * @return array{message:string, is_question:bool, is_followup:bool,
     *               is_closing:bool, skill_assessed:string, completed:bool,
     *               questions_asked:int, language:string}
     */
    public function processMessage(int $interviewId, string $candidateMessage): array
    {
        $interview = $this->repo->findById($interviewId);
        if ($interview === null) {
            throw new RuntimeException('Interview not found.');
        }
        if ($interview['status'] === 'completed') {
            throw new RuntimeException('This interview has already been completed.');
        }

        $candidateMessage = trim($candidateMessage);
        if ($candidateMessage === '') {
            throw new RuntimeException('Empty message.');
        }

        if ($interview['status'] !== 'in_progress') {
            $this->repo->markStarted($interviewId);
            $interview = $this->repo->findById($interviewId) ?? $interview;
        }

        $application = $this->getApplication((int) $interview['application_id']);
        $job      = $this->getJob((int) ($application['job_id'] ?? 0));
        $criteria = $this->getCriteria((int) ($application['job_id'] ?? 0));

        // Persist the candidate's answer.
        $this->repo->saveMessage($interviewId, 'candidate', $candidateMessage);

        // Gather full history (now including the new answer) for the conductor.
        $messages = $this->repo->getMessages($interviewId);

        $next = $this->conductor->getNextMessage($interview, $messages, $candidateMessage, $job, $criteria);

        // Persist detected language on first detection.
        if (!empty($next['language']) && empty($interview['language_detected'])) {
            $this->repo->update($interviewId, ['language_detected' => $next['language']]);
        }

        // Save the AI turn.
        $this->repo->saveMessage($interviewId, 'ai', $next['message'], [
            'is_question'    => $next['is_question'],
            'is_followup'    => $next['is_followup'],
            'skill_assessed' => $next['skill_assessed'],
        ]);

        $questionsAsked = $this->countQuestions($this->repo->getMessages($interviewId));
        $completed = false;

        if (!empty($next['is_closing'])) {
            // Closing message delivered -> finalize and evaluate.
            $this->completeInterview($interviewId);
            $completed = true;
        }

        return [
            'message'         => $next['message'],
            'is_question'     => (bool) $next['is_question'],
            'is_followup'     => (bool) $next['is_followup'],
            'is_closing'      => (bool) $next['is_closing'],
            'skill_assessed'  => (string) $next['skill_assessed'],
            'completed'       => $completed,
            'questions_asked' => $questionsAsked,
            'language'        => (string) ($next['language'] ?? 'en'),
        ];
    }

    /**
     * Complete an interview: mark it completed, run the full evaluation, store
     * it, and update the application with the final score/recommendation/stage.
     *
     * Idempotent: if an evaluation already exists it is returned as-is.
     *
     * @return array The stored evaluation.
     */
    public function completeInterview(int $interviewId): array
    {
        $interview = $this->repo->findById($interviewId);
        if ($interview === null) {
            throw new RuntimeException('Interview not found.');
        }

        $existing = $this->repo->getEvaluation($interviewId);
        if ($existing !== null && $interview['status'] === 'completed') {
            return $existing;
        }

        $this->repo->updateStatus($interviewId, 'completed');

        $application = $this->getApplication((int) $interview['application_id']);
        $job      = $this->getJob((int) ($application['job_id'] ?? 0));
        $criteria = $this->getCriteria((int) ($application['job_id'] ?? 0));
        $messages = $this->repo->getMessages($interviewId);

        // Reuse the CV analysis already stored on the application for consistency checks.
        $cvAnalysis = [];
        if (!empty($application['cv_analysis'])) {
            $decoded = is_string($application['cv_analysis'])
                ? json_decode($application['cv_analysis'], true)
                : $application['cv_analysis'];
            $cvAnalysis = is_array($decoded) ? $decoded : [];
        }

        $evaluation = $this->evaluator->evaluate($interview, $messages, $job, $criteria, $cvAnalysis);
        $evaluation['application_id'] = (int) $interview['application_id'];

        $evaluationId = $this->repo->saveEvaluation($interviewId, $evaluation);

        // Sync the application + interview link.
        $this->syncApplicationAfterEvaluation((int) $interview['application_id'], $interviewId, $evaluation);

        $evaluation['id'] = $evaluationId;
        return $evaluation;
    }

    /**
     * Get the stored evaluation for an interview (running completion if needed).
     */
    public function getEvaluation(int $interviewId): array
    {
        $evaluation = $this->repo->getEvaluation($interviewId);
        if ($evaluation !== null) {
            return $evaluation;
        }
        // Not yet evaluated: evaluate now if the interview has any transcript.
        $messages = $this->repo->getMessages($interviewId);
        if (!empty($messages)) {
            return $this->completeInterview($interviewId);
        }
        return [];
    }

    /**
     * Generate a unique, time-limited interview link token for an application
     * and persist it. Returns the absolute interview URL.
     */
    public function generateInterviewLink(int $applicationId, int $expiryDays = 14): string
    {
        $application = $this->getApplication($applicationId);
        if ($application === null) {
            throw new RuntimeException('Application not found.');
        }

        $token = $this->randomToken();
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiryDays * 86400));

        $this->db->update('applications', [
            'interview_link_token'      => $token,
            'interview_link_expires_at' => $expiresAt,
            'interview_link_used'       => 0,
        ], ['id' => $applicationId]);

        return $this->interviewUrl($token);
    }

    /**
     * Validate an interview token and return the associated application +
     * job + candidate, or null if invalid/expired.
     */
    public function validateToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $row = $this->db->fetch('
            SELECT a.*, j.title AS job_title, j.interview_type, j.interview_duration,
                   j.max_questions, j.language AS job_language,
                   c.full_name AS candidate_name, c.email AS candidate_email
              FROM applications a
              JOIN jobs j ON j.id = a.job_id
              JOIN candidates c ON c.id = a.candidate_id
             WHERE a.interview_link_token = ?
             LIMIT 1
        ', [$token]);

        if (!$row) {
            return null;
        }

        if (!empty($row['interview_link_expires_at'])) {
            $expires = strtotime((string) $row['interview_link_expires_at']);
            if ($expires !== false && $expires < time()) {
                return null;
            }
        }

        return $row;
    }

    // ==================================================================
    // Internal helpers
    // ==================================================================

    /**
     * After evaluation, write the final score / recommendation back to the
     * application and advance its stage based on the recommendation.
     */
    private function syncApplicationAfterEvaluation(int $applicationId, int $interviewId, array $evaluation): void
    {
        $recommendation = (string) ($evaluation['recommendation'] ?? 'not_recommended');
        $score = (float) ($evaluation['overall_score'] ?? 0);

        $stage = match ($recommendation) {
            'strong', 'suitable' => 'qualified',
            'possible'           => 'final_review',
            default              => 'disqualified',
        };

        $this->db->update('applications', [
            'interview_id'        => $interviewId,
            'final_score'         => $score,
            'ai_recommendation'   => $recommendation,
            'stage'               => $stage,
            'interview_link_used' => 1,
        ], ['id' => $applicationId]);

        // Update the candidate's rolling average skill/match score.
        $this->updateCandidateAverages($applicationId, $score);
    }

    private function updateCandidateAverages(int $applicationId, float $latestScore): void
    {
        $application = $this->getApplication($applicationId);
        if ($application === null) {
            return;
        }
        $candidateId = (int) $application['candidate_id'];

        $avg = $this->db->fetchColumn('
            SELECT AVG(final_score) FROM applications
             WHERE candidate_id = ? AND final_score IS NOT NULL
        ', [$candidateId]);

        if ($avg !== null && $avg !== false) {
            $this->db->update('candidates', [
                'avg_skill_score' => round((float) $avg, 2),
                'avg_match_score' => round((float) $avg, 2),
            ], ['id' => $candidateId]);
        }
    }

    private function updateApplicationStage(int $applicationId, string $stage): void
    {
        $this->db->update('applications', ['stage' => $stage], ['id' => $applicationId]);
    }

    private function countQuestions(array $messages): int
    {
        $count = 0;
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'ai' && (int) ($m['is_question'] ?? 0) === 1 && (int) ($m['is_followup'] ?? 0) === 0) {
                $count++;
            }
        }
        return $count;
    }

    private function lastAiMessage(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'ai') {
                return (string) $messages[$i]['content'];
            }
        }
        return '';
    }

    private function randomToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    private function interviewUrl(string $token): string
    {
        $base = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        if ($base === '') {
            $base = '';
        }
        return $base . '/interview/' . $token;
    }

    // ----- data fetchers (read-only) -----

    private function getApplication(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM applications WHERE id = ? LIMIT 1', [$id]);
        return $row ?: null;
    }

    private function getJob(int $id): array
    {
        if ($id <= 0) {
            return [];
        }
        $row = $this->db->fetch('SELECT * FROM jobs WHERE id = ? LIMIT 1', [$id]);
        return $row ?: [];
    }

    private function getCandidate(int $id): array
    {
        if ($id <= 0) {
            return [];
        }
        $row = $this->db->fetch('SELECT * FROM candidates WHERE id = ? LIMIT 1', [$id]);
        return $row ?: [];
    }

    private function getCriteria(int $jobId): array
    {
        if ($jobId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT * FROM job_criteria WHERE job_id = ? ORDER BY `order` ASC, id ASC',
            [$jobId]
        );
    }

    private function getQuestionBank(int $tenantId, int $jobId): array
    {
        return $this->db->fetchAll('
            SELECT * FROM question_bank
             WHERE tenant_id = ? AND (job_id = ? OR job_id IS NULL OR is_global = 1)
             ORDER BY (job_id = ?) DESC, usage_count DESC
             LIMIT 20
        ', [$tenantId, $jobId, $jobId]);
    }
}
