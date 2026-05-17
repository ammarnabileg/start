<?php
/**
 * Content Hub — index view
 * Vars from controller: $brand, $result (paginated), $stats, $filters, $user, $csrf
 *
 * $result = [ 'data'=>[], 'total'=>0, 'per_page'=>18, 'current_page'=>1, 'last_page'=>1 ]
 */

$pageTitle  = 'Content Hub';
$activePage = 'content';

// ── Safe defaults ──────────────────────────────────────────────────────────
$brand   = $brand   ?? ['id' => 0, 'name' => 'Brand', 'slug' => 'brand'];
$stats   = $stats   ?? [];
$filters = $filters ?? [];
$user    = $user    ?? ['id' => 0, 'name' => 'User', 'initials' => 'U', 'role' => 'admin'];
$csrf    = $csrf    ?? bin2hex(random_bytes(16));
$result  = $result  ?? ['data' => [], 'total' => 0, 'per_page' => 18, 'current_page' => 1, 'last_page' => 1];
$posts   = $result['data'] ?? [];

// ── Status config ──────────────────────────────────────────────────────────
$statusConfig = [
    'draft'          => ['label' => 'Draft',          'badge' => 'badge-neutral', 'icon' => '📝', 'dot' => '#6B7280'],
    'pending_review' => ['label' => 'Pending Review', 'badge' => 'badge-warning', 'icon' => '⏳', 'dot' => '#F59E0B'],
    'approved'       => ['label' => 'Approved',       'badge' => 'badge-success', 'icon' => '✅', 'dot' => '#10B981'],
    'scheduled'      => ['label' => 'Scheduled',      'badge' => 'badge-info',    'icon' => '📅', 'dot' => '#3B82F6'],
    'published'      => ['label' => 'Published',      'badge' => 'badge-purple',  'icon' => '🚀', 'dot' => '#8B5CF6'],
    'failed'         => ['label' => 'Failed',          'badge' => 'badge-error',   'icon' => '❌', 'dot' => '#EF4444'],
];

// ── Platform config ──────────────────────────────────────────────────────
$platformConfig = [
    'linkedin'  => ['label' => 'LinkedIn',  'color' => '#0077B5', 'icon' => '🔵'],
    'instagram' => ['label' => 'Instagram', 'color' => '#E1306C', 'icon' => '📸'],
    'facebook'  => ['label' => 'Facebook',  'color' => '#1877F2', 'icon' => '📘'],
    'tiktok'    => ['label' => 'TikTok',    'color' => '#ff0050', 'icon' => '🎵'],
    'twitter'   => ['label' => 'X/Twitter', 'color' => '#1DA1F2', 'icon' => '🐦'],
    'youtube'   => ['label' => 'YouTube',   'color' => '#FF0000', 'icon' => '▶️'],
    'snapchat'  => ['label' => 'Snapchat',  'color' => '#FFFC00', 'icon' => '👻'],
    'threads'   => ['label' => 'Threads',   'color' => '#FFFFFF', 'icon' => '🧵'],
];

// ── Mock data for demo when $posts is empty ────────────────────────────────
if (empty($posts)) {
    $posts = [
        ['id' => 1, 'title' => '5 AI Trends Reshaping Social Media in 2026', 'content_type' => 'linkedin_post',
         'body_text' => 'Artificial intelligence is no longer just a buzzword — it\'s fundamentally changing how brands connect with their audiences. Here are 5 key trends every social media manager needs to know right now.',
         'platform' => 'linkedin', 'status' => 'published', 'language' => 'english',
         'scheduled_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
         'created_by_name' => 'Ahmed Al-Rashid', 'created_by_initials' => 'AA',
         'viral_score' => 87, 'likes' => 1240, 'comments' => 94, 'ai_generated' => 1,
         'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))],

        ['id' => 2, 'title' => 'Behind the Scenes: Building SociAI', 'content_type' => 'caption',
         'body_text' => 'Swipe to see how we built an entire AI social media OS from scratch in 6 months 🚀 The journey, the late nights, and the breakthroughs. What question do you have about AI product development?',
         'platform' => 'instagram', 'status' => 'scheduled', 'language' => 'english',
         'scheduled_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
         'created_by_name' => 'Sara Ahmed', 'created_by_initials' => 'SA',
         'viral_score' => 73, 'likes' => 0, 'comments' => 0, 'ai_generated' => 1,
         'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))],

        ['id' => 3, 'title' => 'محتوى تسويقي لإطلاق المنتج', 'content_type' => 'ad_copy',
         'body_text' => 'هل أنت مستعد لمضاعفة نمو علامتك التجارية؟ SociAI OS يجمع قوة الذكاء الاصطناعي مع إدارة وسائل التواصل الاجتماعي في منصة واحدة متكاملة.',
         'platform' => 'facebook', 'status' => 'pending_review', 'language' => 'arabic',
         'scheduled_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
         'created_by_name' => 'Omar Khalid', 'created_by_initials' => 'OK',
         'viral_score' => 61, 'likes' => 0, 'comments' => 0, 'ai_generated' => 1,
         'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours'))],

        ['id' => 4, 'title' => 'Thread: How to 10x Your LinkedIn Reach', 'content_type' => 'thread',
         'body_text' => '1/ Most LinkedIn creators plateau at 500 views per post. Here\'s the exact system I used to go from 500 → 50,000 views in 30 days (without posting more content).',
         'platform' => 'twitter', 'status' => 'approved', 'language' => 'english',
         'scheduled_at' => date('Y-m-d H:i:s', strtotime('+4 hours')),
         'created_by_name' => 'Ahmed Al-Rashid', 'created_by_initials' => 'AA',
         'viral_score' => 92, 'likes' => 0, 'comments' => 0, 'ai_generated' => 0,
         'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))],

        ['id' => 5, 'title' => 'TikTok Hook: AI Tools You\'re Not Using', 'content_type' => 'hook',
         'body_text' => 'POV: You discover the 3 AI tools that save 20 hours every single week… and nobody\'s talking about them yet.',
         'platform' => 'tiktok', 'status' => 'draft', 'language' => 'english',
         'scheduled_at' => null,
         'created_by_name' => 'Sara Ahmed', 'created_by_initials' => 'SA',
         'viral_score' => 45, 'likes' => 0, 'comments' => 0, 'ai_generated' => 1,
         'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))],

        ['id' => 6, 'title' => 'Campaign CTA — Q3 Growth Push', 'content_type' => 'cta',
         'body_text' => 'Your competitors are already using AI to outpace you. Don\'t get left behind. Start your free 14-day trial of SociAI OS today — no credit card required.',
         'platform' => 'linkedin', 'status' => 'failed', 'language' => 'english',
         'scheduled_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
         'created_by_name' => 'Ahmed Al-Rashid', 'created_by_initials' => 'AA',
         'viral_score' => 55, 'likes' => 0, 'comments' => 0, 'ai_generated' => 1,
         'created_at' => date('Y-m-d H:i:s', strtotime('-4 days'))],
    ];
}

$totalItems  = $result['total']        ?? count($posts);
$currentPage = $result['current_page'] ?? 1;
$lastPage    = $result['last_page']    ?? 1;
$perPage     = $result['per_page']     ?? 18;

// ── Stat counts ─────────────────────────────────────────────────────────────
$statsByStatus = [];
foreach ($statusConfig as $k => $v) {
    $statsByStatus[$k] = $stats[$k] ?? count(array_filter($posts, fn($p) => ($p['status'] ?? '') === $k));
}

// ── Build calendar data ──────────────────────────────────────────────────
$today = new DateTimeImmutable();
$calYear  = (int)($filters['cal_year']  ?? $today->format('Y'));
$calMonth = (int)($filters['cal_month'] ?? $today->format('n'));
$calDt    = DateTimeImmutable::createFromFormat('Y-n-d', "$calYear-$calMonth-1");
$calDaysInMonth = (int)$calDt->format('t');
$calFirstDow    = (int)$calDt->format('N'); // 1=Mon … 7=Sun

// Count posts per day
$postsByDay = [];
foreach ($posts as $p) {
    if (!empty($p['scheduled_at'])) {
        $d = (int)date('j', strtotime($p['scheduled_at']));
        $m = (int)date('n', strtotime($p['scheduled_at']));
        $y = (int)date('Y', strtotime($p['scheduled_at']));
        if ($m === $calMonth && $y === $calYear) {
            $postsByDay[$d] = ($postsByDay[$d] ?? 0) + 1;
        }
    }
}

$contentTypeLabels = [
    'caption'       => 'Caption',
    'linkedin_post' => 'LinkedIn Post',
    'thread'        => 'Thread / X',
    'script'        => 'Video Script',
    'hook'          => 'Hook',
    'cta'           => 'CTA',
    'ad_copy'       => 'Ad Copy',
    'carousel'      => 'Carousel',
    'story'         => 'Story',
    'comment_reply' => 'Comment Reply',
    'dm_reply'      => 'DM Reply',
];

ob_start();
?>

<!-- ═══════════════════════════════════════════════════════════
     CONTENT HUB
     ═══════════════════════════════════════════════════════════ -->
<div class="page-header page-header-row" style="margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.6rem;margin-bottom:0.25rem">📦 Content Hub</h1>
    <p style="font-size:0.9rem;color:var(--text-muted)">
      <?= number_format($totalItems) ?> pieces across all platforms
    </p>
  </div>
  <div style="display:flex;align-items:center;gap:0.75rem">
    <button class="btn btn-ghost" id="bulkActionsToggle" style="display:none">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
      Bulk Actions
    </button>
    <button class="btn btn-primary" onclick="SociAI.openModal('createContentModal')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Create New Content
    </button>
  </div>
</div>

<!-- ── STAT STRIP ─────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:0.75rem;margin-bottom:1.5rem">
  <?php foreach ($statusConfig as $key => $cfg): ?>
  <div class="glass-card" style="padding:0.85rem 1rem;cursor:pointer;transition:all 0.2s;border-color:<?= $cfg['dot'] ?>22"
       onclick="ContentHub.filterByStatus('<?= $key ?>')"
       title="Filter by <?= htmlspecialchars($cfg['label']) ?>">
    <div style="font-size:1.4rem;margin-bottom:0.3rem"><?= $cfg['icon'] ?></div>
    <div style="font-size:1.4rem;font-weight:800;line-height:1"><?= number_format($statsByStatus[$key] ?? 0) ?></div>
    <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.2rem"><?= htmlspecialchars($cfg['label']) ?></div>
  </div>
  <?php endforeach ?>
</div>

<!-- ── FILTER BAR ────────────────────────────────────────── -->
<div class="glass-card" style="padding:0.85rem 1.1rem;margin-bottom:1.25rem">
  <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">

    <!-- Status filter buttons -->
    <div style="display:flex;gap:0.35rem;flex-wrap:wrap" id="statusFilterBtns">
      <button class="filter-status-btn active" data-status="">All</button>
      <?php foreach ($statusConfig as $key => $cfg): ?>
      <button class="filter-status-btn" data-status="<?= $key ?>">
        <?= $cfg['icon'] ?> <?= htmlspecialchars($cfg['label']) ?>
      </button>
      <?php endforeach ?>
    </div>

    <div style="flex:1;min-width:200px;position:relative">
      <input type="text" id="contentSearch" class="form-input" style="padding-left:2.25rem;padding-top:0.45rem;padding-bottom:0.45rem"
             placeholder="Search content, platform, title…"
             value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
      <span style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </span>
    </div>

    <!-- Sort -->
    <select class="form-select" id="sortSelect" style="width:auto;padding-top:0.45rem;padding-bottom:0.45rem">
      <option value="newest">Newest First</option>
      <option value="oldest">Oldest First</option>
      <option value="viral_desc">Viral Score ↓</option>
      <option value="viral_asc">Viral Score ↑</option>
      <option value="scheduled">Scheduled Date</option>
    </select>

    <!-- Select All checkbox -->
    <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.82rem;color:var(--text-muted)">
      <input type="checkbox" id="selectAllCb" style="width:14px;height:14px;accent-color:var(--blue)">
      Select All
    </label>
  </div>
</div>

<!-- ── BULK ACTION BAR (hidden until selection) ──────────── -->
<div id="bulkBar" style="display:none;margin-bottom:1rem">
  <div class="glass-card" style="padding:0.6rem 1rem;border-color:rgba(59,130,246,0.3);background:rgba(59,130,246,0.05)">
    <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
      <span style="font-size:0.85rem;font-weight:600;color:var(--blue-light)">
        <span id="bulkSelectedCount">0</span> selected
      </span>
      <button class="btn btn-ghost" style="padding:0.3rem 0.75rem;font-size:0.8rem" onclick="ContentHub.bulkApprove()">✅ Approve</button>
      <button class="btn btn-ghost" style="padding:0.3rem 0.75rem;font-size:0.8rem" onclick="ContentHub.bulkReject()">❌ Reject</button>
      <button class="btn btn-ghost" style="padding:0.3rem 0.75rem;font-size:0.8rem;color:#FC8181" onclick="ContentHub.bulkDelete()">🗑️ Delete</button>
      <button class="btn btn-ghost" style="padding:0.3rem 0.75rem;font-size:0.8rem" onclick="ContentHub.clearSelection()">✕ Clear</button>
    </div>
  </div>
</div>

<!-- ── CONTENT GRID ───────────────────────────────────────── -->
<div id="contentGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1rem;margin-bottom:1.5rem">
  <?php foreach ($posts as $post): ?>
  <?php
    $status     = $post['status']   ?? 'draft';
    $sCfg       = $statusConfig[$status] ?? $statusConfig['draft'];
    $platform   = $post['platform'] ?? 'linkedin';
    $pCfg       = $platformConfig[$platform] ?? ['label' => ucfirst($platform), 'color' => 'var(--blue)', 'icon' => '📱'];
    $bodyText   = $post['body_text'] ?? '';
    $truncated  = mb_strlen($bodyText) > 120 ? mb_substr($bodyText, 0, 120) . '…' : $bodyText;
    $viralScore = (int)($post['viral_score'] ?? 0);
    $vsColor    = $viralScore >= 80 ? 'var(--green)' : ($viralScore >= 60 ? 'var(--blue)' : ($viralScore >= 40 ? 'var(--yellow)' : 'var(--red)'));
    $cType      = $contentTypeLabels[$post['content_type'] ?? 'caption'] ?? ucfirst($post['content_type'] ?? '');
    $initials   = htmlspecialchars(substr($post['created_by_initials'] ?? 'U', 0, 2));
    $isArabic   = ($post['language'] ?? 'english') === 'arabic';
  ?>
  <div class="glass-card content-post-card"
       data-id="<?= (int)$post['id'] ?>"
       data-status="<?= htmlspecialchars($status) ?>"
       data-platform="<?= htmlspecialchars($platform) ?>"
       data-search="<?= htmlspecialchars(strtolower(($post['title'] ?? '') . ' ' . $bodyText . ' ' . $platform)) ?>"
       data-viral="<?= $viralScore ?>"
       data-date="<?= htmlspecialchars($post['created_at'] ?? '') ?>"
       data-sched="<?= htmlspecialchars($post['scheduled_at'] ?? '') ?>"
       style="padding:1.1rem;transition:all 0.2s;border-color:<?= $pCfg['color'] ?>22;position:relative">

    <!-- Select checkbox -->
    <input type="checkbox" class="post-select-cb"
           style="position:absolute;top:0.85rem;right:0.85rem;width:14px;height:14px;accent-color:var(--blue);cursor:pointer"
           data-id="<?= (int)$post['id'] ?>">

    <!-- Card header: platform + status -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;padding-right:1.5rem">
      <div style="display:flex;align-items:center;gap:0.45rem">
        <span style="font-size:1.1rem"><?= $pCfg['icon'] ?></span>
        <span style="font-size:0.8rem;font-weight:600;color:<?= $pCfg['color'] ?>"><?= htmlspecialchars($pCfg['label']) ?></span>
        <span style="font-size:0.75rem;color:var(--text-muted)">·</span>
        <span style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($cType) ?></span>
      </div>
      <span class="badge <?= $sCfg['badge'] ?>">
        <?= $sCfg['icon'] ?> <?= htmlspecialchars($sCfg['label']) ?>
      </span>
    </div>

    <!-- Title -->
    <?php if (!empty($post['title'])): ?>
    <div style="font-size:0.9rem;font-weight:600;color:var(--text-primary);margin-bottom:0.4rem;<?= $isArabic ? 'direction:rtl;text-align:right' : '' ?>">
      <?= htmlspecialchars($post['title']) ?>
    </div>
    <?php endif ?>

    <!-- Body preview -->
    <div style="font-size:0.83rem;color:var(--text-muted);line-height:1.6;margin-bottom:0.75rem;<?= $isArabic ? 'direction:rtl;text-align:right' : '' ?>">
      <?= htmlspecialchars($truncated) ?>
    </div>

    <!-- Viral score meter -->
    <div style="margin-bottom:0.75rem">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem">
        <span style="font-size:0.7rem;color:var(--text-muted)">Viral Score</span>
        <span style="font-size:0.75rem;font-weight:700;color:<?= $vsColor ?>"><?= $viralScore ?>/100</span>
      </div>
      <div style="height:4px;background:rgba(255,255,255,0.06);border-radius:9999px;overflow:hidden">
        <div style="height:100%;width:<?= $viralScore ?>%;background:<?= $vsColor ?>;border-radius:9999px;transition:width 0.8s ease"></div>
      </div>
    </div>

    <!-- Meta row: author + date -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem">
      <div style="display:flex;align-items:center;gap:0.45rem">
        <div class="user-avatar" style="width:22px;height:22px;min-width:22px;font-size:0.65rem">
          <?= $initials ?>
        </div>
        <span style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($post['created_by_name'] ?? 'Team') ?></span>
        <?php if (!empty($post['ai_generated'])): ?>
        <span class="badge badge-purple" style="font-size:0.65rem;padding:0.1rem 0.4rem">🤖 AI</span>
        <?php endif ?>
      </div>
      <div style="font-size:0.72rem;color:var(--text-muted)">
        <?php if (!empty($post['scheduled_at'])): ?>
          📅 <?= htmlspecialchars(date('M j, H:i', strtotime($post['scheduled_at']))) ?>
        <?php else: ?>
          <?= htmlspecialchars(date('M j, Y', strtotime($post['created_at'] ?? 'now'))) ?>
        <?php endif ?>
      </div>
    </div>

    <!-- Action buttons -->
    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;border-top:1px solid var(--glass-border);padding-top:0.75rem">
      <button class="btn btn-ghost"
              style="flex:1;padding:0.35rem 0.5rem;font-size:0.75rem;min-width:0"
              onclick="ContentHub.editPost(<?= (int)$post['id'] ?>)">
        ✏️ Edit
      </button>
      <?php if (in_array($status, ['draft','pending_review'])): ?>
      <button class="btn btn-ghost"
              style="flex:1;padding:0.35rem 0.5rem;font-size:0.75rem;color:var(--green-light);border-color:rgba(16,185,129,0.3);min-width:0"
              onclick="ContentHub.approvePost(<?= (int)$post['id'] ?>, this)">
        ✅ Approve
      </button>
      <button class="btn btn-ghost"
              style="flex:1;padding:0.35rem 0.5rem;font-size:0.75rem;color:#FC8181;border-color:rgba(239,68,68,0.3);min-width:0"
              onclick="ContentHub.rejectPost(<?= (int)$post['id'] ?>, this)">
        ❌ Reject
      </button>
      <?php endif ?>
      <?php if ($status === 'approved'): ?>
      <button class="btn btn-primary"
              style="flex:1;padding:0.35rem 0.5rem;font-size:0.75rem;min-width:0"
              onclick="ContentHub.schedulePost(<?= (int)$post['id'] ?>)">
        📅 Schedule
      </button>
      <?php endif ?>
    </div>
  </div>
  <?php endforeach ?>

  <!-- Empty state (shown via JS when filter yields nothing) -->
  <div id="noResultsCard" style="display:none;grid-column:1/-1">
    <div class="glass-card text-center" style="padding:3rem 2rem">
      <div style="font-size:3rem;margin-bottom:0.75rem">🔍</div>
      <div style="font-weight:600;margin-bottom:0.4rem">No content found</div>
      <div class="text-muted text-sm">Try adjusting your filters or create new content.</div>
      <button class="btn btn-primary mt-3" onclick="SociAI.openModal('createContentModal')">
        + Create New Content
      </button>
    </div>
  </div>
</div>

<!-- ── PAGINATION ─────────────────────────────────────────── -->
<?php if ($lastPage > 1): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem">
  <span style="font-size:0.83rem;color:var(--text-muted)">
    Showing <?= (($currentPage - 1) * $perPage) + 1 ?>–<?= min($currentPage * $perPage, $totalItems) ?> of <?= number_format($totalItems) ?>
  </span>
  <div style="display:flex;gap:0.5rem;align-items:center">
    <?php if ($currentPage > 1): ?>
    <a href="?page=<?= $currentPage - 1 ?><?= !empty($filters['status']) ? '&status='.$filters['status'] : '' ?>"
       class="btn btn-ghost" style="padding:0.4rem 0.9rem">
      ← Prev
    </a>
    <?php else: ?>
    <button class="btn btn-ghost" disabled style="padding:0.4rem 0.9rem;opacity:0.4">← Prev</button>
    <?php endif ?>

    <!-- Page numbers (show ±2 of current) -->
    <?php
    $start = max(1, $currentPage - 2);
    $end   = min($lastPage, $currentPage + 2);
    if ($start > 1): ?>
    <a href="?page=1" class="btn btn-ghost" style="padding:0.4rem 0.7rem;font-size:0.82rem">1</a>
    <?php if ($start > 2): ?><span style="color:var(--text-muted);padding:0 0.25rem">…</span><?php endif ?>
    <?php endif ?>

    <?php for ($pg = $start; $pg <= $end; $pg++): ?>
    <a href="?page=<?= $pg ?><?= !empty($filters['status']) ? '&status='.$filters['status'] : '' ?>"
       class="btn <?= $pg === $currentPage ? 'btn-primary' : 'btn-ghost' ?>"
       style="padding:0.4rem 0.7rem;font-size:0.82rem">
      <?= $pg ?>
    </a>
    <?php endfor ?>

    <?php if ($end < $lastPage): ?>
    <?php if ($end < $lastPage - 1): ?><span style="color:var(--text-muted);padding:0 0.25rem">…</span><?php endif ?>
    <a href="?page=<?= $lastPage ?>" class="btn btn-ghost" style="padding:0.4rem 0.7rem;font-size:0.82rem"><?= $lastPage ?></a>
    <?php endif ?>

    <?php if ($currentPage < $lastPage): ?>
    <a href="?page=<?= $currentPage + 1 ?><?= !empty($filters['status']) ? '&status='.$filters['status'] : '' ?>"
       class="btn btn-ghost" style="padding:0.4rem 0.9rem">
      Next →
    </a>
    <?php else: ?>
    <button class="btn btn-ghost" disabled style="padding:0.4rem 0.9rem;opacity:0.4">Next →</button>
    <?php endif ?>
  </div>
</div>
<?php endif ?>

<!-- ═══════════════════════════════════════════════════════════
     CONTENT CALENDAR SECTION
     ═══════════════════════════════════════════════════════════ -->
<div class="glass-card" style="padding:1.5rem;margin-bottom:1.5rem">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem">
    <h2 style="font-size:1.1rem;font-weight:700">
      📅 Content Calendar —
      <span id="calMonthLabel"><?= $calDt->format('F Y') ?></span>
    </h2>
    <div style="display:flex;gap:0.5rem;align-items:center">
      <button class="btn btn-ghost" style="padding:0.35rem 0.7rem" onclick="ContentHub.calNav(-1)" title="Previous month">
        ‹
      </button>
      <button class="btn btn-ghost" style="padding:0.35rem 0.7rem" onclick="ContentHub.calNav(0)" title="Today">
        Today
      </button>
      <button class="btn btn-ghost" style="padding:0.35rem 0.7rem" onclick="ContentHub.calNav(1)" title="Next month">
        ›
      </button>
    </div>
  </div>

  <!-- Day headers -->
  <div class="cal-grid" style="margin-bottom:0.5rem">
    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dow): ?>
    <div class="cal-header-cell"><?= $dow ?></div>
    <?php endforeach ?>
  </div>

  <!-- Calendar grid -->
  <div class="cal-grid" id="calGrid">
    <?php
    // Empty cells before first day
    for ($e = 1; $e < $calFirstDow; $e++): ?>
    <div class="cal-cell empty"></div>
    <?php endfor ?>

    <?php for ($day = 1; $day <= $calDaysInMonth; $day++):
      $isToday    = ($day === (int)$today->format('j') && $calMonth === (int)$today->format('n') && $calYear === (int)$today->format('Y'));
      $postCount  = $postsByDay[$day] ?? 0;
      $hasPost    = $postCount > 0;
    ?>
    <div class="cal-cell <?= $isToday ? 'today' : '' ?> <?= $hasPost ? 'has-post' : '' ?>"
         onclick="ContentHub.showDayDetail(<?= $calYear ?>, <?= $calMonth ?>, <?= $day ?>)"
         title="<?= $postCount ?> post<?= $postCount !== 1 ? 's' : '' ?> on <?= $calDt->format('F') ?> <?= $day ?>">
      <div class="cal-day-num" style="color:<?= $isToday ? 'var(--blue-light)' : 'var(--text-secondary)' ?>"><?= $day ?></div>
      <?php if ($postCount > 0): ?>
      <div style="margin-top:3px">
        <?php for ($d = 0; $d < min($postCount, 3); $d++): ?>
        <span class="cal-dot" style="display:inline-block;margin-right:2px"></span>
        <?php endfor ?>
        <?php if ($postCount > 3): ?>
        <span style="font-size:0.65rem;color:var(--blue-light)">+<?= $postCount - 3 ?></span>
        <?php endif ?>
      </div>
      <?php endif ?>
    </div>
    <?php endfor ?>
  </div>

  <!-- Day detail panel (slides in) -->
  <div id="dayDetailPanel" style="display:none;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--glass-border)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem">
      <h4 id="dayDetailTitle" style="font-size:0.95rem;font-weight:700"></h4>
      <button class="btn btn-ghost" style="padding:0.2rem 0.5rem;font-size:0.78rem" onclick="document.getElementById('dayDetailPanel').style.display='none'">✕</button>
    </div>
    <div id="dayDetailContent" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:0.75rem"></div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     CREATE CONTENT MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="createContentModal">
  <div class="modal-content modal-content-lg" style="max-width:760px">
    <div class="modal-header">
      <h3>✨ Create New Content</h3>
      <button class="modal-close" onclick="SociAI.closeModal('createContentModal')">×</button>
    </div>

    <form id="createContentForm" action="/dashboard/content/store" method="POST">
      <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">

        <!-- Title -->
        <div class="form-group" style="grid-column:1/-1">
          <label class="form-label" for="ccTitle">Title</label>
          <input type="text" id="ccTitle" name="title" class="form-input" placeholder="Give this content a memorable title…" required>
        </div>

        <!-- Platform -->
        <div class="form-group">
          <label class="form-label" for="ccPlatform">Platform</label>
          <select class="form-select" id="ccPlatform" name="platform" required>
            <?php foreach ($platformConfig as $key => $pcfg): ?>
            <option value="<?= htmlspecialchars($key) ?>"><?= $pcfg['icon'] ?> <?= htmlspecialchars($pcfg['label']) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <!-- Content Type -->
        <div class="form-group">
          <label class="form-label" for="ccContentType">Content Type</label>
          <select class="form-select" id="ccContentType" name="content_type" required>
            <?php foreach ($contentTypeLabels as $val => $lbl): ?>
            <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($lbl) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <!-- Language -->
        <div class="form-group">
          <label class="form-label" for="ccLanguage">Language</label>
          <select class="form-select" id="ccLanguage" name="language">
            <option value="english">🇬🇧 English</option>
            <option value="arabic">🇸🇦 Arabic</option>
            <option value="mixed">🔀 Mixed</option>
          </select>
        </div>

        <!-- Writing Style -->
        <div class="form-group">
          <label class="form-label" for="ccStyle">Writing Style</label>
          <select class="form-select" id="ccStyle" name="writing_style">
            <option value="professional">Professional</option>
            <option value="casual">Casual</option>
            <option value="storytelling">Storytelling</option>
            <option value="viral">Viral</option>
            <option value="educational">Educational</option>
            <option value="emotional">Emotional</option>
            <option value="humorous">Humorous</option>
            <option value="inspirational">Inspirational</option>
            <option value="sales">Sales</option>
            <option value="thought_leadership">Thought Leadership</option>
          </select>
        </div>

        <!-- Content Body -->
        <div class="form-group" style="grid-column:1/-1">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem">
            <label class="form-label" for="ccBody" style="margin-bottom:0">Content</label>
            <button type="button" class="btn btn-ghost" style="padding:0.25rem 0.6rem;font-size:0.75rem;gap:0.35rem" id="ccAiGenerateBtn">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
              AI Generate
            </button>
          </div>
          <textarea class="form-textarea" id="ccBody" name="body_text" rows="6"
                    placeholder="Write your content here, or click AI Generate to create it automatically…"></textarea>
          <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;text-align:right">
            <span id="ccCharCount">0</span> characters
          </div>
        </div>

        <!-- Topic (for AI generation, hidden until AI generate is used) -->
        <div class="form-group" id="ccTopicGroup" style="grid-column:1/-1;display:none">
          <label class="form-label" for="ccTopic">Topic / Brief <span style="color:var(--text-muted);font-weight:400">(for AI generation)</span></label>
          <input type="text" id="ccTopic" name="topic" class="form-input" placeholder="What is this content about?">
        </div>

        <!-- Schedule Date/Time -->
        <div class="form-group">
          <label class="form-label" for="ccScheduleDate">Schedule Date &amp; Time</label>
          <input type="datetime-local" id="ccScheduleDate" name="scheduled_at" class="form-input"
                 min="<?= date('Y-m-d\TH:i') ?>">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.2rem">Leave empty to save as draft</div>
        </div>

        <!-- Campaign (optional) -->
        <div class="form-group">
          <label class="form-label" for="ccCampaign">Campaign <span style="font-weight:400;color:var(--text-muted)">(optional)</span></label>
          <select class="form-select" id="ccCampaign" name="campaign_id">
            <option value="">— No campaign —</option>
          </select>
        </div>

      </div>

      <!-- AI generation inline area -->
      <div id="ccAiArea" style="display:none;margin-top:0.75rem;padding:0.85rem 1rem;background:rgba(59,130,246,0.05);border:1px solid rgba(59,130,246,0.2);border-radius:var(--radius-sm)">
        <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.5rem">
          <div class="live-indicator"><div class="live-dot"></div></div>
          <span style="font-size:0.82rem;color:var(--blue-light);font-weight:600" id="ccAiStatus">Generating…</span>
        </div>
        <div class="skeleton skeleton-text" style="width:90%"></div>
        <div class="skeleton skeleton-text" style="width:75%"></div>
        <div class="skeleton skeleton-text" style="width:82%"></div>
      </div>

      <div class="modal-footer" style="margin-top:1.25rem">
        <button type="button" class="btn btn-ghost" onclick="SociAI.closeModal('createContentModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="ccSubmitBtn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v14z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Save Content
        </button>
      </div>
    </form>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     SCHEDULE MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="scheduleModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>📅 Schedule Post</h3>
      <button class="modal-close" onclick="SociAI.closeModal('scheduleModal')">×</button>
    </div>
    <input type="hidden" id="schedulePostId">
    <div class="form-group">
      <label class="form-label" for="schedulePlatform">Platform Account</label>
      <select class="form-select" id="schedulePlatform" name="platform_account_id">
        <?php foreach ($platformConfig as $k => $pc): ?>
        <option value="<?= htmlspecialchars($k) ?>"><?= $pc['icon'] ?> <?= htmlspecialchars($pc['label']) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label" for="scheduleDatetime">Date &amp; Time</label>
      <input type="datetime-local" id="scheduleDatetime" class="form-input" min="<?= date('Y-m-d\TH:i') ?>">
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="SociAI.closeModal('scheduleModal')">Cancel</button>
      <button class="btn btn-primary" onclick="ContentHub.confirmSchedule()">Schedule Post</button>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     REJECT MODAL
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>❌ Reject Content</h3>
      <button class="modal-close" onclick="SociAI.closeModal('rejectModal')">×</button>
    </div>
    <input type="hidden" id="rejectPostId">
    <div class="form-group">
      <label class="form-label" for="rejectReason">Reason for rejection</label>
      <textarea class="form-textarea" id="rejectReason" rows="3" placeholder="Explain why this content is being rejected…"></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="SociAI.closeModal('rejectModal')">Cancel</button>
      <button class="btn btn-primary" style="background:linear-gradient(135deg,#EF4444,#DC2626)" onclick="ContentHub.confirmReject()">Reject</button>
    </div>
  </div>
</div>


<style>
/* Filter status buttons */
.filter-status-btn {
  padding:0.3rem 0.75rem; border-radius:var(--radius-sm);
  border:1px solid var(--glass-border); background:var(--glass-bg);
  font-size:0.78rem; color:var(--text-muted); cursor:pointer; transition:all 0.15s;
  white-space:nowrap;
}
.filter-status-btn:hover { background:var(--glass-bg-hover); color:var(--text-primary); }
.filter-status-btn.active { background:var(--gradient-primary); color:#fff; border-color:transparent; }

/* Post card hover */
.content-post-card:hover { border-color:var(--glass-border-hover)!important; transform:translateY(-2px); }

/* Calendar selected day */
.cal-cell.selected { border-color:var(--purple)!important; background:rgba(139,92,246,0.12)!important; }

/* Day detail panel animation */
#dayDetailPanel { animation:fadeIn 0.2s ease; }

/* datetime-local input styling */
input[type="datetime-local"].form-input {
  color-scheme: dark;
}
</style>

<script>
(function() {
  'use strict';

  /* ── Data passed from PHP ── */
  const ALL_POSTS_DATA = <?= json_encode(array_map(function($p) {
    return [
      'id'           => (int)($p['id'] ?? 0),
      'status'       => $p['status'] ?? 'draft',
      'platform'     => $p['platform'] ?? 'linkedin',
      'scheduled_at' => $p['scheduled_at'] ?? null,
      'body_text'    => $p['body_text'] ?? '',
      'title'        => $p['title'] ?? '',
      'language'     => $p['language'] ?? 'english',
      'content_type' => $p['content_type'] ?? 'caption',
      'viral_score'  => (int)($p['viral_score'] ?? 0),
      'created_at'   => $p['created_at'] ?? '',
    ];
  }, $posts), JSON_UNESCAPED_UNICODE) ?>;

  const PLATFORM_CONFIG = <?= json_encode(array_map(fn($p) => ['label'=>$p['label'],'icon'=>$p['icon'],'color'=>$p['color']], $platformConfig), JSON_UNESCAPED_UNICODE) ?>;
  const STATUS_CONFIG   = <?= json_encode(array_map(fn($s) => ['label'=>$s['label'],'icon'=>$s['icon']], $statusConfig), JSON_UNESCAPED_UNICODE) ?>;
  const CSRF_TOKEN      = <?= json_encode($csrf) ?>;

  /* ── State ── */
  let activeStatusFilter = '';
  let searchQuery        = '';
  let sortOrder          = 'newest';
  let selectedIds        = new Set();
  let calYear            = <?= $calYear ?>;
  let calMonth           = <?= $calMonth ?>;

  /* ── DOM refs ── */
  const grid         = document.getElementById('contentGrid');
  const noResults    = document.getElementById('noResultsCard');
  const bulkBar      = document.getElementById('bulkBar');
  const bulkCount    = document.getElementById('bulkSelectedCount');
  const selectAllCb  = document.getElementById('selectAllCb');

  /* ── Status filter buttons ── */
  document.getElementById('statusFilterBtns').addEventListener('click', e => {
    const btn = e.target.closest('.filter-status-btn');
    if (!btn) return;
    document.querySelectorAll('.filter-status-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeStatusFilter = btn.dataset.status;
    applyFilters();
  });

  /* ── Search ── */
  let searchDebounce;
  document.getElementById('contentSearch').addEventListener('input', e => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => { searchQuery = e.target.value.toLowerCase().trim(); applyFilters(); }, 200);
  });

  /* ── Sort ── */
  document.getElementById('sortSelect').addEventListener('change', e => {
    sortOrder = e.target.value;
    applyFilters();
  });

  /* ── Select all ── */
  selectAllCb.addEventListener('change', () => {
    const cbs = document.querySelectorAll('.post-select-cb');
    cbs.forEach(cb => {
      const card = cb.closest('.content-post-card');
      if (card && card.style.display !== 'none') {
        cb.checked = selectAllCb.checked;
        const id = parseInt(cb.dataset.id);
        if (selectAllCb.checked) selectedIds.add(id); else selectedIds.delete(id);
      }
    });
    updateBulkBar();
  });

  /* ── Individual checkbox ── */
  grid.addEventListener('change', e => {
    if (!e.target.classList.contains('post-select-cb')) return;
    const id = parseInt(e.target.dataset.id);
    if (e.target.checked) selectedIds.add(id); else selectedIds.delete(id);
    updateBulkBar();
  });

  /* ── Filter & sort logic ── */
  function applyFilters() {
    const cards = [...document.querySelectorAll('.content-post-card')];
    let visible = 0;

    // Filter
    const filtered = cards.filter(card => {
      const status   = card.dataset.status;
      const search   = card.dataset.search;
      const statusOk = !activeStatusFilter || status === activeStatusFilter;
      const searchOk = !searchQuery || search.includes(searchQuery);
      const show     = statusOk && searchOk;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
      return show;
    });

    // Sort visible cards
    const parent    = grid;
    const visCards  = filtered.filter(c => c.style.display !== 'none');
    const compareFn = getCompareFn(sortOrder);
    visCards.sort(compareFn).forEach(c => parent.appendChild(c));

    // Empty state
    noResults.style.display = visible === 0 ? '' : 'none';
    if (visible === 0) parent.appendChild(noResults);
  }

  function getCompareFn(order) {
    switch(order) {
      case 'oldest':    return (a,b) => new Date(a.dataset.date) - new Date(b.dataset.date);
      case 'viral_desc':return (a,b) => parseInt(b.dataset.viral) - parseInt(a.dataset.viral);
      case 'viral_asc': return (a,b) => parseInt(a.dataset.viral) - parseInt(b.dataset.viral);
      case 'scheduled': return (a,b) => {
        const da = a.dataset.sched ? new Date(a.dataset.sched) : new Date('9999');
        const db = b.dataset.sched ? new Date(b.dataset.sched) : new Date('9999');
        return da - db;
      };
      default: return (a,b) => new Date(b.dataset.date) - new Date(a.dataset.date);
    }
  }

  function updateBulkBar() {
    const count = selectedIds.size;
    bulkCount.textContent = count;
    bulkBar.style.display = count > 0 ? '' : 'none';
  }

  /* ── Create Content form ── */
  const ccBody  = document.getElementById('ccBody');
  const ccCount = document.getElementById('ccCharCount');
  if (ccBody) {
    ccBody.addEventListener('input', () => { ccCount.textContent = ccBody.value.length; });
  }

  // AI Generate button in create form
  document.getElementById('ccAiGenerateBtn').addEventListener('click', async () => {
    const topicGroup = document.getElementById('ccTopicGroup');
    topicGroup.style.display = '';
    const topic = document.getElementById('ccTopic').value.trim();
    if (!topic) {
      document.getElementById('ccTopic').focus();
      document.getElementById('ccTopic').placeholder = 'Enter a topic first, then click AI Generate again';
      return;
    }

    const platform    = document.getElementById('ccPlatform').value;
    const contentType = document.getElementById('ccContentType').value;
    const style       = document.getElementById('ccStyle').value;
    const language    = document.getElementById('ccLanguage').value;
    const aiArea      = document.getElementById('ccAiArea');
    const aiStatus    = document.getElementById('ccAiStatus');
    const generateBtn = document.getElementById('ccAiGenerateBtn');

    aiArea.style.display = '';
    generateBtn.disabled = true;
    aiStatus.textContent = 'Generating content…';

    try {
      const resp = await fetch('/api/copywriting/generate', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body:    JSON.stringify({ content_type: contentType, platform, topic, style, language, variations: 1 }),
      });
      const data = await resp.json();

      if (data.success) {
        const raw   = data.result ?? {};
        const texts = extractFirstText(raw);
        if (texts) {
          ccBody.value = texts;
          ccBody.dispatchEvent(new Event('input'));
          aiStatus.textContent = '✅ Content generated! You can edit it below.';
          setTimeout(() => { aiArea.style.display = 'none'; }, 2000);
        }
      } else {
        aiStatus.textContent = '⚠️ ' + (data.error ?? 'Generation failed.');
        setTimeout(() => { aiArea.style.display = 'none'; }, 3000);
      }
    } catch (err) {
      aiStatus.textContent = '⚠️ Network error. Please try again.';
      setTimeout(() => { aiArea.style.display = 'none'; }, 3000);
    } finally {
      generateBtn.disabled = false;
    }
  });

  function extractFirstText(raw) {
    if (typeof raw === 'string') return raw;
    const keys = ['caption','post','tweets','script','hooks','cta','ad_copy','slides','story_frames','reply','text'];
    for (const k of keys) {
      if (raw[k]) {
        const v = raw[k];
        if (typeof v === 'string') return v;
        if (Array.isArray(v) && v.length) return typeof v[0] === 'string' ? v[0] : (v[0].text ?? JSON.stringify(v[0]));
        if (typeof v === 'object') return JSON.stringify(v, null, 2);
      }
    }
    return typeof raw === 'object' ? JSON.stringify(raw, null, 2) : String(raw);
  }

  // Prefill content form from sessionStorage (from Copywriting Studio)
  const prefill = sessionStorage.getItem('prefill_content');
  if (prefill) {
    try {
      const p = JSON.parse(prefill);
      if (p.body_text) { ccBody.value = p.body_text; ccBody.dispatchEvent(new Event('input')); }
      if (p.platform) {
        const sel = document.getElementById('ccPlatform');
        if (sel) sel.value = p.platform;
      }
      if (p.content_type) {
        const sel = document.getElementById('ccContentType');
        if (sel) sel.value = p.content_type;
      }
      if (p.language) {
        const sel = document.getElementById('ccLanguage');
        if (sel) sel.value = p.language;
      }
      sessionStorage.removeItem('prefill_content');
      // Auto-open modal if action=schedule
      const params = new URLSearchParams(window.location.search);
      if (params.get('action') === 'schedule') {
        SociAI.openModal('createContentModal');
      }
    } catch(e) {}
  }

  /* ── Calendar navigation ── */
  window.ContentHub = {

    filterByStatus(status) {
      const btn = document.querySelector(`.filter-status-btn[data-status="${status}"]`);
      if (btn) btn.click();
      document.getElementById('contentGrid').scrollIntoView({ behavior: 'smooth' });
    },

    editPost(id) {
      window.location.href = '/dashboard/content/' + id + '/edit';
    },

    approvePost(id, btn) {
      if (!confirm('Approve this content?')) return;
      btn.disabled = true;
      fetch('/dashboard/content/' + id + '/approve', {
        method:  'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
        body:    JSON.stringify({ _token: CSRF_TOKEN }),
      }).then(r => r.json()).then(d => {
        if (d.success || d.message) {
          const card = btn.closest('.content-post-card');
          if (card) {
            const statusBadge = card.querySelector('.badge');
            if (statusBadge) {
              statusBadge.className = 'badge badge-success';
              statusBadge.textContent = '✅ Approved';
            }
            card.dataset.status = 'approved';
            // Swap buttons
            btn.closest('.content-post-card').querySelectorAll('[onclick*="approvePost"],[onclick*="rejectPost"]').forEach(b => b.remove());
            const schedBtn = document.createElement('button');
            schedBtn.className = 'btn btn-primary';
            schedBtn.style.cssText = 'flex:1;padding:0.35rem 0.5rem;font-size:0.75rem;min-width:0';
            schedBtn.textContent = '📅 Schedule';
            schedBtn.onclick = () => ContentHub.schedulePost(id);
            btn.parentElement.appendChild(schedBtn);
          }
          if (typeof SociAI !== 'undefined' && SociAI.showToast) SociAI.showToast('Content approved!', 'success');
        }
      }).catch(() => { btn.disabled = false; });
    },

    rejectPost(id) {
      document.getElementById('rejectPostId').value = id;
      document.getElementById('rejectReason').value = '';
      SociAI.openModal('rejectModal');
    },

    confirmReject() {
      const id     = document.getElementById('rejectPostId').value;
      const reason = document.getElementById('rejectReason').value.trim() || 'No reason provided.';
      fetch('/dashboard/content/' + id + '/reject', {
        method:  'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
        body:    JSON.stringify({ _token: CSRF_TOKEN, reason }),
      }).then(r => r.json()).then(d => {
        if (d.success || d.message) {
          SociAI.closeModal('rejectModal');
          const card = document.querySelector(`.content-post-card[data-id="${id}"]`);
          if (card) {
            const statusBadge = card.querySelector('.badge');
            if (statusBadge) { statusBadge.className = 'badge badge-error'; statusBadge.textContent = '❌ Rejected'; }
            card.dataset.status = 'rejected';
          }
          if (typeof SociAI !== 'undefined' && SociAI.showToast) SociAI.showToast('Content rejected.', 'error');
        }
      });
    },

    schedulePost(id) {
      document.getElementById('schedulePostId').value = id;
      SociAI.openModal('scheduleModal');
    },

    confirmSchedule() {
      const id       = document.getElementById('schedulePostId').value;
      const platform = document.getElementById('schedulePlatform').value;
      const datetime = document.getElementById('scheduleDatetime').value;
      if (!datetime) { alert('Please select a date and time.'); return; }

      fetch('/dashboard/content/' + id + '/schedule', {
        method:  'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
        body:    JSON.stringify({ _token: CSRF_TOKEN, platform_account_id: platform, scheduled_at: datetime }),
      }).then(r => r.json()).then(d => {
        if (d.success || d.schedule_id) {
          SociAI.closeModal('scheduleModal');
          const card = document.querySelector(`.content-post-card[data-id="${id}"]`);
          if (card) {
            const statusBadge = card.querySelector('.badge');
            if (statusBadge) { statusBadge.className = 'badge badge-info'; statusBadge.textContent = '📅 Scheduled'; }
            card.dataset.status = 'scheduled';
            card.dataset.sched  = datetime;
          }
          if (typeof SociAI !== 'undefined' && SociAI.showToast) SociAI.showToast('Post scheduled!', 'success');
        }
      });
    },

    /* ── Bulk actions ── */
    clearSelection() {
      selectedIds.clear();
      document.querySelectorAll('.post-select-cb').forEach(cb => cb.checked = false);
      selectAllCb.checked = false;
      updateBulkBar();
    },

    bulkApprove() {
      if (!selectedIds.size || !confirm(`Approve ${selectedIds.size} content piece(s)?`)) return;
      this._bulkAction('approve');
    },

    bulkReject() {
      if (!selectedIds.size || !confirm(`Reject ${selectedIds.size} content piece(s)?`)) return;
      this._bulkAction('reject');
    },

    bulkDelete() {
      if (!selectedIds.size || !confirm(`Permanently delete ${selectedIds.size} content piece(s)? This cannot be undone.`)) return;
      this._bulkAction('delete');
    },

    _bulkAction(action) {
      fetch('/dashboard/content/bulk-' + action, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body:    JSON.stringify({ _token: CSRF_TOKEN, ids: [...selectedIds] }),
      }).then(r => r.json()).then(d => {
        if (d.success || d.message) {
          selectedIds.forEach(id => {
            const card = document.querySelector(`.content-post-card[data-id="${id}"]`);
            if (card) {
              if (action === 'delete') {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.95)';
                setTimeout(() => card.remove(), 300);
              } else {
                const badge = card.querySelector('.badge');
                if (badge && action === 'approve') { badge.className = 'badge badge-success'; badge.textContent = '✅ Approved'; }
                if (badge && action === 'reject')  { badge.className = 'badge badge-error';   badge.textContent = '❌ Rejected'; }
              }
            }
          });
          this.clearSelection();
          if (typeof SociAI !== 'undefined' && SociAI.showToast) SociAI.showToast(`Bulk ${action} complete.`, 'success');
        }
      }).catch(() => {
        if (typeof SociAI !== 'undefined' && SociAI.showToast) SociAI.showToast('Action failed. Please try again.', 'error');
      });
    },

    /* ── Calendar ── */
    calNav(dir) {
      if (dir === 0) {
        const now = new Date();
        calYear  = now.getFullYear();
        calMonth = now.getMonth() + 1;
      } else {
        calMonth += dir;
        if (calMonth > 12) { calMonth = 1; calYear++; }
        if (calMonth < 1)  { calMonth = 12; calYear--; }
      }
      this._fetchCalendarData(calYear, calMonth);
    },

    _fetchCalendarData(year, month) {
      // Update month label
      const dt = new Date(year, month - 1, 1);
      document.getElementById('calMonthLabel').textContent = dt.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

      // Count posts for the new month from local data
      const postsByDay = {};
      ALL_POSTS_DATA.forEach(p => {
        if (p.scheduled_at) {
          const d = new Date(p.scheduled_at);
          if (d.getFullYear() === year && d.getMonth() + 1 === month) {
            const day = d.getDate();
            postsByDay[day] = (postsByDay[day] ?? 0) + 1;
          }
        }
      });

      // Re-render calendar grid
      const today   = new Date();
      const daysInM = new Date(year, month, 0).getDate();
      const firstDow = new Date(year, month - 1, 1).getDay(); // 0=Sun
      // Convert Sun=0 to Mon-based: Mon=1…Sun=7 → offset
      const firstDowMon = firstDow === 0 ? 7 : firstDow; // ISO weekday

      const calGrid = document.getElementById('calGrid');
      let html = '';

      // Leading empty cells
      for (let e = 1; e < firstDowMon; e++) html += '<div class="cal-cell empty"></div>';

      for (let day = 1; day <= daysInM; day++) {
        const isToday  = (day === today.getDate() && month === today.getMonth()+1 && year === today.getFullYear());
        const cnt      = postsByDay[day] ?? 0;
        const dots     = Array.from({length: Math.min(cnt,3)}, () => '<span class="cal-dot" style="display:inline-block;margin-right:2px"></span>').join('');
        const overflow = cnt > 3 ? `<span style="font-size:0.65rem;color:var(--blue-light)">+${cnt-3}</span>` : '';
        html += `<div class="cal-cell ${isToday?'today':''} ${cnt>0?'has-post':''}"
                      onclick="ContentHub.showDayDetail(${year},${month},${day})"
                      title="${cnt} post${cnt!==1?'s':''} on ${dt.toLocaleDateString('en-US',{month:'short'})} ${day}">
                   <div class="cal-day-num" style="color:${isToday?'var(--blue-light)':'var(--text-secondary)'}">${day}</div>
                   ${cnt>0 ? `<div style="margin-top:3px">${dots}${overflow}</div>` : ''}
                 </div>`;
      }
      calGrid.innerHTML = html;
      document.getElementById('dayDetailPanel').style.display = 'none';
    },

    showDayDetail(year, month, day) {
      // Clear previous selection
      document.querySelectorAll('.cal-cell.selected').forEach(c => c.classList.remove('selected'));

      // Highlight selected cell (find by day number)
      const cells = document.querySelectorAll('.cal-cell:not(.empty)');
      cells.forEach(c => {
        const num = parseInt(c.querySelector('.cal-day-num')?.textContent);
        if (num === day) c.classList.add('selected');
      });

      const panel   = document.getElementById('dayDetailPanel');
      const title   = document.getElementById('dayDetailTitle');
      const content = document.getElementById('dayDetailContent');
      const dt      = new Date(year, month - 1, day);

      title.textContent = dt.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

      // Find posts for this day
      const dayPosts = ALL_POSTS_DATA.filter(p => {
        if (!p.scheduled_at) return false;
        const d = new Date(p.scheduled_at);
        return d.getFullYear() === year && d.getMonth()+1 === month && d.getDate() === day;
      });

      if (!dayPosts.length) {
        content.innerHTML = `
          <div style="grid-column:1/-1;text-align:center;padding:1.5rem">
            <div class="text-muted text-sm">No posts scheduled for this day.</div>
            <button class="btn btn-ghost mt-2" style="font-size:0.8rem" onclick="SociAI.openModal('createContentModal')">+ Schedule Content</button>
          </div>`;
      } else {
        content.innerHTML = dayPosts.map(p => {
          const pc  = PLATFORM_CONFIG[p.platform] ?? {label: p.platform, icon: '📱', color: 'var(--blue)'};
          const sc  = STATUS_CONFIG[p.status]     ?? {label: p.status, icon: '📝'};
          const txt = p.body_text.length > 100 ? p.body_text.slice(0, 100) + '…' : p.body_text;
          const t   = p.scheduled_at ? new Date(p.scheduled_at).toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'}) : '';
          return `
            <div class="glass-card" style="padding:0.85rem;border-color:${pc.color}22">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem">
                <span style="font-size:0.95rem">${pc.icon}</span>
                <span style="font-size:0.75rem;color:var(--text-muted)">${t}</span>
              </div>
              <div style="font-size:0.82rem;color:var(--text-secondary);line-height:1.6;margin-bottom:0.5rem">${escHtml(txt)}</div>
              <div style="display:flex;gap:0.35rem">
                <span class="badge badge-info" style="font-size:0.7rem">${pc.label}</span>
                <span class="badge ${p.status==='published'?'badge-purple':p.status==='scheduled'?'badge-info':'badge-neutral'}" style="font-size:0.7rem">${sc.icon} ${sc.label}</span>
              </div>
            </div>`;
        }).join('');
      }

      panel.style.display = '';
      panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    },
  };

  function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  /* ── Auto-open create modal if URL has ?action=create ── */
  if (new URLSearchParams(window.location.search).get('action') === 'create') {
    SociAI.openModal('createContentModal');
  }

})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
