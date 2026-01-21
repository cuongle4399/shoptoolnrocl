<?php
header('Content-Type: application/json');
session_start();

require_once '../../config/database.php';
require_once '../../src/classes/User.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->connect();

if (!$db) {
    http_response_code(500);
    response('error', 'Database connection failed');
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['username']) || empty($data['email']) || empty($data['password']) || empty($data['confirm_password'])) {
    http_response_code(400);
    response('error', 'Missing required fields');
}

if ($data['password'] !== $data['confirm_password']) {
    http_response_code(400);
    response('error', 'Mật khẩu không khớp');
}

if (strlen($data['password']) < 8 || !preg_match('/[A-Za-z]/', $data['password']) || !preg_match('/[0-9]/', $data['password'])) {
    http_response_code(400);
    response('error', 'Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ và số');
}

$userClass = new User($db);

// Check if username exists
$existingUser = $userClass->getUserByUsername($data['username']);
if ($existingUser) {
    http_response_code(400);
    response('error', 'Tên đăng nhập đã tồn tại');
}

// Create user (password will be hashed by createUser)
$userData = [
    'username' => $data['username'],
    'email' => $data['email'],
    'password' => $data['password']
];

if ($userClass->createUser($userData)) {
    response('success', 'Đăng ký thành công! Vui lòng đăng nhập.');
} else {
    http_response_code(400);
    response('error', 'Không thể tạo tài khoản');
}
?>

