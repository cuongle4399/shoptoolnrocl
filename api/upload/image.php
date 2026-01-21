<?php
if (ob_get_level()) { @ob_clean(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
session_start();

require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    response('error', 'Unauthorized');
}

if (!isset($_FILES['image'])) {
    http_response_code(400);
    response('error', 'No image file provided');
}

$file = $_FILES['image'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    response('error', 'Invalid file type');
}

if ($file['size'] > 5 * 1024 * 1024) { // 5MB
    http_response_code(400);
    response('error', 'File too large');
}

$upload_dir = __DIR__ . '/../../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$filename = uniqid() . '_' . basename($file['name']);
$filepath = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $url = '/ShopToolNro/uploads/' . $filename;
    response('success', 'Image uploaded successfully', ['url' => $url]);
} else {
    http_response_code(400);
    response('error', 'Failed to upload file');
}
?>
