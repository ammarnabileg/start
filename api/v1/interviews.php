<?php
/**
 * /api/v1/interviews
 *   GET    /                    list
 *   POST   /                    create for application
 *   GET    /{id}                one
 *   GET    /room/{token}        interview room data (public)
 *   POST   /start/{token}       start (public)
 *   POST   /message/{token}     candidate message -> AI response (public)
 *   POST   /complete/{token}    complete + evaluate (public)
 *   GET    /{id}/report         full report
 */

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\Interviews\InterviewService;

$api = $GLOBALS['__api'];
$req = new Request();
$service = new InterviewService();

$id = $api['sub'];
$token = $api['sub2'];

// ---- Public, token-based endpoints (no auth, no session) ----------------
if ($id === 'room' && $api['method'] === 'GET') {
    $roomData = roomData((string) $token);
    $roomData ? Response::success($roomData) : Response::error('Interview not found', 404);
    return;
}
if ($id === 'start' && $api['method'] === 'POST') {
    $result = $service->startInterview((string) $token);
    $result ? Response::success($result) : Response::error('Unable to start interview', 400);
    return;
}
if ($id === 'message' && $api['method'] === 'POST') {
    $message = (string) $req->post('message', '');
    if (trim($message) === '') {
        Response::error('Message is required', 422);
        return;
    }
    $result = $service->processMessage((string) $token, $message);
    Response::success($result);
    return;
}
if ($id === 'complete' && $api['method'] === 'POST') {
    $result = $service->completeInterview((string) $token);
    Response::success($result, 'Interview completed');
    return;
}

// ---- Authenticated endpoints --------------------------------------------
$auth = new Auth();
$tenantId = (new Tenant())->currentId();

if ($api['method'] === 'GET' && $api['sub2'] === 'report') {
    $auth->requirePermission('interviews.report');
    Response::success($service->getReport((int) $id));
    return;
}

if ($api['method'] === 'GET' && ($id === null || $id === '')) {
    $auth->requirePermission('interviews.view');
    Response::success($service->getInterviews($tenantId, [
        'type'   => $req->get('type'),
        'status' => $req->get('status'),
        'date'   => $req->get('date'),
    ]));
    return;
}

if ($api['method'] === 'GET' && $id !== null) {
    $auth->requirePermission('interviews.view');
    Response::success($service->getReport((int) $id));
    return;
}

if ($api['method'] === 'POST' && ($id === null || $id === '')) {
    $auth->requirePermission('interviews.create');
    $applicationId = (int) $req->post('application_id', 0);
    $type = (string) $req->post('type', 'ai_text');
    if (!$applicationId) {
        Response::error('application_id is required', 422);
        return;
    }
    $interview = $service->createInterview($applicationId, $type, $req->all());
    Response::success([
        'interview' => $interview,
        'room_url'  => '/interview/room/' . ($interview['token'] ?? ''),
    ], 'Interview created', 201);
    return;
}

Response::error('Method not allowed', 405);

/** Helper to load room data by token without leaking internals. */
function roomData(string $token): ?array
{
    $repo = new App\Modules\Interviews\InterviewRepository();
    $interview = $repo->findByToken($token);
    if (!$interview) {
        return null;
    }
    return [
        'token'    => $token,
        'type'     => $interview['type'] ?? 'ai_text',
        'status'   => $interview['status'] ?? 'pending',
        'job'      => $interview['job_title'] ?? ($interview['title'] ?? 'Interview'),
        'company'  => $interview['company_name'] ?? '',
        'avatar'   => [
            'heygen_avatar_id' => $interview['heygen_avatar_id'] ?? null,
            'voice_id'         => $interview['voice_id'] ?? null,
        ],
        'messages' => $repo->getMessages((int) $interview['id']),
    ];
}
