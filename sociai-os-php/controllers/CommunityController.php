<?php
/**
 * SociAI OS - Community Controller
 * Real community inbox: sync, AI replies, approve & publish.
 */

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response, Security, PlatformManager};

require_once __DIR__ . '/../core/PlatformManager.php';

class CommunityController
{
    private Database $db;
    private Request  $request;

    public function __construct()
    {
        $this->db      = Database::getInstance();
        $this->request = new Request();
    }

    // --------------------------------------------------------
    // Dashboard view — community inbox
    // --------------------------------------------------------

    public function index(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $status   = $this->request->get('status',   'new');
        $platform = $this->request->get('platform',  'all');
        $type     = $this->request->get('type',      'all');
        $page     = max(1, (int)$this->request->get('page', 1));

        [$items, $total, $pages] = $this->fetchInteractions($brandId, $status, $platform, $type, $page, 25);
        $stats   = $this->getStats($brandId);
        $brand   = $this->getBrand($brandId);

        Response::view('community/index', [
            'title'       => 'Community – SociAI OS',
            'interactions'=> $items,
            'stats'       => $stats,
            'brand'       => $brand,
            'brandId'     => $brandId,
            'currentPage' => $page,
            'totalPages'  => $pages,
            'totalItems'  => $total,
            'filterStatus'   => $status,
            'filterPlatform' => $platform,
            'filterType'     => $type,
            'csrf'        => Auth::csrfToken(),
        ]);
    }

    // --------------------------------------------------------
    // API: Get queue (JSON)
    // --------------------------------------------------------

    public function getQueue(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $status  = $this->request->get('status',   'all');
        $platform= $this->request->get('platform',  'all');
        $type    = $this->request->get('type',      'all');
        $page    = max(1, (int)$this->request->get('page', 1));

        [$items, $total, $pages] = $this->fetchInteractions($brandId, $status, $platform, $type, $page, 25);

        Response::json([
            'success' => true,
            'items'   => $items,
            'total'   => $total,
            'pages'   => $pages,
            'page'    => $page,
        ]);
    }

    // --------------------------------------------------------
    // Reply to an interaction — approve AI or custom text
    // --------------------------------------------------------

    public function reply(array $params = []): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $interactionId = $params['id'] ?? $this->request->post('interaction_id', '');
        $replyText     = trim((string)$this->request->post('reply_text', ''));
        $useAiReply    = (bool)$this->request->post('use_ai_reply', false);
        $publish       = (bool)$this->request->post('publish', true);

        if (empty($interactionId)) {
            Response::json(['success' => false, 'error' => 'interaction_id required'], 400);
            return;
        }

        $interaction = $this->getInteraction((string)$interactionId, $brandId);
        if (!$interaction) {
            Response::json(['success' => false, 'error' => 'Interaction not found'], 404);
            return;
        }

        // Use AI-suggested reply if requested
        if ($useAiReply && !empty($interaction['ai_suggested_reply'])) {
            $replyText = $interaction['ai_suggested_reply'];
        }

        if (empty($replyText)) {
            Response::json(['success' => false, 'error' => 'Reply text is required'], 400);
            return;
        }

        $published = false;
        if ($publish) {
            // Publish via platform API
            $brand     = $this->getBrand($brandId);
            $published = PlatformManager::publishReply($interaction, $replyText, $brandId);
        }

        if (!$publish || $published) {
            // Update DB regardless (save the reply text)
            $this->db->update(
                'community_interactions',
                [
                    'actual_reply' => $replyText,
                    'replied_by'   => $user['id'],
                    'replied_at'   => date('Y-m-d H:i:s'),
                    'status'       => 'replied',
                ],
                'id = ? AND brand_id = ?',
                [(int)$interactionId, $brandId]
            );
        }

        Response::json([
            'success'    => true,
            'reply_text' => $replyText,
            'published'  => $published,
        ]);
    }

    // --------------------------------------------------------
    // Ignore an interaction
    // --------------------------------------------------------

    public function ignore(array $params = []): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $interactionId = $params['id'] ?? $this->request->post('interaction_id', '');

        if (empty($interactionId)) {
            Response::json(['success' => false, 'error' => 'interaction_id required'], 400);
            return;
        }

        $interaction = $this->getInteraction((string)$interactionId, $brandId);
        if (!$interaction) {
            Response::json(['success' => false, 'error' => 'Not found'], 404);
            return;
        }

        $this->db->update(
            'community_interactions',
            ['status' => 'ignored'],
            'id = ? AND brand_id = ?',
            [(int)$interactionId, $brandId]
        );

        Response::json(['success' => true, 'message' => 'Interaction ignored.']);
    }

    // --------------------------------------------------------
    // Mark as spam
    // --------------------------------------------------------

    public function markSpam(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $id      = $this->request->post('interaction_id', '');

        $item = $this->getInteraction((string)$id, $brandId);
        if (!$item) {
            Response::json(['success' => false, 'error' => 'Not found'], 404);
            return;
        }

        $this->db->update(
            'community_interactions',
            ['is_spam' => 1, 'status' => 'ignored'],
            'id = ? AND brand_id = ?',
            [(int)$id, $brandId]
        );

        Response::json(['success' => true, 'message' => 'Marked as spam.']);
    }

    // --------------------------------------------------------
    // Bulk reply — approve all pending items with AI replies
    // --------------------------------------------------------

    public function bulkReply(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $pending = $this->db->fetchAll(
            "SELECT * FROM community_interactions
             WHERE brand_id = ? AND status = 'new' AND ai_suggested_reply IS NOT NULL AND ai_suggested_reply != ''
             LIMIT 50",
            [$brandId]
        );

        $replied = 0;
        foreach ($pending as $interaction) {
            $replyText = $interaction['ai_suggested_reply'] ?? '';
            if (empty($replyText)) {
                continue;
            }

            $published = PlatformManager::publishReply($interaction, $replyText, $brandId);

            if ($published) {
                $this->db->update(
                    'community_interactions',
                    [
                        'actual_reply' => $replyText,
                        'replied_by'   => $user['id'],
                        'replied_at'   => date('Y-m-d H:i:s'),
                        'status'       => 'replied',
                    ],
                    'id = ?',
                    [$interaction['id']]
                );
                $replied++;
            }
        }

        Response::json([
            'success' => true,
            'replied' => $replied,
            'message' => "Bulk replied to {$replied} interactions.",
        ]);
    }

    // --------------------------------------------------------
    // Manual sync trigger
    // --------------------------------------------------------

    public function syncNow(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        try {
            $newCount = PlatformManager::syncAllInteractions($brandId);
            Response::json([
                'success'   => true,
                'new_count' => $newCount,
                'message'   => "Synced. {$newCount} new interaction(s) found.",
            ]);
        } catch (\Throwable $e) {
            error_log('[CommunityController] syncNow error: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }

    // --------------------------------------------------------
    // Private helpers
    // --------------------------------------------------------

    /**
     * @return array{0: array, 1: int, 2: int} [items, total, pages]
     */
    private function fetchInteractions(
        string $brandId,
        string $status,
        string $platform,
        string $type,
        int    $page,
        int    $perPage
    ): array {
        $where  = ['brand_id = ?'];
        $params = [$brandId];

        $validStatuses = ['new', 'in_review', 'replied', 'ignored', 'escalated'];
        if ($status !== 'all' && in_array($status, $validStatuses, true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        if ($platform !== 'all') {
            $where[] = 'platform = ?';
            $params[] = $platform;
        }

        $validTypes = ['comment', 'dm', 'mention', 'review'];
        if ($type !== 'all' && in_array($type, $validTypes, true)) {
            $where[] = 'interaction_type = ?';
            $params[] = $type;
        }

        $wc     = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM community_interactions WHERE {$wc}",
            $params
        );

        $items = $this->db->fetchAll(
            "SELECT id, platform, interaction_type, platform_item_id,
                    author_name, author_handle, author_avatar,
                    message_text, sentiment, is_spam, is_lead,
                    ai_suggested_reply, actual_reply, status, replied_at, created_at
             FROM community_interactions
             WHERE {$wc}
             ORDER BY created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $pages = (int)ceil($total / $perPage);

        return [$items, $total, max(1, $pages)];
    }

    private function getInteraction(string $id, string $brandId): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM community_interactions WHERE id = ? AND brand_id = ? LIMIT 1",
            [(int)$id, $brandId]
        );
    }

    private function getStats(string $brandId): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'new') AS pending,
                SUM(status = 'replied') AS replied,
                SUM(status = 'ignored') AS ignored,
                SUM(is_spam = 1) AS spam,
                SUM(DATE(replied_at) = CURDATE()) AS replied_today,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, replied_at)) AS avg_response_minutes
             FROM community_interactions
             WHERE brand_id = ?",
            [$brandId]
        );
        return $row ?: [
            'total' => 0, 'pending' => 0, 'replied' => 0,
            'ignored' => 0, 'spam' => 0, 'replied_today' => 0, 'avg_response_minutes' => null,
        ];
    }

    private function getBrand(string $brandId): array
    {
        return $this->db->fetchOne(
            "SELECT id, name, description, settings FROM brands WHERE id = ? LIMIT 1",
            [$brandId]
        ) ?: ['id' => $brandId, 'name' => '', 'description' => '', 'settings' => '{}'];
    }

    private function getActiveBrandId(string $userId): string
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (string)$_SESSION['active_brand_id'];
        }
        $row = $this->db->fetchOne(
            "SELECT b.id FROM brands b
             INNER JOIN team_members tm ON tm.brand_id = b.id
             WHERE tm.user_id = ?
             ORDER BY tm.created_at ASC LIMIT 1",
            [$userId]
        );
        if ($row) {
            $_SESSION['active_brand_id'] = $row['id'];
            return (string)$row['id'];
        }
        return '';
    }
}
