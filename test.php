<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'connect.php';

// ✅ تحميل الدعوة
if (!isset($_GET['id']) || empty($_GET['id'])) {
 die("Invalid invitation ID.");
}
$invitation_id = (int) $_GET['id'];
$result = $mysqli->query("SELECT * FROM events_invitations WHERE events_invitations_id = $invitation_id");

if (!$result || $result->num_rows == 0) {
 die("Invitation not found.");
}

$row = $result->fetch_assoc();
$events_invitations_name = $row["events_invitations_name"];
$events_invitations_count = $row["events_invitations_count"];

// ✅ مسار الخط
$font_path = "fonts/Cairo-SemiBold.ttf";
if (!file_exists($font_path)) {
 die("Error: Font file not found.");
}

// ✅ تحميل صورة الخلفية
$bg_url = "https://start.com.eg/assets/img/invitation1.jpg";
$bg_image = @imagecreatefromjpeg($bg_url);
if (!$bg_image) {
 die("Error: Could not load background image.");
}

// ✅ تحميل صورة QR
$qr_image_path = "https://start.com.eg/qr.php?f=png&s=qr&d=https://start.com.eg/invitation_review.php?id=".$invitation_id."&sf=8&w=225&h=225";
$qr_image = @imagecreatefrompng($qr_image_path);
if (!$qr_image) {
 die("Error: Could not load QR image.");
}

// ✅ إنشاء النصوص كـ PNG شفاف باستخدام SVG
function createTextImage($text1, $text2, $font_path) {
 $svg_file = "/var/www/vhosts/start.com.eg/httpdocs/temp_text.svg";
 $png_file = "/var/www/vhosts/start.com.eg/httpdocs/temp_text.png";

 $svg_content = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="150">
 <style>
 @font-face {
 font-family: 'Cairo-SemiBold';
 src: url('{$font_path}') format('truetype');
 }
 text {
 font-family: 'Cairo-SemiBold';
 fill: black;
 text-anchor: middle;
 alignment-baseline: middle;
 }
 </style>
 <text x="50%" y="40%" font-size="50">{$text1}</text>
 <text x="50%" y="80%" font-size="40">عدد الضيوف: {$text2}</text>
</svg>
SVG;

 file_put_contents($svg_file, $svg_content);

 // ✅ تحويل SVG إلى PNG باستخدام Imagick
 $imagick = new Imagick();
 $imagick->readImage($svg_file);
 $imagick->setImageFormat("png");
 $imagick->setImageBackgroundColor('transparent');
 $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
 $imagick->writeImage($png_file);

 if (!file_exists($png_file)) {
 die("Error: Could not convert SVG to PNG.");
 }

 return $png_file;
}

// ✅ إنشاء النصوص
$text_image_path = createTextImage($events_invitations_name, $events_invitations_count, $font_path);
$text_image = @imagecreatefrompng($text_image_path);
if (!$text_image) {
 die("Error: Could not load text image.");
}

// ✅ دمج النص في الصورة
$bg_width = imagesx($bg_image);
$text_width = imagesx($text_image);
$text_height = imagesy($text_image);
$text_x = ($bg_width - $text_width) / 2;
$text_y = 850;
imagecopy($bg_image, $text_image, $text_x, $text_y, 0, 0, $text_width, $text_height);

// ✅ دمج QR في موضعه
$qr_width = imagesx($qr_image);
$qr_height = imagesy($qr_image);
$qr_x_position = ($bg_width - $qr_width) / 2;
$qr_y_position = 260;
//imagecopy($bg_image, $qr_image, $qr_x_position, $qr_y_position, 0, 0, $qr_width, $qr_height);
imagecopy($bg_image, $qr_image, 88, 1313, 0, 0, 225, 225);

// ✅ تصدير الصورة النهائية
header("Content-Type: image/png");
imagepng($bg_image);

// ✅ تنظيف الذاكرة
imagedestroy($bg_image);
imagedestroy($text_image);
imagedestroy($qr_image);
unlink($text_image_path);
?>
