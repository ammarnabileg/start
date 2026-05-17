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

    public function index(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $currentUser = [
            'name'     => $user['full_name'] ?? $user['username'] ?? 'User',
            'email'    => $user['email'] ?? '',
            'initials' => $this->initials($user['full_name'] ?? $user['username'] ?? 'U'),
            'role'     => 'Owner',
        ];

        Response::view('dashboard/index', [
            'title'       => 'Dashboard - SociAI OS',
            'pageTitle'   => 'Dashboard',
            'activePage'  => 'dashboard',
            'user'        => $user,
            'currentUser' => $currentUser,
            'brandId'     => $brandId,
            'csrf'        => Auth::csrfToken(),
        ]);
    }

    public function content(): void    { $this->renderPage('content',    'Content Hub'); }
    public function strategy(): void   { $this->renderPage('strategy',   'Strategy Intelligence'); }
    public function copywriting(): void{ $this->renderPage('copywriting','Copywriting Studio'); }
    public function analytics(): void  { $this->renderPage('analytics',  'Analytics'); }
    public function campaigns(): void  { $this->renderPage('campaigns',  'Campaigns'); }
    public function community(): void  { $this->renderPage('community',  'Community'); }
    public function trends(): void     { $this->renderPage('trends',     'Trend Hunter'); }
    public function agents(): void     { $this->renderPage('agents',     'AI Agents'); }
    public function team(): void       { $this->renderPage('team',       'Team Management'); }
    public function settings(): void   { $this->renderPage('settings',   'Settings'); }

    private function renderPage(string $page, string $title): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();

        $currentUser = [
            'name'     => $user['full_name'] ?? $user['username'] ?? 'User',
            'email'    => $user['email'] ?? '',
            'initials' => $this->initials($user['full_name'] ?? $user['username'] ?? 'U'),
            'role'     => 'Owner',
        ];

        $file = VIEWS_PATH . '/dashboard/' . $page . '.php';
        if (!file_exists($file)) {
            abort(404, 'Page not found.');
        }

        extract([
            'user'        => $user,
            'currentUser' => $currentUser,
            'brandId'     => $this->getActiveBrandId($user['id']),
            'csrf'        => Auth::csrfToken(),
        ], EXTR_SKIP);

        require $file;
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
