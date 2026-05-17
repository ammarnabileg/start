<?php
/**
 * SociAI OS - AI Agent Controller
 */

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Request, Response, Database, Security};
use SociAI\Models\Brand;
use SociAI\Agents\{ContentGeneratorAgent, StrategyExtractorAgent, TrendHunterAgent, CommunityReplyAgent};

class AgentController
{
    private Request  $request;
    private Brand    $brandModel;
    private Database $db;

    public function __construct()
    {
        $this->request    = new Request();
        $this->brandModel = new Brand();
        $this->db         = Database::getInstance();
    }

    public function index(array $params): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->getBrandBySlug($params['slug'], $user['id']);

        $tasks = $this->db->fetchAll(
            "SELECT * FROM agent_tasks WHERE brand_id = ? ORDER BY created_at DESC LIMIT 50",
            [$brand['id']]
        );

        Response::view('agents.index', [
            'brand'     => $brand,
            'tasks'     => $tasks,
            'user'      => $user,
            'pageTitle' => 'AI Agents',
            'layout'    => 'app',
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function generateContent(array $params): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->getBrandBySlug($params['slug'], $user['id']);

        $input = array_merge($this->request->all(), [
            'brand_id' => $brand['id'],
            'user_id'  => $user['id'],
            'save'     => $this->request->post('save', true),
        ]);

        try {
            $agent  = new ContentGeneratorAgent();
            $result = $agent->generate($input);

            if ($this->request->isAjax()) {
                Response::success($result['output'], 'Content generated successfully.');
            } else {
                Response::flash('success', 'Content generated successfully!');
                Response::redirect('/brands/' . $params['slug'] . '/content');
            }
        } catch (\Throwable $e) {
            if ($this->request->isAjax()) {
                Response::error($e->getMessage());
            } else {
                Response::flash('error', 'Agent failed: ' . $e->getMessage());
                Response::redirect('/brands/' . $params['slug'] . '/agents');
            }
        }
    }

    public function extractStrategy(array $params): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->getBrandBySlug($params['slug'], $user['id']);

        $input = [
            'brand_id'      => $brand['id'],
            'user_id'       => $user['id'],
            'name'          => $this->request->post('name', 'Extracted Strategy'),
            'document_text' => $this->request->post('document_text', ''),
            'document_path' => '',
        ];

        // Handle file upload
        if ($this->request->hasFile('document')) {
            $file   = $this->request->file('document');
            $errors = \SociAI\Core\Security::validateUpload($file, ['application/pdf','text/plain','application/msword']);
            if (empty($errors)) {
                $filename   = Security::generateToken(16) . '_' . Security::safeFilename($file['name']);
                $uploadPath = UPLOAD_DIR . '/strategies/' . $brand['id'] . '/' . $filename;
                @mkdir(dirname($uploadPath), 0755, true);
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $input['document_path'] = $uploadPath;
                    $input['document_url']  = UPLOAD_URL . '/strategies/' . $brand['id'] . '/' . $filename;
                }
            }
        }

        try {
            $agent  = new StrategyExtractorAgent();
            $result = $agent->extract($input);

            if ($this->request->isAjax()) {
                Response::success($result['output'], 'Strategy extracted successfully.');
            } else {
                Response::flash('success', 'Strategy analysed and saved!');
                Response::redirect('/brands/' . $params['slug'] . '/strategy');
            }
        } catch (\Throwable $e) {
            if ($this->request->isAjax()) {
                Response::error($e->getMessage());
            } else {
                Response::flash('error', $e->getMessage());
                Response::redirect('/brands/' . $params['slug'] . '/strategy');
            }
        }
    }

    public function huntTrends(array $params): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->getBrandBySlug($params['slug'], $user['id']);

        $input = [
            'brand_id'  => $brand['id'],
            'user_id'   => $user['id'],
            'industry'  => $brand['industry'] ?? 'general',
            'region'    => $this->request->post('region', 'global'),
            'platforms' => $this->request->post('platforms', ['instagram','tiktok','twitter','linkedin']),
            'language'  => $this->request->post('language', 'english'),
        ];

        try {
            $agent  = new TrendHunterAgent();
            $result = $agent->hunt($input);

            Response::success($result['output'], 'Trends discovered successfully!');
        } catch (\Throwable $e) {
            Response::error($e->getMessage());
        }
    }

    public function suggestReplies(array $params): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->getBrandBySlug($params['slug'], $user['id']);

        $interactionIds = $this->request->post('interaction_ids', []);

        try {
            $agent  = new CommunityReplyAgent();
            $result = $agent->processBatch($brand['id'], $interactionIds);
            Response::success($result['output'], 'Replies generated successfully.');
        } catch (\Throwable $e) {
            Response::error($e->getMessage());
        }
    }

    public function scoreContent(array $params): void
    {
        Auth::requireAuth();
        $metrics = $this->request->json() ?? $this->request->all();

        $analyticsModel = new \SociAI\Models\Analytics();
        $score = $analyticsModel->calculateViralScore([
            'impressions'  => (int)($metrics['impressions']  ?? 1000),
            'likes'        => (int)($metrics['likes']        ?? 0),
            'comments'     => (int)($metrics['comments']     ?? 0),
            'shares'       => (int)($metrics['shares']       ?? 0),
            'saves'        => (int)($metrics['saves']        ?? 0),
            'clicks'       => (int)($metrics['clicks']       ?? 0),
            'video_views'  => (int)($metrics['video_views']  ?? 0),
        ]);

        Response::success(['viral_score' => $score]);
    }

    public function taskList(array $params): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->getBrandBySlug($params['slug'], $user['id']);

        $tasks = $this->db->fetchAll(
            "SELECT id, agent_type, task_name, status, progress, cost_usd, tokens_used, created_at, completed_at
             FROM agent_tasks WHERE brand_id = ? ORDER BY created_at DESC LIMIT 100",
            [$brand['id']]
        );

        Response::success($tasks);
    }

    public function taskStatus(array $params): void
    {
        Auth::requireAuth();
        $taskId = $params['id'] ?? '';
        $task   = $this->db->fetchOne("SELECT * FROM agent_tasks WHERE id = ?", [$taskId]);

        if (!$task) {
            Response::error('Task not found.', 404);
            return;
        }

        // Decode JSON fields
        foreach (['input_data','output_data'] as $field) {
            if (isset($task[$field]) && is_string($task[$field])) {
                $task[$field] = json_decode($task[$field], true);
            }
        }

        Response::success($task);
    }

    private function getBrandBySlug(string $slug, string $userId): array
    {
        $brand = $this->brandModel->findBySlug($slug);
        if (!$brand || !$this->brandModel->userCanAccess($brand['id'], $userId)) {
            abort(403, 'Access denied to this brand.');
        }
        return $brand;
    }
}
