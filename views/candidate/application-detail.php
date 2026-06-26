<div style="margin-bottom:1.5rem;">
    <a href="/c/applications" style="font-size:.78rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:.5rem;">← Back to Applications</a>
    <h1 style="font-size:1.25rem;font-weight:800;color:#f1f5f9;"><?= htmlspecialchars($application['job_title'] ?? '—') ?></h1>
    <div style="font-size:.875rem;color:#64748b;margin-top:2px;"><?= htmlspecialchars($application['company_name'] ?? '—') ?></div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;">
    <!-- Main -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <!-- Status Timeline -->
        <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
            <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:1rem;">Application Status</h3>
            <?php
            $stages = ['applied','screening','ai_interview','technical_test','human_interview','shortlisted','offer_extended','hired'];
            $currentStage = $application['status'] ?? 'applied';
            $currentIdx = array_search($currentStage, $stages);
            if ($currentIdx === false) $currentIdx = 0;
            ?>
            <div style="display:flex;align-items:center;gap:0;overflow-x:auto;padding-bottom:4px;">
                <?php foreach ($stages as $i => $stage): ?>
                <?php $active = $i <= $currentIdx; $current = $i === $currentIdx; ?>
                <div style="display:flex;align-items:center;min-width:80px;">
                    <div style="text-align:center;flex:1;">
                        <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:.7rem;font-weight:700;<?= $current ? 'background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;' : ($active ? 'background:rgba(79,70,229,.2);color:#818cf8;' : 'background:#0f0f1a;border:1px solid rgba(79,70,229,.2);color:#475569;') ?>"><?= $active ? '✓' : ($i + 1) ?></div>
                        <div style="font-size:.62rem;color:<?= $active ? '#94a3b8' : '#475569' ?>;margin-top:4px;text-align:center;line-height:1.2;"><?= str_replace('_',' ',ucfirst($stage)) ?></div>
                    </div>
                    <?php if ($i < count($stages) - 1): ?>
                    <div style="height:2px;flex:1;background:<?= $i < $currentIdx ? 'linear-gradient(90deg,#4f46e5,#7c3aed)' : 'rgba(79,70,229,.15)' ?>;margin-bottom:20px;"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- AI Interview Card -->
        <?php if (!empty($aiInterview)): ?>
        <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
            <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:1rem;">🤖 AI Interview</h3>
            <?php if ($aiInterview['status'] === 'completed'): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:14px;background:rgba(16,185,129,.06);border-radius:8px;border:1px solid rgba(16,185,129,.15);">
                <span style="font-size:1.5rem;">✅</span>
                <div>
                    <div style="font-weight:600;color:#4ade80;font-size:.9rem;">Interview Completed</div>
                    <div style="font-size:.78rem;color:#64748b;margin-top:2px;">Completed <?= $aiInterview['updated_at'] ? ago($aiInterview['updated_at']) : '' ?></div>
                </div>
            </div>
            <?php elseif ($aiInterview['status'] === 'pending'): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px;background:rgba(245,158,11,.06);border-radius:8px;border:1px solid rgba(245,158,11,.15);">
                <div>
                    <div style="font-weight:600;color:#fbbf24;font-size:.9rem;">Interview Pending</div>
                    <div style="font-size:.78rem;color:#64748b;margin-top:2px;">Click to start your AI interview</div>
                </div>
                <a href="/interview/<?= htmlspecialchars($aiInterview['token']) ?>" style="padding:8px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:8px;color:#fff;font-size:.82rem;font-weight:600;text-decoration:none;">Start Now →</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Offer Card -->
        <?php if (!empty($offer)): ?>
        <div style="background:#1a1a2e;border:1px solid rgba(16,185,129,.2);border-radius:12px;padding:20px;">
            <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:1rem;">🎉 Job Offer</h3>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php if ($offer['salary_amount']): ?>
                <div style="display:flex;justify-content:space-between;"><span style="color:#64748b;font-size:.85rem;">Salary</span><span style="color:#e2e8f0;font-weight:600;"><?= '$' . number_format($offer['salary_amount']) ?>/<?= htmlspecialchars($offer['salary_period'] ?? 'yr') ?></span></div>
                <?php endif; ?>
                <?php if ($offer['start_date']): ?>
                <div style="display:flex;justify-content:space-between;"><span style="color:#64748b;font-size:.85rem;">Start Date</span><span style="color:#e2e8f0;font-weight:600;"><?= date('M j, Y', strtotime($offer['start_date'])) ?></span></div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;"><span style="color:#64748b;font-size:.85rem;">Expires</span><span style="color:#e2e8f0;font-weight:600;"><?= $offer['expires_at'] ? date('M j, Y', strtotime($offer['expires_at'])) : '—' ?></span></div>
                <?php if ($offer['status'] === 'sent'): ?>
                <div style="display:flex;gap:10px;margin-top:6px;">
                    <form method="POST" action="/c/offers/<?= $offer['id'] ?>/accept" style="flex:1;"><button type="submit" class="btn-primary" style="width:100%;padding:10px;">Accept Offer</button></form>
                    <form method="POST" action="/c/offers/<?= $offer['id'] ?>/reject" style="flex:1;"><button type="submit" style="width:100%;padding:10px;background:transparent;border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#f87171;cursor:pointer;font-size:.875rem;font-weight:600;">Decline</button></form>
                </div>
                <?php elseif ($offer['status'] === 'accepted'): ?>
                <div style="text-align:center;color:#4ade80;font-weight:600;font-size:.9rem;">✓ You accepted this offer</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div style="display:flex;flex-direction:column;gap:14px;">
        <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:18px;">
            <h4 style="font-size:.85rem;font-weight:700;color:#f1f5f9;margin-bottom:12px;">Job Details</h4>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <?php if ($application['location']): ?>
                <div style="display:flex;gap:8px;font-size:.82rem;"><span style="color:#64748b;">📍</span><span style="color:#94a3b8;"><?= htmlspecialchars($application['location']) ?></span></div>
                <?php endif; ?>
                <?php if ($application['job_type']): ?>
                <div style="display:flex;gap:8px;font-size:.82rem;"><span style="color:#64748b;">💼</span><span style="color:#94a3b8;text-transform:capitalize;"><?= htmlspecialchars(str_replace('_',' ',$application['job_type'])) ?></span></div>
                <?php endif; ?>
                <?php if ($application['salary_min']): ?>
                <div style="display:flex;gap:8px;font-size:.82rem;"><span style="color:#64748b;">💰</span><span style="color:#94a3b8;"><?= '$' . number_format($application['salary_min']) . ' – $' . number_format($application['salary_max']) ?></span></div>
                <?php endif; ?>
            </div>
            <a href="/c/jobs/<?= $application['job_id'] ?>" style="display:block;margin-top:12px;text-align:center;padding:8px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:6px;color:#e2e8f0;font-size:.8rem;text-decoration:none;">View Job Posting</a>
        </div>
        <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:18px;">
            <h4 style="font-size:.85rem;font-weight:700;color:#f1f5f9;margin-bottom:8px;">Applied</h4>
            <div style="font-size:.82rem;color:#64748b;"><?= $application['created_at'] ? date('M j, Y', strtotime($application['created_at'])) : '—' ?></div>
        </div>
    </div>
</div>
