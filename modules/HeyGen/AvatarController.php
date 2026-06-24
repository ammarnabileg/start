<?php
namespace App\Modules\HeyGen;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;

/**
 * HTTP controller for avatar management. Avatars are HeyGen-backed; the company
 * persists a selection of them (with voice) in the avatars table for use in AI
 * video interviews.
 *
 * Gated by avatars.view (read) / avatars.manage (mutate + HeyGen proxy calls).
 */
class AvatarController
{
    private HeyGenService $heygen;
    private Auth $auth;
    private Request $request;
    private Database $db;

    public function __construct(?HeyGenService $heygen = null)
    {
        $this->heygen = $heygen ?? new HeyGenService();
        $this->auth = new Auth();
        $this->request = new Request();
        $this->db = Database::instance();
    }

    /**
     * List the company's saved avatars from the database.
     */
    public function index(array $params = []): void
    {
        $this->auth->requirePermission('avatars.view');
        $tenantId = $this->tenantId();

        $avatars = $this->db->fetchAll(
            'SELECT * FROM avatars WHERE tenant_id = :tid ORDER BY id DESC',
            [':tid' => $tenantId]
        );

        if ($this->wantsJson()) {
            Response::success(['avatars' => $avatars]);
            return;
        }
        Response::view('hr.avatars', ['avatars' => $avatars]);
    }

    /**
     * Proxy: list HeyGen avatars available to the account.
     */
    public function listHeyGenAvatars(array $params = []): void
    {
        $this->auth->requirePermission('avatars.view');
        $result = $this->heygen->listAvatars();
        if (isset($result['error'])) {
            Response::success(['avatars' => $result['data'] ?? [], 'warning' => $result['error']]);
            return;
        }
        $avatars = $result['data']['avatars'] ?? $result['data'] ?? [];
        Response::success(['avatars' => $avatars]);
    }

    /**
     * Proxy: list HeyGen voices available to the account.
     */
    public function listHeyGenVoices(array $params = []): void
    {
        $this->auth->requirePermission('avatars.view');
        $result = $this->heygen->listVoices();
        if (isset($result['error'])) {
            Response::success(['voices' => $result['data'] ?? [], 'warning' => $result['error']]);
            return;
        }
        $voices = $result['data']['voices'] ?? $result['data'] ?? [];
        Response::success(['voices' => $voices]);
    }

    /**
     * Save (insert) an avatar selection for the tenant.
     */
    public function save(array $params = []): void
    {
        $this->auth->requirePermission('avatars.manage');
        $tenantId = $this->tenantId();
        $data = $this->request->all();

        $heygenAvatarId = trim((string) ($data['heygen_avatar_id'] ?? ''));
        if ($heygenAvatarId === '') {
            Response::error('heygen_avatar_id is required', 422);
            return;
        }

        // Upsert by (tenant_id, heygen_avatar_id) so re-saving updates instead of duplicating.
        $existing = $this->db->fetch(
            'SELECT * FROM avatars WHERE tenant_id = :tid AND heygen_avatar_id = :hid LIMIT 1',
            [':tid' => $tenantId, ':hid' => $heygenAvatarId]
        );

        $payload = [
            'name'        => $data['name'] ?? null,
            'preview_url' => $data['preview_url'] ?? null,
            'voice_id'    => $data['voice_id'] ?? null,
            'language'    => $data['language'] ?? 'en',
            'is_active'   => isset($data['is_active']) ? (int) ((bool) $data['is_active']) : 1,
        ];

        if ($existing !== null) {
            $this->db->update('avatars', $payload, ['id' => (int) $existing['id'], 'tenant_id' => $tenantId]);
            $id = (int) $existing['id'];
        } else {
            $id = $this->db->insert('avatars', array_merge($payload, [
                'tenant_id'        => $tenantId,
                'heygen_avatar_id' => $heygenAvatarId,
            ]));
        }

        $avatar = $this->db->fetch('SELECT * FROM avatars WHERE id = :id LIMIT 1', [':id' => $id]);
        Response::success(['avatar' => $avatar], 'Avatar saved', $existing !== null ? 200 : 201);
    }

    /**
     * Delete an avatar row (tenant-scoped).
     */
    public function delete(array $params = []): void
    {
        $this->auth->requirePermission('avatars.manage');
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? $this->request->input('id', 0));

        if ($id <= 0) {
            Response::error('Avatar id is required', 422);
            return;
        }
        $existing = $this->db->fetch(
            'SELECT id FROM avatars WHERE id = :id AND tenant_id = :tid LIMIT 1',
            [':id' => $id, ':tid' => $tenantId]
        );
        if ($existing === null) {
            Response::error('Avatar not found', 404);
            return;
        }

        $this->db->delete('avatars', ['id' => $id, 'tenant_id' => $tenantId]);
        Response::success(['id' => $id], 'Avatar deleted');
    }

    /**
     * Generate a short preview video greeting for an avatar.
     */
    public function previewAvatar(array $params = []): void
    {
        $this->auth->requirePermission('avatars.view');
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? $this->request->input('id', 0));

        $avatarId = (string) $this->request->input('heygen_avatar_id', '');
        $voiceId = (string) $this->request->input('voice_id', '');

        // Prefer a stored avatar's identifiers when an internal id is given.
        if ($id > 0) {
            $avatar = $this->db->fetch(
                'SELECT * FROM avatars WHERE id = :id AND tenant_id = :tid LIMIT 1',
                [':id' => $id, ':tid' => $tenantId]
            );
            if ($avatar === null) {
                Response::error('Avatar not found', 404);
                return;
            }
            $avatarId = (string) $avatar['heygen_avatar_id'];
            $voiceId = $voiceId !== '' ? $voiceId : (string) ($avatar['voice_id'] ?? '');
        }

        if ($avatarId === '') {
            Response::error('An avatar id is required', 422);
            return;
        }

        $script = (string) $this->request->input(
            'script',
            'Hello! Thank you for joining. I am your AI interviewer today, and I am looking forward to our conversation.'
        );

        $video = $this->heygen->generateVideo($avatarId, $voiceId, $script);
        if (isset($video['error'])) {
            Response::error($video['error'], 502, ['data' => $video['data'] ?? []]);
            return;
        }

        Response::success(['video' => $video['data'] ?? $video]);
    }

    /**
     * Create a realtime streaming session and return its token/session data.
     */
    public function getStreamingToken(array $params = []): void
    {
        $this->auth->requirePermission('avatars.view');
        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? $this->request->input('id', 0));

        $avatarId = (string) $this->request->input('heygen_avatar_id', '');
        $voiceId = (string) $this->request->input('voice_id', '');

        if ($id > 0) {
            $avatar = $this->db->fetch(
                'SELECT * FROM avatars WHERE id = :id AND tenant_id = :tid LIMIT 1',
                [':id' => $id, ':tid' => $tenantId]
            );
            if ($avatar === null) {
                Response::error('Avatar not found', 404);
                return;
            }
            $avatarId = (string) $avatar['heygen_avatar_id'];
            $voiceId = $voiceId !== '' ? $voiceId : (string) ($avatar['voice_id'] ?? '');
        }

        if ($avatarId === '') {
            Response::error('An avatar id is required', 422);
            return;
        }

        $session = $this->heygen->createStreamingSession($avatarId, $voiceId);
        if (isset($session['error'])) {
            Response::error($session['error'], 502, ['data' => $session['data'] ?? []]);
            return;
        }

        Response::success(['session' => $session['data'] ?? $session]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function tenantId(): int
    {
        (new Tenant())->resolve();
        $tenantId = (new Tenant())->currentId();
        if ($tenantId === null) {
            $user = $this->auth->user();
            $tenantId = $user && $user['tenant_id'] !== null ? (int) $user['tenant_id'] : 0;
        }
        if ($tenantId > 0) {
            $this->db->setTenantId($tenantId);
        }
        return (int) $tenantId;
    }

    private function wantsJson(): bool
    {
        return $this->request->isAjax()
            || str_contains((string) $this->request->header('Accept'), 'application/json')
            || $this->request->bearerToken() !== null;
    }
}
