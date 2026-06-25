<?php /** @var array[] $users @var array $pagination @var string $search @var Request $req */ ?>
<meta name="csrf" content="<?= htmlspecialchars($req->csrf()) ?>">

<div class="space-y-6">
  <h1 class="text-2xl font-bold text-gray-900">All Platform Users</h1>

  <form method="GET" class="flex gap-3">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search users..."
      class="flex-1 max-w-xs border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <button type="submit" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm">Search</button>
  </form>

  <div id="flash-msg" class="hidden rounded-lg p-4 text-sm font-medium"></div>

  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
    <table class="w-full">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Name</th>
          <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Email</th>
          <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Company</th>
          <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
          <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Last Login</th>
          <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if (empty($users)): ?>
          <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400">No users found.</td></tr>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
          <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 font-medium text-gray-900"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></td>
            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($u['tenant_name'] ?? '—') ?></td>
            <td class="px-6 py-4">
              <span class="px-2 py-1 rounded-full text-xs font-medium <?= $u['status']==='active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= ucfirst($u['status']) ?>
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500"><?= $u['last_login_at'] ? date('M j, Y', strtotime($u['last_login_at'])) : 'Never' ?></td>
            <td class="px-6 py-4">
              <button onclick="toggleUserStatus(<?= $u['id'] ?>, '<?= $u['status']==='active' ? 'inactive' : 'active' ?>')"
                class="text-xs px-3 py-1 rounded border border-gray-300 hover:bg-gray-50 text-gray-600">
                <?= $u['status']==='active' ? 'Suspend' : 'Activate' ?>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const csrf = document.querySelector('meta[name=csrf]').content;
function showFlash(msg, ok) {
  const el = document.getElementById('flash-msg');
  el.textContent = msg;
  el.className = 'rounded-lg p-4 text-sm font-medium ' + (ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700');
  el.classList.remove('hidden');
  setTimeout(() => ok && location.reload(), 1500);
}
async function toggleUserStatus(id, status) {
  if (!confirm('Change user status?')) return;
  const r = await fetch('/api/v1/super/users/' + id + '/status', {
    method:'PATCH', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},
    body: JSON.stringify({status})
  });
  const d = await r.json();
  showFlash(d.message || (d.ok?'Done!':'Error'), d.ok);
}
</script>
