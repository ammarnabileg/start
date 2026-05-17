<?php
// SociAI OS - Main Application Layout
$user       = $user ?? null;
$brand      = $activeBrand ?? $brand ?? null;
$brands     = $brands ?? [];
$pageTitle  = $pageTitle ?? 'SociAI OS';
$notifCount = $notifCount ?? 0;
$slug       = $brand['slug'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($user['preferred_language'] ?? 'en') ?>" dir="<?= ($user['preferred_language'] ?? 'en') === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SociAI OS - Enterprise AI Social Media Platform">
    <title><?= htmlspecialchars($pageTitle) ?> &mdash; <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_tokens'] ? array_key_first(array_filter($_SESSION['csrf_tokens'] ?? [], fn($v) => $v > time())) ?? '' : '') ?>">
</head>
<body class="app-layout">

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="/dashboard" class="brand-logo">
            <span class="logo-icon">⚡</span>
            <span class="logo-text"><?= APP_NAME ?></span>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <?php if (!empty($brands)): ?>
    <!-- Brand Switcher -->
    <div class="brand-switcher px-3 mb-3">
        <div class="dropdown">
            <button class="btn brand-selector w-100 d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                <?php if ($brand && $brand['logo_url']): ?>
                    <img src="<?= htmlspecialchars($brand['logo_url']) ?>" alt="" class="brand-logo-sm rounded">
                <?php else: ?>
                    <span class="brand-initial"><?= strtoupper(substr($brand['name'] ?? 'B', 0, 1)) ?></span>
                <?php endif; ?>
                <span class="brand-name text-truncate"><?= htmlspecialchars($brand['name'] ?? 'Select Brand') ?></span>
                <i class="bi bi-chevron-down ms-auto"></i>
            </button>
            <ul class="dropdown-menu w-100">
                <?php foreach ($brands as $b): ?>
                <li>
                    <a class="dropdown-item <?= ($brand['id'] ?? '') === $b['id'] ? 'active' : '' ?>"
                       href="/brands/<?= htmlspecialchars($b['slug']) ?>">
                        <?= htmlspecialchars($b['name']) ?>
                        <small class="text-muted d-block"><?= htmlspecialchars($b['user_role'] ?? '') ?></small>
                    </a>
                </li>
                <?php endforeach; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="/brands/create"><i class="bi bi-plus-circle me-2"></i>New Brand</a></li>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation -->
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="/dashboard" class="nav-link <?= $pageTitle === 'Dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i><span>Dashboard</span>
            </a>
        </li>
        <?php if ($slug): ?>
        <li class="nav-section-title">Content</li>
        <li class="nav-item">
            <a href="/brands/<?= $slug ?>/content" class="nav-link <?= str_contains($pageTitle, 'Content') ? 'active' : '' ?>">
                <i class="bi bi-file-text"></i><span>Content Library</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/brands/<?= $slug ?>/calendar" class="nav-link">
                <i class="bi bi-calendar3"></i><span>Calendar</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/brands/<?= $slug ?>/campaigns" class="nav-link">
                <i class="bi bi-megaphone"></i><span>Campaigns</span>
            </a>
        </li>

        <li class="nav-section-title">Intelligence</li>
        <li class="nav-item">
            <a href="/brands/<?= $slug ?>/agents" class="nav-link <?= str_contains($pageTitle, 'Agent') ? 'active' : '' ?>">
                <i class="bi bi-robot"></i><span>AI Agents</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/brands/<?= $slug ?>/analytics" class="nav-link">
                <i class="bi bi-bar-chart-line"></i><span>Analytics</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/brands/<?= $slug ?>/community" class="nav-link">
                <i class="bi bi-chat-dots"></i><span>Community</span>
            </a>
        </li>

        <li class="nav-section-title">Brand</li>
        <li class="nav-item">
            <a href="/brands/<?= $slug ?>/strategy" class="nav-link">
                <i class="bi bi-bullseye"></i><span>Strategy</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/brands/<?= $slug ?>/team" class="nav-link">
                <i class="bi bi-people"></i><span>Team</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/brands/<?= $slug ?>" class="nav-link">
                <i class="bi bi-gear"></i><span>Brand Settings</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="/profile" class="user-profile-link">
            <?php if (!empty($user['avatar_url'])): ?>
                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" class="avatar" alt="">
            <?php else: ?>
                <span class="avatar-initials"><?= strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'U', 0, 2)) ?></span>
            <?php endif; ?>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? $user['username'] ?? '') ?></span>
                <span class="user-email text-muted small"><?= htmlspecialchars($user['email'] ?? '') ?></span>
            </div>
        </a>
        <form method="POST" action="/auth/logout" class="d-inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? Auth::csrfToken()) ?>">
            <button type="submit" class="btn btn-link logout-btn" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
</nav>

<!-- Main Content -->
<main class="main-content" id="mainContent">

    <!-- Top Bar -->
    <header class="topbar">
        <button class="btn btn-sm sidebar-mobile-toggle" id="mobileSidebarToggle">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="topbar-title">
            <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <div class="topbar-actions ms-auto d-flex align-items-center gap-3">
            <?php if ($slug): ?>
            <a href="/brands/<?= $slug ?>/content/create" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>New Content
            </a>
            <?php endif; ?>
            <a href="/notifications" class="btn btn-light btn-sm position-relative">
                <i class="bi bi-bell fs-5"></i>
                <?php if ($notifCount > 0): ?>
                <span class="badge rounded-pill bg-danger notif-badge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php $flash = \SociAI\Core\Response::getFlash(); ?>
    <?php if (!empty($flash)): ?>
    <div class="flash-messages px-4 pt-3">
        <?php foreach ($flash as $type => $messages): ?>
        <?php foreach ($messages as $msg): ?>
        <div class="alert alert-<?= $type === 'error' ? 'danger' : ($type === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Page Content -->
    <div class="content-area p-4">
        <?= $content ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
