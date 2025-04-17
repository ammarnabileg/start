<?php
ob_start(); // Output Buffering Start
session_start();

// Include connection and session files
include '../../connect.php';
include '../../Sessions.php';

// التأكد من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // استلام البيانات من النموذج
    $project_id = $_POST['project_id'];
    $project_name = $_POST['project_name'];
    $txt1 = $_POST['txt1'];
    $txt2 = $_POST['txt2'];
    $txt3 = $_POST['txt3'];
    $video1 = $_POST['video1'];
    $video2 = $_POST['video2'];
    $issold = $_POST['issold'];
    // ملفات الصور
    $project_logo = $_FILES['project_logo']['name'];
    $project_thumbnail = $_FILES['project_thumbnail']['name'];
    $project_photos = $_FILES['project_photos'];

    // تحديد المسار المناسب لرفع الملفات
    $upload_dir = "../../uploads/img/";
    $logo_path = !empty($project_logo) ? $upload_dir . basename($project_logo) : null;
    $thumbnail_path = !empty($project_thumbnail) ? $upload_dir . basename($project_thumbnail) : null;

    // رفع الملفات إلى المجلد
    if ($logo_path && !move_uploaded_file($_FILES['project_logo']['tmp_name'], $logo_path)) {
		$_SESSION['MSG_error']=$_SESSION['MSG_error']."حدث خطأ أثناء رفع شعار المشروع! "."<br>";
		header('Location:https://start.com.eg/cpanel.php?p=UpdateProject&id='.$project_id);   exit;         

    }

    if ($thumbnail_path && !move_uploaded_file($_FILES['project_thumbnail']['tmp_name'], $thumbnail_path)) {
		$_SESSION['MSG_error']=$_SESSION['MSG_error']."حدث خطأ أثناء رفع الصورة الرئيسية! "."<br>";
		header('Location:https://start.com.eg/cpanel.php?p=UpdateProject&id='.$project_id);   exit;         
    }

    // تحديث جدول المشاريع (projects)
    $sql = "UPDATE projects SET 
                projects_name = ?, 
                projects_txt1 = ?, 
                projects_txt2 = ?, 
                projects_txt3 = ?, 
                projects_video1 = ?, 
                projects_video2 = ?,
				projects_sold = ?";

    // إضافة شروط لتحديث الصور فقط إذا تم تحميلها
    $params = [$project_name, $txt1, $txt2, $txt3, $video1, $video2, $issold];

    if ($logo_path) {
        $sql .= ", projects_logo = ?";
        $params[] = $logo_path;
    }
    if ($thumbnail_path) {
        $sql .= ", projects_thumbnail = ?";
        $params[] = $thumbnail_path;
    }

    $sql .= " WHERE projects_id = ?";
    $params[] = $project_id;

    // تحضير وتنفيذ استعلام التحديث للمشروع
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
		$_SESSION['MSG_error']=$_SESSION['MSG_error']. "خطأ في إعداد الاستعلام: " . $conn->error."<br>";
		header('Location:https://start.com.eg/cpanel.php?p=UpdateProject&id='.$project_id);   exit;         
    }

    $stmt->bind_param(str_repeat('s', count($params)), ...$params);

    if ($stmt->execute()) {
		$_SESSION['MSG_success']="تم تحديث المشروع بنجاح.";
		header('Location:https://start.com.eg/cpanel.php?p=UpdateProject&id='.$project_id);   exit;         
    } else {
		$_SESSION['MSG_error']=$_SESSION['MSG_error']. "حدث خطأ أثناء تحديث المشروع: " . $conn->error."<br>";
		header('Location:https://start.com.eg/cpanel.php?p=UpdateProject&id='.$project_id);   exit;         
    }

    // رفع الصور الإضافية إلى جدول projects_photos
    if (!empty($project_photos['name'][0])) {
        foreach ($project_photos['name'] as $key => $photo_name) {
            $photo_tmp = $project_photos['tmp_name'][$key];
            $photo_path = $upload_dir . basename($photo_name);

            if (move_uploaded_file($photo_tmp, $photo_path)) {
                // إدخال الصور في جدول projects_photos
                $sql_photos = "INSERT INTO projects_photos (projects_photos_project_id, projects_photos_img, projects_photos_place) 
                               VALUES (?, ?, ?)";
                $place = $key + 1; // ترتيب الصورة
                $stmt_photos = $conn->prepare($sql_photos);
                if ($stmt_photos) {
                    $stmt_photos->bind_param('iss', $project_id, $photo_path, $place);
                    $stmt_photos->execute();
                } else {
		$_SESSION['MSG_error']=$_SESSION['MSG_error']."حدث خطأ أثناء إعداد استعلام الصور: " . $conn->error."<br>";
		header('Location:https://start.com.eg/cpanel.php?p=UpdateProject&id='.$project_id);   exit;         
                }
            } else {
		$_SESSION['MSG_error']=$_SESSION['MSG_error']."حدث خطأ أثناء رفع الصور الإضافية."."<br>";
		header('Location:https://start.com.eg/cpanel.php?p=UpdateProject&id='.$project_id);   exit;         
            }
        }
    }

    // إغلاق الاتصال
    $stmt->close();
    $conn->close();
} else {
		$_SESSION['MSG_error']=$_SESSION['MSG_error']. "طلب غير صالح."."<br>";
		header('Location:https://start.com.eg/cpanel.php?p=UpdateProject&id='.$project_id);   exit;         
}
?>
