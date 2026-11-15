<?php
/**
 * تصغير تلقائي للصور الكبيرة المرفوعة في أي مكان في المنصة (احتياطي على الخادم بعد التصغير في المتصفح).
 * أي صورة JPEG/PNG/WebP أكبر من 2 ميجا أو ضلعها أكبر من 2560px بتتصغر في مكانها قبل ما الصفحة تقراها.
 */
function upload_shrink_file(string $tmp): ?int
{
    if (!function_exists('imagecreatefromstring') || !is_file($tmp)) return null;
    $sz = (int)filesize($tmp); $info = @getimagesize($tmp); if (!$info) return null;
    [$w, $h, $type] = $info; $L = 2560;
    if ($sz <= 2 * 1048576 && max($w, $h) <= $L) return null;
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $w * $h > 60000000) return null;
    @ini_set('memory_limit', '512M');
    $src = @imagecreatefromstring((string)file_get_contents($tmp)); if (!$src) return null;
    $k = min(1, $L / max($w, $h)); $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
    $dst = $src;
    if ($k < 1) { $dst = imagecreatetruecolor($nw, $nh); imagealphablending($dst, false); imagesavealpha($dst, true); imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h); imagedestroy($src); }
    $out = $tmp . '.s';
    $ok = $type === IMAGETYPE_JPEG ? imagejpeg($dst, $out, 86) : ($type === IMAGETYPE_WEBP ? imagewebp($dst, $out, 86) : (imagesavealpha($dst, true) && imagepng($dst, $out, 9)));
    imagedestroy($dst);
    if (!$ok || !is_file($out) || filesize($out) >= $sz) { @unlink($out); return null; }
    @rename($out, $tmp); clearstatcache(true, $tmp); return (int)filesize($tmp);
}

function upload_shrink_all(): void
{
    foreach ($_FILES as $key => $f) {
        if (!isset($f['tmp_name'])) continue;
        if (is_array($f['tmp_name'])) {
            array_walk_recursive($_FILES[$key]['tmp_name'], function ($tmp, $i) use ($key) {
                // فقط مصفوفة بمستوى واحد (files[]) — الشائعة في المنصة
                if (is_string($tmp) && ($_FILES[$key]['error'][$i] ?? 1) === UPLOAD_ERR_OK && ($n = upload_shrink_file($tmp)) !== null) $_FILES[$key]['size'][$i] = $n;
            });
        } elseif (($f['error'] ?? 1) === UPLOAD_ERR_OK && ($n = upload_shrink_file((string)$f['tmp_name'])) !== null) $_FILES[$key]['size'] = $n;
    }
}
