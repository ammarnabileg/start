<?php
/**
 * Super Admin – Global Users List
 */
$db = Database::getInstance();

$search     = trim($_GET['q'] ?? '');
$filterCo   = (int)($_GET['company'] ?? 0);
$filterRole = trim($_GET['role'] ?? '');
$filterSt   = trim($_GET['status'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 25;
$offset     = ($page - 1) * $perPage;

// Company list for filter dropdown
$allCompanies = $db->fetchAll("SELECT id, name FROM tenants ORDER BY name ASC LIMIT 200") ?? [];

// Build query
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $s = '%' . $search . '%';
    $params[] = $s;
    $params[] = $s;
}
if ($filterCo > 0) {
    $where[]  = 'u.tenant_id = ?';
    $params[] = $filterCo;
}
if ($filterRole !== '') {
    $where[]  = 'EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.slug=?)';
    $params[] = $filterRole;
}
if ($filterSt !== '') {
    $where[]  = 'u.status = ?';
    $params[] = $filterSt;
}

$whereSQL = implode(' AND ', $where);

$total = (int)($db->fetchColumn(
    "SELECT COUNT(*) FROM users u WHERE {$whereSQL}", $params
) ?? 0);

$users = $db->fetchAll(
    "SELECT u.*, t.name AS company_name, t.slug AS company_slug
       FROM users u
  LEFT JOIN tenants t ON t.id = u.tenant_id
      WHERE {$whereSQL}
      ORDER BY u.created_at DESC
      LIMIT {$perPage} OFFSET {$offset}",
    $params
) ?? [];

$lastPage = max(1, (int)ceil($total / $perPage));

function userStatusBadge(string $s): string {
    return match($s) {
        'active'    => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Active</span>',
        'suspended' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Suspended</span>',
        'inactive'  => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>',
        default     => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">' . htmlspecialchars(ucfirst($s)) . '</span>',
    };
}
function roleBadge(string $r): string {
    $map = [
        'super_admin'  => 'bg-violet-100 text-violet-700',
        'admin'        => 'bg-blue-100 text-blue-700',
        'hr_manager'   => 'bg-indigo-100 text-indigo-700',
        'recruiter'    => 'bg-sky-100 text-sky-700',
        'viewer'       => 'bg-gray-100 text-gray-600',
    ];
    $cls = $map[$r] ?? 'bg-gray-100 text-gray-600';
    return "<span class='inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {$cls}'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $r))) . '</span>';
}
?>

<!-- Header ----------------------------------------------------------------->
<div class="flex items-center justify-between mb-6">
  <p class="text-gray-500 text-sm">All users across every company on the platform.</p>
  <span class="text-sm text-gray-400 font-medium"><?= number_format($total) ?> total users</span>
</div>

<!-- Filters ----------------------------------------------------------------->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-5">
  <form method="GET" class="flex flex-col lg:flex-row gap-3">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search name or email…"
           class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">

    <select name="company" class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white min-w-40">
      <option value="">All Companies</option>
      <?php foreach ($allCompanies as $co): ?>
      <option value="<?= (int)$co['id'] ?>" <?= $filterCo === (int)$co['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($co['name']) ?>
      </option>
      <?php endforeach; ?>
    </select>

    <select name="role" class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white min-w-36">
      <option value="">All Roles</option>
      <option value="super_admin"  <?= $filterRole === 'super_admin'  ? 'selected' : '' ?>>Super Admin</option>
      <option value="admin"        <?= $filterRole === 'admin'        ? 'selected' : '' ?>>Admin</option>
      <option value="hr_manager"   <?= $filterRole === 'hr_manager'   ? 'selected' : '' ?>>HR Manager</option>
      <option value="recruiter"    <?= $filterRole === 'recruiter'    ? 'selected' : '' ?>>Recruiter</option>
      <option value="viewer"       <?= $filterRole === 'viewer'       ? 'selected' : '' ?>>Viewer</option>
    </select>

    <select name="status" class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white min-w-32">
      <option value="">All Statuses</option>
      <option value="active"    <?= $filterSt === 'active'    ? 'selected' : '' ?>>Active</option>
      <option value="suspended" <?= $filterSt === 'suspended' ? 'selected' : '' ?>>Suspended</option>
      <option value="inactive"  <?= $filterSt === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
    </select>

    <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors whitespace-nowrap">Filter</button>
    <a href="/super/users" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl text-sm font-medium transition-colors whitespace-nowrap">Reset</a>
  </form>
</div>

<!-- Table ------------------------------------------------------------------>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead>
        <tr class="bg-gray-50 text-left">
          <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
          <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Company</th>
          <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
          <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Login</th>
          <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
          <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($users)): ?>
        <tr>
          <td colspan="6" class="px-6 py-14 text-center text-gray-400 text-sm">
            No users found<?= $search ? ' for "' . htmlspecialchars($search) . '"' : '' ?>.
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($users as $u):
          $initial = strtoupper(substr($u['full_name'] ?? $u['email'] ?? 'U', 0, 1));
          $avatarColors = ['bg-violet-100 text-violet-700', 'bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700', 'bg-indigo-100 text-indigo-700'];
          $avatarCls = $avatarColors[crc32($u['email'] ?? '') % count($avatarColors)];
        ?>
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-6 py-4">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 <?= $avatarCls ?> rounded-xl flex items-center justify-center font-bold text-sm flex-shrink-0">
                <?= $initial ?>
              </div>
              <div>
                <div class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($u['full_name'] ?? '—') ?></div>
                <div class="text-xs text-gray-400"><?= htmlspecialchars($u['email'] ?? '') ?></div>
              </div>
            </div>
          </td>
          <td class="px-6 py-4">
            <?php if ($u['company_name']): ?>
            <div class="text-sm text-gray-700 font-medium"><?= htmlspecialchars($u['company_name']) ?></div>
            <div class="text-xs text-gray-400"><?= htmlspecialchars($u['company_slug'] ?? '') ?></div>
            <?php else: ?>
            <span class="text-xs text-gray-400">—</span>
            <?php endif; ?>
          </td>
          <td class="px-6 py-4"><?= roleBadge($u['role'] ?? 'viewer') ?></td>
          <td class="px-6 py-4">
            <?php if (!empty($u['last_login_at'])): ?>
            <div class="text-sm text-gray-700"><?= date('M j, Y', strtotime($u['last_login_at'])) ?></div>
            <div class="text-xs text-gray-400"><?= date('H:i', strtotime($u['last_login_at'])) ?></div>
            <?php else: ?>
            <span class="text-xs text-gray-400">Never</span>
            <?php endif; ?>
          </td>
          <td class="px-6 py-4"><?= userStatusBadge($u['status'] ?? 'active') ?></td>
          <td class="px-6 py-4">
            <div class="flex items-center gap-1">
              <button onclick="viewUserProfile(<?= (int)$u['id'] ?>)" class="p-1.5 text-gray-400 hover:text-violet-600 rounded-lg hover:bg-violet-50 transition-colors" title="View Profile">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              </button>
              <button onclick="impersonateUser(<?= (int)$u['id'] ?>)" class="p-1.5 text-gray-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 transition-colors" title="Impersonate">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
              </button>
              <?php if (($u['status'] ?? '') === 'active'): ?>
              <button onclick="suspendUser(<?= (int)$u['id'] ?>, true)" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors" title="Suspend">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
              </button>
              <?php else: ?>
              <button onclick="suspendUser(<?= (int)$u['id'] ?>, false)" class="p-1.5 text-gray-400 hover:text-emerald-600 rounded-lg hover:bg-emerald-50 transition-colors" title="Activate">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </button>
              <?php endif; ?>
              <button onclick="deleteUser(<?= (int)$u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'] ?? $u['email'] ?? ''), ENT_QUOTES) ?>')" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors" title="Delete">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($lastPage > 1): ?>
  <div class="px-6 py-4 border-t border-gray-50 flex items-center justify-between flex-wrap gap-2">
    <p class="text-sm text-gray-500">
      Showing <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $total)) ?> of <?= number_format($total) ?> users
    </p>
    <div class="flex gap-1 flex-wrap">
      <?php
      $start = max(1, $page - 2);
      $end   = min($lastPage, $page + 2);
      if ($start > 1): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="px-3 py-1.5 rounded-lg text-sm text-gray-600 hover:bg-gray-100">1</a>
      <?php if ($start > 2): ?><span class="px-2 py-1.5 text-sm text-gray-400">…</span><?php endif; ?>
      <?php endif; ?>
      <?php for ($p = $start; $p <= $end; $p++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
         class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?= $p === $page ? 'bg-violet-600 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
        <?= $p ?>
      </a>
      <?php endfor; ?>
      <?php if ($end < $lastPage): ?>
      <?php if ($end < $lastPage - 1): ?><span class="px-2 py-1.5 text-sm text-gray-400">…</span><?php endif; ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $lastPage])) ?>" class="px-3 py-1.5 rounded-lg text-sm text-gray-600 hover:bg-gray-100"><?= $lastPage ?></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
async function apiPost(url, body) {
  const r = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(body)
  });
  return r.json();
}

function viewUserProfile(id) {
  window.location.href = '/super/users?view=' + id;
}

function impersonateUser(id) {
  if (!confirm('Impersonate this user? You will be logged in as them.')) return;
  window.location.href = '/super/impersonate?user_id=' + id;
}

async function suspendUser(id, doSuspend) {
  const action = doSuspend ? 'suspend' : 'activate';
  if (!confirm('Are you sure you want to ' + action + ' this user?')) return;
  const d = await apiPost('/api/v1/admin?action=user_status', { id, status: doSuspend ? 'suspended' : 'active' });
  if (d.ok) { showToast('User ' + action + 'd.', 'success'); setTimeout(() => location.reload(), 800); }
  else showToast(d.message || 'Failed.', 'error');
}

async function deleteUser(id, name) {
  if (!confirm('Delete user "' + name + '"? This action cannot be undone.')) return;
  const d = await apiPost('/api/v1/admin?action=delete_user', { id });
  if (d.ok) { showToast('User deleted.', 'success'); setTimeout(() => location.reload(), 800); }
  else showToast(d.message || 'Failed.', 'error');
}
</script>
