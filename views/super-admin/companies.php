<?php
/**
 * Super Admin – Company Management
 */
$db = Database::getInstance();

$search    = trim($_GET['q'] ?? '');
$statusTab = $_GET['status'] ?? 'all';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

// ── Build query ──────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(t.name LIKE ? OR t.slug LIKE ?)';
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s]);
}
if ($statusTab !== 'all') {
    $where[]  = 't.status = ?';
    $params[] = $statusTab;
}

$whereSQL = implode(' AND ', $where);

$total = (int)($db->fetchColumn(
    "SELECT COUNT(*) FROM tenants t WHERE {$whereSQL}",
    $params
) ?? 0);

$companies = $db->fetchAll(
    "SELECT t.*,
            COUNT(DISTINCT u.id) AS user_count,
            COUNT(DISTINCT j.id) AS job_count,
            COUNT(DISTINCT i.id) AS interview_count,
            (SELECT CONCAT(u2.first_name,' ',u2.last_name,'|',u2.email) FROM users u2 WHERE u2.tenant_id = t.id AND u2.status='active' ORDER BY u2.id ASC LIMIT 1) AS owner_info
       FROM tenants t
  LEFT JOIN users u ON u.tenant_id = t.id
  LEFT JOIN jobs j  ON j.tenant_id = t.id
  LEFT JOIN interviews i ON i.application_id IN (
               SELECT a.id FROM applications a WHERE a.tenant_id = t.id
               AND a.applied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
      WHERE {$whereSQL}
      GROUP BY t.id
      ORDER BY t.created_at DESC
      LIMIT {$perPage} OFFSET {$offset}",
    $params
) ?? [];

// Parse owner info
foreach ($companies as &$co) {
    if (!empty($co['owner_info'])) {
        [$ownerName, $ownerEmail] = explode('|', $co['owner_info'], 2) + ['', ''];
        $co['owner_name']  = trim($ownerName);
        $co['owner_email'] = trim($ownerEmail);
    } else {
        $co['owner_name']  = '';
        $co['owner_email'] = '';
    }
}
unset($co);

$statusCounts = $db->fetchAll(
    "SELECT status, COUNT(*) AS cnt FROM tenants GROUP BY status"
) ?? [];
$sCounts = array_column($statusCounts, 'cnt', 'status');
$allCount = array_sum($sCounts);

$lastPage = max(1, (int)ceil($total / $perPage));

function coBadge(string $s): string {
    return match($s) {
        'active'    => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Active</span>',
        'suspended' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Suspended</span>',
        'archived'  => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Archived</span>',
        default     => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">' . htmlspecialchars(ucfirst($s)) . '</span>',
    };
}
function planBadge(string $p): string {
    $cls = match($p) {
        'enterprise'   => 'bg-violet-100 text-violet-700',
        'professional' => 'bg-blue-100 text-blue-700',
        default        => 'bg-gray-100 text-gray-600',
    };
    return "<span class='inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {$cls}'>" . htmlspecialchars(ucfirst($p)) . '</span>';
}
?>

<!-- Header ----------------------------------------------------------------->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
  <div>
    <p class="text-gray-500 text-sm">Manage all tenant companies on the platform.</p>
  </div>
  <button onclick="openModal('addCompanyModal')" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors inline-flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    Add Company
  </button>
</div>

<!-- Status Tabs + Search --------------------------------------------------->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-5">
  <div class="flex flex-col lg:flex-row lg:items-center gap-4">
    <!-- Status tabs -->
    <div class="flex items-center gap-1 overflow-x-auto flex-shrink-0">
      <?php
      $tabs = ['all' => 'All', 'active' => 'Active', 'suspended' => 'Suspended', 'archived' => 'Archived'];
      foreach ($tabs as $key => $label):
        $cnt = $key === 'all' ? $allCount : ($sCounts[$key] ?? 0);
        $active = $statusTab === $key;
        $cls = $active
          ? 'bg-violet-600 text-white'
          : 'bg-gray-100 hover:bg-gray-200 text-gray-700';
      ?>
      <a href="?status=<?= $key ?>&q=<?= urlencode($search) ?>"
         class="<?= $cls ?> px-3 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors">
        <?= $label ?> <span class="opacity-70">(<?= number_format($cnt) ?>)</span>
      </a>
      <?php endforeach; ?>
    </div>
    <!-- Search -->
    <form method="GET" class="flex-1 flex gap-2">
      <input type="hidden" name="status" value="<?= htmlspecialchars($statusTab) ?>">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
             placeholder="Search company name, slug, email…"
             class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
      <button class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">Search</button>
      <?php if ($search !== ''): ?>
      <a href="?status=<?= urlencode($statusTab) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl text-sm font-medium transition-colors">Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Bulk actions bar (hidden until rows selected) ------------------------->
<div id="bulkBar" class="hidden bg-amber-50 border border-amber-200 rounded-2xl px-5 py-3 mb-4 flex items-center gap-4">
  <span id="bulkCount" class="text-sm font-semibold text-amber-800">0 selected</span>
  <button onclick="bulkAction('suspend')" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-full text-xs font-semibold transition-colors">Suspend Selected</button>
  <button onclick="bulkAction('archive')" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1.5 rounded-full text-xs font-semibold transition-colors">Archive Selected</button>
  <button onclick="clearSelection()" class="text-gray-500 text-xs hover:text-gray-700 ml-auto">Cancel</button>
</div>

<!-- Table ------------------------------------------------------------------>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead>
        <tr class="bg-gray-50 text-left">
          <th class="px-4 py-3 w-8">
            <input type="checkbox" onchange="toggleAll(this)" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
          </th>
          <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Company</th>
          <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
          <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Users</th>
          <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Jobs</th>
          <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Interviews</th>
          <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
          <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Created</th>
          <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($companies)): ?>
        <tr><td colspan="9" class="px-6 py-14 text-center text-gray-400 text-sm">
          No companies found<?= $search ? " for \"" . htmlspecialchars($search) . "\"" : '' ?>.
        </td></tr>
        <?php else: ?>
        <?php foreach ($companies as $co): ?>
        <tr class="hover:bg-gray-50 transition-colors" data-id="<?= (int)$co['id'] ?>">
          <td class="px-4 py-3.5">
            <input type="checkbox" name="selected[]" value="<?= (int)$co['id'] ?>" onchange="updateSelection()" class="row-check rounded border-gray-300 text-violet-600 focus:ring-violet-500">
          </td>
          <td class="px-4 py-3.5">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 bg-violet-100 rounded-xl flex items-center justify-center text-violet-700 font-bold text-sm flex-shrink-0">
                <?= strtoupper(substr($co['name'] ?? 'C', 0, 1)) ?>
              </div>
              <div>
                <div class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($co['name'] ?? '') ?></div>
                <div class="text-xs text-gray-400"><?= htmlspecialchars($co['slug'] ?? '') ?></div>
                <?php if ($co['owner_email']): ?>
                <div class="text-xs text-violet-500 mt-0.5 cursor-pointer hover:text-violet-700" onclick="copyText('<?= htmlspecialchars($co['owner_email']) ?>')" title="Click to copy"><?= htmlspecialchars($co['owner_email']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td class="px-4 py-3.5"><?= planBadge($co['plan'] ?? 'starter') ?></td>
          <td class="px-4 py-3.5 text-sm text-gray-700"><?= number_format((int)($co['user_count'] ?? 0)) ?></td>
          <td class="px-4 py-3.5 text-sm text-gray-700"><?= number_format((int)($co['job_count'] ?? 0)) ?></td>
          <td class="px-4 py-3.5 text-sm text-gray-700"><?= number_format((int)($co['interview_count'] ?? 0)) ?></td>
          <td class="px-4 py-3.5"><?= coBadge($co['status'] ?? 'active') ?></td>
          <td class="px-4 py-3.5 text-xs text-gray-400 whitespace-nowrap"><?= isset($co['created_at']) ? date('M j, Y', strtotime($co['created_at'])) : '—' ?></td>
          <td class="px-4 py-3.5">
            <div class="flex items-center gap-1">
              <button onclick="sendCredentials(<?= (int)$co['id'] ?>, '<?= htmlspecialchars(addslashes($co['name'] ?? ''), ENT_QUOTES) ?>')" class="p-1.5 text-gray-400 hover:text-emerald-600 rounded-lg hover:bg-emerald-50 transition-colors" title="Send Login Credentials">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              </button>
              <button onclick="openEditModal(<?= htmlspecialchars(json_encode($co), ENT_QUOTES) ?>)" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50 transition-colors" title="Edit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </button>
              <?php if (($co['status'] ?? '') === 'active'): ?>
              <button onclick="toggleStatus(<?= (int)$co['id'] ?>, 'suspended')" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors" title="Suspend">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
              </button>
              <?php else: ?>
              <button onclick="toggleStatus(<?= (int)$co['id'] ?>, 'active')" class="p-1.5 text-gray-400 hover:text-emerald-600 rounded-lg hover:bg-emerald-50 transition-colors" title="Activate">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </button>
              <?php endif; ?>
              <button onclick="impersonateOwner(<?= (int)$co['id'] ?>)" class="p-1.5 text-gray-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 transition-colors" title="Impersonate Owner">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              </button>
              <button onclick="archiveCompany(<?= (int)$co['id'] ?>)" class="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition-colors" title="Archive">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
              </button>
              <button onclick="deleteCompany(<?= (int)$co['id'] ?>, '<?= htmlspecialchars(addslashes($co['name'] ?? ''), ENT_QUOTES) ?>')" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors" title="Delete">
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
  <div class="px-6 py-4 border-t border-gray-50 flex items-center justify-between">
    <p class="text-sm text-gray-500">
      Showing <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $total)) ?> of <?= number_format($total) ?> companies
    </p>
    <div class="flex gap-1">
      <?php for ($p = 1; $p <= $lastPage; $p++): ?>
      <a href="?status=<?= urlencode($statusTab) ?>&q=<?= urlencode($search) ?>&page=<?= $p ?>"
         class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?= $p === $page ? 'bg-violet-600 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">
        <?= $p ?>
      </a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ Add Company Modal ════════════════════════════════════════════════════ -->
<div id="addCompanyModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this)closeModal('addCompanyModal')">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h3 class="font-semibold text-gray-900">Add Company</h3>
      <button onclick="closeModal('addCompanyModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="addCompanyForm" onsubmit="submitAddCompany(event)" class="p-6 space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Company Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" required placeholder="Acme Corp"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Slug <span class="text-red-500">*</span></label>
          <input type="text" name="slug" required placeholder="acme-corp"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Plan</label>
          <select name="plan" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white">
            <option value="starter">Starter</option>
            <option value="professional">Professional</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Owner Email <span class="text-red-500">*</span></label>
          <input type="email" name="owner_email" required placeholder="admin@acme.com"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Owner Password <span class="text-red-500">*</span></label>
          <input type="text" name="owner_password" required placeholder="Temp password"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          <p class="text-xs text-gray-400 mt-1">Share this with the company admin.</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Users</label>
          <input type="number" name="max_users" value="10" min="1"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Jobs</label>
          <input type="number" name="max_jobs" value="20" min="1"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="closeModal('addCompanyModal')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-full text-sm font-medium transition-colors">Cancel</button>
        <button type="submit" class="flex-1 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2.5 rounded-full text-sm font-medium transition-colors">Create Company</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ Edit Company Modal ═══════════════════════════════════════════════════ -->
<div id="editCompanyModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this)closeModal('editCompanyModal')">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" onclick="event.stopPropagation()">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h3 class="font-semibold text-gray-900">Edit Company</h3>
      <button onclick="closeModal('editCompanyModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="editCompanyForm" onsubmit="submitEditCompany(event)" class="p-6 space-y-4">
      <input type="hidden" name="id" id="editId">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Company Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" id="editName" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Slug</label>
          <input type="text" name="slug" id="editSlug" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Plan</label>
          <select name="plan" id="editPlan" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white">
            <option value="starter">Starter</option>
            <option value="professional">Professional</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Users</label>
          <input type="number" name="max_users" id="editMaxUsers" min="1" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Jobs</label>
          <input type="number" name="max_jobs" id="editMaxJobs" min="1" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="closeModal('editCompanyModal')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-full text-sm font-medium transition-colors">Cancel</button>
        <button type="submit" class="flex-1 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2.5 rounded-full text-sm font-medium transition-colors">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Modal helpers ────────────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }

// ── Edit modal population ────────────────────────────────────────────────────
function openEditModal(co) {
  document.getElementById('editId').value       = co.id;
  document.getElementById('editName').value     = co.name || '';
  document.getElementById('editSlug').value     = co.slug || '';
  document.getElementById('editPlan').value     = co.plan || 'starter';
  document.getElementById('editMaxUsers').value = co.max_users || 10;
  document.getElementById('editMaxJobs').value  = co.max_jobs  || 20;
  openModal('editCompanyModal');
}

// ── API calls ────────────────────────────────────────────────────────────────
async function apiPost(url, body) {
  const r = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(body)
  });
  return r.json();
}

async function submitAddCompany(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const data = Object.fromEntries(fd.entries());
  const btn = e.target.querySelector('[type=submit]');
  btn.disabled = true; btn.textContent = 'Creating…';
  try {
    const d = await apiPost('/api/v1/admin?action=create_company', data);
    if (d.ok) {
      closeModal('addCompanyModal');
      showToast('✓ Company created! Login: ' + data.owner_email, 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast(d.message || 'Failed to create company.', 'error');
    }
  } finally { btn.disabled = false; btn.textContent = 'Create Company'; }
}

async function submitEditCompany(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const data = Object.fromEntries(fd.entries());
  const d = await apiPost('/api/v1/admin?action=update_company', data);
  if (d.ok) { showToast('Company updated!', 'success'); setTimeout(() => location.reload(), 1000); }
  else showToast(d.message || 'Failed to update company.', 'error');
}

function viewCompany(id) { window.location.href = '/super/companies?view=' + id; }

async function toggleStatus(id, newStatus) {
  const label = newStatus === 'active' ? 'activate' : 'suspend';
  if (!confirm('Are you sure you want to ' + label + ' this company?')) return;
  const d = await apiPost('/api/v1/admin?action=company_status', { id, status: newStatus });
  if (d.ok) { showToast('Status updated.', 'success'); setTimeout(() => location.reload(), 800); }
  else showToast(d.message || 'Failed.', 'error');
}

async function archiveCompany(id) {
  if (!confirm('Archive this company? Users will lose access.')) return;
  const d = await apiPost('/api/v1/admin?action=company_status', { id, status: 'archived' });
  if (d.ok) { showToast('Company archived.', 'success'); setTimeout(() => location.reload(), 800); }
  else showToast(d.message || 'Failed.', 'error');
}

async function deleteCompany(id, name) {
  if (!confirm('PERMANENTLY delete "' + name + '"? This cannot be undone.')) return;
  if (!confirm('Are you ABSOLUTELY sure? All data will be lost.')) return;
  const d = await apiPost('/api/v1/admin?action=delete_company', { id });
  if (d.ok) { showToast('Company deleted.', 'success'); setTimeout(() => location.reload(), 800); }
  else showToast(d.message || 'Failed.', 'error');
}

function impersonateOwner(id) {
  if (!confirm('Impersonate this company owner?')) return;
  window.location.href = '/super/impersonate?tenant_id=' + id;
}

// ── Bulk selection ────────────────────────────────────────────────────────────
function updateSelection() {
  const boxes = document.querySelectorAll('.row-check:checked');
  const bar   = document.getElementById('bulkBar');
  const cnt   = document.getElementById('bulkCount');
  cnt.textContent = boxes.length + ' selected';
  if (boxes.length > 0) { bar.classList.remove('hidden'); bar.classList.add('flex'); }
  else { bar.classList.add('hidden'); bar.classList.remove('flex'); }
}
function toggleAll(master) {
  document.querySelectorAll('.row-check').forEach(cb => { cb.checked = master.checked; });
  updateSelection();
}
function clearSelection() {
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
  document.querySelector('input[type=checkbox][onchange]').checked = false;
  updateSelection();
}
async function bulkAction(action) {
  const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
  if (!ids.length) return;
  if (!confirm('Apply "' + action + '" to ' + ids.length + ' companies?')) return;
  const d = await apiPost('/api/v1/admin?action=bulk_companies', { ids, action });
  if (d.ok) { showToast('Bulk action applied.', 'success'); setTimeout(() => location.reload(), 800); }
  else showToast(d.message || 'Failed.', 'error');
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => {
    if (typeof showToast === 'function') showToast('Copied: ' + text, 'success');
  });
}

async function sendCredentials(id, name) {
  const newPass = prompt('Set a new password for "' + name + '" (min 8 chars):\nLeave blank to just send their current login email.');
  if (newPass === null) return; // cancelled
  if (newPass && newPass.length < 8) { alert('Password must be at least 8 characters.'); return; }
  const d = await apiPost('/api/v1/admin?action=reset_company_password', { id, password: newPass });
  if (d.ok) {
    const info = 'Login: ' + (d.data?.email || '') + (newPass ? '\nNew Password: ' + newPass : '');
    if (confirm('✓ Done!\n\n' + info + '\n\nCopy to clipboard?')) {
      navigator.clipboard.writeText(info);
    }
  } else {
    showToast(d.message || 'Failed.', 'error');
  }
}

// Auto-generate slug from name
document.querySelector('[name=name]')?.addEventListener('input', function() {
  const slugField = document.querySelector('#addCompanyForm [name=slug]');
  if (slugField && !slugField.dataset.touched) {
    slugField.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  }
});
document.querySelector('#addCompanyForm [name=slug]')?.addEventListener('input', function() {
  this.dataset.touched = '1';
});
</script>
