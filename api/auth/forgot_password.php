<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';
require_once '../../src/classes/User.php';
require_once '../../src/classes/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$turnstileResponse = $input['cf-turnstile-response'] ?? '';

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
    $verifyResult = file_get_contents($verifyUrl, false, $context);
    $json = json_decode($verifyResult, true);

    if (!$json['success']) {
        echo json_encode(['success' => false, 'message' => 'Turnstile verification failed']);
        exit;
    }
}

$db = (new Database())->connect();
$userClass = new User($db);

// Helper to fix the link in User.php if needed, or we rely on User.php's simple logic
// Since User.php is already updated with logic, we just call it.

$result = $userClass->initiatePasswordReset($email);

if ($result === true) {
    echo json_encode(['success' => true, 'message' => 'Nếu email tồn tại, một liên kết đặt lại mật khẩu đã được gửi.']);
} else {
    $errorMsg = is_string($result) ? $result : 'Có lỗi không xác định xảy ra khi gửi email.';
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $errorMsg]);
}
?>