<?php
/* أيقونة التطبيق ديناميكية: نفس شعار الشريط الجانبي (تدرّج الهوية + أول حرف من اسم التطبيق) */
$sz = (int)($_GET['s'] ?? 192); if ($sz < 16 || $sz > 1024) $sz = 192;
$name = trim((string)setting('site_name', '')) ?: t('لوحة النشر');
$letter = mb_substr($name, 0, 1, 'UTF-8');
header('Content-Type: image/png'); header('Cache-Control: public, max-age=86400');
// أيقونة مرفوعة من إعدادات المنصة لها الأولوية
$up = ROOT_DIR . '/uploads/branding/app-icon.png';
if (setting('app_icon', '') === '1' && is_file($up) && function_exists('imagecreatefromstring')) {
    $src = @imagecreatefromstring((string)file_get_contents($up));
    if ($src) {
        $sw = imagesx($src); $sh = imagesy($src); $out = imagecreatetruecolor($sz, $sz); imagesavealpha($out, true); imagealphablending($out, false); imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127)); imagealphablending($out, true);
        $k = max($sz / $sw, $sz / $sh); $nw = (int)round($sw * $k); $nh = (int)round($sh * $k); // cover
        imagecopyresampled($out, $src, (int)(($sz - $nw) / 2), (int)(($sz - $nh) / 2), 0, 0, $nw, $nh, $sw, $sh);
        imagepng($out); imagedestroy($src); imagedestroy($out); exit;
    }
}
if (!function_exists('imagecreatetruecolor')) { http_response_code(404); exit; }
$im = imagecreatetruecolor($sz, $sz); imagesavealpha($im, true); imagealphablending($im, false);
// تدرّج قطري: brand-600 (37,87,224) → cyan-500 (6,182,212) → lime-400 (132,216,31)
$stops = [[0.0, 37, 87, 224], [0.55, 6, 182, 212], [1.0, 132, 216, 31]];
$lerp = function ($t) use ($stops) { for ($i = 1; $i < count($stops); $i++) { if ($t <= $stops[$i][0]) { $a = $stops[$i - 1]; $b = $stops[$i]; $f = ($t - $a[0]) / max(0.0001, $b[0] - $a[0]); return [(int)($a[1] + ($b[1] - $a[1]) * $f), (int)($a[2] + ($b[2] - $a[2]) * $f), (int)($a[3] + ($b[3] - $a[3]) * $f)]; } } return [$stops[count($stops) - 1][1], $stops[count($stops) - 1][2], $stops[count($stops) - 1][3]]; };
for ($y = 0; $y < $sz; $y++) for ($x = 0; $x < $sz; $x++) { $t = ($x + $y) / (2 * ($sz - 1)); [$r, $g, $b] = $lerp($t); imagesetpixel($im, $x, $y, imagecolorallocate($im, $r, $g, $b)); }
// زوايا مدوّرة (قناع ألفا)
$rad = (int)round($sz * 0.22); $trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
$corner = function ($cx, $cy, $x0, $y0, $x1, $y1) use ($im, $rad, $trans) { for ($y = $y0; $y < $y1; $y++) for ($x = $x0; $x < $x1; $x++) if ((($x - $cx) ** 2 + ($y - $cy) ** 2) > $rad * $rad) imagesetpixel($im, $x, $y, $trans); };
$corner($rad, $rad, 0, 0, $rad, $rad); $corner($sz - $rad - 1, $rad, $sz - $rad, 0, $sz, $rad);
$corner($rad, $sz - $rad - 1, 0, $sz - $rad, $rad, $sz); $corner($sz - $rad - 1, $sz - $rad - 1, $sz - $rad, $sz - $rad, $sz, $sz);
// الحرف أبيض في النص
imagealphablending($im, true);
$white = imagecolorallocate($im, 255, 255, 255);
$isArabic = (bool)preg_match('/\x{0600}-\x{06FF}/u', $letter) || (bool)preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u', $letter);
$cands = $isArabic ? [ROOT_DIR . '/assets/fonts/app.ttf', '/usr/share/fonts/opentype/unifont/unifont.otf', '/usr/share/fonts/opentype/unifont/unifont_jp.otf'] : [ROOT_DIR . '/assets/fonts/app.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'];
$font = null; foreach ($cands as $ff) if (is_file($ff)) { $font = $ff; break; }
if ($font && function_exists('imagettftext')) {
    $fs = (int)round($sz * ($isArabic ? 0.46 : 0.5)); $bb = imagettfbbox($fs, 0, $font, $letter); $tw = $bb[2] - $bb[0]; $th = $bb[1] - $bb[7];
    $x = (int)round(($sz - $tw) / 2 - $bb[0]); $y = (int)round(($sz + $th) / 2 - $bb[1]);
    imagettftext($im, $fs, 0, $x, $y, $white, $font, $letter);
} else { $m = (int)($sz * 0.32); imagefilledrectangle($im, $m, $m, $sz - $m, $sz - $m, $white); }
imagepng($im); imagedestroy($im); exit;
