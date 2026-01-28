<?php
// Start output buffering immediately to catch any stray warnings/text
ob_start();

// Disable error display (log to file instead)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Ensure JSON header
header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    // Load local config paths
    $root = dirname(dirname(__DIR__));

    // Manual require order to ensure dependencies are loaded
    require_once $root . '/vendor/autoload.php'; // Load Composer Autoloader FIRST

    if (file_exists($root . '/config/constants.php'))
        require_once $root . '/config/constants.php';
    if (file_exists($root . '/config/database.php'))
        require_once $root . '/config/database.php';
    if (file_exists($root . '/src/classes/User.php'))
        require_once $root . '/src/classes/User.php';
    if (file_exists($root . '/includes/functions.php'))
        require_once $root . '/includes/functions.php';

    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method Not Allowed', 405);
    }

    // Verify Google Client Library
    if (!class_exists('Google\Client')) {
        throw new Exception('Lỗi Server: Google Client Library (SDK) chưa được cài đặt. Vui lòng chạy "composer install" trên máy chủ.', 500);
    }

    // Get input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input', 400);
    }

    $credential = $input['credential'] ?? '';
    if (empty($credential)) {
        throw new Exception('Missing credential token', 400);
    }

    // Check Configuration
    $clientId = getenv('GOOGLE_CLIENT_ID');
    if (!$clientId && defined('GOOGLE_CLIENT_ID'))
        $clientId = GOOGLE_CLIENT_ID;

    if (!$clientId) {
        throw new Exception('Server Config Error: GOOGLE_CLIENT_ID missing', 500);
    }

    // Verify Google Token
    try {
        $client = new Google\Client(['client_id' => $clientId]);

        // DEVELOPMENT FIX: Disable SSL Verify for Guzzle Client in Local Environment
        $httpClient = new GuzzleHttp\Client(['verify' => false]);
        $client->setHttpClient($httpClient);

        $payload = $client->verifyIdToken($credential);
    } catch (Exception $e) {
        throw new Exception('Google Verification Error: ' . $e->getMessage(), 401);
    }

    if (!$payload) {
        throw new Exception('Invalid Google Token', 401);
    }

    // Extract User Info
    $googleId = $payload['sub'];
    $email = $payload['email'];
    $name = $payload['name'] ?? explode('@', $email)[0];
    $picture = $payload['picture'] ?? '';

    // Database Operations
    $db = (new Database())->connect();
    if (!$db) {
        throw new Exception('Lỗi hệ thống: Không thể kết nối cơ sở dữ liệu để đăng nhập Google.', 500);
    }

    $userClass = new User($db);

    // Create or Link User
    $user = $userClass->createOrLinkGoogleUser($email, $googleId, $name, $picture);

    if ($user) {
        if ((isset($user['status']) && $user['status'] !== 'active')) {
            throw new Exception('Tài khoản của bạn đã bị khóa.', 403);
        }

        // Init Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'customer';
        $_SESSION['logged_in'] = true;

        if (isset($user['avatar_url'])) {
            $_SESSION['user_avatar'] = $user['avatar_url'];
        }

        $response = [
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'user' => [
                'username' => $user['username'],
                'role' => $user['role'],
                'avatar' => $user['avatar_url'] ?? $picture
            ]
        ];
    } else {
        throw new Exception('Không thể tạo hoặc cập nhật người dùng.', 500);
    }

} catch (Exception $e) {
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 500;
    }
    http_response_code($code);

    // Log detailed error for admin
    error_log("Google Login Exception [Code $code]: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

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

// Clean buffer and output JSON
ob_end_clean();
echo json_encode($response);
exit;
?>