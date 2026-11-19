<?php
/* ملف الـ PWA ديناميكي: الاسم والأيقونة من اسم التطبيق المضبوط (site_name) بدل اسم ثابت */
$name = trim((string)setting('site_name', '')) ?: t('لوحة النشر');
$short = mb_substr($name, 0, 14);
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$theme = '#2557e0';
echo json_encode([
    'name' => $name,
    'short_name' => $short,
    'start_url' => 'index.php?p=dashboard&src=pwa',
    'scope' => './',
    'display' => 'standalone',
    'background_color' => '#f5f7fb',
    'theme_color' => $theme,
    'dir' => 'auto',
    'lang' => function_exists('is_rtl') && is_rtl() ? 'ar' : 'en',
    'icons' => [
        ['src' => 'index.php?p=appicon&s=192', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => 'index.php?p=appicon&s=512', 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => 'index.php?p=appicon&s=512', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => t('شغل النهارده'), 'url' => 'index.php?p=prospects&stat=due'],
        ['name' => t('الرسائل'), 'url' => 'index.php?p=inbox'],
        ['name' => t('منشور جديد'), 'url' => 'index.php?p=compose'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit;
