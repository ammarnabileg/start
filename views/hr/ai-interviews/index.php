<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">AI Interviews</h1>
        <p style="color:#64748b;font-size:.875rem;margin-top:2px;">All AI-conducted interviews and their evaluations.</p>
    </div>
</div>

<?php if (!($hasOpenAI ?? false)): ?>
<div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:12px;padding:16px 20px;margin-bottom:1.5rem;display:flex;align-items:center;gap:12px;">
    <span style="font-size:1.5rem;">⚠️</span>
    <div>
        <div style="font-size:.9rem;font-weight:600;color:#fbbf24;">OpenAI API key not configured</div>
        <div style="font-size:.82rem;color:#92400e;margin-top:2px;">AI interviews require an OpenAI API key. <a href="/settings/ai" style="color:#f59e0b;">Configure in Settings →</a></div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" style="display:flex;gap:10px;margin-bottom:1.5rem;flex-wrap:wrap;">
    <input name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Search by candidate or job…" style="flex:1;min-width:200px;background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
    <select name="status" style="background:#1a1a2e;border:1px solid rgba(79,70,229,.3);border-radius:8px;padding:8px 14px;color:#e2e8f0;font-size:.875rem;outline:none;">
        <option value="">All Statuses</option>
        <option value="pending" <?= ($statusFilter??'')==='pending'?'selected':'' ?>>Pending</option>
        <option value="in_progress" <?= ($statusFilter??'')==='in_progress'?'selected':'' ?>>In Progress</option>
        <option value="completed" <?= ($statusFilter??'')==='completed'?'selected':'' ?>>Completed</option>
        <option value="expired" <?= ($statusFilter??'')==='expired'?'selected':'' ?>>Expired</option>
    </select>
    <button type="submit" style="padding:8px 18px;background:rgba(79,70,229,.15);border:1px solid rgba(79,70,229,.3);border-radius:8px;color:#e2e8f0;font-size:.875rem;cursor:pointer;">Filter</button>
</form>

<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
        <thead>
            <tr style="border-bottom:1px solid rgba(79,70,229,.15);background:rgba(79,70,229,.05);">
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Candidate</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Job</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Score</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Recommendation</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Status</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Date</th>
                <th style="padding:14px 16px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($interviews['data'] ?? [] as $iv): ?>
            <tr style="border-bottom:1px solid rgba(79,70,229,.08);transition:background .15s;" onmouseover="this.style.background='rgba(79,70,229,.04)'" onmouseout="this.style.background='transparent'">
                <td style="padding:14px 16px;">
                    <div style="font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($iv['candidate_name'] ?? $iv['guest_name'] ?? '—') ?></div>
                    <div style="font-size:.75rem;color:#475569;"><?= htmlspecialchars($iv['candidate_email'] ?? $iv['guest_email'] ?? '') ?></div>
                </td>
                <td style="padding:14px 16px;color:#94a3b8;"><?= htmlspecialchars($iv['job_title'] ?? '—') ?></td>
                <td style="padding:14px 16px;">
                    <?php if ($iv['overall_score'] !== null): ?>
                    <span style="font-size:1rem;font-weight:700;color:<?= $iv['overall_score'] >= 70 ? '#4ade80' : ($iv['overall_score'] >= 50 ? '#fbbf24' : '#f87171') ?>;">
                        <?= (int)$iv['overall_score'] ?>%
                    </span>
                    <?php else: ?>
                    <span style="color:#475569;">—</span>
                    <?php endif; ?>
                </td>
                <td style="padding:14px 16px;">
                    <?php
                    $recColors = [
                        'strong_yes' => ['bg'=>'rgba(16,185,129,.1)','color'=>'#4ade80','label'=>'Strong Yes'],
                        'yes' => ['bg'=>'rgba(34,197,94,.1)','color'=>'#86efac','label'=>'Yes'],
                        'maybe' => ['bg'=>'rgba(245,158,11,.1)','color'=>'#fbbf24','label'=>'Maybe'],
                        'no' => ['bg'=>'rgba(239,68,68,.1)','color'=>'#f87171','label'=>'No'],
                        'strong_no' => ['bg'=>'rgba(220,38,38,.1)','color'=>'#fca5a5','label'=>'Strong No'],
                    ];
                    $rec = $recColors[$iv['recommendation'] ?? ''] ?? null;
                    ?>
                    <?php if ($rec): ?>
                    <span style="background:<?= $rec['bg'] ?>;color:<?= $rec['color'] ?>;padding:3px 10px;border-radius:4px;font-size:.75rem;font-weight:600;"><?= $rec['label'] ?></span>
                    <?php else: ?>
                    <span style="color:#475569;font-size:.8rem;">—</span>
                    <?php endif; ?>
                </td>
                <td style="padding:14px 16px;"><?= statusBadge($iv['status'] ?? 'pending') ?></td>
                <td style="padding:14px 16px;color:#475569;font-size:.78rem;"><?= $iv['created_at'] ? date('M j, Y', strtotime($iv['created_at'])) : '—' ?></td>
                <td style="padding:14px 16px;">
                    <a href="/ai-interviews/<?= $iv['id'] ?>" style="padding:5px 12px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:6px;color:#e2e8f0;font-size:.75rem;text-decoration:none;">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($interviews['data'])): ?>
            <tr><td colspan="7" style="padding:3rem;text-align:center;color:#475569;">No AI interviews found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (($interviews['pages'] ?? 1) > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:1.5rem;">
    <?php for ($i = 1; $i <= $interviews['pages']; $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" style="width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:.82rem;text-decoration:none;<?= $i == ($interviews['page'] ?? 1) ? 'background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;' : 'background:#1a1a2e;color:#94a3b8;border:1px solid rgba(79,70,229,.2);' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
