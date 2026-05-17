<?php
$pageTitle  = 'Team Management';
$activePage = 'team';

$members = $members ?? [
    [
        'id'         => 1,
        'name'       => 'Ahmed Al-Rashid',
        'email'      => 'ahmed@brand.com',
        'initials'   => 'AA',
        'color'      => '#3B82F6',
        'role'       => 'Admin',
        'platforms'  => ['linkedin','instagram','tiktok','facebook','twitter','youtube','snapchat','threads','pinterest','whatsapp','telegram'],
        'last_active'=> '2 minutes ago',
        'status'     => 'active',
    ],
    [
        'id'         => 2,
        'name'       => 'Sara Ahmed',
        'email'      => 'sara@brand.com',
        'initials'   => 'SA',
        'color'      => '#8B5CF6',
        'role'       => 'Editor',
        'platforms'  => ['linkedin','instagram','tiktok'],
        'last_active'=> '1 hour ago',
        'status'     => 'active',
    ],
    [
        'id'         => 3,
        'name'       => 'Mohammed Khalid',
        'email'      => 'm.khalid@brand.com',
        'initials'   => 'MK',
        'color'      => '#10B981',
        'role'       => 'Analyst',
        'platforms'  => ['linkedin','facebook','twitter'],
        'last_active'=> 'Yesterday',
        'status'     => 'active',
    ],
    [
        'id'         => 4,
        'name'       => 'Layla Tariq',
        'email'      => 'layla@brand.com',
        'initials'   => 'LT',
        'color'      => '#EC4899',
        'role'       => 'Editor',
        'platforms'  => ['instagram','tiktok','pinterest'],
        'last_active'=> '3 days ago',
        'status'     => 'active',
    ],
    [
        'id'         => 5,
        'name'       => 'Rania Jabr',
        'email'      => 'rania@partner.com',
        'initials'   => 'RJ',
        'color'      => '#F59E0B',
        'role'       => 'Viewer',
        'platforms'  => ['linkedin'],
        'last_active'=> 'Pending',
        'status'     => 'invited',
    ],
    [
        'id'         => 6,
        'name'       => 'Nasser Hamdan',
        'email'      => 'nasser@brand.com',
        'initials'   => 'NH',
        'color'      => '#6B7280',
        'role'       => 'Editor',
        'platforms'  => ['facebook','twitter','youtube'],
        'last_active'=> '2 weeks ago',
        'status'     => 'suspended',
    ],
];

$activityLog = $activityLog ?? [
    ['member' => 'Sara Ahmed',      'action' => 'Published Instagram Reel to @brand_instagram',     'time' => '5 min ago',  'icon' => '📸'],
    ['member' => 'Ahmed Al-Rashid', 'action' => 'Approved 12 content drafts for Q3 campaign',       'time' => '32 min ago', 'icon' => '✅'],
    ['member' => 'Mohammed Khalid', 'action' => 'Exported analytics report for LinkedIn',            'time' => '1 hr ago',   'icon' => '📊'],
    ['member' => 'Layla Tariq',     'action' => 'Uploaded 8 new creative assets to Content Library','time' => '3 hrs ago',  'icon' => '🖼️'],
    ['member' => 'Ahmed Al-Rashid', 'action' => 'Invited Rania Jabr as Viewer',                     'time' => 'Yesterday',  'icon' => '📧'],
    ['member' => 'Sara Ahmed',      'action' => 'Scheduled 15 posts for next week',                  'time' => 'Yesterday',  'icon' => '📅'],
    ['member' => 'Mohammed Khalid', 'action' => 'Ran A/B test on 3 LinkedIn post variations',        'time' => '2 days ago', 'icon' => '🧪'],
];

$platformEmojis = [
    'linkedin'  => '💼', 'instagram' => '📸', 'tiktok'    => '🎵',
    'facebook'  => '👥', 'twitter'   => '🐦', 'youtube'   => '▶️',
    'snapchat'  => '👻', 'threads'   => '🧵', 'pinterest' => '📌',
    'whatsapp'  => '💬', 'telegram'  => '✈️',
];

$roleBadge = [
    'Admin'   => 'badge-danger',
    'Editor'  => 'badge-info',
    'Analyst' => 'badge-warning',
    'Viewer'  => 'badge-neutral',
];

$rolePermissions = [
    'Admin'   => ['view_content'=>true,  'create_content'=>true,  'publish_content'=>true,  'manage_team'=>true,  'view_analytics'=>true,  'manage_billing'=>true,  'configure_ai'=>true],
    'Editor'  => ['view_content'=>true,  'create_content'=>true,  'publish_content'=>true,  'manage_team'=>false, 'view_analytics'=>true,  'manage_billing'=>false, 'configure_ai'=>false],
    'Analyst' => ['view_content'=>true,  'create_content'=>false, 'publish_content'=>false, 'manage_team'=>false, 'view_analytics'=>true,  'manage_billing'=>false, 'configure_ai'=>false],
    'Viewer'  => ['view_content'=>true,  'create_content'=>false, 'publish_content'=>false, 'manage_team'=>false, 'view_analytics'=>false, 'manage_billing'=>false, 'configure_ai'=>false],
];
$permLabels = [
    'view_content'    => 'View Content',
    'create_content'  => 'Create Content',
    'publish_content' => 'Publish / Schedule',
    'manage_team'     => 'Manage Team',
    'view_analytics'  => 'View Analytics',
    'manage_billing'  => 'Manage Billing',
    'configure_ai'    => 'Configure AI',
];

ob_start();
?>

<div class="team-page">

  <!-- ── PAGE HEADER ──────────────────────────────── -->
  <div class="page-header page-header-row" style="margin-bottom:1.5rem">
    <div>
      <h1>Team Management</h1>
      <p style="color:var(--text-muted);margin-top:0.25rem">Manage access, roles, and permissions for your team</p>
    </div>
    <button class="btn btn-primary" onclick="SociAI.openModal('inviteMemberModal')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Invite Member
    </button>
  </div>

  <!-- ── TEAM MEMBERS GRID ─────────────────────────── -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.75rem">
    <?php foreach ($members as $m): ?>
    <?php
    $statusColor = match($m['status']) {
        'active'    => 'var(--green)',
        'invited'   => 'var(--yellow)',
        'suspended' => 'var(--red-light)',
        default     => 'var(--text-muted)',
    };
    $statusLabel = match($m['status']) {
        'active'    => 'Active',
        'invited'   => 'Invited',
        'suspended' => 'Suspended',
        default     => $m['status'],
    };
    ?>
    <div class="glass-card" style="text-align:center;padding:1.5rem 1.25rem;position:relative;transition:all 0.2s"
         onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">

      <!-- Status dot -->
      <div style="position:absolute;top:1rem;right:1rem;display:flex;align-items:center;gap:0.3rem">
        <span style="width:8px;height:8px;border-radius:50%;background:<?= $statusColor ?>;display:inline-block;<?= $m['status']==='active'?'box-shadow:0 0 6px '.$statusColor.';':'' ?>"></span>
        <span style="font-size:0.7rem;color:<?= $statusColor ?>"><?= $statusLabel ?></span>
      </div>

      <!-- Avatar -->
      <div style="width:56px;height:56px;border-radius:50%;background:<?= htmlspecialchars($m['color']) ?>;display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:700;color:#fff;margin:0 auto 0.75rem;border:2px solid <?= htmlspecialchars($m['color']) ?>40">
        <?= htmlspecialchars($m['initials']) ?>
      </div>

      <!-- Name & Email -->
      <div style="font-size:0.925rem;font-weight:700;margin-bottom:0.2rem;color:var(--text-primary)"><?= htmlspecialchars($m['name']) ?></div>
      <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.6rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($m['email']) ?></div>

      <!-- Role Badge -->
      <span class="badge <?= $roleBadge[$m['role']] ?? 'badge-neutral' ?>" style="margin-bottom:0.75rem;display:inline-block"><?= htmlspecialchars($m['role']) ?></span>

      <!-- Platforms -->
      <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:0.25rem;margin-bottom:0.75rem">
        <?php foreach (array_slice($m['platforms'], 0, 5) as $pl): ?>
        <span title="<?= ucfirst($pl) ?>" style="font-size:0.9rem"><?= $platformEmojis[$pl] ?? '🌐' ?></span>
        <?php endforeach ?>
        <?php if (count($m['platforms']) > 5): ?>
        <span style="font-size:0.68rem;color:var(--text-muted)">+<?= count($m['platforms']) - 5 ?></span>
        <?php endif ?>
      </div>

      <!-- Last Active -->
      <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:1rem">
        Last active: <?= htmlspecialchars($m['last_active']) ?>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:0.4rem;justify-content:center;flex-wrap:wrap">
        <button class="btn btn-ghost btn-sm"
                onclick="teamActions.editRole(<?= (int)$m['id'] ?>, '<?= htmlspecialchars($m['role']) ?>', '<?= htmlspecialchars($m['name']) ?>')">
          Edit Role
        </button>
        <?php if ($m['status'] === 'invited'): ?>
        <button class="btn btn-ghost btn-sm" style="color:var(--blue-light)"
                onclick="teamActions.resendInvite(<?= (int)$m['id'] ?>, '<?= htmlspecialchars($m['email']) ?>')">
          Resend
        </button>
        <?php elseif ($m['status'] === 'suspended'): ?>
        <button class="btn btn-ghost btn-sm" style="color:var(--green)"
                onclick="if(confirm('Restore access for <?= htmlspecialchars(addslashes($m['name'])) ?>?')) window.location.href='/dashboard/team/<?= (int)$m['id'] ?>/restore'">
          Restore
        </button>
        <?php else: ?>
        <button class="btn btn-ghost btn-sm" style="color:var(--red-light)"
                onclick="if(confirm('Revoke access for <?= htmlspecialchars(addslashes($m['name'])) ?>?')) window.location.href='/dashboard/team/<?= (int)$m['id'] ?>/revoke'">
          Revoke
        </button>
        <?php endif ?>
      </div>
    </div>
    <?php endforeach ?>
  </div>

  <!-- ── ROLE PERMISSIONS TABLE ────────────────────── -->
  <div class="glass-card" style="margin-bottom:1.75rem">
    <div class="section-header" style="margin-bottom:1rem">
      <h3>🔐 Role Permissions</h3>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Permission</th>
            <?php foreach (array_keys($rolePermissions) as $role): ?>
            <th style="text-align:center"><?= htmlspecialchars($role) ?></th>
            <?php endforeach ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($permLabels as $key => $label): ?>
          <tr>
            <td style="font-weight:500"><?= htmlspecialchars($label) ?></td>
            <?php foreach ($rolePermissions as $role => $perms): ?>
            <td style="text-align:center">
              <?php if ($perms[$key]): ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              <?php else: ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--red-light)" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              <?php endif ?>
            </td>
            <?php endforeach ?>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── ACTIVITY LOG ──────────────────────────────── -->
  <div class="glass-card">
    <div class="section-header" style="margin-bottom:1rem">
      <h3>📜 Activity Log</h3>
      <a href="/dashboard/team/activity" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div style="display:flex;flex-direction:column;gap:0">
      <?php foreach ($activityLog as $i => $log): ?>
      <div style="display:flex;align-items:center;gap:1rem;padding:0.85rem 0;<?= $i > 0 ? 'border-top:1px solid var(--glass-border)' : '' ?>">
        <div style="width:36px;height:36px;border-radius:50%;background:var(--glass-bg);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0">
          <?= $log['icon'] ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:0.85rem;color:var(--text-primary)">
            <span style="font-weight:600"><?= htmlspecialchars($log['member']) ?></span>
            <span style="color:var(--text-muted)"> <?= htmlspecialchars($log['action']) ?></span>
          </div>
        </div>
        <div style="font-size:0.75rem;color:var(--text-muted);flex-shrink:0"><?= htmlspecialchars($log['time']) ?></div>
      </div>
      <?php endforeach ?>
    </div>
  </div>

</div>

<!-- ── INVITE MEMBER MODAL ───────────────────────── -->
<div class="modal-overlay" id="inviteMemberModal">
  <div class="modal-content" style="max-width:560px;width:100%">
    <div class="modal-header">
      <h3>📧 Invite Team Member</h3>
      <button class="modal-close" onclick="SociAI.closeModal('inviteMemberModal')">×</button>
    </div>
    <form action="/dashboard/team/invite" method="POST">
      <input type="hidden" name="_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16))) ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
        <div class="form-group" style="margin:0">
          <label class="form-label">Full Name <span style="color:var(--red-light)">*</span></label>
          <input type="text" class="form-input" name="name" placeholder="Jane Smith" required>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Email Address <span style="color:var(--red-light)">*</span></label>
          <input type="email" class="form-input" name="email" placeholder="jane@company.com" required>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Role <span style="color:var(--red-light)">*</span></label>
        <select class="form-select" name="role" required onchange="inviteModal.updatePermPreview(this.value)">
          <option value="">Select a role…</option>
          <option value="Admin">Admin — Full access</option>
          <option value="Editor">Editor — Create & publish content</option>
          <option value="Analyst">Analyst — View & analyse only</option>
          <option value="Viewer">Viewer — Read-only access</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Platform Access</label>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.4rem">
          <?php foreach ($platformEmojis as $val => $emoji): ?>
          <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8rem;cursor:pointer;padding:0.35rem 0.5rem;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-sm);transition:border-color 0.15s"
                 onmouseover="this.style.borderColor='var(--blue)'" onmouseout="this.style.borderColor='var(--glass-border)'">
            <input type="checkbox" name="platform_access[]" value="<?= $val ?>">
            <span><?= $emoji ?> <?= ucfirst($val) ?></span>
          </label>
          <?php endforeach ?>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Custom Permissions Note (optional)</label>
        <textarea class="form-textarea" name="custom_permissions" rows="2"
                  placeholder="e.g. Can only post on LinkedIn on weekdays, requires approval for TikTok posts…"></textarea>
      </div>

      <!-- Permission Preview -->
      <div id="invitePermPreview" style="display:none;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-sm);padding:0.75rem;margin-bottom:1rem">
        <div style="font-size:0.75rem;font-weight:600;color:var(--text-muted);margin-bottom:0.5rem">Permission Preview</div>
        <div id="invitePermList" style="display:flex;flex-wrap:wrap;gap:0.3rem"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="SociAI.closeModal('inviteMemberModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">📧 Send Invite</button>
      </div>
    </form>
  </div>
</div>

<!-- ── EDIT ROLE MODAL ───────────────────────────── -->
<div class="modal-overlay" id="editRoleModal">
  <div class="modal-content" style="max-width:420px;width:100%">
    <div class="modal-header">
      <h3>✏️ Edit Role — <span id="editRoleMemberName"></span></h3>
      <button class="modal-close" onclick="SociAI.closeModal('editRoleModal')">×</button>
    </div>
    <form id="editRoleForm" method="POST">
      <input type="hidden" name="_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16))) ?>">
      <input type="hidden" name="member_id" id="editRoleMemberId">
      <div class="form-group" style="margin-bottom:1.25rem">
        <label class="form-label">New Role</label>
        <select class="form-select" name="role" id="editRoleSelect">
          <option value="Admin">Admin</option>
          <option value="Editor">Editor</option>
          <option value="Analyst">Analyst</option>
          <option value="Viewer">Viewer</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="SociAI.closeModal('editRoleModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
const inviteModal = {
  permMap: {
    'Admin':   ['View Content','Create Content','Publish / Schedule','Manage Team','View Analytics','Manage Billing','Configure AI'],
    'Editor':  ['View Content','Create Content','Publish / Schedule','View Analytics'],
    'Analyst': ['View Content','View Analytics'],
    'Viewer':  ['View Content'],
  },
  updatePermPreview(role) {
    const wrap = document.getElementById('invitePermPreview');
    const list = document.getElementById('invitePermList');
    const perms = this.permMap[role];
    if (!perms || !role) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';
    list.innerHTML = perms.map(p =>
      `<span style="background:rgba(59,130,246,0.12);color:var(--blue-light);border-radius:999px;padding:0.15rem 0.6rem;font-size:0.72rem">✓ ${p}</span>`
    ).join('');
  }
};

const teamActions = {
  editRole(id, currentRole, name) {
    document.getElementById('editRoleMemberName').textContent = name;
    document.getElementById('editRoleMemberId').value = id;
    document.getElementById('editRoleSelect').value = currentRole;
    document.getElementById('editRoleForm').action = '/dashboard/team/' + id + '/role';
    SociAI.openModal('editRoleModal');
  },
  resendInvite(id, email) {
    if (!confirm('Resend invitation to ' + email + '?')) return;
    window.location.href = '/dashboard/team/' + id + '/resend-invite';
  }
};
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
