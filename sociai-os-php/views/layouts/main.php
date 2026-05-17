<?php
// Layout variables with defaults
$pageTitle   = $pageTitle   ?? 'Dashboard';
$activePage  = $activePage  ?? 'dashboard';
$currentUser = $currentUser ?? ['name' => 'Ahmed Al-Rashid', 'email' => 'ahmed@brand.com', 'initials' => 'AA', 'role' => 'Admin'];
$brandName   = $brandName   ?? 'SociAI OS';
$appVersion  = $appVersion  ?? '1.0.0';

$nav = [
  ['id'=>'dashboard',   'label'=>'Dashboard',   'icon'=>'chart',    'href'=>'/dashboard',            'badge'=>null],
  ['id'=>'strategy',    'label'=>'Strategy',    'icon'=>'strategy', 'href'=>'/dashboard/strategy',   'badge'=>null],
  ['id'=>'content',     'label'=>'Content Hub', 'icon'=>'content',  'href'=>'/dashboard/content',    'badge'=>'24'],
  ['id'=>'copywriting', 'label'=>'Copywriting', 'icon'=>'pen',      'href'=>'/dashboard/copywriting','badge'=>null],
  ['id'=>'analytics',   'label'=>'Analytics',   'icon'=>'analytics','href'=>'/dashboard/analytics',  'badge'=>null],
  ['id'=>'campaigns',   'label'=>'Campaigns',   'icon'=>'campaign', 'href'=>'/dashboard/campaigns',  'badge'=>null],
  ['id'=>'community',   'label'=>'Community',   'icon'=>'community','href'=>'/dashboard/community',  'badge'=>'12'],
  ['id'=>'trends',      'label'=>'Trends',      'icon'=>'trends',   'href'=>'/dashboard/trends',     'badge'=>null],
  ['id'=>'agents',      'label'=>'AI Agents',   'icon'=>'agents',   'href'=>'/dashboard/agents',     'badge'=>null],
  ['id'=>'team',        'label'=>'Team',        'icon'=>'team',     'href'=>'/dashboard/team',       'badge'=>null],
  ['id'=>'settings',    'label'=>'Settings',    'icon'=>'settings', 'href'=>'/dashboard/settings',   'badge'=>null],
];

$icons = [
  'chart'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>',
  'strategy' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
  'content'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>',
  'pen'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
  'analytics'=> '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
  'campaign' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
  'community'=> '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
  'trends'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
  'agents'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
  'team'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
  'settings' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(bin2hex(random_bytes(16))) ?>">
  <title><?= htmlspecialchars($pageTitle) ?> — SociAI OS</title>
  <meta name="description" content="Enterprise AI Social Media Operating System">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/app.css">
  <style>
    body { font-family: 'Inter', system-ui, sans-serif; }
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
    .cal-header-cell { text-align: center; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); padding: 0.3rem; }
    .cal-cell { min-height: 50px; padding: 0.3rem; background: var(--glass-bg); border-radius: var(--radius-sm); cursor: pointer; transition: all 0.2s; position: relative; border: 1px solid transparent; }
    .cal-cell:hover { background: var(--glass-bg-hover); border-color: var(--glass-border); }
    .cal-cell.today { border-color: var(--blue); background: rgba(59,130,246,0.1); }
    .cal-cell.empty { background: transparent; cursor: default; }
    .cal-day-num { font-size: 0.75rem; font-weight: 600; }
    .cal-dot { width: 5px; height: 5px; background: var(--green); border-radius: 50%; display: block; margin-top: 3px; }
    .cal-cell.has-post::after { content: ''; position: absolute; bottom: 4px; right: 4px; width: 5px; height: 5px; background: var(--blue); border-radius: 50%; }
    .global-loader { position: fixed; top: 0; left: 0; right: 0; height: 2px; background: var(--gradient-primary); z-index: 9999; display: none; animation: shimmer 1.5s infinite; background-size: 200% 100%; }
  </style>
</head>
<body>
<div class="global-loader"></div>

<div class="app-shell">
  <!-- ── SIDEBAR ─────────────────────────────── -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">S</div>
      <span class="sidebar-logo-text"><span>Soci</span>AI OS</span>
    </div>

    <nav class="sidebar-nav">
      <div class="sidebar-section-label">Main</div>
      <?php foreach (array_slice($nav, 0, 6) as $item): ?>
      <div class="nav-item <?= $activePage === $item['id'] ? 'active' : '' ?>" data-href="<?= $item['href'] ?>">
        <span class="nav-icon"><?= $icons[$item['icon']] ?? '' ?></span>
        <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
        <?php if ($item['badge']): ?>
        <span class="nav-badge"><?= $item['badge'] ?></span>
        <?php endif ?>
      </div>
      <?php endforeach ?>

      <div class="sidebar-section-label" style="margin-top:0.75rem">AI Tools</div>
      <?php foreach (array_slice($nav, 6) as $item): ?>
      <div class="nav-item <?= $activePage === $item['id'] ? 'active' : '' ?>" data-href="<?= $item['href'] ?>">
        <span class="nav-icon"><?= $icons[$item['icon']] ?? '' ?></span>
        <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
        <?php if ($item['badge']): ?>
        <span class="nav-badge"><?= $item['badge'] ?></span>
        <?php endif ?>
      </div>
      <?php endforeach ?>
    </nav>

    <div class="sidebar-footer">
      <button class="sidebar-collapse-btn">
        <span class="collapse-icon">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        </span>
        <span class="nav-label">Collapse</span>
      </button>
    </div>
  </aside>

  <!-- ── MAIN CONTENT ──────────────────────── -->
  <div class="main-content" id="mainContent">

    <!-- TOP NAV -->
    <header class="topnav">
      <button class="icon-btn mobile-menu-btn" style="display:none" id="mobileMenuBtn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>

      <div class="search-bar">
        <span class="search-icon">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </span>
        <input type="text" placeholder="Search pages, agents, content...">
      </div>

      <div class="topnav-actions">
        <!-- Lang Toggle -->
        <div class="lang-toggle">
          <button class="lang-btn active" data-lang="en">EN</button>
          <button class="lang-btn" data-lang="ar">AR</button>
        </div>

        <!-- Theme Toggle -->
        <button class="icon-btn theme-toggle" title="Toggle theme">
          <span class="theme-icon">🌙</span>
        </button>

        <!-- Quick Generate -->
        <button class="btn-quick-gen" data-modal="quickGenerateModal">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          <span>AI Generate</span>
        </button>

        <!-- Notification Bell -->
        <div class="icon-btn notif-bell" style="position:relative">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
          <span class="notif-dot"></span>

          <div class="notif-dropdown">
            <div class="notif-header">
              <h4>Notifications</h4>
              <span class="mark-all-read">Mark all read</span>
            </div>
            <div class="notification-item unread">
              <div class="notif-icon-wrap" style="background:rgba(16,185,129,0.15)">🤖</div>
              <div class="notif-body">
                <p>AI Agent completed 47 community replies</p>
                <span>2 minutes ago</span>
              </div>
            </div>
            <div class="notification-item unread">
              <div class="notif-icon-wrap" style="background:rgba(59,130,246,0.15)">🔥</div>
              <div class="notif-body">
                <p>New viral trend detected: #AICreators</p>
                <span>15 minutes ago</span>
              </div>
            </div>
            <div class="notification-item unread">
              <div class="notif-icon-wrap" style="background:rgba(245,158,11,0.15)">📅</div>
              <div class="notif-body">
                <p>3 posts scheduled for today at 2:00 PM</p>
                <span>1 hour ago</span>
              </div>
            </div>
            <div class="notification-item">
              <div class="notif-icon-wrap" style="background:rgba(139,92,246,0.15)">📊</div>
              <div class="notif-body">
                <p>Weekly analytics report is ready</p>
                <span>3 hours ago</span>
              </div>
            </div>
            <div class="notification-item">
              <div class="notif-icon-wrap" style="background:rgba(236,72,153,0.15)">❤️</div>
              <div class="notif-body">
                <p>LinkedIn post reached 10K impressions</p>
                <span>Yesterday</span>
              </div>
            </div>
            <div style="padding:0.75rem 1.25rem; text-align:center; border-top:1px solid var(--glass-border)">
              <a href="/dashboard/notifications" style="font-size:0.8rem; color:var(--blue-light)">View all notifications</a>
            </div>
          </div>
        </div>

        <!-- User Menu -->
        <div class="user-menu" style="position:relative">
          <div class="user-avatar"><?= htmlspecialchars($currentUser['initials']) ?></div>
          <span class="user-name"><?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?></span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>

          <div class="user-dropdown">
            <div style="padding:0.75rem 1rem; border-bottom:1px solid var(--glass-border)">
              <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($currentUser['name']) ?></div>
              <div style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($currentUser['email']) ?></div>
            </div>
            <a href="/dashboard/settings">⚙️ Settings</a>
            <a href="/dashboard/team">👥 Team</a>
            <a href="/dashboard/analytics">📊 Analytics</a>
            <hr>
            <a href="/auth/logout" style="color:#FC8181">🚪 Sign Out</a>
          </div>
        </div>
      </div>
    </header>

    <!-- PAGE CONTENT -->
    <main class="page-content">
      <?= $content ?? '' ?>
    </main>

    <!-- FOOTER -->
    <footer style="padding:1rem 2rem; border-top:1px solid var(--glass-border); display:flex; align-items:center; justify-content:space-between; font-size:0.75rem; color:var(--text-muted)">
      <span>© <?= date('Y') ?> <?= htmlspecialchars($brandName) ?> · All rights reserved</span>
      <span>v<?= htmlspecialchars($appVersion) ?> · Enterprise Edition</span>
    </footer>
  </div>
</div>

<!-- QUICK GENERATE MODAL -->
<div class="modal-overlay" id="quickGenerateModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>✨ AI Quick Generate</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="form-group">
      <label class="form-label">Content Type</label>
      <select class="form-select" id="qgType">
        <option value="caption">Social Caption</option>
        <option value="linkedin">LinkedIn Post</option>
        <option value="thread">Twitter Thread</option>
        <option value="script">Video Script</option>
        <option value="hook">Hook / Opening</option>
        <option value="cta">Call to Action</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Topic / Brief</label>
      <textarea class="form-textarea" id="qgTopic" placeholder="Describe your topic, product, or idea..." rows="3"></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Platform</label>
      <select class="form-select" id="qgPlatform">
        <option>LinkedIn</option><option>Instagram</option><option>TikTok</option>
        <option>Twitter/X</option><option>Facebook</option><option>YouTube</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="SociAI.closeModal('quickGenerateModal')">Cancel</button>
      <button class="btn btn-primary" onclick="SociAI.generateContent(document.getElementById('qgType').value, {}); SociAI.closeModal('quickGenerateModal'); window.location.href='/dashboard/copywriting'">
        ✨ Generate Now
      </button>
    </div>
  </div>
</div>

<script src="/assets/js/app.js"></script>
<style>
  @media(max-width:900px){
    #mobileMenuBtn{display:flex!important}
  }
</style>
</body>
</html>
