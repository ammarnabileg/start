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
<body class="h-full bg-gray-50">

<?php if (!empty($_SESSION['flash'])): ?>
    <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
    <div id="flash-message" class="fixed top-4 left-1/2 -translate-x-1/2 z-50 w-full max-w-sm">
        <?php foreach ($flash as $type => $message): ?>
            <?php
            $colors = [
                'success' => 'bg-green-50 border-green-400 text-green-800',
                'error'   => 'bg-red-50 border-red-400 text-red-800',
                'info'    => 'bg-blue-50 border-blue-400 text-blue-800',
                'warning' => 'bg-yellow-50 border-yellow-400 text-yellow-800',
            ];
            $cls = $colors[$type] ?? $colors['info'];
            ?>
            <div class="border-l-4 p-4 rounded shadow-md mb-2 <?= $cls ?>">
                <p class="text-sm font-medium"><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        setTimeout(function() {
            var el = document.getElementById('flash-message');
            if (el) { el.style.transition = 'opacity 0.5s'; el.style.opacity = '0'; setTimeout(function(){ el.remove(); }, 500); }
        }, 4000);
    </script>
<?php endif; ?>

<div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="flex justify-center mb-6">
            <a href="/" class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="text-2xl font-bold text-gray-900">RecruitAI</span>
            </a>
        </div>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow-xl rounded-2xl sm:px-10 border border-gray-100">
            <?= $content ?>
        </div>
    </div>

    <p class="mt-8 text-center text-xs text-gray-400">
        &copy; <?= date('Y') ?> RecruitAI. All rights reserved.
    </p>
</div>

</body>
</html>
