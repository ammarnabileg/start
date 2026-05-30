<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$current_user = get_auth_user();
$error = '';
$success = '';
$step = max(1, min(5, (int)($_GET['step'] ?? 1)));

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $category = $_POST['category'] ?? 'trending';
            $type = $_POST['type'] ?? 'public';
            $description = trim($_POST['description'] ?? '');
            $short_bio = trim($_POST['short_bio'] ?? '');
            $logo = trim($_POST['logo'] ?? '');
            $banner = trim($_POST['banner'] ?? '');
            $pricing = $_POST['pricing'] ?? 'free';
            $price = (float)($_POST['price'] ?? 0);
            $price_interval = $_POST['price_interval'] ?? 'monthly';
            $language = $_POST['language'] ?? 'en';

            if (!$name) { $error = 'Community name is required.'; }
            elseif (!$slug) { $error = 'Slug is required.'; }
            elseif (!preg_match('/^[a-z0-9-]{3,100}$/', $slug)) { $error = 'Slug must be 3-100 lowercase letters, numbers, or hyphens.'; }
            elseif (db_fetch('SELECT id FROM communities WHERE slug = ?', [$slug])) { $error = 'This slug is already taken.'; }
            else {
                $community_id = db_insert(
                    'INSERT INTO communities (owner_id, name, slug, description, short_bio, logo, banner, category, type, pricing, price, price_interval, language) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$current_user['id'], $name, $slug, $description, $short_bio, $logo, $banner, $category, $type, $pricing, $price, $price_interval, $language]
                );
                // Add owner as member
                db_insert('INSERT INTO memberships (user_id, community_id, role, status) VALUES (?,?,?,?)',
                    [$current_user['id'], $community_id, 'owner', 'approved']);

                // Default topics
                $default_topics = ['General', 'Announcements', 'Q&A'];
                foreach ($default_topics as $i => $tn) {
                    db_insert('INSERT INTO topics (community_id, name, sort_order) VALUES (?,?,?)', [$community_id, $tn, $i]);
                }

                // Save links
                $link_names = $_POST['link_name'] ?? [];
                $link_urls = $_POST['link_url'] ?? [];
                foreach ($link_names as $i => $ln) {
                    $lu = $link_urls[$i] ?? '';
                    if (trim($ln) && trim($lu)) {
                        db_insert('INSERT INTO community_links (community_id, name, url, sort_order) VALUES (?,?,?,?)', [$community_id, trim($ln), trim($lu), $i]);
                    }
                }

                // Award badge
                $badge = db_fetch('SELECT id FROM badges WHERE name = "First Steps" AND community_id IS NULL');
                if ($badge) db_insert('INSERT IGNORE INTO user_badges (user_id, badge_id, community_id) VALUES (?,?,?)', [$current_user['id'], $badge['id'], $community_id]);

                // Update member count
                db_execute('UPDATE communities SET member_count = 1 WHERE id = ?', [$community_id]);

                header('Location: /community.php?slug=' . urlencode($slug));
                exit;
            }
        }
    }
}

$categories = ['trending', 'hobbies', 'music', 'money', 'celebrity', 'tech', 'health', 'sports', 'self_improvement', 'relationships'];
$cat_labels = ['trending'=>'Trending', 'hobbies'=>'Hobbies', 'music'=>'Music', 'money'=>'Money', 'celebrity'=>'Celebrity', 'tech'=>'Tech', 'health'=>'Health', 'sports'=>'Sports', 'self_improvement'=>'Self Improvement', 'relationships'=>'Relationships'];

$page_title = 'Create Community';
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="text-center mb-8">
    <h1 class="text-3xl font-black text-gray-900 dark:text-white mb-2">Create Your Community</h1>
    <p class="text-gray-500 dark:text-gray-400">Build a space for your audience to learn, connect, and grow</p>
  </div>

  <?php if ($error): ?>
    <div class="mb-5 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-xl text-red-600 dark:text-red-400 text-sm">❌ <?= e($error) ?></div>
  <?php endif; ?>

  <div class="bg-white dark:bg-[#1a1a1a] rounded-3xl border border-gray-100 dark:border-white/10 shadow-xl overflow-hidden">
    <!-- Step Indicator -->
    <div class="bg-gradient-to-r from-primary-600 to-accent-500 px-8 py-5">
      <div class="flex items-center justify-between">
        <?php
        $steps = ['Basic Info', 'Media', 'Pricing', 'Links', 'Review'];
        foreach ($steps as $i => $s_label):
            $s_num = $i + 1;
            $is_active = $step === $s_num;
            $is_done = $step > $s_num;
        ?>
          <div class="flex items-center <?= $i < count($steps) - 1 ? 'flex-1' : '' ?>">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                <?= $is_done ? 'bg-white text-primary-600' : ($is_active ? 'bg-white/30 border-2 border-white text-white' : 'bg-white/20 text-white/70') ?>">
                <?= $is_done ? '✓' : $s_num ?>
              </div>
              <span class="text-xs font-medium text-white <?= $is_active ? 'opacity-100' : 'opacity-60' ?> hidden sm:block"><?= $s_label ?></span>
            </div>
            <?php if ($i < count($steps) - 1): ?>
              <div class="flex-1 h-0.5 bg-white/20 mx-3"></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <form method="POST" id="community-form" class="p-8">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create">

      <!-- Step 1: Basic Info -->
      <div id="step-1" class="<?= $step === 1 ? '' : 'hidden' ?>">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5">Basic Information</h2>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Community Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="community-name" value="<?= e($_POST['name'] ?? '') ?>"
              oninput="generateSlug(this.value)"
              class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200"
              placeholder="e.g. Tech Learning Hub" required>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Community URL Slug <span class="text-red-500">*</span></label>
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-400 dark:text-gray-500 flex-shrink-0">discover.com/</span>
              <input type="text" name="slug" id="community-slug" value="<?= e($_POST['slug'] ?? '') ?>"
                class="flex-1 px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200 font-mono text-sm"
                placeholder="my-community-name" required pattern="[a-z0-9-]{3,100}">
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Lowercase letters, numbers, and hyphens only</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Category <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
              <?php foreach ($categories as $cat): ?>
                <label class="cursor-pointer">
                  <input type="radio" name="category" value="<?= $cat ?>" <?= (($_POST['category'] ?? 'trending') === $cat) ? 'checked' : '' ?> class="sr-only peer">
                  <div class="px-3 py-2 rounded-xl border-2 border-gray-200 dark:border-white/10 text-center text-xs font-medium text-gray-600 dark:text-gray-400 peer-checked:border-primary-500 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:text-primary-600 dark:peer-checked:text-primary-400 transition-all cursor-pointer hover:border-gray-300 dark:hover:border-gray-500">
                    <?= $cat_labels[$cat] ?>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Access Type</label>
            <div class="grid grid-cols-2 gap-3">
              <?php foreach (['public' => ['label' => 'Public', 'desc' => 'Anyone can join', 'icon' => '🌐'], 'private' => ['label' => 'Private', 'desc' => 'Approval required', 'icon' => '🔒']] as $val => $opt): ?>
                <label class="cursor-pointer">
                  <input type="radio" name="type" value="<?= $val ?>" <?= (($_POST['type'] ?? 'public') === $val) ? 'checked' : '' ?> class="sr-only peer">
                  <div class="p-4 rounded-xl border-2 border-gray-200 dark:border-white/10 peer-checked:border-primary-500 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 transition-all text-center">
                    <div class="text-xl mb-1"><?= $opt['icon'] ?></div>
                    <div class="font-semibold text-sm text-gray-900 dark:text-white"><?= $opt['label'] ?></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400"><?= $opt['desc'] ?></div>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Short Description (shows on card)</label>
            <input type="text" name="short_bio" value="<?= e($_POST['short_bio'] ?? '') ?>" maxlength="200"
              class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200"
              placeholder="One line summary of your community (max 200 chars)">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Full Description</label>
            <textarea name="description" rows="5"
              class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none dark:text-gray-200 placeholder-gray-400"
              placeholder="Tell potential members what this community is about..."><?= e($_POST['description'] ?? '') ?></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Language</label>
            <select name="language" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
              <option value="en" <?= (($_POST['language'] ?? 'en') === 'en') ? 'selected' : '' ?>>English</option>
              <option value="ar" <?= (($_POST['language'] ?? '') === 'ar') ? 'selected' : '' ?>>Arabic (العربية)</option>
              <option value="fr" <?= (($_POST['language'] ?? '') === 'fr') ? 'selected' : '' ?>>French (Français)</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end mt-6">
          <button type="button" onclick="goToStep(2)" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all hover:-translate-y-0.5">Next: Media →</button>
        </div>
      </div>

      <!-- Step 2: Media -->
      <div id="step-2" class="hidden">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5">Media & Branding</h2>
        <div class="space-y-5">
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Community Logo</label>
            <div class="flex items-center gap-4">
              <img id="logo-preview" src="" class="w-16 h-16 rounded-xl object-cover border-2 border-gray-200 dark:border-white/10 hidden">
              <div id="logo-placeholder" class="w-16 h-16 rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-2xl flex-shrink-0">D</div>
              <div>
                <input type="file" id="logo-upload" accept="image/*" class="hidden"
                       onchange="uploadFile(this, 'community_logo', 'logo-preview', 'logo_url')">
                <label for="logo-upload" class="cursor-pointer px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700">
                  Upload Logo
                </label>
                <p class="text-xs text-gray-500 mt-1">Recommended: 200x200px</p>
              </div>
            </div>
            <input type="hidden" name="logo" id="logo_url" value="<?= e($_POST['logo'] ?? '') ?>">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Banner Image</label>
            <div class="h-32 rounded-2xl bg-gradient-to-br from-primary-600 to-accent-500 overflow-hidden relative mb-2 flex items-center justify-center" id="banner-preview-div">
              <span class="text-white/50 text-sm" id="banner-placeholder-text">Banner preview</span>
              <img id="banner-preview" src="" class="absolute inset-0 w-full h-full object-cover hidden">
            </div>
            <div>
              <input type="file" id="banner-upload" accept="image/*" class="hidden"
                     onchange="uploadFile(this, 'community_banner', 'banner-preview', 'banner_url'); document.getElementById('banner-placeholder-text').style.display='none'; document.getElementById('banner-preview').classList.remove('hidden');">
              <label for="banner-upload" class="cursor-pointer px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700">
                Upload Banner
              </label>
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Recommended: 1200×300px. Max 5MB.</p>
            </div>
            <input type="hidden" name="banner" id="banner_url" value="<?= e($_POST['banner'] ?? '') ?>">
          </div>
        </div>
        <div class="flex justify-between mt-6">
          <button type="button" onclick="goToStep(1)" class="px-5 py-2.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium">← Back</button>
          <button type="button" onclick="goToStep(3)" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all hover:-translate-y-0.5">Next: Pricing →</button>
        </div>
      </div>

      <!-- Step 3: Pricing -->
      <div id="step-3" class="hidden">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5">Pricing Model</h2>
        <div class="grid gap-4">
          <?php
          $pricing_opts = [
              'free' => ['icon' => '🆓', 'label' => 'Free', 'desc' => 'Anyone can join for free'],
              'paid' => ['icon' => '💰', 'label' => 'Paid Membership', 'desc' => 'Charge a recurring fee'],
              'free_trial' => ['icon' => '🎁', 'label' => 'Free Trial', 'desc' => 'Free to try, then paid'],
          ];
          foreach ($pricing_opts as $val => $opt):
          ?>
            <label class="cursor-pointer">
              <input type="radio" name="pricing" value="<?= $val ?>" <?= (($_POST['pricing'] ?? 'free') === $val) ? 'checked' : '' ?>
                class="sr-only peer" onchange="togglePricingFields()">
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

        <div id="paid-fields" class="<?= in_array($_POST['pricing'] ?? 'free', ['paid', 'free_trial']) ? '' : 'hidden' ?> mt-5 space-y-4 p-4 bg-gray-50 dark:bg-white/5 rounded-2xl">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5 block">Price ($)</label>
              <input type="number" name="price" value="<?= e($_POST['price'] ?? '9.99') ?>" step="0.01" min="0"
                class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200"
                placeholder="9.99">
            </div>
            <div>
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5 block">Billing Interval</label>
              <select name="price_interval" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-[#2a2a2a] focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-200">
                <option value="monthly" <?= (($_POST['price_interval'] ?? 'monthly') === 'monthly') ? 'selected' : '' ?>>Monthly</option>
                <option value="yearly" <?= (($_POST['price_interval'] ?? '') === 'yearly') ? 'selected' : '' ?>>Yearly</option>
                <option value="one_time" <?= (($_POST['price_interval'] ?? '') === 'one_time') ? 'selected' : '' ?>>One Time</option>
              </select>
            </div>
          </div>
        </div>

        <div class="flex justify-between mt-6">
          <button type="button" onclick="goToStep(2)" class="px-5 py-2.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium">← Back</button>
          <button type="button" onclick="goToStep(4)" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all hover:-translate-y-0.5">Next: Links →</button>
        </div>
      </div>

      <!-- Step 4: Links -->
      <div id="step-4" class="hidden">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5">Community Links</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Add social media or website links to display on your community sidebar (optional).</p>
        <div id="comm-links-container" class="space-y-2 mb-3">
          <!-- Empty by default -->
        </div>
        <button type="button" onclick="addCommLinkRow()"
          class="flex items-center gap-2 text-sm text-primary-600 dark:text-primary-400 hover:underline font-medium">
          + Add Link
        </button>
        <div class="flex justify-between mt-6">
          <button type="button" onclick="goToStep(3)" class="px-5 py-2.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium">← Back</button>
          <button type="button" onclick="goToStep(5)" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-semibold hover:shadow-lg transition-all hover:-translate-y-0.5">Review →</button>
        </div>
      </div>

      <!-- Step 5: Review -->
      <div id="step-5" class="hidden">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-5">Review & Create</h2>
        <div id="review-content" class="space-y-4 mb-6">
          <div class="bg-gray-50 dark:bg-white/5 rounded-2xl p-5">
            <div id="review-preview" class="text-sm text-gray-700 dark:text-gray-300 space-y-2">
              <div class="flex gap-3">
                <div id="review-logo" class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-xl overflow-hidden flex-shrink-0">D</div>
                <div>
                  <h3 id="review-name" class="text-lg font-black text-gray-900 dark:text-white">-</h3>
                  <p id="review-slug" class="text-xs text-primary-600 dark:text-primary-400">discover.com/-</p>
                  <p id="review-bio" class="text-sm text-gray-500 dark:text-gray-400 mt-1">-</p>
                </div>
              </div>
              <div class="grid grid-cols-3 gap-3 pt-3 border-t border-gray-200 dark:border-white/10">
                <div class="text-center"><div id="review-type" class="font-semibold text-gray-900 dark:text-white text-sm">-</div><div class="text-xs text-gray-400 dark:text-gray-500">Type</div></div>
                <div class="text-center"><div id="review-pricing" class="font-semibold text-gray-900 dark:text-white text-sm">-</div><div class="text-xs text-gray-400 dark:text-gray-500">Pricing</div></div>
                <div class="text-center"><div id="review-category" class="font-semibold text-gray-900 dark:text-white text-sm capitalize">-</div><div class="text-xs text-gray-400 dark:text-gray-500">Category</div></div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-between">
          <button type="button" onclick="goToStep(4)" class="px-5 py-2.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium">← Back</button>
          <button type="submit"
            class="px-8 py-3 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-xl font-bold hover:shadow-xl hover:shadow-primary-500/30 transition-all hover:-translate-y-0.5 flex items-center gap-2">
            🚀 Create Community!
          </button>
        </div>
      </div>
    </form>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
async function uploadFile(input, type, previewId, hiddenId) {
  const file = input.files[0];
  if (!file) return;
  const formData = new FormData();
  formData.append('file', file);
  formData.append('type', type);
  const label = input.nextElementSibling;
  const orig = label ? label.textContent : '';
  if (label) label.textContent = 'Uploading...';
  try {
    const res = await fetch('/api/upload.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.url) {
      const preview = document.getElementById(previewId);
      if (preview) { preview.src = data.url; preview.classList.remove('hidden'); }
      const hidden = document.getElementById(hiddenId);
      if (hidden) hidden.value = data.url;
      // Hide placeholder for logo
      const placeholder = document.getElementById('logo-placeholder');
      if (placeholder && type === 'community_logo') placeholder.style.display = 'none';
    } else {
      alert(data.error || 'Upload failed');
    }
  } catch(e) {
    alert('Upload failed');
  }
  if (label) label.textContent = orig;
}

let currentStep = 1;

function goToStep(step) {
  // Validate step 1 before leaving
  if (currentStep === 1 && step > 1) {
    const name = document.querySelector('[name="name"]').value.trim();
    const slug = document.querySelector('[name="slug"]').value.trim();
    if (!name || !slug) {
      showToast('Please fill in name and slug', 'error');
      return;
    }
  }
  document.getElementById('step-' + currentStep).classList.add('hidden');
  currentStep = step;
  document.getElementById('step-' + step).classList.remove('hidden');
  if (step === 5) updateReview();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function generateSlug(name) {
  const slug = name.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/[\s]+/g, '-').replace(/-+/g, '-').trim('-');
  document.getElementById('community-slug').value = slug.substring(0, 100);
}

function previewLogo(url) {
  const preview = document.getElementById('logo-preview');
  if (url && /^https?:\/\//i.test(url)) {
    const img = document.createElement('img');
    img.src = url;
    img.className = 'w-full h-full object-cover';
    img.onerror = function() { this.style.display = 'none'; };
    preview.innerHTML = '';
    preview.appendChild(img);
  }
}

function previewBanner(url) {
  const preview = document.getElementById('banner-preview-div');
  if (url) {
    // Sanitize: only allow http/https URLs
    if (/^https?:\/\//i.test(url)) {
      preview.style.backgroundImage = 'url(' + url.replace(/['"()]/g, '') + ')';
      preview.style.backgroundSize = 'cover';
      preview.style.backgroundPosition = 'center';
      const span = preview.querySelector('span');
      if (span) span.style.display = 'none';
    }
  }
}

function togglePricingFields() {
  const pricing = document.querySelector('[name="pricing"]:checked')?.value;
  const paidFields = document.getElementById('paid-fields');
  if (pricing === 'paid' || pricing === 'free_trial') {
    paidFields.classList.remove('hidden');
  } else {
    paidFields.classList.add('hidden');
  }
}

function addCommLinkRow() {
  const container = document.getElementById('comm-links-container');
  const row = document.createElement('div');
  row.className = 'flex items-center gap-2 link-row';
  row.innerHTML = `
    <input type="text" name="link_name[]" placeholder="Label" class="w-28 px-3 py-2 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none dark:text-gray-200">
    <input type="url" name="link_url[]" placeholder="https://..." class="flex-1 px-3 py-2 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#2a2a2a] text-xs focus:outline-none dark:text-gray-200">
    <button type="button" onclick="this.closest('.link-row').remove()" class="p-1.5 text-red-400 hover:text-red-600 rounded-lg">✕</button>`;
  container.appendChild(row);
}

function updateReview() {
  const name = document.querySelector('[name="name"]').value || 'Your Community';
  const slug = document.querySelector('[name="slug"]').value || 'your-slug';
  const bio = document.querySelector('[name="short_bio"]').value || 'No description';
  const type = document.querySelector('[name="type"]:checked')?.value || 'public';
  const pricing = document.querySelector('[name="pricing"]:checked')?.value || 'free';
  const category = document.querySelector('[name="category"]:checked')?.value || '-';
  const logoUrl = document.getElementById('logo_url')?.value || '';

  document.getElementById('review-name').textContent = name;
  document.getElementById('review-slug').textContent = 'discover.com/' + slug;
  document.getElementById('review-bio').textContent = bio;
  document.getElementById('review-type').textContent = type.charAt(0).toUpperCase() + type.slice(1);
  document.getElementById('review-pricing').textContent = pricing === 'free' ? 'Free' : pricing === 'paid' ? '$' + (document.querySelector('[name="price"]')?.value || '0') + '/mo' : 'Free Trial';
  document.getElementById('review-category').textContent = category.replace('_', ' ');

  if (logoUrl) {
    document.getElementById('review-logo').innerHTML = `<img src="${logoUrl}" class="w-full h-full object-cover" onerror="this.remove()">`;
  } else {
    document.getElementById('review-logo').textContent = name.charAt(0).toUpperCase();
  }
}
</script>
