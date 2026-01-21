<?php
// ====================================================================
// API ENDPOINT: change_password.php
// Simple password change
// ====================================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/classes/User.php';
require_once __DIR__ . '/../includes/functions.php';

$input = json_decode(file_get_contents('php://input'), true);

$old_password = $input['old_password'] ?? null;
$new_password = $input['new_password'] ?? null;
$confirm_password = $input['confirm_password'] ?? null;

// Validate inputs
if (!$old_password || !$new_password || !$confirm_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin']);
    exit;
}

if (strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mật khẩu phải ít nhất 6 ký tự']);
    exit;
}

if ($new_password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mật khẩu không khớp']);
    exit;
}

try {
    // Get token from Authorization header
    $token = null;
    $headers = getallheaders() ?: [];
    
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'authorization') {
            $token = preg_replace('/^Bearer\s+/i', '', trim($v));
            break;
        }
    }

    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Verify JWT token
    $payload = verifyJWT($token);
    if (!$payload || !isset($payload['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token invalid']);
        exit;
    }

    $user_id = $payload['user_id'];
    
    // Connect to database
    $db = (new Database())->connect();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Get user
    $userClass = new User($db);
    $user = $userClass->getUserById($user_id);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Check old password (plaintext comparison)
    $stored_password = $user['password_'] ?? null;
    
    // Direct comparison since passwords are plaintext
    if ($old_password !== $stored_password) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Mật khẩu cũ không đúng']);
        exit;
    }

    // Update password
    $endpoint = 'users?id=eq.' . (int)$user_id;
    $result = $db->callApi($endpoint, 'PATCH', ['password_' => $new_password]);

    if ($result && ($result->code == 200 || $result->code == 204)) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Mật khẩu đã được thay đổi thành công'
        ]);
    } else {
        throw new Exception('Update failed');
    }

} catch (Exception $e) {
    error_log('change_password error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>


