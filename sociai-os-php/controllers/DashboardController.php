<?php
declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Response};

class DashboardController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── index ──────────────────────────────────────────────────────────────────
    public function index(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        if (!$user) { Response::redirect('/auth/login'); return; }

        $brandId = $this->getActiveBrandId($user['id']);
        $brand   = $brandId ? $this->db->fetchOne('SELECT * FROM brands WHERE id = ?', [$brandId]) : null;

        // Platform accounts (up to 6 for dashboard cards)
        $platforms = [];
        $platformCount = 0;
        if ($brandId) {
            $platforms     = $this->db->fetchAll(
                'SELECT id, platform, account_name, follower_count, profile_url, avatar_url, is_active, last_synced_at
                 FROM platform_accounts WHERE brand_id = ? AND is_active = 1 ORDER BY follower_count DESC LIMIT 6',
                [$brandId]
            );
            $platformCount = (int)$this->db->fetchColumn(
                'SELECT COUNT(*) FROM platform_accounts WHERE brand_id = ? AND is_active = 1',
                [$brandId]
            );
        }

        // Content counts by status
        $contentStats = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'published' => 0, 'rejected' => 0, 'total' => 0];
        if ($brandId) {
            $rows = $this->db->fetchAll(
                'SELECT approval_status, COUNT(*) AS cnt FROM content_pieces WHERE brand_id = ? GROUP BY approval_status',
                [$brandId]
            );
            foreach ($rows as $r) {
                $contentStats[$r['approval_status']] = (int)$r['cnt'];
                $contentStats['total'] += (int)$r['cnt'];
            }
        }

        // Community new interactions count
        $communityNew = 0;
        if ($brandId) {
            $communityNew = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM community_interactions WHERE brand_id = ? AND status = 'new'",
                [$brandId]
            );
        }

        // Recent content pieces (last 5)
        $recentContent = [];
        if ($brandId) {
            $recentContent = $this->db->fetchAll(
                "SELECT id, title, content_type, approval_status, viral_score, created_at
                 FROM content_pieces WHERE brand_id = ?
                 ORDER BY created_at DESC LIMIT 5",
                [$brandId]
            );
        }

        // Campaign counts by status
        $campaignStats = ['active' => 0, 'draft' => 0, 'paused' => 0, 'completed' => 0, 'total' => 0];
        if ($brandId) {
            $rows = $this->db->fetchAll(
                'SELECT status, COUNT(*) AS cnt FROM campaigns WHERE brand_id = ? GROUP BY status',
                [$brandId]
            );
            foreach ($rows as $r) {
                $campaignStats[$r['status']] = (int)$r['cnt'];
                $campaignStats['total'] += (int)$r['cnt'];
            }
        }

        // Agent tasks (last 5)
        $agentTasks = [];
        if ($brandId) {
            $agentTasks = $this->db->fetchAll(
                "SELECT id, agent_type, status, result_data, error_message, created_at, completed_at
                 FROM agent_tasks WHERE brand_id = ?
                 ORDER BY created_at DESC LIMIT 5",
                [$brandId]
            );
        }

        // Trends (global, last 3 for dashboard widget)
        $trends = $this->db->fetchAll(
            'SELECT hashtag, growth_rate, relevance_score, post_count, platform
             FROM trend_opportunities ORDER BY relevance_score DESC LIMIT 3'
        );

        $currentUser = $this->buildCurrentUser($user);

        Response::view('dashboard/index', [
            'title'         => 'Dashboard - SociAI OS',
            'pageTitle'     => 'Dashboard',
            'activePage'    => 'dashboard',
            'user'          => $user,
            'currentUser'   => $currentUser,
            'brand'         => $brand,
            'brandId'       => $brandId,
            'platforms'     => $platforms,
            'platformCount' => $platformCount,
            'contentStats'  => $contentStats,
            'communityNew'  => $communityNew,
            'recentContent' => $recentContent,
            'campaignStats' => $campaignStats,
            'agentTasks'    => $agentTasks,
            'trends'        => $trends,
            'csrf'          => Auth::csrfToken(),
        ]);
    }

    // ── content ────────────────────────────────────────────────────────────────
    public function content(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        if (!$user) { Response::redirect('/auth/login'); return; }

        $brandId = $this->getActiveBrandId($user['id']);
        $brand   = $brandId ? $this->db->fetchOne('SELECT * FROM brands WHERE id = ?', [$brandId]) : null;

        $filterStatus = $_GET['status'] ?? 'all';
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $perPage      = 20;
        $validStatuses = ['draft', 'pending', 'approved', 'published', 'rejected'];

        $contentStats = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'published' => 0, 'rejected' => 0, 'total' => 0];
        $contentPieces = [];
        $totalPages   = 1;
        $total        = 0;

        if ($brandId) {
            $rows = $this->db->fetchAll(
                'SELECT approval_status, COUNT(*) AS cnt FROM content_pieces WHERE brand_id = ? GROUP BY approval_status',
                [$brandId]
            );
            foreach ($rows as $r) {
                $contentStats[$r['approval_status']] = (int)$r['cnt'];
                $contentStats['total'] += (int)$r['cnt'];
            }

            $where  = ['brand_id = ?'];
            $params = [$brandId];
            if ($filterStatus !== 'all' && in_array($filterStatus, $validStatuses, true)) {
                $where[]  = 'approval_status = ?';
                $params[] = $filterStatus;
            }
            $wc     = implode(' AND ', $where);
            $offset = ($page - 1) * $perPage;
            $total  = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM content_pieces WHERE {$wc}", $params);
            $contentPieces = $this->db->fetchAll(
                "SELECT id, title, content_type, body_text, hashtags, approval_status,
                        viral_score, ai_generated, created_at
                 FROM content_pieces WHERE {$wc}
                 ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
                $params
            );
            $totalPages = max(1, (int)ceil($total / $perPage));

            foreach ($contentPieces as &$p) {
                $p['hashtags'] = json_decode($p['hashtags'] ?? '[]', true) ?: [];
            }
            unset($p);
        }

        // Connected platforms for publish modal
        $connectedPlatforms = $brandId ? $this->db->fetchAll(
            'SELECT id, platform, account_name FROM platform_accounts WHERE brand_id = ? AND is_active = 1 ORDER BY platform',
            [$brandId]
        ) : [];

        $currentUser = $this->buildCurrentUser($user);

        Response::view('dashboard/content', [
            'title'              => 'Content Hub - SociAI OS',
            'pageTitle'          => 'Content Hub',
            'activePage'         => 'content',
            'user'               => $user,
            'currentUser'        => $currentUser,
            'brand'              => $brand,
            'brandId'            => $brandId,
            'contentStats'       => $contentStats,
            'contentPieces'      => $contentPieces,
            'connectedPlatforms' => $connectedPlatforms,
            'filterStatus'       => $filterStatus,
            'page'               => $page,
            'perPage'            => $perPage,
            'total'              => $total,
            'totalPages'         => $totalPages,
            'csrf'               => Auth::csrfToken(),
        ]);
    }

    // ── community ──────────────────────────────────────────────────────────────
    public function community(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        if (!$user) { Response::redirect('/auth/login'); return; }

        $brandId = $this->getActiveBrandId($user['id']);
        $brand   = $brandId ? $this->db->fetchOne('SELECT * FROM brands WHERE id = ?', [$brandId]) : null;

        $statusFilter   = $_GET['status']   ?? 'new';
        $platformFilter = $_GET['platform'] ?? 'all';
        $page           = max(1, (int)($_GET['page'] ?? 1));
        $perPage        = 20;

        $stats = ['total' => 0, 'pending' => 0, 'replied' => 0, 'ignored' => 0, 'escalated' => 0];
        $interactions = [];
        $totalPages   = 1;
        $total        = 0;
        $byPlatform   = [];
        $bySentiment  = [];

        if ($brandId) {
            $statsRow = $this->db->fetchOne(
                "SELECT COUNT(*) AS total,
                        SUM(status = 'new') AS pending,
                        SUM(status = 'replied') AS replied,
                        SUM(status = 'ignored') AS ignored,
                        SUM(status = 'escalated') AS escalated
                 FROM community_interactions WHERE brand_id = ?",
                [$brandId]
            );
            if ($statsRow) {
                $stats = [
                    'total'     => (int)$statsRow['total'],
                    'pending'   => (int)$statsRow['pending'],
                    'replied'   => (int)$statsRow['replied'],
                    'ignored'   => (int)$statsRow['ignored'],
                    'escalated' => (int)$statsRow['escalated'],
                ];
            }

            $validStatuses  = ['new', 'in_review', 'replied', 'ignored', 'escalated'];
            $validPlatforms = ['linkedin', 'instagram', 'facebook', 'tiktok', 'twitter', 'youtube', 'threads', 'snapchat'];

            $where  = ['brand_id = ?'];
            $params = [$brandId];
            if ($statusFilter !== 'all' && in_array($statusFilter, $validStatuses, true)) {
                $where[]  = 'status = ?';
                $params[] = $statusFilter;
            }
            if ($platformFilter !== 'all' && in_array($platformFilter, $validPlatforms, true)) {
                $where[]  = 'platform = ?';
                $params[] = $platformFilter;
            }

            $wc     = implode(' AND ', $where);
            $offset = ($page - 1) * $perPage;
            $total  = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM community_interactions WHERE {$wc}", $params);
            $interactions = $this->db->fetchAll(
                "SELECT id, platform, author_name, author_handle, author_avatar,
                        message_text, sentiment, ai_suggested_reply, actual_reply, status, created_at
                 FROM community_interactions WHERE {$wc}
                 ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
                $params
            );
            $totalPages = max(1, (int)ceil($total / $perPage));

            $byPlatform = $this->db->fetchAll(
                "SELECT platform, COUNT(*) AS cnt, SUM(status = 'new') AS pending
                 FROM community_interactions WHERE brand_id = ?
                 GROUP BY platform ORDER BY cnt DESC",
                [$brandId]
            );
            $bySentiment = $this->db->fetchAll(
                "SELECT sentiment, COUNT(*) AS cnt
                 FROM community_interactions WHERE brand_id = ?
                 GROUP BY sentiment ORDER BY cnt DESC",
                [$brandId]
            );
        }

        $currentUser = $this->buildCurrentUser($user);

        Response::view('dashboard/community', [
            'title'          => 'Community - SociAI OS',
            'pageTitle'      => 'Community Management',
            'activePage'     => 'community',
            'user'           => $user,
            'currentUser'    => $currentUser,
            'brand'          => $brand,
            'brandId'        => $brandId,
            'stats'          => $stats,
            'interactions'   => $interactions,
            'byPlatform'     => $byPlatform,
            'bySentiment'    => $bySentiment,
            'statusFilter'   => $statusFilter,
            'platformFilter' => $platformFilter,
            'page'           => $page,
            'total'          => $total,
            'totalPages'     => $totalPages,
            'csrf'           => Auth::csrfToken(),
        ]);
    }

    // ── analytics ──────────────────────────────────────────────────────────────
    public function analytics(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        if (!$user) { Response::redirect('/auth/login'); return; }

        $brandId = $this->getActiveBrandId($user['id']);
        $brand   = $brandId ? $this->db->fetchOne('SELECT * FROM brands WHERE id = ?', [$brandId]) : null;

        $platformAnalytics = [];
        $topPosts          = [];
        $totals            = ['impressions' => 0, 'engagements' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'reach' => 0];

        if ($brandId) {
            $platformAnalytics = $this->db->fetchAll(
                'SELECT platform,
                        SUM(impressions) AS impressions,
                        SUM(engagements) AS engagements,
                        SUM(likes) AS likes,
                        SUM(comments) AS comments,
                        SUM(shares) AS shares,
                        SUM(reach) AS reach,
                        COUNT(DISTINCT content_piece_id) AS post_count
                 FROM post_analytics WHERE brand_id = ?
                 GROUP BY platform ORDER BY engagements DESC',
                [$brandId]
            );

            foreach ($platformAnalytics as $pa) {
                $totals['impressions'] += (int)$pa['impressions'];
                $totals['engagements'] += (int)$pa['engagements'];
                $totals['likes']       += (int)$pa['likes'];
                $totals['comments']    += (int)$pa['comments'];
                $totals['shares']      += (int)$pa['shares'];
                $totals['reach']       += (int)$pa['reach'];
            }

            $topPosts = $this->db->fetchAll(
                "SELECT pa.content_piece_id, cp.title, pa.platform,
                        SUM(pa.engagements) AS total_engagements,
                        SUM(pa.reach) AS total_reach,
                        cp.viral_score
                 FROM post_analytics pa
                 LEFT JOIN content_pieces cp ON cp.id = pa.content_piece_id
                 WHERE pa.brand_id = ?
                 GROUP BY pa.content_piece_id, cp.title, pa.platform, cp.viral_score
                 ORDER BY total_engagements DESC LIMIT 5",
                [$brandId]
            );
        }

        $currentUser = $this->buildCurrentUser($user);

        Response::view('dashboard/analytics', [
            'title'             => 'Analytics - SociAI OS',
            'pageTitle'         => 'Analytics',
            'activePage'        => 'analytics',
            'user'              => $user,
            'currentUser'       => $currentUser,
            'brand'             => $brand,
            'brandId'           => $brandId,
            'platformAnalytics' => $platformAnalytics,
            'topPosts'          => $topPosts,
            'totals'            => $totals,
            'csrf'              => Auth::csrfToken(),
        ]);
    }

    // ── campaigns ──────────────────────────────────────────────────────────────
    public function campaigns(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        if (!$user) { Response::redirect('/auth/login'); return; }

        $brandId = $this->getActiveBrandId($user['id']);
        $brand   = $brandId ? $this->db->fetchOne('SELECT * FROM brands WHERE id = ?', [$brandId]) : null;

        $campaigns    = [];
        $campaignStats = ['active' => 0, 'draft' => 0, 'paused' => 0, 'completed' => 0, 'total' => 0,
                          'total_budget' => 0];

        if ($brandId) {
            $campaigns = $this->db->fetchAll(
                "SELECT id, name, status, budget, start_date, end_date, goals, created_at
                 FROM campaigns WHERE brand_id = ?
                 ORDER BY created_at DESC",
                [$brandId]
            );

            foreach ($campaigns as &$c) {
                $c['goals'] = json_decode($c['goals'] ?? '[]', true) ?: [];
                $campaignStats[$c['status']] = ($campaignStats[$c['status']] ?? 0) + 1;
                $campaignStats['total']++;
                $campaignStats['total_budget'] += (float)($c['budget'] ?? 0);
            }
            unset($c);
        }

        $currentUser = $this->buildCurrentUser($user);

        Response::view('dashboard/campaigns', [
            'title'         => 'Campaigns - SociAI OS',
            'pageTitle'     => 'Campaigns',
            'activePage'    => 'campaigns',
            'user'          => $user,
            'currentUser'   => $currentUser,
            'brand'         => $brand,
            'brandId'       => $brandId,
            'campaigns'     => $campaigns,
            'campaignStats' => $campaignStats,
            'csrf'          => Auth::csrfToken(),
        ]);
    }

    // ── strategy ───────────────────────────────────────────────────────────────
    public function strategy(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        if (!$user) { Response::redirect('/auth/login'); return; }

        $brandId   = $this->getActiveBrandId($user['id']);
        $brand     = $brandId ? $this->db->fetchOne('SELECT * FROM brands WHERE id = ?', [$brandId]) : null;
        $strategies = [];

        if ($brandId) {
            $strategies = $this->db->fetchAll(
                'SELECT id, title, content, created_at FROM marketing_strategies
                 WHERE brand_id = ? ORDER BY created_at DESC',
                [$brandId]
            );
        }

        $currentUser = $this->buildCurrentUser($user);

        Response::view('dashboard/strategy', [
            'title'      => 'Strategy - SociAI OS',
            'pageTitle'  => 'Strategy Intelligence',
            'activePage' => 'strategy',
            'user'       => $user,
            'currentUser'=> $currentUser,
            'brand'      => $brand,
            'brandId'    => $brandId,
            'strategies' => $strategies,
            'csrf'       => Auth::csrfToken(),
        ]);
    }

    // ── agents ─────────────────────────────────────────────────────────────────
    public function agents(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        if (!$user) { Response::redirect('/auth/login'); return; }

        $brandId = $this->getActiveBrandId($user['id']);
        $brand   = $brandId ? $this->db->fetchOne('SELECT * FROM brands WHERE id = ?', [$brandId]) : null;

        $agentTasks   = [];
        $agentSummary = [];

        if ($brandId) {
            $agentTasks = $this->db->fetchAll(
                "SELECT id, agent_type, status, result_data, error_message, created_at, completed_at
                 FROM agent_tasks WHERE brand_id = ?
                 ORDER BY created_at DESC LIMIT 20",
                [$brandId]
            );

            // Group by agent_type for summary cards
            $summaryRows = $this->db->fetchAll(
                "SELECT agent_type,
                        COUNT(*) AS total_tasks,
                        SUM(status = 'completed') AS completed,
                        SUM(status = 'failed') AS failed,
                        SUM(status = 'running') AS running,
                        MAX(created_at) AS last_run
                 FROM agent_tasks WHERE brand_id = ?
                 GROUP BY agent_type",
                [$brandId]
            );
            foreach ($summaryRows as $r) {
                $agentSummary[$r['agent_type']] = $r;
            }
        }

        $currentUser = $this->buildCurrentUser($user);

        Response::view('dashboard/agents', [
            'title'        => 'AI Agents - SociAI OS',
            'pageTitle'    => 'AI Agents',
            'activePage'   => 'agents',
            'user'         => $user,
            'currentUser'  => $currentUser,
            'brand'        => $brand,
            'brandId'      => $brandId,
            'agentTasks'   => $agentTasks,
            'agentSummary' => $agentSummary,
            'csrf'         => Auth::csrfToken(),
        ]);
    }

    // ── team ───────────────────────────────────────────────────────────────────
    public function team(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        if (!$user) { Response::redirect('/auth/login'); return; }

        $brandId = $this->getActiveBrandId($user['id']);
        $brand   = $brandId ? $this->db->fetchOne('SELECT * FROM brands WHERE id = ?', [$brandId]) : null;

        $members = [];
        if ($brandId) {
            $members = $this->db->fetchAll(
                "SELECT tm.id AS member_id, tm.role, tm.created_at AS joined_at,
                        u.id AS user_id, u.full_name, u.username, u.email, u.is_active
                 FROM team_members tm
                 INNER JOIN users u ON u.id = tm.user_id
                 WHERE tm.brand_id = ?
                 ORDER BY tm.created_at ASC",
                [$brandId]
            );
        }

        $currentUser = $this->buildCurrentUser($user);

        Response::view('dashboard/team', [
            'title'      => 'Team - SociAI OS',
            'pageTitle'  => 'Team Management',
            'activePage' => 'team',
            'user'       => $user,
            'currentUser'=> $currentUser,
            'brand'      => $brand,
            'brandId'    => $brandId,
            'members'    => $members,
            'csrf'       => Auth::csrfToken(),
        ]);
    }

    // ── settings ───────────────────────────────────────────────────────────────
    public function settings(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        if (!$user) { Response::redirect('/auth/login'); return; }

        $brandId = $this->getActiveBrandId($user['id']);
        $brand   = $brandId ? $this->db->fetchOne('SELECT * FROM brands WHERE id = ?', [$brandId]) : null;

        $platformAccounts = [];
        if ($brandId) {
            $platformAccounts = $this->db->fetchAll(
                'SELECT id, platform, account_name, follower_count, avatar_url,
                        is_active, last_synced_at
                 FROM platform_accounts WHERE brand_id = ? ORDER BY platform ASC',
                [$brandId]
            );
        }

        // Build lookup by platform
        $connectedMap = [];
        foreach ($platformAccounts as $acc) {
            $connectedMap[$acc['platform']] = $acc;
        }

        $currentUser = $this->buildCurrentUser($user);

        Response::view('dashboard/settings', [
            'title'           => 'Settings - SociAI OS',
            'pageTitle'       => 'Settings',
            'activePage'      => 'settings',
            'user'            => $user,
            'currentUser'     => $currentUser,
            'brand'           => $brand,
            'brandId'         => $brandId,
            'platformAccounts'=> $platformAccounts,
            'connectedMap'    => $connectedMap,
            'csrf'            => Auth::csrfToken(),
        ]);
    }

    // ── trends ─────────────────────────────────────────────────────────────────
    public function trends(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        if (!$user) { Response::redirect('/auth/login'); return; }

        $brandId = $this->getActiveBrandId($user['id']);
        $brand   = $brandId ? $this->db->fetchOne('SELECT * FROM brands WHERE id = ?', [$brandId]) : null;

        $platformFilter = $_GET['platform'] ?? 'all';
        $validPlatforms = ['linkedin', 'instagram', 'facebook', 'tiktok', 'twitter', 'youtube', 'threads', 'snapchat', 'pinterest'];

        $where  = [];
        $params = [];
        if ($platformFilter !== 'all' && in_array($platformFilter, $validPlatforms, true)) {
            $where[]  = 'platform = ?';
            $params[] = $platformFilter;
        }

        $sql = 'SELECT id, platform, hashtag, growth_rate, relevance_score, post_count, region, created_at
                FROM trend_opportunities';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY relevance_score DESC, growth_rate DESC LIMIT 30';

        $trends = $this->db->fetchAll($sql, $params);

        $currentUser = $this->buildCurrentUser($user);

        Response::view('dashboard/trends', [
            'title'          => 'Trends - SociAI OS',
            'pageTitle'      => 'Trend Hunter',
            'activePage'     => 'trends',
            'user'           => $user,
            'currentUser'    => $currentUser,
            'brand'          => $brand,
            'brandId'        => $brandId,
            'trends'         => $trends,
            'platformFilter' => $platformFilter,
            'csrf'           => Auth::csrfToken(),
        ]);
    }

    // ── copywriting ────────────────────────────────────────────────────────────
    public function copywriting(): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        if (!$user) { Response::redirect('/auth/login'); return; }

        $brandId = $this->getActiveBrandId($user['id']);
        $brand   = $brandId ? $this->db->fetchOne('SELECT id, name, industry FROM brands WHERE id = ?', [$brandId]) : null;

        $currentUser = $this->buildCurrentUser($user);

        Response::view('dashboard/copywriting', [
            'title'      => 'Copywriting Studio - SociAI OS',
            'pageTitle'  => 'Copywriting Studio',
            'activePage' => 'copywriting',
            'user'       => $user,
            'currentUser'=> $currentUser,
            'brand'      => $brand,
            'brandId'    => $brandId,
            'csrf'       => Auth::csrfToken(),
        ]);
    }

    // ── helpers ────────────────────────────────────────────────────────────────
    private function buildCurrentUser(array $user): array
    {
        return [
            'name'     => $user['full_name'] ?? $user['username'] ?? 'User',
            'email'    => $user['email'] ?? '',
            'initials' => $this->initials($user['full_name'] ?? $user['username'] ?? 'U'),
            'role'     => 'Owner',
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }

    private function getActiveBrandId(string $userId): string
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (string)$_SESSION['active_brand_id'];
        }
        $row = $this->db->fetchOne(
            'SELECT b.id FROM brands b
             INNER JOIN team_members tm ON tm.brand_id = b.id
             WHERE tm.user_id = ?
             ORDER BY tm.created_at ASC LIMIT 1',
            [$userId]
        );
        if ($row) {
            $_SESSION['active_brand_id'] = $row['id'];
            return (string)$row['id'];
        }
        return '';
    }
}
