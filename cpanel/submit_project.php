<?php
ob_start(); // Output Buffering Start
session_start();
	
//conect
include '../../connect.php';
include '../../Sessions.php';



// جمع بيانات الـ form
$project_name = $_POST['project_name'];
$project_subtitle = $_POST['project_subtitle'];
$txt1 = $_POST['txt1'];
$txt2 = $_POST['txt2'];
$video1 = $_POST['video1'];
$video2 = $_POST['video2'];

// السماح فقط بأنواع ملفات الصور
$allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');

// دالة للتحقق من نوع الملف
function checkFileType($file_name, $allowed_extensions) {
    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
    return in_array(strtolower($file_extension), $allowed_extensions);
}

// رفع الشعار والصورة الرئيسية
$project_logo = $_FILES['project_logo']['name'];
$project_thumbnail = $_FILES['project_thumbnail']['name'];

// التحقق من نوع الملفات المرفوعة
if (!checkFileType($project_logo, $allowed_extensions) || !checkFileType($project_thumbnail, $allowed_extensions)) {
    die("خطأ: يجب رفع ملفات صور فقط!");
}

// رفع الملفات بعد التحقق
move_uploaded_file($_FILES['project_logo']['tmp_name'], "uploads/".time()."_".$project_logo);
move_uploaded_file($_FILES['project_thumbnail']['tmp_name'], "uploads/".time()."_".$project_thumbnail);

// إدخال المشروع في جدول projects
$sql = "INSERT INTO projects (projects_name, projects_logo, projects_thumbnail, projects_txt1, projects_txt2, projects_video1, projects_video2) 
        VALUES ('$project_name', '$project_logo', '$project_thumbnail', '$txt1', '$txt2', '$video1', '$video2')";

if ($conn->query($sql) === TRUE) {
    $project_id = $conn->insert_id;  // الحصول على الـ ID الخاص بالمشروع الجديد

    // التعامل مع الصور الإضافية
    if (!empty($_FILES['project_photos']['name'][0])) {
        $photos = $_FILES['project_photos'];
        for ($i = 0; $i < count($photos['name']); $i++) {
            $photo_name = $photos['name'][$i];
            $photo_tmp = $photos['tmp_name'][$i];

            // التحقق من نوع الصور الإضافية
            if (!checkFileType($photo_name, $allowed_extensions)) {
                die("خطأ: يجب رفع ملفات صور فقط!");
            }

            // رفع الصور بعد التحقق
            $unique_photo_name = time() . "_" . $photo_name;  // إعادة تسمية الصورة باسم فريد
            move_uploaded_file($photo_tmp, "uploads/".$unique_photo_name);

            // إضافة كل صورة في جدول `projects_photos`
            $photo_place = "صورة إضافية";  // يمكن تغيير هذا النص حسب الموقع
            $sql_photos = "INSERT INTO projects_photos (projects_photos_project_id, projects_photos_place, projects_photos_img) 
                           VALUES ('$project_id', '$photo_place', '$unique_photo_name')";
            $conn->query($sql_photos);
        }
    }

    // رسالة النجاح
    echo "تم إضافة المشروع بنجاح!";
} else {
    echo "خطأ: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>
