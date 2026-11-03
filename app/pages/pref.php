<?php
/** تغيير اللغة أو وضع الألوان، والرجوع لنفس الصفحة */
if (isset($_GET['lang'])) {
    set_pref('lang', $_GET['lang'] === 'ar' ? 'ar' : 'en');
}
if (isset($_GET['theme'])) {
    set_pref('theme', $_GET['theme'] === 'dark' ? 'dark' : 'light');
}
$back = (string)($_SERVER['HTTP_REFERER'] ?? '');
$host = (string)parse_url($back, PHP_URL_HOST);
redirect($back !== '' && $host === ($_SERVER['HTTP_HOST'] ?? '') ? $back : url('dashboard'));
