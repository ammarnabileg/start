<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'RecruitAI') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="h-full bg-gray-50 flex flex-col">

<?php
$user = Auth::user();
$currentPath = strtok($_SERVER['REQUEST_URI'], '?');
$userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$userInitials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));

$navLinks = [
    ['href' => '/candidate/dashboard',    'label' => 'Dashboard'],
    ['href' => '/candidate/jobs',         'label' => 'Browse Jobs'],
    ['href' => '/candidate/applications', 'label' => 'My Applications'],
    ['href' => '/candidate/offers',       'label' => 'Offers'],
    ['href' => '/candidate/profile',      'label' => 'Profile'],
];
?>

<!-- Top Navbar -->
<nav class="bg-white border-b border-gray-200 flex-shrink-0 sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <a href="/candidate/dashboard" class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="text-lg font-bold text-gray-900">RecruitAI</span>
            </a>

            <!-- Desktop nav links -->
            <div class="hidden md:flex items-center space-x-1">
                <?php foreach ($navLinks as $link): ?>
                    <?php $active = $currentPath === $link['href']; ?>
                    <a href="<?= $link['href'] ?>"
                       class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= $active ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' ?>">
                        <?= htmlspecialchars($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Right: avatar -->
            <div class="flex items-center space-x-3">
                <!-- Avatar dropdown -->
                <div class="relative">
                    <button id="avatar-btn" onclick="toggleAvatarDropdown()" class="flex items-center space-x-2 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-xs font-bold text-white">
                            <?= htmlspecialchars($userInitials) ?>
                        </div>
                        <span class="text-sm font-medium text-gray-700 hidden sm:block"><?= htmlspecialchars($userName) ?></span>
                        <svg class="w-4 h-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div id="avatar-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($userName) ?></p>
                            <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                        </div>
                        <a href="/candidate/profile" class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Profile
                        </a>
                        <a href="/logout" class="flex items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4 mr-3 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Logout
                        </a>
                    </div>
                </div>

                <!-- Mobile hamburger -->
                <button id="mobile-menu-btn" onclick="toggleMobileMenu()" class="md:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden md:hidden border-t border-gray-200 bg-white">
        <div class="px-4 py-3 space-y-1">
            <?php foreach ($navLinks as $link): ?>
                <?php $active = $currentPath === $link['href']; ?>
                <a href="<?= $link['href'] ?>"
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $active ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' ?>">
                    <?= htmlspecialchars($link['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<!-- Flash messages -->
<?php if (!empty($_SESSION['flash'])): ?>
    <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
    <div id="flash-messages" class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pt-4 space-y-2">
        <?php foreach ($flash as $type => $message): ?>
            <?php
            $styles = [
                'success' => 'bg-green-50 border-green-400 text-green-800',
                'error'   => 'bg-red-50 border-red-400 text-red-800',
                'info'    => 'bg-blue-50 border-blue-400 text-blue-800',
                'warning' => 'bg-yellow-50 border-yellow-400 text-yellow-800',
            ];
            $cls = $styles[$type] ?? $styles['info'];
            ?>
            <div class="border-l-4 p-3 rounded text-sm font-medium <?= $cls ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        setTimeout(function() {
            var el = document.getElementById('flash-messages');
            if (el) { el.style.transition = 'opacity 0.5s'; el.style.opacity = '0'; setTimeout(function(){ el.remove(); }, 500); }
        }, 4000);
    </script>
<?php endif; ?>

<!-- Main content -->
<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <?= $content ?>
</main>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <p class="text-center text-sm text-gray-400">&copy; <?= date('Y') ?> RecruitAI. All rights reserved.</p>
    </div>
</footer>

<script>
function toggleAvatarDropdown() {
    document.getElementById('avatar-dropdown').classList.toggle('hidden');
}
function toggleMobileMenu() {
    document.getElementById('mobile-menu').classList.toggle('hidden');
}
document.addEventListener('click', function(e) {
    var btn = document.getElementById('avatar-btn');
    var dropdown = document.getElementById('avatar-dropdown');
    if (btn && dropdown && !btn.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});
</script>
</body>
</html>
