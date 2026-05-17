<?php
declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

require_once __DIR__ . '/../agents/CommunityAgent.php';

class CommunityController
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
        [$queue,] = $this->fetchInteractionQueue($brandId, 'new', 1, 10);
        Response::view('community/index', [
            'title'         => 'Community - SociAI OS',
            'interactionStats' => $this->getInteractionStats($brandId),
            'recentQueue'   => $queue,
            'brandId'       => $brandId,
            'csrf'          => Auth::csrfToken(),
        ]);
    }

    public function getQueue(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $status  = $this->request->get('status', 'new');
        $platform= $this->request->get('platform', 'all');
        $page    = max(1, (int)$this->request->get('page', 1));
        [$items, $total] = $this->fetchInteractionQueue($brandId, $status, $page, 25, $platform);
        Response::json(['success' => true, 'items' => $items, 'total' => $total, 'page' => $page]);
    }

    public function reply(): void
    {
        Auth::requireAuth();
        $user          = Auth::getCurrentUser();
        $brandId       = $this->getActiveBrandId($user['id']);
        $interactionId = (int)$this->request->post('interaction_id', 0);
        $replyText     = trim($this->request->post('reply_text', ''));
        $useAI         = (bool)$this->request->post('use_ai', false);

        $interaction = $this->getInteraction($interactionId, $brandId);
        if (!$interaction) {
            Response::json(['success' => false, 'error' => 'Interaction not found'], 404);
            return;
        }

        if ($useAI) {
            try {
                $agent     = new \CommunityAgent($brandId);
                $res       = $agent->autoReplyComment($interaction['message_text'], '', $interaction['platform']);
                $replyText = $res['reply'] ?? $replyText;
            } catch (\Throwable $e) {
                error_log('AI reply: ' . $e->getMessage());
            }
        }

        if (empty($replyText)) {
            Response::json(['success' => false, 'error' => 'Reply text required'], 400);
            return;
        }

        $this->db->prepare(
            'UPDATE community_interactions
             SET actual_reply=?, replied_by=?, replied_at=NOW(), status="replied"
             WHERE id=? AND brand_id=?'
        )->execute([$replyText, $user['id'], $interactionId, $brandId]);

        Response::json(['success' => true, 'reply_text' => $replyText, 'is_ai' => $useAI]);
    }

    public function markSpam(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $id      = (int)$this->request->post('interaction_id', 0);
        $item    = $this->getInteraction($id, $brandId);
        if (!$item) {
            Response::json(['success' => false, 'error' => 'Not found'], 404);
            return;
        }
        $this->db->prepare(
            'UPDATE community_interactions SET is_spam=1, status="ignored" WHERE id=?'
        )->execute([$id]);
        Response::json(['success' => true, 'message' => 'Marked as spam.']);
    }

    private function fetchInteractionQueue(string $brandId, string $status, int $page, int $perPage, string $platform = 'all'): array
    {
        $where  = ['brand_id=?']; $params = [$brandId];
        if ($status !== 'all') {
            $where[] = 'status=?'; $params[] = $status;
        }
        if ($platform !== 'all') {
            $where[] = 'platform=?'; $params[] = $platform;
        }
        $wc     = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        $cnt    = $this->db->prepare("SELECT COUNT(*) FROM community_interactions WHERE {$wc}");
        $cnt->execute($params);
        $total  = (int)$cnt->fetchColumn();
        $s      = $this->db->prepare(
            "SELECT id, platform, interaction_type, author_name, author_handle, message_text,
                    sentiment, is_spam, is_lead, status, created_at
             FROM community_interactions WHERE {$wc}
             ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $s->execute($params);
        return [$s->fetchAll(\PDO::FETCH_ASSOC), $total];
    }

    private function getInteraction(int $id, string $brandId): array|false
    {
        $s = $this->db->prepare(
            'SELECT * FROM community_interactions WHERE id=? AND brand_id=? LIMIT 1'
        );
        $s->execute([$id, $brandId]);
        return $s->fetch(\PDO::FETCH_ASSOC);
    }

    private function getInteractionStats(string $brandId): array
    {
        $s = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(status='new') AS new_count,
                    SUM(status='replied') AS replied,
                    SUM(is_spam=1) AS spam,
                    SUM(is_lead=1) AS leads
             FROM community_interactions WHERE brand_id=?"
        );
        $s->execute([$brandId]);
        return $s->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function getActiveBrandId(string $userId): string
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (string)$_SESSION['active_brand_id'];
        }
        $s = $this->db->prepare(
            'SELECT b.id FROM brands b
             INNER JOIN team_members tm ON tm.brand_id = b.id
             WHERE tm.user_id = ?
             ORDER BY tm.created_at ASC LIMIT 1'
        );
        $s->execute([$userId]);
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['active_brand_id'] = $row['id'];
            return (string)$row['id'];
        }
        return '';
    }
}
