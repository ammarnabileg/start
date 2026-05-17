<?php
declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

require_once __DIR__ . '/../agents/StrategyAgent.php';

class StrategyController
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
        $strategy = $this->loadStrategy($brandId);
        Response::view('strategy/index', [
            'title'    => 'Strategy - SociAI OS',
            'strategy' => $strategy,
            'brandId'  => $brandId,
            'csrf'     => Auth::csrfToken(),
        ]);
    }

    public function show(string $id): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $stmt    = $this->db->prepare(
            'SELECT * FROM marketing_strategies WHERE id=? AND brand_id=? LIMIT 1'
        );
        $stmt->execute([$id, $brandId]);
        $strategy = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$strategy) {
            abort(404);
        }
        Response::view('strategy/show', [
            'title'    => $strategy['name'] . ' - SociAI OS',
            'strategy' => $strategy,
            'csrf'     => Auth::csrfToken(),
        ]);
    }

    public function store(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $name        = trim($this->request->post('name', 'Untitled Strategy'));
        $brandTone   = trim($this->request->post('brand_tone', ''));
        $aiSummary   = trim($this->request->post('ai_summary', ''));

        $id = $this->generateUuid();
        $this->db->prepare(
            'INSERT INTO marketing_strategies
             (id, brand_id, name, brand_tone, ai_summary, is_active, created_by, created_at)
             VALUES (?,?,?,?,?,1,?,NOW())'
        )->execute([$id, $brandId, $name, $brandTone, $aiSummary, $user['id']]);

        Response::json(['success' => true, 'id' => $id, 'message' => 'Strategy saved.']);
    }

    public function upload(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        if (empty($_FILES['document'])) {
            Response::json(['success' => false, 'error' => 'No file uploaded'], 400);
            return;
        }
        $file = $_FILES['document'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['size'] > 10 * 1024 * 1024) {
            Response::json(['success' => false, 'error' => 'File too large'], 422);
            return;
        }
        if (!in_array($ext, ['pdf', 'txt', 'docx', 'doc'], true)) {
            Response::json(['success' => false, 'error' => 'Unsupported file type'], 422);
            return;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['success' => false, 'error' => 'Upload error'], 500);
            return;
        }
        $dir   = __DIR__ . '/../storage/uploads/strategy/' . $brandId . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = uniqid('strategy_', true) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $fname)) {
            Response::json(['success' => false, 'error' => 'Save failed'], 500);
            return;
        }
        $id  = $this->generateUuid();
        $url = '/storage/uploads/strategy/' . $brandId . '/' . $fname;
        $this->db->prepare(
            'INSERT INTO marketing_strategies
             (id, brand_id, name, raw_document_url, is_active, created_by, created_at)
             VALUES (?,?,?,?,1,?,NOW())'
        )->execute([$id, $brandId, $file['name'], $url, $user['id']]);
        Response::json(['success' => true, 'id' => $id, 'path' => $url]);
    }

    public function analyze(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $docId   = trim($this->request->post('doc_id', ''));
        if (empty($docId)) {
            Response::json(['success' => false, 'error' => 'Invalid doc_id'], 400);
            return;
        }
        $stmt = $this->db->prepare(
            'SELECT id, raw_document_url FROM marketing_strategies WHERE id=? AND brand_id=? LIMIT 1'
        );
        $stmt->execute([$docId, $brandId]);
        $doc = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$doc) {
            Response::json(['success' => false, 'error' => 'Document not found'], 404);
            return;
        }
        try {
            $agent    = new \StrategyAgent($brandId);
            $filePath = __DIR__ . '/..' . $doc['raw_document_url'];
            $analysis = $agent->analyzeDocument($filePath);
            $this->db->prepare(
                'UPDATE marketing_strategies SET extracted_data=?, ai_summary=?, updated_at=NOW() WHERE id=?'
            )->execute([json_encode($analysis), $analysis['summary'] ?? '', $docId]);
            Response::json(['success' => true, 'analysis' => $analysis]);
        } catch (\Throwable $e) {
            error_log('Strategy analyze: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function save(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $id      = trim($this->request->post('id', ''));

        if (empty($id)) {
            Response::json(['success' => false, 'error' => 'Strategy ID required'], 400);
            return;
        }

        $fields = [
            'brand_tone', 'content_pillars', 'target_audience', 'business_goals',
        ];
        $updates = [];
        $params  = [];
        foreach ($fields as $field) {
            $val = trim((string)$this->request->post($field, ''));
            if (!empty($val)) {
                $updates[] = "{$field}=?";
                $params[]  = $val;
            }
        }
        if (!empty($updates)) {
            $updates[] = 'updated_at=NOW()';
            $params[]  = $id;
            $params[]  = $brandId;
            $this->db->prepare(
                'UPDATE marketing_strategies SET ' . implode(',', $updates) . ' WHERE id=? AND brand_id=?'
            )->execute($params);
        }
        Response::json(['success' => true, 'message' => 'Strategy saved.']);
    }

    private function loadStrategy(string $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM marketing_strategies WHERE brand_id=? AND is_active=1
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$brandId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return [];
        foreach (['extracted_data', 'content_pillars', 'target_audience', 'business_goals'] as $f) {
            if (isset($row[$f]) && is_string($row[$f])) {
                $row[$f] = json_decode($row[$f], true) ?? $row[$f];
            }
        }
        return $row;
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
             WHERE tm.user_id = ?
             ORDER BY tm.created_at ASC LIMIT 1'
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
