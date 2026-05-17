<?php
$pageTitle  = 'Team Management';
$activePage = 'team';
$members = [
  ['name'=>'Ahmed Al-Rashid', 'email'=>'ahmed@brand.com',   'role'=>'Owner',         'initials'=>'AA','lastActive'=>'Just now',   'status'=>'online', 'joined'=>'Jan 2024','permissions'=>'Full Access'],
  ['name'=>'Sarah Johnson',   'email'=>'sarah@brand.com',   'role'=>'Admin',          'initials'=>'SJ','lastActive'=>'5m ago',     'status'=>'online', 'joined'=>'Feb 2024','permissions'=>'Full Access'],
  ['name'=>'Mohammed Hassan', 'email'=>'mo@brand.com',      'role'=>'Content Manager','initials'=>'MH','lastActive'=>'1h ago',     'status'=>'online', 'joined'=>'Mar 2024','permissions'=>'Content & Community'],
  ['name'=>'Priya Patel',     'email'=>'priya@brand.com',   'role'=>'Analyst',        'initials'=>'PP','lastActive'=>'3h ago',     'status'=>'away',   'joined'=>'Apr 2024','permissions'=>'Analytics Only'],
  ['name'=>'Carlos Martinez', 'email'=>'carlos@brand.com',  'role'=>'Content Creator','initials'=>'CM','lastActive'=>'Yesterday',  'status'=>'offline','joined'=>'Apr 2024','permissions'=>'Content Only'],
  ['name'=>'Emma Wilson',     'email'=>'emma@brand.com',    'role'=>'Community Mod',  'initials'=>'EW','lastActive'=>'2d ago',     'status'=>'offline','joined'=>'May 2024','permissions'=>'Community Only'],
];
$roles = ['Owner','Admin','Content Manager','Analyst','Content Creator','Community Moderator','Read Only'];
$statusColors = ['online'=>'var(--green)','away'=>'var(--yellow)','offline'=>'var(--text-muted)'];
$roleColors = ['Owner'=>'badge-purple','Admin'=>'badge-error','Content Manager'=>'badge-info','Analyst'=>'badge-success','Content Creator'=>'badge-warning','Community Mod'=>'badge-neutral','Read Only'=>'badge-neutral'];
?>
<?php ob_start() ?>
<div class="page-header page-header-row">
  <div>
    <h1>Team Management 👥</h1>
    <p>Manage your team members, roles and permissions</p>
  </div>
  <button class="btn btn-primary invite-member-btn" data-modal="inviteMemberModal">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Invite Member
  </button>
</div>

<!-- Stats -->
<div class="dashboard-grid grid-cols-4 mb-4">
  <?php foreach([['Total Members','6','👥'],['Active Now','3','🟢'],['Pending Invites','2','⏳'],['Roles','4','🎭']] as [$l,$v,$i]): ?>
  <div class="metric-card" style="padding:1.25rem">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <div class="metric-label"><?= $l ?></div>
        <div class="metric-value" style="font-size:1.8rem"><?= $v ?></div>
      </div>
      <span style="font-size:1.8rem"><?= $i ?></span>
    </div>
  </div>
  <?php endforeach ?>
</div>

<!-- Members Table -->
<div class="glass-card p-0">
  <div style="padding:1.25rem;border-bottom:1px solid var(--glass-border);display:flex;align-items:center;justify-content:space-between">
    <h3>Team Members</h3>
    <div style="display:flex;gap:0.5rem">
      <input type="text" class="form-input" placeholder="Search members..." style="max-width:240px">
      <select class="form-select" style="max-width:160px">
        <option>All Roles</option>
        <?php foreach($roles as $r): ?><option><?= htmlspecialchars($r) ?></option><?php endforeach ?>
      </select>
    </div>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Member</th>
          <th>Role</th>
          <th>Permissions</th>
          <th>Status</th>
          <th>Last Active</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:0.75rem">
              <div style="position:relative;flex-shrink:0">
                <div class="user-avatar" style="width:38px;height:38px;font-size:0.85rem;background:var(--gradient-primary)"><?= htmlspecialchars($m['initials']) ?></div>
                <span style="position:absolute;bottom:0;right:0;width:10px;height:10px;border-radius:50%;background:<?= $statusColors[$m['status']] ?>;border:2px solid var(--navy)"></span>
              </div>
              <div>
                <div class="td-primary" style="font-size:0.875rem"><?= htmlspecialchars($m['name']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($m['email']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="badge <?= $roleColors[$m['role']] ?? 'badge-neutral' ?>"><?= htmlspecialchars($m['role']) ?></span></td>
          <td style="font-size:0.8rem;color:var(--text-muted)"><?= htmlspecialchars($m['permissions']) ?></td>
          <td>
            <span style="display:flex;align-items:center;gap:0.3rem;font-size:0.8rem;color:<?= $statusColors[$m['status']] ?>">
              <span style="width:7px;height:7px;border-radius:50%;background:currentColor;display:inline-block"></span>
              <?= ucfirst($m['status']) ?>
            </span>
          </td>
          <td style="font-size:0.82rem;color:var(--text-muted)"><?= htmlspecialchars($m['lastActive']) ?></td>
          <td style="font-size:0.8rem;color:var(--text-muted)"><?= htmlspecialchars($m['joined']) ?></td>
          <td>
            <div style="display:flex;gap:0.35rem">
              <?php if ($m['role'] !== 'Owner'): ?>
              <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Opening member settings...','info')">⚙️ Edit</button>
              <button class="btn btn-danger btn-sm" onclick="if(confirm('Remove this member?'))SociAI.showToast('Member removed','error')">Remove</button>
              <?php else: ?>
              <button class="btn btn-ghost btn-sm" disabled>Owner</button>
              <?php endif ?>
            </div>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pending Invites -->
<div class="glass-card" style="margin-top:1.25rem">
  <div class="section-header"><h3>📨 Pending Invitations</h3></div>
  <?php foreach([['nadia@agency.com','Content Creator','Sent 2d ago'],['james@startup.io','Analyst','Sent 3d ago']] as [$email,$role,$time]): ?>
  <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 0;border-bottom:1px solid rgba(255,255,255,0.04)">
    <div class="user-avatar" style="background:rgba(100,116,139,0.3);font-size:0.75rem"><?= strtoupper(substr($email,0,2)) ?></div>
    <div style="flex:1">
      <div style="font-size:0.85rem;font-weight:500;color:var(--text-primary)"><?= htmlspecialchars($email) ?></div>
      <div style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($role) ?> · <?= htmlspecialchars($time) ?></div>
    </div>
    <span class="badge badge-warning badge-dot">Pending</span>
    <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Invitation resent!','success')">Resend</button>
    <button class="btn btn-danger btn-sm" onclick="SociAI.showToast('Invitation cancelled','error')">Cancel</button>
  </div>
  <?php endforeach ?>
</div>

<!-- Role Permissions Table -->
<div class="glass-card" style="margin-top:1.25rem">
  <div class="section-header"><h3>🎭 Role Permissions</h3></div>
  <div class="table-wrapper">
    <table class="data-table" style="font-size:0.8rem">
      <thead>
        <tr>
          <th>Permission</th>
          <th style="text-align:center">Owner</th>
          <th style="text-align:center">Admin</th>
          <th style="text-align:center">Content Mgr</th>
          <th style="text-align:center">Analyst</th>
          <th style="text-align:center">Creator</th>
          <th style="text-align:center">Community Mod</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $perms = [
          ['View Dashboard',      true, true, true, true, true, true],
          ['Create Content',      true, true, true, false, true, false],
          ['Publish Content',     true, true, true, false, false, false],
          ['View Analytics',      true, true, true, true, false, false],
          ['Community Replies',   true, true, true, false, false, true],
          ['Manage Campaigns',    true, true, false, false, false, false],
          ['Manage Team',         true, true, false, false, false, false],
          ['Billing & Settings',  true, false, false, false, false, false],
        ];
        foreach ($perms as $p):
          $name = array_shift($p);
        ?>
        <tr>
          <td class="td-primary"><?= htmlspecialchars($name) ?></td>
          <?php foreach($p as $has): ?>
          <td style="text-align:center;font-size:1rem"><?= $has ? '<span style="color:var(--green-light)">✓</span>' : '<span style="color:var(--text-muted)">—</span>' ?></td>
          <?php endforeach ?>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Invite Modal -->
<div class="modal-overlay" id="inviteMemberModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>📨 Invite Team Member</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="form-group">
      <label class="form-label">Email Address</label>
      <input type="email" class="form-input" placeholder="colleague@company.com">
    </div>
    <div class="form-group">
      <label class="form-label">Role</label>
      <select class="form-select">
        <?php foreach(array_slice($roles,1) as $r): ?><option><?= htmlspecialchars($r) ?></option><?php endforeach ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Personal Message (optional)</label>
      <textarea class="form-textarea" rows="2" placeholder="Hey! I'd like you to join our SociAI OS workspace..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="SociAI.closeModal('inviteMemberModal')">Cancel</button>
      <button class="btn btn-primary" onclick="SociAI.showToast('Invitation sent!','success');SociAI.closeModal('inviteMemberModal')">📨 Send Invitation</button>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php' ?>
