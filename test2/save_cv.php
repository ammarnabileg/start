<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $major = $_POST['major'];
    $skills = $_POST['skills'];
    $experience = $_POST['experience'];

    echo "<!DOCTYPE html><html lang='ar' dir='rtl'><head><meta charset='UTF-8'><title>السيرة الذاتية</title>
    <link href='https://fonts.googleapis.com/css2?family=Alexandria&display=swap' rel='stylesheet'>
    <style>body { font-family: 'Alexandria', sans-serif; padding: 2rem; } h1 { color: #0077b6; } .cv { border: 1px solid #ccc; padding: 2rem; border-radius: 10px; }</style>
    </head><body><div class='cv'><h1>$name</h1><p><strong>البريد:</strong> $email</p><p><strong>الهاتف:</strong> $phone</p><p><strong>التخصص:</strong> $major</p><p><strong>المهارات:</strong><br>$skills</p><p><strong>الخبرات:</strong><br>$experience</p></div></body></html>";
}
?>