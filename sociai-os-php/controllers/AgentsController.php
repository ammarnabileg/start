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
    private Database $db;
    private Request $request;

    private const AGENTS = [
        'copywriting' => \CopywritingAgent::class,
        'strategy'    => \StrategyAgent::class,
        'analytics'   => \AnalyticsAgent::class,
        'community'   => \CommunityAgent::class,
        'research'    => \ResearchAgent::class,
        'publishing'  => \PublishingAgent::class,
    ];

    public function __construct()
    {
        $this->db      = Database::getInstance();
        $this->request = new Request();
    }

    public function index(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $history  = $this->loadTaskHistory($brandId, 20);

        Response::view('agents/index', [
            'title'   => 'AI Agents – SociAI OS',
            'history' => $history,
            'agents'  => array_keys(self::AGENTS),
            'brandId' => $brandId,
            'csrf'    => Auth::csrfToken(),
        ]);
    }

    public function runTask(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $agentType = $this->request->post('agent', '');
        $task      = $this->request->post('task', '');
        $params    = (array)$this->request->post('params', []);

        if (!array_key_exists($agentType, self::AGENTS)) {
            Response::json(['success' => false, 'error' => 'Unknown agent: ' . $agentType], 400);
            return;
        }
        if (empty($task)) {
            Response::json(['success' => false, 'error' => 'Task name is required'], 400);
            return;
        }

        $taskId = $this->createTaskRecord($brandId, $user['id'], $agentType, $task, $params);

        try {
            $this->updateTaskStatus($taskId, 'running');
            $agentClass = self::AGENTS[$agentType];
            $agent      = new $agentClass($brandId);
            $output     = $agent->execute($task, $params);
            $this->updateTaskStatus($taskId, 'completed', $output);
            Response::json([
                'success' => true,
                'task_id' => $taskId,
                'status'  => 'completed',
                'output'  => $output,
            ]);
        } catch (\Throwable $e) {
            $this->updateTaskStatus($taskId, 'failed', [], $e->getMessage());
            error_log("Agent task {$taskId} failed: " . $e->getMessage());
            Response::json([
                'success' => false,
                'task_id' => $taskId,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function taskStatus(string $taskId): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $stmt = $this->db->prepare(
            'SELECT id, agent_type, task_name, status, input_data, output_data,
                    error_message, tokens_used, started_at, completed_at, created_at
             FROM agent_tasks WHERE id = ? AND brand_id = ? LIMIT 1'
        );
        $stmt->execute([$taskId, $brandId]);
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$task) {
            Response::json(['success' => false, 'error' => 'Task not found'], 404);
            return;
        }

        $task['input_data']  = json_decode($task['input_data'] ?? '{}', true);
        $task['output_data'] = json_decode($task['output_data'] ?? 'null', true);

        Response::json(['success' => true, 'task' => $task]);
    }

    public function taskHistory(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $agentFilter  = $this->request->get('agent', 'all');
        $statusFilter = $this->request->get('status', 'all');
        $page    = max(1, (int)$this->request->get('page', 1));
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
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $this->db->prepare(
            "SELECT id, agent_type, task_name, status, tokens_used, started_at, completed_at, created_at
             FROM agent_tasks WHERE {$whereClause}
             ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $dataStmt->execute($params);
        $tasks = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);

        Response::json(['success' => true, 'tasks' => $tasks, 'total' => $total, 'page' => $page]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function createTaskRecord(string $brandId, string $userId, string $agentType, string $task, array $params): string
    {
        $id = $this->generateUuid();
        $this->db->prepare(
            'INSERT INTO agent_tasks (id, brand_id, user_id, agent_type, task_name, status, input_data, created_at)
             VALUES (?, ?, ?, ?, ?, "pending", ?, NOW())'
        )->execute([$id, $brandId, $userId, $agentType, $task, json_encode($params)]);
        return $id;
    }

    private function updateTaskStatus(string $taskId, string $status, array $output = [], string $error = ''): void
    {
        if ($status === 'running') {
            $this->db->prepare(
                'UPDATE agent_tasks SET status = "running", started_at = NOW() WHERE id = ?'
            )->execute([$taskId]);
        } elseif ($status === 'completed') {
            $this->db->prepare(
                'UPDATE agent_tasks SET status = "completed", output_data = ?, completed_at = NOW() WHERE id = ?'
            )->execute([json_encode($output), $taskId]);
        } else {
            $this->db->prepare(
                'UPDATE agent_tasks SET status = "failed", error_message = ?, completed_at = NOW() WHERE id = ?'
            )->execute([$error, $taskId]);
        }
    }

    private function loadTaskHistory(string $brandId, int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, agent_type, task_name, status, tokens_used, created_at, completed_at
             FROM agent_tasks WHERE brand_id = ?
             ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$brandId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function generateUuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function getActiveBrandId(string $userId): string
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (string)$_SESSION['active_brand_id'];
        }
        $stmt = $this->db->prepare(
            'SELECT b.id FROM brands b
             INNER JOIN team_members tm ON tm.brand_id = b.id
             WHERE tm.user_id = ? ORDER BY tm.created_at ASC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['active_brand_id'] = $row['id'];
            return (string)$row['id'];
        }
        return '';
    }
}
