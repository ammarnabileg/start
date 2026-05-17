<?php

declare(strict_types=1);

/**
 * REST API: /api/agents
 *
 * Routes:
 *   POST /api/agents/run              → run an agent task
 *   GET  /api/agents/task/{id}        → get task status
 *   POST /api/agents/workflow         → run a multi-agent workflow
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../agents/CopywritingAgent.php';
require_once __DIR__ . '/../agents/StrategyAgent.php';
require_once __DIR__ . '/../agents/AnalyticsAgent.php';
require_once __DIR__ . '/../agents/CommunityAgent.php';
require_once __DIR__ . '/../agents/ResearchAgent.php';
require_once __DIR__ . '/../agents/PublishingAgent.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─── Auth ─────────────────────────────────────────────────────────────────────

$db = Database::getInstance();

function getAgentApiUser(\PDO $db): ?array
{
    $auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        $token = trim($m[1]);
    }
    if (empty($token)) return null;

    $hash = hash('sha256', $token);
    $stmt = $db->prepare(
        'SELECT at.user_id, at.brand_id, u.role
         FROM api_tokens at
         INNER JOIN users u ON u.id = at.user_id
         WHERE at.token_hash = ? AND at.expires_at > NOW() AND at.is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($row) {
        $db->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?')->execute([$hash]);
    }
    return $row ?: null;
}

$authUser = getAgentApiUser($db);
if (!$authUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$brandId = (int) $authUser['brand_id'];
$method  = $_SERVER['REQUEST_METHOD'];
$uri     = rtrim(preg_replace('#/+#', '/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');

const AGENT_MAP = [
    'copywriting' => CopywritingAgent::class,
    'strategy'    => StrategyAgent::class,
    'analytics'   => AnalyticsAgent::class,
    'community'   => CommunityAgent::class,
    'research'    => ResearchAgent::class,
    'publishing'  => PublishingAgent::class,
];

// ─── Rate limiting (simple in-DB counter) ─────────────────────────────────────

function checkRateLimit(\PDO $db, int $userId, string $endpoint, int $limitPerMinute = 20): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM api_rate_limits
         WHERE user_id = ? AND endpoint = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)'
    );
    $stmt->execute([$userId, $endpoint]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= $limitPerMinute) return false;

    $db->prepare('INSERT INTO api_rate_limits (user_id, endpoint, created_at) VALUES (?, ?, NOW())')
       ->execute([$userId, $endpoint]);
    return true;
}

// ─── Routes ──────────────────────────────────────────────────────────────────

try {

    // POST /api/agents/run
    if ($method === 'POST' && preg_match('#/api/agents/run$#', $uri)) {
        if (!checkRateLimit($db, (int) $authUser['user_id'], 'agents_run', 10)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded. Max 10 agent runs per minute.']);
            exit;
        }

        $body   = json_decode(file_get_contents('php://input'), true) ?: [];
        $agent  = $body['agent'] ?? '';
        $task   = $body['task']  ?? '';
        $params = (array) ($body['params'] ?? []);
        $async  = (bool) ($body['async'] ?? false);

        if (!array_key_exists($agent, AGENT_MAP)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unknown agent. Valid: ' . implode(', ', array_keys(AGENT_MAP))]);
            exit;
        }
        if (empty($task)) {
            http_response_code(400);
            echo json_encode(['error' => 'task is required']);
            exit;
        }

        // Create task record
        $stmt = $db->prepare(
            'INSERT INTO agent_tasks (brand_id, user_id, agent_type, task_name, status, input_params, created_at)
             VALUES (?, ?, ?, ?, "pending", ?, NOW())'
        );
        $stmt->execute([$brandId, $authUser['user_id'], $agent, $task, json_encode($params)]);
        $taskId = (int) $db->lastInsertId();

        if ($async) {
            // Queue for background processing
            $db->prepare('INSERT INTO task_queue (task_id, queued_at) VALUES (?, NOW())')->execute([$taskId]);
            http_response_code(202);
            echo json_encode([
                'task_id' => $taskId,
                'status'  => 'queued',
                'message' => 'Task queued. Poll GET /api/agents/task/' . $taskId,
            ]);
            exit;
        }

        // Synchronous execution
        $db->prepare('UPDATE agent_tasks SET status = "running", started_at = NOW() WHERE id = ?')->execute([$taskId]);

        try {
            $agentClass = AGENT_MAP[$agent];
            $agentObj   = new $agentClass($brandId);
            $output     = $agentObj->execute($task, $params);

            $db->prepare(
                'UPDATE agent_tasks SET status = "completed", output_result = ?, completed_at = NOW() WHERE id = ?'
            )->execute([json_encode($output), $taskId]);

            echo json_encode([
                'task_id' => $taskId,
                'status'  => 'completed',
                'output'  => $output,
            ]);

        } catch (\Throwable $e) {
            $db->prepare(
                'UPDATE agent_tasks SET status = "failed", error_message = ?, completed_at = NOW() WHERE id = ?'
            )->execute([$e->getMessage(), $taskId]);

            http_response_code(500);
            echo json_encode([
                'task_id' => $taskId,
                'status'  => 'failed',
                'error'   => $e->getMessage(),
            ]);
        }
        exit;
    }

    // GET /api/agents/task/{id}
    if ($method === 'GET' && preg_match('#/api/agents/task/(\d+)$#', $uri, $m)) {
        $taskId = (int) $m[1];

        $stmt = $db->prepare(
            'SELECT id, agent_type, task_name, status, input_params, output_result, error_message,
                    tokens_used, started_at, completed_at, created_at
             FROM agent_tasks
             WHERE id = ? AND brand_id = ?
             LIMIT 1'
        );
        $stmt->execute([$taskId, $brandId]);
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$task) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
            exit;
        }

        $task['input_params']  = json_decode($task['input_params'] ?? '{}', true);
        $task['output_result'] = json_decode($task['output_result'] ?? 'null', true);

        echo json_encode(['task' => $task]);
        exit;
    }

    // POST /api/agents/workflow
    if ($method === 'POST' && preg_match('#/api/agents/workflow$#', $uri)) {
        if (!checkRateLimit($db, (int) $authUser['user_id'], 'agents_workflow', 5)) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded. Max 5 workflow runs per minute.']);
            exit;
        }

        $body       = json_decode(file_get_contents('php://input'), true) ?: [];
        $workflowId = $body['workflow_id'] ?? '';
        $params     = (array) ($body['params'] ?? []);

        $workflows = getWorkflowDefinitions();
        if (!array_key_exists($workflowId, $workflows)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unknown workflow. Valid: ' . implode(', ', array_keys($workflows))]);
            exit;
        }

        $workflow = $workflows[$workflowId];

        // Create run record
        $stmt = $db->prepare(
            'INSERT INTO workflow_runs (brand_id, user_id, workflow_id, workflow_name, status, input_params, created_at)
             VALUES (?, ?, ?, ?, "running", ?, NOW())'
        );
        $stmt->execute([$brandId, $authUser['user_id'], $workflowId, $workflow['name'], json_encode($params)]);
        $runId = (int) $db->lastInsertId();

        $results = [];
        $context = array_merge($params, ['brand_id' => $brandId]);

        foreach ($workflow['steps'] as $step) {
            $agentClass = AGENT_MAP[$step['agent']] ?? null;
            if (!$agentClass) continue;

            try {
                $agentObj  = new $agentClass($brandId);
                $stepParams = array_merge($context, (array) ($step['params'] ?? []));
                $output     = $agentObj->execute($step['task'], $stepParams);

                $results[$step['name']] = $output;
                if (!empty($step['output_key'])) {
                    $context[$step['output_key']] = $output;
                }
            } catch (\Throwable $e) {
                $results[$step['name']] = ['error' => $e->getMessage()];
            }
        }

        $db->prepare(
            'UPDATE workflow_runs SET status = "completed", output_result = ?, completed_at = NOW() WHERE id = ?'
        )->execute([json_encode($results), $runId]);

        echo json_encode([
            'run_id'   => $runId,
            'workflow' => $workflowId,
            'status'   => 'completed',
            'results'  => $results,
        ]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);

} catch (\Throwable $e) {
    error_log('API /agents error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function getWorkflowDefinitions(): array
{
    return [
        'full_content_creation' => [
            'name'  => 'Full Content Creation Pipeline',
            'steps' => [
                ['name' => 'research_trends',    'agent' => 'research',    'task' => 'scanTrends',            'params' => [], 'output_key' => 'trends'],
                ['name' => 'generate_copy',      'agent' => 'copywriting', 'task' => 'generateCaption',       'params' => [], 'output_key' => 'copy'],
                ['name' => 'optimize_schedule',  'agent' => 'publishing',  'task' => 'optimizePostingTime',   'params' => [], 'output_key' => 'schedule'],
            ],
        ],
        'weekly_analytics' => [
            'name'  => 'Weekly Analytics & Recommendations',
            'steps' => [
                ['name' => 'generate_report', 'agent' => 'analytics', 'task' => 'generateReport',   'params' => ['period' => '7d'], 'output_key' => 'report'],
                ['name' => 'get_sentiment',   'agent' => 'analytics', 'task' => 'analyzeSentiment', 'params' => ['period' => '7d'], 'output_key' => 'sentiment'],
                ['name' => 'get_recs',        'agent' => 'analytics', 'task' => 'generateRecommendations', 'params' => [], 'output_key' => 'recs'],
            ],
        ],
        'community_sweep' => [
            'name'  => 'Community Management Sweep',
            'steps' => [
                ['name' => 'get_queue',    'agent' => 'community', 'task' => 'getQueue',    'params' => [], 'output_key' => 'queue'],
                ['name' => 'bulk_reply',   'agent' => 'community', 'task' => 'bulkReply',   'params' => [], 'output_key' => 'replies'],
            ],
        ],
        'competitor_research' => [
            'name'  => 'Competitor Research & Content Ideas',
            'steps' => [
                ['name' => 'scan_trends',   'agent' => 'research',    'task' => 'scanTrends',             'params' => [], 'output_key' => 'trends'],
                ['name' => 'get_hashtags',  'agent' => 'research',    'task' => 'analyzeHashtags',        'params' => [], 'output_key' => 'hashtags'],
                ['name' => 'benchmark',     'agent' => 'analytics',   'task' => 'benchmarkCompetitors',   'params' => [], 'output_key' => 'benchmark'],
            ],
        ],
    ];
}
