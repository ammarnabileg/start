<?php
$platform_name_footer = 'Discover';
$footer_text_custom = '';
try {
    $platform_name_footer = get_platform_setting('platform_name', 'Discover');
    $footer_text_custom = get_platform_setting('footer_text', '');
} catch(Exception $e) {}
?>
<footer class="mt-auto py-10 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-primary-600 to-accent-500 flex items-center justify-center">
          <span class="text-white font-bold text-sm">D</span>
        </div>
        <span class="font-black gradient-text"><?= e($platform_name_footer) ?></span>
        <span class="text-gray-400 dark:text-gray-600 text-sm"><?= $footer_text_custom ? e($footer_text_custom) : ('&copy; ' . date('Y') . ' ' . e($platform_name_footer) . '. All rights reserved.') ?></span>
      </div>
      <nav class="flex flex-wrap items-center justify-center gap-4 text-sm text-gray-500 dark:text-gray-400">
        <a href="/index.php" class="hover:text-primary-600 dark:hover:text-primary-400 transition-smooth">Discover</a>
        <a href="/create-community.php" class="hover:text-primary-600 dark:hover:text-primary-400 transition-smooth">Create Community</a>
        <a href="#" class="hover:text-primary-600 dark:hover:text-primary-400 transition-smooth">Privacy</a>
        <a href="#" class="hover:text-primary-600 dark:hover:text-primary-400 transition-smooth">Terms</a>
        <a href="#" class="hover:text-primary-600 dark:hover:text-primary-400 transition-smooth">Help</a>
      </nav>
      <div class="flex items-center gap-3">
        <span class="text-xs text-gray-400 dark:text-gray-600">Built with ❤️ for the Gulf</span>
      </div>
    </div>
  </div>
</footer>
</body>
</html>
