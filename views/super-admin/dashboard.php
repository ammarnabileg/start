<div style="margin-bottom:2rem;">
    <h1 style="font-size:1.5rem;font-weight:800;color:#f1f5f9;">Super Admin Dashboard</h1>
    <p style="color:#64748b;margin-top:.25rem;">Platform-wide overview and management.</p>
</div>

<!-- Platform Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:2rem;">
    <?php
    $pstats = [
        ['label'=>'Total Companies','value'=>$stats['total_tenants'] ?? 0,'icon'=>'🏢','color'=>'#4f46e5','link'=>'/super/companies'],
        ['label'=>'Active Companies','value'=>$stats['active_tenants'] ?? 0,'icon'=>'✅','color'=>'#10b981','link'=>'/super/companies'],
        ['label'=>'Total Users','value'=>$stats['total_users'] ?? 0,'icon'=>'👥','color'=>'#8b5cf6','link'=>'/super/users'],
        ['label'=>'Total Jobs','value'=>$stats['total_jobs'] ?? 0,'icon'=>'💼','color'=>'#f59e0b','link'=>null],
        ['label'=>'AI Interviews','value'=>$stats['total_ai_interviews'] ?? 0,'icon'=>'🤖','color'=>'#06b6d4','link'=>null],
        ['label'=>'This Month','value'=>$stats['new_tenants_month'] ?? 0,'icon'=>'📈','color'=>'#ec4899','link'=>null],
    ];
    foreach ($pstats as $s):
    ?>
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:18px;<?= $s['link'] ? 'cursor:pointer;' : '' ?>" <?= $s['link'] ? "onclick=\"location.href='{$s['link']}'\"" : '' ?>>
        <div style="font-size:1.4rem;margin-bottom:8px;"><?= $s['icon'] ?></div>
        <div style="font-size:1.7rem;font-weight:800;color:<?= $s['color'] ?>;"><?= number_format($s['value']) ?></div>
        <div style="font-size:.78rem;color:#64748b;margin-top:2px;"><?= $s['label'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- Recent Companies -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;">Recent Companies</h3>
            <a href="/super/companies" style="font-size:.78rem;color:#4f46e5;text-decoration:none;">View all →</a>
        </div>
        <?php if (empty($recentTenants)): ?>
        <div style="text-align:center;padding:1.5rem 0;color:#475569;font-size:.85rem;">No companies yet</div>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
            <thead>
                <tr style="border-bottom:1px solid rgba(79,70,229,.1);">
                    <th style="text-align:left;padding:6px 0;color:#64748b;font-weight:600;">Company</th>
                    <th style="text-align:left;padding:6px 0;color:#64748b;font-weight:600;">Plan</th>
                    <th style="text-align:left;padding:6px 0;color:#64748b;font-weight:600;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentTenants as $t): ?>
                <tr style="border-bottom:1px solid rgba(79,70,229,.06);" onclick="location.href='/super/companies/<?= $t['id'] ?>'" style="cursor:pointer;">
                    <td style="padding:10px 0;">
                        <div style="font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($t['name']) ?></div>
                        <div style="color:#475569;font-size:.75rem;"><?= htmlspecialchars($t['email'] ?? '') ?></div>
                    </td>
                    <td style="padding:10px 0;color:#94a3b8;text-transform:capitalize;"><?= htmlspecialchars($t['plan'] ?? 'free') ?></td>
                    <td style="padding:10px 0;">
                        <?php if ($t['active']): ?>
                        <span style="background:rgba(16,185,129,.1);color:#4ade80;padding:2px 8px;border-radius:4px;font-size:.72rem;font-weight:600;">Active</span>
                        <?php else: ?>
                        <span style="background:rgba(239,68,68,.1);color:#f87171;padding:2px 8px;border-radius:4px;font-size:.72rem;font-weight:600;">Inactive</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- AI Key Status -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;">AI Key Status</h3>
            <a href="/super/ai-analytics" style="font-size:.78rem;color:#4f46e5;text-decoration:none;">Analytics →</a>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="padding:14px;background:rgba(79,70,229,.06);border-radius:8px;border:1px solid rgba(79,70,229,.1);">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:.85rem;font-weight:600;color:#e2e8f0;">OpenAI Configured</div>
                        <div style="font-size:.75rem;color:#64748b;margin-top:2px;">Companies with API keys</div>
                    </div>
                    <div style="font-size:1.5rem;font-weight:800;color:#10b981;"><?= (int)($stats['tenants_with_openai'] ?? 0) ?></div>
                </div>
            </div>
            <div style="padding:14px;background:rgba(79,70,229,.06);border-radius:8px;border:1px solid rgba(79,70,229,.1);">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:.85rem;font-weight:600;color:#e2e8f0;">HeyGen Configured</div>
                        <div style="font-size:.75rem;color:#64748b;margin-top:2px;">Companies with video AI</div>
                    </div>
                    <div style="font-size:1.5rem;font-weight:800;color:#8b5cf6;"><?= (int)($stats['tenants_with_heygen'] ?? 0) ?></div>
                </div>
            </div>
            <div style="padding:14px;background:rgba(239,68,68,.04);border-radius:8px;border:1px solid rgba(239,68,68,.1);">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:.85rem;font-weight:600;color:#e2e8f0;">No AI Keys</div>
                        <div style="font-size:.75rem;color:#64748b;margin-top:2px;">Companies without AI</div>
                    </div>
                    <div style="font-size:1.5rem;font-weight:800;color:#f87171;"><?= max(0, ($stats['active_tenants'] ?? 0) - ($stats['tenants_with_openai'] ?? 0)) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
    <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:16px;">Quick Actions</h3>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="/super/companies/create" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:8px;color:#fff;font-size:.85rem;font-weight:600;text-decoration:none;">🏢 Add Company</a>
        <a href="/super/users" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#e2e8f0;font-size:.85rem;font-weight:600;text-decoration:none;">👥 Manage Users</a>
        <a href="/super/settings" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#e2e8f0;font-size:.85rem;font-weight:600;text-decoration:none;">⚙️ Platform Settings</a>
        <a href="/super/ai-analytics" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#e2e8f0;font-size:.85rem;font-weight:600;text-decoration:none;">📊 AI Analytics</a>
    </div>
</div>
