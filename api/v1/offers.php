<?php
/**
 * /api/v1/offers
 *   GET    /             list
 *   POST   /             create
 *   GET    /{id}         one
 *   PUT    /{id}         update
 *   POST   /{id}/send    send to candidate
 *   POST   /accept/{token}   candidate accepts (public)
 *   POST   /reject/{token}   candidate rejects (public)
 */

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\Offers\OfferService;

$api = $GLOBALS['__api'];
$req = new Request();
$service = new OfferService();

$id = $api['sub'];
$action = $api['sub2'];

// Public accept/reject by token.
if ($id === 'accept' && $api['method'] === 'POST') {
    $offer = $service->processResponse((string) $action, true);
    $offer ? Response::success($offer, 'Offer accepted') : Response::error('Offer not found', 404);
    return;
}
if ($id === 'reject' && $api['method'] === 'POST') {
    $offer = $service->processResponse((string) $action, false);
    $offer ? Response::success($offer, 'Offer declined') : Response::error('Offer not found', 404);
    return;
}

$auth = new Auth();
$tenantId = (new Tenant())->currentId();

if ($api['method'] === 'GET' && ($id === null || $id === '')) {
    $auth->requirePermission('offers.view');
    Response::success($service->getOffers($tenantId, ['status' => $req->get('status')]));
    return;
}

if ($api['method'] === 'GET' && $id !== null) {
    $auth->requirePermission('offers.view');
    Response::success($service->getOffers($tenantId, ['id' => (int) $id]));
    return;
}

if ($api['method'] === 'POST' && ($id === null || $id === '')) {
    $auth->requirePermission('offers.create');
    $applicationId = (int) $req->post('application_id', 0);
    if (!$applicationId) {
        Response::error('application_id is required', 422);
        return;
    }
    $offer = $service->createOffer($applicationId, $req->all());
    Response::success($offer, 'Offer created', 201);
    return;
}

if ($api['method'] === 'POST' && $action === 'send') {
    $auth->requirePermission('offers.send');
    Response::success($service->sendOffer((int) $id), 'Offer sent');
    return;
}

if ($api['method'] === 'PUT' && $id !== null) {
    $auth->requirePermission('offers.create');
    $service->updateOffer((int) $id, $req->all());
    Response::success(null, 'Offer updated');
    return;
}

Response::error('Method not allowed', 405);
