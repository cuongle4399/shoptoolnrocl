<?php
// Simple license download endpoint for logged-in users
header('Content-Type: text/plain; charset=utf-8');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

require_once '../../config/database.php';
require_once '../../src/classes/License.php';
require_once '../../src/classes/User.php';
require_once '../../config.php';

$license_key = $_GET['license'] ?? '';
if (empty($license_key)) {
    http_response_code(400);
    echo 'Missing license';
    exit;
}

$database = new Database();
$db = $database->connect();
$licenseClass = new License($db);
$userClass = new User($db);

$key = $licenseClass->getKeyByLicense($license_key);
if (!$key) {
    http_response_code(404);
    echo 'License not found';
    exit;
}

// Check owner
$owner_ok = false;
if (!empty($key['user_info'])) {
    $ui = json_decode($key['user_info'], true);
    if (is_array($ui) && isset($ui['user_id']) && (int)$ui['user_id'] === (int)$_SESSION['user_id']) {
        $owner_ok = true;
    }
}
if (!$owner_ok && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (empty($key['hwid'])) {
    http_response_code(400);
    echo 'HWID not set for this license';
    exit;
}

// Compose file
$user_info = $key['user_info'] ?? ('User-' . ($_SESSION['user_id'] ?? '')); 
$hwid = $key['hwid'];
$license = $key['license_key'];
$timestamp = date('Y-m-d_H-i-s');
$filename = 'license_' . $license . '_' . $timestamp . '.txt';

$content = "User: " . $user_info . "\n";
$content .= "HWID: " . $hwid . "\n";
$content .= "License: " . $license . "\n";
$content .= "Generated: " . date('Y-m-d H:i:s') . "\n";

header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Optionally log
require_once '../../config.php';
logAudit('download_license', $user_info, 'Downloaded license file', 'success');

echo $content;
exit;