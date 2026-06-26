<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">My Applications</h1>
    <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Track the status of all your job applications.</p>
</div>

<!-- Filter -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:1.5rem;flex-wrap:wrap;">
    <input name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Search jobs…" style="flex:1;min-width:200px;background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
    <select name="status" style="background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
        <option value="">All Statuses</option>
        <?php foreach (['applied','screening','ai_interview','technical_test','human_interview','shortlisted','offer_extended','offer_accepted','hired','rejected'] as $s): ?>
        <option value="<?= $s ?>" <?= ($statusFilter??'')===$s?'selected':'' ?>><?= str_replace('_',' ',ucfirst($s)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" style="padding:8px 18px;background:rgba(79,70,229,.15);border:1px solid rgba(79,70,229,.3);border-radius:8px;color:#e2e8f0;font-size:.875rem;cursor:pointer;">Filter</button>
</form>

<?php if (empty($applications['data'])): ?>
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:4rem;text-align:center;">
    <div style="font-size:3rem;margin-bottom:1rem;">📭</div>
    <div style="font-size:1rem;font-weight:600;color:#e2e8f0;margin-bottom:.5rem;">No applications yet</div>
    <div style="font-size:.875rem;color:#64748b;margin-bottom:1.5rem;">Start applying to jobs to track your progress here</div>
    <a href="/c/jobs" style="display:inline-block;padding:10px 24px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;text-decoration:none;">Browse Jobs</a>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($applications['data'] as $app): ?>
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:18px 20px;display:flex;justify-content:space-between;align-items:center;gap:16px;">
        <div style="flex:1;min-width:0;">
            <div style="font-size:.95rem;font-weight:700;color:#e2e8f0;"><?= htmlspecialchars($app['job_title'] ?? '—') ?></div>
            <div style="font-size:.82rem;color:#64748b;margin-top:2px;"><?= htmlspecialchars($app['company_name'] ?? '—') ?> <?= $app['location'] ? '· ' . htmlspecialchars($app['location']) : '' ?></div>
            <div style="margin-top:8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <?= statusBadge($app['status'] ?? 'applied') ?>
                <span style="font-size:.75rem;color:#475569;"><?= $app['created_at'] ? 'Applied ' . ago($app['created_at']) : '' ?></span>
                <?php if ($app['pending_interview'] ?? null): ?>
                <a href="/interview/<?= htmlspecialchars($app['interview_token']) ?>" style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:4px;color:#fff;font-size:.72rem;font-weight:600;text-decoration:none;">🤖 Interview Pending</a>
                <?php endif; ?>
            </div>
        </div>
        <a href="/c/applications/<?= $app['id'] ?>" style="display:inline-flex;align-items:center;padding:8px 16px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#e2e8f0;font-size:.82rem;font-weight:600;text-decoration:none;white-space:nowrap;">View Details</a>
    </div>
    <?php endforeach; ?>
</div>

<?php if (($applications['pages'] ?? 1) > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:1.5rem;">
    <?php for ($i = 1; $i <= $applications['pages']; $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:.82rem;text-decoration:none;<?= $i == ($applications['page'] ?? 1) ? 'background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;' : 'background:#1a1a2e;color:#94a3b8;border:1px solid rgba(79,70,229,.2);' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>
