<?php
/**
 * صور مصغّرة لكروت المنشورات والتقويم: الصفحة كانت بتحمّل الصورة الأصلية (ممكن كذا ميجا) عشان تعرضها في كارت صغير.
 * thumb_url() بيعمل رابط موقّع (مايشتغلش غير للصور اللي المنصة نفسها عرضتها)، وصفحة thumb بتنزّل الصورة مرة واحدة
 * من خلال safe_fetch (بيمنع العناوين الداخلية)، وتصغّرها، وتخزنها، وترجعها بكاش طويل في المتصفح.
 */

function thumb_key(): string
{
    static $k = null;
    if ($k === null) { $c = function_exists('app_config') ? app_config() : []; $k = (string)($c['app_key'] ?? ''); if ($k === '') { $k = (string)setting('thumb_key', ''); if ($k === '' && function_exists('save_setting')) { $k = bin2hex(random_bytes(24)); save_setting('thumb_key', $k); } } }
    return $k;
}

function thumb_sig(string $src, int $w): string
{
    return substr(hash_hmac('sha256', $w . '|' . $src, thumb_key()), 0, 20);
}

/** رابط الصورة المصغرة (ولو المكتبات مش موجودة بيرجع الأصلية زي ما هي) */
function thumb_url(string $src, int $w = 640): string
{
    if ($src === '' || !preg_match('~^https?://~i', $src) || !function_exists('imagecreatefromstring')) return $src;
    return url('thumb', ['u' => $src, 'w' => $w, 's' => thumb_sig($src, $w)]);
}

function thumb_serve(): void
{
    $src = (string)($_GET['u'] ?? ''); $w = (int)($_GET['w'] ?? 640); $sig = (string)($_GET['s'] ?? '');
    if (!in_array($w, [320, 640, 960], true) || !preg_match('~^https?://~i', $src) || !hash_equals(thumb_sig($src, $w), $sig)) { http_response_code(403); exit; }
    $dir = ROOT_DIR . '/uploads/cache/thumbs'; @mkdir($dir, 0750, true);
    $f = $dir . '/' . sha1($w . '|' . $src) . '.jpg';
    $send = function (string $file) { header('Content-Type: image/jpeg'); header('Cache-Control: private, max-age=2592000, immutable'); header('Content-Length: ' . filesize($file)); readfile($file); exit; };
    // تنظيف بسيط من وقت للتاني: المصغّرات اللي اتعملت من أكتر من 30 يوم (بتتعمل تاني لو اتطلبت)
    if (mt_rand(1, 300) === 1) { foreach (glob($dir . '/*') ?: [] as $old) if (@filemtime($old) < time() - 30 * 86400) @unlink($old); }
    if (is_file($f) && filesize($f) > 0) $send($f);
    $fail = $dir . '/' . sha1($w . '|' . $src) . '.fail';
    // لو التصغير فشل قبل كده (صورة مش مدعومة/الموقع رافض): نرجع للأصلية من غير ما نحاول كل مرة
    if (is_file($fail) && filemtime($fail) > time() - 86400) { header('Location: ' . $src, true, 302); exit; }
    if (function_exists('session_write_close')) session_write_close();
    $r = safe_fetch($src, ['max' => 15 * 1024 * 1024, 'accept' => '~image~i', 'timeout' => 20]);
    $img = empty($r['err']) ? @imagecreatefromstring((string)($r['body'] ?? '')) : false;
    if (!$img) { @touch($fail); header('Location: ' . $src, true, 302); exit; }
    $ow = imagesx($img); $oh = imagesy($img);
    if ($ow > $w) { $nh = (int)round($oh * $w / $ow); $t = imagecreatetruecolor($w, $nh); imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255)); imagecopyresampled($t, $img, 0, 0, 0, 0, $w, $nh, $ow, $oh); imagedestroy($img); $img = $t; }
    $tmp = $f . '.' . getmypid() . '.tmp';
    if (@imagejpeg($img, $tmp, 80)) @rename($tmp, $f);
    imagedestroy($img);
    if (is_file($f)) $send($f);
    header('Location: ' . $src, true, 302); exit;
}
