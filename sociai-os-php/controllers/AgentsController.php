<?php

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

require_once __DIR__ . '/../agents/CopywritingAgent.php';
require_once __DIR__ . '/../agents/StrategyAgent.php';
require_once __DIR__ . '/../agents/AnalyticsAgent.php';
require_once __DIR__ . '/../agents/CommunityAgent.php';
require_once __DIR__ . '/../agents/ResearchAgent.php';
require_once __DIR__ . '/../agents/PublishingAgent.php';

class AgentsController
{
    private \PDO $db;
    private Auth $auth;
    private Request $request;
    private Response $response;

    private const AGENTS = [
        'copywriting' => CopywritingAgent::class,
        'strategy'    => StrategyAgent::class,
        'analytics'   => AnalyticsAgent::class,
        'community'   => CommunityAgent::class,
        'research'    => ResearchAgent::class,
        'publishing'  => PublishingAgent::class,
    ];

    public function __construct()
    {
        $this->db       = Database::getInstance();
        $this->auth     = new Auth();
        $this->request  = new Request();
        $this->response = new Response();
    }

    public function index(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $statuses = $this->loadAgentStatuses($brandId);
        $history  = $this->loadTaskHistory($brandId, 20);
        $workflows = $this->loadWorkflowDefinitions();

        $this->response->view('agents/index', [
            'title'     => 'AI Agents – SociAI OS',
            'statuses'  => $statuses,
            'history'   => $history,
            'workflows' => $workflows,
            'agents'    => array_keys(self::AGENTS),
            'brandId'   => $brandId,
        ]);
    }

    public function runTask(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $agentType = $this->request->post('agent', '');
        $task      = $this->request->post('task', '');
        $params    = (array) $this->request->post('params', []);
        $async     = (bool) $this->request->post('async', false);

        if (!array_key_exists($agentType, self::AGENTS)) {
            $this->response->json(['success' => false, 'error' => 'Unknown agent: ' . $agentType], 400);
            return;
        }

        if (empty($task)) {
            $this->response->json(['success' => false, 'error' => 'Task name is required'], 400);
            return;
        }

        // Create task record
        $taskId = $this->createTaskRecord($brandId, $user['id'], $agentType, $task, $params);

        if ($async) {
            // Queue for background execution
            $this->queueTask($taskId);
            $this->response->json([
                'success' => true,
                'task_id' => $taskId,
                'status'  => 'queued',
                'message' => 'Task queued for background execution.',
            ]);
            return;
        }

        // Synchronous execution
        try {
            $this->updateTaskStatus($taskId, 'running');

            $agentClass = self::AGENTS[$agentType];
            /** @var \agents\BaseAgent $agent */
            $agent  = new $agentClass($brandId);
            $output = $agent->execute($task, $params);

            $this->updateTaskStatus($taskId, 'completed', $output);

            $this->response->json([
                'success' => true,
                'task_id' => $taskId,
                'status'  => 'completed',
                'output'  => $output,
            ]);

        } catch (\Throwable $e) {
            $this->updateTaskStatus($taskId, 'failed', [], $e->getMessage());
            error_log("Agent task {$taskId} failed: " . $e->getMessage());

            $this->response->json([
                'success' => false,
                'task_id' => $taskId,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function taskStatus(int $taskId): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $stmt = $this->db->prepare(
            'SELECT id, agent_type, task_name, status, input_params, output_result, error_message,
                    tokens_used, started_at, completed_at, created_at
             FROM agent_tasks
             WHERE id = ? AND brand_id = ?
             LIMIT 1'
        );
        $stmt->execute([$taskId, $brandId]);
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$task) {
            $this->response->json(['success' => false, 'error' => 'Task not found'], 404);
            return;
        }

        $task['input_params']   = json_decode($task['input_params'] ?? '{}', true);
        $task['output_result']  = json_decode($task['output_result'] ?? 'null', true);

        $this->response->json(['success' => true, 'task' => $task]);
    }

    public function taskHistory(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $agentFilter = $this->request->get('agent', 'all');
        $statusFilter = $this->request->get('status', 'all');
        $page    = max(1, (int) $this->request->get('page', 1));
        $perPage = 25;
        $offset  = ($page - 1) * $perPage;

        $where  = ['brand_id = ?'];
        $params = [$brandId];

        if ($agentFilter !== 'all' && array_key_exists($agentFilter, self::AGENTS)) {
            $where[]  = 'agent_type = ?';
            $params[] = $agentFilter;
        }
        if ($statusFilter !== 'all') {
            $where[]  = 'status = ?';
            $params[] = $statusFilter;
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM agent_tasks WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $this->db->prepare(
            "SELECT id, agent_type, task_name, status, tokens_used, started_at, completed_at, created_at
             FROM agent_tasks
             WHERE {$whereClause}
             ORDER BY created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $dataStmt->execute($params);
        $tasks = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->response->json([
            'success' => true,
            'tasks'   => $tasks,
            'total'   => $total,
            'page'    => $page,
        ]);
    }

    public function runWorkflow(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $workflowId = $this->request->post('workflow_id', '');
        $params     = (array) $this->request->post('params', []);

        $workflow = $this->getWorkflowDefinition($workflowId);
        if (!$workflow) {
            $this->response->json(['success' => false, 'error' => 'Unknown workflow'], 400);
            return;
        }

        // Create workflow run record
        $stmt = $this->db->prepare(
            'INSERT INTO workflow_runs (brand_id, user_id, workflow_id, workflow_name, status, input_params, created_at)
             VALUES (?, ?, ?, ?, "running", ?, NOW())'
        );
        $stmt->execute([$brandId, $user['id'], $workflowId, $workflow['name'], json_encode($params)]);
        $runId = (int) $this->db->lastInsertId();

        $results = [];
        $context = array_merge($params, ['brand_id' => $brandId]);

        try {
            foreach ($workflow['steps'] as $step) {
                $agentClass = self::AGENTS[$step['agent']] ?? null;
                if (!$agentClass) continue;

                /** @var \agents\BaseAgent $agent */
                $agent  = new $agentClass($brandId);
                $stepParams = array_merge($context, $step['params'] ?? []);
                $output = $agent->execute($step['task'], $stepParams);

                $results[$step['name']] = $output;
                // Pass output to next step context
                if (!empty($step['output_key'])) {
                    $context[$step['output_key']] = $output;
                }
            }

            $this->db->prepare(
                'UPDATE workflow_runs SET status = "completed", output_result = ?, completed_at = NOW() WHERE id = ?'
            )->execute([json_encode($results), $runId]);

            $this->response->json([
                'success'    => true,
                'run_id'     => $runId,
                'workflow'   => $workflowId,
                'results'    => $results,
            ]);

        } catch (\Throwable $e) {
            $this->db->prepare(
                'UPDATE workflow_runs SET status = "failed", error_message = ? WHERE id = ?'
            )->execute([$e->getMessage(), $runId]);

            $this->response->json([
                'success' => false,
                'run_id'  => $runId,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function workflowStatus(int $runId): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $stmt = $this->db->prepare(
            'SELECT id, workflow_id, workflow_name, status, input_params, output_result, error_message, created_at, completed_at
             FROM workflow_runs
             WHERE id = ? AND brand_id = ?
             LIMIT 1'
        );
        $stmt->execute([$runId, $brandId]);
        $run = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$run) {
            $this->response->json(['success' => false, 'error' => 'Workflow run not found'], 404);
            return;
        }

        $run['input_params']  = json_decode($run['input_params'] ?? '{}', true);
        $run['output_result'] = json_decode($run['output_result'] ?? 'null', true);

        $this->response->json(['success' => true, 'run' => $run]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function createTaskRecord(int $brandId, int $userId, string $agentType, string $task, array $params): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO agent_tasks (brand_id, user_id, agent_type, task_name, status, input_params, created_at)
             VALUES (?, ?, ?, ?, "pending", ?, NOW())'
        );
        $stmt->execute([$brandId, $userId, $agentType, $task, json_encode($params)]);
        return (int) $this->db->lastInsertId();
    }

    private function updateTaskStatus(int $taskId, string $status, array $output = [], string $error = ''): void
    {
        if ($status === 'running') {
            $this->db->prepare(
                'UPDATE agent_tasks SET status = "running", started_at = NOW() WHERE id = ?'
            )->execute([$taskId]);
        } elseif ($status === 'completed') {
            $this->db->prepare(
                'UPDATE agent_tasks SET status = "completed", output_result = ?, completed_at = NOW() WHERE id = ?'
            )->execute([json_encode($output), $taskId]);
        } else {
            $this->db->prepare(
                'UPDATE agent_tasks SET status = "failed", error_message = ?, completed_at = NOW() WHERE id = ?'
            )->execute([$error, $taskId]);
        }
    }

    private function queueTask(int $taskId): void
    {
        $this->db->prepare(
            'INSERT INTO task_queue (task_id, queued_at) VALUES (?, NOW())'
        )->execute([$taskId]);
    }

    private function loadAgentStatuses(int $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT agent_type, status, last_run_at, task_count, error_count
             FROM agent_status WHERE brand_id = ? ORDER BY agent_type'
        );
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function loadTaskHistory(int $brandId, int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, agent_type, task_name, status, tokens_used, created_at, completed_at
             FROM agent_tasks
             WHERE brand_id = ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$brandId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function loadWorkflowDefinitions(): array
    {
        return array_keys($this->getWorkflowDefinitions());
    }

    private function getWorkflowDefinition(string $id): ?array
    {
        return $this->getWorkflowDefinitions()[$id] ?? null;
    }

    private function getWorkflowDefinitions(): array
    {
        return [
            'full_content_creation' => [
                'name' => 'Full Content Creation',
                'steps' => [
                    ['name' => 'scan_trends', 'agent' => 'research', 'task' => 'scanTrends', 'params' => [], 'output_key' => 'trends'],
                    ['name' => 'generate_copy', 'agent' => 'copywriting', 'task' => 'generateCaption', 'params' => [], 'output_key' => 'copy'],
                    ['name' => 'schedule_post', 'agent' => 'publishing', 'task' => 'optimizePostingTime', 'params' => [], 'output_key' => 'schedule'],
                ],
            ],
            'weekly_analytics_report' => [
                'name' => 'Weekly Analytics Report',
                'steps' => [
                    ['name' => 'generate_report', 'agent' => 'analytics', 'task' => 'generateReport', 'params' => ['period' => '7d'], 'output_key' => 'report'],
                    ['name' => 'analyze_sentiment', 'agent' => 'analytics', 'task' => 'analyzeSentiment', 'params' => ['period' => '7d'], 'output_key' => 'sentiment'],
                    ['name' => 'get_recommendations', 'agent' => 'analytics', 'task' => 'generateRecommendations', 'params' => [], 'output_key' => 'recommendations'],
                ],
            ],
            'community_digest' => [
                'name' => 'Community Digest & Reply',
                'steps' => [
                    ['name' => 'get_comments', 'agent' => 'community', 'task' => 'getQueue', 'params' => [], 'output_key' => 'comments'],
                    ['name' => 'reply_comments', 'agent' => 'community', 'task' => 'bulkReply', 'params' => [], 'output_key' => 'replies'],
                ],
            ],
        ];
    }

    private function getActiveBrandId(int $userId): int
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (int) $_SESSION['active_brand_id'];
        }
        $stmt = $this->db->prepare(
            'SELECT b.id FROM brands b INNER JOIN brand_users bu ON bu.brand_id = b.id WHERE bu.user_id = ? ORDER BY bu.created_at ASC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : 0;
    }
}
