<?php
declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

require_once __DIR__ . '/../agents/StrategyAgent.php';

class StrategyController
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
        $strategy= $this->loadStrategy($brandId);
        $pillars = $this->loadContentPillars($brandId);
        $audience= $this->loadTargetAudience($brandId);
        $calendar= $this->loadContentCalendar($brandId);
        $this->response->view('strategy/index', ['title'=>'Strategy - SociAI OS','strategy'=>$strategy,'pillars'=>$pillars,'audience'=>$audience,'calendar'=>$calendar,'brandId'=>$brandId]);
    }

    public function upload(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        if (empty($_FILES['document'])) { $this->response->json(['success'=>false,'error'=>'No file uploaded'],400); return; }
        $file = $_FILES['document'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['size'] > 10*1024*1024) { $this->response->json(['success'=>false,'error'=>'File too large'],422); return; }
        if (!in_array($ext, ['pdf','txt','docx','doc'], true)) { $this->response->json(['success'=>false,'error'=>'Unsupported file type'],422); return; }
        if ($file['error'] !== UPLOAD_ERR_OK) { $this->response->json(['success'=>false,'error'=>'Upload error'],500); return; }
        $dir = __DIR__ . '/../storage/uploads/strategy/' . $brandId . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = uniqid('strategy_', true) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir.$fname)) { $this->response->json(['success'=>false,'error'=>'Save failed'],500); return; }
        $stmt = $this->db->prepare('INSERT INTO strategy_documents (brand_id,filename,original_name,file_path,file_type,file_size,uploaded_by,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$brandId,$fname,$file['name'],$dir.$fname,$ext,$file['size'],$user['id']]);
        $this->response->json(['success'=>true,'doc_id'=>(int)$this->db->lastInsertId(),'path'=>$dir.$fname]);
    }

    public function analyze(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $docId   = (int)$this->request->post('doc_id', 0);
        if ($docId <= 0) { $this->response->json(['success'=>false,'error'=>'Invalid doc_id'],400); return; }
        $stmt = $this->db->prepare('SELECT id,file_path,file_type FROM strategy_documents WHERE id=? AND brand_id=? LIMIT 1');
        $stmt->execute([$docId,$brandId]);
        $doc = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$doc) { $this->response->json(['success'=>false,'error'=>'Document not found'],404); return; }
        try {
            $agent    = new StrategyAgent($brandId);
            $analysis = $agent->analyzeDocument($doc['file_path']);
            $this->db->prepare('UPDATE strategy_documents SET analysis_result=?,analyzed_at=NOW() WHERE id=?')->execute([json_encode($analysis),$docId]);
            if (!empty($analysis['brand_tone'])) $this->upsertStrategyField($brandId,'brand_tone',$analysis['brand_tone']);
            if (!empty($analysis['content_pillars'])) $this->upsertStrategyField($brandId,'content_pillars',json_encode($analysis['content_pillars']));
            if (!empty($analysis['target_audience'])) $this->upsertStrategyField($brandId,'target_audience',json_encode($analysis['target_audience']));
            $this->response->json(['success'=>true,'analysis'=>$analysis]);
        } catch (\Throwable $e) {
            error_log('Strategy analyze: '.$e->getMessage());
            $this->response->json(['success'=>false,'error'=>$e->getMessage()],500);
        }
    }

    public function save(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $fields  = ['brand_tone','brand_voice','content_pillars','target_audience','posting_frequency','primary_platforms','mission_statement','value_proposition'];
        foreach ($fields as $field) {
            $val = trim((string)$this->request->post($field,''));
            if (!empty($val)) $this->upsertStrategyField($brandId, $field, $val);
        }
        $this->response->json(['success'=>true,'message'=>'Strategy saved.']);
    }

    public function getBrandTone(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $tone    = $this->getStrategyField($brandId,'brand_tone');
        $this->response->json(['brand_tone'=>$tone??'']);
    }

    public function getContentPillars(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $raw     = $this->getStrategyField($brandId,'content_pillars');
        $this->response->json(['content_pillars'=> $raw ? (json_decode($raw,true)??[]) : []]);
    }

    private function loadStrategy(int $brandId): array
    {
        $stmt = $this->db->prepare('SELECT field_name,field_value FROM brand_strategy WHERE brand_id=?');
        $stmt->execute([$brandId]);
        $s = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) $s[$r['field_name']] = $r['field_value'];
        return $s;
    }

    private function loadContentPillars(int $brandId): array
    {
        $raw = $this->getStrategyField($brandId,'content_pillars');
        return $raw ? (json_decode($raw,true)?:[]) : [];
    }

    private function loadTargetAudience(int $brandId): array
    {
        $raw = $this->getStrategyField($brandId,'target_audience');
        return $raw ? (json_decode($raw,true)?:[]) : [];
    }

    private function loadContentCalendar(int $brandId): array
    {
        $stmt = $this->db->prepare('SELECT id,title,platform,content_type,scheduled_for,status FROM content_calendar WHERE brand_id=? AND scheduled_for>=CURDATE() ORDER BY scheduled_for ASC LIMIT 30');
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function upsertStrategyField(int $brandId, string $field, string $value): void
    {
        $this->db->prepare('INSERT INTO brand_strategy (brand_id,field_name,field_value,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE field_value=VALUES(field_value),updated_at=NOW()')->execute([$brandId,$field,$value]);
    }

    private function getStrategyField(int $brandId, string $field): ?string
    {
        $stmt = $this->db->prepare('SELECT field_value FROM brand_strategy WHERE brand_id=? AND field_name=? LIMIT 1');
        $stmt->execute([$brandId,$field]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $row['field_value'] : null;
    }

    private function getActiveBrandId(int $userId): int
    {
        if (!empty($_SESSION['active_brand_id'])) return (int)$_SESSION['active_brand_id'];
        $stmt = $this->db->prepare('SELECT b.id FROM brands b INNER JOIN brand_users bu ON bu.brand_id=b.id WHERE bu.user_id=? ORDER BY bu.created_at ASC LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : 0;
    }
}
