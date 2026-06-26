<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">AI Analytics</h1>
    <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Platform-wide AI usage and key status across all companies.</p>
</div>

<!-- Summary Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:2rem;">
    <?php
    $s = $summary ?? [];
    $cards = [
        ['label'=>'Total AI Interviews','value'=>$s['total_interviews'] ?? 0,'icon'=>'🤖','color'=>'#4f46e5'],
        ['label'=>'Completed','value'=>$s['completed'] ?? 0,'icon'=>'✅','color'=>'#10b981'],
        ['label'=>'Pending','value'=>$s['pending'] ?? 0,'icon'=>'⏳','color'=>'#f59e0b'],
        ['label'=>'Failed','value'=>$s['failed'] ?? 0,'icon'=>'❌','color'=>'#ef4444'],
        ['label'=>'With OpenAI','value'=>$s['tenants_with_openai'] ?? 0,'icon'=>'🔑','color'=>'#8b5cf6'],
        ['label'=>'Without Keys','value'=>$s['tenants_no_keys'] ?? 0,'icon'=>'🚫','color'=>'#64748b'],
    ];
    foreach ($cards as $c):
    ?>
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:16px;">
        <div style="font-size:1.4rem;margin-bottom:8px;"><?= $c['icon'] ?></div>
        <div style="font-size:1.5rem;font-weight:800;color:<?= $c['color'] ?>;"><?= number_format($c['value']) ?></div>
        <div style="font-size:.75rem;color:#64748b;margin-top:2px;"><?= $c['label'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Company AI Key Status Table -->
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid rgba(79,70,229,.1);">
        <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;">Company AI Key Status</h3>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
        <thead>
            <tr style="border-bottom:1px solid rgba(79,70,229,.1);background:rgba(79,70,229,.03);">
                <th style="text-align:left;padding:12px 16px;color:#64748b;font-weight:600;">Company</th>
                <th style="text-align:left;padding:12px 16px;color:#64748b;font-weight:600;">Plan</th>
                <th style="text-align:left;padding:12px 16px;color:#64748b;font-weight:600;">OpenAI Key</th>
                <th style="text-align:left;padding:12px 16px;color:#64748b;font-weight:600;">HeyGen Key</th>
                <th style="text-align:left;padding:12px 16px;color:#64748b;font-weight:600;">AI Interviews</th>
                <th style="text-align:left;padding:12px 16px;color:#64748b;font-weight:600;">Last Used</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($companyAIStatus ?? [] as $row): ?>
            <tr style="border-bottom:1px solid rgba(79,70,229,.06);">
                <td style="padding:12px 16px;">
                    <div style="font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($row['company_name']) ?></div>
                </td>
                <td style="padding:12px 16px;color:#94a3b8;text-transform:capitalize;"><?= htmlspecialchars($row['plan'] ?? 'free') ?></td>
                <td style="padding:12px 16px;">
                    <?php if ($row['openai_key']): ?>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="color:#4ade80;font-size:.8rem;">🟢 Connected</span>
                        <span style="font-size:.72rem;color:#475569;font-family:monospace;"><?= htmlspecialchars(substr($row['openai_key'], 0, 10)) ?>…</span>
                    </div>
                    <?php else: ?>
                    <span style="color:#64748b;font-size:.8rem;">🔴 Not set</span>
                    <?php endif; ?>
                </td>
                <td style="padding:12px 16px;">
                    <?php if ($row['heygen_key']): ?>
                    <span style="color:#4ade80;font-size:.8rem;">🟢 Connected</span>
                    <?php else: ?>
                    <span style="color:#64748b;font-size:.8rem;">🔴 Not set</span>
                    <?php endif; ?>
                </td>
                <td style="padding:12px 16px;color:#94a3b8;"><?= (int)($row['interview_count'] ?? 0) ?></td>
                <td style="padding:12px 16px;color:#475569;font-size:.78rem;"><?= $row['last_interview'] ? date('M j, Y', strtotime($row['last_interview'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($companyAIStatus)): ?>
            <tr><td colspan="6" style="padding:2rem;text-align:center;color:#475569;">No data available</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
