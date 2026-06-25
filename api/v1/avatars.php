<?php
/**
 * /api/v1/avatars
 *   GET    /                 company avatars
 *   GET    /heygen           list HeyGen avatars (proxy)
 *   POST   /                 save an avatar for company
 *   DELETE /{id}             remove
 *   POST   /{id}/preview     generate preview video
 *   POST   /{id}/streaming   get streaming session token
 */

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Modules\HeyGen\AvatarController;

$api = $GLOBALS['__api'];
$auth = new Auth();
$controller = new AvatarController();

$id = $api['sub'];
$action = $api['sub2'];

if ($api['method'] === 'GET' && $id === 'heygen') {
    $auth->requirePermission('avatars.view');
    $controller->listHeyGenAvatars();
    return;
}

if ($api['method'] === 'GET' && ($id === null || $id === '')) {
    $auth->requirePermission('avatars.view');
    $controller->index();
    return;
}

if ($api['method'] === 'POST' && ($id === null || $id === '')) {
    $auth->requirePermission('avatars.manage');
    $controller->save(['avatarId' => (new Request())->post('heygen_avatar_id')]);
    return;
}

if ($api['method'] === 'POST' && $action === 'preview') {
    $auth->requirePermission('avatars.view');
    $controller->previewAvatar(['id' => $id]);
    return;
}

if ($api['method'] === 'POST' && $action === 'streaming') {
    $auth->requirePermission('avatars.view');
    $controller->getStreamingToken(['id' => $id]);
    return;
}

if ($api['method'] === 'DELETE' && $id !== null) {
    $auth->requirePermission('avatars.manage');
    $controller->delete(['id' => $id]);
    return;
}

Response::error('Method not allowed', 405);
