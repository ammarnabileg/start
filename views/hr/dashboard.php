<?php
// views/hr/dashboard.php
// Variables from controller: $stats (array), $recentApplications (array), $attentionNeeded (array)
$stats = $stats ?? [];
$recentApplications = $recentApplications ?? [];
$attentionNeeded = $attentionNeeded ?? [];

function dashStatusBadge(string $status): string {
    $map = [
        'applied'            => ['bg:#3b82f620;color:#60a5fa;border:1px solid #3b82f640', 'Applied'],
        'ai_screening'       => ['bg:#8b5cf620;color:#a78bfa;border:1px solid #8b5cf640', 'AI Screening'],
        'qualified'          => ['bg:#22c55e20;color:#4ade80;border:1px solid #22c55e40', 'Qualified'],
        'disqualified'       => ['bg:#ef444420;color:#f87171;border:1px solid #ef444440', 'Disqualified'],
        'tech_interview'     => ['bg:#f59e0b20;color:#fbbf24;border:1px solid #f59e0b40', 'Tech Interview'],
        'manager_interview'  => ['bg:#f59e0b20;color:#fbbf24;border:1px solid #f59e0b40', 'Mgr Interview'],
        'final_review'       => ['bg:#ec489920;color:#f472b6;border:1px solid #ec489940', 'Final Review'],
        'offer'              => ['bg:#14b8a620;color:#2dd4bf;border:1px solid #14b8a640', 'Offer Sent'],
        'hired'              => ['bg:#22c55e20;color:#4ade80;border:1px solid #22c55e40', 'Hired'],
        'rejected'           => ['bg:#ef444420;color:#f87171;border:1px solid #ef444440', 'Rejected'],
        'withdrawn'          => ['bg:#64748b20;color:#94a3b8;border:1px solid #64748b40', 'Withdrawn'],
    ];
    $s = $map[$status] ?? ['bg:#64748b20;color:#94a3b8;border:1px solid #64748b40', ucfirst($status)];
    return '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:600;' . $s[0] . '">' . htmlspecialchars($s[1]) . '</span>';
}

function dashScoreBadge(?float $score): string {
    if ($score === null) return '<span style="color:#475569;font-size:0.8rem;">—</span>';
    $color = $score >= 80 ? '#4ade80' : ($score >= 60 ? '#fbbf24' : '#f87171');
    return '<span style="font-weight:700;color:' . $color . ';font-size:0.875rem;">' . number_format($score, 1) . '</span>';
}
?>
<style>
  .dash-grid { display: grid; gap: 20px; }
  .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
  .stat-card {
    background: #1e1e32;
    border: 1px solid rgba(79,70,229,0.2);
    border-radius: 14px;
    padding: 22px 24px;
    display: flex;
    align-items: center;
    gap: 18px;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  .stat-card:hover { border-color: rgba(79,70,229,0.5); box-shadow: 0 4px 24px rgba(79,70,229,0.1); }
  .stat-icon {
    width: 52px; height: 52px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
  }
  .stat-label { font-size: 0.78rem; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
  .stat-value { font-size: 2rem; font-weight: 800; color: #f1f5f9; line-height: 1; }
  .stat-sub { font-size: 0.75rem; color: #475569; margin-top: 4px; }
  .section-card {
    background: #1e1e32;
    border: 1px solid rgba(79,70,229,0.15);
    border-radius: 14px;
    overflow: hidden;
  }
  .section-header {
    padding: 18px 24px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid rgba(79,70,229,0.1);
  }
  .section-title { font-size: 1rem; font-weight: 700; color: #e2e8f0; }
  .data-table { width: 100%; border-collapse: collapse; }
  .data-table th {
    padding: 11px 16px;
    text-align: left; font-size: 0.72rem; font-weight: 600;
    color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;
    background: rgba(15,15,26,0.5);
    border-bottom: 1px solid rgba(79,70,229,0.1);
  }
  .data-table td {
    padding: 13px 16px;
    font-size: 0.875rem; color: #cbd5e1;
    border-bottom: 1px solid rgba(79,70,229,0.06);
    vertical-align: middle;
  }
  .data-table tr:last-child td { border-bottom: none; }
  .data-table tr:hover td { background: rgba(79,70,229,0.04); }
  .initials-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700; color: #fff;
    flex-shrink: 0;
  }
  .candidate-cell { display: flex; align-items: center; gap: 10px; }
  .candidate-name { font-weight: 600; color: #e2e8f0; font-size: 0.875rem; }
  .candidate-email { font-size: 0.75rem; color: #475569; }
  .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: all 0.15s; }
  .btn-primary { background: linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; }
  .btn-primary:hover { opacity:0.9; transform:translateY(-1px); }
  .btn-ghost { background: transparent; color: #94a3b8; border: 1px solid rgba(79,70,229,0.25); }
  .btn-ghost:hover { background: rgba(79,70,229,0.1); color: #e2e8f0; }
  .quick-actions { display: flex; gap: 10px; flex-wrap: wrap; }
  .attention-list { padding: 0; }
  .attention-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 24px;
    border-bottom: 1px solid rgba(79,70,229,0.06);
    gap: 12px;
  }
  .attention-item:last-child { border-bottom: none; }
  .attention-info { flex: 1; }
  .attention-name { font-weight: 600; color: #e2e8f0; font-size: 0.875rem; }
  .attention-meta { font-size: 0.75rem; color: #64748b; margin-top: 2px; }
  .attention-days { font-size: 0.75rem; font-weight: 600; color: #f59e0b; background: rgba(245,158,11,0.1); padding: 3px 8px; border-radius: 6px; white-space: nowrap; }
  .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
  .page-title { font-size: 1.6rem; font-weight: 800; color: #f1f5f9; }
  .page-subtitle { color: #64748b; font-size: 0.875rem; margin-top: 2px; }
  .empty-table { text-align: center; padding: 48px 24px; color: #475569; }
  @media (max-width: 768px) { .stat-cards { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="page-header">
  <div>
    <div class="page-title">Dashboard</div>
    <div class="page-subtitle">Welcome back — here's what's happening today</div>
  </div>
  <div class="quick-actions">
    <a href="/jobs/create" class="btn btn-primary">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      New Job
    </a>
    <a href="/pipeline" class="btn btn-ghost">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="5" height="18" rx="1"/><rect x="10" y="3" width="5" height="12" rx="1"/><rect x="17" y="3" width="4" height="8" rx="1"/></svg>
      View Pipeline
    </a>
    <a href="/candidates?export=1" class="btn btn-ghost">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
      Export
    </a>
  </div>
</div>

<div class="dash-grid">

  <!-- Stats Cards -->
  <div class="stat-cards">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(79,70,229,0.15);">
        <svg width="24" height="24" fill="none" stroke="#818cf8" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
      </div>
      <div>
        <div class="stat-label">Total Jobs</div>
        <div class="stat-value"><?= htmlspecialchars((string)($stats['total_jobs'] ?? 0)) ?></div>
        <div class="stat-sub"><?= htmlspecialchars((string)($stats['active_jobs'] ?? 0)) ?> active</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(34,197,94,0.12);">
        <svg width="24" height="24" fill="none" stroke="#4ade80" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div>
        <div class="stat-label">Active Applications</div>
        <div class="stat-value"><?= htmlspecialchars((string)($stats['active_applications'] ?? 0)) ?></div>
        <div class="stat-sub"><?= htmlspecialchars((string)($stats['new_today'] ?? 0)) ?> new today</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(139,92,246,0.15);">
        <svg width="24" height="24" fill="none" stroke="#a78bfa" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
      </div>
      <div>
        <div class="stat-label">AI Interviews Done</div>
        <div class="stat-value"><?= htmlspecialchars((string)($stats['ai_interviews_completed'] ?? 0)) ?></div>
        <div class="stat-sub">this month</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(20,184,166,0.12);">
        <svg width="24" height="24" fill="none" stroke="#2dd4bf" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div>
        <div class="stat-label">Hired This Month</div>
        <div class="stat-value"><?= htmlspecialchars((string)($stats['hired_this_month'] ?? 0)) ?></div>
        <div class="stat-sub"><?= htmlspecialchars((string)($stats['offers_pending'] ?? 0)) ?> offers pending</div>
      </div>
    </div>
  </div>

  <!-- Recent Applications -->
  <div class="section-card">
    <div class="section-header">
      <span class="section-title">Recent Applications</span>
      <a href="/candidates" class="btn btn-ghost" style="padding:6px 12px;font-size:0.8rem;">View All</a>
    </div>
    <?php if (empty($recentApplications)): ?>
      <div class="empty-table">
        <svg width="40" height="40" fill="none" stroke="#475569" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px;display:block;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <p>No applications yet</p>
      </div>
    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>Candidate</th>
            <th>Job</th>
            <th>Status</th>
            <th>AI Score</th>
            <th>Applied</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($recentApplications, 0, 10) as $app): ?>
            <?php
              $initials = strtoupper(
                substr($app['first_name'] ?? 'U', 0, 1) .
                substr($app['last_name'] ?? '', 0, 1)
              );
            ?>
            <tr>
              <td>
                <div class="candidate-cell">
                  <div class="initials-avatar"><?= htmlspecialchars($initials) ?></div>
                  <div>
                    <div class="candidate-name">
                      <a href="/candidates/<?= (int)$app['id'] ?>" style="color:inherit;text-decoration:none;">
                        <?= htmlspecialchars(trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''))) ?>
                      </a>
                    </div>
                    <div class="candidate-email"><?= htmlspecialchars($app['email'] ?? '') ?></div>
                  </div>
                </div>
              </td>
              <td style="color:#94a3b8;"><?= htmlspecialchars($app['job_title'] ?? $app['title'] ?? '—') ?></td>
              <td><?= dashStatusBadge($app['status'] ?? 'applied') ?></td>
              <td><?= dashScoreBadge(isset($app['final_score']) ? (float)$app['final_score'] : null) ?></td>
              <td style="color:#64748b;white-space:nowrap;">
                <?= $app['applied_at'] ? date('M j, Y', strtotime($app['applied_at'])) : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Attention Needed -->
  <?php if (!empty($attentionNeeded)): ?>
  <div class="section-card">
    <div class="section-header">
      <span class="section-title" style="color:#fbbf24;">
        <svg width="16" height="16" fill="none" stroke="#fbbf24" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-3px;margin-right:6px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Attention Needed
      </span>
      <span style="font-size:0.8rem;color:#64748b;"><?= count($attentionNeeded) ?> waiting</span>
    </div>
    <div class="attention-list">
      <?php foreach ($attentionNeeded as $item): ?>
        <div class="attention-item">
          <div class="initials-avatar" style="width:32px;height:32px;font-size:0.7rem;">
            <?= strtoupper(substr($item['first_name'] ?? 'U', 0, 1) . substr($item['last_name'] ?? '', 0, 1)) ?>
          </div>
          <div class="attention-info">
            <div class="attention-name"><?= htmlspecialchars(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?></div>
            <div class="attention-meta"><?= htmlspecialchars($item['job_title'] ?? '') ?> &middot; <?= dashStatusBadge($item['status'] ?? 'applied') ?></div>
          </div>
          <div class="attention-days"><?= htmlspecialchars((string)($item['days_waiting'] ?? '?')) ?>d waiting</div>
          <a href="/candidates/<?= (int)($item['application_id'] ?? $item['id'] ?? 0) ?>" class="btn btn-ghost" style="padding:6px 12px;font-size:0.78rem;">Review</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>
