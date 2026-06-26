<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Browse Jobs</h1>
    <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Discover opportunities that match your skills.</p>
</div>

<!-- Search / Filter -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:1.5rem;flex-wrap:wrap;">
    <input name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Job title or keyword…" style="flex:2;min-width:200px;background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
    <input name="location" value="<?= htmlspecialchars($location ?? '') ?>" placeholder="Location…" style="flex:1;min-width:140px;background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
    <select name="type" style="background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
        <option value="">All Types</option>
        <option value="full_time" <?= ($type??'')==='full_time'?'selected':'' ?>>Full Time</option>
        <option value="part_time" <?= ($type??'')==='part_time'?'selected':'' ?>>Part Time</option>
        <option value="contract" <?= ($type??'')==='contract'?'selected':'' ?>>Contract</option>
        <option value="remote" <?= ($type??'')==='remote'?'selected':'' ?>>Remote</option>
    </select>
    <button type="submit" style="padding:8px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">Search</button>
</form>

<div style="font-size:.82rem;color:#64748b;margin-bottom:1rem;"><?= number_format($jobs['total'] ?? 0) ?> jobs found</div>

<?php if (empty($jobs['data'])): ?>
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:4rem;text-align:center;">
    <div style="font-size:3rem;margin-bottom:1rem;">🔍</div>
    <div style="font-size:1rem;font-weight:600;color:#e2e8f0;margin-bottom:.5rem;">No jobs found</div>
    <div style="font-size:.875rem;color:#64748b;">Try adjusting your search or check back later</div>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($jobs['data'] as $job): ?>
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;display:flex;gap:16px;justify-content:space-between;align-items:flex-start;transition:border .15s;" onmouseover="this.style.borderColor='rgba(79,70,229,.3)'" onmouseout="this.style.borderColor='rgba(79,70,229,.15)'">
        <div style="flex:1;min-width:0;">
            <div style="font-size:1rem;font-weight:700;color:#e2e8f0;margin-bottom:4px;"><?= htmlspecialchars($job['title']) ?></div>
            <div style="font-size:.82rem;color:#64748b;margin-bottom:8px;"><?= htmlspecialchars($job['company_name'] ?? '—') ?></div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                <?php if ($job['location']): ?>
                <span style="font-size:.75rem;color:#94a3b8;">📍 <?= htmlspecialchars($job['location']) ?></span>
                <?php endif; ?>
                <?php if ($job['job_type']): ?>
                <span style="font-size:.75rem;background:rgba(79,70,229,.1);color:#818cf8;padding:2px 8px;border-radius:4px;text-transform:capitalize;"><?= str_replace('_',' ',$job['job_type']) ?></span>
                <?php endif; ?>
                <?php if ($job['salary_min']): ?>
                <span style="font-size:.75rem;color:#4ade80;">💰 <?= '$' . number_format($job['salary_min']) . '–$' . number_format($job['salary_max']) ?></span>
                <?php endif; ?>
                <?php if ($job['remote_ok']): ?>
                <span style="font-size:.75rem;background:rgba(16,185,129,.1);color:#4ade80;padding:2px 8px;border-radius:4px;">Remote OK</span>
                <?php endif; ?>
            </div>
            <?php if ($job['description']): ?>
            <div style="font-size:.82rem;color:#64748b;line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= htmlspecialchars(strip_tags($job['description'])) ?></div>
            <?php endif; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;flex-shrink:0;">
            <?php if (in_array($job['id'], $appliedJobIds ?? [])): ?>
            <span style="padding:8px 16px;background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);border-radius:8px;color:#4ade80;font-size:.8rem;font-weight:600;">Applied ✓</span>
            <?php else: ?>
            <a href="/c/jobs/<?= $job['id'] ?>/apply" style="padding:8px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:8px;color:#fff;font-size:.82rem;font-weight:600;text-decoration:none;">Apply Now</a>
            <?php endif; ?>
            <a href="/c/jobs/<?= $job['id'] ?>" style="font-size:.75rem;color:#64748b;text-decoration:none;">View Details</a>
            <div style="font-size:.72rem;color:#475569;"><?= $job['created_at'] ? ago($job['created_at']) : '' ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (($jobs['pages'] ?? 1) > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:1.5rem;">
    <?php for ($i = 1; $i <= $jobs['pages']; $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:.82rem;text-decoration:none;<?= $i == ($jobs['page'] ?? 1) ? 'background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;' : 'background:#1a1a2e;color:#94a3b8;border:1px solid rgba(79,70,229,.2);' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>
