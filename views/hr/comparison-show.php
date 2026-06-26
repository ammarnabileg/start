<div style="margin-bottom:1.5rem;">
    <a href="/comparisons" style="font-size:.78rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:.5rem;">← Back to Comparisons</a>
    <h1 style="font-size:1.25rem;font-weight:800;color:#f1f5f9;"><?= htmlspecialchars($comparison['title']) ?></h1>
    <div style="font-size:.85rem;color:#64748b;margin-top:2px;">Job: <?= htmlspecialchars($comparison['job_title'] ?? '—') ?> · <?= count($candidates ?? []) ?> candidates</div>
</div>

<!-- Comparison Grid -->
<div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem;min-width:600px;">
        <thead>
            <tr>
                <th style="text-align:left;padding:12px 16px;background:#1a1a2e;border:1px solid rgba(79,70,229,.1);color:#64748b;font-weight:600;width:160px;">Metric</th>
                <?php foreach ($candidates as $cand): ?>
                <th style="padding:12px 16px;background:#1a1a2e;border:1px solid rgba(79,70,229,.1);text-align:center;">
                    <div style="font-weight:700;color:#e2e8f0;"><?= htmlspecialchars(($cand['first_name'] ?? '') . ' ' . ($cand['last_name'] ?? '')) ?></div>
                    <div style="font-size:.72rem;color:#64748b;margin-top:2px;"><?= htmlspecialchars($cand['email'] ?? '') ?></div>
                </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <!-- Overall Score -->
            <tr>
                <td style="padding:12px 16px;background:#0f0f1a;border:1px solid rgba(79,70,229,.08);color:#64748b;font-weight:600;">Overall Score</td>
                <?php foreach ($candidates as $cand): ?>
                <td style="padding:12px 16px;background:#0f0f1a;border:1px solid rgba(79,70,229,.08);text-align:center;">
                    <?php $score = $cand['overall_score'] ?? null; ?>
                    <?php if ($score !== null): ?>
                    <span style="font-size:1.2rem;font-weight:800;color:<?= $score >= 70 ? '#4ade80' : ($score >= 50 ? '#fbbf24' : '#f87171') ?>;"><?= (int)$score ?>%</span>
                    <?php else: ?>
                    <span style="color:#475569;">—</span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <!-- Recommendation -->
            <tr>
                <td style="padding:12px 16px;border:1px solid rgba(79,70,229,.08);color:#64748b;font-weight:600;">Recommendation</td>
                <?php foreach ($candidates as $cand): ?>
                <td style="padding:12px 16px;border:1px solid rgba(79,70,229,.08);text-align:center;">
                    <?php
                    $recLabels = ['strong_yes'=>'Strong Yes ✅','yes'=>'Yes 👍','maybe'=>'Maybe 🤔','no'=>'No 👎','strong_no'=>'Strong No ❌'];
                    echo $cand['recommendation'] ? htmlspecialchars($recLabels[$cand['recommendation']] ?? $cand['recommendation']) : '<span style="color:#475569;">—</span>';
                    ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <!-- Experience -->
            <tr>
                <td style="padding:12px 16px;background:#0f0f1a;border:1px solid rgba(79,70,229,.08);color:#64748b;font-weight:600;">Experience</td>
                <?php foreach ($candidates as $cand): ?>
                <td style="padding:12px 16px;background:#0f0f1a;border:1px solid rgba(79,70,229,.08);text-align:center;color:#94a3b8;"><?= (int)($cand['years_experience'] ?? 0) ?> yrs</td>
                <?php endforeach; ?>
            </tr>
            <!-- Skill Scores -->
            <?php foreach ($skillNames ?? [] as $skillName): ?>
            <tr>
                <td style="padding:12px 16px;border:1px solid rgba(79,70,229,.08);color:#64748b;font-weight:600;"><?= htmlspecialchars($skillName) ?></td>
                <?php foreach ($candidates as $cand): ?>
                <td style="padding:12px 16px;border:1px solid rgba(79,70,229,.08);text-align:center;">
                    <?php
                    $ss = $cand['skill_scores'][$skillName] ?? null;
                    if ($ss !== null):
                    ?>
                    <div>
                        <div style="font-size:.9rem;font-weight:700;color:<?= $ss >= 70 ? '#4ade80' : ($ss >= 50 ? '#fbbf24' : '#f87171') ?>;"><?= (int)$ss ?>%</div>
                        <div style="height:4px;background:#0f0f1a;border-radius:2px;margin-top:4px;overflow:hidden;">
                            <div style="height:100%;width:<?= (int)$ss ?>%;background:<?= $ss >= 70 ? '#10b981' : ($ss >= 50 ? '#d97706' : '#dc2626') ?>;border-radius:2px;"></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <span style="color:#475569;">—</span>
                    <?php endif; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
