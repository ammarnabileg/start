<?php

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

require_once __DIR__ . '/../agents/ResearchAgent.php';
require_once __DIR__ . '/../agents/CopywritingAgent.php';

class TrendsController
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

        $trends = $this->loadTrends();

        Response::view('trends/index', [
            'title'   => 'Trends & Research – SociAI OS',
            'trends'  => $trends,
            'brandId' => $brandId,
            'csrf'    => Auth::csrfToken(),
        ]);
    }

    public function scanTrends(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $platform = $this->request->post('platform', 'instagram');
        $niche    = trim($this->request->post('niche', ''));

        if (empty($niche)) {
            Response::json(['success' => false, 'error' => 'Niche/industry is required'], 400);
            return;
        }

        try {
            $agent  = new \ResearchAgent($brandId);
            $trends = $agent->scanTrends($platform, $niche);

            Response::json([
                'success'    => true,
                'trends'     => $trends,
                'platform'   => $platform,
                'niche'      => $niche,
                'scanned_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('TrendsController scanTrends error: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getHashtags(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $niche    = trim($this->request->get('niche', ''));
        $platform = $this->request->get('platform', 'instagram');
        $count    = min(50, max(5, (int)$this->request->get('count', 20)));

        if (empty($niche)) {
            Response::json(['success' => false, 'error' => 'Niche is required'], 400);
            return;
        }

        try {
            $agent    = new \ResearchAgent($brandId);
            $hashtags = $agent->analyzeHashtags($niche, $platform, $count);
            Response::json(['success' => true, 'hashtags' => $hashtags, 'count' => count($hashtags)]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function generateFromTrend(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $trendText = trim($this->request->post('trend_text', ''));
        $platform  = $this->request->post('platform', 'instagram');
        $style     = $this->request->post('style', 'casual');
        $language  = $this->request->post('language', 'english');

        if (empty($trendText)) {
            Response::json(['success' => false, 'error' => 'Trend text is required'], 400);
            return;
        }

        try {
            $researchAgent = new \ResearchAgent($brandId);
            $reactive      = $researchAgent->generateReactiveContent($trendText, []);
            $copyAgent     = new \CopywritingAgent($brandId);
            $caption       = $copyAgent->generateCaption($platform, $trendText, $style, $language, []);

            Response::json([
                'success'  => true,
                'reactive' => $reactive,
                'caption'  => $caption,
                'trend'    => $trendText,
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function loadTrends(): array
    {
        // Load from trend_opportunities table
        $stmt = $this->db->prepare(
            'SELECT id, platform, region, trend_name, description, virality_score,
                    volume, hashtags, detected_at
             FROM trend_opportunities
             WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY virality_score DESC, detected_at DESC
             LIMIT 50'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['hashtags'] = json_decode($r['hashtags'] ?? '[]', true);
        }
        unset($r);
        return $rows;
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
