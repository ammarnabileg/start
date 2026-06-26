<?php
$statusColors = [
    'pending' => ['bg'=>'rgba(245,158,11,.1)','color'=>'#fbbf24'],
    'in_progress' => ['bg'=>'rgba(79,70,229,.1)','color'=>'#818cf8'],
    'completed' => ['bg'=>'rgba(16,185,129,.1)','color'=>'#4ade80'],
    'expired' => ['bg'=>'rgba(100,116,139,.1)','color'=>'#94a3b8'],
];
$sc = $statusColors[$interview['status'] ?? 'pending'] ?? $statusColors['pending'];
?>

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;gap:12px;">
    <div>
        <a href="/ai-interviews" style="font-size:.78rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:.5rem;">← Back to AI Interviews</a>
        <h1 style="font-size:1.25rem;font-weight:800;color:#f1f5f9;"><?= htmlspecialchars($interview['candidate_name'] ?? $interview['guest_name'] ?? 'Interview') ?></h1>
        <div style="font-size:.85rem;color:#64748b;margin-top:2px;"><?= htmlspecialchars($interview['job_title'] ?? '—') ?></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <span style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;padding:5px 14px;border-radius:6px;font-size:.8rem;font-weight:600;text-transform:capitalize;"><?= str_replace('_',' ',$interview['status'] ?? '') ?></span>
        <?php if ($interview['overall_score'] !== null): ?>
        <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.2);border-radius:8px;padding:8px 16px;text-align:center;">
            <div style="font-size:1.5rem;font-weight:800;color:<?= $interview['overall_score'] >= 70 ? '#4ade80' : ($interview['overall_score'] >= 50 ? '#fbbf24' : '#f87171') ?>;"><?= (int)$interview['overall_score'] ?>%</div>
            <div style="font-size:.7rem;color:#64748b;">Overall Score</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- AI Evaluation -->
    <?php if ($interview['status'] === 'completed'): ?>
    <div style="grid-column:1/-1;">
        <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;margin-bottom:16px;">
            <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:1rem;">AI Recommendation</h3>
            <?php
            $recColors = [
                'strong_yes' => ['color'=>'#4ade80','label'=>'Strong Yes ✅'],
                'yes' => ['color'=>'#86efac','label'=>'Yes 👍'],
                'maybe' => ['color'=>'#fbbf24','label'=>'Maybe 🤔'],
                'no' => ['color'=>'#f87171','label'=>'No 👎'],
                'strong_no' => ['color'=>'#fca5a5','label'=>'Strong No ❌'],
            ];
            $rec = $recColors[$interview['recommendation'] ?? ''] ?? null;
            ?>
            <?php if ($rec): ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                <span style="font-size:1.5rem;font-weight:800;color:<?= $rec['color'] ?>;"><?= $rec['label'] ?></span>
            </div>
            <?php endif; ?>
            <?php if ($interview['recommendation_summary']): ?>
            <div style="font-size:.875rem;color:#94a3b8;line-height:1.7;"><?= nl2br(htmlspecialchars($interview['recommendation_summary'])) ?></div>
            <?php endif; ?>

            <?php if ($interview['strengths'] || $interview['weaknesses']): ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
                <?php if ($interview['strengths']): ?>
                <div>
                    <div style="font-size:.78rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Strengths</div>
                    <?php foreach (json_decode($interview['strengths'], true) ?? [] as $s): ?>
                    <div style="font-size:.82rem;color:#e2e8f0;display:flex;align-items:center;gap:6px;margin-bottom:4px;"><span style="color:#4ade80;">✓</span> <?= htmlspecialchars($s) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($interview['weaknesses']): ?>
                <div>
                    <div style="font-size:.78rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Areas to Improve</div>
                    <?php foreach (json_decode($interview['weaknesses'], true) ?? [] as $w): ?>
                    <div style="font-size:.82rem;color:#e2e8f0;display:flex;align-items:center;gap:6px;margin-bottom:4px;"><span style="color:#f87171;">✗</span> <?= htmlspecialchars($w) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Skill Scores -->
        <?php if (!empty($skillScores)): ?>
        <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;margin-bottom:16px;">
            <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:1rem;">Skill Assessment</h3>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($skillScores as $skill): ?>
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:.85rem;color:#e2e8f0;"><?= htmlspecialchars($skill['skill_name']) ?></span>
                        <span style="font-size:.85rem;font-weight:700;color:<?= $skill['score'] >= 70 ? '#4ade80' : ($skill['score'] >= 50 ? '#fbbf24' : '#f87171') ?>;"><?= (int)$skill['score'] ?>%</span>
                    </div>
                    <div style="height:6px;background:#0f0f1a;border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= (int)$skill['score'] ?>%;background:<?= $skill['score'] >= 70 ? 'linear-gradient(90deg,#10b981,#4ade80)' : ($skill['score'] >= 50 ? 'linear-gradient(90deg,#d97706,#fbbf24)' : 'linear-gradient(90deg,#dc2626,#f87171)') ?>;border-radius:3px;transition:width .5s ease;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Red Flags -->
        <?php if (!empty($redFlags)): ?>
        <div style="background:#1a1a2e;border:1px solid rgba(239,68,68,.2);border-radius:12px;padding:20px;margin-bottom:16px;">
            <h3 style="font-size:.95rem;font-weight:700;color:#f87171;margin-bottom:1rem;">⚠️ Red Flags Detected</h3>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($redFlags as $rf): ?>
                <div style="background:rgba(239,68,68,.06);border-radius:8px;padding:12px 14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-size:.85rem;font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($rf['flag_type']) ?></span>
                        <span style="font-size:.72rem;padding:2px 8px;border-radius:4px;font-weight:600;<?= $rf['severity'] === 'high' ? 'background:rgba(239,68,68,.2);color:#f87171;' : 'background:rgba(245,158,11,.15);color:#fbbf24;' ?>"><?= ucfirst($rf['severity']) ?></span>
                    </div>
                    <div style="font-size:.82rem;color:#94a3b8;"><?= htmlspecialchars($rf['description']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Chat Transcript -->
<div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;">
    <h3 style="font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:1rem;">Interview Transcript</h3>
    <div style="display:flex;flex-direction:column;gap:12px;max-height:500px;overflow-y:auto;">
        <?php foreach ($messages ?? [] as $msg): ?>
        <div style="display:flex;gap:10px;<?= $msg['role'] === 'candidate' ? 'flex-direction:row-reverse;' : '' ?>">
            <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:#0f0f1a;border:1px solid rgba(79,70,229,.2);"><?= $msg['role'] === 'interviewer' ? '🤖' : '👤' ?></div>
            <div style="max-width:70%;">
                <div style="padding:10px 14px;border-radius:12px;font-size:.85rem;line-height:1.6;<?= $msg['role'] === 'interviewer' ? 'background:#16213e;border:1px solid rgba(79,70,229,.15);' : 'background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;' ?>"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                <div style="font-size:.7rem;color:#475569;margin-top:3px;<?= $msg['role'] === 'candidate' ? 'text-align:right;' : '' ?>"><?= $msg['created_at'] ? date('g:i A', strtotime($msg['created_at'])) : '' ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($messages)): ?>
        <div style="text-align:center;padding:2rem;color:#475569;">No messages yet.</div>
        <?php endif; ?>
    </div>
</div>
