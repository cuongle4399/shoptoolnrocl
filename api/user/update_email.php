<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../src/classes/User.php';
require_once '../../includes/functions.php';

// Check auth
$headers = getallheaders();
// Check auth
session_start();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        $payload = verifyJWT($token);
        if ($payload) {
            $userId = $payload['user_id'];
        }
    }
}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$newEmail = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($newEmail) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
    exit;
}

if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
    exit;
}

$db = (new Database())->connect();
$userClass = new User($db);

$user = $userClass->getUserById($userId);
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Verify password
if (!verifyPassword($password, $user['password_'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng']);
    exit;
}

// Update email
$updateResult = $userClass->updateEmail($userId, $newEmail);
if ($updateResult === true) {
    echo json_encode(['success' => true, 'message' => 'Cập nhật email thành công']);
} else {
    // If updateEmail returns a string, it's an error message (like "Email already in use")
    echo json_encode(['success' => false, 'message' => is_string($updateResult) ? $updateResult : 'Cập nhật thất bại']);
}
?>