<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../agents/StrategyAgent.php';

class CampaignsController
{
    private \PDO $db;
    private Auth $auth;
    private Request $request;
    private Response $response;

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

        $status = $this->request->get('status', 'all');
        $page   = max(1, (int) $this->request->get('page', 1));
        $perPage = 12;

        [$campaigns, $total] = $this->fetchCampaigns($brandId, $status, $page, $perPage);

        $this->response->view('campaigns/index', [
            'title'     => 'Campaigns – SociAI OS',
            'campaigns' => $campaigns,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'status'    => $status,
            'brandId'   => $brandId,
        ]);
    }

    public function create(): void
    {
        $this->auth->requireAuth();
        $this->response->view('campaigns/create', ['title' => 'New Campaign – SociAI OS']);
    }

    public function store(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $data   = $this->validateCampaignData();
        if (isset($data['error'])) {
            $this->response->json(['success' => false, 'error' => $data['error']], 422);
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO campaigns
             (brand_id, name, description, goal, target_audience, platforms, start_date, end_date,
              budget, status, brief, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "draft", ?, ?, NOW())'
        );
        $stmt->execute([
            $brandId,
            $data['name'],
            $data['description'],
            $data['goal'],
            $data['target_audience'],
            json_encode($data['platforms']),
            $data['start_date'],
            $data['end_date'],
            $data['budget'],
            $data['brief'],
            $user['id'],
        ]);
        $campaignId = (int) $this->db->lastInsertId();

        $this->response->json([
            'success'     => true,
            'campaign_id' => $campaignId,
            'message'     => 'Campaign created successfully.',
        ]);
    }

    public function show(int $id): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $campaign = $this->getCampaign($id, $brandId);
        if (!$campaign) {
            $this->response->view('errors/404', ['title' => '404 – Not Found']);
            return;
        }

        $campaign['platforms'] = json_decode($campaign['platforms'] ?? '[]', true);

        // Campaign content posts
        $stmt = $this->db->prepare(
            'SELECT cp.id, cp.platform, cp.content_type, cp.content_text, cp.status,
                    cp.scheduled_at, cp.published_at,
                    COALESCE(pm.impressions,0) AS impressions,
                    COALESCE(pm.engagement_rate,0) AS engagement_rate
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id = cp.id
             WHERE cp.campaign_id = ?
             ORDER BY cp.created_at DESC'
        );
        $stmt->execute([$id]);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Campaign metrics summary
        $metrics = $this->getCampaignMetrics($id);

        $this->response->view('campaigns/show', [
            'title'    => $campaign['name'] . ' – SociAI OS',
            'campaign' => $campaign,
            'posts'    => $posts,
            'metrics'  => $metrics,
        ]);
    }

    public function edit(int $id): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $campaign = $this->getCampaign($id, $brandId);
        if (!$campaign) {
            $this->response->view('errors/404', ['title' => '404 – Not Found']);
            return;
        }

        $campaign['platforms'] = json_decode($campaign['platforms'] ?? '[]', true);

        $this->response->view('campaigns/edit', [
            'title'    => 'Edit Campaign – SociAI OS',
            'campaign' => $campaign,
        ]);
    }

    public function update(int $id): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $campaign = $this->getCampaign($id, $brandId);
        if (!$campaign) {
            $this->response->json(['success' => false, 'error' => 'Campaign not found'], 404);
            return;
        }

        $data = $this->validateCampaignData();
        if (isset($data['error'])) {
            $this->response->json(['success' => false, 'error' => $data['error']], 422);
            return;
        }

        $this->db->prepare(
            'UPDATE campaigns
             SET name = ?, description = ?, goal = ?, target_audience = ?, platforms = ?,
                 start_date = ?, end_date = ?, budget = ?, updated_at = NOW()
             WHERE id = ? AND brand_id = ?'
        )->execute([
            $data['name'],
            $data['description'],
            $data['goal'],
            $data['target_audience'],
            json_encode($data['platforms']),
            $data['start_date'],
            $data['end_date'],
            $data['budget'],
            $id,
            $brandId,
        ]);

        $this->response->json(['success' => true, 'message' => 'Campaign updated.']);
    }

    public function delete(int $id): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $campaign = $this->getCampaign($id, $brandId);
        if (!$campaign) {
            $this->response->json(['success' => false, 'error' => 'Campaign not found'], 404);
            return;
        }

        $this->db->prepare(
            "UPDATE campaigns SET status = 'deleted', deleted_at = NOW() WHERE id = ? AND brand_id = ?"
        )->execute([$id, $brandId]);

        $this->response->json(['success' => true, 'message' => 'Campaign deleted.']);
    }

    public function generateBrief(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $goal     = trim($this->request->post('goal', ''));
        $audience = trim($this->request->post('target_audience', ''));
        $platforms = (array) $this->request->post('platforms', ['instagram', 'linkedin']);

        if (empty($goal)) {
            $this->response->json(['success' => false, 'error' => 'Campaign goal is required'], 400);
            return;
        }

        try {
            $agent = new StrategyAgent($brandId);
            $brief = $agent->generateCampaignBrief($goal, $audience, $platforms);

            $this->response->json([
                'success' => true,
                'brief'   => $brief,
            ]);

        } catch (\Throwable $e) {
            error_log('Campaign brief generation failed: ' . $e->getMessage());
            $this->response->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function fetchCampaigns(int $brandId, string $status, int $page, int $perPage): array
    {
        $where  = ["brand_id = ?", "status != 'deleted'"];
        $params = [$brandId];

        if ($status !== 'all') {
            $where[]  = 'status = ?';
            $params[] = $status;
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM campaigns WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $this->db->prepare(
            "SELECT id, name, description, goal, status, start_date, end_date, budget, created_at
             FROM campaigns
             WHERE {$whereClause}
             ORDER BY created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $dataStmt->execute($params);
        $campaigns = $dataStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [$campaigns, $total];
    }

    private function getCampaign(int $id, int $brandId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM campaigns WHERE id = ? AND brand_id = ? AND status != 'deleted' LIMIT 1"
        );
        $stmt->execute([$id, $brandId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function getCampaignMetrics(int $campaignId): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(cp.id) AS post_count,
                    COALESCE(SUM(pm.impressions),0) AS total_reach,
                    COALESCE(SUM(pm.engagement_count),0) AS total_engagement,
                    COALESCE(AVG(pm.engagement_rate),0) AS avg_engagement_rate
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id = cp.id
             WHERE cp.campaign_id = ?'
        );
        $stmt->execute([$campaignId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function validateCampaignData(): array
    {
        $name      = trim($this->request->post('name', ''));
        $goal      = trim($this->request->post('goal', ''));
        $startDate = $this->request->post('start_date', '');
        $endDate   = $this->request->post('end_date', '');
        $budget    = (float) $this->request->post('budget', 0);
        $platforms = (array) $this->request->post('platforms', []);

        if (strlen($name) < 3) return ['error' => 'Campaign name must be at least 3 characters'];
        if (empty($goal))      return ['error' => 'Campaign goal is required'];
        if (empty($startDate)) return ['error' => 'Start date is required'];
        if ($endDate && strtotime($endDate) < strtotime($startDate)) {
            return ['error' => 'End date must be after start date'];
        }

        return [
            'name'            => $name,
            'description'     => trim($this->request->post('description', '')),
            'goal'            => $goal,
            'target_audience' => trim($this->request->post('target_audience', '')),
            'platforms'       => $platforms,
            'start_date'      => $startDate,
            'end_date'        => $endDate ?: null,
            'budget'          => $budget,
            'brief'           => trim($this->request->post('brief', '')),
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
