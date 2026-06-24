<?php
declare(strict_types=1);
/**
 * api/v1/offers.php — Offer management endpoints
 */

Auth::requireAuth();
$db     = Database::getInstance();
$userId = Auth::user()['id'];
$tid    = Auth::user()['tenant_id'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$action = $request->get('action') ?? $request->input('action') ?? '';

// ── List offers ───────────────────────────────────────────────────────────
if ($method === 'GET' && !$action) {
    Auth::requirePermission('offers.view');
    $page   = max(1, (int)$request->get('page', 1));
    $status = $request->get('status', '');

    $where  = ['o.tenant_id = ?'];
    $params = [$tid];
    if ($status) { $where[] = 'o.status = ?'; $params[] = $status; }

    $sql = "SELECT o.*, c.full_name, c.email, j.title as job_title,
                   a.current_stage
            FROM offers o
            JOIN candidates c ON c.id = o.candidate_id
            JOIN jobs j ON j.id = o.job_id
            LEFT JOIN applications a ON a.id = o.application_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.created_at DESC";

    $result = $db->paginate($sql, $params, $page, 20);
    Response::paginated($result['data'], $result['total'], $page, 20);
}

// ── Create offer ──────────────────────────────────────────────────────────
elseif ($method === 'POST' && !$action) {
    Auth::requirePermission('offers.create');

    $data = $request->only(['application_id','candidate_id','job_id','title',
                             'salary_amount','salary_currency','salary_type',
                             'start_date','expiry_date','benefits','conditions','offer_letter']);

    $appId = (int)($data['application_id'] ?? 0);
    if (!$appId) { Response::error('application_id required', 422); exit; }

    $app = $db->fetch("SELECT * FROM applications WHERE id = ? AND tenant_id = ?", [$appId, $tid]);
    if (!$app) { Response::error('Application not found', 404); exit; }

    $offerId = $db->insert('offers', [
        'tenant_id'       => $tid,
        'application_id'  => $appId,
        'candidate_id'    => $app['candidate_id'],
        'job_id'          => $app['job_id'],
        'created_by'      => $userId,
        'title'           => $data['title'] ?? '',
        'salary_amount'   => $data['salary_amount'] ?? null,
        'salary_currency' => $data['salary_currency'] ?? 'USD',
        'salary_type'     => $data['salary_type'] ?? 'annual',
        'benefits'        => is_array($data['benefits'] ?? null) ? json_encode($data['benefits']) : ($data['benefits'] ?? null),
        'conditions'      => $data['conditions'] ?? null,
        'offer_letter'    => $data['offer_letter'] ?? null,
        'start_date'      => $data['start_date'] ?? null,
        'expiry_date'     => $data['expiry_date'] ?? null,
        'status'          => 'draft',
        'created_at'      => date('Y-m-d H:i:s'),
        'updated_at'      => date('Y-m-d H:i:s')
    ]);

    // Move application to offer stage
    $db->update('applications', ['current_stage' => 'offer', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $appId]);

    $db->insert('audit_logs', [
        'tenant_id' => $tid, 'user_id' => $userId,
        'action' => 'offer.created', 'entity_type' => 'offer', 'entity_id' => $offerId,
        'meta' => json_encode(['application_id' => $appId]),
        'created_at' => date('Y-m-d H:i:s')
    ]);

    Response::success(['id' => $offerId, 'message' => 'Offer created']);
}

// ── Update offer ──────────────────────────────────────────────────────────
elseif ($method === 'PUT') {
    Auth::requirePermission('offers.edit');
    $id  = (int)$request->get('id');
    $offer = $db->fetch("SELECT id FROM offers WHERE id = ? AND tenant_id = ?", [$id, $tid]);
    if (!$offer) { Response::error('Not found', 404); exit; }

    $data = $request->only(['title','salary_amount','salary_currency','salary_type',
                             'start_date','expiry_date','benefits','conditions','offer_letter']);
    if (isset($data['benefits']) && is_array($data['benefits'])) {
        $data['benefits'] = json_encode($data['benefits']);
    }
    $data['updated_at'] = date('Y-m-d H:i:s');

    $db->update('offers', $data, ['id' => $id]);
    Response::success(['message' => 'Updated']);
}

// ── Send offer ────────────────────────────────────────────────────────────
elseif ($method === 'POST' && $action === 'send') {
    Auth::requirePermission('offers.edit');
    $id = (int)$request->input('id');
    $offer = $db->fetch("SELECT * FROM offers WHERE id = ? AND tenant_id = ?", [$id, $tid]);
    if (!$offer) { Response::error('Not found', 404); exit; }

    $db->update('offers', ['status' => 'pending', 'sent_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);

    // Create notification for candidate
    $db->insert('notifications', [
        'user_id'    => $offer['candidate_id'],
        'tenant_id'  => $tid,
        'type'       => 'offer_received',
        'title'      => 'You have received a job offer',
        'body'       => 'A new offer has been sent to you. Please review and respond.',
        'data'       => json_encode(['offer_id' => $id]),
        'created_at' => date('Y-m-d H:i:s')
    ]);

    Response::success(['message' => 'Offer sent']);
}

// ── Accept / Decline offer (candidate action) ────────────────────────────
elseif ($method === 'POST' && in_array($action, ['accept','decline'])) {
    $id    = (int)$request->input('id');
    $offer = $db->fetch("SELECT * FROM offers WHERE id = ? AND tenant_id = ?", [$id, $tid]);
    if (!$offer) { Response::error('Not found', 404); exit; }

    $status = $action === 'accept' ? 'accepted' : 'declined';
    $db->update('offers', [
        'status'       => $status,
        'responded_at' => date('Y-m-d H:i:s'),
        'updated_at'   => date('Y-m-d H:i:s')
    ], ['id' => $id]);

    if ($action === 'accept' && $offer['application_id']) {
        $db->update('applications', ['current_stage' => 'hired', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $offer['application_id']]);
    }

    Response::success(['message' => ucfirst($status)]);
}

// ── AI generate offer letter ──────────────────────────────────────────────
elseif ($method === 'POST' && $action === 'generate') {
    Auth::requirePermission('ai.use');
    $offerId = (int)$request->input('offer_id');
    $offer   = $db->fetch(
        "SELECT o.*, c.full_name, j.title as job_title, j.department
         FROM offers o
         JOIN candidates c ON c.id = o.candidate_id
         JOIN jobs j ON j.id = o.job_id
         WHERE o.id = ? AND o.tenant_id = ?",
        [$offerId, $tid]
    );
    if (!$offer) { Response::error('Not found', 404); exit; }

    require_once BASE_PATH . '/modules/AI/OpenAIService.php';
    $ai = new OpenAIService();

    $tenant = $db->fetch("SELECT name FROM tenants WHERE id = ?", [$tid]);
    $company = $tenant['name'] ?? 'Our Company';

    $prompt = "Write a professional job offer letter for {$company}.
Candidate: {$offer['full_name']}
Position: {$offer['job_title']}
Department: {$offer['department']}
Salary: {$offer['salary_amount']} {$offer['salary_currency']} per {$offer['salary_type']}
Start Date: {$offer['start_date']}
Benefits: " . ($offer['benefits'] ? implode(', ', json_decode($offer['benefits'], true) ?: []) : 'Standard package') . "

Write a warm, professional offer letter in HTML format (use <p> and <br> tags, no full HTML document wrapper). Include standard offer letter elements: welcome, position details, compensation, benefits summary, next steps.";

    $letter = $ai->complete($prompt, 1000);
    $db->update('offers', ['offer_letter' => $letter, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $offerId]);

    Response::success(['offer_letter' => $letter]);
}

else {
    Response::error('Unknown action', 400);
}
