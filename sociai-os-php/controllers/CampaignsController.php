<?php

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

require_once __DIR__ . '/../agents/StrategyAgent.php';

class CampaignsController
{
    private Database $db;
    private Request $request;

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

        $status  = $this->request->get('status', 'all');
        $page    = max(1, (int)$this->request->get('page', 1));
        $perPage = 12;

        [$campaigns, $total] = $this->fetchCampaigns($brandId, $status, $page, $perPage);

        Response::view('campaigns/index', [
            'title'     => 'Campaigns – SociAI OS',
            'campaigns' => $campaigns,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'status'    => $status,
            'brandId'   => $brandId,
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function create(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        Response::view('campaigns/create', [
            'title'   => 'New Campaign – SociAI OS',
            'brandId' => $brandId,
            'csrf'    => Auth::csrfToken(),
        ]);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $data = $this->validateCampaignData();
        if (isset($data['error'])) {
            Response::json(['success' => false, 'error' => $data['error']], 422);
            return;
        }

        $id = $this->generateUuid();
        $this->db->prepare(
            'INSERT INTO campaigns
             (id, brand_id, name, description, goal, target_platforms, start_date, end_date,
              budget, status, ai_brief, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "draft", ?, ?, NOW())'
        )->execute([
            $id,
            $brandId,
            $data['name'],
            $data['description'],
            $data['goal'],
            json_encode($data['platforms']),
            $data['start_date'],
            $data['end_date'],
            $data['budget'],
            $data['brief'],
            $user['id'],
        ]);

        Response::json([
            'success'     => true,
            'campaign_id' => $id,
            'message'     => 'Campaign created successfully.',
        ]);
    }

    public function show(string $id): void
    {
        Auth::requireAuth();
        $user     = Auth::getCurrentUser();
        $brandId  = $this->getActiveBrandId($user['id']);
        $campaign = $this->getCampaign($id, $brandId);

        if (!$campaign) {
            Response::view('errors/404', ['title' => '404 – Not Found']);
            return;
        }

        $campaign['target_platforms'] = json_decode($campaign['target_platforms'] ?? '[]', true);

        // Campaign content pieces
        $stmt = $this->db->prepare(
            'SELECT cp.id, cp.content_type, cp.topic, cp.body_text, cp.approval_status,
                    cp.created_at, cp.viral_score
             FROM content_pieces cp
             WHERE cp.campaign_id = ?
             ORDER BY cp.created_at DESC'
        );
        $stmt->execute([$id]);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        Response::view('campaigns/show', [
            'title'    => $campaign['name'] . ' – SociAI OS',
            'campaign' => $campaign,
            'posts'    => $posts,
            'csrf'     => Auth::csrfToken(),
        ]);
    }

    public function edit(string $id): void
    {
        Auth::requireAuth();
        $user     = Auth::getCurrentUser();
        $brandId  = $this->getActiveBrandId($user['id']);
        $campaign = $this->getCampaign($id, $brandId);
        if (!$campaign) {
            Response::view('errors/404', ['title' => '404 – Not Found']);
            return;
        }
        $campaign['target_platforms'] = json_decode($campaign['target_platforms'] ?? '[]', true);
        Response::view('campaigns/edit', [
            'title'    => 'Edit Campaign – SociAI OS',
            'campaign' => $campaign,
            'csrf'     => Auth::csrfToken(),
        ]);
    }

    public function update(string $id): void
    {
        Auth::requireAuth();
        $user     = Auth::getCurrentUser();
        $brandId  = $this->getActiveBrandId($user['id']);
        $campaign = $this->getCampaign($id, $brandId);
        if (!$campaign) {
            Response::json(['success' => false, 'error' => 'Campaign not found'], 404);
            return;
        }
        $data = $this->validateCampaignData();
        if (isset($data['error'])) {
            Response::json(['success' => false, 'error' => $data['error']], 422);
            return;
        }
        $this->db->prepare(
            'UPDATE campaigns
             SET name = ?, description = ?, goal = ?, target_platforms = ?,
                 start_date = ?, end_date = ?, budget = ?, updated_at = NOW()
             WHERE id = ? AND brand_id = ?'
        )->execute([
            $data['name'],
            $data['description'],
            $data['goal'],
            json_encode($data['platforms']),
            $data['start_date'],
            $data['end_date'],
            $data['budget'],
            $id,
            $brandId,
        ]);
        Response::json(['success' => true, 'message' => 'Campaign updated.']);
    }

    public function delete(string $id): void
    {
        Auth::requireAuth();
        $user     = Auth::getCurrentUser();
        $brandId  = $this->getActiveBrandId($user['id']);
        $campaign = $this->getCampaign($id, $brandId);
        if (!$campaign) {
            Response::json(['success' => false, 'error' => 'Campaign not found'], 404);
            return;
        }
        $this->db->prepare(
            "UPDATE campaigns SET status = 'completed', updated_at = NOW() WHERE id = ? AND brand_id = ?"
        )->execute([$id, $brandId]);
        Response::json(['success' => true, 'message' => 'Campaign deleted.']);
    }

    public function generateBrief(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $goal      = trim($this->request->post('goal', ''));
        $platforms = (array)$this->request->post('platforms', ['instagram', 'linkedin']);

        if (empty($goal)) {
            Response::json(['success' => false, 'error' => 'Campaign goal is required'], 400);
            return;
        }

        try {
            $agent = new \StrategyAgent($brandId);
            $brief = $agent->generateCampaignBrief($goal, '', $platforms);
            Response::json(['success' => true, 'brief' => $brief]);
        } catch (\Throwable $e) {
            error_log('Campaign brief generation failed: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function fetchCampaigns(string $brandId, string $status, int $page, int $perPage): array
    {
        $where  = ["brand_id = ?", "status != 'completed'"];
        $params = [$brandId];

        if ($status !== 'all') {
            $where[]  = 'status = ?';
            $params[] = $status;
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM campaigns WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

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

    private function getCampaign(string $id, string $brandId): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM campaigns WHERE id = ? AND brand_id = ? LIMIT 1"
        );
        $stmt->execute([$id, $brandId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function validateCampaignData(): array
    {
        $name      = trim($this->request->post('name', ''));
        $goal      = trim($this->request->post('goal', ''));
        $startDate = $this->request->post('start_date', '');
        $endDate   = $this->request->post('end_date', '');
        $budget    = (float)$this->request->post('budget', 0);
        $platforms = (array)$this->request->post('platforms', []);

        if (strlen($name) < 3) return ['error' => 'Campaign name must be at least 3 characters'];
        if (empty($goal))      return ['error' => 'Campaign goal is required'];
        if (empty($startDate)) return ['error' => 'Start date is required'];
        if ($endDate && strtotime($endDate) < strtotime($startDate)) {
            return ['error' => 'End date must be after start date'];
        }

        return [
            'name'        => $name,
            'description' => trim($this->request->post('description', '')),
            'goal'        => $goal,
            'platforms'   => $platforms,
            'start_date'  => $startDate,
            'end_date'    => $endDate ?: null,
            'budget'      => $budget,
            'brief'       => trim($this->request->post('brief', '')),
        ];
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
