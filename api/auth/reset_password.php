<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../src/classes/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$password = $input['password'] ?? '';
$turnstileResponse = $input['cf-turnstile-response'] ?? '';

if (empty($token) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin']);
    exit;
}

// Verify Turnstile
$turnstileSecret = getenv('TURNSTILE_SECRET_KEY');
if ($turnstileSecret) {
    $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $turnstileSecret,
        'response' => $turnstileResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($verifyUrl, false, $context);
    $json = json_decode($result, true);
    if (!$json['success']) {
        echo json_encode(['success' => false, 'message' => 'Xác minh Turnstile thất bại']);
        exit;
    }
}

$db = (new Database())->connect();
$userClass = new User($db);

$success = $userClass->resetPasswordUsingToken($token, $password);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Đặt lại mật khẩu thành công.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Token không hợp lệ hoặc đã hết hạn.']);
}
?>