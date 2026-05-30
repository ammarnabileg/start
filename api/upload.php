<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$current_user = get_auth_user();
if (!$current_user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_POST['type'] ?? ''; // avatar, community_logo, community_banner, post_image, site_logo, og_image
$file = $_FILES['file'] ?? null;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

// Validate image
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed_types)) {
    echo json_encode(['error' => 'Only images allowed (jpg, png, gif, webp)']);
    exit;
}

// Max 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['error' => 'File too large (max 5MB)']);
    exit;
}

// Determine directory based on type
$dirs = [
    'avatar'           => 'avatars',
    'community_logo'   => 'community/logos',
    'community_banner' => 'community/banners',
    'post_image'       => 'posts',
    'site_logo'        => 'site',
    'og_image'         => 'site',
    'favicon'          => 'site',
];
$dir = $dirs[$type] ?? 'misc';
$upload_base = __DIR__ . '/../uploads/' . $dir . '/';

// Create directory if not exists
if (!is_dir($upload_base)) {
    mkdir($upload_base, 0755, true);
}

// Generate unique filename
$ext = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    default      => 'jpg'
};
$filename = uniqid($current_user['id'] . '_', true) . '.' . $ext;
$dest = $upload_base . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['error' => 'Upload failed']);
    exit;
}

$url = '/uploads/' . $dir . '/' . $filename;
echo json_encode(['success' => true, 'url' => $url]);
