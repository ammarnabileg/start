<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Talent Pool</h1>
        <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Organize candidates into groups for future roles.</p>
    </div>
    <button onclick="document.getElementById('createGroupModal').style.display='flex'" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">+ New Group</button>
</div>

<?= flash() ?>

<?php if (empty($groups)): ?>
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:4rem;text-align:center;">
    <div style="font-size:3rem;margin-bottom:1rem;">👥</div>
    <div style="font-size:1rem;font-weight:600;color:#e2e8f0;margin-bottom:.5rem;">No talent groups yet</div>
    <div style="font-size:.85rem;color:#64748b;margin-bottom:1.5rem;">Create groups to organize candidates for future opportunities</div>
    <button onclick="document.getElementById('createGroupModal').style.display='flex'" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">Create First Group</button>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    <?php foreach ($groups as $group): ?>
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
            <div>
                <div style="font-size:1rem;font-weight:700;color:#e2e8f0;"><?= htmlspecialchars($group['name']) ?></div>
                <div style="font-size:.78rem;color:#64748b;margin-top:2px;"><?= (int)($group['member_count'] ?? 0) ?> members</div>
            </div>
            <button onclick="deleteGroup(<?= $group['id'] ?>)" style="background:transparent;border:none;color:#475569;cursor:pointer;font-size:1rem;">✕</button>
        </div>
        <?php if ($group['description']): ?>
        <div style="font-size:.82rem;color:#64748b;margin-bottom:12px;line-height:1.5;"><?= htmlspecialchars($group['description']) ?></div>
        <?php endif; ?>
        <a href="/talent-pool/<?= $group['id'] ?>" style="display:inline-block;padding:6px 14px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:6px;color:#e2e8f0;font-size:.8rem;text-decoration:none;">View Members →</a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create Group Modal -->
<div id="createGroupModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center;">
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.2);border-radius:16px;padding:28px;width:100%;max-width:440px;margin:1rem;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Create Talent Group</h3>
        <form method="POST" action="/talent-pool">
            <div class="form-group">
                <label class="form-label">Group Name *</label>
                <input class="form-input" type="text" name="name" required autofocus placeholder="e.g. Senior Backend Engineers">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-input" name="description" rows="3" style="resize:none;"></textarea>
            </div>
            <div style="display:flex;gap:10px;margin-top:1rem;">
                <button type="submit" class="btn-primary" style="flex:1;">Create Group</button>
                <button type="button" onclick="document.getElementById('createGroupModal').style.display='none'" style="flex:1;padding:11px;background:transparent;border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#94a3b8;cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteGroup(id) {
    if (!confirm('Delete this talent group? Members will not be deleted.')) return;
    api('/talent-pool/' + id, 'DELETE', {}).then(function(res) {
        if (res.ok) location.reload();
        else showToast('Error deleting group', 'error');
    });
}
</script>
