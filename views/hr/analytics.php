<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Analytics</h1>
    <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Recruitment performance metrics and trends.</p>
</div>

<!-- Date Range -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:1.5rem;align-items:center;">
    <input type="date" name="from" value="<?= htmlspecialchars($from ?? date('Y-m-01')) ?>" style="background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
    <span style="color:#64748b;">to</span>
    <input type="date" name="to" value="<?= htmlspecialchars($to ?? date('Y-m-d')) ?>" style="background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
    <button type="submit" style="padding:8px 18px;background:rgba(79,70,229,.15);border:1px solid rgba(79,70,229,.3);border-radius:8px;color:#e2e8f0;font-size:.875rem;cursor:pointer;">Apply</button>
</form>

<!-- Summary Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:2rem;">
    <?php
    $as = $analyticsStats ?? [];
    $metricCards = [
        ['label'=>'Total Applications','value'=>$as['total_applications'] ?? 0,'icon'=>'📋','color'=>'#4f46e5'],
        ['label'=>'AI Interviews','value'=>$as['ai_interviews'] ?? 0,'icon'=>'🤖','color'=>'#8b5cf6'],
        ['label'=>'Human Interviews','value'=>$as['human_interviews'] ?? 0,'icon'=>'👥','color'=>'#06b6d4'],
        ['label'=>'Offers Sent','value'=>$as['offers_sent'] ?? 0,'icon'=>'📄','color'=>'#f59e0b'],
        ['label'=>'Hired','value'=>$as['hired'] ?? 0,'icon'=>'🎉','color'=>'#10b981'],
        ['label'=>'Conversion Rate','value'=>($as['conversion_rate'] ?? 0) . '%','icon'=>'📈','color'=>'#ec4899'],
    ];
    foreach ($metricCards as $mc):
    ?>
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:16px;">
        <div style="font-size:1.4rem;margin-bottom:8px;"><?= $mc['icon'] ?></div>
        <div style="font-size:1.5rem;font-weight:800;color:<?= $mc['color'] ?>;"><?= $mc['value'] ?></div>
        <div style="font-size:.75rem;color:#64748b;margin-top:2px;"><?= $mc['label'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <!-- Applications by Status -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
        <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:1rem;">Applications by Status</h3>
        <?php foreach ($byStatus ?? [] as $row): ?>
        <?php
        $total = array_sum(array_column($byStatus, 'count'));
        $pct = $total > 0 ? round(($row['count'] / $total) * 100) : 0;
        ?>
        <div style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                <span style="font-size:.82rem;color:#94a3b8;text-transform:capitalize;"><?= str_replace('_',' ',htmlspecialchars($row['status'])) ?></span>
                <span style="font-size:.82rem;color:#e2e8f0;font-weight:600;"><?= $row['count'] ?></span>
            </div>
            <div style="height:5px;background:#0f0f1a;border-radius:3px;overflow:hidden;">
                <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,#4f46e5,#7c3aed);border-radius:3px;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Top Jobs by Applications -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
        <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:1rem;">Top Jobs by Applications</h3>
        <?php foreach ($topJobs ?? [] as $job): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(79,70,229,.08);">
            <div>
                <div style="font-size:.85rem;font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($job['title']) ?></div>
                <div style="font-size:.75rem;color:#64748b;"><?= (int)($job['hired_count'] ?? 0) ?> hired</div>
            </div>
            <span style="font-size:.9rem;font-weight:700;color:#4f46e5;"><?= $job['application_count'] ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topJobs)): ?>
        <div style="text-align:center;padding:1.5rem;color:#475569;font-size:.85rem;">No data available</div>
        <?php endif; ?>
    </div>
</div>
