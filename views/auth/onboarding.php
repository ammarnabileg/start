<?php
$steps = $steps ?? [];
$user  = $user ?? [];
$type  = $user['type'] ?? 'company';
$completedCount = count(array_filter($steps, fn($s) => $s['completed'] || $s['skipped']));
$totalSteps     = count($steps);
$progress       = $totalSteps > 0 ? round(($completedCount / $totalSteps) * 100) : 0;
?>
<style>
.onb-wrap{max-width:680px;margin:0 auto;padding:2rem 1rem}
.onb-header{text-align:center;margin-bottom:2.5rem}
.onb-title{font-size:1.8rem;font-weight:800;background:linear-gradient(135deg,#4f46e5,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.onb-subtitle{color:#94a3b8;margin-top:.5rem}
.progress-bar-wrap{background:#1a1a2e;border-radius:8px;height:8px;margin:1.5rem 0;overflow:hidden;border:1px solid rgba(79,70,229,.2)}
.progress-bar{height:100%;background:linear-gradient(90deg,#4f46e5,#7c3aed);border-radius:8px;transition:width .4s ease}
.steps-list{display:flex;flex-direction:column;gap:12px}
.step-card{background:#1a1a2e;border:1px solid rgba(79,70,229,.2);border-radius:12px;padding:20px;display:flex;align-items:flex-start;gap:16px;transition:border .2s}
.step-card.done{border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.03)}
.step-card.skipped{opacity:.6}
.step-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;background:rgba(79,70,229,.1)}
.step-icon.done{background:rgba(34,197,94,.1)}
.step-body{flex:1}
.step-title{font-weight:700;color:#e2e8f0;font-size:.95rem}
.step-desc{color:#64748b;font-size:.85rem;margin-top:4px}
.step-actions{display:flex;gap:8px;margin-top:10px}
.btn-sm{padding:6px 14px;border-radius:6px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;transition:all .15s}
.btn-complete{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff}
.btn-skip{background:transparent;color:#64748b;border:1px solid rgba(79,70,229,.2)}
.btn-skip:hover{background:rgba(79,70,229,.1);color:#94a3b8}
.onb-footer{text-align:center;margin-top:2rem}
</style>

<div class="onb-wrap">
    <div class="onb-header">
        <div class="onb-title">Welcome to AI Recruit 🎉</div>
        <div class="onb-subtitle">Complete these steps to get the most out of the platform</div>
        <div style="margin-top:1rem;font-size:.85rem;color:#64748b;"><?= $completedCount ?>/<?= $totalSteps ?> steps done</div>
        <div class="progress-bar-wrap">
            <div class="progress-bar" style="width:<?= $progress ?>%"></div>
        </div>
    </div>

    <div class="steps-list">
        <?php foreach ($steps as $step): ?>
        <?php
        $isDone    = $step['completed'];
        $isSkipped = $step['skipped'];
        $cardClass = $isDone ? 'done' : ($isSkipped ? 'skipped' : '');
        $icon = $step['icon'] ?? '📋';
        ?>
        <div class="step-card <?= $cardClass ?>" id="step-<?= htmlspecialchars($step['step']) ?>">
            <div class="step-icon <?= $isDone ? 'done' : '' ?>">
                <?= $isDone ? '✅' : ($isSkipped ? '⏭️' : htmlspecialchars($icon)) ?>
            </div>
            <div class="step-body">
                <div class="step-title"><?= htmlspecialchars($step['title']) ?></div>
                <div class="step-desc"><?= htmlspecialchars($step['description']) ?></div>
                <?php if (!$isDone && !$isSkipped): ?>
                <div class="step-actions">
                    <button class="btn-sm btn-complete" onclick="completeStep('<?= htmlspecialchars($step['step']) ?>')">Mark Done</button>
                    <button class="btn-sm btn-skip" onclick="skipStep('<?= htmlspecialchars($step['step']) ?>')">Skip</button>
                </div>
                <?php elseif ($isDone): ?>
                <div style="color:#4ade80;font-size:.8rem;margin-top:6px;font-weight:600;">✓ Completed <?= $step['completed_at'] ? date('M j', strtotime($step['completed_at'])) : '' ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="onb-footer">
        <?php $dashUrl = $type === 'super_admin' ? '/super/dashboard' : ($type === 'candidate' ? '/c/dashboard' : '/dashboard'); ?>
        <a href="<?= $dashUrl ?>" style="color:#64748b;font-size:.875rem;">Skip for now → Go to Dashboard</a>
    </div>
</div>

<script>
function completeStep(step) {
    api('/onboarding', 'POST', {action: 'complete_step', step: step}).then(function(res) {
        if (res.ok) {
            var card = document.getElementById('step-' + step);
            if (card) {
                card.classList.add('done');
                var btn = card.querySelector('.step-actions');
                if (btn) btn.innerHTML = '<div style="color:#4ade80;font-size:.8rem;font-weight:600;">✓ Completed</div>';
            }
            if (res.data && res.data.onboarding_done) {
                showToast('All steps done! Redirecting…', 'success');
                setTimeout(function() { window.location.href = '<?= $dashUrl ?>'; }, 1200);
            }
        } else {
            showToast('Error saving. Please try again.', 'error');
        }
    });
}
function skipStep(step) {
    api('/onboarding', 'POST', {action: 'skip', step: step}).then(function(res) {
        if (res.ok) {
            var card = document.getElementById('step-' + step);
            if (card) {
                card.classList.add('skipped');
                var btn = card.querySelector('.step-actions');
                if (btn) btn.innerHTML = '<div style="color:#64748b;font-size:.8rem;">Skipped</div>';
            }
        }
    });
}
</script>
