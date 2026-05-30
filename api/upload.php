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

$type = $_POST['type'] ?? '';
$file = $_FILES['file'] ?? null;

if (!$file) {
    echo json_encode(['error' => 'No file in request', 'debug' => $_FILES]);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => 'File too large (server limit)',
        UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit)',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by extension',
    ];
    echo json_encode(['error' => $upload_errors[$file['error']] ?? 'Upload error code: ' . $file['error']]);
    exit;
}

// Validate image type using finfo
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed_types)) {
    echo json_encode(['error' => 'Only images allowed. Detected: ' . $mime]);
    exit;
}

// Max 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['error' => 'File too large (max 5MB). Size: ' . round($file['size']/1024/1024, 2) . 'MB']);
    exit;
}

// Map type to subdirectory
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

// Build absolute path — uploads/ is at web root (one level above api/)
$uploads_root = realpath(__DIR__ . '/..') . '/uploads';
$upload_base  = $uploads_root . '/' . $dir . '/';

// Create directory if needed
if (!is_dir($upload_base)) {
    if (!mkdir($upload_base, 0755, true)) {
        echo json_encode(['error' => 'Cannot create upload directory: ' . $upload_base]);
        exit;
    }
}

// Check writeable
if (!is_writable($upload_base)) {
    echo json_encode(['error' => 'Upload directory not writable: ' . $upload_base]);
    exit;
}

// Generate unique filename
$ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$ext      = $ext_map[$mime] ?? 'jpg';
$filename = $current_user['id'] . '_' . uniqid('', true) . '.' . $ext;
$dest     = $upload_base . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['error' => 'move_uploaded_file failed. Dest: ' . $dest]);
    exit;
}

$url = '/uploads/' . $dir . '/' . $filename;
echo json_encode(['success' => true, 'url' => $url]);
