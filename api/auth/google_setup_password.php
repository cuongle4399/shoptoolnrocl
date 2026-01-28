<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../src/classes/User.php';
require_once '../../includes/functions.php';

$response = ['success' => false, 'message' => 'Lỗi không xác định'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Phương thức không hợp lệ', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $google_id = $input['google_id'] ?? '';
    $name = $input['name'] ?? '';
    $avatar = $input['avatar'] ?? '';
    $password = $input['password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';

    if (empty($email) || empty($google_id) || empty($password)) {
        throw new Exception('Vui lòng nhập đầy đủ thông tin', 400);
    }

    if ($password !== $confirm_password) {
        throw new Exception('Mật khẩu nhập lại không khớp', 400);
    }

    if (strlen($password) < 6) {
        throw new Exception('Mật khẩu phải từ 6 ký tự trở lên', 400);
    }

    $db = (new Database())->connect();
    $userClass = new User($db);

    // 1. Double check if user already exists
    if ($userClass->getUserByEmail($email) || $userClass->getUserByGoogleId($google_id)) {
        throw new Exception('Tài khoản này đã tồn tại hoặc đã được thiết lập.', 400);
    }

    // 2. Create user
    $user = $userClass->completeGoogleRegistration($email, $google_id, $name, $avatar, $password);

    if ($user) {
        // 3. Start Session
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
            'message' => 'Đăng ký và thiết lập mật khẩu thành công',
            'user' => [
                'username' => $user['username'],
                'role' => $user['role'] ?? 'customer'
            ]
        ];
    } else {
        throw new Exception('Lỗi khi tạo tài khoản', 500);
    }

} catch (Throwable $e) {
    http_response_code($e->getCode() ?: 500);
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
