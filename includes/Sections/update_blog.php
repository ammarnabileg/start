<?php
ob_start(); // Output Buffering Start
session_start();

// Include connection and session files
include '../../connect.php';
include '../../Sessions.php';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Get the data from the form
    $post_id = isset($_POST['blog_posts_id']) ? intval($_POST['blog_posts_id']) : 0;
    $title = mysqli_real_escape_string($conn, $_POST['project_name']);
    $content = mysqli_real_escape_string($conn, $_POST['txt1']);

    // Initialize variables
    $image = $_FILES['project_logo']['name'];
    $image_path = "";

    // Check if a new image was uploaded
    if (!empty($image)) {
        $target_dir = "../../uploads/blog/";
        $target_file = $target_dir . basename($image);

        // Check if the upload directory exists
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true); // Create the directory if it doesn't exist
        }

        // Try to upload the file
        if (move_uploaded_file($_FILES['project_logo']['tmp_name'], $target_file)) {
            $image_path = $target_file; // Set the path to be stored in the database
        } else {
            echo "عذرًا، حدث خطأ أثناء رفع الصورة.";
            exit;
        }
    }

    // Check if post_id is valid before proceeding
    if ($post_id > 0) {
        // Construct the SQL query
        if (!empty($image_path)) {
            // Update with a new image
            $sql = "UPDATE blog_posts 
                    SET blog_posts_title = '$title', blog_posts_img = '$image_path', blog_posts_text = '$content' 
                    WHERE blog_posts_id = $post_id";
        } else {
            // Update without changing the image
            $sql = "UPDATE blog_posts 
                    SET blog_posts_title = '$title', blog_posts_text = '$content' 
                    WHERE blog_posts_id = $post_id";
        }

        // Execute the query and check for success
        if (mysqli_query($conn, $sql)) {
            echo "تم تحديث المقالة بنجاح!";
        } else {
            echo "حدث خطأ: " . mysqli_error($conn);
        }
    } else {
        echo "حدث خطأ: معرّف المقالة غير صالح.";
    }

    // Close the database connection
    mysqli_close($conn);
}
?>
