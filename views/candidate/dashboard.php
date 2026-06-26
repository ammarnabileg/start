<div style="margin-bottom:2rem;">
    <h1 style="font-size:1.5rem;font-weight:800;color:#f1f5f9;">Welcome back, <?= htmlspecialchars($candidate['first_name'] ?? 'there') ?> 👋</h1>
    <p style="color:#64748b;margin-top:.25rem;">Here's your job search overview.</p>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:2rem;">
    <?php
    $stats = [
        ['label'=>'Total Applications','value'=>$stats['total_applications'] ?? 0,'icon'=>'📋','color'=>'#4f46e5'],
        ['label'=>'In Progress','value'=>$stats['in_progress'] ?? 0,'icon'=>'⏳','color'=>'#f59e0b'],
        ['label'=>'AI Interviews','value'=>$stats['ai_interviews'] ?? 0,'icon'=>'🤖','color'=>'#8b5cf6'],
        ['label'=>'Offers Received','value'=>$stats['offers'] ?? 0,'icon'=>'🎉','color'=>'#10b981'],
    ];
    foreach ($stats as $s):
    ?>
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:18px;">
        <div style="font-size:1.5rem;margin-bottom:8px;"><?= $s['icon'] ?></div>
        <div style="font-size:1.6rem;font-weight:800;color:<?= $s['color'] ?>;"><?= $s['value'] ?></div>
        <div style="font-size:.78rem;color:#64748b;margin-top:2px;"><?= $s['label'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <!-- Recent Applications -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;">Recent Applications</h3>
            <a href="/c/applications" style="font-size:.78rem;color:#4f46e5;text-decoration:none;">View all →</a>
        </div>
        <?php if (empty($recentApplications)): ?>
        <div style="text-align:center;padding:2rem 0;color:#475569;">
            <div style="font-size:2rem;margin-bottom:.5rem;">📭</div>
            <div style="font-size:.85rem;">No applications yet</div>
            <a href="/c/jobs" style="display:inline-block;margin-top:.75rem;font-size:.82rem;color:#4f46e5;text-decoration:none;">Browse jobs →</a>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($recentApplications as $app): ?>
            <a href="/c/applications/<?= $app['id'] ?>" style="display:block;text-decoration:none;padding:12px;background:rgba(79,70,229,.05);border-radius:8px;border:1px solid rgba(79,70,229,.1);transition:border .15s;" onmouseover="this.style.borderColor='rgba(79,70,229,.3)'" onmouseout="this.style.borderColor='rgba(79,70,229,.1)'">
                <div style="font-size:.88rem;font-weight:600;color:#e2e8f0;margin-bottom:4px;"><?= htmlspecialchars($app['job_title'] ?? '—') ?></div>
                <div style="font-size:.78rem;color:#64748b;"><?= htmlspecialchars($app['company_name'] ?? '—') ?></div>
                <div style="margin-top:6px;"><?= statusBadge($app['status'] ?? 'applied') ?></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pending Interviews -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;">Pending Interviews</h3>
        </div>
        <?php if (empty($pendingInterviews)): ?>
        <div style="text-align:center;padding:2rem 0;color:#475569;">
            <div style="font-size:2rem;margin-bottom:.5rem;">🎤</div>
            <div style="font-size:.85rem;">No pending interviews</div>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($pendingInterviews as $iv): ?>
            <div style="padding:12px;background:rgba(245,158,11,.05);border:1px solid rgba(245,158,11,.2);border-radius:8px;">
                <div style="font-size:.88rem;font-weight:600;color:#e2e8f0;margin-bottom:4px;"><?= htmlspecialchars($iv['job_title'] ?? '—') ?></div>
                <div style="font-size:.78rem;color:#64748b;margin-bottom:6px;"><?= htmlspecialchars($iv['company_name'] ?? '—') ?></div>
                <a href="/interview/<?= htmlspecialchars($iv['token']) ?>" style="display:inline-block;padding:5px 14px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:6px;color:#fff;font-size:.78rem;font-weight:600;text-decoration:none;">Start Interview →</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div style="margin-top:20px;background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
    <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:16px;">Quick Actions</h3>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="/c/jobs" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:8px;color:#fff;font-size:.85rem;font-weight:600;text-decoration:none;">🔍 Browse Jobs</a>
        <a href="/c/profile" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#e2e8f0;font-size:.85rem;font-weight:600;text-decoration:none;">👤 Update Profile</a>
        <a href="/c/applications" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#e2e8f0;font-size:.85rem;font-weight:600;text-decoration:none;">📋 My Applications</a>
        <a href="/c/offers" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#e2e8f0;font-size:.85rem;font-weight:600;text-decoration:none;">🎉 My Offers</a>
    </div>
</div>
