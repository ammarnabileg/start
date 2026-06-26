<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Roles & Permissions</h1>
        <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Control what each role can access and do.</p>
    </div>
</div>

<?= flash() ?>

<div style="display:grid;grid-template-columns:240px 1fr;gap:20px;">
    <!-- Role List -->
    <div>
        <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;overflow:hidden;">
            <?php foreach ($roles ?? [] as $i => $role): ?>
            <button onclick="showRole(<?= $i ?>)" id="roleTab<?= $i ?>" style="width:100%;padding:14px 16px;background:<?= $i === 0 ? 'rgba(79,70,229,.15)' : 'transparent' ?>;border:none;border-bottom:1px solid rgba(79,70,229,.1);color:<?= $i === 0 ? '#e2e8f0' : '#94a3b8' ?>;font-size:.875rem;text-align:left;cursor:pointer;display:flex;justify-content:space-between;align-items:center;">
                <span style="font-weight:600;"><?= htmlspecialchars($role['name']) ?></span>
                <span style="font-size:.72rem;background:rgba(79,70,229,.15);padding:2px 8px;border-radius:4px;color:#6366f1;"><?= $role['user_count'] ?? 0 ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Permissions Panel -->
    <div>
        <?php foreach ($roles ?? [] as $i => $role): ?>
        <div id="rolePanel<?= $i ?>" style="<?= $i !== 0 ? 'display:none;' : '' ?>">
            <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
                    <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;"><?= htmlspecialchars($role['name']) ?> Permissions</h3>
                    <div style="font-size:.78rem;color:#64748b;"><?= count($role['permissions'] ?? []) ?> permissions granted</div>
                </div>
                <form method="POST" action="/roles/<?= $role['id'] ?>/permissions">
                    <?php foreach ($allPermissions ?? [] as $group => $perms): ?>
                    <div style="margin-bottom:1.25rem;">
                        <div style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;margin-bottom:8px;"><?= htmlspecialchars($group) ?></div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;">
                            <?php foreach ($perms as $perm): ?>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:6px 10px;border-radius:6px;border:1px solid rgba(79,70,229,.1);transition:background .15s;" onmouseover="this.style.background='rgba(79,70,229,.07)'" onmouseout="this.style.background='transparent'">
                                <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm['key']) ?>" <?= in_array($perm['key'], $role['permissions'] ?? []) ? 'checked' : '' ?> style="width:14px;height:14px;accent-color:#4f46e5;">
                                <span style="font-size:.8rem;color:#e2e8f0;"><?= htmlspecialchars($perm['label']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top:1rem;">
                        <button type="submit" class="btn-primary" style="padding:10px 24px;">Save Permissions</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
var totalRoles = <?= count($roles ?? []) ?>;
function showRole(idx) {
    for (var i = 0; i < totalRoles; i++) {
        var tab = document.getElementById('roleTab' + i);
        var panel = document.getElementById('rolePanel' + i);
        if (tab) { tab.style.background = i === idx ? 'rgba(79,70,229,.15)' : 'transparent'; tab.style.color = i === idx ? '#e2e8f0' : '#94a3b8'; }
        if (panel) panel.style.display = i === idx ? 'block' : 'none';
    }
}
</script>
