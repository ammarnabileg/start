<?php

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

require_once __DIR__ . '/../agents/ResearchAgent.php';
require_once __DIR__ . '/../agents/CopywritingAgent.php';

class TrendsController
{
    private Database $db;
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

        $savedTrends   = $this->loadSavedTrends($brandId);
        $trendingAlerts = $this->loadTrendingAlerts($brandId);

        $this->response->view('trends/index', [
            'title'          => 'Trends & Research – SociAI OS',
            'savedTrends'    => $savedTrends,
            'trendingAlerts' => $trendingAlerts,
            'brandId'        => $brandId,
        ]);
    }

    public function scanTrends(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $platform = $this->request->post('platform', 'instagram');
        $niche    = trim($this->request->post('niche', ''));

        if (empty($niche)) {
            $this->response->json(['success' => false, 'error' => 'Niche/industry is required'], 400);
            return;
        }

        try {
            $agent  = new ResearchAgent($brandId);
            $trends = $agent->scanTrends($platform, $niche);

            // Save to DB for caching
            foreach ($trends as $trend) {
                $this->db->prepare(
                    'INSERT INTO trend_data (brand_id, platform, niche, trend_text, trend_type, score, raw_data, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE score = VALUES(score), raw_data = VALUES(raw_data)'
                )->execute([
                    $brandId,
                    $platform,
                    $niche,
                    $trend['text'] ?? $trend['trend'] ?? '',
                    $trend['type'] ?? 'topic',
                    $trend['score'] ?? 0,
                    json_encode($trend),
                ]);
            }

            $this->response->json([
                'success'  => true,
                'trends'   => $trends,
                'platform' => $platform,
                'niche'    => $niche,
                'scanned_at' => date('Y-m-d H:i:s'),
            ]);

        } catch (\Throwable $e) {
            error_log('TrendsController scanTrends error: ' . $e->getMessage());
            $this->response->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getHashtags(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $niche    = trim($this->request->get('niche', ''));
        $platform = $this->request->get('platform', 'instagram');
        $count    = min(50, max(5, (int) $this->request->get('count', 20)));

        if (empty($niche)) {
            $this->response->json(['success' => false, 'error' => 'Niche is required'], 400);
            return;
        }

        try {
            $agent    = new ResearchAgent($brandId);
            $hashtags = $agent->analyzeHashtags($niche, $platform, $count);

            $this->response->json([
                'success'  => true,
                'hashtags' => $hashtags,
                'count'    => count($hashtags),
            ]);

        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function generateFromTrend(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $trendText = trim($this->request->post('trend_text', ''));
        $platform  = $this->request->post('platform', 'instagram');
        $style     = $this->request->post('style', 'casual');
        $language  = $this->request->post('language', 'english');

        if (empty($trendText)) {
            $this->response->json(['success' => false, 'error' => 'Trend text is required'], 400);
            return;
        }

        try {
            $researchAgent = new ResearchAgent($brandId);
            $brandContext  = $this->getBrandContext($brandId);
            $reactive      = $researchAgent->generateReactiveContent($trendText, $brandContext);

            $copyAgent = new CopywritingAgent($brandId);
            $caption   = $copyAgent->generateCaption($platform, $trendText, $style, $language, $brandContext);

            $this->response->json([
                'success'  => true,
                'reactive' => $reactive,
                'caption'  => $caption,
                'trend'    => $trendText,
            ]);

        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getViralSounds(): void
    {
        $this->auth->requireAuth();
        $user    = $this->auth->getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $platform = $this->request->get('platform', 'tiktok');

        try {
            $agent  = new ResearchAgent($brandId);
            $sounds = $agent->findViralSounds($platform);

            $this->response->json([
                'success'  => true,
                'sounds'   => $sounds,
                'platform' => $platform,
            ]);

        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function loadSavedTrends(int $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, platform, niche, trend_text, trend_type, score, created_at
             FROM trend_data
             WHERE brand_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY score DESC, created_at DESC
             LIMIT 50'
        );
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function loadTrendingAlerts(int $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, alert_type, message, platform, severity, is_read, created_at
             FROM trend_alerts
             WHERE brand_id = ? AND is_read = 0
             ORDER BY severity DESC, created_at DESC
             LIMIT 10'
        );
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function getBrandContext(int $brandId): array
    {
        $stmt = $this->db->prepare('SELECT field_name, field_value FROM brand_strategy WHERE brand_id = ?');
        $stmt->execute([$brandId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $ctx  = [];
        foreach ($rows as $r) {
            $ctx[$r['field_name']] = $r['field_value'];
        }
        return $ctx;
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
