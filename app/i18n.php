<?php
/**
 * اللغات: النصوص مكتوبة بالعربي في الكود، وترجمتها الإنجليزي في app/lang/en.php.
 * الإنجليزي هو الأساسي. كل مستخدم يختار لغته ووضع الألوان، والاختيار بيتحفظ في الكوكيز.
 */

function lang(): string
{
    $l = $_COOKIE['lang'] ?? platform_default('default_lang', 'en');
    return $l === 'ar' ? 'ar' : 'en';
}

function theme(): string
{
    return ($_COOKIE['theme'] ?? platform_default('default_theme', 'light')) === 'dark' ? 'dark' : 'light';
}

/** الافتراضي اللي المدير اختاره من إعدادات النظام (لو البرنامج متسطب) */
function platform_default(string $name, string $fallback): string
{
    static $cache = [];
    if (!array_key_exists($name, $cache)) {
        $cache[$name] = $fallback;
        try {
            if (function_exists('is_installed') && is_installed()) {
                $cache[$name] = (string)setting($name, $fallback);
            }
        } catch (Throwable $t) {
        }
    }
    return $cache[$name];
}

function is_rtl(): bool
{
    return lang() === 'ar';
}

/** يترجم نص. لو مفيش ترجمة بيرجع النص الأصلي. يقبل %s للقيم المتغيرة. */
function t(string $s, ...$args): string
{
    static $maps = [];
    $l = lang();
    if (!isset($maps[$l])) {
        // الإنجليزي: en*.php. العربي: ar*.php (صياغة بالفصحى لنفس النصوص)
        $maps[$l] = [];
        foreach (glob(__DIR__ . '/lang/' . $l . '*.php') ?: [] as $f) {
            $maps[$l] += (array)(require $f);
        }
    }
    $s = $maps[$l][$s] ?? $s;
    return $args ? vsprintf($s, $args) : $s;
}

/** نص مترجم جاهز يتحط جوه JavaScript في attribute (زي confirm) */
function tjs(string $s, ...$args): string
{
    return e(addslashes(t($s, ...$args)));
}

function set_pref(string $name, string $value): void
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie($name, $value, ['expires' => time() + 31536000, 'path' => '/', 'secure' => $secure, 'httponly' => false, 'samesite' => 'Lax']);
    $_COOKIE[$name] = $value;
}
