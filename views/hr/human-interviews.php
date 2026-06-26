<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Human Interviews</h1>
        <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Scheduled and completed in-person or video interviews.</p>
    </div>
</div>

<?= flash() ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:1.5rem;">
    <?php
    $counts = $statusCounts ?? [];
    $statuses = [
        ['key'=>'scheduled','label'=>'Scheduled','color'=>'#4f46e5'],
        ['key'=>'completed','label'=>'Completed','color'=>'#10b981'],
        ['key'=>'cancelled','label'=>'Cancelled','color'=>'#ef4444'],
        ['key'=>'no_show','label'=>'No Show','color'=>'#f59e0b'],
    ];
    foreach ($statuses as $st):
    ?>
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:10px;padding:14px;text-align:center;">
        <div style="font-size:1.4rem;font-weight:800;color:<?= $st['color'] ?>;"><?= (int)($counts[$st['key']] ?? 0) ?></div>
        <div style="font-size:.75rem;color:#64748b;margin-top:2px;"><?= $st['label'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Table -->
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
        <thead>
            <tr style="border-bottom:1px solid rgba(79,70,229,.15);background:rgba(79,70,229,.05);">
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Candidate</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Job</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Type</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Interviewer</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Scheduled</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Status</th>
                <th style="padding:14px 16px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($interviews['data'] ?? [] as $iv): ?>
            <tr style="border-bottom:1px solid rgba(79,70,229,.08);">
                <td style="padding:14px 16px;">
                    <div style="font-weight:600;color:#e2e8f0;"><?= htmlspecialchars(($iv['first_name'] ?? '') . ' ' . ($iv['last_name'] ?? '')) ?></div>
                    <div style="font-size:.75rem;color:#475569;"><?= htmlspecialchars($iv['email'] ?? '') ?></div>
                </td>
                <td style="padding:14px 16px;color:#94a3b8;"><?= htmlspecialchars($iv['job_title'] ?? '—') ?></td>
                <td style="padding:14px 16px;color:#94a3b8;text-transform:capitalize;"><?= htmlspecialchars(str_replace('_',' ',$iv['interview_type'] ?? '')) ?></td>
                <td style="padding:14px 16px;color:#94a3b8;"><?= htmlspecialchars($iv['interviewer_name'] ?? '—') ?></td>
                <td style="padding:14px 16px;color:#94a3b8;font-size:.8rem;"><?= $iv['scheduled_at'] ? date('M j, Y g:i A', strtotime($iv['scheduled_at'])) : '—' ?></td>
                <td style="padding:14px 16px;"><?= statusBadge($iv['status'] ?? 'scheduled') ?></td>
                <td style="padding:14px 16px;">
                    <?php if ($iv['status'] === 'scheduled'): ?>
                    <button onclick="openEvaluate(<?= $iv['id'] ?>)" style="padding:5px 12px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:6px;color:#fff;font-size:.75rem;cursor:pointer;">Evaluate</button>
                    <?php elseif ($iv['status'] === 'completed'): ?>
                    <span style="font-size:.8rem;color:#4ade80;font-weight:600;">Score: <?= $iv['overall_score'] !== null ? (int)$iv['overall_score'] : '—' ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($interviews['data'])): ?>
            <tr><td colspan="7" style="padding:3rem;text-align:center;color:#475569;">No human interviews scheduled</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Evaluate Modal -->
<div id="evaluateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center;">
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.2);border-radius:16px;padding:28px;width:100%;max-width:500px;margin:1rem;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Evaluate Interview</h3>
        <form id="evaluateForm" method="POST">
            <input type="hidden" name="interview_id" id="evalInterviewId">
            <div class="form-group">
                <label class="form-label">Overall Score (0–100)</label>
                <input class="form-input" type="number" name="overall_score" min="0" max="100" required>
            </div>
            <div class="form-group">
                <label class="form-label">Communication</label>
                <input class="form-input" type="number" name="communication_score" min="0" max="100">
            </div>
            <div class="form-group">
                <label class="form-label">Technical Knowledge</label>
                <input class="form-input" type="number" name="technical_score" min="0" max="100">
            </div>
            <div class="form-group">
                <label class="form-label">Cultural Fit</label>
                <input class="form-input" type="number" name="culture_fit_score" min="0" max="100">
            </div>
            <div class="form-group">
                <label class="form-label">Recommendation</label>
                <select class="form-input" name="recommendation" required>
                    <option value="">— Select —</option>
                    <option value="strong_yes">Strong Yes</option>
                    <option value="yes">Yes</option>
                    <option value="maybe">Maybe</option>
                    <option value="no">No</option>
                    <option value="strong_no">Strong No</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea class="form-input" name="notes" rows="3" style="resize:none;"></textarea>
            </div>
            <div style="display:flex;gap:10px;margin-top:1rem;">
                <button type="submit" class="btn-primary" style="flex:1;">Save Evaluation</button>
                <button type="button" onclick="closeEvaluate()" style="flex:1;padding:11px;background:transparent;border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#94a3b8;cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEvaluate(id) {
    document.getElementById('evalInterviewId').value = id;
    document.getElementById('evaluateForm').action = '/human-interviews/' + id + '/evaluate';
    document.getElementById('evaluateModal').style.display = 'flex';
}
function closeEvaluate() {
    document.getElementById('evaluateModal').style.display = 'none';
}
</script>
