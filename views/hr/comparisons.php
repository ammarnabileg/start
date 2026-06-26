<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Candidate Comparisons</h1>
        <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Side-by-side comparison of candidates for a position.</p>
    </div>
    <button onclick="document.getElementById('createCompModal').style.display='flex'" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">+ New Comparison</button>
</div>

<?= flash() ?>

<?php if (empty($comparisons)): ?>
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:4rem;text-align:center;">
    <div style="font-size:3rem;margin-bottom:1rem;">⚖️</div>
    <div style="font-size:1rem;font-weight:600;color:#e2e8f0;margin-bottom:.5rem;">No comparisons yet</div>
    <div style="font-size:.85rem;color:#64748b;margin-bottom:1.5rem;">Create a comparison to evaluate candidates side-by-side</div>
    <button onclick="document.getElementById('createCompModal').style.display='flex'" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">Create Comparison</button>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($comparisons as $comp): ?>
    <a href="/comparisons/<?= $comp['id'] ?>" style="display:block;text-decoration:none;background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:18px 20px;transition:border .15s;" onmouseover="this.style.borderColor='rgba(79,70,229,.35)'" onmouseout="this.style.borderColor='rgba(79,70,229,.15)'">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:.95rem;font-weight:700;color:#e2e8f0;"><?= htmlspecialchars($comp['title']) ?></div>
                <div style="font-size:.8rem;color:#64748b;margin-top:2px;">Job: <?= htmlspecialchars($comp['job_title'] ?? '—') ?> · <?= (int)($comp['candidate_count'] ?? 0) ?> candidates</div>
            </div>
            <div style="font-size:.78rem;color:#475569;"><?= $comp['created_at'] ? date('M j, Y', strtotime($comp['created_at'])) : '' ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create Comparison Modal -->
<div id="createCompModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center;">
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.2);border-radius:16px;padding:28px;width:100%;max-width:520px;margin:1rem;max-height:90vh;overflow-y:auto;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Create Candidate Comparison</h3>
        <form method="POST" action="/comparisons">
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input class="form-input" type="text" name="title" required placeholder="e.g. Senior Dev Finalists">
            </div>
            <div class="form-group">
                <label class="form-label">Job Position</label>
                <select class="form-input" name="job_id">
                    <option value="">— Any —</option>
                    <?php foreach ($jobs ?? [] as $job): ?>
                    <option value="<?= $job['id'] ?>"><?= htmlspecialchars($job['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Select Candidates (2–10) *</label>
                <div style="max-height:220px;overflow-y:auto;background:#0f0f1a;border:1px solid rgba(79,70,229,.2);border-radius:8px;padding:10px;">
                    <?php foreach ($candidates ?? [] as $cand): ?>
                    <label style="display:flex;align-items:center;gap:8px;padding:6px 4px;cursor:pointer;">
                        <input type="checkbox" name="candidate_ids[]" value="<?= $cand['id'] ?>" style="width:14px;height:14px;accent-color:#4f46e5;">
                        <span style="font-size:.85rem;color:#e2e8f0;"><?= htmlspecialchars(($cand['first_name'] ?? '') . ' ' . ($cand['last_name'] ?? '')) ?></span>
                        <span style="font-size:.75rem;color:#475569;"><?= htmlspecialchars($cand['email'] ?? '') ?></span>
                    </label>
                    <?php endforeach; ?>
                    <?php if (empty($candidates)): ?>
                    <div style="text-align:center;padding:1rem;color:#475569;font-size:.85rem;">No candidates with AI interviews available</div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:1rem;">
                <button type="submit" class="btn-primary" style="flex:1;">Create Comparison</button>
                <button type="button" onclick="document.getElementById('createCompModal').style.display='none'" style="flex:1;padding:11px;background:transparent;border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#94a3b8;cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>
