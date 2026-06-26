<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Companies</h1>
        <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Manage all tenant companies on the platform.</p>
    </div>
    <a href="/super/companies/create" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;text-decoration:none;">+ Add Company</a>
</div>

<!-- Search / Filter -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:1.5rem;flex-wrap:wrap;">
    <input name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Search companies…" style="flex:1;min-width:200px;background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
    <select name="plan" style="background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
        <option value="">All Plans</option>
        <option value="free" <?= ($plan??'')==='free'?'selected':'' ?>>Free</option>
        <option value="starter" <?= ($plan??'')==='starter'?'selected':'' ?>>Starter</option>
        <option value="pro" <?= ($plan??'')==='pro'?'selected':'' ?>>Pro</option>
        <option value="enterprise" <?= ($plan??'')==='enterprise'?'selected':'' ?>>Enterprise</option>
    </select>
    <select name="status" style="background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
        <option value="">All Status</option>
        <option value="1" <?= ($status??'')==='1'?'selected':'' ?>>Active</option>
        <option value="0" <?= ($status??'')==='0'?'selected':'' ?>>Inactive</option>
    </select>
    <button type="submit" style="padding:8px 18px;background:rgba(79,70,229,.15);border:1px solid rgba(79,70,229,.3);border-radius:8px;color:#e2e8f0;font-size:.875rem;cursor:pointer;">Filter</button>
</form>

<!-- Table -->
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
        <thead>
            <tr style="border-bottom:1px solid rgba(79,70,229,.15);background:rgba(79,70,229,.05);">
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Company</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Plan</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Users</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Jobs</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">AI Keys</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Status</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Joined</th>
                <th style="padding:14px 16px;color:#64748b;font-weight:600;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tenants['data'] as $t): ?>
            <tr style="border-bottom:1px solid rgba(79,70,229,.08);transition:background .15s;" onmouseover="this.style.background='rgba(79,70,229,.04)'" onmouseout="this.style.background='transparent'">
                <td style="padding:14px 16px;">
                    <div style="font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($t['name']) ?></div>
                    <div style="font-size:.75rem;color:#475569;"><?= htmlspecialchars($t['email'] ?? '') ?></div>
                </td>
                <td style="padding:14px 16px;">
                    <span style="text-transform:capitalize;color:#94a3b8;"><?= htmlspecialchars($t['plan'] ?? 'free') ?></span>
                </td>
                <td style="padding:14px 16px;color:#94a3b8;"><?= (int)($t['user_count'] ?? 0) ?></td>
                <td style="padding:14px 16px;color:#94a3b8;"><?= (int)($t['job_count'] ?? 0) ?></td>
                <td style="padding:14px 16px;">
                    <span style="font-size:.75rem;"><?= $t['has_openai'] ? '🟢 OpenAI' : '🔴 None' ?></span>
                </td>
                <td style="padding:14px 16px;">
                    <?php if ($t['active']): ?>
                    <span style="background:rgba(16,185,129,.1);color:#4ade80;padding:3px 10px;border-radius:4px;font-size:.75rem;font-weight:600;">Active</span>
                    <?php else: ?>
                    <span style="background:rgba(239,68,68,.1);color:#f87171;padding:3px 10px;border-radius:4px;font-size:.75rem;font-weight:600;">Inactive</span>
                    <?php endif; ?>
                </td>
                <td style="padding:14px 16px;color:#475569;font-size:.78rem;"><?= $t['created_at'] ? date('M j, Y', strtotime($t['created_at'])) : '—' ?></td>
                <td style="padding:14px 16px;">
                    <div style="display:flex;gap:6px;justify-content:flex-end;">
                        <a href="/super/companies/<?= $t['id'] ?>" style="padding:5px 12px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:6px;color:#e2e8f0;font-size:.75rem;text-decoration:none;">View</a>
                        <button onclick="toggleTenant(<?= $t['id'] ?>, <?= $t['active'] ? 0 : 1 ?>)" style="padding:5px 12px;background:transparent;border:1px solid rgba(239,68,68,.3);border-radius:6px;color:#f87171;font-size:.75rem;cursor:pointer;"><?= $t['active'] ? 'Disable' : 'Enable' ?></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tenants['data'])): ?>
            <tr><td colspan="8" style="padding:3rem;text-align:center;color:#475569;">No companies found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if (($tenants['pages'] ?? 1) > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:1.5rem;">
    <?php for ($i = 1; $i <= $tenants['pages']; $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:.82rem;text-decoration:none;<?= $i == ($tenants['page'] ?? 1) ? 'background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;' : 'background:#1a1a2e;color:#94a3b8;border:1px solid rgba(79,70,229,.2);' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<script>
function toggleTenant(id, active) {
    api('/super/companies/' + id + '/toggle', 'POST', {active: active}).then(function(res) {
        if (res.ok) location.reload();
        else showToast('Error updating company', 'error');
    });
}
</script>
