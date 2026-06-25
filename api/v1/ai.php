<?php
declare(strict_types=1);

use Modules\AI\JobBuilder;
use Modules\AI\CVAnalyzer;
use Modules\AI\CandidateMatcher;
use Modules\AI\RecruitmentCopilot;

/**
 * api/v1/ai.php - AI feature REST API (HR-authenticated).
 *
 * Surfaces the AI module capabilities to the HR UI. All endpoints require an
 * authenticated company user and operate within that user's tenant.
 *
 * Routes (relative to /api/v1):
 *   POST /ai/build-job         {title, seniority, industry, context}
 *   POST /ai/analyze-cv        {cv_text, job_id, candidate_id?}
 *   POST /ai/match-candidates  {job_id, candidate_ids}
 *   POST /ai/copilot           {message, context?}
 *   GET  /ai/usage             ?from=&to=
 */
class AiApi
{
    private Request $request;
    /** @var object Global \Database singleton. */
    private $db;
    private int $tenantId = 0;
    private int $userId = 0;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->db      = \Database::getInstance();
    }

    /**
     * @param string[] $segments e.g. ['ai', 'build-job']
     */
    public function dispatch(array $segments, string $method): void
    {
        $this->authenticate();

        $action = (string) ($segments[1] ?? '');

        switch ($action) {
            case 'build-job':
                $this->requireMethod($method, 'POST');
                $this->buildJob();
                break;

            case 'analyze-cv':
                $this->requireMethod($method, 'POST');
                $this->analyzeCv();
                break;

            case 'match-candidates':
                $this->requireMethod($method, 'POST');
                $this->matchCandidates();
                break;

            case 'copilot':
                $this->requireMethod($method, 'POST');
                $this->copilot();
                break;

            case 'usage':
                $this->requireMethod($method, 'GET');
                $this->usage();
                break;

            default:
                Response::error('Unknown AI action.', 404);
        }
    }

    // ==================================================================
    // Endpoints
    // ==================================================================

    private function buildJob(): void
    {
        $title     = trim((string) $this->request->input('title', ''));
        $seniority = (string) $this->request->input('seniority', 'mid');
        $industry  = (string) $this->request->input('industry', '');

        if ($title === '') {
            Response::error('Job title is required.', 422, ['title' => 'Title is required.']);
        }

        $context = (array) $this->request->input('context', []);
        $context['tenant_id'] = $this->tenantId;
        $context['user_id']   = $this->userId;

        $builder = new JobBuilder();
        $result = $builder->generate($title, $seniority, $industry, $context);

        Response::success($result, 'Job generated');
    }

    private function analyzeCv(): void
    {
        $cvText = (string) $this->request->input('cv_text', '');
        $jobId  = (int) $this->request->input('job_id', 0);

        if (trim($cvText) === '') {
            Response::error('cv_text is required.', 422, ['cv_text' => 'CV text is required.']);
        }
        if ($jobId <= 0) {
            Response::error('job_id is required.', 422, ['job_id' => 'A job is required.']);
        }

        $job = $this->db->fetch(
            'SELECT * FROM jobs WHERE id = ? AND tenant_id = ? LIMIT 1',
            [$jobId, $this->tenantId]
        );
        if (!$job) {
            Response::error('Job not found.', 404);
        }

        $candidateId = (int) $this->request->input('candidate_id', 0);
        $candidate = [];
        if ($candidateId > 0) {
            $candidate = $this->db->fetch(
                'SELECT * FROM candidates WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) LIMIT 1',
                [$candidateId, $this->tenantId]
            ) ?: [];
        }

        $analyzer = new CVAnalyzer();
        $analysis = $analyzer->analyze($cvText, $job, $candidate, $this->tenantId, $this->userId);

        // Persist onto the application when one exists for this candidate+job.
        if ($candidateId > 0) {
            $application = $this->db->fetch(
                'SELECT id FROM applications WHERE job_id = ? AND candidate_id = ? AND tenant_id = ? LIMIT 1',
                [$jobId, $candidateId, $this->tenantId]
            );
            if ($application) {
                $this->db->update('applications', [
                    'cv_match_score' => (float) ($analysis['match_score'] ?? 0),
                    'cv_analysis'    => json_encode($analysis, JSON_UNESCAPED_UNICODE),
                ], ['id' => (int) $application['id']]);
            }
        }

        Response::success($analysis, 'CV analyzed');
    }

    private function matchCandidates(): void
    {
        $jobId = (int) $this->request->input('job_id', 0);
        if ($jobId <= 0) {
            Response::error('job_id is required.', 422, ['job_id' => 'A job is required.']);
        }

        $job = $this->db->fetch(
            'SELECT * FROM jobs WHERE id = ? AND tenant_id = ? LIMIT 1',
            [$jobId, $this->tenantId]
        );
        if (!$job) {
            Response::error('Job not found.', 404);
        }

        $candidateIds = $this->request->input('candidate_ids', []);
        if (is_string($candidateIds)) {
            $candidateIds = array_filter(array_map('intval', explode(',', $candidateIds)));
        }
        $candidateIds = array_values(array_unique(array_map('intval', (array) $candidateIds)));

        if (empty($candidateIds)) {
            // Default to the applicants for this job.
            $rows = $this->db->fetchAll('
                SELECT c.*, a.id AS application_id, a.current_stage AS stage,
                       a.ai_match_score AS final_score, a.ai_recommendation, a.cv_match_score
                  FROM applications a
                  JOIN candidates c ON c.id = a.candidate_id
                 WHERE a.job_id = ? AND a.tenant_id = ?
                 ORDER BY a.applied_at DESC
                 LIMIT 50
            ', [$jobId, $this->tenantId]);
        } else {
            $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
            $rows = $this->db->fetchAll("
                SELECT c.*, a.id AS application_id, a.current_stage AS stage,
                       a.ai_match_score AS final_score, a.ai_recommendation, a.cv_match_score
                  FROM candidates c
             LEFT JOIN applications a ON a.candidate_id = c.id AND a.job_id = ?
                 WHERE c.id IN ({$placeholders}) AND c.tenant_id = ?
            ", array_merge([$jobId], $candidateIds, [$this->tenantId]));
        }

        if (empty($rows)) {
            Response::success([], 'No candidates to rank');
        }

        // Attach any stored interview evaluation as a ranking signal.
        foreach ($rows as &$row) {
            if (!empty($row['application_id'])) {
                $eval = $this->db->fetch(
                    'SELECT overall_score, recommendation, executive_summary
                       FROM interview_evaluations WHERE interview_id = (SELECT id FROM interviews WHERE application_id = ? ORDER BY id DESC LIMIT 1) ORDER BY id DESC LIMIT 1',
                    [(int) $row['application_id']]
                );
                if ($eval) {
                    $row['evaluation'] = $eval;
                }
            }
        }
        unset($row);

        $matcher = new CandidateMatcher();
        $ranked = $matcher->rankCandidates($rows, $job);

        Response::success([
            'job_id'  => $jobId,
            'ranked'  => $ranked,
            'count'   => count($ranked),
        ], 'Candidates ranked');
    }

    private function copilot(): void
    {
        $message = trim((string) $this->request->input('message', ''));
        if ($message === '') {
            Response::error('message is required.', 422, ['message' => 'A question is required.']);
        }

        $context = (array) $this->request->input('context', []);
        $context = $this->enrichCopilotContext($context);
        $context['user_id'] = $this->userId;

        $copilot = new RecruitmentCopilot();
        $result = $copilot->chat($message, $context, $this->tenantId);

        Response::success($result);
    }

    private function usage(): void
    {
        $from = (string) $this->request->get('from', date('Y-m-01'));
        $to   = (string) $this->request->get('to', date('Y-m-d'));

        // Normalize dates; fall back to the current month on bad input.
        $fromTs = strtotime($from) ?: strtotime(date('Y-m-01'));
        $toTs   = strtotime($to) ?: time();
        $fromDate = date('Y-m-d 00:00:00', $fromTs);
        $toDate   = date('Y-m-d 23:59:59', $toTs);

        $totals = $this->db->fetch('
            SELECT
                COUNT(*) AS requests,
                COALESCE(SUM(prompt_tokens), 0) AS prompt_tokens,
                COALESCE(SUM(completion_tokens), 0) AS completion_tokens,
                COALESCE(SUM(total_tokens), 0) AS total_tokens,
                COALESCE(SUM(cost_usd), 0) AS cost_usd
            FROM ai_usage_logs
            WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
        ', [$this->tenantId, $fromDate, $toDate]) ?: [];

        $byFeature = $this->db->fetchAll('
            SELECT feature,
                   COUNT(*) AS requests,
                   COALESCE(SUM(total_tokens), 0) AS total_tokens,
                   COALESCE(SUM(cost_usd), 0) AS cost_usd
            FROM ai_usage_logs
            WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
            GROUP BY feature
            ORDER BY cost_usd DESC
        ', [$this->tenantId, $fromDate, $toDate]);

        $daily = $this->db->fetchAll('
            SELECT DATE(created_at) AS day,
                   COUNT(*) AS requests,
                   COALESCE(SUM(total_tokens), 0) AS total_tokens,
                   COALESCE(SUM(cost_usd), 0) AS cost_usd
            FROM ai_usage_logs
            WHERE tenant_id = ? AND created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY day ASC
        ', [$this->tenantId, $fromDate, $toDate]);

        Response::success([
            'range'      => ['from' => $fromDate, 'to' => $toDate],
            'totals'     => [
                'requests'          => (int) ($totals['requests'] ?? 0),
                'prompt_tokens'     => (int) ($totals['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($totals['completion_tokens'] ?? 0),
                'total_tokens'      => (int) ($totals['total_tokens'] ?? 0),
                'cost_usd'          => round((float) ($totals['cost_usd'] ?? 0), 4),
            ],
            'by_feature' => $byFeature,
            'daily'      => $daily,
        ]);
    }

    // ==================================================================
    // Context enrichment for the copilot
    // ==================================================================

    /**
     * If the caller did not supply a rich context, build a compact snapshot of
     * the tenant's pipeline so the copilot has data to reason over.
     */
    private function enrichCopilotContext(array $context): array
    {
        if (empty($context['pipeline'])) {
            $stages = $this->db->fetchAll('
                SELECT current_stage AS stage, COUNT(*) AS n FROM applications
                 WHERE tenant_id = ? GROUP BY current_stage
            ', [$this->tenantId]);
            $pipeline = [];
            foreach ($stages as $s) {
                $pipeline[$s['stage']] = (int) $s['n'];
            }
            $context['pipeline'] = $pipeline;
        }

        if (empty($context['jobs'])) {
            $context['jobs'] = $this->db->fetchAll('
                SELECT id, title, experience_level AS seniority, status,
                       (SELECT COUNT(*) FROM applications a WHERE a.job_id=jobs.id) AS applications_count
                  FROM jobs WHERE tenant_id = ?
                 ORDER BY created_at DESC LIMIT 25
            ', [$this->tenantId]);
        }

        if (empty($context['candidates'])) {
            $context['candidates'] = $this->db->fetchAll('
                SELECT c.id, CONCAT(c.first_name,' ',c.last_name) AS full_name,
                       c.years_experience, c.expected_salary, c.location, c.skills,
                       j.title AS job_title, a.current_stage AS stage,
                       a.ai_match_score AS final_score, a.ai_recommendation,
                       ev.overall_score,
                       JSON_EXTRACT(ev.skills_analysis, "$.english_proficiency.score") AS english_proficiency
                  FROM applications a
                  JOIN candidates c ON c.id = a.candidate_id
                  JOIN jobs j ON j.id = a.job_id
             LEFT JOIN interview_evaluations ev ON ev.interview_id = (
                 SELECT id FROM interviews WHERE application_id = a.id ORDER BY id DESC LIMIT 1
             )
                 WHERE a.tenant_id = ?
                 ORDER BY (a.ai_match_score IS NULL), a.ai_match_score DESC
                 LIMIT 60
            ', [$this->tenantId]);
        }

        return $context;
    }

    // ==================================================================
    // Auth + helpers
    // ==================================================================

    private function authenticate(): void
    {
        if (!class_exists('Auth') || !Auth::check()) {
            Response::error('Unauthorized.', 401);
        }
        $user = Auth::user();
        if (!$user) {
            Response::error('Unauthorized.', 401);
        }

        // Candidates cannot use HR AI tools.
        if (($user['type'] ?? '') === 'candidate') {
            Response::error('Forbidden.', 403);
        }

        $this->userId = (int) ($user['id'] ?? 0);
        $this->tenantId = (int) ($user['tenant_id'] ?? 0);

        if ($this->tenantId <= 0) {
            // Allow super admins to target a tenant explicitly.
            $headerTenant = (int) ($_SERVER['HTTP_X_TENANT_ID'] ?? 0);
            if ($headerTenant > 0) {
                $this->tenantId = $headerTenant;
            }
        }

        if ($this->tenantId <= 0) {
            Response::error('No active tenant context.', 400);
        }

        // Scope the DB to this tenant for any tenant-aware helpers.
        if (method_exists($this->db, 'setTenantId')) {
            $this->db->setTenantId($this->tenantId);
        }
    }

    private function requireMethod(string $actual, string $expected): void
    {
        if (strtoupper($actual) !== strtoupper($expected)) {
            Response::error('Method not allowed. Expected ' . $expected . '.', 405);
        }
    }
}
