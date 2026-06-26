<?php
declare(strict_types=1);

class TalentPoolController
{
    public static function index(Request $r): void
    {
        Auth::requirePermission('talent_pool.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $groupId = (int)$r->get('group_id', 0);
        $search  = $r->get('q', '');

        $groups = $db->fetchAll(
            "SELECT g.*, COUNT(m.id) AS member_count
             FROM talent_pool_groups g
             LEFT JOIN talent_pool_members m ON m.group_id = g.id
             WHERE g.tenant_id = ? GROUP BY g.id ORDER BY g.name",
            [$tenantId]
        );

        $sql = "SELECT m.*, u.first_name, u.last_name, u.email, u.phone,
                       cp.years_experience, cp.current_job_title,
                       g.name AS group_name
                FROM talent_pool_members m
                JOIN users u ON u.id = m.user_id
                JOIN talent_pool_groups g ON g.id = m.group_id
                LEFT JOIN candidate_profiles cp ON cp.user_id = m.user_id
                WHERE g.tenant_id = ?";
        $params = [$tenantId];

        if ($groupId) { $sql .= " AND m.group_id = ?"; $params[] = $groupId; }
        if ($search) {
            $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $like = "%$search%";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $sql .= " ORDER BY m.added_at DESC";

        $members = $db->fetchAll($sql, $params);

        renderView('hr/talent-pool', compact('groups', 'members', 'groupId', 'search'), 'app');
    }

    public static function createGroup(Request $r): void
    {
        Auth::requirePermission('talent_pool.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $data = $r->only(['name', 'description', 'color']);
        $v = Validator::make($data, ['name' => 'required|max:255']);
        if ($v->fails()) { Response::error($v->firstError(), 422, $v->errors()); return; }

        $now = date('Y-m-d H:i:s');
        $id  = $db->insert('talent_pool_groups', [
            'tenant_id'   => $tenantId,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'color'       => $data['color'] ?? '#4f46e5',
            'created_by'  => Auth::id(),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        Response::success(['id' => $id, 'name' => $data['name']], 'Group created.');
    }

    public static function addMember(Request $r): void
    {
        Auth::requirePermission('talent_pool.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $userId  = (int)$r->post('user_id', 0);
        $groupId = (int)$r->post('group_id', 0);
        $notes   = (string)$r->post('notes', '');

        $group = $db->fetch("SELECT id FROM talent_pool_groups WHERE id = ? AND tenant_id = ?", [$groupId, $tenantId]);
        if (!$group) { Response::error('Group not found.', 404); return; }

        $existing = $db->fetchColumn(
            "SELECT COUNT(*) FROM talent_pool_members WHERE group_id = ? AND user_id = ?",
            [$groupId, $userId]
        );
        if ($existing) { Response::error('Candidate already in this group.', 422); return; }

        $now = date('Y-m-d H:i:s');
        $db->insert('talent_pool_members', [
            'group_id'   => $groupId,
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'notes'      => $notes ?: null,
            'added_by'   => Auth::id(),
            'added_at'   => $now,
            'created_at' => $now,
        ]);

        Response::success(null, 'Candidate added to talent pool.');
    }

    public static function removeMember(Request $r): void
    {
        Auth::requirePermission('talent_pool.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $userId  = (int)$r->post('user_id', 0);
        $groupId = (int)$r->post('group_id', 0);

        $group = $db->fetch("SELECT id FROM talent_pool_groups WHERE id = ? AND tenant_id = ?", [$groupId, $tenantId]);
        if (!$group) { Response::error('Group not found.', 404); return; }

        $db->delete('talent_pool_members', ['group_id' => $groupId, 'user_id' => $userId]);
        Response::success(null, 'Removed from talent pool.');
    }
}
