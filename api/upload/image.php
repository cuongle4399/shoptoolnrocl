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

$supabaseUrl = SUPABASE_URL;
$bucket = SUPABASE_BUCKET;
$jwtSecret = getenv('JWT_SECRET') ?: JWT_SECRET;

// Create valid JWT token for Supabase with proper secret
$header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
$payload = json_encode([
    'role' => 'service_role',
    'iss' => 'supabase',
    'iat' => time(),
    'exp' => time() + 3600
]);

// Base64Url encode
$header64 = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
$payload64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
$signature = hash_hmac('sha256', "$header64.$payload64", $jwtSecret, true);
$signature64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
$jwtToken = "$header64.$payload64.$signature64";

error_log('Uploading with generated JWT token...');

$uploadUrl = $supabaseUrl . '/storage/v1/object/' . $bucket . '/' . $filename;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $uploadUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $jwtToken,
    'Content-Type: ' . $file['type']
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

error_log('Upload Response - HTTP ' . $httpCode);

if ($httpCode === 200) {
    error_log('Upload SUCCESS');
    $publicUrl = $supabaseUrl . '/storage/v1/object/public/' . $bucket . '/' . $filename;
    response('success', 'Image uploaded successfully', ['url' => $publicUrl]);
}

// Failed
$errorMsg = 'Upload failed';
if (!empty($response)) {
    $errorData = @json_decode($response, true);
    if ($errorData && isset($errorData['message'])) {
        $errorMsg = 'Upload failed: ' . $errorData['message'];
    } else {
        $errorMsg = 'Upload error: ' . substr($response, 0, 200);
    }
}

http_response_code(400);
response('error', $errorMsg);
?>
