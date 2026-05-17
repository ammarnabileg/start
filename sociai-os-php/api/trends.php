<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/AI.php';
require_once __DIR__ . '/../agents/ResearchAgent.php';

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

$agent = new ResearchAgent($db, $ai, $brandId);

switch ($action) {
    case 'scan':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }
        $platform = $_GET['platform'] ?? 'all';
        $trends = $agent->scanTrends($platform);
        echo json_encode(['success' => true, 'trends' => $trends, 'scanned_at' => date('Y-m-d H:i:s')]);
        break;

    case 'hashtags':
        $topic = $_GET['topic'] ?? '';
        $platform = $_GET['platform'] ?? 'instagram';
        $hashtags = $agent->analyzeHashtags($topic, $platform);
        echo json_encode(['success' => true, 'hashtags' => $hashtags]);
        break;

    case 'sounds':
        $platform = $_GET['platform'] ?? 'tiktok';
        $sounds = $agent->findViralSounds($platform);
        echo json_encode(['success' => true, 'sounds' => $sounds]);
        break;

    case 'content-ideas':
        $data = $request->json();
        $trends = $data['trends'] ?? [];
        $ideas = $agent->generateReactiveContent($trends);
        echo json_encode(['success' => true, 'ideas' => $ideas]);
        break;

    case 'list':
        $platform = $_GET['platform'] ?? null;
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $whereClause = 'brand_id = ?';
        $params = [$brandId];
        if ($platform) {
            $whereClause .= ' AND platform = ?';
            $params[] = $platform;
        }
        $trends = $db->fetchAll(
            "SELECT * FROM trend_opportunities WHERE $whereClause ORDER BY growth_rate DESC LIMIT ?",
            array_merge($params, [$limit])
        );
        echo json_encode(['success' => true, 'trends' => $trends ?: []]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}
