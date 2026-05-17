<?php
declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

class TeamController
{
    private Database $db;
    private Request $request;

    private const ROLES = ['owner', 'admin', 'manager', 'editor', 'viewer'];
    private const PERMISSIONS = [
        'owner'   => ['all'],
        'admin'   => ['content.manage', 'analytics.view', 'team.manage', 'settings.view', 'campaigns.manage', 'community.manage'],
        'manager' => ['content.manage', 'analytics.view', 'campaigns.manage', 'community.manage'],
        'editor'  => ['content.create', 'content.edit', 'campaigns.view', 'community.reply'],
        'viewer'  => ['content.view', 'analytics.view', 'campaigns.view'],
    ];

    public function __construct()
    {
        $this->db      = Database::getInstance();
        $this->request = new Request();
    }

    public function index(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $members = $this->loadTeamMembers($brandId);
        $pending = $this->loadPendingInvites($brandId);
        Response::view('team/index', [
            'title'   => 'Team - SociAI OS',
            'members' => $members,
            'pending' => $pending,
            'roles'   => self::ROLES,
            'user'    => $user,
            'csrf'    => Auth::csrfToken(),
        ]);
    }

    public function invite(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        if (!$this->userIsAdmin($user, $brandId)) {
            Response::json(['success' => false, 'error' => 'Insufficient permissions'], 403);
            return;
        }
        $email = trim($this->request->post('email', ''));
        $role  = $this->request->post('role', 'viewer');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'error' => 'Invalid email'], 422);
            return;
        }
        if (!in_array($role, self::ROLES, true) || $role === 'owner') {
            Response::json(['success' => false, 'error' => 'Invalid role'], 422);
            return;
        }
        // Check already a member via team_members
        $s = $this->db->prepare(
            'SELECT u.id FROM users u
             INNER JOIN team_members tm ON tm.user_id = u.id
             WHERE u.email=? AND tm.brand_id=? LIMIT 1'
        );
        $s->execute([$email, $brandId]);
        if ($s->fetch()) {
            Response::json(['success' => false, 'error' => 'Already a member'], 422);
            return;
        }
        // Check pending invite
        $s2 = $this->db->prepare(
            'SELECT id FROM team_invites WHERE email=? AND brand_id=? AND status="pending" LIMIT 1'
        );
        $s2->execute([$email, $brandId]);
        if ($s2->fetch()) {
            Response::json(['success' => false, 'error' => 'Invite already sent'], 422);
            return;
        }
        $token = bin2hex(random_bytes(32));
        $this->db->prepare(
            'INSERT INTO team_invites (brand_id,email,role,token,invited_by,expires_at,status,created_at)
             VALUES (?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 7 DAY),"pending",NOW())'
        )->execute([$brandId, $email, $role, hash('sha256', $token), $user['id']]);
        $inviteUrl = (defined('APP_URL') ? APP_URL : '') . '/invite/accept?token=' . $token;
        Response::json(['success' => true, 'message' => 'Invitation sent to ' . $email, 'invite_url' => $inviteUrl]);
    }

    public function updateRole(): void
    {
        Auth::requireAuth();
        $user     = Auth::getCurrentUser();
        $brandId  = $this->getActiveBrandId($user['id']);
        if (!$this->userIsAdmin($user, $brandId)) {
            Response::json(['success' => false, 'error' => 'Insufficient permissions'], 403);
            return;
        }
        $memberId = $this->request->post('member_id', '');
        $newRole  = $this->request->post('role', '');
        if (!in_array($newRole, self::ROLES, true) || $newRole === 'owner') {
            Response::json(['success' => false, 'error' => 'Invalid role'], 422);
            return;
        }
        $s = $this->db->prepare(
            'SELECT role FROM team_members WHERE user_id=? AND brand_id=? LIMIT 1'
        );
        $s->execute([$memberId, $brandId]);
        $m = $s->fetch(\PDO::FETCH_ASSOC);
        if (!$m) {
            Response::json(['success' => false, 'error' => 'Member not found'], 404);
            return;
        }
        if ($m['role'] === 'owner') {
            Response::json(['success' => false, 'error' => 'Cannot change owner role'], 422);
            return;
        }
        $this->db->prepare(
            'UPDATE team_members SET role=?, updated_at=NOW() WHERE user_id=? AND brand_id=?'
        )->execute([$newRole, $memberId, $brandId]);
        Response::json(['success' => true, 'message' => 'Role updated.']);
    }

    public function removeMember(): void
    {
        Auth::requireAuth();
        $user     = Auth::getCurrentUser();
        $brandId  = $this->getActiveBrandId($user['id']);
        if (!$this->userIsAdmin($user, $brandId)) {
            Response::json(['success' => false, 'error' => 'Insufficient permissions'], 403);
            return;
        }
        $memberId = $this->request->post('member_id', '');
        if ($memberId === $user['id']) {
            Response::json(['success' => false, 'error' => 'Cannot remove yourself'], 422);
            return;
        }
        $s = $this->db->prepare(
            'SELECT role FROM team_members WHERE user_id=? AND brand_id=? LIMIT 1'
        );
        $s->execute([$memberId, $brandId]);
        $m = $s->fetch(\PDO::FETCH_ASSOC);
        if (!$m) {
            Response::json(['success' => false, 'error' => 'Not found'], 404);
            return;
        }
        if ($m['role'] === 'owner') {
            Response::json(['success' => false, 'error' => 'Cannot remove owner'], 422);
            return;
        }
        $this->db->prepare(
            'DELETE FROM team_members WHERE user_id=? AND brand_id=?'
        )->execute([$memberId, $brandId]);
        Response::json(['success' => true, 'message' => 'Member removed.']);
    }

    public function getPermissions(): void
    {
        Auth::requireAuth();
        $role = $this->request->get('role', '');
        if (!array_key_exists($role, self::PERMISSIONS)) {
            Response::json(['success' => false, 'error' => 'Unknown role'], 400);
            return;
        }
        Response::json(['success' => true, 'role' => $role, 'permissions' => self::PERMISSIONS[$role]]);
    }

    private function loadTeamMembers(string $brandId): array
    {
        $s = $this->db->prepare(
            'SELECT u.id, u.full_name, u.email, tm.role, tm.created_at AS joined_at, u.is_active
             FROM team_members tm
             INNER JOIN users u ON u.id = tm.user_id
             WHERE tm.brand_id=?
             ORDER BY tm.created_at ASC'
        );
        $s->execute([$brandId]);
        return $s->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function loadPendingInvites(string $brandId): array
    {
        $s = $this->db->prepare(
            'SELECT id, email, role, expires_at, created_at
             FROM team_invites WHERE brand_id=? AND status="pending" AND expires_at>NOW()
             ORDER BY created_at DESC'
        );
        $s->execute([$brandId]);
        return $s->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function userIsAdmin(array $user, string $brandId): bool
    {
        $s = $this->db->prepare(
            'SELECT role FROM team_members WHERE user_id=? AND brand_id=? LIMIT 1'
        );
        $s->execute([$user['id'], $brandId]);
        $r = $s->fetch(\PDO::FETCH_ASSOC);
        return $r && in_array($r['role'], ['owner', 'admin'], true);
    }

    private function getActiveBrandId(string $userId): string
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (string)$_SESSION['active_brand_id'];
        }
        $s = $this->db->prepare(
            'SELECT b.id FROM brands b
             INNER JOIN team_members tm ON tm.brand_id = b.id
             WHERE tm.user_id = ?
             ORDER BY tm.created_at ASC LIMIT 1'
        );
        $s->execute([$userId]);
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['active_brand_id'] = $row['id'];
            return (string)$row['id'];
        }
        return '';
    }
}
