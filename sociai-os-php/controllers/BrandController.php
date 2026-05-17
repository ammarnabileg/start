<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth, Database, Request, Response};
use SociAI\Models\Brand;

class BrandController
{
    private Request $request;
    private Brand $brandModel;

    public function __construct()
    {
        $this->request    = new Request();
        $this->brandModel = new Brand();
    }

    public function index(array $p): void
    {
        Auth::requireAuth();
        $user   = Auth::getCurrentUser();
        $brands = \SociAI\Models\User::getBrands($user['id']);
        Response::view('brands.index', [
            'brands'    => $brands,
            'user'      => $user,
            'pageTitle' => 'Brands',
            'layout'    => 'app',
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function create(array $p): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        Response::view('brands.create', [
            'user'      => $user,
            'pageTitle' => 'Create Brand',
            'layout'    => 'app',
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function store(array $p): void
    {
        Auth::requireAuth();
        $user = Auth::getCurrentUser();
        $name = trim($this->request->post('name', ''));
        if (strlen($name) < 2) {
            Response::flash('error', 'Name is required (min 2 chars).');
            Response::redirect('/brands/create');
            return;
        }
        $brandId = Brand::create([
            'owner_id'    => $user['id'],
            'name'        => $name,
            'description' => $this->request->post('description', ''),
            'industry'    => $this->request->post('industry', ''),
        ]);
        // Add creator as owner in team_members
        $db   = Database::getInstance();
        $tmId = $this->generateUuid();
        $db->prepare(
            'INSERT INTO team_members (id, brand_id, user_id, role, created_at) VALUES (?,?,?,?,NOW())'
        )->execute([$tmId, $brandId, $user['id'], 'owner']);

        $stmt = $db->prepare('SELECT slug FROM brands WHERE id=? LIMIT 1');
        $stmt->execute([$brandId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        Response::flash('success', 'Brand created!');
        Response::redirect('/brands/' . ($row['slug'] ?? $brandId));
    }

    public function show(array $p): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->brandModel->findBySlug($p['slug']);
        if (!$brand) abort(404);
        if (!$this->brandModel->userCanAccess($brand['id'], $user['id'])) abort(403);
        $stats     = Brand::getStats($brand['id']);
        $platforms = Brand::getPlatformAccounts($brand['id']);
        Response::view('brands.show', [
            'brand'      => $brand,
            'stats'      => $stats,
            'platforms'  => $platforms,
            'user'       => $user,
            'pageTitle'  => $brand['name'],
            'layout'     => 'app',
            'activeBrand'=> $brand,
            'csrf'       => Auth::csrfToken(),
        ]);
    }

    public function edit(array $p): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->brandModel->findBySlug($p['slug']);
        if (!$brand) abort(404);
        if (!$this->brandModel->userCanAccess($brand['id'], $user['id'], 'admin', 'owner')) abort(403);
        Response::view('brands.edit', [
            'brand'      => $brand,
            'user'       => $user,
            'pageTitle'  => 'Edit ' . $brand['name'],
            'layout'     => 'app',
            'activeBrand'=> $brand,
            'csrf'       => Auth::csrfToken(),
        ]);
    }

    public function update(array $p): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->brandModel->findBySlug($p['slug']);
        if (!$brand) abort(404);
        if (!$this->brandModel->userCanAccess($brand['id'], $user['id'], 'admin', 'owner')) abort(403);
        $this->brandModel->update($brand['id'], [
            'name'        => $this->request->post('name', $brand['name']),
            'description' => $this->request->post('description', ''),
            'industry'    => $this->request->post('industry', ''),
        ]);
        Response::flash('success', 'Brand updated.');
        Response::redirect('/brands/' . $p['slug']);
    }

    public function delete(array $p): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->brandModel->findBySlug($p['slug']);
        if (!$brand) abort(404);
        if (!$this->brandModel->userCanAccess($brand['id'], $user['id'], 'owner')) abort(403);
        $this->brandModel->delete($brand['id']);
        Response::flash('success', 'Brand deleted.');
        Response::redirect('/brands');
    }

    private function generateUuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
