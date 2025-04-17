<?php
session_start();
require_once '../../../connect.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// التحقق من وجود البيانات المطلوبة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['project_name'] ?? '';
    $content = $_POST['txt1'] ?? '';
    
    // التحقق من وجود صورة المقالة
    if (isset($_FILES['project_logo']) && $_FILES['project_logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['project_logo'];
        $fileName = time() . '_' . basename($file['name']);
        $targetPath = '../../../uploads/blog_images/' . $fileName;
        
        // إنشاء المجلد إذا لم يكن موجوداً
        if (!file_exists('../../../uploads/blog_images/')) {
            mkdir('../../../uploads/blog_images/', 0777, true);
        }
        
        // نقل الصورة إلى المجلد المحدد
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $imagePath = 'uploads/blog_images/' . $fileName;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء رفع الصورة';
            header('Location: ../NewArticle.php');
            exit();
        }
    } else {
        $_SESSION['error'] = 'يرجى اختيار صورة للمقالة';
        header('Location: ../NewArticle.php');
        exit();
    }
    
    try {
        // إدخال البيانات في قاعدة البيانات
        $stmt = $conn->prepare("INSERT INTO blog_posts (title, content, image_path, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$title, $content, $imagePath, $_SESSION['user_id']]);
        
        $_SESSION['success'] = 'تم إضافة المقالة بنجاح';
        header('Location: ../blog.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'حدث خطأ أثناء حفظ المقالة';
        header('Location: ../NewArticle.php');
        exit();
    }
} else {
    header('Location: ../NewArticle.php');
    exit();
}
?> 