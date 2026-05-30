<?php
// edit-community.php - Edit community settings (owner only)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$current_user = get_auth_user();
$community_id = (int)($_GET['id'] ?? 0);

if (!$community_id) { header('Location: /index.php'); exit; }

$community = db_fetch('SELECT * FROM communities WHERE id = ? AND is_active = 1', [$community_id]);
if (!$community) { http_response_code(404); die('Community not found.'); }

// Only the owner can edit
if ((int)$community['owner_id'] !== (int)$current_user['id']) {
    header('Location: /community.php?slug=' . urlencode($community['slug']));
    exit;
}

$community_links = db_fetch_all('SELECT * FROM community_links WHERE community_id = ? ORDER BY sort_order', [$community_id]);
$all_topics = db_fetch_all('SELECT * FROM topics WHERE community_id = ? ORDER BY sort_order', [$community_id]);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $name         = trim($_POST['name'] ?? '');
        $slug         = trim($_POST['slug'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $short_bio    = trim($_POST['short_bio'] ?? '');
        $category     = $_POST['category'] ?? $community['category'];
        $type         = $_POST['type'] ?? $community['type'];
        $pricing      = $_POST['pricing'] ?? $community['pricing'];
        $price        = (float)($_POST['price'] ?? 0);
        $price_interval = $_POST['price_interval'] ?? $community['price_interval'];
        $logo         = trim($_POST['logo'] ?? $community['logo']);
        $banner       = trim($_POST['banner'] ?? $community['banner']);
        $is_active    = (int)($_POST['is_active'] ?? 1);

        if (!$name) {
            $error = 'Community name is required.';
        } elseif (!$slug || !preg_match('/^[a-z0-9-]{3,100}$/', $slug)) {
            $error = 'Slug must be 3-100 lowercase letters, numbers, or hyphens.';
        } elseif ($slug !== $community['slug'] && db_fetch('SELECT id FROM communities WHERE slug = ?', [$slug])) {
            $error = 'This slug is already taken.';
        } else {
            db_execute(
                'UPDATE communities SET name=?, slug=?, description=?, short_bio=?, category=?, type=?, pricing=?, price=?, price_interval=?, logo=?, banner=?, is_active=? WHERE id=?',
                [$name, $slug, $description, $short_bio, $category, $type, $pricing, $price, $price_interval, $logo, $banner, $is_active, $community_id]
            );

            // Update links
            db_execute('DELETE FROM community_links WHERE community_id = ?', [$community_id]);
            $link_names = $_POST['link_name'] ?? [];
            $link_urls  = $_POST['link_url'] ?? [];
            foreach ($link_names as $i => $ln) {
                $lu = $link_urls[$i] ?? '';
                if (trim($ln) && trim($lu)) {
                    db_insert('INSERT INTO community_links (community_id, name, url, sort_order) VALUES (?,?,?,?)', [$community_id, trim($ln), trim($lu), $i]);
                }
            }

            header('Location: /community.php?slug=' . urlencode($slug) . '&updated=1');
            exit;
        }
    }
}

$categories = ['trending', 'hobbies', 'music', 'money', 'celebrity', 'tech', 'health', 'sports', 'self_improvement', 'relationships'];
$cat_labels = ['trending'=>'Trending', 'hobbies'=>'Hobbies', 'music'=>'Music', 'money'=>'Money', 'celebrity'=>'Celebrity', 'tech'=>'Tech', 'health'=>'Health', 'sports'=>'Sports', 'self_improvement'=>'Self Improvement', 'relationships'=>'Relationships'];

$page_title = 'Edit Community: ' . $community['name'];
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
    <a href="/community.php?slug=<?= e($community['slug']) ?>" class="hover:text-primary-600 dark:hover:text-primary-400"><?= e($community['name']) ?></a>
    <span>›</span>
    <span class="text-gray-700 dark:text-gray-300 font-medium">Edit Community</span>
  </nav>

  <div class="mb-8">
    <h1 class="text-3xl font-black text-gray-900 dark:text-white mb-1">Edit Community</h1>
    <p class="text-gray-500 dark:text-gray-400">Update settings for <?= e($community['name']) ?></p>
  </div>

  <?php if ($error): ?>
    <div class="mb-5 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-2xl text-red-600 dark:text-red-400 text-sm">❌ <?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <!-- Basic Info -->
    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-100 dark:border-white/10 shadow-airbnb p-6">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Basic Information</h2>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Community Name <span class="text-red-500">*</span></label>
          <input type="text" name="name" value="<?= e($community['name']) ?>" required
            class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200 shadow-airbnb">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">URL Slug <span class="text-red-500">*</span></label>
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400 dark:text-gray-500 flex-shrink-0">discover.com/</span>
            <input type="text" name="slug" value="<?= e($community['slug']) ?>" required pattern="[a-z0-9-]{3,100}"
              class="flex-1 px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200 font-mono text-sm shadow-airbnb">
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Category</label>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <?php foreach ($categories as $cat): ?>
              <label class="cursor-pointer">
                <input type="radio" name="category" value="<?= $cat ?>" <?= $community['category'] === $cat ? 'checked' : '' ?> class="sr-only peer">
                <div class="px-3 py-2 rounded-xl border-2 border-gray-200 dark:border-white/10 text-center text-xs font-medium text-gray-600 dark:text-gray-400 peer-checked:border-primary-500 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:text-primary-600 transition-all cursor-pointer">
                  <?= $cat_labels[$cat] ?>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Access Type</label>
          <div class="grid grid-cols-2 gap-3">
            <?php foreach (['public' => ['label' => 'Public', 'icon' => '🌐'], 'private' => ['label' => 'Private', 'icon' => '🔒']] as $val => $opt): ?>
              <label class="cursor-pointer">
                <input type="radio" name="type" value="<?= $val ?>" <?= $community['type'] === $val ? 'checked' : '' ?> class="sr-only peer">
                <div class="p-4 rounded-xl border-2 border-gray-200 dark:border-white/10 peer-checked:border-primary-500 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 transition-all text-center">
                  <div class="text-xl mb-1"><?= $opt['icon'] ?></div>
                  <div class="font-semibold text-sm text-gray-900 dark:text-white"><?= $opt['label'] ?></div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Short Description</label>
          <input type="text" name="short_bio" value="<?= e($community['short_bio'] ?? '') ?>" maxlength="200"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200 shadow-airbnb"
            placeholder="One-line description (max 200 chars)">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Full Description</label>
          <textarea name="description" rows="5"
            class="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none dark:text-gray-200 shadow-airbnb"><?= e($community['description'] ?? '') ?></textarea>
        </div>
        <div>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" <?= $community['is_active'] ? 'checked' : '' ?>
              class="w-4 h-4 text-primary-600 rounded focus:ring-primary-500">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Community is active (visible publicly)</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Media -->
    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-100 dark:border-white/10 shadow-airbnb p-6">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Media & Branding</h2>
      <div class="space-y-5">
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Community Logo</label>
          <div class="flex items-center gap-4">
            <?php if ($community['logo']): ?>
              <img id="logo-preview" src="<?= e($community['logo']) ?>" class="w-16 h-16 rounded-xl object-cover border-2 border-gray-200 dark:border-white/10">
            <?php else: ?>
              <div id="logo-preview" class="w-16 h-16 rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-2xl flex-shrink-0"><?= strtoupper(substr($community['name'], 0, 1)) ?></div>
            <?php endif; ?>
            <div>
              <input type="file" id="logo-upload" accept="image/*" class="hidden" onchange="uploadMedia(this, 'community_logo', 'logo-preview', 'logo-url')">
              <label for="logo-upload" class="cursor-pointer px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 transition-all">Change Logo</label>
            </div>
          </div>
          <input type="hidden" name="logo" id="logo-url" value="<?= e($community['logo'] ?? '') ?>">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Banner Image</label>
          <div class="h-28 rounded-2xl overflow-hidden relative mb-2 bg-gradient-to-br from-primary-600 to-accent-500">
            <?php if ($community['banner']): ?>
              <img id="banner-preview" src="<?= e($community['banner']) ?>" class="w-full h-full object-cover">
            <?php else: ?>
              <img id="banner-preview" src="" class="w-full h-full object-cover hidden">
            <?php endif; ?>
          </div>
          <input type="file" id="banner-upload" accept="image/*" class="hidden" onchange="uploadMedia(this, 'community_banner', 'banner-preview', 'banner-url')">
          <label for="banner-upload" class="cursor-pointer px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 transition-all">Change Banner</label>
          <input type="hidden" name="banner" id="banner-url" value="<?= e($community['banner'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- Pricing -->
    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-100 dark:border-white/10 shadow-airbnb p-6">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Pricing</h2>
      <div class="grid gap-3 mb-4">
        <?php foreach (['free'=>['icon'=>'🆓','label'=>'Free','desc'=>'Anyone can join for free'], 'paid'=>['icon'=>'💰','label'=>'Paid','desc'=>'Charge a recurring fee'], 'free_trial'=>['icon'=>'🎁','label'=>'Free Trial','desc'=>'Free to try, then paid']] as $val => $opt): ?>
          <label class="cursor-pointer">
            <input type="radio" name="pricing" value="<?= $val ?>" <?= $community['pricing'] === $val ? 'checked' : '' ?> class="sr-only peer" onchange="togglePaidFields()">
            <div class="flex items-center gap-4 p-4 rounded-2xl border-2 border-gray-200 dark:border-white/10 peer-checked:border-primary-500 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 transition-all">
              <div class="text-2xl"><?= $opt['icon'] ?></div>
              <div>
                <div class="font-bold text-gray-900 dark:text-white"><?= $opt['label'] ?></div>
                <div class="text-sm text-gray-500 dark:text-gray-400"><?= $opt['desc'] ?></div>
              </div>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
      <div id="paid-fields" class="<?= in_array($community['pricing'], ['paid', 'free_trial']) ? '' : 'hidden' ?> grid grid-cols-2 gap-4 p-4 bg-gray-50 dark:bg-white/5 rounded-2xl">
        <div>
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5 block">Price ($)</label>
          <input type="number" name="price" value="<?= e($community['price'] ?? '0') ?>" step="0.01" min="0"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
        </div>
        <div>
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5 block">Billing</label>
          <select name="price_interval" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
            <option value="monthly" <?= $community['price_interval'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
            <option value="yearly" <?= $community['price_interval'] === 'yearly' ? 'selected' : '' ?>>Yearly</option>
            <option value="one_time" <?= $community['price_interval'] === 'one_time' ? 'selected' : '' ?>>One Time</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Links -->
    <div class="bg-white dark:bg-[#1a1a1a] rounded-2xl border border-gray-100 dark:border-white/10 shadow-airbnb p-6">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Community Links</h2>
      <div id="links-container" class="space-y-2 mb-3">
        <?php foreach ($community_links as $link): ?>
          <div class="flex items-center gap-2 link-row">
            <input type="text" name="link_name[]" value="<?= e($link['name']) ?>" placeholder="Label"
              class="w-28 px-3 py-2 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none dark:text-gray-200">
            <input type="url" name="link_url[]" value="<?= e($link['url']) ?>" placeholder="https://..."
              class="flex-1 px-3 py-2 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none dark:text-gray-200">
            <button type="button" onclick="this.closest('.link-row').remove()" class="p-1.5 text-red-400 hover:text-red-600 rounded-lg">✕</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" onclick="addLink()" class="flex items-center gap-2 text-sm text-primary-600 dark:text-primary-400 hover:underline font-medium">+ Add Link</button>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between">
      <a href="/community.php?slug=<?= e($community['slug']) ?>" class="px-5 py-3 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-400 rounded-xl font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">Cancel</a>
      <button type="submit"
        class="px-8 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-bold hover:shadow-xl hover:shadow-primary-500/30 transition-all hover:-translate-y-0.5 flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        Save Changes
      </button>
    </div>
  </form>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
function addLink() {
  const container = document.getElementById('links-container');
  const row = document.createElement('div');
  row.className = 'flex items-center gap-2 link-row';
  row.innerHTML = `
    <input type="text" name="link_name[]" placeholder="Label" class="w-28 px-3 py-2 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none dark:text-gray-200">
    <input type="url" name="link_url[]" placeholder="https://..." class="flex-1 px-3 py-2 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none dark:text-gray-200">
    <button type="button" onclick="this.closest('.link-row').remove()" class="p-1.5 text-red-400 hover:text-red-600 rounded-lg">✕</button>`;
  container.appendChild(row);
}

function togglePaidFields() {
  const pricing = document.querySelector('[name="pricing"]:checked')?.value;
  const paidFields = document.getElementById('paid-fields');
  if (pricing === 'paid' || pricing === 'free_trial') paidFields.classList.remove('hidden');
  else paidFields.classList.add('hidden');
}

async function uploadMedia(input, type, previewId, hiddenId) {
  const file = input.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('file', file);
  fd.append('type', type);
  try {
    const res = await fetch('/api/upload.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.url) {
      const preview = document.getElementById(previewId);
      if (preview) { preview.src = data.url; preview.classList.remove('hidden'); }
      document.getElementById(hiddenId).value = data.url;
      showToast('Uploaded!');
    } else {
      showToast(data.error || 'Upload failed', 'error');
    }
  } catch(e) {
    showToast('Upload failed', 'error');
  }
}
</script>
