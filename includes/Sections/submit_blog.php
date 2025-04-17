<?php
ob_start(); // Output Buffering Start
session_start();

//conect
include '../../connect.php';
include '../../Sessions.php';



// التحقق مما إذا كان النموذج قد تم إرساله
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // الحصول على البيانات من النموذج
    $title = mysqli_real_escape_string($conn, $_POST['project_name']);
    $content = mysqli_real_escape_string($conn, $_POST['txt1']);

    // تحميل الصورة
    $image = $_FILES['project_logo']['name'];
    $target_dir = "../../uploads/blog/";
    $target_file = $target_dir . basename($image);
    
    // التحقق من رفع الصورة
    if (move_uploaded_file($_FILES['project_logo']['tmp_name'], $target_file)) {
        // استعلام إدخال البيانات في الجدول
        $sql = "INSERT INTO blog_posts (blog_posts_title, blog_posts_img, blog_posts_text) 
                VALUES ('$title', '$target_file', '$content')";
        
        // تنفيذ الاستعلام والتحقق من النجاح
        if (mysqli_query($conn, $sql)) {
            echo "تم إضافة المقالة بنجاح!";
        } else {
            echo "حدث خطأ: " . mysqli_error($conn);
        }
    } else {
        echo "عذرًا، حدث خطأ أثناء رفع الصورة.";
    }

    // إغلاق الاتصال بقاعدة البيانات
    mysqli_close($conn);
}
?>
