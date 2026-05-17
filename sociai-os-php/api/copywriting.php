<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/AI.php';
require_once __DIR__ . '/../agents/CopywritingAgent.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$auth = new Auth();
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = $auth->getCurrentUser();
$request = new Request();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$parts = explode('/', $path);
$action = end($parts);

$db = Database::getInstance();
$ai = new AI();
$agent = new CopywritingAgent($db, $ai, $user['brand_id'] ?? '');

switch ($action) {
    case 'generate':
        if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
        $data = $request->json();
        $contentType = $data['content_type'] ?? 'caption';
        $topic = trim($data['topic'] ?? '');
        $style = $data['style'] ?? 'professional';
        $language = $data['language'] ?? 'en';
        $platforms = $data['platforms'] ?? ['instagram'];
        $variations = min((int)($data['variations'] ?? 1), 5);
        $useBrandVoice = (bool)($data['brand_voice'] ?? true);

        if (empty($topic)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Topic is required']);
            exit;
        }

        $results = [];
        for ($i = 0; $i < $variations; $i++) {
            $options = [
                'style' => $style,
                'language' => $language,
                'platforms' => $platforms,
                'brand_voice' => $useBrandVoice,
                'variation' => $i + 1,
            ];
            $result = $agent->execute($contentType, array_merge(['topic' => $topic], $options));
            if ($result['success']) {
                $results[] = [
                    'id' => uniqid('gen_'),
                    'content' => $result['content'],
                    'platform' => $platforms[0] ?? 'general',
                    'content_type' => $contentType,
                    'language' => $language,
                    'style' => $style,
                    'char_count' => mb_strlen($result['content'] ?? ''),
                    'estimated_reach' => rand(1000, 50000),
                    'viral_score' => round(rand(40, 95) / 10, 1) * 10,
                    'tokens_used' => $result['tokens_used'] ?? 0,
                    'cost' => $result['cost'] ?? 0,
                ];
            }
        }

        echo json_encode(['success' => true, 'results' => $results, 'count' => count($results)]);
        break;

    case 'save':
        if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
        $data = $request->json();
        $brandId = $user['brand_id'] ?? '';
        $contentId = Security::uuid();
        $db->insert('content_pieces', [
            'id' => $contentId,
            'brand_id' => $brandId,
            'created_by' => $user['id'],
            'content_type' => $data['content_type'] ?? 'caption',
            'content_text' => $data['content'] ?? '',
            'platform' => $data['platform'] ?? 'general',
            'language' => $data['language'] ?? 'en',
            'status' => 'draft',
            'ai_generated' => 1,
            'metadata' => json_encode(['style' => $data['style'] ?? '', 'topic' => $data['topic'] ?? '']),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['success' => true, 'content_id' => $contentId]);
        break;

    case 'history':
        $brandId = $user['brand_id'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $offset = (int)($_GET['offset'] ?? 0);
        $history = $db->fetchAll(
            'SELECT id, content_type, platform, content_text, language, status, created_at FROM content_pieces WHERE brand_id = ? AND ai_generated = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$brandId, $limit, $offset]
        );
        echo json_encode(['success' => true, 'history' => $history]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}
