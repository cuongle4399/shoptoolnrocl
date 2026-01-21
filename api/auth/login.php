<?php
header('Content-Type: application/json');
session_start();

error_log("=== LOGIN API CALLED ===");

require_once '../../config/database.php';
require_once '../../src/classes/User.php';
require_once '../../includes/functions.php';

try {
    $database = new Database();
    $db = $database->connect();
    
    if (!$db) {
        error_log("Database connection returned null");
        http_response_code(500);
        response('error', 'Database connection failed');
    }
    
    error_log("Database connected successfully");
    
    // Get input
    $input = file_get_contents("php://input");
    error_log("Login attempt received");
    
    $data = json_decode($input, true);
    
    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        response('error', 'Missing username or password');
    }
    
    error_log("Attempting login for user: " . $data['username']);
    
    $userClass = new User($db);
    $user = $userClass->login($data['username'], $data['password']);
    
    error_log("Login result: " . (is_string($user) ? $user : ($user ? "User found" : "User not found")));
    
    // Handle disabled accounts explicitly
    if ($user === 'disabled') {
        error_log("Login blocked: account disabled for user " . $data['username']);
        http_response_code(403);
        response('error', 'Tài khoản đã bị vô hiệu hóa. Liên hệ quản trị để biết thêm chi tiết.');
    }
    
    if ($user) {
        error_log("Login successful for user id: " . ($user['id'] ?? 'unknown'));

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        // Generate JWT token
        $secret = JWT_SECRET; // Use secure secret from environment
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + (30 * 24 * 60 * 60) // 30 days
        ]);
        
        $header_encoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payload_encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $header_encoded . '.' . $payload_encoded, $secret, true);
        $signature_encoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $token = $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;

        // Redirect based on role
        $redirect = ($user['role'] === 'admin') ? 
            '/ShopToolNro/views/admin/dashboard.php' : 
            '/ShopToolNro/views/pages/index.php';

        response('success', 'Đăng nhập thành công', [
            'token' => $token,
            'redirect' => $redirect,
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);
    } else {
        http_response_code(401);
        response('error', 'Tên đăng nhập hoặc mật khẩu không đúng');
    }
    
} catch (Exception $e) {
    error_log("Login handler exception: " . $e->getMessage());
    http_response_code(500);
    response('error', 'Server error');
}
?>
