<?php
ob_start();
$pageTitle = 'Roles & Permissions';
if (!Auth::can('roles.manage')) { header('Location: /unauthorized'); exit; }
$db = Database::getInstance();
$tid = Auth::user()['tenant_id'];
$roles = $db->fetchAll("SELECT r.*, (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) as user_count FROM roles r WHERE r.tenant_id = ? OR r.tenant_id IS NULL ORDER BY r.is_system DESC, r.name ASC", [$tid]) ?: [];
$permissions = $db->fetchAll("SELECT * FROM permissions ORDER BY category, name") ?: [];
?>

<?php
// Group permissions by category
$permsByCategory = [];
foreach ($permissions as $perm) {
    $permsByCategory[$perm['category']][] = $perm;
}

// Category display metadata
$categoryMeta = [
    'jobs'       => ['label' => 'Jobs',         'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => 'violet'],
    'candidates' => ['label' => 'Candidates',   'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'color' => 'blue'],
    'interviews' => ['label' => 'Interviews',   'icon' => 'M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z', 'color' => 'amber'],
    'offers'     => ['label' => 'Offers',       'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'color' => 'emerald'],
    'users'      => ['label' => 'Users',        'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'color' => 'indigo'],
    'ai'         => ['label' => 'AI Features',  'icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z', 'color' => 'rose'],
    'settings'   => ['label' => 'Settings',     'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', 'color' => 'gray'],
];

$colorMap = [
    'violet'  => ['bg' => 'bg-violet-50',  'text' => 'text-violet-600',  'badge' => 'bg-violet-100 text-violet-700'],
    'blue'    => ['bg' => 'bg-blue-50',    'text' => 'text-blue-600',    'badge' => 'bg-blue-100 text-blue-700'],
    'amber'   => ['bg' => 'bg-amber-50',   'text' => 'text-amber-600',   'badge' => 'bg-amber-100 text-amber-700'],
    'emerald' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'badge' => 'bg-emerald-100 text-emerald-700'],
    'indigo'  => ['bg' => 'bg-indigo-50',  'text' => 'text-indigo-600',  'badge' => 'bg-indigo-100 text-indigo-700'],
    'rose'    => ['bg' => 'bg-rose-50',    'text' => 'text-rose-600',    'badge' => 'bg-rose-100 text-rose-700'],
    'gray'    => ['bg' => 'bg-gray-50',    'text' => 'text-gray-600',    'badge' => 'bg-gray-100 text-gray-700'],
];

// Fetch role-permission assignments (role_id => [perm_id => true])
$rolePermRows = $db->fetchAll(
    "SELECT rp.role_id, rp.permission_id FROM role_permissions rp
     JOIN roles r ON r.id = rp.role_id
     WHERE r.tenant_id = ? OR r.tenant_id IS NULL",
    [$tid]
) ?: [];
$rolePermsMap = [];
foreach ($rolePermRows as $rp) {
    $rolePermsMap[$rp['role_id']][$rp['permission_id']] = true;
}
?>

<div class="fade-in">
  <!-- Page Header -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Roles &amp; Permissions</h1>
      <p class="text-sm text-gray-500 mt-0.5">Control what each role can access within the platform</p>
    </div>
    <button onclick="openCreateRoleModal()"
      class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors flex items-center gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
      Create Custom Role
    </button>
  </div>

  <div class="flex gap-5" style="min-height: calc(100vh - 13rem);">

    <!-- ===== Left: Role List ===== -->
    <div class="w-72 flex-shrink-0 flex flex-col gap-3 overflow-y-auto pb-6">
      <?php if (empty($roles)): ?>
      <div class="bg-white rounded-2xl border border-gray-100 p-8 text-center">
        <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>
        <p class="text-sm text-gray-500 mb-2">No roles yet</p>
        <button onclick="openCreateRoleModal()" class="text-violet-600 text-xs font-medium hover:underline">Create first role</button>
      </div>
      <?php else: ?>
      <?php foreach ($roles as $role):
        $isSystem = (bool)($role['is_system'] ?? false);
        $permCount = count($rolePermsMap[$role['id']] ?? []);
        $userCount = (int)$role['user_count'];
      ?>
      <div
        class="role-card bg-white rounded-2xl border border-gray-100 p-4 cursor-pointer hover:border-violet-300 hover:shadow-sm transition-all group select-none"
        data-role-id="<?= $role['id'] ?>"
        onclick="selectRole(<?= $role['id'] ?>, <?= htmlspecialchars(json_encode($role), ENT_QUOTES) ?>)">
        <div class="flex items-start gap-3 mb-3">
          <div class="w-9 h-9 rounded-xl <?= $isSystem ? 'bg-amber-100' : 'bg-violet-100' ?> flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-4 h-4 <?= $isSystem ? 'text-amber-600' : 'text-violet-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-sm font-semibold text-gray-900 group-hover:text-violet-700 transition-colors truncate">
                <?= htmlspecialchars($role['name']) ?>
              </span>
              <?php if ($isSystem): ?>
              <span class="flex-shrink-0 text-xs font-medium bg-amber-100 text-amber-700 rounded-full px-2 py-0.5">System</span>
              <?php endif; ?>
            </div>
            <?php if (!empty($role['description'])): ?>
            <p class="text-xs text-gray-400 mt-0.5 line-clamp-2 leading-relaxed"><?= htmlspecialchars($role['description']) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <div class="flex items-center gap-3 text-xs text-gray-400">
          <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <?= $userCount ?> <?= $userCount === 1 ? 'user' : 'users' ?>
          </span>
          <span class="text-gray-200">·</span>
          <span class="flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <?= $permCount ?> permissions
          </span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ===== Right: Permissions Panel ===== -->
    <div class="flex-1 overflow-y-auto pb-6">

      <!-- Empty State -->
      <div id="emptyPermState" class="flex items-center justify-center" style="min-height:400px">
        <div class="text-center max-w-xs">
          <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          </div>
          <h3 class="text-sm font-semibold text-gray-700 mb-1">Select a Role</h3>
          <p class="text-xs text-gray-400 leading-relaxed">Click any role on the left to view and configure its permissions.</p>
        </div>
      </div>

      <!-- Active Permissions Panel -->
      <div id="activePermPanel" class="hidden">

        <!-- Role Header Card -->
        <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-4 flex items-start justify-between">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <div>
              <h2 class="text-base font-semibold text-gray-900" id="activeRoleName">—</h2>
              <p class="text-xs text-gray-500 mt-0.5" id="activeRoleDesc">—</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span id="systemRoleBadge" class="hidden bg-amber-100 text-amber-700 text-xs font-semibold rounded-full px-3 py-1.5">System Role</span>
            <button id="editRoleBtn" onclick="editCurrentRole()"
              class="hidden text-xs text-violet-600 hover:text-violet-800 border border-violet-200 hover:border-violet-400 hover:bg-violet-50 rounded-full px-3 py-1.5 font-medium transition-all">
              Edit Role
            </button>
          </div>
        </div>

        <!-- Bulk Actions -->
        <div class="flex items-center gap-4 mb-4 px-1">
          <button onclick="toggleAllPermissions(true)"
            class="text-xs text-violet-600 hover:text-violet-800 font-medium flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Select All
          </button>
          <span class="text-gray-300 text-sm">|</span>
          <button onclick="toggleAllPermissions(false)"
            class="text-xs text-gray-500 hover:text-gray-700 font-medium flex items-center gap-1.5 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Deselect All
          </button>
          <span class="ml-auto text-xs text-gray-400 font-medium" id="permCountDisplay">0 permissions enabled</span>
        </div>

        <!-- Permission Categories -->
        <div class="space-y-3" id="permCategoryList">
          <?php foreach ($permsByCategory as $category => $perms):
            $meta = $categoryMeta[$category] ?? ['label' => ucfirst($category), 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => 'gray'];
            $col  = $colorMap[$meta['color']] ?? $colorMap['gray'];
          ?>
          <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <!-- Category header -->
            <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-50 <?= $col['bg'] ?>">
              <div class="w-7 h-7 rounded-lg bg-white/80 flex items-center justify-center flex-shrink-0 shadow-sm">
                <svg class="w-3.5 h-3.5 <?= $col['text'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $meta['icon'] ?>"/>
                </svg>
              </div>
              <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($meta['label']) ?></span>
              <span class="ml-auto <?= $col['badge'] ?> text-xs font-semibold rounded-full px-2.5 py-0.5" id="catCount-<?= htmlspecialchars($category) ?>">
                0 / <?= count($perms) ?>
              </span>
            </div>
            <!-- Permission rows -->
            <div class="divide-y divide-gray-50">
              <?php foreach ($perms as $perm): ?>
              <label class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors cursor-pointer group">
                <!-- Custom checkbox -->
                <div class="flex-shrink-0 relative">
                  <input type="checkbox"
                    class="perm-checkbox sr-only"
                    data-perm-id="<?= (int)$perm['id'] ?>"
                    data-category="<?= htmlspecialchars($category) ?>"
                    onchange="onPermChange(this)">
                  <div class="perm-visual w-5 h-5 rounded-md border-2 border-gray-300 group-hover:border-violet-400 transition-all flex items-center justify-center">
                    <svg class="perm-tick w-3 h-3 text-white hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                  </div>
                </div>
                <!-- Label -->
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-gray-800"><?= htmlspecialchars($perm['label'] ?? $perm['name']) ?></div>
                  <?php if (!empty($perm['description'])): ?>
                  <div class="text-xs text-gray-400 mt-0.5 leading-relaxed"><?= htmlspecialchars($perm['description']) ?></div>
                  <?php endif; ?>
                </div>
                <!-- Key -->
                <code class="text-xs text-gray-300 flex-shrink-0 hidden sm:block"><?= htmlspecialchars($perm['name']) ?></code>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <?php if (empty($permsByCategory)): ?>
          <div class="bg-white rounded-2xl border border-gray-100 p-8 text-center text-sm text-gray-400">
            No permissions defined in the system yet.
          </div>
          <?php endif; ?>
        </div>

        <!-- Sticky Save Bar -->
        <div class="sticky bottom-0 mt-4 bg-white/95 backdrop-blur border border-gray-100 rounded-2xl shadow-lg px-5 py-4 flex items-center gap-4">
          <div>
            <p class="text-sm font-semibold text-gray-900">
              Permissions for <span id="saveRoleName" class="text-violet-700">this role</span>
            </p>
            <p class="text-xs text-gray-400 mt-0.5">Changes take effect immediately for all users assigned this role.</p>
          </div>
          <div class="ml-auto flex items-center gap-3">
            <span id="permSaveMsg" class="text-sm text-emerald-600 font-medium hidden">Saved!</span>
            <button onclick="savePermissions()"
              class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-full text-sm font-medium transition-colors flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              Save Permissions
            </button>
          </div>
        </div>

      </div><!-- /activePermPanel -->
    </div><!-- /right panel -->
  </div><!-- /flex row -->
</div>

<!-- ===== Create Role Modal ===== -->
<div id="createRoleModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeCreateRoleModal()"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 relative">
      <button onclick="closeCreateRoleModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
      <h3 class="text-base font-semibold text-gray-900 mb-1">Create Custom Role</h3>
      <p class="text-sm text-gray-500 mb-5">Define a new role and optionally copy permissions from an existing one.</p>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Role Name <span class="text-red-500">*</span></label>
          <input type="text" id="newRoleName"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition"
            placeholder="e.g. Senior Recruiter">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
          <textarea id="newRoleDesc" rows="2"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition resize-none"
            placeholder="What can people in this role do?"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Copy Permissions From</label>
          <select id="copyFromRole"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition bg-white">
            <option value="">— Start from scratch —</option>
            <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="flex items-center gap-3 mt-6">
        <button onclick="createRole()"
          class="flex-1 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2.5 rounded-full text-sm font-medium transition-colors">
          Create Role
        </button>
        <button onclick="closeCreateRoleModal()"
          class="flex-1 border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2.5 rounded-full text-sm font-medium transition-colors">
          Cancel
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Edit Role Modal ===== -->
<div id="editRoleModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeEditRoleModal()"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 relative">
      <button onclick="closeEditRoleModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
      <h3 class="text-base font-semibold text-gray-900 mb-5">Edit Role</h3>
      <input type="hidden" id="editRoleId">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Role Name</label>
          <input type="text" id="editRoleNameInput"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
          <textarea id="editRoleDescInput" rows="2"
            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition resize-none"></textarea>
        </div>
      </div>
      <div class="flex items-center gap-3 mt-6">
        <button onclick="updateRole()"
          class="flex-1 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2.5 rounded-full text-sm font-medium transition-colors">
          Save Changes
        </button>
        <button onclick="closeEditRoleModal()"
          class="flex-1 border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2.5 rounded-full text-sm font-medium transition-colors">
          Cancel
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 pointer-events-none"></div>

<style>
.role-card.active {
  border-color: #7C3AED;
  box-shadow: 0 0 0 3px rgba(124,58,237,0.12);
}
.perm-checkbox:checked ~ .perm-visual,
.perm-visual.checked {
  background-color: #7C3AED;
  border-color: #7C3AED;
}
.perm-checkbox:checked ~ .perm-visual .perm-tick,
.perm-visual.checked .perm-tick {
  display: block;
}
@keyframes slideInRight {
  from { opacity: 0; transform: translateX(12px); }
  to   { opacity: 1; transform: translateX(0); }
}
#activePermPanel { animation: slideInRight 0.25s ease; }
</style>

<script>
// ===== PHP data bridge =====
const serverRolePerms = <?= json_encode($rolePermsMap) ?>;
let activeRoleId   = null;
let activeRoleData = null;

// Local mutable copy so we can update without page reload
const localRolePerms = JSON.parse(JSON.stringify(serverRolePerms));

// ===== Toast helper =====
function showToast(msg, type = 'success') {
  const tc = document.getElementById('toastContainer');
  const bg = type === 'success' ? 'bg-emerald-600' : type === 'error' ? 'bg-red-600' : 'bg-blue-600';
  const icon = type === 'success'
    ? 'M5 13l4 4L19 7'
    : type === 'error'
    ? 'M6 18L18 6M6 6l12 12'
    : 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
  const t = document.createElement('div');
  t.className = `pointer-events-auto ${bg} text-white text-sm font-medium px-5 py-3 rounded-xl shadow-xl flex items-center gap-2 opacity-0 transition-opacity duration-300`;
  t.innerHTML = `<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${icon}"/></svg><span>${msg}</span>`;
  tc.appendChild(t);
  requestAnimationFrame(() => { t.style.opacity = '1'; });
  setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 350); }, 3500);
}

// ===== Select Role =====
function selectRole(roleId, role) {
  activeRoleId   = roleId;
  activeRoleData = role;

  // Highlight card
  document.querySelectorAll('.role-card').forEach(c => c.classList.remove('active'));
  document.querySelector(`.role-card[data-role-id="${roleId}"]`)?.classList.add('active');

  // Show panel
  document.getElementById('emptyPermState').classList.add('hidden');
  const panel = document.getElementById('activePermPanel');
  panel.classList.remove('hidden');

  // Fill header
  document.getElementById('activeRoleName').textContent = role.name;
  document.getElementById('activeRoleDesc').textContent = role.description || (role.is_system ? 'System-managed role' : 'Custom role');
  document.getElementById('saveRoleName').textContent = role.name;

  const isSystem = role.is_system == 1 || role.is_system === true;
  document.getElementById('systemRoleBadge').classList.toggle('hidden', !isSystem);
  document.getElementById('editRoleBtn').classList.toggle('hidden', isSystem);

  // Load checkboxes
  const currentPerms = localRolePerms[roleId] || {};
  document.querySelectorAll('.perm-checkbox').forEach(cb => {
    const permId = parseInt(cb.dataset.permId, 10);
    const checked = !!currentPerms[permId];
    cb.checked = checked;
    applyVisual(cb, checked);
  });

  updateCountDisplays();
}

function applyVisual(cb, checked) {
  const visual = cb.parentElement.querySelector('.perm-visual');
  const tick   = visual.querySelector('.perm-tick');
  if (checked) {
    visual.style.backgroundColor = '#7C3AED';
    visual.style.borderColor = '#7C3AED';
    tick.classList.remove('hidden');
  } else {
    visual.style.backgroundColor = '';
    visual.style.borderColor = '';
    tick.classList.add('hidden');
  }
}

// ===== Checkbox change =====
function onPermChange(cb) {
  applyVisual(cb, cb.checked);
  updateCountDisplays();
}

// ===== Count displays =====
function updateCountDisplays() {
  const catCounts = {};
  let totalEnabled = 0;

  document.querySelectorAll('.perm-checkbox').forEach(cb => {
    const cat = cb.dataset.category;
    if (!catCounts[cat]) catCounts[cat] = { total: 0, checked: 0 };
    catCounts[cat].total++;
    if (cb.checked) { catCounts[cat].checked++; totalEnabled++; }
  });

  Object.entries(catCounts).forEach(([cat, c]) => {
    const el = document.getElementById('catCount-' + cat);
    if (el) el.textContent = `${c.checked} / ${c.total}`;
  });

  const el = document.getElementById('permCountDisplay');
  if (el) el.textContent = `${totalEnabled} permission${totalEnabled !== 1 ? 's' : ''} enabled`;
}

// ===== Toggle All =====
function toggleAllPermissions(enable) {
  document.querySelectorAll('.perm-checkbox').forEach(cb => {
    cb.checked = enable;
    applyVisual(cb, enable);
  });
  updateCountDisplays();
}

// ===== Save Permissions =====
async function savePermissions() {
  if (!activeRoleId) return;
  const permIds = [];
  document.querySelectorAll('.perm-checkbox:checked').forEach(cb => permIds.push(parseInt(cb.dataset.permId, 10)));

  try {
    const res = await fetch('/api/v1/roles?action=save_permissions', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ role_id: activeRoleId, permission_ids: permIds })
    });
    const json = await res.json();
    if (json.ok) {
      // Update local cache
      const map = {};
      permIds.forEach(id => { map[id] = true; });
      localRolePerms[activeRoleId] = map;

      // Update card perm count
      const card = document.querySelector(`.role-card[data-role-id="${activeRoleId}"]`);
      if (card) {
        const spans = card.querySelectorAll('.flex.items-center.gap-1');
        if (spans[1]) spans[1].innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>${permIds.length} permissions`;
      }

      showToast(`Permissions saved for ${activeRoleData.name}`);
      const msg = document.getElementById('permSaveMsg');
      msg.classList.remove('hidden');
      setTimeout(() => msg.classList.add('hidden'), 3000);
    } else {
      showToast(json.message || 'Failed to save permissions.', 'error');
    }
  } catch (err) {
    showToast('Network error. Please try again.', 'error');
  }
}

// ===== Create Role Modal =====
function openCreateRoleModal() {
  document.getElementById('createRoleModal').classList.remove('hidden');
  setTimeout(() => document.getElementById('newRoleName').focus(), 50);
}
function closeCreateRoleModal() {
  document.getElementById('createRoleModal').classList.add('hidden');
  document.getElementById('newRoleName').value = '';
  document.getElementById('newRoleDesc').value = '';
  document.getElementById('copyFromRole').value = '';
}

async function createRole() {
  const name     = document.getElementById('newRoleName').value.trim();
  const desc     = document.getElementById('newRoleDesc').value.trim();
  const copyFrom = document.getElementById('copyFromRole').value;
  if (!name) { showToast('Please enter a role name.', 'error'); return; }
  try {
    const res = await fetch('/api/v1/roles?action=create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ name, description: desc, copy_from_role_id: copyFrom || null })
    });
    const json = await res.json();
    if (json.ok) {
      closeCreateRoleModal();
      showToast(`Role "${name}" created!`);
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(json.message || 'Failed to create role.', 'error');
    }
  } catch (err) { showToast('Network error.', 'error'); }
}

// ===== Edit Role Modal =====
function editCurrentRole() {
  if (!activeRoleData || activeRoleData.is_system) return;
  document.getElementById('editRoleId').value        = activeRoleId;
  document.getElementById('editRoleNameInput').value = activeRoleData.name;
  document.getElementById('editRoleDescInput').value = activeRoleData.description || '';
  document.getElementById('editRoleModal').classList.remove('hidden');
}
function closeEditRoleModal() {
  document.getElementById('editRoleModal').classList.add('hidden');
}

async function updateRole() {
  const id   = document.getElementById('editRoleId').value;
  const name = document.getElementById('editRoleNameInput').value.trim();
  const desc = document.getElementById('editRoleDescInput').value.trim();
  if (!name) { showToast('Role name cannot be empty.', 'error'); return; }
  try {
    const res = await fetch('/api/v1/roles?action=update', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ id, name, description: desc })
    });
    const json = await res.json();
    if (json.ok) {
      closeEditRoleModal();
      showToast('Role updated!');
      // Patch UI without reload
      document.getElementById('activeRoleName').textContent = name;
      document.getElementById('activeRoleDesc').textContent = desc || 'Custom role';
      document.getElementById('saveRoleName').textContent   = name;
      const nameEl = document.querySelector(`.role-card[data-role-id="${id}"] .font-semibold`);
      if (nameEl) nameEl.textContent = name;
      activeRoleData.name        = name;
      activeRoleData.description = desc;
    } else {
      showToast(json.message || 'Failed to update role.', 'error');
    }
  } catch (err) { showToast('Network error.', 'error'); }
}

// Close modals on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeCreateRoleModal();
    closeEditRoleModal();
  }
});
</script>
<?php $content = ob_get_clean(); require VIEWS_PATH . '/layouts/app.php'; ?>
