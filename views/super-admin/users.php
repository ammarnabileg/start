<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">All Users</h1>
        <p style="color:#64748b;font-size:.875rem;margin-top:2px;">View and manage all platform users.</p>
    </div>
</div>

<!-- Search -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:1.5rem;flex-wrap:wrap;">
    <input name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Search users…" style="flex:1;min-width:200px;background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
    <select name="type" style="background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
        <option value="">All Types</option>
        <option value="super_admin" <?= ($type??'')==='super_admin'?'selected':'' ?>>Super Admin</option>
        <option value="company" <?= ($type??'')==='company'?'selected':'' ?>>Company</option>
        <option value="candidate" <?= ($type??'')==='candidate'?'selected':'' ?>>Candidate</option>
    </select>
    <button type="submit" style="padding:8px 18px;background:rgba(79,70,229,.15);border:1px solid rgba(79,70,229,.3);border-radius:8px;color:#e2e8f0;font-size:.875rem;cursor:pointer;">Filter</button>
</form>

<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
        <thead>
            <tr style="border-bottom:1px solid rgba(79,70,229,.15);background:rgba(79,70,229,.05);">
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">User</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Type</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Company</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Status</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Joined</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users['data'] ?? [] as $u): ?>
            <tr style="border-bottom:1px solid rgba(79,70,229,.08);">
                <td style="padding:14px 16px;">
                    <div style="font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                    <div style="font-size:.75rem;color:#475569;"><?= htmlspecialchars($u['email']) ?></div>
                </td>
                <td style="padding:14px 16px;">
                    <?php
                    $typeColors = ['super_admin'=>'#8b5cf6','company'=>'#4f46e5','candidate'=>'#10b981'];
                    $tc = $typeColors[$u['type'] ?? 'company'] ?? '#64748b';
                    ?>
                    <span style="background:<?= $tc ?>22;color:<?= $tc ?>;padding:3px 10px;border-radius:4px;font-size:.75rem;font-weight:600;text-transform:capitalize;"><?= htmlspecialchars(str_replace('_',' ',$u['type'] ?? '')) ?></span>
                </td>
                <td style="padding:14px 16px;color:#94a3b8;"><?= htmlspecialchars($u['tenant_name'] ?? '—') ?></td>
                <td style="padding:14px 16px;">
                    <?php if (!($u['deleted_at'] ?? null)): ?>
                    <span style="background:rgba(16,185,129,.1);color:#4ade80;padding:3px 10px;border-radius:4px;font-size:.75rem;font-weight:600;">Active</span>
                    <?php else: ?>
                    <span style="background:rgba(239,68,68,.1);color:#f87171;padding:3px 10px;border-radius:4px;font-size:.75rem;font-weight:600;">Deleted</span>
                    <?php endif; ?>
                </td>
                <td style="padding:14px 16px;color:#475569;font-size:.78rem;"><?= $u['created_at'] ? date('M j, Y', strtotime($u['created_at'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users['data'])): ?>
            <tr><td colspan="5" style="padding:3rem;text-align:center;color:#475569;">No users found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (($users['pages'] ?? 1) > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:1.5rem;">
    <?php for ($i = 1; $i <= $users['pages']; $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:.82rem;text-decoration:none;<?= $i == ($users['page'] ?? 1) ? 'background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;' : 'background:#1a1a2e;color:#94a3b8;border:1px solid rgba(79,70,229,.2);' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
