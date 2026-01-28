<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../src/classes/User.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    response('error', 'Không có quyền');
}

$database = new Database();
$db = $database->connect();

if (!$db) {
    http_response_code(500);
    response('error', 'Kết nối cơ sở dữ liệu thất bại');
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['old_password']) || empty($data['new_password'])) {
    http_response_code(400);
    response('error', 'Thiếu thông tin bắt buộc');
}

if (strlen($data['new_password']) < 6) {
    http_response_code(400);
    response('error', 'Mật khẩu phải ít nhất 6 ký tự');
}

$userClass = new User($db);
$result = $userClass->changePassword($_SESSION['user_id'], $data['old_password'], $data['new_password']);

if ($result) {
    response('success', 'Đã thay đổi mật khẩu thành công');
} else {
    http_response_code(400);
    response('error', 'Mật khẩu cũ không đúng hoặc lỗi cập nhật');
}
?>