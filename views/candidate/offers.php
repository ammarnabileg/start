<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">My Offers</h1>
    <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Job offers received from companies.</p>
</div>

<?= flash() ?>

<?php if (empty($offers)): ?>
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:4rem;text-align:center;">
    <div style="font-size:3rem;margin-bottom:1rem;">🎉</div>
    <div style="font-size:1rem;font-weight:600;color:#e2e8f0;margin-bottom:.5rem;">No offers yet</div>
    <div style="font-size:.875rem;color:#64748b;">Keep applying and interviewing — offers will appear here</div>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px;">
    <?php foreach ($offers as $offer): ?>
    <div style="background:#1a1a2e;border:1px solid rgba(<?= $offer['status'] === 'sent' ? '79,70,229' : '79,70,229' ?>,.15);border-radius:12px;padding:22px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px;">
            <div>
                <div style="font-size:1rem;font-weight:700;color:#e2e8f0;"><?= htmlspecialchars($offer['job_title'] ?? '—') ?></div>
                <div style="font-size:.82rem;color:#64748b;margin-top:2px;"><?= htmlspecialchars($offer['company_name'] ?? '—') ?></div>
            </div>
            <?= statusBadge($offer['status'] ?? 'sent') ?>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px;">
            <?php if ($offer['salary_amount']): ?>
            <div style="background:#0f0f1a;border-radius:8px;padding:10px 14px;">
                <div style="font-size:.72rem;color:#64748b;margin-bottom:2px;">Salary</div>
                <div style="font-size:.95rem;font-weight:700;color:#4ade80;"><?= '$' . number_format($offer['salary_amount']) ?><span style="font-size:.72rem;color:#64748b;">/ <?= htmlspecialchars($offer['salary_period'] ?? 'yr') ?></span></div>
            </div>
            <?php endif; ?>
            <?php if ($offer['start_date']): ?>
            <div style="background:#0f0f1a;border-radius:8px;padding:10px 14px;">
                <div style="font-size:.72rem;color:#64748b;margin-bottom:2px;">Start Date</div>
                <div style="font-size:.875rem;font-weight:600;color:#e2e8f0;"><?= date('M j, Y', strtotime($offer['start_date'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($offer['expires_at']): ?>
            <div style="background:#0f0f1a;border-radius:8px;padding:10px 14px;">
                <div style="font-size:.72rem;color:#64748b;margin-bottom:2px;">Expires</div>
                <div style="font-size:.875rem;font-weight:600;color:<?= strtotime($offer['expires_at']) < time() ? '#f87171' : '#e2e8f0' ?>;"><?= date('M j, Y', strtotime($offer['expires_at'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($offer['notes']): ?>
        <div style="font-size:.82rem;color:#64748b;line-height:1.6;margin-bottom:14px;padding:12px;background:#0f0f1a;border-radius:8px;"><?= nl2br(htmlspecialchars($offer['notes'])) ?></div>
        <?php endif; ?>
        <?php if ($offer['status'] === 'sent'): ?>
        <div style="display:flex;gap:10px;">
            <form method="POST" action="/c/offers/<?= $offer['id'] ?>/accept">
                <button type="submit" class="btn-primary" style="padding:10px 24px;">Accept Offer</button>
            </form>
            <form method="POST" action="/c/offers/<?= $offer['id'] ?>/reject">
                <button type="submit" style="padding:10px 22px;background:transparent;border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#f87171;cursor:pointer;font-size:.875rem;font-weight:600;">Decline</button>
            </form>
        </div>
        <?php elseif ($offer['status'] === 'accepted'): ?>
        <div style="color:#4ade80;font-weight:600;font-size:.875rem;">✓ You accepted this offer</div>
        <?php elseif ($offer['status'] === 'declined'): ?>
        <div style="color:#f87171;font-size:.875rem;">You declined this offer</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
