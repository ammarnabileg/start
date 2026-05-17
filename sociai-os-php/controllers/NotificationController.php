<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth, Database, Request, Response};

class NotificationController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function index(array $p): void
    {
        Auth::requireAuth();
        $u      = Auth::getCurrentUser();
        $notifs = $this->getNotifications($u['id']);
        if ((new Request())->isAjax()) {
            Response::success($notifs);
            return;
        }
        Response::view('notifications.index', [
            'notifications' => $notifs,
            'user'          => $u,
            'pageTitle'     => 'Notifications',
            'layout'        => 'app',
            'notifCount'    => count(array_filter($notifs, fn($n) => !$n['is_read'])),
            'csrf'          => Auth::csrfToken(),
        ]);
    }

    public function markRead(array $p): void
    {
        Auth::requireAuth();
        $u   = Auth::getCurrentUser();
        $ids = (new Request())->post('ids', null);

        if ($ids === null) {
            // Mark all read
            $this->db->prepare(
                'UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0'
            )->execute([$u['id']]);
        } else {
            $ids = array_filter(array_map('intval', (array)$ids));
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $this->db->prepare(
                    "UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND id IN ({$ph})"
                )->execute(array_merge([$u['id']], $ids));
            }
        }
        Response::success([], 'Marked as read.');
    }

    private function getNotifications(string $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, type, title, message, action_url, is_read, read_at, created_at
             FROM notifications WHERE user_id=?
             ORDER BY created_at DESC LIMIT 50'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
