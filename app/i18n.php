<?php
/**
 * اللغات: النصوص مكتوبة بالعربي في الكود، وترجمتها الإنجليزي في app/lang/en.php.
 * الإنجليزي هو الأساسي. كل مستخدم يختار لغته ووضع الألوان، والاختيار بيتحفظ في الكوكيز.
 */

function lang(): string
{
    $l = $_COOKIE['lang'] ?? 'en';
    return $l === 'ar' ? 'ar' : 'en';
}

function theme(): string
{
    return ($_COOKIE['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
}

function is_rtl(): bool
{
    return lang() === 'ar';
}

/** يترجم نص. لو مفيش ترجمة بيرجع النص الأصلي. يقبل %s للقيم المتغيرة. */
function t(string $s, ...$args): string
{
    static $map = null;
    if (lang() === 'en') {
        if ($map === null) {
            // en.php الأساسي + ملف لكل جزء (en_analytics.php, en_whatsapp.php ...)
            $map = [];
            foreach (glob(__DIR__ . '/lang/en*.php') ?: [] as $f) {
                $map += (array)(require $f);
            }
        }
        $s = $map[$s] ?? $s;
    }
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
