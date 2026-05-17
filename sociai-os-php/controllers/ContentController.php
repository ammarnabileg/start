<?php
/**
 * SociAI OS - Content Controller
 */

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Request, Response};
use SociAI\Models\{Brand, Content};

class ContentController
{
    private Request  $request;
    private Response $response;
    private Brand    $brandModel;
    private Content  $contentModel;

    public function __construct()
    {
        $this->request      = new Request();
        $this->response     = new Response();
        $this->brandModel   = new Brand();
        $this->contentModel = new Content();
    }

    public function index(array $params): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->getBrand($params['slug'], $user['id']);

        $filters = $this->request->only(['status','language','content_type','campaign_id','search']);
        $page    = $this->request->getInt('page', 1);

        $result = $this->contentModel->getByBrand($brand['id'], $filters, $page);

        if ($this->request->isAjax()) {
            $this->response->paginated($result);
            return;
        }

        $stats = $this->contentModel->getStats($brand['id']);

        $this->response->view('content.index', [
            'brand'     => $brand,
            'result'    => $result,
            'stats'     => $stats,
            'filters'   => $filters,
            'user'      => $user,
            'pageTitle' => 'Content',
            'layout'    => 'app',
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function create(array $params): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->getBrand($params['slug'], $user['id'], 'editor', 'manager', 'admin', 'owner');

        $campaigns = \SociAI\Core\Database::getInstance()->fetchAll(
            "SELECT id, name FROM campaigns WHERE brand_id = ? AND status IN ('draft','active') ORDER BY name",
            [$brand['id']]
        );
        $platforms = $this->brandModel->getPlatformAccounts($brand['id']);
        $strategy  = $this->brandModel->getActiveStrategy($brand['id']);

        $this->response->view('content.create', [
            'brand'     => $brand,
            'campaigns' => $campaigns,
            'platforms' => $platforms,
            'strategy'  => $strategy,
            'user'      => $user,
            'pageTitle' => 'Create Content',
            'layout'    => 'app',
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function store(array $params): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->getBrand($params['slug'], $user['id'], 'editor', 'manager', 'admin', 'owner');

        $errors = $this->request->validate([
            'content_type' => 'required',
            'language'     => 'required|in:arabic,english,mixed',
        ]);
        if (!empty($errors)) {
            if ($this->request->isAjax()) {
                $this->response->error('Validation failed.', 422, $errors);
            } else {
                Response::flash('error', 'Please fix the errors below.');
                $this->response->redirect('/brands/' . $params['slug'] . '/content/create');
            }
            return;
        }

        $hashtags = $this->request->post('hashtags', '');
        if (is_string($hashtags)) {
            $hashtags = array_filter(array_map('trim', explode(',', $hashtags)));
        }

        $content = $this->contentModel->create([
            'brand_id'      => $brand['id'],
            'campaign_id'   => $this->request->post('campaign_id') ?: null,
            'title'         => $this->request->post('title', ''),
            'content_type'  => $this->request->post('content_type'),
            'topic'         => $this->request->post('topic', ''),
            'writing_style' => $this->request->post('writing_style', 'professional'),
            'language'      => $this->request->post('language', 'english'),
            'body_text'     => $this->request->post('body_text', ''),
            'hook'          => $this->request->post('hook', ''),
            'cta'           => $this->request->post('cta', ''),
            'hashtags'      => $hashtags,
            'created_by'    => $user['id'],
            'ai_generated'  => 0,
        ]);

        if ($this->request->isAjax()) {
            $this->response->success($content, 'Content created.', 201);
            return;
        }
        Response::flash('success', 'Content piece created successfully.');
        $this->response->redirect('/brands/' . $params['slug'] . '/content/' . $content['id']);
    }

    public function show(array $params): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brand   = $this->getBrand($params['slug'], $user['id']);
        $content = $this->contentModel->findOrFail($params['id']);

        if ($content['brand_id'] !== $brand['id']) {
            abort(403);
        }

        $scheduledPosts = \SociAI\Core\Database::getInstance()->fetchAll(
            "SELECT sp.*, pa.account_name FROM scheduled_posts sp
             JOIN platform_accounts pa ON pa.id = sp.platform_account_id
             WHERE sp.content_id = ? ORDER BY sp.scheduled_at ASC",
            [$content['id']]
        );

        $this->response->view('content.show', [
            'brand'          => $brand,
            'content'        => $content,
            'scheduledPosts' => $scheduledPosts,
            'platforms'      => $this->brandModel->getPlatformAccounts($brand['id']),
            'userRole'       => $this->brandModel->getMemberRole($brand['id'], $user['id']),
            'user'           => $user,
            'pageTitle'      => $content['title'] ?: 'Content Details',
            'layout'         => 'app',
            'csrf'           => Auth::csrfToken(),
        ]);
    }

    public function edit(array $params): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brand   = $this->getBrand($params['slug'], $user['id'], 'editor','manager','admin','owner');
        $content = $this->contentModel->findOrFail($params['id']);
        if ($content['brand_id'] !== $brand['id']) abort(403);

        $this->response->view('content.edit', [
            'brand'     => $brand,
            'content'   => $content,
            'user'      => $user,
            'pageTitle' => 'Edit Content',
            'layout'    => 'app',
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function update(array $params): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brand   = $this->getBrand($params['slug'], $user['id'], 'editor','manager','admin','owner');
        $content = $this->contentModel->findOrFail($params['id']);
        if ($content['brand_id'] !== $brand['id']) abort(403);

        $hashtags = $this->request->post('hashtags', []);
        if (is_string($hashtags)) {
            $hashtags = array_filter(array_map('trim', explode(',', $hashtags)));
        }

        $this->contentModel->update($params['id'], [
            'title'        => $this->request->post('title', $content['title']),
            'body_text'    => $this->request->post('body_text', ''),
            'hook'         => $this->request->post('hook', ''),
            'cta'          => $this->request->post('cta', ''),
            'hashtags'     => $hashtags,
            'writing_style'=> $this->request->post('writing_style', 'professional'),
            'language'     => $this->request->post('language', 'english'),
        ]);

        if ($this->request->isAjax()) {
            $this->response->success([], 'Updated.');
            return;
        }
        Response::flash('success', 'Content updated.');
        $this->response->redirect('/brands/' . $params['slug'] . '/content/' . $params['id']);
    }

    public function approve(array $params): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->getBrand($params['slug'], $user['id'], 'manager','admin','owner');

        if ($this->contentModel->approve($params['id'], $user['id'])) {
            $this->response->success([], 'Content approved.');
        } else {
            $this->response->error('Could not approve this content. It may not be in pending status.');
        }
    }

    public function reject(array $params): void
    {
        Auth::requireAuth();
        $user   = Auth::getCurrentUser();
        $brand  = $this->getBrand($params['slug'], $user['id'], 'manager','admin','owner');
        $reason = $this->request->post('reason', 'No reason provided.');

        if ($this->contentModel->reject($params['id'], $reason)) {
            $this->response->success([], 'Content rejected.');
        } else {
            $this->response->error('Could not reject this content.');
        }
    }

    public function schedule(array $params): void
    {
        Auth::requireAuth();
        $user  = Auth::getCurrentUser();
        $brand = $this->getBrand($params['slug'], $user['id'], 'editor','manager','admin','owner');

        $errors = $this->request->validate([
            'platform_account_id' => 'required|uuid',
            'scheduled_at'        => 'required',
        ]);
        if (!empty($errors)) {
            $this->response->error('Validation failed.', 422, $errors);
            return;
        }

        try {
            $scheduleId = $this->contentModel->schedule(
                $params['id'],
                $this->request->post('platform_account_id'),
                $this->request->post('scheduled_at')
            );
            $this->response->success(['schedule_id' => $scheduleId], 'Post scheduled successfully.');
        } catch (\Throwable $e) {
            $this->response->error($e->getMessage());
        }
    }

    private function getBrand(string $slug, string $userId, string ...$roles): array
    {
        $brand = $this->brandModel->findBySlug($slug);
        if (!$brand) abort(404, 'Brand not found.');
        if (!$this->brandModel->userCanAccess($brand['id'], $userId, ...$roles)) {
            abort(403, 'Insufficient permissions.');
        }
        return $brand;
    }
}
