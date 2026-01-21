<?php
if (ob_get_level()) { @ob_clean(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
session_start();

require_once '../../config/constants.php';
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
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    response('error', 'Invalid file type');
}

if ($file['size'] > 5 * 1024 * 1024) { // 5MB
    http_response_code(400);
    response('error', 'File too large');
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$safeExt = preg_replace('/[^a-z0-9]/i', '', strtolower($ext));
$filename = bin2hex(random_bytes(8)) . ($safeExt ? '.' . $safeExt : '.jpg');

// Read file content
$fileContent = file_get_contents($file['tmp_name']);
if ($fileContent === false) {
    http_response_code(400);
    response('error', 'Failed to read uploaded file');
}

// Upload to Supabase Storage
$supabaseUrl = SUPABASE_URL;
$bucket = SUPABASE_BUCKET;
$apiKey = SUPABASE_ANON_KEY;

// Build Supabase Storage URL
$uploadUrl = $supabaseUrl . '/storage/v1/object/' . $bucket . '/' . urlencode($filename);

// Prepare curl request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $uploadUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: ' . $file['type']
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log('Supabase upload curl error: ' . $curlError);
    http_response_code(500);
    response('error', 'Upload failed (curl error)');
}

if ($httpCode !== 200) {
    error_log('Supabase upload HTTP ' . $httpCode . ': ' . $response);
    http_response_code(400);
    response('error', 'Supabase upload failed: HTTP ' . $httpCode);
}

// Build public URL
$publicUrl = $supabaseUrl . '/storage/v1/object/public/' . $bucket . '/' . urlencode($filename);

response('success', 'Image uploaded successfully', ['url' => $publicUrl]);
?>
