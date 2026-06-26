<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Reports</h1>
        <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Download and manage your recruitment reports.</p>
    </div>
    <button onclick="document.getElementById('createReportModal').style.display='flex'" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">+ Generate Report</button>
</div>

<?= flash() ?>

<?php if (empty($reports)): ?>
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:4rem;text-align:center;">
    <div style="font-size:3rem;margin-bottom:1rem;">📊</div>
    <div style="font-size:1rem;font-weight:600;color:#e2e8f0;margin-bottom:.5rem;">No reports yet</div>
    <div style="font-size:.875rem;color:#64748b;margin-bottom:1.5rem;">Generate your first recruitment analytics report</div>
    <button onclick="document.getElementById('createReportModal').style.display='flex'" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">Generate Report</button>
</div>
<?php else: ?>
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
        <thead>
            <tr style="border-bottom:1px solid rgba(79,70,229,.15);background:rgba(79,70,229,.05);">
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Report</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Type</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Period</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Generated</th>
                <th style="padding:14px 16px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reports as $report): ?>
            <tr style="border-bottom:1px solid rgba(79,70,229,.08);">
                <td style="padding:14px 16px;font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($report['name']) ?></td>
                <td style="padding:14px 16px;color:#94a3b8;text-transform:capitalize;"><?= htmlspecialchars(str_replace('_',' ',$report['type'] ?? '')) ?></td>
                <td style="padding:14px 16px;color:#94a3b8;font-size:.78rem;">
                    <?= $report['date_from'] ? date('M j', strtotime($report['date_from'])) : '' ?>
                    <?= $report['date_to'] ? ' – ' . date('M j, Y', strtotime($report['date_to'])) : '' ?>
                </td>
                <td style="padding:14px 16px;color:#475569;font-size:.78rem;"><?= $report['created_at'] ? date('M j, Y', strtotime($report['created_at'])) : '—' ?></td>
                <td style="padding:14px 16px;">
                    <a href="/reports/<?= $report['id'] ?>/download" style="padding:5px 12px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:6px;color:#e2e8f0;font-size:.75rem;text-decoration:none;">Download</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Generate Report Modal -->
<div id="createReportModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center;">
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.2);border-radius:16px;padding:28px;width:100%;max-width:460px;margin:1rem;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Generate Report</h3>
        <form method="POST" action="/reports">
            <div class="form-group">
                <label class="form-label">Report Name *</label>
                <input class="form-input" type="text" name="name" required placeholder="e.g. Q1 2024 Recruitment Summary">
            </div>
            <div class="form-group">
                <label class="form-label">Report Type</label>
                <select class="form-input" name="type">
                    <option value="recruitment_summary">Recruitment Summary</option>
                    <option value="ai_interview_analytics">AI Interview Analytics</option>
                    <option value="pipeline_funnel">Pipeline Funnel</option>
                    <option value="offer_acceptance">Offer Acceptance</option>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">From Date</label>
                    <input class="form-input" type="date" name="date_from" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">To Date</label>
                    <input class="form-input" type="date" name="date_to" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:.5rem;">
                <button type="submit" class="btn-primary" style="flex:1;">Generate</button>
                <button type="button" onclick="document.getElementById('createReportModal').style.display='none'" style="flex:1;padding:11px;background:transparent;border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#94a3b8;cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>
