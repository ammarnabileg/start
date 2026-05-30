<?php
$platform_name_footer = 'Discover';
$footer_text_custom = '';
try {
 $platform_name_footer = get_platform_setting('platform_name', 'Discover');
 $footer_text_custom = get_platform_setting('footer_text', '');
} catch(Exception $e) {}
?>
<footer class="border-t border-gray-200 dark:border-white/10 mt-16 py-8 bg-white dark:bg-[#121212]">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500 dark:text-gray-500">
 <div class="flex items-center gap-2">
 <div class="w-6 h-6 rounded-lg bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center">
 <span class="text-white font-bold text-xs"><?= strtoupper(substr($platform_name_footer, 0, 1)) ?></span>
 </div>
 <span class="text-gray-400 dark:text-gray-600">
 <?= $footer_text_custom ? e($footer_text_custom) : ('&copy; ' . date('Y') . ' ' . e($platform_name_footer) . '. All rights reserved.') ?>
 </span>
 </div>
 <nav class="flex items-center gap-6">
 <a href="/index.php" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">Discover</a>
 <a href="/create-community.php" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">Create</a>
 <a href="#" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">Privacy</a>
 <a href="#" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">Terms</a>
 <a href="#" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">Support</a>
 </nav>
 </div>
 </div>
</footer>
</body>
</html>
