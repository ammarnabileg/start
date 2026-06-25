<?php
ob_start();
$pageTitle = 'My Profile';
$u = Auth::user();
$db = Database::getInstance();

// Activity log
$activityLog = $db->fetchAll(
    "SELECT action, description, created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
    [$u['id']]
) ?: [];

$activeTab = $_GET['tab'] ?? 'info';
?>
<meta name="csrf" content="<?= htmlspecialchars($req->csrf()) ?>">

<div class="max-w-3xl mx-auto space-y-6">
  <!-- Page Header -->
  <div>
    <h1 class="text-2xl font-bold text-gray-900">My Profile</h1>
    <p class="text-sm text-gray-500 mt-1">Manage your personal information and account settings</p>
  </div>

  <!-- Profile Card -->
  <div class="bg-white rounded-xl border border-gray-200 p-6 flex items-center gap-5">
    <div class="w-16 h-16 rounded-full bg-indigo-600 flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
      <?= strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)) ?>
    </div>
    <div>
      <div class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
      <div class="text-sm text-gray-500"><?= htmlspecialchars($u['email']) ?></div>
      <div class="flex gap-2 mt-2">
        <?php foreach ($u['roles'] ?? [] as $role): ?>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700"><?= htmlspecialchars($role) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Tab Nav -->
  <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
    <?php foreach (['info' => 'Personal Info', 'password' => 'Change Password', 'activity' => 'Activity Log'] as $tab => $label): ?>
    <button onclick="switchTab('<?= $tab ?>')"
      id="tab-<?= $tab ?>"
      class="flex-1 text-sm font-medium py-2 px-4 rounded-lg transition-colors <?= $activeTab === $tab ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">
      <?= $label ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- Personal Info Tab -->
  <div id="panel-info" class="<?= $activeTab !== 'info' ? 'hidden' : '' ?>">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <h2 class="text-base font-semibold text-gray-900 mb-5">Personal Information</h2>
      <div id="profileAlert" class="hidden mb-4"></div>
      <form id="profileForm" onsubmit="saveProfile(event)">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name</label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($u['first_name']) ?>"
              class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
            <input type="text" name="last_name" value="<?= htmlspecialchars($u['last_name']) ?>"
              class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>"
              class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($u['phone'] ?? '') ?>"
              class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" placeholder="+1 (555) 000-0000">
          </div>
        </div>
        <div class="mt-6 flex justify-end">
          <button type="submit" id="saveProfileBtn"
            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-60">
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Change Password Tab -->
  <div id="panel-password" class="<?= $activeTab !== 'password' ? 'hidden' : '' ?>">
    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <h2 class="text-base font-semibold text-gray-900 mb-5">Change Password</h2>
      <div id="passwordAlert" class="hidden mb-4"></div>
      <form id="passwordForm" onsubmit="changePassword(event)" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Current Password</label>
          <input type="password" name="current_password"
            class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
          <input type="password" name="new_password" id="newPassword"
            class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" required minlength="8">
          <p class="text-xs text-gray-400 mt-1.5">Minimum 8 characters</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm New Password</label>
          <input type="password" name="confirm_password" id="confirmPassword"
            class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" required minlength="8">
        </div>
        <div class="flex justify-end">
          <button type="submit"
            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            Update Password
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Activity Log Tab -->
  <div id="panel-activity" class="<?= $activeTab !== 'activity' ? 'hidden' : '' ?>">
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-base font-semibold text-gray-900">Recent Activity</h2>
        <p class="text-xs text-gray-400 mt-0.5">Your last 10 account actions</p>
      </div>
      <?php if (empty($activityLog)): ?>
      <div class="px-6 py-12 text-center">
        <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <p class="text-sm text-gray-500">No activity recorded yet</p>
      </div>
      <?php else: ?>
      <ul class="divide-y divide-gray-100">
        <?php foreach ($activityLog as $entry): ?>
        <li class="px-6 py-4 flex items-start gap-3">
          <span class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </span>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($entry['action'] ?? '') ?></div>
            <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($entry['description'] ?? '') ?></div>
          </div>
          <span class="text-xs text-gray-400 flex-shrink-0 whitespace-nowrap">
            <?= !empty($entry['created_at']) ? date('M j, Y g:i A', strtotime($entry['created_at'])) : '' ?>
          </span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function switchTab(tab) {
  ['info','password','activity'].forEach(function(t) {
    document.getElementById('panel-' + t).classList.toggle('hidden', t !== tab);
    var btn = document.getElementById('tab-' + t);
    if (t === tab) {
      btn.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
      btn.classList.remove('text-gray-500');
    } else {
      btn.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
      btn.classList.add('text-gray-500');
    }
  });
  history.replaceState(null, '', '?tab=' + tab);
}

function showAlert(id, msg, type) {
  var el = document.getElementById(id);
  var isOk = type === 'success';
  el.className = 'mb-4 px-4 py-3 rounded-lg text-sm ' + (isOk ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200');
  el.textContent = msg;
  el.classList.remove('hidden');
  if (isOk) setTimeout(function(){ el.classList.add('hidden'); }, 4000);
}

async function saveProfile(e) {
  e.preventDefault();
  var btn = document.getElementById('saveProfileBtn');
  btn.disabled = true; btn.textContent = 'Saving…';
  var fd = new FormData(e.target);
  var body = {};
  fd.forEach(function(v,k){ body[k]=v; });
  try {
    var res = await fetch('/api/v1/profile', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf"]').content},
      body: JSON.stringify(body)
    });
    var json = await res.json();
    if (json.ok) {
      showAlert('profileAlert', 'Profile updated successfully.', 'success');
    } else {
      showAlert('profileAlert', json.message || 'Update failed. Please try again.', 'error');
    }
  } catch(err) {
    showAlert('profileAlert', 'Network error. Please try again.', 'error');
  } finally {
    btn.disabled = false; btn.textContent = 'Save Changes';
  }
}

async function changePassword(e) {
  e.preventDefault();
  var np = document.getElementById('newPassword').value;
  var cp = document.getElementById('confirmPassword').value;
  if (np !== cp) { showAlert('passwordAlert', 'Passwords do not match.', 'error'); return; }
  var fd = new FormData(e.target);
  var body = {};
  fd.forEach(function(v,k){ body[k]=v; });
  try {
    var res = await fetch('/api/v1/profile/password', {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf"]').content},
      body: JSON.stringify(body)
    });
    var json = await res.json();
    if (json.ok) {
      showAlert('passwordAlert', 'Password changed successfully.', 'success');
      e.target.reset();
    } else {
      showAlert('passwordAlert', json.message || 'Failed to change password.', 'error');
    }
  } catch(err) {
    showAlert('passwordAlert', 'Network error. Please try again.', 'error');
  }
}
</script>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
?>
