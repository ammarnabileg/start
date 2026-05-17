<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth, Request, Response, Database};
use SociAI\Models\Brand;

class CampaignController
{
    private Request $req;
    private Brand $brandModel;
    private Database $db;

    public function __construct()
    {
        $this->req        = new Request();
        $this->brandModel = new Brand();
        $this->db         = Database::getInstance();
    }

    private function generateUuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function getBrand(string $slug, string $uid, string ...$r): array
    {
        $b = $this->brandModel->findBySlug($slug);
        if (!$b) abort(404);
        if (!$this->brandModel->userCanAccess($b['id'], $uid, ...$r)) abort(403);
        return $b;
    }

    public function index(array $p): void
    {
        Auth::requireAuth();
        $u     = Auth::getCurrentUser();
        $b     = $this->getBrand($p['slug'], $u['id']);
        $camps = $this->db->fetchAll(
            "SELECT * FROM campaigns WHERE brand_id=? ORDER BY created_at DESC",
            [$b['id']]
        );
        Response::view('campaigns.index', [
            'brand'      => $b,
            'campaigns'  => $camps,
            'user'       => $u,
            'pageTitle'  => 'Campaigns',
            'layout'     => 'app',
            'activeBrand'=> $b,
            'csrf'       => Auth::csrfToken(),
        ]);
    }

    public function create(array $p): void
    {
        Auth::requireAuth();
        $u = Auth::getCurrentUser();
        $b = $this->getBrand($p['slug'], $u['id'], 'manager', 'admin', 'owner');
        Response::view('campaigns.create', [
            'brand'      => $b,
            'user'       => $u,
            'pageTitle'  => 'New Campaign',
            'layout'     => 'app',
            'activeBrand'=> $b,
            'csrf'       => Auth::csrfToken(),
        ]);
    }

    public function store(array $p): void
    {
        Auth::requireAuth();
        $u  = Auth::getCurrentUser();
        $b  = $this->getBrand($p['slug'], $u['id'], 'manager', 'admin', 'owner');
        $id = $this->generateUuid();
        $startDate = $this->req->post('start_date') ?: null;
        $endDate   = $this->req->post('end_date') ?: null;
        $this->db->prepare(
            'INSERT INTO campaigns
             (id, brand_id, name, description, goal, status, created_by, start_date, end_date, created_at)
             VALUES (?,?,?,?,?,"draft",?,?,?,NOW())'
        )->execute([
            $id,
            $b['id'],
            $this->req->post('name'),
            $this->req->post('description', ''),
            $this->req->post('goal', ''),
            $u['id'],
            $startDate,
            $endDate,
        ]);
        Response::flash('success', 'Campaign created!');
        Response::redirect('/brands/' . $p['slug'] . '/campaigns/' . $id);
    }

    public function show(array $p): void
    {
        Auth::requireAuth();
        $u = Auth::getCurrentUser();
        $b = $this->getBrand($p['slug'], $u['id']);
        $c = $this->db->fetchOne(
            "SELECT * FROM campaigns WHERE id=? AND brand_id=?",
            [$p['id'], $b['id']]
        );
        if (!$c) abort(404);
        Response::view('campaigns.show', [
            'brand'      => $b,
            'campaign'   => $c,
            'user'       => $u,
            'pageTitle'  => $c['name'],
            'layout'     => 'app',
            'activeBrand'=> $b,
            'csrf'       => Auth::csrfToken(),
        ]);
    }

    public function update(array $p): void
    {
        Auth::requireAuth();
        $u = Auth::getCurrentUser();
        $b = $this->getBrand($p['slug'], $u['id'], 'manager', 'admin', 'owner');
        $this->db->prepare(
            'UPDATE campaigns SET name=?, status=?, updated_at=NOW() WHERE id=? AND brand_id=?'
        )->execute([
            $this->req->post('name'),
            $this->req->post('status', 'draft'),
            $p['id'],
            $b['id'],
        ]);
        Response::flash('success', 'Campaign updated.');
        Response::redirect('/brands/' . $p['slug'] . '/campaigns/' . $p['id']);
    }
}
