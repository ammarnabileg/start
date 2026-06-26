<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Offers</h1>
        <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Manage job offers sent to candidates.</p>
    </div>
</div>

<?= flash() ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:1.5rem;">
    <?php
    $oc = $offerCounts ?? [];
    $ostats = [
        ['label'=>'Draft','value'=>$oc['draft'] ?? 0,'color'=>'#64748b'],
        ['label'=>'Sent','value'=>$oc['sent'] ?? 0,'color'=>'#4f46e5'],
        ['label'=>'Accepted','value'=>$oc['accepted'] ?? 0,'color'=>'#10b981'],
        ['label'=>'Declined','value'=>$oc['declined'] ?? 0,'color'=>'#ef4444'],
    ];
    foreach ($ostats as $os):
    ?>
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:10px;padding:14px;text-align:center;">
        <div style="font-size:1.4rem;font-weight:800;color:<?= $os['color'] ?>;"><?= $os['value'] ?></div>
        <div style="font-size:.75rem;color:#64748b;margin-top:2px;"><?= $os['label'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
        <thead>
            <tr style="border-bottom:1px solid rgba(79,70,229,.15);background:rgba(79,70,229,.05);">
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Candidate</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Job</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Salary</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Expires</th>
                <th style="text-align:left;padding:14px 16px;color:#64748b;font-weight:600;">Status</th>
                <th style="padding:14px 16px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($offers['data'] ?? [] as $offer): ?>
            <tr style="border-bottom:1px solid rgba(79,70,229,.08);">
                <td style="padding:14px 16px;">
                    <div style="font-weight:600;color:#e2e8f0;"><?= htmlspecialchars(($offer['first_name'] ?? '') . ' ' . ($offer['last_name'] ?? '')) ?></div>
                    <div style="font-size:.75rem;color:#475569;"><?= htmlspecialchars($offer['email'] ?? '') ?></div>
                </td>
                <td style="padding:14px 16px;color:#94a3b8;"><?= htmlspecialchars($offer['job_title'] ?? '—') ?></td>
                <td style="padding:14px 16px;color:#94a3b8;"><?= $offer['salary_amount'] ? '$' . number_format($offer['salary_amount']) . '/' . htmlspecialchars($offer['salary_period'] ?? 'yr') : '—' ?></td>
                <td style="padding:14px 16px;color:#94a3b8;font-size:.8rem;"><?= $offer['expires_at'] ? date('M j, Y', strtotime($offer['expires_at'])) : '—' ?></td>
                <td style="padding:14px 16px;"><?= statusBadge($offer['status'] ?? 'draft') ?></td>
                <td style="padding:14px 16px;">
                    <div style="display:flex;gap:6px;">
                        <?php if ($offer['status'] === 'draft'): ?>
                        <button onclick="sendOffer(<?= $offer['id'] ?>)" style="padding:5px 12px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:6px;color:#fff;font-size:.75rem;cursor:pointer;">Send</button>
                        <?php endif; ?>
                        <?php if (in_array($offer['status'], ['sent','draft'])): ?>
                        <button onclick="revokeOffer(<?= $offer['id'] ?>)" style="padding:5px 12px;background:transparent;border:1px solid rgba(239,68,68,.3);border-radius:6px;color:#f87171;font-size:.75rem;cursor:pointer;">Revoke</button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($offers['data'])): ?>
            <tr><td colspan="6" style="padding:3rem;text-align:center;color:#475569;">No offers yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function sendOffer(id) {
    if (!confirm('Send this offer to the candidate?')) return;
    api('/offers/' + id + '/send', 'POST', {}).then(function(res) {
        if (res.ok) { showToast('Offer sent!', 'success'); location.reload(); }
        else showToast(res.message || 'Error sending offer', 'error');
    });
}
function revokeOffer(id) {
    if (!confirm('Revoke this offer?')) return;
    api('/offers/' + id + '/revoke', 'POST', {}).then(function(res) {
        if (res.ok) { showToast('Offer revoked', 'success'); location.reload(); }
        else showToast('Error', 'error');
    });
}
</script>
