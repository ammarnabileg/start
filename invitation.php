<?php
include 'connect.php';
include 'includes/languages/ar-php-master/src/Arabic.php';
include 'includes/languages/ar-php-master/src/I18N/Arabic/Glyphs.php';

// التحقق من وجود معرف الدعوة
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid invitation ID.");
}

$invitation_id = (int) $_GET['id']; // تحويل المعرف إلى عدد صحيح لحماية من SQL Injection

$result = $mysqli->query("SELECT * FROM events_invitations WHERE events_invitations_id = $invitation_id") or die($mysqli->error);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $events_invitations_name = $row["events_invitations_name"];
    $events_invitations_count = $row["events_invitations_count"];
}

header("Content-Type: image/jpeg");

// روابط الصور
$bg_url = "https://start.com.eg/assets/img/invitation.jpg";
$qr_url = "https://start.com.eg/qr.php?f=png&s=qr&d=https://start.com.eg/invitation_review.php?id=" . urlencode($invitation_id) . "&sf=8&w=350&h=350";

// تحميل الصور من الإنترنت
$bg_image = @imagecreatefromjpeg($bg_url);
$qr_image = @imagecreatefrompng($qr_url);
if (!$bg_image || !$qr_image) {
    die("Error loading images.");
}

// تحديد حجم QR
$qr_width = 350;
$qr_height = 350;
$qr_resized = imagecreatetruecolor($qr_width, $qr_height);
imagealphablending($qr_resized, false);
imagesavealpha($qr_resized, true);
imagecopyresampled($qr_resized, $qr_image, 0, 0, 0, 0, $qr_width, $qr_height, imagesx($qr_image), imagesy($qr_image));

// حساب أبعاد الصورة الخلفية
$bg_width = imagesx($bg_image);
$bg_height = imagesy($bg_image);

// تحديد موقع QR ليكون في منتصف الشاشة أفقيًا وعلى بعد 260px من الأعلى
$x_position = ($bg_width - $qr_width) / 2;
$y_position = 260;
imagecopy($bg_image, $qr_resized, $x_position, $y_position, 0, 0, $qr_width, $qr_height);

// إضافة النصوص
$font_file = __DIR__ . "/fonts/Cairo-SemiBold.ttf"; // تأكد من أن ملف الخط موجود
if (!file_exists($font_file)) {
    die("Error: Font file not found.");
}

$white_color = imagecolorallocate($bg_image, 255, 255, 255); // لون النص أبيض
$gold_color = imagecolorallocate($bg_image, 216, 173, 118); // لون النص ذهبي

// دالة لحساب تمركز النص
function center_text($bg_width, $text, $font_size, $font_file) {
    $bbox = imagettfbbox($font_size, 0, $font_file, $text);
    return ($bg_width - ($bbox[2] - $bbox[0])) / 2;
}

// النص الأول - اسم المدعو (يدعم العربية)
$text1 = mb_convert_encoding($events_invitations_name, "HTML-ENTITIES", "UTF-8");
$text1_font_size = 35;
$text_x1 = center_text($bg_width, $text1, $text1_font_size, $font_file);
$text_y1 = 670;
imagettftext($bg_image, $text1_font_size, 0, $text_x1, $text_y1, $gold_color, $font_file, $text1);

// النص الثاني - عدد المدعوين
$text2 = $events_invitations_count . " Guests";
$text2_font_size = 20;
$text_x2 = center_text($bg_width, $text2, $text2_font_size, $font_file);
$text_y2 = $text_y1 + 50; // مسافة 50px بين النصين
imagettftext($bg_image, $text2_font_size, 0, $text_x2, $text_y2, $white_color, $font_file, $text2);

// عرض الصورة
imagejpeg($bg_image);

// تنظيف الذاكرة
imagedestroy($bg_image);
imagedestroy($qr_image);
imagedestroy($qr_resized);
?>
