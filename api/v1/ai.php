<?php
/**
 * /api/v1/ai
 *   POST /analyze-cv        analyze a CV against a job
 *   POST /build-job         AI job builder
 *   POST /match-candidates  rank candidates for a job
 *   POST /copilot           copilot chat
 *   GET  /usage             usage stats
 */

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\AI\OpenAIService;

$api = $GLOBALS['__api'];
$req = new Request();
$auth = new Auth();
$auth->requireAuth();

$tenantId = (new Tenant())->currentId();
$userId = $auth->id();
// Provide tenant/user context to the AI usage logger.
if (method_exists(OpenAIService::class, 'setContext')) {
    OpenAIService::setContext(['tenant_id' => $tenantId, 'user_id' => $userId]);
}

$action = $api['sub'];

switch ($api['method'] . ':' . $action) {
    case 'POST:analyze-cv': {
        $auth->requirePermission('ai.use');
        $candidateId = (int) $req->post('candidate_id', 0);
        $jobId = (int) $req->post('job_id', 0);
        if ($candidateId && $jobId) {
            $result = (new App\Modules\Candidates\CandidateService())->analyzeCV($candidateId, $jobId);
            Response::success($result);
            return;
        }
        // Inline mode: raw cv_text + job_description.
        $cvText = (string) $req->post('cv_text', '');
        $jobDesc = (string) $req->post('job_description', '');
        $criteria = $req->post('criteria', []);
        $result = (new App\Modules\AI\CVAnalyzer())->analyze($cvText, $jobDesc, is_array($criteria) ? $criteria : []);
        Response::success($result);
        return;
    }

    case 'POST:build-job': {
        $auth->requirePermission('ai.use');
        $prompt = (string) $req->post('prompt', '');
        if (trim($prompt) === '') {
            Response::error('Prompt is required', 422);
            return;
        }
        $company = ['tenant_id' => $tenantId];
        $job = (new App\Modules\AI\JobBuilder())->buildFromPrompt($prompt, $company);
        Response::success($job);
        return;
    }

    case 'POST:match-candidates': {
        $auth->requirePermission('ai.use');
        $jobId = (int) $req->post('job_id', 0);
        $ids = $req->post('candidate_ids', []);
        $ids = is_array($ids) ? array_map('intval', $ids) : [];
        $matcher = new App\Modules\AI\CandidateMatcher();
        $ranked = empty($ids)
            ? $matcher->findMatchingCandidates($jobId)
            : $matcher->matchCandidatesToJob($jobId, $ids);
        Response::success($ranked);
        return;
    }

    case 'POST:copilot': {
        $auth->requirePermission('ai.use');
        $message = (string) $req->post('message', '');
        $history = $req->post('history', []);
        $copilot = new App\Modules\AI\RecruitmentCopilot();
        $context = $copilot->getContext((int) $tenantId);
        $reply = $copilot->chat($message, $context, is_array($history) ? $history : []);
        Response::success($reply);
        return;
    }

    case 'GET:usage': {
        $auth->requirePermission('ai.analytics');
        $rows = App\Core\Database::instance()->fetchAll(
            'SELECT feature, COUNT(*) AS calls, SUM(tokens_used) AS tokens, SUM(cost) AS cost
               FROM ai_usage_logs
              WHERE (:tid1 IS NULL OR tenant_id = :tid2)
              GROUP BY feature ORDER BY tokens DESC',
            [':tid1' => $tenantId, ':tid2' => $tenantId]
        );
        $totals = App\Core\Database::instance()->fetch(
            'SELECT COUNT(*) AS calls, COALESCE(SUM(tokens_used),0) AS tokens, COALESCE(SUM(cost),0) AS cost
               FROM ai_usage_logs WHERE (:tid1 IS NULL OR tenant_id = :tid2)',
            [':tid1' => $tenantId, ':tid2' => $tenantId]
        );
        Response::success(['by_feature' => $rows, 'totals' => $totals]);
        return;
    }

    default:
        Response::error('Not found', 404);
}
