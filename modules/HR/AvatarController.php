<?php
declare(strict_types=1);

class AvatarController
{
    public static function index(Request $r): void
    {
        Auth::requirePermission('avatars.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $avatars    = $db->fetchAll("SELECT * FROM avatars WHERE tenant_id = ? ORDER BY is_default DESC, name ASC", [$tenantId]);
        $hasHeyGen  = Tenant::hasHeyGen();
        $hasOpenAI  = Tenant::hasOpenAI();

        renderView('hr/avatars', compact('avatars', 'hasHeyGen', 'hasOpenAI'), 'app');
    }

    public static function save(Request $r): void
    {
        Auth::requirePermission('avatars.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $data = $r->only(['id', 'name', 'gender', 'language', 'style', 'personality_prompt', 'heygen_avatar_id', 'heygen_voice_id', 'is_default']);
        $v = Validator::make($data, [
            'name'               => 'required|max:100',
            'gender'             => 'required|in:male,female,neutral',
            'language'           => 'required|in:en,ar,both',
            'style'              => 'required|in:formal,casual,technical',
            'personality_prompt' => 'required',
        ]);
        if ($v->fails()) { Response::error($v->firstError(), 422, $v->errors()); return; }

        $now  = date('Y-m-d H:i:s');
        $id   = (int)($data['id'] ?? 0);
        $isDefault = !empty($data['is_default']) ? 1 : 0;

        if ($isDefault) {
            $db->query("UPDATE avatars SET is_default = 0 WHERE tenant_id = ?", [$tenantId]);
        }

        $payload = [
            'name'               => $data['name'],
            'gender'             => $data['gender'],
            'language'           => $data['language'],
            'style'              => $data['style'],
            'personality_prompt' => $data['personality_prompt'],
            'heygen_avatar_id'   => $data['heygen_avatar_id'] ?? null,
            'heygen_voice_id'    => $data['heygen_voice_id'] ?? null,
            'is_default'         => $isDefault,
            'status'             => 'active',
            'updated_at'         => $now,
        ];

        if ($id) {
            $existing = $db->fetch("SELECT id FROM avatars WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            if (!$existing) { Response::error('Not found', 404); return; }
            $db->update('avatars', $payload, ['id' => $id]);
        } else {
            $payload['tenant_id']  = $tenantId;
            $payload['created_at'] = $now;
            $id = $db->insert('avatars', $payload);
        }

        Response::success(['id' => $id], 'Avatar saved.');
    }

    public static function delete(Request $r, int $id): void
    {
        Auth::requirePermission('avatars.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $avatar = $db->fetch("SELECT * FROM avatars WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$avatar) { Response::error('Not found', 404); return; }
        if ($avatar['is_default']) { Response::error('Cannot delete the default avatar.', 422); return; }

        $db->update('avatars', ['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        Response::success(null, 'Avatar deleted.');
    }
}
