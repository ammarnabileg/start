<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/AI.php';
require_once __DIR__ . '/../agents/CommunityAgent.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$auth = new Auth();
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = $auth->getCurrentUser();
$db = Database::getInstance();
$ai = new AI();
$brandId = $user['brand_id'] ?? '';
$request = new Request();

$path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$parts = explode('/', $path);
$action = $parts[2] ?? '';

$agent = new CommunityAgent($db, $ai, $brandId);

switch ($action) {
    case 'comments':
        $filter = $_GET['filter'] ?? 'all';
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $whereClause = 'brand_id = ?';
        $params = [$brandId];
        if ($filter !== 'all') {
            $whereClause .= ' AND sentiment = ?';
            $params[] = $filter;
        }
        $comments = $db->fetchAll(
            "SELECT * FROM community_interactions WHERE $whereClause ORDER BY created_at DESC LIMIT ?",
            array_merge($params, [$limit])
        );
        // Enrich with AI analysis if not already done
        $enriched = array_map(function($c) use ($agent) {
            if (empty($c['sentiment'])) {
                $analysis = $agent->analyzeSentiment($c['content'] ?? '');
                $c['sentiment'] = $analysis['sentiment'];
                $c['ai_confidence'] = $analysis['confidence'];
                $c['is_spam'] = (int)$agent->detectSpam($c['content'] ?? '');
                $c['suggested_reply'] = $agent->autoReplyComment($c);
            }
            return $c;
        }, $comments ?: []);
        echo json_encode(['success' => true, 'comments' => $enriched]);
        break;

    case 'reply':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }
        $data = $request->json();
        $commentId = $data['comment_id'] ?? '';
        $replyText = trim($data['reply'] ?? '');
        $platform = $data['platform'] ?? '';

        if (empty($replyText)) {
            http_response_code(400);
            echo json_encode(['error' => 'Reply text required']);
            exit;
        }

        // Post reply via platform API (simplified — marks as replied in DB)
        $db->update('community_interactions', [
            'replied_at' => date('Y-m-d H:i:s'),
            'reply_text' => $replyText,
            'replied_by' => $user['id'],
        ], 'id = ? AND brand_id = ?', [$commentId, $brandId]);

        echo json_encode(['success' => true, 'message' => 'Reply sent']);
        break;

    case 'suggest-reply':
        $data = $request->json();
        $comment = [
            'content' => $data['comment'] ?? '',
            'platform' => $data['platform'] ?? 'instagram',
            'sentiment' => $data['sentiment'] ?? 'neutral',
            'username' => $data['username'] ?? 'user',
        ];
        $suggestion = $agent->autoReplyComment($comment);
        echo json_encode(['success' => true, 'suggestion' => $suggestion]);
        break;

    case 'mark-spam':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }
        $data = $request->json();
        $db->update('community_interactions', ['is_spam' => 1, 'status' => 'spam'], 'id = ? AND brand_id = ?', [$data['comment_id'] ?? '', $brandId]);
        echo json_encode(['success' => true]);
        break;

    case 'escalate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }
        $data = $request->json();
        $db->update('community_interactions', ['status' => 'escalated', 'escalated_at' => date('Y-m-d H:i:s')], 'id = ? AND brand_id = ?', [$data['comment_id'] ?? '', $brandId]);
        echo json_encode(['success' => true]);
        break;

    case 'auto-reply-all':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }
        $pending = $db->fetchAll(
            'SELECT * FROM community_interactions WHERE brand_id = ? AND replied_at IS NULL AND is_spam = 0 AND status = "pending" LIMIT 50',
            [$brandId]
        );
        $replied = 0;
        foreach ($pending as $comment) {
            if (!$agent->needsEscalation($comment)) {
                $reply = $agent->autoReplyComment($comment);
                if ($reply) {
                    $db->update('community_interactions', [
                        'replied_at' => date('Y-m-d H:i:s'),
                        'reply_text' => $reply,
                        'replied_by' => 'ai',
                    ], 'id = ?', [$comment['id']]);
                    $replied++;
                }
            }
        }
        echo json_encode(['success' => true, 'replied' => $replied]);
        break;

    case 'stats':
        $stats = $db->fetchOne(
            'SELECT
                COUNT(*) as total,
                SUM(CASE WHEN replied_at IS NULL AND is_spam=0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN replied_at IS NOT NULL THEN 1 ELSE 0 END) as replied,
                SUM(CASE WHEN status="escalated" THEN 1 ELSE 0 END) as escalated,
                SUM(CASE WHEN is_spam=1 THEN 1 ELSE 0 END) as spam
             FROM community_interactions WHERE brand_id = ?',
            [$brandId]
        );
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}
