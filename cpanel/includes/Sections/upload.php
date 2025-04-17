<?php
session_start();
require_once '../../../connect.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح']);
    exit();
}

// التحقق من وجود ملف
if (!isset($_FILES['upload'])) {
    http_response_code(400);
    echo json_encode(['error' => 'لم يتم اختيار ملف']);
    exit();
}

$file = $_FILES['upload'];
$fileName = time() . '_' . basename($file['name']);
$targetPath = '../../../uploads/editor_images/' . $fileName;

// إنشاء المجلد إذا لم يكن موجوداً
if (!file_exists('../../../uploads/editor_images/')) {
    mkdir('../../../uploads/editor_images/', 0777, true);
}

// التحقق من نوع الملف
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp', 'image/tiff'];
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'نوع الملف غير مسموح به']);
    exit();
}

// نقل الملف
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    $url = '/uploads/editor_images/' . $fileName;
    echo json_encode([
        'url' => $url,
        'uploaded' => 1,
        'fileName' => $fileName
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ أثناء رفع الملف']);
}
?> 