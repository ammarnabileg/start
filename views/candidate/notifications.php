<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">Notifications</h1>
    </div>
    <?php if (!empty($notifications)): ?>
    <form method="POST" action="/c/notifications/read-all">
        <button type="submit" style="padding:8px 16px;background:transparent;border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#94a3b8;font-size:.8rem;cursor:pointer;">Mark all read</button>
    </form>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:4rem;text-align:center;">
    <div style="font-size:3rem;margin-bottom:1rem;">🔔</div>
    <div style="font-size:1rem;font-weight:600;color:#e2e8f0;margin-bottom:.5rem;">No notifications</div>
    <div style="font-size:.875rem;color:#64748b;">You're all caught up!</div>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:8px;">
    <?php foreach ($notifications as $notif): ?>
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,<?= $notif['read_at'] ? '.1' : '.25' ?>);border-radius:10px;padding:16px 20px;display:flex;align-items:flex-start;gap:14px;<?= !$notif['read_at'] ? 'border-left:3px solid #4f46e5;' : '' ?>">
        <div style="font-size:1.3rem;flex-shrink:0;">
            <?php
            $icons = ['interview_sent'=>'🤖','offer_received'=>'🎉','status_change'=>'📋','general'=>'🔔'];
            echo $icons[$notif['type'] ?? 'general'] ?? '🔔';
            ?>
        </div>
        <div style="flex:1;min-width:0;">
            <div style="font-size:.9rem;font-weight:<?= $notif['read_at'] ? '400' : '600' ?>;color:#e2e8f0;line-height:1.5;"><?= htmlspecialchars($notif['message'] ?? '') ?></div>
            <div style="font-size:.72rem;color:#475569;margin-top:4px;"><?= $notif['created_at'] ? ago($notif['created_at']) : '' ?></div>
        </div>
        <?php if (!$notif['read_at']): ?>
        <div style="width:8px;height:8px;border-radius:50%;background:#4f46e5;flex-shrink:0;margin-top:4px;"></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
