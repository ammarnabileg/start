<?php ob_start(); ?>
<?php
// Variables passed from DashboardController::team()
// $brand, $brandId, $members, $csrf

$roleBadgeMap = [
    'owner'  => 'badge-purple',
    'admin'  => 'badge-error',
    'editor' => 'badge-warning',
    'viewer' => '',
];
$roleLabels = [
    'owner'  => 'Owner',
    'admin'  => 'Admin',
    'editor' => 'Editor',
    'viewer' => 'Viewer',
];

$roles = ['admin', 'editor', 'viewer'];

function teamInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="font-size:1.6rem;font-weight:700;margin-bottom:0.25rem;">Team Management 👥</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage your team members, roles and permissions</p>
    </div>
    <?php if ($brandId): ?>
    <button class="btn btn-primary" onclick="openInviteModal()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Invite Member
    </button>
    <?php endif; ?>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <?php
    $ownerCount  = 0;
    $adminCount  = 0;
    $editorCount = 0;
    $viewerCount = 0;
    foreach ($members as $m) {
        match($m['role'] ?? '') {
            'owner'  => $ownerCount++,
            'admin'  => $adminCount++,
            'editor' => $editorCount++,
            'viewer' => $viewerCount++,
            default  => null,
        };
    }
    $statCards = [
        ['label' => 'Total Members', 'val' => count($members), 'icon' => '👥', 'color' => 'var(--text-primary)'],
        ['label' => 'Owners',        'val' => $ownerCount,     'icon' => '👑', 'color' => '#c4b5fd'],
        ['label' => 'Admins',        'val' => $adminCount,     'icon' => '🛡️', 'color' => 'var(--red)'],
        ['label' => 'Editors',       'val' => $editorCount,    'icon' => '✏️', 'color' => 'var(--yellow)'],
    ];
    foreach ($statCards as $sc):
    ?>
    <div class="glass-card" style="padding:1rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.25rem;">
            <div style="font-size:0.7rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;"><?= $sc['label'] ?></div>
            <span style="font-size:1.1rem;"><?= $sc['icon'] ?></span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:<?= $sc['color'] ?>;"><?= $sc['val'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!$brandId): ?>
<div class="glass-card" style="text-align:center;padding:4rem 2rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">🏢</div>
    <h3 style="font-size:1rem;margin-bottom:0.5rem;color:var(--text-secondary);">No brand set up yet</h3>
    <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:1.25rem;">Create a brand workspace to add team members.</p>
    <a href="/brands/create" class="btn btn-primary">Create Brand</a>
</div>

<?php else: ?>

<!-- Members Table -->
<div class="glass-card" style="padding:0;margin-bottom:1.25rem;">
    <div style="padding:1.25rem;border-bottom:1px solid var(--glass-border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
        <h3 style="font-size:1rem;font-weight:600;">Team Members</h3>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <input type="text" class="form-input" placeholder="Search members…" style="max-width:220px;" oninput="filterMembers(this.value)">
            <select class="form-select" style="max-width:140px;" onchange="filterByRole(this.value)">
                <option value="">All Roles</option>
                <?php foreach (['owner' => 'Owner', 'admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'] as $rv => $rl): ?>
                <option value="<?= $rv ?>"><?= $rl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php if (empty($members)): ?>
    <div style="text-align:center;padding:3rem 2rem;color:var(--text-muted);">
        <div style="font-size:3rem;margin-bottom:1rem;">👥</div>
        <h3 style="font-size:0.95rem;font-weight:600;margin-bottom:0.4rem;color:var(--text-secondary);">No team members yet</h3>
        <p style="font-size:0.82rem;margin-bottom:1rem;">Invite your first team member to collaborate on your brand.</p>
        <button class="btn btn-primary" style="font-size:0.82rem;" onclick="openInviteModal()">📨 Invite Member</button>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;" id="membersTable">
            <thead>
                <tr>
                    <?php foreach (['Member', 'Role', 'Status', 'Joined', 'Actions'] as $th): ?>
                    <th style="padding:0.7rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);"><?= $th ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m):
                    $initials = teamInitials($m['full_name'] ?? $m['username'] ?? 'U');
                    $badge    = $roleBadgeMap[$m['role'] ?? 'viewer'] ?? '';
                    $roleLabel = $roleLabels[$m['role'] ?? 'viewer'] ?? ucfirst($m['role'] ?? '—');
                    $isActive  = (bool)($m['is_active'] ?? true);
                ?>
                <tr class="member-row" data-name="<?= strtolower(htmlspecialchars($m['full_name'] ?? '')) ?>" data-role="<?= htmlspecialchars($m['role'] ?? '') ?>" style="border-bottom:1px solid rgba(255,255,255,0.04);transition:background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
                    <td style="padding:0.85rem 1rem;">
                        <div style="display:flex;align-items:center;gap:0.75rem;">
                            <div style="width:36px;height:36px;border-radius:50%;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;font-size:0.78rem;font-weight:700;color:#fff;flex-shrink:0;">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                            <div>
                                <div style="font-size:0.875rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($m['full_name'] ?? $m['username'] ?? '—') ?></div>
                                <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($m['email'] ?? '—') ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <span class="badge <?= $badge ?>">
                            <?= htmlspecialchars($roleLabel) ?>
                        </span>
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <div style="display:flex;align-items:center;gap:0.35rem;">
                            <span style="width:7px;height:7px;border-radius:50%;background:<?= $isActive ? 'var(--green)' : 'var(--text-muted)' ?>;display:inline-block;"></span>
                            <span style="font-size:0.8rem;color:<?= $isActive ? 'var(--green-light)' : 'var(--text-muted)' ?>;"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                        </div>
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <span style="font-size:0.8rem;color:var(--text-muted);"><?= isset($m['joined_at']) ? date('M j, Y', strtotime($m['joined_at'])) : '—' ?></span>
                    </td>
                    <td style="padding:0.85rem 1rem;">
                        <div style="display:flex;gap:0.35rem;flex-wrap:wrap;">
                            <?php if (($m['role'] ?? '') !== 'owner'): ?>
                            <button class="btn btn-ghost btn-sm" onclick="openEditRoleModal('<?= htmlspecialchars((string)$m['member_id']) ?>','<?= htmlspecialchars($m['role'] ?? '') ?>')">✏️ Role</button>
                            <button class="btn btn-sm" style="background:rgba(239,68,68,.12);color:var(--red);border:1px solid rgba(239,68,68,.2);font-size:0.75rem;padding:0.3rem 0.6rem;border-radius:var(--radius-sm);cursor:pointer;" onclick="removeMember('<?= htmlspecialchars((string)$m['member_id']) ?>','<?= htmlspecialchars($m['full_name'] ?? 'this member') ?>')">Remove</button>
                            <?php else: ?>
                            <span class="badge badge-purple" style="font-size:0.68rem;">Owner</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Role Permissions Reference -->
<div class="glass-card" style="margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;font-weight:600;margin-bottom:1rem;">🎭 Role Permissions Reference</h3>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
            <thead>
                <tr>
                    <th style="padding:0.6rem 0.75rem;text-align:left;border-bottom:1px solid var(--glass-border);color:var(--text-muted);font-size:0.72rem;text-transform:uppercase;">Permission</th>
                    <?php foreach (['Owner', 'Admin', 'Editor', 'Viewer'] as $role): ?>
                    <th style="padding:0.6rem 0.75rem;text-align:center;border-bottom:1px solid var(--glass-border);color:var(--text-muted);font-size:0.72rem;text-transform:uppercase;"><?= $role ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $perms = [
                    ['View Dashboard',    true,  true,  true,  true],
                    ['Create Content',    true,  true,  true,  false],
                    ['Publish Content',   true,  true,  false, false],
                    ['View Analytics',    true,  true,  true,  true],
                    ['Community Replies', true,  true,  true,  false],
                    ['Manage Campaigns',  true,  true,  false, false],
                    ['Manage Team',       true,  true,  false, false],
                    ['Brand Settings',    true,  false, false, false],
                ];
                foreach ($perms as $perm):
                    $permName = array_shift($perm);
                ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
                    <td style="padding:0.6rem 0.75rem;color:var(--text-secondary);"><?= htmlspecialchars($permName) ?></td>
                    <?php foreach ($perm as $has): ?>
                    <td style="padding:0.6rem 0.75rem;text-align:center;font-size:0.95rem;">
                        <?= $has ? '<span style="color:var(--green-light);">✓</span>' : '<span style="color:var(--text-muted);">—</span>' ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; // brandId ?>

<!-- Invite Modal -->
<div id="inviteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;">
    <div class="glass-card" style="width:100%;max-width:480px;margin:1rem;padding:0;">
        <div class="modal-header">
            <h3>📨 Invite Team Member</h3>
            <button class="modal-close" onclick="closeInviteModal()">×</button>
        </div>
        <div style="padding:1.25rem;">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-input" id="inviteEmail" placeholder="colleague@company.com">
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select class="form-select" id="inviteRole">
                    <option value="admin">Admin — Full access except billing</option>
                    <option value="editor" selected>Editor — Create and publish content</option>
                    <option value="viewer">Viewer — Read-only access</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Personal Message (optional)</label>
                <textarea class="form-textarea" id="inviteMessage" rows="2" placeholder="Hey! I'd like you to join our SociAI OS workspace…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeInviteModal()">Cancel</button>
            <button class="btn btn-primary" onclick="sendInvite()">📨 Send Invitation</button>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div id="editRoleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;">
    <div class="glass-card" style="width:100%;max-width:400px;margin:1rem;padding:0;">
        <div class="modal-header">
            <h3>✏️ Change Role</h3>
            <button class="modal-close" onclick="closeEditRoleModal()">×</button>
        </div>
        <div style="padding:1.25rem;">
            <input type="hidden" id="editMemberId">
            <div class="form-group">
                <label class="form-label">New Role</label>
                <select class="form-select" id="editRoleSelect">
                    <option value="admin">Admin</option>
                    <option value="editor">Editor</option>
                    <option value="viewer">Viewer</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeEditRoleModal()">Cancel</button>
            <button class="btn btn-primary" onclick="saveRole()">Save Role</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:none;">
    <div id="toastMsg" style="background:var(--navy-mid,#1e1e3f);border:1px solid var(--glass-border);border-left:3px solid var(--green-light);border-radius:var(--radius-md);padding:.75rem 1.25rem;font-size:.85rem;box-shadow:0 4px 24px rgba(0,0,0,.4);"></div>
</div>

<script>
const CSRF = <?= json_encode($csrf ?? '') ?>;

function showToast(msg, ok = true) {
    const m = document.getElementById('toastMsg');
    if (!m) return;
    m.textContent = msg;
    m.style.borderLeftColor = ok ? 'var(--green-light)' : '#f87171';
    const t = document.getElementById('toast');
    t.style.display = 'block';
    clearTimeout(t._tid);
    t._tid = setTimeout(() => t.style.display = 'none', 4000);
}

async function apiPost(url, data) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(data),
    });
    return r.json();
}

function openInviteModal()  { document.getElementById('inviteModal').style.display  = 'flex'; }
function closeInviteModal() { document.getElementById('inviteModal').style.display  = 'none'; }
function openEditRoleModal(id, role) {
    document.getElementById('editMemberId').value = id;
    document.getElementById('editRoleSelect').value = role;
    document.getElementById('editRoleModal').style.display = 'flex';
}
function closeEditRoleModal() { document.getElementById('editRoleModal').style.display = 'none'; }

['inviteModal','editRoleModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});

async function sendInvite() {
    const email   = document.getElementById('inviteEmail').value.trim();
    const role    = document.getElementById('inviteRole').value;
    const message = document.getElementById('inviteMessage').value.trim();
    if (!email) { showToast('Email address is required', false); return; }
    const d = await apiPost('/api/team/invite', { email, role, message });
    closeInviteModal();
    showToast(d.success ? '📨 Invitation sent!' : (d.error || 'Failed to send invitation'), d.success !== false);
    if (d.success) setTimeout(() => location.reload(), 1000);
}

async function saveRole() {
    const id   = document.getElementById('editMemberId').value;
    const role = document.getElementById('editRoleSelect').value;
    const d    = await apiPost('/api/team/update-role', { member_id: id, role });
    closeEditRoleModal();
    showToast(d.success ? 'Role updated!' : (d.error || 'Failed'), d.success !== false);
    if (d.success) setTimeout(() => location.reload(), 500);
}

async function removeMember(id, name) {
    if (!confirm('Remove ' + name + ' from the team?')) return;
    const d = await apiPost('/api/team/remove', { member_id: id });
    showToast(d.success ? 'Member removed' : (d.error || 'Failed'), d.success !== false);
    if (d.success) setTimeout(() => location.reload(), 500);
}

function filterMembers(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.member-row').forEach(row => {
        const name = row.dataset.name || '';
        row.style.display = name.includes(q) ? '' : 'none';
    });
}

function filterByRole(role) {
    document.querySelectorAll('.member-row').forEach(row => {
        const r = row.dataset.role || '';
        row.style.display = !role || r === role ? '' : 'none';
    });
}
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
