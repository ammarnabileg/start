<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Team Members</h1>
        <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Manage your team's access to this platform.</p>
    </div>
    <button onclick="document.getElementById('createUserModal').style.display='flex'" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">+ Invite Member</button>
</div>

<?= flash() ?>

<?php if (isset($limitWarning)): ?>
<div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:10px;padding:14px 18px;margin-bottom:1.5rem;font-size:.85rem;color:#fbbf24;">
    ⚠️ <?= htmlspecialchars($limitWarning) ?>
</div>
<?php endif; ?>

<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
        <thead>
            <tr style="border-bottom:1px solid rgba(79,70,229,.15);background:rgba(79,70,229,.05);">
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Member</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Role</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Status</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Joined</th>
                <th style="padding:14px 16px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users ?? [] as $u): ?>
            <tr style="border-bottom:1px solid rgba(79,70,229,.08);">
                <td style="padding:14px 16px;">
                    <div style="font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                    <div style="font-size:.75rem;color:#475569;"><?= htmlspecialchars($u['email']) ?></div>
                </td>
                <td style="padding:14px 16px;">
                    <select onchange="changeRole(<?= $u['id'] ?>, this.value)" style="background:#0f0f1a;border:1px solid rgba(79,70,229,.2);border-radius:6px;padding:4px 10px;color:#e2e8f0;font-size:.8rem;outline:none;">
                        <?php foreach ($roles ?? [] as $role): ?>
                        <option value="<?= $role['id'] ?>" <?= in_array($role['id'], $u['role_ids'] ?? []) ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="padding:14px 16px;">
                    <?php if (!$u['deleted_at']): ?>
                    <span style="background:rgba(16,185,129,.1);color:#4ade80;padding:3px 10px;border-radius:4px;font-size:.75rem;font-weight:600;">Active</span>
                    <?php else: ?>
                    <span style="background:rgba(239,68,68,.1);color:#f87171;padding:3px 10px;border-radius:4px;font-size:.75rem;font-weight:600;">Inactive</span>
                    <?php endif; ?>
                </td>
                <td style="padding:14px 16px;color:#475569;font-size:.78rem;"><?= $u['created_at'] ? date('M j, Y', strtotime($u['created_at'])) : '—' ?></td>
                <td style="padding:14px 16px;">
                    <button onclick="toggleUser(<?= $u['id'] ?>, <?= $u['deleted_at'] ? 1 : 0 ?>)" style="padding:5px 12px;background:transparent;border:1px solid rgba(79,70,229,.2);border-radius:6px;color:#94a3b8;font-size:.75rem;cursor:pointer;"><?= $u['deleted_at'] ? 'Restore' : 'Deactivate' ?></button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
            <tr><td colspan="5" style="padding:3rem;text-align:center;color:#475569;">No team members yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Create User Modal -->
<div id="createUserModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center;">
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.2);border-radius:16px;padding:28px;width:100%;max-width:480px;margin:1rem;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Invite Team Member</h3>
        <form method="POST" action="/users">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label">First Name *</label>
                    <input class="form-input" type="text" name="first_name" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label class="form-label">Last Name *</label>
                    <input class="form-input" type="text" name="last_name" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input class="form-input" type="email" name="email" required>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select class="form-input" name="role_id">
                    <?php foreach ($roles ?? [] as $role): ?>
                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Temporary Password *</label>
                <input class="form-input" type="password" name="password" minlength="8" required>
            </div>
            <div style="display:flex;gap:10px;margin-top:1rem;">
                <button type="submit" class="btn-primary" style="flex:1;">Create Member</button>
                <button type="button" onclick="document.getElementById('createUserModal').style.display='none'" style="flex:1;padding:11px;background:transparent;border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#94a3b8;cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleUser(id, restore) {
    var msg = restore ? 'Restore this user?' : 'Deactivate this user?';
    if (!confirm(msg)) return;
    api('/users/' + id + '/toggle', 'POST', {}).then(function(res) {
        if (res.ok) location.reload();
        else showToast('Error', 'error');
    });
}
function changeRole(userId, roleId) {
    api('/users/' + userId + '/roles', 'POST', {role_id: roleId}).then(function(res) {
        if (res.ok) showToast('Role updated', 'success');
        else showToast('Error updating role', 'error');
    });
}
</script>
