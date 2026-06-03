<?php
require_once dirname(__DIR__) . '/includes/config.php';
pi_require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Upload error']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['error' => 'File too large (max 5MB)']);
    exit;
}

$uploads_dir = dirname(__DIR__) . '/uploads/';
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

$fname = 'art_' . time() . '_' . rand(100, 999) . '.' . $ext;
$dest  = $uploads_dir . $fname;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

echo json_encode(['location' => '../uploads/' . $fname]);
