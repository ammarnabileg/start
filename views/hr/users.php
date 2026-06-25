<?php
ob_start();
$pageTitle = 'Team Management';
$canInvite = Auth::can('users.create');
$canEdit   = Auth::can('users.edit');
$canDelete = Auth::can('users.delete');
$tid       = Auth::user()['tenant_id'];
$db        = Database::getInstance();

$users = Cache::remember("users_list_{$tid}", 120, function() use ($db, $tid) {
    return $db->fetchAll(
        "SELECT u.id, CONCAT(u.first_name,' ',u.last_name) as full_name, u.email, u.status, u.last_login_at as last_login, u.created_at,
                GROUP_CONCAT(r.slug ORDER BY r.id SEPARATOR ',') as roles,
                (SELECT r2.display_name FROM roles r2 JOIN user_roles ur2 ON ur2.role_id=r2.id WHERE ur2.user_id=u.id LIMIT 1) as role
         FROM users u
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         WHERE u.tenant_id = ?
           AND NOT EXISTS (SELECT 1 FROM user_roles ur3 JOIN roles r3 ON r3.id=ur3.role_id WHERE ur3.user_id=u.id AND r3.slug='candidate')
         GROUP BY u.id
         ORDER BY u.created_at DESC",
        [$tid]
    ) ?: [];
});

$roles = $db->fetchAll("SELECT * FROM roles WHERE is_system=0 OR is_system=1 ORDER BY name");
?>

<div class="max-w-6xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Team Management</h1>
      <p class="text-gray-500 text-sm mt-1">Manage your HR team and permissions</p>
    </div>
    <?php if ($canInvite): ?>
    <button onclick="openModal('invite-modal')" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium flex items-center gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Invite Team Member
    </button>
    <?php endif; ?>
  </div>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-4 border-b border-gray-100">
      <input type="text" id="user-search" placeholder="Search by name or email..."
        class="w-full max-w-xs border border-gray-200 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"
        oninput="filterUsers(this.value)">
    </div>

    <table class="w-full">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
          <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100" id="users-table">
        <?php if (empty($users)): ?>
        <tr>
          <td colspan="6" class="px-6 py-12 text-center text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <p class="font-medium">No team members yet</p>
            <p class="text-sm">Invite your first team member to get started</p>
          </td>
        </tr>
        <?php else: foreach ($users as $u): ?>
        <tr class="hover:bg-gray-50 user-row" data-name="<?= strtolower(htmlspecialchars($u['full_name'])) ?>" data-email="<?= strtolower(htmlspecialchars($u['email'])) ?>">
          <td class="px-6 py-4">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-sm">
                <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
              </div>
              <div>
                <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($u['full_name']) ?></p>
                <p class="text-gray-500 text-xs"><?= htmlspecialchars($u['email']) ?></p>
              </div>
            </div>
          </td>
          <td class="px-6 py-4">
            <span class="text-sm text-gray-700"><?= htmlspecialchars($u['role'] ?? 'No role') ?></span>
          </td>
          <td class="px-6 py-4">
            <?php $sc = $u['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'; ?>
            <span class="text-xs font-medium px-2 py-1 rounded-full <?= $sc ?>"><?= ucfirst($u['status']) ?></span>
          </td>
          <td class="px-6 py-4 text-sm text-gray-500">
            <?= $u['last_login'] ? date('M j, Y', strtotime($u['last_login'])) : 'Never' ?>
          </td>
          <td class="px-6 py-4 text-sm text-gray-500">
            <?= date('M j, Y', strtotime($u['created_at'])) ?>
          </td>
          <td class="px-6 py-4 text-right">
            <div class="flex items-center justify-end gap-2">
              <?php if ($canEdit): ?>
              <button onclick="editUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>', '<?= htmlspecialchars(explode(',', $u['roles'] ?? '')[0] ?? '') ?>')"
                class="text-xs text-violet-600 hover:text-violet-800 font-medium">Edit</button>
              <?php endif; ?>
              <?php if ($canDelete && $u['id'] !== Auth::user()['id']): ?>
              <button onclick="removeUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')"
                class="text-xs text-red-600 hover:text-red-800 font-medium">Remove</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Invite Modal -->
<div id="invite-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 modal-overlay">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
      <h3 class="font-semibold text-gray-900">Invite Team Member</h3>
      <button data-modal-close class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <form id="invite-form" class="p-6 space-y-4">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
          <input type="text" name="first_name" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
          <input type="text" name="last_name" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
        <input type="email" name="email" required class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
        <select name="role" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
          <?php foreach ($roles as $r): ?>
          <?php if ($r['slug'] !== 'super_admin' && $r['slug'] !== 'candidate'): ?>
          <option value="<?= htmlspecialchars($r['slug']) ?>"><?= htmlspecialchars($r['display_name'] ?? str_replace('_', ' ', $r['name'])) ?></option>
          <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Temporary Password</label>
        <input type="password" name="password" required minlength="8" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="closeModal('invite-modal')" class="flex-1 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full">Cancel</button>
        <button type="submit" id="invite-btn" class="flex-1 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-full">Send Invitation</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Role Modal -->
<div id="edit-user-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 modal-overlay">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 p-6">
    <h3 class="font-semibold text-gray-900 mb-4">Change Role</h3>
    <input type="hidden" id="edit-user-id">
    <p class="text-sm text-gray-600 mb-4">Changing role for: <strong id="edit-user-name"></strong></p>
    <select id="edit-user-role" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-violet-500">
      <?php foreach ($roles as $r): ?>
      <?php if ($r['slug'] !== 'super_admin' && $r['slug'] !== 'candidate'): ?>
      <option value="<?= htmlspecialchars($r['slug']) ?>"><?= htmlspecialchars($r['display_name'] ?? str_replace('_', ' ', $r['name'])) ?></option>
      <?php endif; ?>
      <?php endforeach; ?>
    </select>
    <div class="flex gap-3">
      <button onclick="closeModal('edit-user-modal')" class="flex-1 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full">Cancel</button>
      <button onclick="saveUserRole()" class="flex-1 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-full">Save</button>
    </div>
  </div>
</div>

<script>
function filterUsers(term) {
  document.querySelectorAll('.user-row').forEach(row => {
    const match = !term || row.dataset.name.includes(term.toLowerCase()) || row.dataset.email.includes(term.toLowerCase());
    row.style.display = match ? '' : 'none';
  });
}

document.getElementById('invite-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('invite-btn');
  setLoading(btn, true, 'Inviting...');
  const fd = new FormData(e.target);
  const body = Object.fromEntries(fd);
  body.full_name = (body.first_name + ' ' + body.last_name).trim();
  delete body.first_name;
  delete body.last_name;
  try {
    const res = await ajax('/api/v1/users', { method: 'POST', body });
    if (res.ok) {
      showToast('Invitation sent! ' + body.email, 'success');
      closeModal('invite-modal');
      setTimeout(() => location.reload(), 1000);
    } else throw new Error(res.message);
  } catch(err) {
    showToast(err.message || 'Failed to invite user', 'error');
  } finally { setLoading(btn, false); }
});

function editUser(id, name, role) {
  document.getElementById('edit-user-id').value = id;
  document.getElementById('edit-user-name').textContent = name;
  document.getElementById('edit-user-role').value = role;
  openModal('edit-user-modal');
}

async function saveUserRole() {
  const id   = document.getElementById('edit-user-id').value;
  const role = document.getElementById('edit-user-role').value;
  try {
    const res = await ajax('/api/v1/users', { method: 'PUT', body: { id, role } });
    if (res.ok) { showToast('Role updated', 'success'); closeModal('edit-user-modal'); setTimeout(() => location.reload(), 800); }
    else throw new Error(res.message);
  } catch(err) { showToast(err.message || 'Failed', 'error'); }
}

function removeUser(id, name) {
  confirm2(`Remove ${name} from your team? They will lose access immediately.`, async () => {
    try {
      const res = await ajax('/api/v1/users', { method: 'DELETE', body: { id } });
      if (res.ok) { showToast(`${name} removed`, 'success'); setTimeout(() => location.reload(), 800); }
      else throw new Error(res.message);
    } catch(err) { showToast(err.message || 'Failed', 'error'); }
  });
}
</script>
<?php $content = ob_get_clean(); require VIEWS_PATH . '/layouts/app.php'; ?>
