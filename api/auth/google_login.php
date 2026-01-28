<?php
/**
 * Google Login API
 * Optimized for robustness and debugging
 */

// 1. Start buffering immediately to catch any stray output
ob_start();

// 2. Configure error handling
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 3. Set JSON header
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Lỗi không xác định'];

try {
    $root = dirname(dirname(__DIR__));

    // 4. Load dependencies
    if (!file_exists($root . '/vendor/autoload.php')) {
        throw new Exception('Hệ thống thiếu thư viện (Vendor). Vui lòng cài đặt composer.', 500);
    }
    require_once $root . '/vendor/autoload.php';

    // Load configs
    $configFiles = [
        $root . '/config/constants.php',
        $root . '/config/database.php',
        $root . '/src/classes/User.php',
        $root . '/includes/functions.php'
    ];

    foreach ($configFiles as $file) {
        if (file_exists($file)) {
            require_once $file;
        }
    }

    // 5. Validate Request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Phương thức không hợp lệ', 405);
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dữ liệu JSON không hợp lệ: ' . json_last_error_msg(), 400);
    }

    $credential = $input['credential'] ?? '';
    if (empty($credential)) {
        throw new Exception('Thiếu mã xác thực Google (Token)', 400);
    }

    // 6. Get Client ID
    $clientId = getenv('GOOGLE_CLIENT_ID');
    if (!$clientId && defined('GOOGLE_CLIENT_ID')) {
        $clientId = GOOGLE_CLIENT_ID;
    }

    if (!$clientId) {
        throw new Exception('Lỗi cấu hình Server: Thiếu GOOGLE_CLIENT_ID', 500);
    }

    // 7. Verify Google Token
    if (!class_exists('Google\Client')) {
        throw new Exception('Lỗi Server: Google Client SDK chưa được tải.', 500);
    }

    try {
        $client = new Google\Client(['client_id' => $clientId]);

        // Fix for Local Environment (XAMPP) SSL Issues
        if (class_exists('GuzzleHttp\Client')) {
            $httpClient = new GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 30.0,        // Increased for mobile compatibility
                'connect_timeout' => 15.0  // Increased for mobile compatibility
            ]);
            $client->setHttpClient($httpClient);
        }

        $payload = $client->verifyIdToken($credential);
    } catch (Throwable $e) {
        error_log("Google Token Verification Failed: " . $e->getMessage());
        throw new Exception('Xác thực Google thất bại: ' . $e->getMessage(), 401);
    }

    if (!$payload) {
        throw new Exception('Token Google không hợp lệ hoặc đã hết hạn.', 401);
    }

    // 8. Extract Info
    $googleId = $payload['sub'] ?? '';
    $email = $payload['email'] ?? '';
    $name = $payload['name'] ?? explode('@', $email)[0];
    $picture = $payload['picture'] ?? '';

    if (empty($email) || empty($googleId)) {
        throw new Exception('Không lấy được thông tin Email từ Google.', 400);
    }

    // 9. Database Ops
    if (!class_exists('Database')) {
        throw new Exception('Lỗi hệ thống: Không tìm thấy lớp Database.', 500);
    }

    $dbClass = new Database();
    $db = $dbClass->connect();
    if (!$db) {
        throw new Exception('Lỗi kết nối CSDL.', 500);
    }

    if (!class_exists('User')) {
        throw new Exception('Lỗi hệ thống: Không tìm thấy lớp User.', 500);
    }

    $userClass = new User($db);
    $userResult = $userClass->createOrLinkGoogleUser($email, $googleId, $name, $picture);

    if ($userResult['status'] === 'setup_required') {
        // Clear any previous output (warnings, etc.)
        if (ob_get_length())
            ob_clean();

        echo json_encode([
            'success' => true,
            'action' => 'setup_required',
            'google_info' => $userResult['google_info'],
            'message' => 'Vui lòng đặt mật khẩu cho tài khoản mới của bạn'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user = $userResult['user'];

    if ($user) {
        if (isset($user['status']) && $user['status'] !== 'active') {
            throw new Exception('Tài khoản này đã bị khóa.', 403);
        }

        // 10. Start Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'customer';
        $_SESSION['logged_in'] = true;

        if (!empty($user['avatar_url'])) {
            $_SESSION['user_avatar'] = $user['avatar_url'];
        }

        $response = [
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'user' => [
                'username' => $user['username'],
                'role' => $user['role'] ?? 'customer',
                'avatar' => $user['avatar_url'] ?? $picture
            ]
        ];
    } else {
        throw new Exception('Không thể xác thực thông tin tài khoản Google.', 401);
    }

} catch (Throwable $e) {
    // Clear any previous output (warnings, etc.)
    if (ob_get_length())
        ob_clean();

    $code = $e->getCode();
    if ($code < 100 || $code > 599)
        $code = 500;
    http_response_code($code);

    error_log("Google Login Failed [{$code}]: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());

    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'code' => $code,
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ];
}

// 11. Final output
if (ob_get_length()) {
    $junk = ob_get_clean();
    if (!empty(trim($junk))) {
        error_log("Google Login caught unexpected output: " . $junk);
    }
} else {
    // Just in case ob_start was never called (should not happen)
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;