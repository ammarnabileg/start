<?php
if ($_FILES['image']['error'] === 0) {
    $folder = 'uploads/';
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($_FILES['image']['type'], $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'نوع الملف غير مدعوم.']);
        exit;
    }

    if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'حجم الملف أكبر من 2MB.']);
        exit;
    }

    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $path = $folder . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
        echo json_encode(['url' => $path]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'فشل في رفع الصورة.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'لم يتم رفع ملف.']);
}
?>
