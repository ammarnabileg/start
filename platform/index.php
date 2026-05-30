<?php
ini_set('display_errors', 0);
error_reporting(0);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Discover Communities';
$current_user = get_auth_user();

// Filters
$q        = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? 'all';
$price    = $_GET['price'] ?? '';
$type     = $_GET['type'] ?? '';
$sort     = $_GET['sort'] ?? 'trending';
$lang     = $_GET['lang'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset   = ($page - 1) * $per_page;

$categories = ['all', 'trending', 'hobbies', 'music', 'money', 'celebrity', 'tech', 'health', 'sports', 'self_improvement', 'relationships'];
$cat_labels = ['all'=>'All', 'trending'=>'Trending', 'hobbies'=>'Hobbies', 'music'=>'Music', 'money'=>'Money', 'celebrity'=>'Celebrity', 'tech'=>'Tech', 'health'=>'Health', 'sports'=>'Sports', 'self_improvement'=>'Self Improvement', 'relationships'=>'Relationships'];

// Build query
$where = ['c.is_active = 1'];
$params = [];

if ($q) {
    $where[] = '(c.name LIKE ? OR c.description LIKE ? OR c.short_bio LIKE ?)';
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%"]);
}
if ($category && $category !== 'all') {
    $where[] = 'c.category = ?';
    $params[] = $category;
}
if ($price === 'free') {
    $where[] = 'c.pricing = "free"';
} elseif ($price === 'paid') {
    $where[] = 'c.pricing = "paid"';
} elseif ($price === 'free_trial') {
    $where[] = 'c.pricing = "free_trial"';
}
if ($type === 'public') {
    $where[] = 'c.type = "public"';
} elseif ($type === 'private') {
    $where[] = 'c.type = "private"';
}
if ($lang) {
    $where[] = 'c.language = ?';
    $params[] = $lang;
}

$where_str = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$order = $sort === 'top' ? 'c.member_count DESC' : 'c.member_count DESC, c.created_at DESC';

$count_row = db_fetch("SELECT COUNT(*) as cnt FROM communities c $where_str", $params);
$total = (int)($count_row['cnt'] ?? 0);
$total_pages = ceil($total / $per_page);

$communities = db_fetch_all(
    "SELECT c.*, u.username as owner_username, u.first_name as owner_first, u.last_name as owner_last,
     (SELECT COUNT(*) FROM memberships m WHERE m.community_id = c.id AND m.status = 'approved') as real_member_count
     FROM communities c
     JOIN users u ON u.id = c.owner_id
     $where_str
     ORDER BY $order
     LIMIT ? OFFSET ?",
    array_merge($params, [$per_page, $offset])
);

include __DIR__ . '/includes/header.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

  <!-- Hero Section -->
  <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-900 via-primary-700 to-accent-500 mb-10 p-8 sm:p-12">
    <div class="absolute inset-0 opacity-10">
      <div class="absolute top-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl transform translate-x-32 -translate-y-16"></div>
      <div class="absolute bottom-0 left-0 w-64 h-64 bg-accent-300 rounded-full blur-3xl transform -translate-x-16 translate-y-16"></div>
    </div>
    <div class="relative z-10 text-center max-w-2xl mx-auto">
      <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white mb-4 leading-tight">
        Discover Your <span class="text-accent-300">Community</span>
      </h1>
      <p class="text-primary-100 text-lg mb-8">Join thousands of communities for learning, networking, and growth across the Gulf region.</p>
      <form action="/index.php" method="GET" class="flex gap-2 max-w-xl mx-auto">
        <div class="flex-1 relative">
          <input type="text" name="q" value="<?= e($q) ?>"
            placeholder="Search for communities..."
            class="w-full px-5 py-4 rounded-2xl text-gray-900 text-sm focus:outline-none focus:ring-4 focus:ring-white/30 shadow-2xl font-medium placeholder-gray-400">
          <svg class="absolute right-4 top-4 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </div>
        <button type="submit" class="px-6 py-4 bg-white text-primary-600 rounded-2xl font-bold text-sm hover:bg-primary-50 shadow-2xl transition-all hover:-translate-y-0.5">
          Search
        </button>
      </form>
    </div>
  </div>

  <!-- Category Tabs + Filter Button -->
  <div class="flex items-center gap-3 mb-6 overflow-x-auto pb-2 scrollbar-hide">
    <div class="flex gap-2 flex-1 overflow-x-auto">
      <?php foreach ($categories as $cat): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['category' => $cat, 'page' => 1])) ?>"
          class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition-all whitespace-nowrap
          <?= ($category === $cat) ? 'bg-primary-600 text-white shadow-md shadow-primary-500/30' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600' ?>">
          <?= $cat_labels[$cat] ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Filter button -->
    <button onclick="toggleFilters()"
      class="flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
      </svg>
      Filters
      <?php if ($price || $type || ($sort && $sort !== 'trending') || $lang): ?>
        <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
      <?php endif; ?>
    </button>
  </div>

  <!-- Filter Panel -->
  <div id="filter-panel" class="<?= ($price || $type || ($sort && $sort !== 'trending') || $lang) ? '' : 'hidden' ?> bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6 shadow-sm">
    <form method="GET" id="filter-form">
      <input type="hidden" name="q" value="<?= e($q) ?>">
      <input type="hidden" name="category" value="<?= e($category) ?>">
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
        <div>
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Price</h4>
          <?php foreach ([''=>'All Prices', 'free'=>'Free', 'paid'=>'Paid', 'free_trial'=>'Free Trial'] as $val => $label): ?>
            <label class="flex items-center gap-2 mb-2 cursor-pointer">
              <input type="radio" name="price" value="<?= $val ?>" <?= $price === $val ? 'checked' : '' ?>
                class="text-primary-600 focus:ring-primary-500">
              <span class="text-sm text-gray-600 dark:text-gray-400"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Type</h4>
          <?php foreach ([''=>'All Types', 'public'=>'Public', 'private'=>'Private'] as $val => $label): ?>
            <label class="flex items-center gap-2 mb-2 cursor-pointer">
              <input type="radio" name="type" value="<?= $val ?>" <?= $type === $val ? 'checked' : '' ?>
                class="text-primary-600 focus:ring-primary-500">
              <span class="text-sm text-gray-600 dark:text-gray-400"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Sort By</h4>
          <?php foreach (['trending'=>'Trending', 'top'=>'Top Members'] as $val => $label): ?>
            <label class="flex items-center gap-2 mb-2 cursor-pointer">
              <input type="radio" name="sort" value="<?= $val ?>" <?= $sort === $val ? 'checked' : '' ?>
                class="text-primary-600 focus:ring-primary-500">
              <span class="text-sm text-gray-600 dark:text-gray-400"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div>
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Language</h4>
          <?php foreach ([''=>'All Languages', 'en'=>'English', 'ar'=>'Arabic', 'fr'=>'French'] as $val => $label): ?>
            <label class="flex items-center gap-2 mb-2 cursor-pointer">
              <input type="radio" name="lang" value="<?= $val ?>" <?= $lang === $val ? 'checked' : '' ?>
                class="text-primary-600 focus:ring-primary-500">
              <span class="text-sm text-gray-600 dark:text-gray-400"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
        <a href="/index.php" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium">Reset</a>
        <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 transition-all">Apply Filters</button>
      </div>
    </form>
  </div>

  <!-- Results Header -->
  <div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500 dark:text-gray-400">
      <?php if ($q): ?>
        <span class="font-medium text-gray-700 dark:text-gray-300"><?= $total ?></span> results for "<span class="font-medium text-primary-600"><?= e($q) ?></span>"
      <?php else: ?>
        <span class="font-medium text-gray-700 dark:text-gray-300"><?= $total ?></span> communities found
      <?php endif; ?>
    </p>
    <?php if ($current_user): ?>
      <a href="/create-community.php" class="text-sm text-primary-600 dark:text-primary-400 font-semibold hover:underline flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Create Community
      </a>
    <?php endif; ?>
  </div>

  <!-- Community Cards Grid -->
  <?php if (empty($communities)): ?>
    <div class="text-center py-20">
      <div class="text-6xl mb-4">🔍</div>
      <h3 class="text-xl font-bold text-gray-700 dark:text-gray-300 mb-2">No communities found</h3>
      <p class="text-gray-500 dark:text-gray-400 mb-6">Try different search terms or filters</p>
      <a href="/index.php" class="px-6 py-3 bg-primary-600 text-white rounded-xl font-semibold hover:bg-primary-700 transition-all">Browse All</a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
      <?php foreach ($communities as $c): ?>
        <?php
        $is_member = false;
        $membership_status = null;
        if ($current_user) {
            $mem = db_fetch('SELECT status FROM memberships WHERE user_id = ? AND community_id = ?', [$current_user['id'], $c['id']]);
            if ($mem) { $is_member = $mem['status'] === 'approved'; $membership_status = $mem['status']; }
        }
        ?>
        <div class="group bg-white dark:bg-gray-800 rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-700 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-1 transition-all duration-300 flex flex-col">
          <!-- Banner -->
          <div class="relative h-28 bg-gradient-to-br from-primary-600 to-accent-500 overflow-hidden">
            <?php if ($c['banner']): ?>
              <img src="<?= e($c['banner']) ?>" alt="" class="w-full h-full object-cover">
            <?php else: ?>
              <div class="absolute inset-0 bg-gradient-to-br from-primary-700 to-accent-500 opacity-80"></div>
              <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_50%_50%,rgba(255,255,255,0.3),transparent)]"></div>
            <?php endif; ?>
            <!-- Category badge -->
            <div class="absolute top-2 right-2">
              <span class="px-2 py-0.5 bg-black/30 backdrop-blur-sm text-white text-xs font-medium rounded-full capitalize"><?= $cat_labels[$c['category']] ?? $c['category'] ?></span>
            </div>
            <!-- Pricing badge -->
            <?php if ($c['pricing'] === 'paid'): ?>
              <div class="absolute top-2 left-2">
                <span class="px-2 py-0.5 bg-amber-500 text-white text-xs font-bold rounded-full"><?= format_price($c['price'], $c['price_interval']) ?></span>
              </div>
            <?php elseif ($c['pricing'] === 'free_trial'): ?>
              <div class="absolute top-2 left-2">
                <span class="px-2 py-0.5 bg-green-500 text-white text-xs font-bold rounded-full">Free Trial</span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Logo overlapping banner -->
          <div class="px-4 -mt-6 mb-2">
            <?php if ($c['logo']): ?>
              <img src="<?= e($c['logo']) ?>" alt="<?= e($c['name']) ?>"
                class="w-12 h-12 rounded-xl border-2 border-white dark:border-gray-800 shadow-md object-cover bg-white">
            <?php else: ?>
              <div class="w-12 h-12 rounded-xl border-2 border-white dark:border-gray-800 shadow-md bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-black text-lg">
                <?= strtoupper(substr($c['name'], 0, 1)) ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Content -->
          <div class="px-4 pb-4 flex-1 flex flex-col">
            <h3 class="font-bold text-gray-900 dark:text-white mb-1 leading-tight line-clamp-2"><?= e($c['name']) ?></h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 line-clamp-3 flex-1"><?= e($c['short_bio'] ?: $c['description']) ?></p>

            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="font-semibold"><?= format_member_count($c['real_member_count']) ?></span> members
              </div>
              <span class="flex items-center gap-1 text-xs <?= $c['type'] === 'private' ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' ?>">
                <?php if ($c['type'] === 'private'): ?>
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                  Private
                <?php else: ?>
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  Public
                <?php endif; ?>
              </span>
            </div>

            <?php if ($is_member): ?>
              <a href="/community.php?slug=<?= e($c['slug']) ?>"
                class="w-full text-center py-2 rounded-xl bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 text-sm font-semibold hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-all">
                Open Community
              </a>
            <?php elseif ($membership_status === 'pending'): ?>
              <button disabled class="w-full py-2 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 text-sm font-semibold cursor-not-allowed">
                Request Pending
              </button>
            <?php else: ?>
              <a href="/community.php?slug=<?= e($c['slug']) ?>"
                class="w-full text-center py-2 rounded-xl bg-gradient-to-r from-primary-600 to-accent-500 text-white text-sm font-semibold hover:shadow-md hover:shadow-primary-500/30 transition-all hover:-translate-y-0.5">
                <?= $c['pricing'] === 'paid' ? 'View Community' : 'Join Free' ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div class="flex items-center justify-center gap-2 mt-10">
        <?php if ($page > 1): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
            class="w-10 h-10 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
            <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </a>
        <?php endif; ?>

        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
            class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-semibold transition-all
            <?= $i === $page ? 'bg-primary-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
            class="w-10 h-10 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-700 transition-all">
            <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
function toggleFilters() {
  const panel = document.getElementById('filter-panel');
  panel.classList.toggle('hidden');
}
</script>
