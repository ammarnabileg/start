<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'];
    file_put_contents('saved_content.html', $content);
    header('Location: index.php');
    exit;
}
?>
